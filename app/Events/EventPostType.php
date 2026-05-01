<?php
namespace VW\Events;

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
    }

    /**
     * Registriert den CPT 'vw_event'
     */
    public function create_post_type()
    {
        register_post_type('vw_event', [
            'labels' => [
                'name' => 'Veranstaltungen',
                'singular_name' => 'Veranstaltung',
                'menu_name' => 'Veranstaltungen',
                'all_items' => 'Alle Veranstaltungen',
                'add_new' => 'Neue hinzufügen',
                'add_new_item' => 'Neue Veranstaltung anlegen',
                'edit_item' => 'Veranstaltung bearbeiten',
                'view_item' => 'Anzeigen',
                'search_items' => 'Veranstaltungen suchen',
                'not_found' => 'Keine Veranstaltungen gefunden',
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-calendar-alt',
            'supports' => ['title', 'editor', 'thumbnail', 'author'],
            'show_in_rest' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_admin_bar' => true,
            'rewrite' => ['slug' => 'veranstaltungen'],
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
                'name' => 'Event-Kategorien',
                'singular_name' => 'Event-Kategorie',
                'search_items' => 'Kategorien suchen',
                'all_items' => 'Alle Kategorien',
                'edit_item' => 'Kategorie bearbeiten',
                'update_item' => 'Kategorie aktualisieren',
                'add_new_item' => 'Neue Kategorie hinzufügen',
                'new_item_name' => 'Name der neuen Kategorie',
                'menu_name' => 'Kategorien',
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
        $new_columns['cb'] = $columns['cb'];
        $new_columns['title'] = 'Veranstaltung';
        $new_columns['event_dt'] = 'Datum & Uhrzeit';
        $new_columns['location'] = 'Ort';
        $new_columns['registrations'] = 'Belegung';
        $new_columns['author'] = 'Verantwortlich';
        $new_columns['date'] = $columns['date'];
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
                        echo '<strong>' . $dt->format('d.m.Y') . '</strong><br><small>' . $dt->format('H:i') . ' Uhr</small>';
                    } catch (\Exception $e) {
                        echo 'Formatfehler';
                    }
                } else {
                    echo '<span class="description">—</span>';
                }
                break;

            case 'location':
                $location = $repo->get_location_by_event($post_id);
                echo esc_html($location ? $location->name : '—');
                break;

            case 'registrations':
                $stats = $repo->get_registration_stats($post_id);
                $full_label = $stats['is_full'] ? ' <span style="color:#d63638; font-weight:bold;">(VOLL)</span>' : '';
                echo '<strong>' . $stats['current'] . '</strong> / ' . ($stats['max'] ?: '∞') . $full_label;
                if ($stats['waitlist'] > 0) {
                    echo '<br><small style="color:#856404;">Warteliste: ' . $stats['waitlist'] . '</small>';
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
            wp_die('Keine Berechtigung.');
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
                MailService::send_registration_mail($reg_id, 'event-absage');

                // Nachrücker-Logik triggern
                if ($event_id) {
                    $max_cap = (int) get_post_meta($event_id, 'vw_max_capacity', true);
                    $repo->process_waitlist_move_up($event_id, $max_cap);
                }
            }

            // REDIRECT-LOGIK (Zurück zur Herkunftsseite)
            $referer = wp_get_referer();
            if ($referer) {
                // WICHTIG: 'action' hier nicht entfernen, sonst geht die Event-Bearbeitungsseite kaputt!
                $redirect_url = remove_query_arg(['status_updated'], $referer);
            } elseif ($event_id) {
                $redirect_url = admin_url('post.php?post=' . $event_id . '&action=edit');
            } else {
                $redirect_url = admin_url('admin.php?page=vw_dashboard');
            }

            wp_safe_redirect(add_query_arg('status_updated', '1', $redirect_url));
            exit;
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
            wp_die('Keine Berechtigung.');
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
        if (!current_user_can('edit_posts'))
            wp_die('Keine Berechtigung.');

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

            // REDIRECT-LOGIK (Zurück zur Herkunftsseite)
            $referer = wp_get_referer();
            if ($referer) {
                // WICHTIG: 'action' hier nicht entfernen!
                $redirect_url = remove_query_arg(['status_updated'], $referer);
            } elseif ($event_id) {
                $redirect_url = admin_url('post.php?post=' . $event_id . '&action=edit');
            } else {
                $redirect_url = admin_url('admin.php?page=vw_dashboard');
            }

            wp_safe_redirect(add_query_arg('status_updated', '1', $redirect_url));
            exit;
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
                'show_option_all' => 'Alle Verantwortlichen',
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
                    <p>Aktion erfolgreich durchgeführt und Warteliste aktualisiert.</p>
                  </div>';
        }
    }
}