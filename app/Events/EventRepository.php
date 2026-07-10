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
        WHERE event_post_id = %d AND status = 'eingegangen'
    ", $event_id));

        return [
            'max' => $max,
            'current' => $current,
            'confirmed_count' => $current, // Für die Anzeige in der Meta-Box
            'waitlist' => $waitlist,
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

    public function process_waitlist_move_up($event_id, $max_capacity)
    {
        $stats = $this->get_registration_stats($event_id);
        $available_slots = $max_capacity - $stats['current'];

        if ($available_slots <= 0)
            return;

        // Wir holen die nächsten Kandidaten (Warteliste oder Eingegangen)
        $waitlist_entries = $this->wpdb->get_results($this->wpdb->prepare("
        SELECT r.id, r.seats_total, r.person_id, p.trust_status 
        FROM {$this->table_regs} r
        LEFT JOIN {$this->table_persons} p ON r.person_id = p.id
        WHERE r.event_post_id = %d AND r.status IN ('warteliste', 'eingegangen')
        ORDER BY r.registered_at ASC
    ", $event_id));

        foreach ($waitlist_entries as $entry) {
            if ($entry->seats_total <= $available_slots) {

                // LOGIK: Ist die Person global bestätigt?
                if ($entry->trust_status === 'bestätigt') {
                    // JA: Automatisch bestätigen + Mail
                    $this->update_registration_status($entry->id, 'bestätigt');
                    MailService::send_registration_mail($entry->id, 'anmeldebestaetigung');
                    $available_slots -= $entry->seats_total;
                } else {
                    // NEIN: Nur Status ändern, damit du im Backend manuell bestätigen kannst
                    // Wir setzen ihn auf 'eingegangen' (oder 'nachgerückt'), damit er in der Liste oben steht
                    $this->update_registration_status($entry->id, 'eingegangen');
                    // KEINE Mail hier! Du bestätigst später manuell im Backend.
                }
            }
            if ($available_slots <= 0)
                break;
        }

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

        return (int) $this->wpdb->insert_id;
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