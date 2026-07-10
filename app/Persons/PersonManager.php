<?php 
namespace VW\Persons;

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

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
        
        // NEU: Import-Funktion
        add_action('admin_post_vw_import_persons', [$this, 'handle_import_persons']);

        // DSGVO / Datenschutz-Integration
        add_filter('wp_privacy_personal_data_exporters', [$this, 'register_privacy_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'register_privacy_eraser']);
        
        // Automatischer DSGVO-Cronjob (1x täglich)
        add_action('vw_daily_gdpr_cleanup', [$this, 'run_gdpr_cleanup']);
        if (!wp_next_scheduled('vw_daily_gdpr_cleanup')) {
            wp_schedule_event(time(), 'daily', 'vw_daily_gdpr_cleanup');
        }

        add_action('admin_post_vw_approve_person', [$this, 'handle_approve_person']);
    }

    /**
     * Rendert die Admin-Oberfläche.
     */
    public static function render_admin_page() {
        if (!current_user_can('manage_options')) return;
    
        $repo = new EventRepository();
        
        // Sortier-Parameter aus der URL lesen
        $orderby = isset($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'last_name';
        $order   = isset($_GET['order']) ? sanitize_text_field($_GET['order']) : 'ASC';
        
        $edit_id     = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $edit_person = $edit_id ? $repo->get_person($edit_id) : null;
        
        // Sortierung übergeben
        $persons     = $repo->get_all_persons($orderby, $order);
        
        include VW_PLUGIN_DIR . 'app/Persons/views/admin-page.php';
    }

    /**
     * Speichert oder aktualisiert eine Person.
     */
    public function handle_save_person() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Du hast nicht die erforderlichen Rechte.', 'veranstaltungswart'));
        }
        
        check_admin_referer('vw_person_action', 'vw_person_nonce');
        
        $repo = new EventRepository();
        
        $id    = isset($_REQUEST['person_id']) ? intval($_REQUEST['person_id']) : 0;
        $email = isset($_REQUEST['email']) ? sanitize_email($_REQUEST['email']) : '';
        
        if (empty($email)) {
            wp_redirect(admin_url('admin.php?page=vw_persons&status=error'));
            exit;
        }

        if ($repo->is_email_taken($email, $id)) {
            wp_redirect(admin_url('admin.php?page=vw_persons&status=email_exists'));
            exit;
        }

        $trust_status = isset($_REQUEST['trust_status']) ? sanitize_text_field($_REQUEST['trust_status']) : (($id === 0) ? 'freigegeben' : 'eingegangen');

        $data = [
            'first_name'   => isset($_REQUEST['first_name']) ? sanitize_text_field($_REQUEST['first_name']) : '',
            'last_name'    => isset($_REQUEST['last_name']) ? sanitize_text_field($_REQUEST['last_name']) : '',
            'email'        => $email,
            'trust_status' => $trust_status
        ];

        $repo->update_person($id, $data);
        
        wp_redirect(admin_url('admin.php?page=vw_persons&status=success'));
        exit;
    }

    /**
     * Löscht eine Person aus der Datenbank.
     */
    public function handle_delete_person() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Du hast nicht die erforderlichen Rechte.', 'veranstaltungswart'));
        }
        
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        check_admin_referer('vw_delete_person_nonce_' . $id);
        
        $repo = new EventRepository();
        $repo->delete_person($id);
        
        wp_redirect(admin_url('admin.php?page=vw_persons&status=deleted'));
        exit;
    }

    /**
     * Setzt den Status einer einzelnen Person per 1-Klick auf "freigegeben".
     */
    public function handle_approve_person() {
        if (!current_user_can('edit_posts')) {
            wp_die(__('Keine Berechtigung.', 'veranstaltungswart'));
        }

        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        check_admin_referer('vw_approve_person_nonce_' . $id);

        if ($id) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'vw_persons';
            $wpdb->update(
                $table_name,
                ['trust_status' => 'freigegeben'],
                ['id' => $id],
                ['%s'],
                ['%d']
            );
        }

        wp_safe_redirect(admin_url('admin.php?page=vw_persons&status=approved'));
        exit;
    }

    /**
     * Verarbeitet Mehrfach-Aktionen (Bulk Actions).
     */
    public function handle_bulk_action() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Du hast nicht die erforderlichen Rechte.', 'veranstaltungswart'));
        }
        
        check_admin_referer('vw_bulk_person_nonce', '_wpnonce_bulk');
        
        $action = isset($_POST['bulk_action']) ? sanitize_text_field($_POST['bulk_action']) : '-1';
        $person_ids = isset($_POST['person_ids']) ? array_map('intval', $_POST['person_ids']) : [];
        
        if ($action !== '-1' && !empty($person_ids)) {
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
            wp_redirect(admin_url('admin.php?page=vw_persons&status=bulk_success&count=' . $count));
            exit;
        }
        wp_redirect(admin_url('admin.php?page=vw_persons'));
        exit;
    }

    /**
     * Verarbeitet den Teilnehmer-Import (CSV).
     */
    public function handle_import_persons() {
        if (!current_user_can('manage_options')) wp_die('Zugriff verweigert.');
        check_admin_referer('vw_import_persons_nonce', 'vw_import_nonce');

        if (!empty($_FILES['csv_file']['tmp_name'])) {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            $repo = new EventRepository();
            $count = 0;

            while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
                if (count($data) >= 3) {
                    $p_id = $repo->get_or_create_person_id(
                        sanitize_text_field($data[0]), 
                        sanitize_text_field($data[1]), 
                        sanitize_email($data[2])
                    );
                    $repo->update_person($p_id, ['trust_status' => 'freigegeben']);
                    $count++;
                }
            }
            fclose($handle);
            wp_redirect(admin_url('admin.php?page=vw_persons&status=bulk_success&count=' . $count));
            exit;
        }
    }

    /**
     * DSGVO: Registriert den Daten-Export.
     */
    public function register_privacy_exporter($exporters) {
        $exporters['vw_events'] = [
            'exporter_friendly_name' => __('VeranstaltungsWart', 'veranstaltungswart'),
            'callback'               => [$this, 'privacy_export_data'],
        ];
        return $exporters;
    }

    /**
     * DSGVO: Registriert die Daten-Löschung.
     */
    public function register_privacy_eraser($erasers) {
        $erasers['vw_events'] = [
            'eraser_friendly_name' => __('VeranstaltungsWart', 'veranstaltungswart'),
            'callback'             => [$this, 'privacy_erase_data'],
        ];
        return $erasers;
    }

    /**
     * DSGVO: Daten-Export Logik.
     */
    public function privacy_export_data($email_address, $page = 1) {
        global $wpdb;
        $export_items = [];
        $table_persons = $wpdb->prefix . 'vw_persons';
        $table_reg     = $wpdb->prefix . 'vw_registrations';
        
        $person = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_persons WHERE email = %s", $email_address));
        
        if ($person) {
            $data = [
                ['name' => __('Vorname', 'veranstaltungswart'), 'value' => $person->first_name],
                ['name' => __('Nachname', 'veranstaltungswart'), 'value' => $person->last_name],
                ['name' => __('E-Mail', 'veranstaltungswart'), 'value' => $person->email],
                ['name' => __('Vertrauensstatus', 'veranstaltungswart'), 'value' => $person->trust_status],
            ];
            $export_items[] = [
                'group_id'    => 'vw_persons',
                'group_label' => __('VeranstaltungsWart - Personendaten', 'veranstaltungswart'),
                'item_id'     => "person-{$person->id}",
                'data'        => $data,
            ];
            $registrations = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_reg WHERE person_id = %d", $person->id));
            foreach ($registrations as $reg) {
                $event_title = get_the_title($reg->event_post_id);
                $reg_data = [
                    ['name' => __('Veranstaltung', 'veranstaltungswart'), 'value' => $event_title ?: __('Gelöschte Veranstaltung', 'veranstaltungswart')],
                    ['name' => __('Registriert am', 'veranstaltungswart'), 'value' => $reg->registered_at],
                    ['name' => __('Status', 'veranstaltungswart'), 'value' => $reg->status],
                    ['name' => __('Begleitpersonen', 'veranstaltungswart'), 'value' => $reg->guests],
                    ['name' => __('Gesamtplätze', 'veranstaltungswart'), 'value' => $reg->seats_total],
                ];
                $export_items[] = [
                    'group_id'    => 'vw_registrations',
                    'group_label' => __('VeranstaltungsWart - Anmeldungen', 'veranstaltungswart'),
                    'item_id'     => "reg-{$reg->id}",
                    'data'        => $reg_data,
                ];
            }
        }
        return ['data' => $export_items, 'done' => true];
    }

    /**
     * DSGVO: Löschungs-Logik.
     */
    public function privacy_erase_data($email_address, $page = 1) {
        global $wpdb;
        $table_persons = $wpdb->prefix . 'vw_persons';
        $table_reg     = $wpdb->prefix . 'vw_registrations';
        $items_removed  = false;
        $items_retained = false;
        $messages       = [];
        
        $person = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table_persons WHERE email = %s", $email_address));
        
        if ($person) {
            $wpdb->delete($table_reg, ['person_id' => $person->id], ['%d']);
            $deleted = $wpdb->delete($table_persons, ['id' => $person->id], ['%d']);
            
            if ($deleted !== false) {
                $items_removed = true;
            } else {
                $items_retained = true;
                $messages[] = __('Fehler beim Löschen der Personendaten im VeranstaltungsWart.', 'veranstaltungswart');
            }
        }
        return ['items_removed'  => $items_removed, 'items_retained' => $items_retained, 'messages' => $messages, 'done' => true];
    }

    /**
     * Cronjob: DSGVO-Bereinigung.
     */
    public function run_gdpr_cleanup() {
        global $wpdb;
        $table_persons = $wpdb->prefix . 'vw_persons';
        $table_reg     = $wpdb->prefix . 'vw_registrations';
        
        $months_to_keep = 12;
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$months_to_keep} months"));
        
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
                $this->privacy_erase_data($person->email);
            }
        }
    }
}