<?php
namespace VW\Registrations;
if (!defined('ABSPATH')) exit;
use VW\Events\EventRepository;
use VW\Events\EventSettings;
class RegistrationShortcode {
    public function init() { add_shortcode('event_registration', [$this, 'render_form']); }
    public function render_form($atts = []) {
        $atts = shortcode_atts(['id' => 0, 'heading' => 'Anmeldung', 'button' => 'Jetzt anmelden', 'show_places' => true], $atts, 'event_registration');
        $event_id = absint($atts['id']) ?: get_the_ID();
        if (!EventSettings::is_public($event_id)) return '';
        wp_enqueue_style('vw-blocks');
        $reason = EventSettings::closed_reason($event_id);
        if ($reason) return '<div class="vw-notice" role="status">' . esc_html($reason) . '</div>';
        $stats = (new EventRepository())->get_registration_stats($event_id);
        $allow_guests = EventSettings::get($event_id, 'vw_allow_guests') === '1';
        $allow_message = EventSettings::get($event_id, 'vw_allow_message') === '1';
        $max_guests = min(100, (int) EventSettings::get($event_id, 'vw_max_guests'));
        $required = EventSettings::get($event_id, 'vw_message_required') === '1';
        $uid = wp_unique_id('vw-form-');
        ob_start();
        include VW_PLUGIN_DIR . 'app/Registrations/views/form-view.php';
        return ob_get_clean();
    }
    public function render_messages($id) {
        if (isset($_GET['vw_event']) && absint($_GET['vw_event']) !== (int) $id) return;
        $errors = ['nonce_fail' => 'Bitte lade die Seite neu und versuche es erneut.', 'closed' => 'Die Anmeldung ist geschlossen.', 'invalid_data' => 'Bitte prüfe Name, E-Mail-Adresse und Datenschutzzustimmung.', 'invalid_guests' => 'Die Anzahl der Begleitpersonen ist nicht erlaubt.', 'invalid_message' => 'Bitte prüfe das Freitextfeld (maximal 5.000 Zeichen/Bytes).', 'duplicate' => 'Für diese E-Mail-Adresse besteht bereits eine aktive Anmeldung.', 'busy' => 'Bitte versuche es gleich noch einmal.', 'database' => 'Die Anmeldung konnte nicht gespeichert werden. Bitte erneut versuchen.'];
        $success = ['ok' => 'Vielen Dank! Deine Anmeldung ist bestätigt.', 'pending' => 'Deine Anmeldung ist eingegangen und wird geprüft. Die Bestätigung erfolgt separat.', 'waitlist' => 'Du stehst auf der Warteliste. Dies ist noch keine Teilnahmebestätigung.'];
        $error = isset($_GET['reg_error']) && is_string($_GET['reg_error']) ? sanitize_key($_GET['reg_error']) : '';
        $status = isset($_GET['reg']) && is_string($_GET['reg']) ? sanitize_key($_GET['reg']) : '';
        $message = $error ? ($errors[$error] ?? 'Ein Fehler ist aufgetreten.') : ($success[$status] ?? '');
        if (isset($_GET['cancel']) && $_GET['cancel'] === 'success') $message = 'Deine Anmeldung wurde storniert.';
        if ($message) echo '<div class="vw-notice" role="status">' . esc_html($message) . '</div>';
    }
}
