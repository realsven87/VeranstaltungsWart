<?php
namespace VW\Dashboard;

use VW\Locations\LocationManager;
use VW\Persons\PersonManager; 

/**
 * Modul: Dashboard & Hilfe-Zentrale
 * Steuert die gesamte Menüstruktur und zeigt die Übersicht an.
 */
class DashboardManager {

    /**
     * Registriert die Hooks für das Admin-Menü.
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
        global $wpdb;
        
        $table_reg = $wpdb->prefix . 'vw_registrations';
        $pending_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_reg WHERE status = 'eingegangen'");
        
        $badge = '';
        if ($pending_count > 0) {
            $badge = sprintf(
                ' <span class="update-plugins count-%1$d"><span class="plugin-count">%1$s</span></span>',
                number_format_i18n($pending_count)
            );
        }

        add_menu_page(
            'VeranstaltungsWart',
            'VeranstaltungsWart' . $badge,
            'edit_posts', 
            'vw_dashboard', 
            [$this, 'render_dashboard'],
            'dashicons-calendar-alt',
            6
        );

        add_submenu_page('vw_dashboard', 'Dashboard', 'Dashboard', 'edit_posts', 'vw_dashboard', [$this, 'render_dashboard']);
        add_submenu_page('vw_dashboard', 'Alle Veranstaltungen', 'Veranstaltungen', 'edit_posts', 'edit.php?post_type=vw_event');
        add_submenu_page('vw_dashboard', 'Event-Kategorien', 'Kategorien', 'manage_options', 'edit-tags.php?taxonomy=vw_event_category&post_type=vw_event');
        add_submenu_page('vw_dashboard', 'Veranstaltungsorte', 'Orte', 'edit_posts', 'vw_locations', [LocationManager::class, 'render_admin_page']);
        add_submenu_page('vw_dashboard', 'Personen-Verwaltung', 'Personen', 'manage_options', 'vw_persons', [PersonManager::class, 'render_admin_page']);
        add_submenu_page('vw_dashboard', 'Hilfe & Dokumentation', 'Hilfe', 'edit_posts', 'vw_help', [$this, 'render_help_page']);
    }

    public function render_dashboard() {
        global $wpdb;
        $table_reg = $wpdb->prefix . 'vw_registrations';
        $table_persons = $wpdb->prefix . 'vw_persons';
        $current_time = current_time('mysql');

        $total_pending_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_reg WHERE status = 'eingegangen'");
        
        $active_events_count = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT p.ID) 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'vw_event' 
            AND p.post_status = 'publish'
            AND pm.meta_key = 'vw_event_date' 
            AND pm.meta_value >= %s
        ", $current_time));

        $pending = $wpdb->get_results("
            SELECT r.*, p.first_name, p.last_name, p.email, post.post_title 
            FROM $table_reg r
            JOIN $table_persons p ON r.person_id = p.id
            JOIN {$wpdb->posts} post ON r.event_post_id = post.ID
            WHERE r.status = 'eingegangen'
            ORDER BY r.registered_at ASC
            LIMIT 50
        ");

        include VW_PLUGIN_DIR . 'app/Dashboard/views/dashboard-view.php';
    }

    public function render_help_page() {
        include VW_PLUGIN_DIR . 'app/Dashboard/views/help-view.php';
    }
}