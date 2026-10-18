<?php
namespace VW\Events;
if (!defined('ABSPATH')) exit;

/** Shared settings for the classic metabox, REST editor and public form. */
class EventSettings {
    public static function fields() {
        return [
            'vw_event_date' => ['Datum und Beginn', 'date', ''],
            'vw_event_end' => ['Ende (optional)', 'date', ''],
            'vw_registration_deadline' => ['Anmeldeschluss (optional)', 'date', ''],
            'vw_location_id' => ['Veranstaltungsort', 'integer', 0],
            'vw_max_capacity' => ['Maximale Teilnehmerzahl (0 = unbegrenzt)', 'integer', 0],
            'vw_allow_guests' => ['Begleitpersonen erlauben', 'boolean', '1'],
            'vw_max_guests' => ['Maximale Begleitpersonen pro Anmeldung', 'integer', 5],
            'vw_allow_message' => ['Freitextfeld anzeigen', 'boolean', '0'],
            'vw_message_label' => ['Beschriftung Freitextfeld', 'text', 'Anmerkungen / Nachricht'],
            'vw_message_hint' => ['Hinweis im Freitextfeld', 'text', ''],
            'vw_message_required' => ['Freitextfeld ist Pflichtfeld', 'boolean', '0'],
            'vw_location_private' => ['Ort erst nach bestätigter Anmeldung mitteilen', 'boolean', '1'],
            'vw_event_notes' => ['Hinweise für Teilnehmer', 'text', ''],
            'vw_send_reminders' => ['Erinnerungen 7 und 3 Tage vorher senden', 'boolean', '1'],
        ];
    }
    public static function get($id, $key) {
        $fields = self::fields();
        $value = get_post_meta($id, $key, true);
        return $value === '' && isset($fields[$key]) ? $fields[$key][2] : $value;
    }
    public static function all($id) {
        $data = [];
        foreach (self::fields() as $key => $field) $data[$key] = self::get($id, $key);
        return $data;
    }
    public static function date($value) {
        if (!$value) return null;
        foreach (['Y-m-d\\TH:i', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $format) {
            $dt = \DateTimeImmutable::createFromFormat('!' . $format, $value, wp_timezone());
            if ($dt && $dt->format($format) === $value) return $dt;
        }
        return null;
    }
    public static function save($id, $input) {
        $data = self::all($id);
        foreach (self::fields() as $key => $field) {
            if (!array_key_exists($key, $input)) continue;
            $value = $input[$key];
            if (!is_scalar($value)) return new \WP_Error('invalid_setting', 'Ungültiger Einstellungswert.');
            switch ($field[1]) {
                case 'date':
                    $dt = self::date((string) $value);
                    if ($value !== '' && !$dt) return new \WP_Error('invalid_date', 'Bitte ein gültiges Datum angeben.');
                    $value = $dt ? $dt->format('Y-m-d H:i:s') : '';
                    break;
                case 'integer':
                    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) return new \WP_Error('invalid_number', 'Bitte eine nichtnegative ganze Zahl angeben.');
                    $value = (int) $value;
                    if ($key === 'vw_max_guests' && $value > 100) return new \WP_Error('invalid_guests', 'Maximal 100 Begleitpersonen erlaubt.');
                    break;
                case 'boolean': $value = in_array($value, [true, 1, '1'], true) ? '1' : '0'; break;
                default: $value = sanitize_textarea_field($value);
            }
            $data[$key] = $value;
        }
        // API callers can explicitly override capacity; otherwise a new location supplies it.
        if ((int) $data['vw_location_id'] !== (int) self::get($id, 'vw_location_id') && $data['vw_location_id'] && !array_key_exists('vw_max_capacity', $input)) {
            $location = (new EventRepository())->get_location($data['vw_location_id']);
            if ($location) $data['vw_max_capacity'] = max(0, (int) $location->default_capacity);
        }
        $start = self::date($data['vw_event_date']);
        $end = self::date($data['vw_event_end']);
        $deadline = self::date($data['vw_registration_deadline']);
        if ($end && (!$start || $end <= $start)) return new \WP_Error('invalid_end', 'Das Ende muss nach dem Beginn liegen.');
        if ($deadline && (!$start || $deadline > $start)) return new \WP_Error('invalid_deadline', 'Der Anmeldeschluss muss vor oder beim Beginn liegen.');
        if ($data['vw_location_id'] && !(new EventRepository())->get_location($data['vw_location_id'])) return new \WP_Error('invalid_location', 'Veranstaltungsort nicht gefunden.');
        $repo = new EventRepository();
        return $repo->with_event_lock($id, function() use ($id, $data, $repo) {
            $stats = $repo->get_registration_stats($id);
            if ($data['vw_max_capacity'] > 0 && $data['vw_max_capacity'] < $stats['current']) return new \WP_Error('capacity', 'Die Kapazität darf nicht unter den bereits bestätigten Plätzen liegen.');
            $old = (int) self::get($id, 'vw_max_capacity');
            foreach ($data as $key => $value) update_post_meta($id, $key, $value);
            if ($old > 0 && ($data['vw_max_capacity'] == 0 || $data['vw_max_capacity'] > $old)) $repo->process_waitlist_move_up($id, (int) $data['vw_max_capacity']);
            return true;
        });
    }
    public static function is_public($id) {
        $post = get_post($id);
        return $post && $post->post_type === 'vw_event' && $post->post_status === 'publish' && !$post->post_password;
    }
    public static function closed_reason($id) {
        if (!self::is_public($id)) return 'Diese Veranstaltung ist nicht zur Anmeldung verfügbar.';
        if (get_post_meta($id, 'vw_event_canceled', true) === '1') return 'Diese Veranstaltung wurde abgesagt.';
        $start = self::date(self::get($id, 'vw_event_date'));
        if (!$start) return 'Für diese Veranstaltung ist noch kein Termin hinterlegt.';
        $deadline = self::date(self::get($id, 'vw_registration_deadline')) ?: $start;
        if ($deadline <= current_datetime()) return 'Die Anmeldung ist geschlossen.';
        return '';
    }
}
