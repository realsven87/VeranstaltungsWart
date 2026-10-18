<?php
namespace VW\Events;

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

use VW\Mails\MailService;

/**
 * Modul: EventRepository
 * Die zentrale Daten-Schnittstelle. Hier wird die gesamte Datenbank-Logik
 * für Events, Registrierungen, Personen und Orte gekapselt.
 */
class EventRepository
{
    private $wpdb;
    private $table_regs;
    private $table_persons;
    private $table_locations;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_regs = $wpdb->prefix . 'vw_registrations';
        $this->table_persons = $wpdb->prefix . 'vw_persons';
        $this->table_locations = $wpdb->prefix . 'vw_locations';
    }

    /* --- REGISTRIERUNGEN --- */

    public function get_registration_stats($event_id)
    {
        $max = (int) get_post_meta($event_id, 'vw_max_capacity', true);

        // KORREKTUR: Nur 'bestätigt' zählt als belegter Platz
        $current = (int) $this->wpdb->get_var($this->wpdb->prepare("
        SELECT SUM(seats_total) FROM {$this->table_regs}
        WHERE event_post_id = %d AND status = 'bestätigt'
    ", $event_id));

        // Warteliste: Alles, was nicht bestätigt oder abgelehnt ist (z.B. 'eingegangen')
        $waitlist = (int) $this->wpdb->get_var($this->wpdb->prepare("
        SELECT SUM(seats_total) FROM {$this->table_regs}
        WHERE event_post_id = %d AND status = 'warteliste'
    ", $event_id));

        return [
            'max' => $max,
            'current' => $current,
            'confirmed_count' => $current, // Für die Anzeige in der Meta-Box
            'waitlist' => $waitlist,
            'pending_count' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COALESCE(SUM(seats_total), 0) FROM {$this->table_regs} WHERE event_post_id = %d AND status = 'eingegangen'", $event_id)),
            'waiting_count' => $waitlist, // Konsistenz für registration-list-table.php
            'max_capacity' => $max,
            'is_full' => ($max > 0 && $current >= $max)
        ];
    }

public function get_registrations_for_event($event_id, $orderby = 'registered_at', $order = 'DESC')
    {
        $whitelist = ['first_name', 'last_name', 'email', 'registered_at', 'seats_total'];
        $orderby = in_array($orderby, $whitelist) ? $orderby : 'registered_at';
        $order = ($order === 'ASC') ? 'ASC' : 'DESC';

        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT r.*, p.first_name, p.last_name, p.email, p.trust_status
            FROM {$this->table_regs} r
            LEFT JOIN {$this->table_persons} p ON r.person_id = p.id
            WHERE r.event_post_id = %d
            ORDER BY $orderby $order
        ", $event_id));
    }

    public function create_registration($data)
    {
        $this->wpdb->insert($this->table_regs, $data);
        return $this->wpdb->insert_id;
    }

    public function update_registration_status($reg_id, $status)
    {
        return $this->wpdb->update($this->table_regs, ['status' => $status], ['id' => $reg_id]);
    }

    public function delete_registration($reg_id)
    {
        return $this->wpdb->delete($this->table_regs, ['id' => $reg_id], ['%d']);
    }

    private static $locks = [];

    /** Serialize every capacity-changing path, including independent PHP requests. */
    public function with_event_lock($event_id, $callback) {
        $key = 'vw_' . md5(DB_NAME . $this->wpdb->prefix . ':' . (int) $event_id);
        if (isset(self::$locks[$key])) return $callback();
        if ((int) $this->wpdb->get_var($this->wpdb->prepare('SELECT GET_LOCK(%s, 5)', $key)) !== 1) {
            return new \WP_Error('busy', 'Die Veranstaltung wird gerade bearbeitet. Bitte erneut versuchen.');
        }
        self::$locks[$key] = true;
        try { return $callback(); }
        finally {
            unset(self::$locks[$key]);
            $this->wpdb->get_var($this->wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
        }
    }

    public function get_registration($id) {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_regs} WHERE id = %d", $id));
    }

    public function active_registration($event_id, $person_id) {
        return $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->table_regs} WHERE event_post_id = %d AND person_id = %d AND status IN ('eingegangen','warteliste','bestätigt') LIMIT 1", $event_id, $person_id));
    }

    public function change_status($reg_id, $status) {
        $reg = $this->get_registration($reg_id);
        if (!$reg || !in_array($status, ['bestätigt', 'abgelehnt', 'storniert'], true)) return new \WP_Error('invalid', 'Ungültige Anmeldung oder Status.');
        return $this->with_event_lock($reg->event_post_id, function() use ($reg_id, $status) {
            $reg = $this->get_registration($reg_id);
            if (!$reg) return new \WP_Error('invalid', 'Anmeldung nicht gefunden.');
            if ($reg->status === $status) return false;
            if ($status === 'bestätigt') {
                if (EventSettings::closed_reason($reg->event_post_id)) return new \WP_Error('closed', 'Die Veranstaltung ist nicht mehr zur Anmeldung geöffnet.');
                if ($reg->status === 'storniert') return new \WP_Error('cancelled', 'Stornierte Anmeldung kann nicht bestätigt werden.');
                $active = $this->active_registration($reg->event_post_id, $reg->person_id);
                if ($active && (int) $active !== (int) $reg_id) return new \WP_Error('duplicate', 'Eine andere aktive Anmeldung existiert bereits.');
                $stats = $this->get_registration_stats($reg->event_post_id);
                if ($stats['max'] > 0 && $stats['current'] + $reg->seats_total > $stats['max']) return new \WP_Error('full', 'Nicht genügend freie Plätze.');
            }
            if ($this->update_registration_status($reg_id, $status) === false) return new \WP_Error('database', 'Speichern fehlgeschlagen.');
            $slug = ['bestätigt' => 'anmeldebestaetigung', 'abgelehnt' => 'ablehnung-info', 'storniert' => 'storno-bestaetigung'];
            MailService::send_registration_mail($reg_id, $slug[$status]);
            if ($status !== 'bestätigt') $this->process_waitlist_move_up($reg->event_post_id, (int) EventSettings::get($reg->event_post_id, 'vw_max_capacity'));
            return true;
        });
    }

    public function process_waitlist_move_up($event_id, $max_capacity) {
        return $this->with_event_lock($event_id, function() use ($event_id, $max_capacity) {
            if (EventSettings::closed_reason($event_id)) return;
            $stats = $this->get_registration_stats($event_id);
            $available = $max_capacity > 0 ? $max_capacity - $stats['current'] : PHP_INT_MAX;
            $entries = $this->wpdb->get_results($this->wpdb->prepare("SELECT r.*, p.trust_status FROM {$this->table_regs} r LEFT JOIN {$this->table_persons} p ON r.person_id = p.id WHERE r.event_post_id = %d AND r.status IN ('warteliste', 'eingegangen') ORDER BY r.registered_at ASC, r.id ASC", $event_id));
            foreach ($entries as $entry) {
                if ($entry->seats_total > $available) continue; // A group is never split.
                if ($entry->trust_status === 'freigegeben') {
                    if ($this->update_registration_status($entry->id, 'bestätigt') !== false) {
                        $available -= $entry->seats_total;
                        MailService::send_registration_mail($entry->id, 'anmeldebestaetigung');
                    }
                } elseif ($entry->status === 'warteliste') {
                    if ($this->update_registration_status($entry->id, 'eingegangen') !== false) MailService::send_registration_mail($entry->id, 'freigabe-info');
                }
            }
        });
    }

    /* --- PERSONEN --- */

    public function get_all_persons($orderby = 'last_name', $order = 'ASC')
    {
    // Whitelist für erlaubte Spalten zur Sicherheit (SQL-Injection-Schutz)
    $allowed = ['last_name', 'first_name', 'email', 'trust_status'];
    $orderby = in_array($orderby, $allowed) ? $orderby : 'last_name';
    $order   = ($order === 'DESC') ? 'DESC' : 'ASC';

    return $this->wpdb->get_results("SELECT * FROM {$this->table_persons} ORDER BY $orderby $order");
    }

    public function get_person($id)
    {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_persons} WHERE id = %d", $id));
    }

    public function get_or_create_person_id($first, $last, $email)
    {
        $id = $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->table_persons} WHERE email = %s", $email));

        if ($id)
            return (int) $id;

        $this->wpdb->insert($this->table_persons, [
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'trust_status' => 'nicht_freigegeben',
            'created_at' => current_time('mysql')
        ]);

        return (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$this->table_persons} WHERE email = %s", $email));
    }

    public function update_person($id, $data)
    {
        if ($id > 0) {
            return $this->wpdb->update($this->table_persons, $data, ['id' => $id]);
        }
        // Beim Neuanlegen via Backend Zeitstempel setzen
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        return $this->wpdb->insert($this->table_persons, $data);
    }

    public function delete_person($id)
    {
        // 1. Zuerst alle Anmeldungen der Person suchen (um später die Wartelisten anzustoßen)
        $registrations = $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT event_post_id FROM {$this->table_regs} WHERE person_id = %d",
            $id
        ));

        // 2. Alle Anmeldungen/Tickets dieser Person löschen (verhindert die leeren Zeilen)
        $this->wpdb->delete($this->table_regs, ['person_id' => (int) $id]);

        // 3. Personendaten aus der Datenbank löschen
        $deleted = $this->wpdb->delete($this->table_persons, ['id' => (int) $id]);

        // 4. Warteliste-Logik für alle Events anstoßen, bei denen die Person angemeldet war
        if (!empty($registrations)) {
            foreach ($registrations as $reg) {
                $max_cap = (int) get_post_meta($reg->event_post_id, 'vw_max_capacity', true);
                if ($max_cap > 0) {
                    $this->process_waitlist_move_up($reg->event_post_id, $max_cap);
                }
            }
        }

        return $deleted;
    }

    public function is_email_taken($email, $exclude_id = 0)
    {
        return (bool) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$this->table_persons} WHERE email = %s AND id != %d",
            $email,
            $exclude_id
        ));
    }

    /* --- VERANSTALTUNGSORTE --- */

    public function get_all_locations()
    {
        return $this->wpdb->get_results("SELECT * FROM {$this->table_locations} ORDER BY name ASC");
    }

    public function get_location($id)
    {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$this->table_locations} WHERE id = %d", $id));
    }

    public function get_location_by_event($event_id)
    {
        $location_id = get_post_meta($event_id, 'vw_location_id', true);
        return $location_id ? $this->get_location($location_id) : null;
    }

    public function save_location($id, $data)
    {
        if ($id > 0) {
            return $this->wpdb->update($this->table_locations, $data, ['id' => $id]);
        }
        return $this->wpdb->insert($this->table_locations, $data);
    }

    public function delete_location($id)
    {
        return $this->wpdb->delete($this->table_locations, ['id' => (int) $id]);
    }
}
