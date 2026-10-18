<?php
namespace VW\Dashboard;

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

use VW\Locations\LocationManager;
use VW\Persons\PersonManager;

/**
 * Modul: Dashboard & Hilfe-Zentrale
 * Steuert die gesamte Menüstruktur und zeigt die Übersicht an.
 */
class DashboardManager {

    /**
     * Registriert die Hooks für das Admin-Menü
     */
    public function register() {
        // Menüaufbau (Priorität 5, damit es weit oben erscheint)
        add_action('admin_menu', [$this, 'setup_admin_menu'], 5);

        // Das Standard-Menü des CPTs (vw_event) aus der Hauptleiste entfernen
        add_action('admin_menu', function() {
            remove_menu_page('edit.php?post_type=vw_event');
        }, 999);
    }

    public function setup_admin_menu() {
        $pending_count = $this->get_dashboard_data(0)['total_pending_count'];

        $badge = '';
        if ($pending_count > 0) {
            $badge = sprintf(
                ' <span class="update-plugins count-%1$d"><span class="plugin-count">%2$s</span></span>',
                $pending_count, number_format_i18n($pending_count)
            );
        }

        // Hauptmenü
        add_menu_page(
            __('VeranstaltungsWart', 'veranstaltungswart'),
            __('VeranstaltungsWart', 'veranstaltungswart') . $badge,
            'manage_vw_events',
            'vw_dashboard',
            [$this, 'render_dashboard'],
            'dashicons-calendar-alt',
            6
        );

        // Untermenüs
        add_submenu_page('vw_dashboard', __('Dashboard', 'veranstaltungswart'), __('Dashboard', 'veranstaltungswart'), 'manage_vw_events', 'vw_dashboard', [$this, 'render_dashboard']);
        add_submenu_page('vw_dashboard', __('Alle Veranstaltungen', 'veranstaltungswart'), __('Veranstaltungen', 'veranstaltungswart'), 'manage_vw_events', 'edit.php?post_type=vw_event');
        add_submenu_page('vw_dashboard', __('Event-Kategorien', 'veranstaltungswart'), __('Kategorien', 'veranstaltungswart'), 'manage_options', 'edit-tags.php?taxonomy=vw_event_category&post_type=vw_event');
        add_submenu_page('vw_dashboard', __('Veranstaltungsorte', 'veranstaltungswart'), __('Orte', 'veranstaltungswart'), 'manage_options', 'vw_locations', [LocationManager::class, 'render_admin_page']);
        add_submenu_page('vw_dashboard', __('Personen-Verwaltung', 'veranstaltungswart'), __('Personen', 'veranstaltungswart'), 'manage_options', 'vw_persons', [PersonManager::class, 'render_admin_page']);
        add_submenu_page('vw_dashboard', __('Hilfe & Dokumentation', 'veranstaltungswart'), __('Hilfe', 'veranstaltungswart'), 'manage_vw_events', 'vw_help', [$this, 'render_help_page']);
    }

    /** Scope every count and row to the same per-event permission as the actions. */
    public function get_dashboard_data($page = 1) {
        $empty = ['total_pending_count' => 0, 'active_events_count' => 0, 'pending' => [], 'page' => max(1, (int) $page)];
        if (!current_user_can('manage_vw_events')) return $empty;
        $ids = get_posts([
            'post_type' => 'vw_event', 'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
            'posts_per_page' => -1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC',
        ]);
        $allowed = array_filter($ids, [\VW\Core\Capabilities::class, 'can_manage_event']);
        if (!$allowed) return $empty;
        // Only integer IDs obtained from WordPress enter this SQL fragment.
        $scope = implode(',', array_map('absint', $allowed));
        global $wpdb;
        $table_reg = $wpdb->prefix . 'vw_registrations';
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_reg WHERE status = 'eingegangen' AND event_post_id IN ($scope)");
        $active = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.ID IN ($scope) AND p.post_status = 'publish' AND pm.meta_key = 'vw_event_date' AND pm.meta_value >= %s
             AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} canceled WHERE canceled.post_id = p.ID AND canceled.meta_key = 'vw_event_canceled' AND canceled.meta_value = '1')",
            current_time('mysql')
        ));
        $current_page = min(max(1, (int) $page), max(1, (int) ceil($count / 50)));
        $pending = $page > 0 ? $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, p.first_name, p.last_name, p.email, post.post_title
             FROM $table_reg r JOIN {$wpdb->prefix}vw_persons p ON r.person_id = p.id
             JOIN {$wpdb->posts} post ON r.event_post_id = post.ID
             WHERE r.status = 'eingegangen' AND r.event_post_id IN ($scope)
             ORDER BY r.registered_at ASC, r.id ASC LIMIT 50 OFFSET %d", ($current_page - 1) * 50
        )) : [];
        return ['total_pending_count' => $count, 'active_events_count' => $active, 'pending' => $pending, 'page' => $current_page];
    }

    public function render_dashboard() {
        if (!current_user_can('manage_vw_events')) return;
        $data = $this->get_dashboard_data(isset($_GET['vw_page']) ? absint($_GET['vw_page']) : 1);
        $total_pending_count = $data['total_pending_count'];
        $active_events_count = $data['active_events_count'];
        $pending = $data['pending'];
        $page = $data['page'];
        include VW_PLUGIN_DIR . 'app/Dashboard/views/dashboard-view.php';
    }

    public function render_help_page() {
        include VW_PLUGIN_DIR . 'app/Dashboard/views/help-view.php';
    }
}
