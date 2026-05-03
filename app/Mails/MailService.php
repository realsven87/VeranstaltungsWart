<?php
namespace VW\Mails;

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

use VW\Events\EventRepository;

/**
 * Modul: MailService
 * Zentraler Dienst für den E-Mail-Versand.
 * Verknüpft Registrierungsdaten mit den Templates und versendet HTML-Mails.
 */
class MailService {

    /**
     * Versendet eine E-Mail basierend auf einer Registrierungs-ID und einem Template-Slug.
     */
    public static function send_registration_mail($reg_id, $template_slug) {
        global $wpdb;
        $repo = new EventRepository();

        // 1. Alle relevanten Daten über einen JOIN holen (Performance-Vorteil)
        $reg = $wpdb->get_row($wpdb->prepare("
            SELECT r.*, p.first_name, p.last_name, p.email, post.post_title as event_title
            FROM {$wpdb->prefix}vw_registrations r
            JOIN {$wpdb->prefix}vw_persons p ON r.person_id = p.id
            JOIN {$wpdb->posts} post ON r.event_post_id = post.ID
            WHERE r.id = %d
        ", $reg_id));

        if (!$reg) return false;

        // 2. Event-Details aufbereiten
        $event_date_raw = get_post_meta($reg->event_post_id, 'vw_event_date', true);
        $datum = ''; 
        $uhrzeit = '';
        
        if ($event_date_raw) {
            try {
                $dt = new \DateTime($event_date_raw);
                $datum = $dt->format('d.m.Y');
                $uhrzeit = $dt->format('H:i');
            } catch (\Exception $e) {
                // Falls das Datum mal ein falsches Format hat, bleibt es leer statt Fehler zu werfen
            }
        }

        $loc_id = get_post_meta($reg->event_post_id, 'vw_location_id', true);
        $adresse = '';
        if ($loc_id) {
            $location = $repo->get_location($loc_id);
            $adresse = $location ? $location->address : '';
        }

        // 3. Sicherheits-Hash für den Stornierungs-Link
        // Wir nutzen E-Mail + ID, damit der Link für jede Anmeldung unique und sicher ist
        $hash = wp_hash($reg->email . $reg->id);
        
        // ACHTUNG: Der Link führt zum admin-post.php, um den Handler zu triggern
        $cancel_url = admin_url('admin-post.php?action=vw_cancel_registration&id=' . $reg->id . '&hash=' . $hash);
        $cancel_link = '<a href="' . esc_url($cancel_url) . '" style="color: #dc3545; font-weight: bold;">Anmeldung hier stornieren</a>';

        // 4. Platzhalter-Mapping
        // Diese Schlüssel (links) können im Template-Text als {vorname} etc. verwendet werden
        $replacements = [
            'vorname'     => $reg->first_name,
            'nachname'    => $reg->last_name,
            'email'       => $reg->email,
            'event_name'  => $reg->event_title,
            'event_datum' => $datum,
            'event_zeit'  => $uhrzeit,
            'event_adresse'     => $adresse,
            'event_plaetze'     => $reg->seats_total,
            'storno_link' => $cancel_link,
            'admin_link'  => admin_url('admin.php?page=vw_dashboard')
        ];

        // 5. Template über den Manager laden und rendern
        // Der TemplateManager ersetzt alle {platzhalter} durch die echten Werte
        $mail_data = MailTemplateManager::get_rendered_mail($template_slug, $replacements);

        // 6. Empfänger festlegen
        // 'freigabe-info' geht an den Admin, alles andere an den User
        $is_admin_mail = in_array($template_slug, ['freigabe-info']);
        $recipient = $is_admin_mail ? get_option('admin_email') : $reg->email;

        if (empty($recipient)) return false;

        // 7. Versand-Konfiguration (HTML-Modus)
        $set_html_content_type = function() { return 'text/html'; };
        add_filter('wp_mail_content_type', $set_html_content_type);
        
        // Absendername auf den Website-Namen setzen
        $blog_name = get_bloginfo('name');
        $set_from_name = function() use ($blog_name) { return $blog_name; };
        add_filter('wp_mail_from_name', $set_from_name);

        // Versand ausführen
        $sent = wp_mail($recipient, $mail_data['subject'], $mail_data['body']);
        
        // Filter sofort wieder entfernen (wichtig!)
        remove_filter('wp_mail_content_type', $set_html_content_type);
        remove_filter('wp_mail_from_name', $set_from_name);

        return $sent;
    }
}