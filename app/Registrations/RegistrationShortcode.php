<?php
namespace VW\Registrations;
if (!defined('ABSPATH')) exit;
use VW\Events\EventRepository;
use VW\Events\EventSettings;
class RegistrationShortcode {
    public function init() { add_shortcode('event_registration', [$this, 'render_form']); }
    public function render_form($atts = []) {
        $atts = shortcode_atts(['id' => 0, 'heading' => __('Anmeldung', 'veranstaltungswart'), 'button' => __('Jetzt anmelden', 'veranstaltungswart'), 'show_places' => true], $atts, 'event_registration');
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
        $questions = \VW\Events\RegistrationQuestions::get($event_id);
        if (array_filter($questions, function($q) { return $q['active']; })) wp_enqueue_script('vw-registration');
        $uid = wp_unique_id('vw-form-');
        ob_start();
        include VW_PLUGIN_DIR . 'app/Registrations/views/form-view.php';
        return ob_get_clean();
    }
    public function render_messages($id) {
        if (isset($_GET['vw_event_id']) && absint($_GET['vw_event_id']) !== (int) $id) return;
        $errors = ['invalid_answers' => __('Bitte beantworte die Zusatzfragen vollständig. Falls die Fragen inzwischen geändert wurden, lade das Formular neu.', 'veranstaltungswart'), 'nonce_fail' => __('Bitte lade die Seite neu und versuche es erneut.', 'veranstaltungswart'), 'closed' => __('Die Anmeldung ist geschlossen.', 'veranstaltungswart'), 'invalid_data' => __('Bitte prüfe Name, E-Mail-Adresse und Datenschutzzustimmung.', 'veranstaltungswart'), 'invalid_guests' => __('Die Anzahl der Begleitpersonen ist nicht erlaubt.', 'veranstaltungswart'), 'invalid_message' => __('Bitte prüfe das Freitextfeld (maximal 5.000 Zeichen/Bytes).', 'veranstaltungswart'), 'duplicate' => __('Für diese E-Mail-Adresse besteht bereits eine aktive Anmeldung.', 'veranstaltungswart'), 'busy' => __('Bitte versuche es gleich noch einmal.', 'veranstaltungswart'), 'database' => __('Die Anmeldung konnte nicht gespeichert werden. Bitte erneut versuchen.', 'veranstaltungswart')];
        $success = ['ok' => __('Vielen Dank! Deine Anmeldung ist bestätigt.', 'veranstaltungswart'), 'pending' => __('Deine Anmeldung ist eingegangen und wird geprüft. Die Bestätigung erfolgt separat.', 'veranstaltungswart'), 'waitlist' => __('Du stehst auf der Warteliste. Dies ist noch keine Teilnahmebestätigung.', 'veranstaltungswart')];
        $error = isset($_GET['reg_error']) && is_string($_GET['reg_error']) ? sanitize_key(wp_unslash($_GET['reg_error'])) : '';
        $status = isset($_GET['reg']) && is_string($_GET['reg']) ? sanitize_key(wp_unslash($_GET['reg'])) : '';
        $message = $error ? ($errors[$error] ?? __('Ein Fehler ist aufgetreten.', 'veranstaltungswart')) : ($success[$status] ?? '');
        if (isset($_GET['cancel']) && $_GET['cancel'] === 'success') $message = __('Deine Anmeldung wurde storniert.', 'veranstaltungswart');
        if ($message) echo '<div class="vw-notice" role="status">' . esc_html($message) . '</div>';
    }
}
