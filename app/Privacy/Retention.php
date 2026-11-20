<?php
namespace VW\Privacy;
if (!defined('ABSPATH')) exit;

use VW\Events\EventSettings;
use VW\Events\EventRepository;

/** One retention rule shared by the settings screen and scheduled cleanup. */
class Retention {
    const OPTION = 'vw_retention_days';
    const DEFAULT_DAYS = 90;

    public function register() {
        add_action('admin_init', [$this, 'settings']);
        add_action('admin_menu', function() {
            add_submenu_page('vw_dashboard', __('Einstellungen', 'veranstaltungswart'), __('Einstellungen', 'veranstaltungswart'), 'manage_options', 'vw_settings', [$this, 'render']);
        });
    }

    public static function days() {
        $value = get_option(self::OPTION, self::DEFAULT_DAYS);
        return self::valid_days($value) ? (int) $value : self::DEFAULT_DAYS;
    }

    private static function valid_days($value) {
        return (is_int($value) || is_string($value)) && preg_match('/^[0-9]+$/D', (string) $value) && (int) $value >= 1 && (int) $value <= 3650;
    }

    public function sanitize($value) {
        if (!self::valid_days($value)) {
            add_settings_error(self::OPTION, 'vw_invalid_retention', __('Bitte eine ganze Zahl zwischen 1 und 3650 Tagen eingeben.', 'veranstaltungswart'));
            return self::days();
        }
        return (int) $value;
    }

    public function settings() {
        register_setting('vw_privacy', self::OPTION, ['type' => 'integer', 'default' => self::DEFAULT_DAYS, 'sanitize_callback' => [$this, 'sanitize']]);
    }

    public function render() {
        if (!current_user_can('manage_options')) return;
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('VeranstaltungsWart – Einstellungen', 'veranstaltungswart'); ?></h1>
            <?php settings_errors(); ?>
            <h2><?php esc_html_e('Datenschutz und automatische Löschung', 'veranstaltungswart'); ?></h2>
            <form method="post" action="options.php">
                <?php settings_fields('vw_privacy'); ?>
                <table class="form-table" role="presentation"><tr>
                    <th scope="row"><label for="vw_retention_days"><?php esc_html_e('Aufbewahrungsdauer in Tagen', 'veranstaltungswart'); ?></label></th>
                    <td><input name="vw_retention_days" id="vw_retention_days" type="number" min="1" max="3650" step="1" required value="<?php echo esc_attr(self::days()); ?>" aria-describedby="vw-retention-help">
                    <p class="description" id="vw-retention-help"><?php esc_html_e('Standard: 90 Tage. Die Frist beginnt mit dem Veranstaltungsende. Ist kein Ende eingetragen, zählt 23:59:59 Uhr des Veranstaltungstags in der Zeitzone dieser Website.', 'veranstaltungswart'); ?></p></td>
                </tr></table>
                <p><?php esc_html_e('Nach Ablauf der Frist löscht der tägliche Bereinigungslauf alle Anmeldungen dieser Veranstaltung – unabhängig vom Status, einschließlich Freitext, Zusatzantworten und Angaben zu Begleitpersonen. Die Veranstaltung selbst bleibt bestehen. Anmeldungen für andere Veranstaltungen bleiben erhalten.', 'veranstaltungswart'); ?></p>
                <p><?php esc_html_e('Kontakte ohne verbleibende Anmeldung und ohne Listenzuordnung werden ebenfalls gelöscht, sobald ihre Anlage mindestens so lange zurückliegt. Ein Vertrauensstatus verhindert die Löschung nicht. Kontakte mit Listenzuordnung bleiben erhalten und müssen für den jeweiligen Zweck separat geprüft werden.', 'veranstaltungswart'); ?></p>
                <p><strong><?php esc_html_e('Die Frist gilt auch für bestehende Daten. Eine Verkürzung kann beim nächsten Lauf weitere Daten unwiderruflich löschen. Bereits gelöschte Daten lassen sich durch eine Verlängerung nicht wiederherstellen.', 'veranstaltungswart'); ?></strong></p>
                <p><?php esc_html_e('Bei fehlenden oder ungültigen Veranstaltungsdaten bleiben Anmeldungen zur manuellen Prüfung erhalten. Das gilt auch für endgültig gelöschte Veranstaltungen. CSV-Exporte, versendete E-Mails und Backups werden nicht bereinigt. Die gewählte Frist ist eine technische Einstellung, keine gesetzlich festgelegte Aufbewahrungsdauer. Passe deine Datenschutzhinweise entsprechend an.', 'veranstaltungswart'); ?></p>
                <?php submit_button(); ?>
            </form>
            <h2><?php esc_html_e('Automatische Bereinigung', 'veranstaltungswart'); ?></h2>
            <p><?php esc_html_e('Die Bereinigung läuft täglich über WordPress-Cron. Für eine zuverlässige Ausführung ohne Websitebesuche sollte dein Hosting WordPress-Cron regelmäßig auslösen. Bei großen Datenbeständen werden pro Lauf bis zu 100 fällige Veranstaltungen bereinigt; weitere folgen beim nächsten Lauf.', 'veranstaltungswart'); ?></p>
            <?php $last = get_option('vw_retention_last_run', []); $next = wp_next_scheduled('vw_daily_gdpr_cleanup'); ?>
            <p><?php echo esc_html(sprintf(__('Nächster geplanter Lauf: %s', 'veranstaltungswart'), $next ? wp_date('d.m.Y H:i', $next) : __('Nicht geplant', 'veranstaltungswart'))); ?></p>
            <?php if ($last) : ?>
                <p><?php echo esc_html(sprintf(__('Letzter Lauf: %1$s. Gelöschte Anmeldungen: %2$d. Gelöschte Kontakte: %3$d. Veranstaltungen mit fehlenden oder ungültigen Daten: %4$d. Fehler: %5$d.', 'veranstaltungswart'), wp_date('d.m.Y H:i', $last['time']), $last['registrations'], $last['persons'], $last['skipped'], $last['errors'])); ?></p>
            <?php endif; ?>
            <?php if (!$next || $next < time() - DAY_IN_SECONDS || (!$last && $next < time()) || ($last && ($last['time'] < time() - 2 * DAY_IN_SECONDS || $last['errors'] || $last['skipped']))) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e('Bitte die Ausführung der Bereinigung und gegebenenfalls Veranstaltungsdaten prüfen. Es liegt noch kein erfolgreicher aktueller Lauf vor oder es wurden Probleme gemeldet.', 'veranstaltungswart'); ?></p></div>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function deadline($id) {
        if (get_post_type($id) !== 'vw_event') return null;
        $start = EventSettings::date(EventSettings::get($id, 'vw_event_date'));
        $raw_end = EventSettings::get($id, 'vw_event_end');
        $end = EventSettings::date($raw_end);
        if (!$start || ($raw_end !== '' && (!$end || $end <= $start))) return null;
        $end = $end ?: $start->setTime(23, 59, 59);
        return $end->modify('+' . self::days() . ' days');
    }

    public static function cleanup() {
        global $wpdb;
        $regs = $wpdb->prefix . 'vw_registrations';
        $persons = $wpdb->prefix . 'vw_persons';
        $lists = $wpdb->prefix . 'vw_person_list';
        $result = ['time' => time(), 'registrations' => 0, 'persons' => 0, 'skipped' => 0, 'errors' => 0];
        $ids = $wpdb->get_col($wpdb->prepare('SELECT DISTINCT event_post_id FROM %i ORDER BY event_post_id', $regs));
        if ($wpdb->last_error) $result['errors']++;
        $repo = new EventRepository();
        $processed = 0;
        foreach ($ids as $id) {
            $deadline = self::deadline($id);
            if (!$deadline) { $result['skipped']++; continue; }
            if ($deadline > current_datetime() || $processed >= 100) continue;
            $processed++;
            $deleted = $repo->with_event_lock($id, function() use ($id, $wpdb, $regs) {
                // A concurrent reschedule must not cause premature deletion.
                clean_post_cache($id);
                $deadline = self::deadline($id);
                if (!$deadline || $deadline > current_datetime()) return 0;
                return $wpdb->delete($regs, ['event_post_id' => $id], ['%d']);
            });
            if (is_wp_error($deleted) || $deleted === false) $result['errors']++;
            else $result['registrations'] += $deleted;
        }
        // Keep contacts needed by other registrations or lists. One conditional SQL
        // statement avoids deleting a contact based on a stale PHP candidate list.
        $deleted = $repo->with_event_lock(0, function() use ($wpdb, $persons, $regs, $lists) {
            return $wpdb->query($wpdb->prepare(
            'DELETE p FROM %i p WHERE p.created_at > %s AND p.created_at <= %s
             AND NOT EXISTS (SELECT 1 FROM %i r WHERE r.person_id = p.id)
             AND NOT EXISTS (SELECT 1 FROM %i l WHERE l.person_id = p.id)',
            $persons, '0000-00-00 00:00:00', current_datetime()->modify('-' . self::days() . ' days')->format('Y-m-d H:i:s'), $regs, $lists
            ));
        });
        if (is_wp_error($deleted) || $deleted === false) $result['errors']++;
        else $result['persons'] = $deleted;
        update_option('vw_retention_last_run', $result, false);
        return $result;
    }
}
