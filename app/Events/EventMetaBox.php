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
        add_action('save_post_vw_event', [$this, 'save']);
    }

    /**
     * Rendert die Felder für Datum, Ort und Kapazität
     */
    public function render_settings_meta_box($post) {
        $repo = new EventRepository();
        $locations = $repo->get_all_locations();
        
        $current_location = get_post_meta($post->ID, 'vw_location_id', true);
        $current_capacity = get_post_meta($post->ID, 'vw_max_capacity', true);
        $current_date     = get_post_meta($post->ID, 'vw_event_date', true);
        $allow_guests     = get_post_meta($post->ID, 'vw_allow_guests', true);
        $allow_message    = get_post_meta($post->ID, 'vw_allow_message', true);
        $send_reminders   = get_post_meta($post->ID, 'vw_send_reminders', true);
        $event_hinweis    = get_post_meta($post->ID, 'vw_event_notes', true);

        if ($allow_guests === '') $allow_guests = '1'; 
        if ($send_reminders === '') $send_reminders = '1';
        
        wp_nonce_field('vw_event_settings_nonce', 'vw_event_settings_nonce_field');
        ?>
        
        <style>
            /* Erzwingt exakt gleiche Breiten und Box-Modelle für alle Felder in dieser MetaBox */
            .vw-event-settings-fields input[type="datetime-local"],
            .vw-event-settings-fields input[type="number"],
            .vw-event-settings-fields select {
                width: 100%;
                max-width: 100%;
                box-sizing: border-box;
                margin-top: 5px;
            }
        </style>
        <div class="vw-event-settings-fields">
            <p>
                <label for="vw_event_date"><strong><?php esc_html_e('Datum & Uhrzeit:', 'veranstaltungswart'); ?></strong></label>
                <input type="datetime-local" id="vw_event_date" name="vw_event_date"
                        value="<?php echo esc_attr(str_replace(' ', 'T', $current_date)); ?>"
                        class="widefat" required>
            </p>
            
            <p>
                <label for="vw_location_id"><strong><?php esc_html_e('Veranstaltungsort:', 'veranstaltungswart'); ?></strong></label>
                <select name="vw_location_id" id="vw_location_id" class="widefat" required>
                    <option value=""><?php esc_html_e('-- Ort wählen --', 'veranstaltungswart'); ?></option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?php echo (int) $loc->id; ?>"
                                 data-capacity="<?php echo (int) $loc->default_capacity; ?>"
                                 <?php selected($current_location, $loc->id); ?>>
                            <?php echo esc_html($loc->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label for="vw_max_capacity"><strong><?php esc_html_e('Max. Teilnehmer:', 'veranstaltungswart'); ?></strong></label>
                <input type="number" name="vw_max_capacity" id="vw_max_capacity"
                        value="<?php echo esc_attr($current_capacity); ?>"
                        class="widefat" min="0">
                <span class="description" style="display: block; margin-top: 4px;"><?php esc_html_e('0 = Unbegrenzt', 'veranstaltungswart'); ?></span>
            </p>
            <hr>
            <p style="margin-top: 15px;">
                <label for="vw_event_notes"><strong><?php esc_html_e('Hinweise für Teilnehmer (Optional):', 'veranstaltungswart'); ?></strong></label>
                <textarea name="vw_event_notes" id="vw_event_notes" class="widefat" rows="3" placeholder="<?php esc_attr_e('Z.B. Bitte festes Schuhwerk mitbringen...', 'veranstaltungswart'); ?>"><?php echo esc_textarea($event_hinweis); ?></textarea>
                <span class="description" style="display: block; margin-top: 4px;"><?php esc_html_e('Dieser Text wird über den Platzhalter {event_hinweis} in die E-Mails eingefügt.', 'veranstaltungswart'); ?></span>
            </p>

            <hr>
            <p>
                <label>
                    <input type="checkbox" name="vw_allow_guests" value="1" <?php checked($allow_guests, '1'); ?>>
                    <strong><?php esc_html_e('Begleitpersonen erlauben', 'veranstaltungswart'); ?></strong>
                </label>
            </p>
            <p style="margin-top: 10px;">
                <label>
                    <input type="checkbox" name="vw_allow_message" value="1" <?php checked($allow_message, '1'); ?>>
                    <strong><?php esc_html_e('Freitextfeld (Anmerkungen) anzeigen', 'veranstaltungswart'); ?></strong>
                </label>
            </p>         
            <p style="margin-top: 10px;">
                <label>
                    <input type="checkbox" name="vw_send_reminders" value="1" <?php checked($send_reminders, '1'); ?>>
                    <strong><?php esc_html_e('Erinnerungen versenden (7 & 3 Tage vor Veranstaltung)', 'veranstaltungswart'); ?></strong>
                </label>
            </p>
            
            <hr>
            <p style="margin-top: 15px;">
                <label>
                    <input type="checkbox" name="vw_notify_change" value="1">
                    <strong style="color: #2271b1;"><?php esc_html_e('Teilnehmer über diese Änderung informieren (E-Mail)', 'veranstaltungswart'); ?></strong>
                </label>
                <span class="description" style="display: block; margin-top: 4px;"><?php esc_html_e('Sendet nach dem Speichern sofort eine Update-E-Mail an alle aktiven Teilnehmer (Warteliste & Bestätigt).', 'veranstaltungswart'); ?></span>
            </p>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const locSelect = document.getElementById('vw_location_id');
                const capInput  = document.getElementById('vw_max_capacity');
                if(locSelect && capInput) {
                    locSelect.addEventListener('change', function() {
                        const selectedOption = this.options[this.selectedIndex];
                        const defaultCap = selectedOption.getAttribute('data-capacity');
                        
                        if (defaultCap && (capInput.value === '' || capInput.value === '0')) {
                            capInput.value = defaultCap;
                        }
                    });
                }
            });
        </script>
        
        <?php 
            $is_canceled = get_post_meta($post->ID, 'vw_event_canceled', true) === '1';
            if (!$is_canceled): 
                $cancel_url = admin_url('admin-post.php?action=vw_cancel_event&event_id=' . $post->ID);
                $cancel_url = wp_nonce_url($cancel_url, 'vw_cancel_event_' . $post->ID);
            ?>
                <p style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #ccd0d4; text-align: center;">
                    <a href="<?php echo esc_url($cancel_url); ?>" 
                       class="button" 
                       style="background: #d63638; color: white; border-color: #d63638; text-shadow: none; width: 100%; font-weight: bold; padding: 5px 0; height: auto; display: flex; justify-content: center; align-items: center;"
                       onclick="return confirm('Möchtest du diese Veranstaltung wirklich absagen? Alle angemeldeten Personen und Wartelisten-Teilnehmer werden unwiderruflich per E-Mail informiert!');">
                        <span class="dashicons dashicons-calendar-cropper" style="vertical-align: middle; margin-top: -3px; margin-right: 5px;"></span> 
                        Veranstaltung absagen
                    </a>
                </p>
            <?php else: ?>
                <p style="margin-top: 25px; padding-top: 15px; border-top: 1px solid #ccd0d4; text-align: center; color: #d63638; font-weight: bold; font-size: 14px;">
                    Diese Veranstaltung wurde abgesagt!
                </p>
            <?php endif; ?>
        <?php
    }

    /**
     * Speichert die Daten und triggert bei Kapazitätserhöhung die Warteliste.
     */
    public function save($post_id) {
        if (!isset($_POST['vw_event_settings_nonce_field']) || !wp_verify_nonce($_POST['vw_event_settings_nonce_field'], 'vw_event_settings_nonce')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        $repo = new EventRepository();
        
        $old_capacity = (int) get_post_meta($post_id, 'vw_max_capacity', true);
        $new_capacity = isset($_POST['vw_max_capacity']) ? (int) $_POST['vw_max_capacity'] : 0;
        
        // Datum für SQL formatieren (T von datetime-local entfernen)
        $clean_date = isset($_POST['vw_event_date']) ? str_replace('T', ' ', sanitize_text_field($_POST['vw_event_date'])) : '';
        
        update_post_meta($post_id, 'vw_event_date', $clean_date);
        update_post_meta($post_id, 'vw_location_id', (int) $_POST['vw_location_id']);
        update_post_meta($post_id, 'vw_max_capacity', $new_capacity);
        update_post_meta($post_id, 'vw_allow_guests', isset($_POST['vw_allow_guests']) ? '1' : '0');
        update_post_meta($post_id, 'vw_allow_message', isset($_POST['vw_allow_message']) ? '1' : '0');
        update_post_meta($post_id, 'vw_send_reminders', isset($_POST['vw_send_reminders']) ? '1' : '0');

        // Hinweise für Teilnehmer
        $event_notes = isset($_POST['vw_event_notes']) ? sanitize_textarea_field($_POST['vw_event_notes']) : '';
        update_post_meta($post_id, 'vw_event_notes', $event_notes);

        // Wartelisten-Automatik
        if ($new_capacity > 0 && $new_capacity > $old_capacity) {
            $repo->process_waitlist_move_up($post_id, $new_capacity);
        }

        // Änderungs-Benachrichtigungen versenden
        if (isset($_POST['vw_notify_change']) && $_POST['vw_notify_change'] === '1') {
            $registrations = $repo->get_registrations_for_event($post_id);
            if (!empty($registrations)) {
                foreach ($registrations as $reg) {
                    $rid = isset($reg->id) ? $reg->id : (isset($reg->reg_id) ? $reg->reg_id : 0);
                    
                    // Wir informieren alle aktiven Teilnehmer (stornierte/abgehnte sind irrelevant)
                    if ($rid && $reg->status !== 'storniert' && $reg->status !== 'abgelehnt') {
                        MailService::send_registration_mail($rid, 'event-aenderung');
                    }
                }
            }
        }
    }

    /**
     * Zeigt die Tabelle mit den Anmeldungen unter dem Content-Editor an.
     */
    public function render_registration_list($post) {
        $repo = new EventRepository();
        $registrations = $repo->get_registrations_for_event($post->ID);
        $stats = $repo->get_registration_stats($post->ID);
        
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
            $current_url = urlencode(wp_unslash($_SERVER['REQUEST_URI']));
            include $view_file;
        } else {
            echo '<p>' . esc_html__('Teilnehmerliste konnte nicht geladen werden.', 'veranstaltungswart') . '</p>';
        }
    }
}