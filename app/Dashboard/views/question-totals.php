<?php if (!defined('ABSPATH')) exit; ?>
<h2><?php esc_html_e('Zusatzfragen auswerten', 'veranstaltungswart'); ?></h2>
<form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
    <input type="hidden" name="page" value="vw_dashboard">
    <label for="vw-summary-event"><?php esc_html_e('Veranstaltung für Auswertung', 'veranstaltungswart'); ?></label>
    <select id="vw-summary-event" name="vw_summary_event">
        <option value="0"><?php esc_html_e('Bitte auswählen', 'veranstaltungswart'); ?></option>
        <?php foreach ($summary_events as $event): ?>
            <option value="<?php echo (int) $event->ID; ?>" <?php selected($summary_id, $event->ID); ?>><?php echo esc_html($event->post_title); ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="button"><?php esc_html_e('Auswertung anzeigen', 'veranstaltungswart'); ?></button>
</form>
<?php if ($summary_id && \VW\Core\Capabilities::can_manage_event($summary_id)) \VW\Events\RegistrationQuestions::render_totals($summary_id); ?>
