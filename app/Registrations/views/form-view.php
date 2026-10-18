<?php if (!defined('ABSPATH')) exit; ?>
<div class="vw-registration-container">
    <?php $this->render_messages($event_id); ?>
    <?php if ($atts['heading']): ?><h3><?php echo esc_html($atts['heading']); ?></h3><?php endif; ?>
    <?php if ($stats['is_full']): ?>
        <p><?php esc_html_e('Diese Veranstaltung ist ausgebucht. Du kannst dich auf die Warteliste setzen lassen.', 'veranstaltungswart'); ?></p>
    <?php elseif (filter_var($atts['show_places'], FILTER_VALIDATE_BOOLEAN) && $stats['max'] > 0): ?>
        <p><?php $places = max(0, $stats['max'] - $stats['current']); /* translators: %d: available seats. */ echo esc_html(sprintf(_n('%d Platz verfügbar', '%d Plätze verfügbar', $places, 'veranstaltungswart'), $places)); ?></p>
    <?php endif; ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <input type="hidden" name="action" value="vw_submit_registration">
        <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url(get_permalink(get_queried_object_id()) ?: get_permalink($event_id)); ?>">
        <?php wp_nonce_field('vw_reg_nonce_action_' . $event_id, 'vw_reg_nonce_field', false); ?>
        <div class="vw-honeypot" aria-hidden="true"><label><?php esc_html_e('Website', 'veranstaltungswart'); ?><input name="website" tabindex="-1" autocomplete="off"></label></div>
        <div class="vw-form-grid">
            <p><label for="<?php echo esc_attr($uid); ?>first"><?php esc_html_e('Vorname *', 'veranstaltungswart'); ?></label><input id="<?php echo esc_attr($uid); ?>first" name="first_name" autocomplete="given-name" required maxlength="100"></p>
            <p><label for="<?php echo esc_attr($uid); ?>last"><?php esc_html_e('Nachname *', 'veranstaltungswart'); ?></label><input id="<?php echo esc_attr($uid); ?>last" name="last_name" autocomplete="family-name" required maxlength="100"></p>
        </div>
        <p><label for="<?php echo esc_attr($uid); ?>email"><?php esc_html_e('E-Mail-Adresse *', 'veranstaltungswart'); ?></label><input type="email" id="<?php echo esc_attr($uid); ?>email" name="email" autocomplete="email" required maxlength="100"></p>
        <?php if ($allow_guests && $max_guests > 0): ?>
            <p><label for="<?php echo esc_attr($uid); ?>guests"><?php esc_html_e('Zusätzliche Begleitpersonen', 'veranstaltungswart'); ?></label><select id="<?php echo esc_attr($uid); ?>guests" name="guests">
                <?php for ($i = 0; $i <= $max_guests; $i++): ?><option value="<?php echo (int) $i; ?>"><?php echo (int) $i; ?></option><?php endfor; ?>
            </select></p>
        <?php else: ?><input type="hidden" name="guests" value="0"><?php endif; ?>
        <?php if ($allow_message): ?>
            <p><label for="<?php echo esc_attr($uid); ?>message"><?php echo esc_html(\VW\Events\EventSettings::get($event_id, 'vw_message_label')); ?><?php echo $required ? ' *' : esc_html__(' (optional)', 'veranstaltungswart'); ?></label>
            <textarea id="<?php echo esc_attr($uid); ?>message" name="message" rows="3" maxlength="5000" placeholder="<?php echo esc_attr(\VW\Events\EventSettings::get($event_id, 'vw_message_hint')); ?>" <?php echo $required ? 'required' : ''; ?>></textarea></p>
        <?php endif; ?>
        <?php if (array_filter($questions, function($q) { return $q['active']; })): ?>
            <input type="hidden" name="vw_questions_version" value="<?php echo esc_attr(\VW\Events\RegistrationQuestions::version($event_id)); ?>">
            <input type="hidden" name="vw_answers" value="{}">
            <div class="vw-questions" data-guest-label="<?php /* translators: %d: guest number, not a name. */ esc_attr_e('Begleitperson %d', 'veranstaltungswart'); ?>" data-required-message="<?php esc_attr_e('Bitte mindestens eine Option auswählen.', 'veranstaltungswart'); ?>">
                <?php \VW\Events\RegistrationQuestions::render_fields($questions, 'registration', 'registration', $uid); ?>
                <?php if (array_filter($questions, function($q) { return $q['active'] && $q['scope'] === 'person'; })): ?>
                    <fieldset class="vw-person-questions"><legend><?php esc_html_e('Du', 'veranstaltungswart'); ?></legend>
                        <?php \VW\Events\RegistrationQuestions::render_fields($questions, 'person', '0', $uid); ?>
                    </fieldset>
                    <div class="vw-guest-questions"></div>
                    <template><fieldset class="vw-person-questions"><legend></legend>
                        <?php \VW\Events\RegistrationQuestions::render_fields($questions, 'person', '__guest__', $uid); ?>
                    </fieldset></template>
                <?php endif; ?>
            </div>
            <noscript><p><?php esc_html_e('Bitte JavaScript aktivieren, um die Zusatzfragen für dich und deine Begleitpersonen auszufüllen.', 'veranstaltungswart'); ?></p></noscript>
        <?php endif; ?>
        <p><label class="vw-consent"><input type="checkbox" name="gdpr_consent" value="1" required><span><?php esc_html_e('Ich stimme der Verarbeitung meiner Angaben zur Bearbeitung der Anmeldung zu. Informationen in der', 'veranstaltungswart'); ?> <a href="<?php echo esc_url(get_privacy_policy_url() ?: home_url('/datenschutz/')); ?>" target="_blank" rel="noopener"><?php esc_html_e('Datenschutzerklärung', 'veranstaltungswart'); ?></a>. *</span></label></p>
        <button class="wp-element-button vw-submit" type="submit" <?php disabled((bool) array_filter($questions, function($q) { return $q['active']; })); ?>><?php echo esc_html($stats['is_full'] ? __('Auf Warteliste setzen', 'veranstaltungswart') : $atts['button']); ?></button>
    </form>
</div>
