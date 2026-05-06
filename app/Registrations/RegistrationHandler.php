<?php
namespace VW\Registrations;

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

use VW\Mails\MailService;
use VW\Events\EventRepository;

/**
 * Modul: RegistrationHandler
 * Verarbeitet die POST-Daten der Anmeldung und die GET-Anfragen der Stornierung.
 * Steuert die Logik für Status-Zuweisung (Warteliste vs. Bestätigt).
 */
class RegistrationHandler {

    public function register() {
        // Anmeldung (Frontend)
        add_action('admin_post_nopriv_vw_submit_registration', [$this, 'handle_registration']);
        add_action('admin_post_vw_submit_registration', [$this, 'handle_registration']);

        // Stornierung (via E-Mail Link)
        add_action('admin_post_nopriv_vw_cancel_registration', [$this, 'handle_cancellation']);
        add_action('admin_post_vw_cancel_registration', [$this, 'handle_cancellation']);
    }

    /**
     * Verarbeitet das Absenden des Anmeldeformulars.
     */
    public function handle_registration() {
        // 1. Sicherheit & CSRF-Schutz
        if (!isset($_POST['vw_reg_nonce_field']) || !wp_verify_nonce($_POST['vw_reg_nonce_field'], 'vw_reg_nonce_action')) {
            wp_die('Sicherheits-Check fehlgeschlagen.');
        }
        
        $repo       = new EventRepository();
        $event_id   = intval($_POST['event_id']); 
        $email      = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name  = sanitize_text_field($_POST['last_name']);
        $guests     = max(0, intval($_POST['guests']));
        $message    = isset($_POST['message']) ? sanitize_textarea_field($_POST['message']) : '';
        $total_seats = $guests + 1;

        $redirect_base = get_permalink($event_id) ?: home_url();

        // 2. Validierung
        if (empty($first_name) || empty($last_name) || !is_email($email)) {
            wp_redirect(add_query_arg('reg_error', 'invalid_data', $redirect_base));
            exit;
        }

        // Regex für Namen (erlaubt Umlaute, Leerzeichen, Bindestriche)
        $name_pattern = '/^[a-zA-ZäöüÄÖÜß\s-]+$/u';
        if (!preg_match($name_pattern, $first_name) || !preg_match($name_pattern, $last_name)) {
            wp_redirect(add_query_arg('reg_error', 'invalid_name', $redirect_base));
            exit;
        }

        // 3. Person-Handling (Existiert die E-Mail schon?)
        $p_id = $repo->get_or_create_person_id($first_name, $last_name, $email);
        $person = $repo->get_person($p_id);

        // 4. Status-Entscheidung
        $stats = $repo->get_registration_stats($event_id);
        $is_trusted = ($person && $person->trust_status === 'freigegeben');

        // Prüfen, ob noch Platz ist
        if ($stats['max'] > 0 && ($stats['current'] + $total_seats) > $stats['max']) {
            $final_status  = 'warteliste';
            $redirect_type = 'waitlist';
        } else {
            // "bestätigt" nur bei vertrauenswürdigen Personen, sonst "eingegangen"
            $final_status  = $is_trusted ? 'bestätigt' : 'eingegangen';
            $redirect_type = 'ok';
        }

        // 5. Eintrag in die Datenbank
        $reg_id = $repo->create_registration([
            'event_post_id' => $event_id, 
            'person_id'     => $p_id, 
            'guest_count'   => $guests,
            'message'       => $message,
            'seats_total'   => $total_seats, 
            'status'        => $final_status,
            'registered_at' => current_time('mysql')
        ]);

        // 6. E-Mail Versand via MailService
        if ($reg_id) {
            // Mapping der Slugs passend zum MailTemplateManager
            if ($final_status === 'warteliste') {
                $mail_slug = 'warteliste-info';
            } elseif ($final_status === 'bestätigt') {
                $mail_slug = 'anmeldebestaetigung';
            } else {
                // Dies triggert das neue 'eingangsbestaetigung' Template
                $mail_slug = 'eingangsbestaetigung';
            }
            
            // Sende Mail an Teilnehmer
            MailService::send_registration_mail($reg_id, $mail_slug);

            // Benachrichtigung an Admin, wenn manuelle Freigabe nötig
            if ($final_status === 'eingegangen') {
                MailService::send_registration_mail($reg_id, 'freigabe-info');
            }
        }

        wp_redirect(add_query_arg('reg', $redirect_type, $redirect_base)); 
        exit;
    }

    /**
     * Verarbeitet Stornierungs-Links aus E-Mails.
     * URL-Format: admin-post.php?action=vw_cancel_registration&id=123&hash=xyz
     */
    public function handle_cancellation() {
        $reg_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $hash   = isset($_GET['hash']) ? sanitize_text_field($_GET['hash']) : '';
        
        if (!$reg_id || !$hash) {
            wp_die('Ungültiger Aufruf.');
        }

        global $wpdb;
        $repo = new EventRepository();

        // Registrierung mit E-Mail laden für Hash-Vergleich
        $reg = $wpdb->get_row($wpdb->prepare("
            SELECT r.id, p.email, r.event_post_id, r.status 
            FROM {$wpdb->prefix}vw_registrations r 
            JOIN {$wpdb->prefix}vw_persons p ON r.person_id = p.id 
            WHERE r.id = %d", $reg_id
        ));

        // Sicherheit: Hash prüfen
        if ($reg && wp_hash($reg->email . $reg->id) === $hash) {
            
            // Status auf storniert setzen
            $repo->update_registration_status($reg_id, 'storniert');
            
            // NEU: E-Mail-Bestätigung über die erfolgreiche Stornierung senden
            MailService::send_registration_mail($reg_id, 'storno-bestaetigung');
            
            // Wichtig: Jetzt Plätze für die Warteliste frei machen!
            $max_cap = (int) get_post_meta($reg->event_post_id, 'vw_max_capacity', true);
            if ($max_cap > 0) {
                $repo->process_waitlist_move_up($reg->event_post_id, $max_cap);
            }

            wp_redirect(add_query_arg('cancel', 'success', get_permalink($reg->event_post_id)));
            exit;
        }

        wp_die('Dieser Stornierungs-Link ist nicht korrekt oder wurde bereits verwendet.');
    }
}