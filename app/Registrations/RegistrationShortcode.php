<?php 
namespace VW\Registrations; 

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

use VW\Events\EventRepository;

/**
 * Modul: RegistrationShortcode
 * Verantwortlich für die Anzeige des Anmeldeformulars und die 
 * Verarbeitung von Statusmeldungen via URL-Parameter.
 */
class RegistrationShortcode {

    public function init() {
        add_shortcode('event_registration', [$this, 'render_form']);
    }

    public function render_form($atts) {
        $event_id = get_the_ID();
        
        // Sicherstellen, dass wir in einem Event-CPT sind
        if (get_post_type($event_id) !== 'vw_event') return '';

        // --- NEU: Sperre für abgesagte Veranstaltungen ---
        if (get_post_meta($event_id, 'vw_event_canceled', true) === '1') {
            return '<div style="padding:15px; background:#f8d7da; color:#721c24; border-left: 5px solid #dc3545; border-radius:4px; margin-bottom:20px; font-weight: bold;">
                        Diese Veranstaltung wurde abgesagt. Eine Anmeldung ist nicht mehr möglich.
                    </div>';
        }
        // -------------------------------------------------

        $repo = new EventRepository();
        $stats = $repo->get_registration_stats($event_id);
        $allow_guests = get_post_meta($event_id, 'vw_allow_guests', true) !== '0';
        $allow_message = get_post_meta($event_id, 'vw_allow_message', true) === '1';
        
        // Berechnung der Restplätze (darf nicht negativ sein)
        $available_seats = max(0, $stats['max'] - $stats['current']);
        $is_full = $stats['is_full'];

        ob_start();
        
        // 1. Feedback-Meldungen ausgeben
        $this->render_messages();
        
        // 2. Die externe HTML-Datei laden
        $view_file = VW_PLUGIN_DIR . 'app/Registrations/views/form-view.php';
        if (file_exists($view_file)) {
            include $view_file;
        }

        return ob_get_clean();
    }

    /**
     * Rendert Erfolgs- und Fehlermeldungen basierend auf URL-Parametern.
     */
    private function render_messages() {
        if (isset($_GET['reg_error'])) {
            $error_code = sanitize_text_field($_GET['reg_error']);
            $msg = __('Ein Fehler ist aufgetreten. Bitte versuche es erneut.', 'veranstaltungswart');
            
            if ($error_code === 'invalid_name') $msg = __('Bitte gib einen gültigen Namen an.', 'veranstaltungswart');
            if ($error_code === 'invalid_email') $msg = __('Bitte gib eine gültige E-Mail-Adresse an.', 'veranstaltungswart');
            if ($error_code === 'nonce_fail') $msg = __('Sicherheits-Check fehlgeschlagen. Bitte lade die Seite neu.', 'veranstaltungswart');
            
            echo '<div style="padding:15px; background:#f8d7da; color:#721c24; border-left: 5px solid #dc3545; border-radius:4px; margin-bottom:20px; font-weight: bold;">' . esc_html($msg) . '</div>';
        }

        if (isset($_GET['reg'])) {
            $status = sanitize_text_field($_GET['reg']);
            $msg = ($status === 'ok') ? __('Vielen Dank! Deine Anmeldung war erfolgreich.', 'veranstaltungswart') : __('Du wurdest erfolgreich auf die Warteliste gesetzt.', 'veranstaltungswart');
            $bg = ($status === 'ok') ? '#d4edda' : '#fff3cd';
            $border = ($status === 'ok') ? '#28a745' : '#ffc107';
            $color = ($status === 'ok') ? '#155724' : '#856404';
            
            echo '<div style="padding:15px; background:'.$bg.'; color:'.$color.'; border-left: 5px solid '.$border.'; border-radius:4px; margin-bottom:20px; font-weight: bold;">' . esc_html($msg) . '</div>';
        }

        if (isset($_GET['cancel']) && $_GET['cancel'] === 'success') {
            echo '<div style="padding:15px; background:#e2e3e5; color:#383d41; border-left: 5px solid #6c757d; border-radius:4px; margin-bottom:20px; font-weight: bold;">' . esc_html__('Deine Anmeldung wurde erfolgreich storniert.', 'veranstaltungswart') . '</div>';
        }
    }
}