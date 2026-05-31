<?php 
namespace VW\Events;

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

use VW\Mails\MailService;  

/**
 * Modul: Veranstaltungsverwaltung (Kern)
 * Verantwortlich für die Registrierung des Custom Post Types (CPT) 'vw_event',
 * die Anpassung der Admin-Tabellen und die Verarbeitung administrativer Aktionen.
 */
class EventPostType
{
    /**
     * Registriert alle Hooks für den Post Type
     */
    public function register()
    {
        add_action('init', [$this, 'create_post_type']);
        add_action('init', [$this, 'create_event_taxonomy']);
        
        // Meta Boxen (Logik in EventMetaBox.php)
        $meta_boxes = new EventMetaBox();
        add_action('add_meta_boxes', [$meta_boxes, 'register']);
        add_action('save_post', [$meta_boxes, 'save']);
        
        // Admin Actions (Handler für Dashboard und MetaBoxen)
        add_action('admin_post_vw_update_reg_status', [$this, 'handle_status_update']);
        add_action('admin_post_vw_delete_registration', [$this, 'handle_delete_registration']);
        add_action('admin_post_vw_export_participants', [$this, 'handle_csv_export']);
        
        // Feedback-Meldungen im Admin-Bereich anzeigen
        add_action('admin_notices', [$this, 'show_admin_notices']);
        
        // Admin-Listenansicht (Übersichtstabelle aller Events)
        add_filter('manage_vw_event_posts_columns', [$this, 'set_custom_event_columns']);
        add_action('manage_vw_event_posts_custom_column', [$this, 'custom_event_column_content'], 10, 2);
        add_filter('manage_edit-vw_event_sortable_columns', [$this, 'set_sortable_event_columns']);
        add_action('pre_get_posts', [$this, 'event_column_orderby']);
        add_action('restrict_manage_posts', [$this, 'add_author_filter']);

        add_action('admin_post_vw_cancel_event', [$this, 'handle_cancel_event']);
        // Automatischer Cronjob für Erinnerungs-Mails (1x täglich)
        add_action('vw_daily_event_reminders', [$this, 'send_event_reminders']);
        if (!wp_next_scheduled('vw_daily_event_reminders')) {
            wp_schedule_event(time(), 'daily', 'vw_daily_event_reminders');
        }
    }

    /**
     * Registriert den CPT 'vw_event'
     */
    public function create_post_type()
    {
        register_post_type('vw_event', [
            'labels' => [
                'name'               => __('Veranstaltungen', 'veranstaltungswart'),
                'singular_name'      => __('Veranstaltung', 'veranstaltungswart'),
                'menu_name'          => __('Veranstaltungen', 'veranstaltungswart'),
                'all_items'          => __('Alle Veranstaltungen', 'veranstaltungswart'),
                'add_new'            => __('Neue hinzufügen', 'veranstaltungswart'),
                'add_new_item'       => __('Neue Veranstaltung anlegen', 'veranstaltungswart'),
                'edit_item'          => __('Veranstaltung bearbeiten', 'veranstaltungswart'),
                'view_item'          => __('Anzeigen', 'veranstaltungswart'),
                'search_items'       => __('Veranstaltungen suchen', 'veranstaltungswart'),
                'not_found'          => __('Keine Veranstaltungen gefunden', 'veranstaltungswart'),
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title', 'editor', 'thumbnail', 'author'],
            'show_in_rest' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => true,
            // Bei rewrite Slugs ist es oft besser, sie unübersetzt zu lassen, oder _x() für den Kontext zu nutzen
            'rewrite' => ['slug' => _x('veranstaltungen', 'URL slug', 'veranstaltungswart')],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    /**
     * Registriert die Taxonomie für Event-Kategorien
     */
    public function create_event_taxonomy()
    {
        register_taxonomy('vw_event_category', ['vw_event'], [
            'hierarchical' => true,
            'labels' => [
                'name'              => __('Event-Kategorien', 'veranstaltungswart'),
                'singular_name'     => __('Event-Kategorie', 'veranstaltungswart'),
                'search_items'      => __('Kategorien suchen', 'veranstaltungswart'),
                'all_items'         => __('Alle Kategorien', 'veranstaltungswart'),
                'edit_item'         => __('Kategorie bearbeiten', 'veranstaltungswart'),
                'update_item'       => __('Kategorie aktualisieren', 'veranstaltungswart'),
                'add_new_item'      => __('Neue Kategorie hinzufügen', 'veranstaltungswart'),
                'new_item_name'     => __('Name der neuen Kategorie', 'veranstaltungswart'),
                'menu_name'         => __('Kategorien', 'veranstaltungswart'),
            ],
            'show_ui' => true,
            'show_admin_column' => false,
            'show_in_rest' => true,
        ]);
    }

    /**
     * Spalten-Definition für die Admin-Übersicht
     */
    public function set_custom_event_columns($columns)
    {
        $new_columns = [];
        $new_columns['cb']            = $columns['cb'];
        $new_columns['title']         = __('Veranstaltung', 'veranstaltungswart');
        $new_columns['event_dt']      = __('Datum & Uhrzeit', 'veranstaltungswart');
        $new_columns['location']      = __('Ort', 'veranstaltungswart');
        $new_columns['registrations'] = __('Belegung', 'veranstaltungswart');
        $new_columns['author']        = __('Verantwortlich', 'veranstaltungswart');
        $new_columns['date']          = $columns['date'];
        return $new_columns;
    }

    /**
     * Spalten-Inhalt für die Admin-Übersicht
     */
    public function custom_event_column_content($column, $post_id)
    {
        $repo = new EventRepository();
        switch ($column) {
            case 'event_dt':
                $date_raw = get_post_meta($post_id, 'vw_event_date', true);
                if ($date_raw) {
                    try {
                        $dt = new \DateTime($date_raw);
                        // Hier 'Uhr' übersetzbar gemacht
                        echo '<strong>' . esc_html($dt->format('d.m.Y')) . '</strong><br><small>' . esc_html($dt->format('H:i')) . ' ' . esc_html__('Uhr', 'veranstaltungswart') . '</small>';
                    } catch (\Exception $e) {
                        echo esc_html__('Formatfehler', 'veranstaltungswart');
                    }
                } else {
                    echo '<span class="description">-</span>';
                }
                break;
            case 'location':
                $location = $repo->get_location_by_event($post_id);
                echo esc_html($location ? $location->name : '-');
                break;
            case 'registrations':
                $stats = $repo->get_registration_stats($post_id);
                // (VOLL) übersetzbar gemacht
                $full_label = $stats['is_full'] ? ' <span style="color:#d63638; font-weight:bold;">' . esc_html__('(VOLL)', 'veranstaltungswart') . '</span>' : '';
                echo '<strong>' . intval($stats['current']) . '</strong> / ' . ($stats['max'] ?: '-') . $full_label;
                
                if ($stats['waitlist'] > 0) {
                    // Warteliste übersetzbar gemacht
                    echo '<br><small style="color:#856404;">' . esc_html__('Warteliste:', 'veranstaltungswart') . ' ' . intval($stats['waitlist']) . '</small>';
                }
                break;
        }
    }

    /**
     * Handler: Status-Update einer Anmeldung (Bestätigen / Ablehnen)
     */
    public function handle_status_update()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Keine Berechtigung.', 'veranstaltungswart'));
        }
        
        $reg_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $status = isset($_GET['new_status']) ? sanitize_text_field($_GET['new_status']) : '';
        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
        
        check_admin_referer('vw_reg_action_' . $reg_id);
        
        if ($reg_id && $status) {
            $repo = new EventRepository();
            $repo->update_registration_status($reg_id, $status);
            
            // Mail-Trigger
            if ($status === 'bestätigt') {
                MailService::send_registration_mail($reg_id, 'anmeldebestaetigung');
            } elseif ($status === 'abgelehnt') {
                MailService::send_registration_mail($reg_id, 'ablehnung-info');
                // Nachrücker-Logik triggern
                if ($event_id) {
                    $max_cap = (int) get_post_meta($event_id, 'vw_max_capacity', true);
                    $repo->process_waitlist_move_up($event_id, $max_cap);
                }
            }
            
            // Saubere Weiterleitung
            $this->redirect_back($event_id, 'status_updated');
        }
        
        wp_safe_redirect(admin_url('admin.php?page=vw_dashboard'));
        exit;
    }

    /**
     * Handler: Teilnehmer-Export (CSV) - Delegiert an den EventExporter
     */
    public function handle_csv_export()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Keine Berechtigung.', 'veranstaltungswart'));
        }
        
        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
        check_admin_referer('vw_export_nonce');
        
        if ($event_id) {
            $exporter = new EventExporter();
            $exporter->download_participants_csv($event_id);
        }
        exit;
    }

    /**
     * Handler: Löschen einer Anmeldung
     */
    public function handle_delete_registration()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Keine Berechtigung.', 'veranstaltungswart'));
        }
        
        $reg_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
        
        check_admin_referer('vw_delete_reg_nonce_' . $reg_id);
        
        if ($reg_id) {
            $repo = new EventRepository();
            $repo->delete_registration($reg_id);
            
            // Auch beim Löschen die Warteliste prüfen
            if ($event_id) {
                $max_cap = (int) get_post_meta($event_id, 'vw_max_capacity', true);
                $repo->process_waitlist_move_up($event_id, $max_cap);
            }
            
            // Saubere Weiterleitung
            $this->redirect_back($event_id, 'status_updated');
        }
        
        wp_safe_redirect(admin_url('admin.php?page=vw_dashboard'));
        exit;
    }

    /**
     * Handler: Komplette Veranstaltung absagen und alle Teilnehmer benachrichtigen
     */
    public function handle_cancel_event()
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Keine Berechtigung.');
        }

        $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
        check_admin_referer('vw_cancel_event_' . $event_id);

        if ($event_id) {
            $repo = new EventRepository();
            // Holt alle Personen, die für dieses Event registriert sind
            $registrations = $repo->get_registrations_for_event($event_id);

            if (!empty($registrations)) {
                foreach ($registrations as $reg) {
                    $rid = isset($reg->id) ? $reg->id : (isset($reg->reg_id) ? $reg->reg_id : 0);
                    
                    // Nur Personen anschreiben, die nicht ohnehin schon storniert oder abgelehnt haben
                    if ($rid && $reg->status !== 'storniert' && $reg->status !== 'abgelehnt') {
                        // 1. Absage-Mail senden
                        MailService::send_registration_mail($rid, 'event-abgesagt');
                        // 2. Status auf abgelehnt setzen, damit die Plätze freigegeben werden
                        $repo->update_registration_status($rid, 'abgelehnt');
                    }
                }
            }

            // 3. In den Event-Metadaten hinterlegen, dass das Event abgesagt wurde
            update_post_meta($event_id, 'vw_event_canceled', '1');

            // Saubere Weiterleitung
            $this->redirect_back($event_id, 'cancellation_success');
        }

        wp_safe_redirect(admin_url('admin.php?page=vw_dashboard'));
        exit;
    }

    public function set_sortable_event_columns($columns)
    {
        $columns['event_dt'] = 'event_dt';
        return $columns;
    }

    public function event_column_orderby($query)
    {
        if (!is_admin() || !$query->is_main_query())
            return;
            
        if ('event_dt' === $query->get('orderby')) {
            $query->set('meta_key', 'vw_event_date');
            $query->set('orderby', 'meta_value');
        }
    }

    public function add_author_filter()
    {
        global $typenow;
        if ($typenow === 'vw_event') {
            wp_dropdown_users([
                'show_option_all' => __('Alle Verantwortlichen', 'veranstaltungswart'),
                'name' => 'author',
                'selected' => isset($_GET['author']) ? (int) $_GET['author'] : 0,
            ]);
        }
    }

    /**
     * Zeigt Erfolgsmeldungen an, wenn Status-Updates durchgeführt wurden.
     */
    public function show_admin_notices() {
        if (isset($_GET['status_updated']) && $_GET['status_updated'] == '1') {
            echo '<div class="notice notice-success is-dismissible">
                    <p>' . esc_html__('Aktion erfolgreich durchgeführt und Warteliste aktualisiert.', 'veranstaltungswart') . '</p>
                  </div>';
        }
        if (isset($_GET['cancellation_success']) && $_GET['cancellation_success'] == '1') {
            echo '<div class="notice notice-success is-dismissible">
                    <p>Die Veranstaltung wurde erfolgreich abgesagt. Alle aktiven Teilnehmer und Wartelisten-Personen wurden per E-Mail benachrichtigt.</p>
                  </div>';
        }
    }

    /**
     * Hilfsmethode für saubere Weiterleitungen nach Aktionen (DRY-Prinzip)
     */
    private function redirect_back($event_id, $success_arg = 'status_updated') {
        $referer = wp_get_referer();
        if ($referer) {
            $redirect_url = remove_query_arg([$success_arg], $referer);
        } elseif ($event_id) {
            $redirect_url = admin_url('post.php?post=' . $event_id . '&action=edit');
        } else {
            $redirect_url = admin_url('admin.php?page=vw_dashboard');
        }
        wp_safe_redirect(add_query_arg($success_arg, '1', $redirect_url));
        exit;
    }

    /**
     * Cronjob: Versendet Erinnerungen 7 und 3 Tage vor dem Event.
     */
    public function send_event_reminders() {
        $events = get_posts([
            'post_type' => 'vw_event',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ]);
        
        $today = new \DateTime('today');
        $repo = new EventRepository();

        foreach ($events as $event) {
            // Wenn Event abgesagt wurde -> überspringen
            if (get_post_meta($event->ID, 'vw_event_canceled', true) === '1') continue;
            
            // Wenn Opt-Out (Häkchen entfernt) -> überspringen
            $send_reminders = get_post_meta($event->ID, 'vw_send_reminders', true);
            if ($send_reminders === '0') continue; // Opt-Out check. Wenn leer oder '1', weitermachen.

            $event_date_raw = get_post_meta($event->ID, 'vw_event_date', true);
            if (!$event_date_raw) continue;

            try {
                $event_date = new \DateTime($event_date_raw);
                $event_date->setTime(0, 0, 0); // Nur das reine Datum vergleichen
                
                // Tage bis zum Event berechnen
                $interval = $today->diff($event_date);
                $days_diff = (int)$interval->format('%R%a'); // Gibt z.B. +7 oder -2 zurück

                // Befinden wir uns exakt 7 oder 3 Tage vor dem Event?
                if ($days_diff === 7 || $days_diff === 3) {
                    $registrations = $repo->get_registrations_for_event($event->ID);
                    if (!empty($registrations)) {
                        foreach ($registrations as $reg) {
                            $rid = isset($reg->id) ? $reg->id : (isset($reg->reg_id) ? $reg->reg_id : 0);
                            
                            // E-Mail NUR an Teilnehmer versenden, die auch wirklich bestätigt sind!
                            if ($rid && $reg->status === 'bestätigt') {
                                MailService::send_registration_mail($rid, 'event-erinnerung');
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
}