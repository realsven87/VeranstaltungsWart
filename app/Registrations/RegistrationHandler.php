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
        
        // ICS Kalender-Download
        add_action('admin_post_nopriv_vw_download_ics', [$this, 'handle_ics_download']);
        add_action('admin_post_vw_download_ics', [$this, 'handle_ics_download']);
    
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

    /**
     * Verarbeitet den Klick auf den Kalender-Link und generiert die .ics Datei on the fly
     */
    public function handle_ics_download() {
        $reg_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $hash   = isset($_GET['hash']) ? sanitize_text_field($_GET['hash']) : '';
        
        if (!$reg_id || !$hash) {
            wp_die('Ungültiger Aufruf.');
        }

        global $wpdb;
        $repo = new EventRepository();

        $reg = $wpdb->get_row($wpdb->prepare("
            SELECT r.id, p.email, r.event_post_id, post.post_title, post.post_content
            FROM {$wpdb->prefix}vw_registrations r
            JOIN {$wpdb->prefix}vw_persons p ON r.person_id = p.id
            JOIN {$wpdb->posts} post ON r.event_post_id = post.ID
            WHERE r.id = %d", $reg_id
        ));

        // Sicherheit: Hash prüfen
        if ($reg && wp_hash($reg->email . $reg->id) === $hash) {
            
            $event_date_raw = get_post_meta($reg->event_post_id, 'vw_event_date', true);
            if (!$event_date_raw) wp_die('Kein Datum für diese Veranstaltung hinterlegt.');

            // Adresse ermitteln
            $loc_id = get_post_meta($reg->event_post_id, 'vw_location_id', true);
            $adresse = '';
            if ($loc_id) {
                $location = $repo->get_location($loc_id);
                // Kombiniere Location-Name und Adresse
                $adresse = $location ? $location->name . ', ' . $location->address : '';
            }

            try {
                // WordPress Zeitzone nutzen
                $tz = wp_timezone();
                $start_dt = new \DateTime($event_date_raw, $tz);
                
                // Wir gehen pauschal von 2 Stunden Dauer aus
                $end_dt = clone $start_dt;
                $end_dt->modify('+2 hours');
                
                // ICS-Dateien erwarten das Datum zwingend in UTC (Z)
                $start_dt->setTimezone(new \DateTimeZone('UTC'));
                $end_dt->setTimezone(new \DateTimeZone('UTC'));
            
                $ics_content = "BEGIN:VCALENDAR\r\n" .
                               "VERSION:2.0\r\n" .
                               "PRODID:-//VeranstaltungsWart//DE\r\n" .
                               "CALSCALE:GREGORIAN\r\n" .
                               "BEGIN:VEVENT\r\n" .
                               "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n" .
                               "DTSTART:" . $start_dt->format('Ymd\THis\Z') . "\r\n" .
                               "DTEND:" . $end_dt->format('Ymd\THis\Z') . "\r\n" .
                               "SUMMARY:" . $this->escape_ics($reg->post_title) . "\r\n" .
                               "LOCATION:" . $this->escape_ics($adresse) . "\r\n" .
                               "DESCRIPTION:" . $this->escape_ics($desc) . "\r\n" .
                               "UID:vw-event-" . $reg->event_post_id . "@" . $_SERVER['HTTP_HOST'] . "\r\n" .
                               "END:VEVENT\r\n" .
                               "END:VCALENDAR";

                // Sende die Datei an den Browser (Erzwingt den Download)
                header('Content-Type: text/calendar; charset=utf-8');
                header('Content-Disposition: attachment; filename="termin.ics"');
                echo $ics_content;
                exit;

            } catch (\Exception $e) {
                wp_die('Fehler beim Generieren der Kalenderdatei.');
            }
        }

        wp_die('Dieser Link ist nicht korrekt oder abgelaufen.');
    }

    /**
     * Hilfsfunktion: Bereitet Text für die .ics Datei vor (Escaping von Sonderzeichen)
     */
    private function escape_ics($text) {
        return str_replace(["\\", ",", ";", "\n", "\r"], ["\\\\", "\,", "\;", "\\n", ""], $text);
    }
}