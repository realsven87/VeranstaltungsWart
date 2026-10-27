<?php
namespace VW\Events;

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

use VW\Events\EventRepository;
use VW\Mails\MailService;

/**
 * Modul: Event-Meta-Boxen
 * Verantwortlich für die Eingabefelder im Editor (Side-Panel) und die
 * Anzeige der Teilnehmerliste (Main-Panel) unter dem Editor.
 */
class EventMetaBox {
    /**
     * Registriert die Meta-Boxen und den Save-Hook im WordPress-Backend.
     */
    public function register() {
        // 1. Einstellungen in der Seitenleiste (Side)
        add_meta_box(
            'vw_event_settings_v2',
            __('Veranstaltungs-Details', 'veranstaltungswart'),
            [$this, 'render_settings_meta_box'],
            'vw_event',
            'side',
            'high'
        );

        // 2. Teilnehmerliste im Hauptbereich (Normal)
        add_meta_box(
            'vw_event_registrations_v2',
            __('Angemeldete Teilnehmer & Warteliste', 'veranstaltungswart'),
            [$this, 'render_registration_list'],
            'vw_event',
            'normal',
            'high'
        );

        // 3. Speicher-Hook registrieren

    }

    /**
     * Rendert die Felder für Datum, Ort und Kapazität
     */
    public function render_settings_meta_box($post) {
        if (function_exists('use_block_editor_for_post') && use_block_editor_for_post($post)) {
            echo '<p>' . esc_html__('Die Einstellungen findest du rechts im Bereich „VeranstaltungsWart“ oder im Anmeldeblock. Dort separat speichern.', 'veranstaltungswart') . '</p>';
            return;
        }
        wp_nonce_field('vw_event_settings_nonce', 'vw_event_settings_nonce_field');
        $locations = (new EventRepository())->get_all_locations();
        echo '<div class="vw-settings-fields">';
        foreach (EventSettings::fields() as $key => $field) {
            $value = EventSettings::get($post->ID, $key);
            echo '<div class="vw-setting-field"><label for="' . esc_attr($key) . '">' . esc_html($field[0]) . '</label><br>';
            if ($field[1] === 'questions') {
                echo '<div data-vw-question-editor><input type="hidden" name="vw_questions" value="' . esc_attr(wp_json_encode($value)) . '"><div class="vw-question-editor-root"></div></div>';
            } elseif ($field[1] === 'boolean') {
                echo '<input type="checkbox" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="1" ' . checked($value, '1', false) . '>';
            } elseif ($key === 'vw_location_id') {
                echo '<select id="' . esc_attr($key) . '" name="' . esc_attr($key) . '"><option value="0">' . esc_html__('Kein Ort', 'veranstaltungswart') . '</option>';
                foreach ($locations as $location) echo '<option data-capacity="' . esc_attr(max(0, (int) $location->default_capacity)) . '" value="' . (int) $location->id . '" ' . selected($value, $location->id, false) . '>' . esc_html($location->name) . '</option>';
                echo '</select>';
            } else {
                $type = $field[1] === 'date' ? 'datetime-local' : ($field[1] === 'integer' ? 'number' : 'text');
                if ($type === 'datetime-local') $value = str_replace(' ', 'T', substr($value, 0, 16));
                echo '<input class="widefat" type="' . esc_attr($type) . '" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" min="0">';
            }
            if (!empty($field[3])) echo '<p class="description">' . esc_html($field[3]) . '</p>';
            echo '</div>';
        }
        echo '</div>';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const location = document.getElementById('vw_location_id');
            const capacity = document.getElementById('vw_max_capacity');
            if (location && capacity) location.addEventListener('change', function() {
                const value = this.options[this.selectedIndex].getAttribute('data-capacity');
                if (value !== null) capacity.value = value;
            });
        });
        </script>
        <?php
    }

    public function save($post_id) {
        if (get_post_type($post_id) !== 'vw_event' || !isset($_POST['vw_event_settings_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vw_event_settings_nonce_field'])), 'vw_event_settings_nonce')) return;
        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) return;
        $input = [];
        foreach (EventSettings::fields() as $key => $field) {
            if ($field[1] === 'boolean') $input[$key] = isset($_POST[$key]) ? '1' : '0';
            elseif (isset($_POST[$key])) {
                $input[$key] = wp_unslash($_POST[$key]);
                if ($field[1] === 'questions') $input[$key] = is_string($input[$key]) ? json_decode($input[$key], true) : null;
            }
        }
        $result = EventSettings::save($post_id, $input);
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
    }

    /**
     * Zeigt die Tabelle mit den Anmeldungen unter dem Content-Editor an.
     */
    public function render_registration_list($post) {
        if (get_post_meta($post->ID, 'vw_event_canceled', true) === '1') {
            echo '<p><strong>' . esc_html__('Diese Veranstaltung wurde abgesagt.', 'veranstaltungswart') . '</strong></p>';
        } else {
            $url = wp_nonce_url(admin_url('admin-post.php?action=vw_cancel_event&event_id=' . $post->ID), 'vw_cancel_event_' . $post->ID);
            echo '<p><a class="button" onclick="' . esc_attr('return confirm(' . wp_json_encode(__('Veranstaltung absagen und alle aktiven Teilnehmer informieren?', 'veranstaltungswart')) . ')') . '" href="' . esc_url($url) . '">' . esc_html__('Veranstaltung absagen', 'veranstaltungswart') . '</a></p>';
        }
        $notify = wp_nonce_url(admin_url('admin-post.php?action=vw_notify_event_change&event_id=' . $post->ID), 'vw_notify_event_' . $post->ID);
        echo '<p>' . esc_html__('Änderungen zuerst speichern. Danach:', 'veranstaltungswart') . ' <a class="button" onclick="' . esc_attr('return confirm(' . wp_json_encode(__('Aktuelle Veranstaltungsdaten per E-Mail an alle aktiven Teilnehmer senden?', 'veranstaltungswart')) . ')') . '" href="' . esc_url($notify) . '">' . esc_html__('Teilnehmer über Änderungen informieren', 'veranstaltungswart') . '</a></p>';
        $repo = new EventRepository();
        $registrations = $repo->get_registrations_for_event($post->ID);
        $stats = $repo->get_registration_stats($post->ID);
        RegistrationQuestions::render_totals($post->ID);

        // Export-Button oben rechts platzieren
        echo '<div style="text-align: right; margin-bottom: 15px;">';
        // Hinzugefügt: esc_url um den Button-Link für maximale Sicherheit
        echo '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=vw_export_participants&event_id=' . $post->ID), 'vw_export_nonce')) . '"
                  class="button button-secondary">
                 <span class="dashicons dashicons-download" style="margin-top:4px;"></span> ' . esc_html__('Teilnehmerliste (CSV) exportieren', 'veranstaltungswart') . '
              </a>';
        echo '</div>';

        $view_file = VW_PLUGIN_DIR . 'app/Events/views/registration-list-table.php';
        if (file_exists($view_file)) {
            // Aktuelle URL für Referer-Logik
            $current_url = urlencode(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'] ?? '')));
            include $view_file;
        } else {
            echo '<p>' . esc_html__('Teilnehmerliste konnte nicht geladen werden.', 'veranstaltungswart') . '</p>';
        }
    }
}
