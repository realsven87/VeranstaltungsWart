<?php
namespace VW\Persons;

use VW\Events\EventRepository;

/**
 * Modul: PersonManager
 * Verwaltet die Teilnehmerdatenbank und deren Vertrauensstatus.
 * Die Seite wird zentral über den DashboardManager in das Menü eingebunden.
 */
class PersonManager {

    /**
     * Registriert die Hooks für Admin-Actions.
     */
    public function register() {
        // Verarbeitet das Speichern/Aktualisieren (Formular & Quick-Link)
        add_action('admin_post_vw_save_person', [$this, 'handle_save_person']);
        
        // Verarbeitet das Löschen eines einzelnen Eintrags
        add_action('admin_post_vw_delete_person', [$this, 'handle_delete_person']);
        
        // NEU: Verarbeitet Mehrfach-Aktionen (Bulk Actions)
        add_action('admin_post_vw_bulk_person_action', [$this, 'handle_bulk_action']);
        
        // DSGVO / Datenschutz-Integration
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_privacy_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_privacy_eraser']);
        
        // Automatischer DSGVO-Cronjob (1x täglich)
        add_action('vw_daily_gdpr_cleanup', [$this, 'run_gdpr_cleanup']);
        if (!wp_next_scheduled('vw_daily_gdpr_cleanup')) {
            wp_schedule_event(time(), 'daily', 'vw_daily_gdpr_cleanup');
        }
    }

    /**
     * Rendert die Admin-Oberfläche.
     * Statisch, damit der DashboardManager via [PersonManager::class, 'render_admin_page'] darauf zugreifen kann.
     */
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) return;

        $repo = new EventRepository();
        
        // Edit-Logik: Falls eine ID übergeben wurde, Daten laden
        $edit_id     = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $edit_person = $edit_id ? $repo->get_person($edit_id) : null;
        
        // Alle Personen für die Tabelle laden
        $persons     = $repo->get_all_persons();

        include VW_PLUGIN_DIR . 'app/Persons/views/admin-page.php';
    }

    /**
     * Speichert oder aktualisiert eine Person.
     * Unterstützt POST (Formular) und GET (Quick-Trust-Link aus der Liste).
     */
    public function handle_save_person() {
        if (!current_user_can('manage_options')) {
            wp_die('Du hast nicht die erforderlichen Rechte.');
        }

        // Nonce-Prüfung für die Sicherheit (funktioniert für $_POST und $_GET)
        check_admin_referer('vw_person_action', 'vw_person_nonce');

        $repo = new EventRepository();
        
        // Daten aus $_REQUEST ziehen, um flexibel auf POST und GET zu reagieren
        $id    = isset($_REQUEST['person_id']) ? intval($_REQUEST['person_id']) : 0;
        $email = isset($_REQUEST['email']) ? sanitize_email($_REQUEST['email']) : '';
        
        if (empty($email)) {
            wp_redirect(admin_url('admin.php?page=vw_persons&status=error'));
            exit;
        }

        // Dubletten-Check: Existiert die E-Mail bereits bei einer ANDEREN ID?
        if ($repo->is_email_taken($email, $id)) {
            wp_redirect(admin_url('admin.php?page=vw_persons&status=email_exists'));
            exit;
        }

        /**
         * Status-Logik:
         * 1. Wenn ein expliziter Status übertragen wurde (Dropdown oder Link), nimm diesen.
         * 2. Wenn es eine neue Person ist ($id === 0) und im Backend angelegt wird: 'freigegeben'.
         * 3. Ansonsten Standard: 'eingegangen'.
         */
        if (isset($_REQUEST['trust_status'])) {
            $trust_status = sanitize_text_field($_REQUEST['trust_status']);
        } else {
            $trust_status = ($id === 0) ? 'freigegeben' : 'eingegangen';
        }

        $data = [
            'first_name'   => isset($_REQUEST['first_name']) ? sanitize_text_field($_REQUEST['first_name']) : '',
            'last_name'    => isset($_REQUEST['last_name']) ? sanitize_text_field($_REQUEST['last_name']) : '',
            'email'        => $email,
            'trust_status' => $trust_status
        ];

        // In der Datenbank speichern oder aktualisieren
        $repo->update_person($id, $data);

        // Zurück zur Liste mit Erfolgsmeldung
        wp_redirect(admin_url('admin.php?page=vw_persons&status=success'));
        exit;
    }

    /**
     * Löscht eine Person aus der Datenbank.
     */
    public function handle_delete_person() {
        if (!current_user_can('manage_options')) {
            wp_die('Du hast nicht die erforderlichen Rechte.');
        }

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        check_admin_referer('vw_delete_person_nonce_' . $id);

        $repo = new EventRepository();
        $repo->delete_person($id);

        wp_redirect(admin_url('admin.php?page=vw_persons&status=deleted'));
        exit;
    }

    /**
     * NEU: Verarbeitet Mehrfach-Aktionen (Bulk Actions) aus der Personen-Tabelle.
     */
    public function handle_bulk_action() {
        if (!current_user_can('manage_options')) {
            wp_die('Du hast nicht die erforderlichen Rechte.');
        }

        check_admin_referer('vw_bulk_person_nonce', '_wpnonce_bulk');

        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '-1';
        $person_ids = isset($_POST['person_ids']) ? array_map('intval', $_POST['person_ids']) : [];

        if ($action === '-1' || empty($person_ids)) {
            // Nichts ausgewählt oder keine Aktion -> einfach zurück
            wp_redirect(admin_url('admin.php?page=vw_persons'));
            exit;
        }

        $repo = new EventRepository();
        $count = 0;

        foreach ($person_ids as $id) {
            if ($action === 'delete') {
                $repo->delete_person($id);
                $count++;
            } elseif ($action === 'freigegeben' || $action === 'eingegangen') {
                $repo->update_person($id, ['trust_status' => $action]);
                $count++;
            }
        }

        // Zurückleiten mit Erfolgsmeldung und Anzahl der bearbeiteten Items
        wp_redirect(admin_url('admin.php?page=vw_persons&status=bulk_success&count=' . $count));
        exit;
    }

    /**
     * DSGVO: Registriert das Plugin für den WordPress Daten-Export
     */
    public function register_privacy_exporter($exporters) {
        $exporters['vw_events'] = [
            'exporter_friendly_name' => 'VeranstaltungsWart',
            'callback'               => [$this, 'privacy_export_data'],
        ];
        return $exporters;
    }

    /**
     * DSGVO: Registriert das Plugin für die WordPress Daten-Löschung
     */
    public function register_privacy_eraser($erasers) {
        $erasers['vw_events'] = [
            'eraser_friendly_name' => 'VeranstaltungsWart',
            'callback'             => [$this, 'privacy_erase_data'],
        ];
        return $erasers;
    }

    /**
     * DSGVO: Sammelt alle Daten einer E-Mail-Adresse für den ZIP-Export
     */
    public function privacy_export_data($email_address, $page = 1) {
        global $wpdb;
        $export_items = [];
        
        $table_persons = $wpdb->prefix . 'vw_persons';
        $table_reg     = $wpdb->prefix . 'vw_registrations';
        
        // Suche die Person
        $person = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_persons WHERE email = %s", $email_address));
        
        if ($person) {
            // 1. Stammdaten der Person
            $data = [
                ['name' => 'Vorname', 'value' => $person->first_name],
                ['name' => 'Nachname', 'value' => $person->last_name],
                ['name' => 'E-Mail', 'value' => $person->email],
                ['name' => 'Vertrauensstatus', 'value' => $person->trust_status],
            ];
            
            $export_items[] = [
                'group_id'    => 'vw_persons',
                'group_label' => 'VeranstaltungsWart - Personendaten',
                'item_id'     => "person-{$person->id}",
                'data'        => $data,
            ];
            
            // 2. Alle Anmeldungen der Person suchen
            $registrations = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_reg WHERE person_id = %d", $person->id));
            foreach ($registrations as $reg) {
                $event_title = get_the_title($reg->event_post_id);
                $reg_data = [
                    ['name' => 'Veranstaltung', 'value' => $event_title ?: 'Gelöschte Veranstaltung'],
                    ['name' => 'Registriert am', 'value' => $reg->registered_at],
                    ['name' => 'Status', 'value' => $reg->status],
                    ['name' => 'Begleitpersonen', 'value' => $reg->guests],
                    ['name' => 'Gesamtplätze', 'value' => $reg->seats_total],
                ];
                
                $export_items[] = [
                    'group_id'    => 'vw_registrations',
                    'group_label' => 'VeranstaltungsWart - Anmeldungen',
                    'item_id'     => "reg-{$reg->id}",
                    'data'        => $reg_data,
                ];
            }
        }
        
        return [
            'data' => $export_items,
            'done' => true,
        ];
    }

    /**
     * DSGVO: Löscht alle personenbezogenen Daten (Stammdaten & Anmeldungen) endgültig
     */
    public function privacy_erase_data($email_address, $page = 1) {
        global $wpdb;
        $table_persons = $wpdb->prefix . 'vw_persons';
        $table_reg     = $wpdb->prefix . 'vw_registrations';
        
        $items_removed  = false;
        $items_retained = false;
        $messages       = [];
        
        // Suche die Person
        $person = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_persons WHERE email = %s", $email_address));
        
        if ($person) {
            // 1. Zuerst alle Anmeldungen in den Events löschen
            $wpdb->delete($table_reg, ['person_id' => $person->id], ['%d']);
            
            // 2. Dann den Datensatz der Person selbst löschen
            $deleted = $wpdb->delete($table_persons, ['id' => $person->id], ['%d']);
            
            if ($deleted !== false) {
                $items_removed = true;
            } else {
                $items_retained = true;
                $messages[] = 'Fehler beim Löschen der Personendaten im VeranstaltungsWart.';
            }
        }
        
        return [
            'items_removed'  => $items_removed,
            'items_retained' => $items_retained,
            'messages'       => $messages,
            'done'           => true,
        ];
    }

    /**
     * DSGVO: Automatischer "Staubsauger" (Cronjob)
     * Löscht Personen, deren letzte Anmeldung länger als X Monate zurückliegt.
     */
    public function run_gdpr_cleanup() {
        global $wpdb;
        
        $table_persons = $wpdb->prefix . 'vw_persons';
        $table_reg     = $wpdb->prefix . 'vw_registrations';
        
        // EINSTELLUNG: Wie viele Monate sollen Daten maximal aufgehoben werden?
        $months_to_keep = 12;
        
        // Berechne das Stichtag-Datum (z.B. heute minus 24 Monate)
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$months_to_keep} months"));
        
        /**
         * Finde alle Personen, die:
         * 1. Sich in der Vergangenheit angemeldet haben
         * 2. Deren JÜNGSTE (letzte) Anmeldung älter ist als unser Stichtag
         */
        $query = $wpdb->prepare("
            SELECT p.email, MAX(r.registered_at) as last_activity
            FROM $table_persons p
            JOIN $table_reg r ON p.id = r.person_id
            GROUP BY p.id
            HAVING last_activity < %s
        ", $cutoff_date);
        
        $old_persons = $wpdb->get_results($query);
        
        if (!empty($old_persons)) {
            foreach ($old_persons as $person) {
                // Wir nutzen einfach unsere DSGVO-Funktion von vorhin, 
                // um die Person und all ihre Historien restlos zu löschen!
                $this->privacy_erase_data($person->email);
            }
        }
    }
}