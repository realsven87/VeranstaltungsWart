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
        ?>
        
        <style>
            .vw-form-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin-bottom: 15px;
            }
            .vw-form-group {
                margin-bottom: 15px;
            }
            .vw-input {
                width: 100%;
                padding: 10px;
                border: 1px solid #ccc;
                border-radius: 4px;
                box-sizing: border-box;
            }
            .vw-label {
                display: block;
                font-weight: bold;
                margin-bottom: 5px;
            }
            
            /* Styling für die DSGVO Checkbox */
            .vw-gdpr-box {
                background: #fff; 
                padding: 15px; 
                border: 1px solid #eee; 
                border-left: 3px solid #2271b1;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            .vw-gdpr-label {
                display: flex;
                gap: 10px;
                align-items: flex-start;
                cursor: pointer;
                font-weight: normal;
                margin: 0;
            }
            .vw-gdpr-label input[type="checkbox"] {
                margin-top: 4px;
            }
            
            /* Breakpoint für mobile Geräte (Smartphones) */
            @media (max-width: 600px) {
                .vw-form-grid {
                    grid-template-columns: 1fr; /* Untereinander statt nebeneinander */
                    gap: 0;
                }
            }
        </style>

        <div class="vw-registration-container" style="background: #f9f9f9; padding: 25px; border-radius: 8px; border: 1px solid #ddd; margin-bottom: 20px;">
            <h3 style="margin-top:0; margin-bottom: 20px;">
                <?php echo $is_full ? esc_html__('Anmeldung zu Warteliste', 'veranstaltungswart') : esc_html__('Anmeldung zu Veranstaltung', 'veranstaltungswart'); ?>
            </h3>
            
            <?php if ($is_full): ?>
                <p style="color: #856404; font-weight: bold; background: #fff3cd; padding: 10px; border-radius: 4px;">
                    <?php esc_html_e('Diese Veranstaltung ist aktuell ausgebucht. Du kannst dich auf die Warteliste setzen lassen.', 'veranstaltungswart'); ?>
                </p>
            <?php elseif ($stats['max'] > 0 && $available_seats < 10): ?>
                <p style="color: #d63638; font-weight: bold; background: #f8d7da; padding: 10px; border-radius: 4px;">
                    <?php 
                    /* translators: %s: Anzahl der verbleibenden Plätze */
                    printf(esc_html__('Nur noch %s Plätze verfügbar!', 'veranstaltungswart'), (int) $available_seats); 
                    ?>
                </p>
            <?php endif; ?>

            <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                <input type="hidden" name="action" value="vw_submit_registration">
                <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>">
                <?php wp_nonce_field('vw_reg_nonce_action', 'vw_reg_nonce_field'); ?>
                
                <div class="vw-form-grid">
                    <div class="vw-form-group">
                        <label class="vw-label"><?php esc_html_e('Vorname *', 'veranstaltungswart'); ?></label>
                        <input type="text" name="first_name" required class="vw-input"
                                pattern="[A-Za-z \s-]+" title="<?php esc_attr_e('Nur Buchstaben erlaubt', 'veranstaltungswart'); ?>">
                    </div>
                    <div class="vw-form-group">
                        <label class="vw-label"><?php esc_html_e('Nachname *', 'veranstaltungswart'); ?></label>
                        <input type="text" name="last_name" required class="vw-input"
                                pattern="[A-Za-z \s-]+" title="<?php esc_attr_e('Nur Buchstaben erlaubt', 'veranstaltungswart'); ?>">
                    </div>
                </div>

                <div class="vw-form-group">
                    <label class="vw-label"><?php esc_html_e('E-Mail-Adresse *', 'veranstaltungswart'); ?></label>
                    <input type="email" name="email" required class="vw-input">
                </div>
                
                <?php if ($allow_guests): ?>
                    <div class="vw-form-group" style="background: #fff; padding: 15px; border: 1px solid #eee; border-radius: 4px;">
                        <label class="vw-label"><?php esc_html_e('Zusätzliche Begleitpersonen', 'veranstaltungswart'); ?></label>
                        <select name="guests" class="vw-input" style="width: 80px; display: inline-block;">
                            <?php for ($i = 0; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                        <span style="font-size: 0.9em; color: #666; margin-left: 10px;"><?php esc_html_e('Personen, die du mitbringst.', 'veranstaltungswart'); ?></span>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="guests" value="0">
                <?php endif; ?>

                <?php if ($allow_message): ?>
                    <div class="vw-form-group" style="background: #fff; padding: 15px; border: 1px solid #eee; border-radius: 4px; margin-bottom: 20px;">
                        <label class="vw-label"><?php esc_html_e('Anmerkungen / Nachricht (optional)', 'veranstaltungswart'); ?></label>
                        <textarea name="message" class="vw-input" rows="3" placeholder="<?php esc_attr_e('Allergien, Platzwünsche oder Besonderheiten...', 'veranstaltungswart'); ?>"></textarea>
                    </div>
                <?php endif; ?>
                
                <div class="vw-gdpr-box">
                    <label class="vw-gdpr-label">
                        <input type="checkbox" name="gdpr_consent" required 
                               oninvalid="this.setCustomValidity('<?php esc_attr_e('Bitte stimme der Datenschutzerklärung zu, um dich verbindlich anzumelden.', 'veranstaltungswart'); ?>')"
                               onchange="this.setCustomValidity('')">
                        <span style="font-size: 0.9em; line-height: 1.4; color: #444;">
                            <?php esc_html_e('Ich stimme zu, dass meine Angaben aus diesem Formular zur Bearbeitung meiner Anmeldung erhoben und verarbeitet werden. Weitere Informationen findest du in der', 'veranstaltungswart'); ?> 
                            <?php 
                            // Holt den Link zur offiziellen WP-Datenschutzseite. Falls nicht gesetzt, Fallback auf /datenschutz
                            $privacy_url = function_exists('get_privacy_policy_url') && get_privacy_policy_url() ? get_privacy_policy_url() : '/datenschutz';
                            ?>
                            <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" style="color: #2271b1; text-decoration: underline;">
                                <?php esc_html_e('Datenschutzerklärung', 'veranstaltungswart'); ?>
                            </a>. *
                        </span>
                    </label>
                </div>
                
                <div style="margin-top: 25px;">
                    <button type="submit" style="background:<?php echo $is_full ? '#856404' : '#0073aa'; ?>; color:white; padding:15px 24px; border:none; border-radius:4px; cursor:pointer; font-weight:bold; width:100%; font-size: 1.1em; transition: opacity 0.2s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                        <?php echo $is_full ? esc_html__('Auf Warteliste setzen', 'veranstaltungswart') : esc_html__('Jetzt anmelden', 'veranstaltungswart'); ?>
                    </button>
                </div>
            </form>
        </div>

        <?php 
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