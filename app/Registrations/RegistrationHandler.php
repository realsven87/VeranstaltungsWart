<?php
namespace VW\Registrations;
if (!defined('ABSPATH')) exit;
use VW\Events\EventRepository;
use VW\Events\EventSettings;
use VW\Events\EventPresentation;
use VW\Mails\MailService;

class RegistrationHandler {
    public function register() {
        foreach (['vw_submit_registration' => 'handle_registration', 'vw_cancel_registration' => 'handle_cancellation', 'vw_download_ics' => 'handle_ics_download'] as $action => $method) {
            add_action('admin_post_' . $action, [$this, $method]);
            add_action('admin_post_nopriv_' . $action, [$this, $method]);
        }
    }
    private function redirect($id, $key, $value) {
        $fallback = get_permalink($id) ?: home_url('/');
        $target = isset($_POST['redirect_to']) && is_string($_POST['redirect_to']) ? wp_validate_redirect(esc_url_raw(wp_unslash($_POST['redirect_to'])), $fallback) : $fallback;
        $target = remove_query_arg(['reg', 'reg_error', 'vw_event_id'], $target);
        wp_safe_redirect(add_query_arg([$key => $value, 'vw_event_id' => $id], $target));
        exit;
    }
    public function handle_registration() {
        $id = absint($_POST['event_id'] ?? 0);
        if (!isset($_POST['vw_reg_nonce_field']) || !is_string($_POST['vw_reg_nonce_field']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['vw_reg_nonce_field'])), 'vw_reg_nonce_action_' . $id)) $this->redirect($id, 'reg_error', 'nonce_fail');
        $repo = new EventRepository();
        $result = $repo->with_event_lock($id, function() use ($id, $repo) {
            if (EventSettings::closed_reason($id)) return new \WP_Error('closed');
            $input = [];
            foreach (['first_name', 'last_name', 'email', 'guests', 'message', 'gdpr_consent', 'website'] as $field) {
                if (isset($_POST[$field]) && !is_scalar($_POST[$field])) return new \WP_Error('invalid_data');
                $input[$field] = isset($_POST[$field]) ? wp_unslash((string) $_POST[$field]) : '';
            }
            if ($input['website'] !== '' || $input['gdpr_consent'] !== '1') return new \WP_Error('invalid_data');
            $first = sanitize_text_field($input['first_name']);
            $last = sanitize_text_field($input['last_name']);
            $email = sanitize_email($input['email']);
            // Accept international names, including apostrophes and combining marks.
            if (!$first || !$last || strlen($first) > 100 || strlen($last) > 100 || !preg_match("/^[\\p{L}\\p{M} '\x{2019}.\\-]+$/u", $first . $last) || !is_email($email) || strlen($email) > 100) return new \WP_Error('invalid_data');
            $guests = filter_var($input['guests'] === '' ? '0' : $input['guests'], FILTER_VALIDATE_INT);
            $limit = EventSettings::get($id, 'vw_allow_guests') === '1' ? (int) EventSettings::get($id, 'vw_max_guests') : 0;
            if ($guests === false || $guests < 0 || $guests > $limit) return new \WP_Error('invalid_guests');
            $message = EventSettings::get($id, 'vw_allow_message') === '1' ? sanitize_textarea_field($input['message']) : '';
            if (strlen($message) > 5000 || (EventSettings::get($id, 'vw_allow_message') === '1' && EventSettings::get($id, 'vw_message_required') === '1' && trim($message) === '')) return new \WP_Error('invalid_message');
            $answers = \VW\Events\RegistrationQuestions::validate_answers($id, $guests, isset($_POST['vw_answers']) ? wp_unslash($_POST['vw_answers']) : '', isset($_POST['vw_questions_version']) ? wp_unslash($_POST['vw_questions_version']) : '');
            if (is_wp_error($answers)) return $answers;
            $person_id = $repo->get_or_create_person_id($first, $last, $email);
            if (!$person_id) return new \WP_Error('database');
            if ($repo->active_registration($id, $person_id)) return new \WP_Error('duplicate');
            $person = $repo->get_person($person_id);
            $stats = $repo->get_registration_stats($id);
            $status = $person && $person->trust_status === 'freigegeben' ? 'bestätigt' : 'eingegangen';
            if ($stats['max'] > 0 && $stats['current'] + $guests + 1 > $stats['max']) $status = 'warteliste';
            $reg_id = $repo->create_registration(['event_post_id' => $id, 'person_id' => $person_id, 'guest_count' => $guests, 'message' => $message, 'answers' => wp_json_encode($answers), 'seats_total' => $guests + 1, 'status' => $status, 'registered_at' => current_time('mysql')]);
            if (!$reg_id) return new \WP_Error('database');
            return ['id' => $reg_id, 'status' => $status];
        });
        if (is_wp_error($result)) $this->redirect($id, 'reg_error', $result->get_error_code());
        $slugs = ['bestätigt' => 'anmeldebestaetigung', 'eingegangen' => 'eingangsbestaetigung', 'warteliste' => 'warteliste-info'];
        MailService::send_registration_mail($result['id'], $slugs[$result['status']]);
        if ($result['status'] === 'eingegangen') MailService::send_registration_mail($result['id'], 'freigabe-info');
        $this->redirect($id, 'reg', $result['status'] === 'warteliste' ? 'waitlist' : ($result['status'] === 'bestätigt' ? 'ok' : 'pending'));
    }
    private function token_registration() {
        global $wpdb;
        $id = absint($_REQUEST['id'] ?? 0);
        $hash = isset($_REQUEST['hash']) && is_string($_REQUEST['hash']) ? sanitize_text_field(wp_unslash($_REQUEST['hash'])) : '';
        $reg = $wpdb->get_row($wpdb->prepare("SELECT r.*, p.email FROM {$wpdb->prefix}vw_registrations r JOIN {$wpdb->prefix}vw_persons p ON r.person_id = p.id WHERE r.id = %d", $id));
        if (!$reg || !$hash || !hash_equals(wp_hash($reg->email . $reg->id), $hash)) wp_die(esc_html__('Dieser Link ist ungültig.', 'veranstaltungswart'));
        return $reg;
    }
    public function handle_cancellation() {
        $reg = $this->token_registration();
        // GET only displays confirmation: mail scanners must not cancel registrations.
        if (sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            nocache_headers();
            $html = '<h1>' . esc_html__('Anmeldung stornieren?', 'veranstaltungswart') . '</h1><p>' . esc_html(get_the_title($reg->event_post_id)) . '</p><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            foreach (['action' => 'vw_cancel_registration', 'id' => $reg->id, 'hash' => wp_hash($reg->email . $reg->id)] as $name => $value) $html .= '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '">';
            $html .= wp_nonce_field('vw_cancel_' . $reg->id, '_wpnonce', true, false);
            $html .= '<button type="submit">' . esc_html__('Stornierung bestätigen', 'veranstaltungswart') . '</button></form>';
            // All text, URLs and attributes were escaped while constructing this form; wp_kses_post would remove its input fields.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped form HTML assembled immediately above.
            wp_die($html, esc_html__('Anmeldung stornieren', 'veranstaltungswart'), ['response' => 200]);
        }
        check_admin_referer('vw_cancel_' . $reg->id);
        $result = (new EventRepository())->change_status($reg->id, 'storniert');
        if (is_wp_error($result)) wp_die(esc_html($result->get_error_message()));
        wp_safe_redirect(add_query_arg('cancel', 'success', get_permalink($reg->event_post_id)));
        exit;
    }
    public function handle_ics_download() {
        $reg = $this->token_registration();
        if ($reg->status !== 'bestätigt' || get_post_meta($reg->event_post_id, 'vw_event_canceled', true) === '1') wp_die(esc_html__('Der Kalenderdownload steht nur für bestätigte Anmeldungen zur Verfügung.', 'veranstaltungswart'));
        $start = EventSettings::date(EventSettings::get($reg->event_post_id, 'vw_event_date'));
        $end = EventSettings::date(EventSettings::get($reg->event_post_id, 'vw_event_end'));
        if (!$start) wp_die(esc_html__('Kein gültiger Termin hinterlegt.', 'veranstaltungswart'));
        $data = EventPresentation::values($reg->event_post_id, true);
        $utc = new \DateTimeZone('UTC');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//VeranstaltungsWart//DE', 'CALSCALE:GREGORIAN', 'BEGIN:VEVENT', 'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'DTSTART:' . $start->setTimezone($utc)->format('Ymd\THis\Z')];
        if ($end && $end > $start) $lines[] = 'DTEND:' . $end->setTimezone($utc)->format('Ymd\THis\Z');
        $lines[] = 'SUMMARY:' . $this->escape_ics($data['event_name']);
        $lines[] = 'LOCATION:' . $this->escape_ics($data['event_ort'] . ', ' . $data['event_adresse']);
        $lines[] = 'DESCRIPTION:' . $this->escape_ics($data['event_hinweis']);
        $lines[] = 'UID:vw-event-' . $reg->event_post_id . '@' . wp_parse_url(home_url(), PHP_URL_HOST);
        $lines[] = 'END:VEVENT'; $lines[] = 'END:VCALENDAR';
        $folded = [];
        foreach ($lines as $line) {
            // Fold by UTF-8 byte length without breaking a character (RFC 5545).
            $part = '';
            foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                if (strlen($part . $char) > 75) { $folded[] = $part; $part = ' '; }
                $part .= $char;
            }
            $folded[] = $part;
        }
        nocache_headers();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="termin.ics"');
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/calendar response, not HTML; every user-supplied ICS value is escaped by escape_ics().
        echo implode("\r\n", $folded) . "\r\n";
        exit;
    }
    private function escape_ics($text) {
        return str_replace(["\\", ",", ";", "\r", "\n"], ["\\\\", "\\,", "\\;", '', "\\n"], $text);
    }
}
