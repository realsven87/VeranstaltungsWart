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
            SELECT r.*, p.first_name, p.last_name, p.email, post.post_title as event_title, post.post_author
            FROM {$wpdb->prefix}vw_registrations r
            JOIN {$wpdb->prefix}vw_persons p ON r.person_id = p.id
            JOIN {$wpdb->posts} post ON r.event_post_id = post.ID
            WHERE r.id = %d
        ", $reg_id));

        if (!$reg) return false;

        // 3. Sicherheits-Hash für den Stornierungs-Link
        // Wir nutzen E-Mail + ID, damit der Link für jede Anmeldung unique und sicher ist
        $hash = wp_hash($reg->email . $reg->id);

        // ACHTUNG: Der Link führt zum admin-post.php, um den Handler zu triggern
        $cancel_url = admin_url('admin-post.php?action=vw_cancel_registration&id=' . $reg->id . '&hash=' . $hash);
        $cancel_link = '<a href="' . esc_url($cancel_url) . '" style="color: #dc3545; font-weight: bold;">Anmeldung hier stornieren</a>';

        // NEU: Kalender-Download-Link generieren
        $ics_url = admin_url('admin-post.php?action=vw_download_ics&id=' . $reg->id . '&hash=' . $hash);
        $ics_link = '<a href="' . esc_url($ics_url) . '" style="color: #0073aa; font-weight: bold;">Termin in den Kalender eintragen</a>';

        // 4. Platzhalter-Mapping
        // Diese Schlüssel (links) können im Template-Text als {vorname} etc. verwendet werden
        $replacements = [
            'vorname'     => $reg->first_name,
            'nachname'    => $reg->last_name,
            'email'       => $reg->email,
            'event_plaetze' => $reg->seats_total,
            'teilnehmer_nachricht' => $reg->message,
            'storno_link' => $cancel_link,
            'kalender_link' => $ics_link
        ];

        $replacements = array_merge($replacements, \VW\Events\EventPresentation::values($reg->event_post_id, $reg->status === 'bestätigt'));

        // 5. Template über den Manager laden und rendern
        $mail_data = MailTemplateManager::get_rendered_mail($template_slug, $replacements);

        $is_admin_mail = in_array($template_slug, ['freigabe-info']);

        // 6. Sende-Konfiguration (Zentrale Admin-E-Mail bleibt Standard-Absender)
        $admin_email = get_option('admin_email');
        $sender_name = get_bloginfo('name');

        // Basis-Header aufbauen (HTML-Format und Admin als Absender)
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            "From: {$sender_name} <{$admin_email}>"
        ];

        // 7. Veranstaltungs-Autor (Ersteller) aus der Datenbank laden
        $event_author = get_userdata($reg->post_author);
        $author_email = ($event_author && is_email($event_author->user_email))
                        ? $event_author->user_email
                        : $admin_email; // Fallback zur Admin-Adresse, falls der Autor nicht existiert

        // 8. Empfänger und Reply-To flexibel verteilen
        if ($is_admin_mail) {
            // Die Freigabe-Info geht gezielt an den Ersteller dieser Veranstaltung
            $recipient = $author_email;

            // Klickt der Ersteller auf "Antworten", schreibt er direkt dem Teilnehmer
            $headers[] = "Reply-To: {$reg->first_name} {$reg->last_name} <{$reg->email}>";
        } else {
            // Die Bestätigung geht an den Teilnehmer
            $recipient = $reg->email;

            // Klickt der Teilnehmer auf "Antworten", landet die Nachricht direkt beim zuständigen Ersteller
            $headers[] = "Reply-To: {$sender_name} <{$author_email}>";
        }

        if (empty($recipient)) return false;

        // 9. Versand ausführen
        $sent = wp_mail($recipient, $mail_data['subject'], $mail_data['body'], $headers);

        return $sent;
    }
}
