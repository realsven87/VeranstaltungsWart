<?php if (!defined('ABSPATH')) exit; ?>
<div class="vw-registration-container">
    <?php $this->render_messages($event_id); ?>
    <?php if ($atts['heading']): ?><h3><?php echo esc_html($atts['heading']); ?></h3><?php endif; ?>
    <?php if ($stats['is_full']): ?>
        <p>Diese Veranstaltung ist ausgebucht. Du kannst dich auf die Warteliste setzen lassen.</p>
    <?php elseif (filter_var($atts['show_places'], FILTER_VALIDATE_BOOLEAN) && $stats['max'] > 0): ?>
        <p><?php echo esc_html(max(0, $stats['max'] - $stats['current'])); ?> Plätze verfügbar</p>
    <?php endif; ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="vw_submit_registration">
        <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url(get_permalink(get_queried_object_id()) ?: get_permalink($event_id)); ?>">
        <?php wp_nonce_field('vw_reg_nonce_action_' . $event_id, 'vw_reg_nonce_field', false); ?>
        <div class="vw-honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="vw-form-grid">
            <p><label for="<?php echo esc_attr($uid); ?>first">Vorname *</label><input id="<?php echo esc_attr($uid); ?>first" name="first_name" autocomplete="given-name" required maxlength="100"></p>
            <p><label for="<?php echo esc_attr($uid); ?>last">Nachname *</label><input id="<?php echo esc_attr($uid); ?>last" name="last_name" autocomplete="family-name" required maxlength="100"></p>
        </div>
        <p><label for="<?php echo esc_attr($uid); ?>email">E-Mail-Adresse *</label><input type="email" id="<?php echo esc_attr($uid); ?>email" name="email" autocomplete="email" required maxlength="100"></p>
        <?php if ($allow_guests && $max_guests > 0): ?>
            <p><label for="<?php echo esc_attr($uid); ?>guests">Zusätzliche Begleitpersonen</label><select id="<?php echo esc_attr($uid); ?>guests" name="guests">
                <?php for ($i = 0; $i <= $max_guests; $i++): ?><option value="<?php echo $i; ?>"><?php echo $i; ?></option><?php endfor; ?>
            </select></p>
        <?php else: ?><input type="hidden" name="guests" value="0"><?php endif; ?>
        <?php if ($allow_message): ?>
            <p><label for="<?php echo esc_attr($uid); ?>message"><?php echo esc_html(\VW\Events\EventSettings::get($event_id, 'vw_message_label')); ?><?php echo $required ? ' *' : ' (optional)'; ?></label>
            <textarea id="<?php echo esc_attr($uid); ?>message" name="message" rows="3" maxlength="5000" placeholder="<?php echo esc_attr(\VW\Events\EventSettings::get($event_id, 'vw_message_hint')); ?>" <?php echo $required ? 'required' : ''; ?>></textarea></p>
        <?php endif; ?>
        <p><label class="vw-consent"><input type="checkbox" name="gdpr_consent" value="1" required><span>Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung der Anmeldung zu. Informationen in der <a href="<?php echo esc_url(get_privacy_policy_url() ?: home_url('/datenschutz/')); ?>" target="_blank" rel="noopener">Datenschutzerklärung</a>. *</span></label></p>
        <button class="wp-element-button vw-submit" type="submit"><?php echo esc_html($stats['is_full'] ? 'Auf Warteliste setzen' : $atts['button']); ?></button>
    </form>
</div>
