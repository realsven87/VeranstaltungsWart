<?php 
namespace VW\Locations;

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

use VW\Events\EventRepository;

/**
 * Modul: LocationManager
 * Steuert die CRUD-Operationen für Veranstaltungsorte.
 */
class LocationManager {

    /**
     * Registriert die Hooks für das Speichern und Löschen.
     * Diese Methode muss in der Haupt-Plugin-Klasse aufgerufen werden.
     */
    public function register() {
        add_action('admin_post_vw_save_location', [$this, 'handle_save_location']);
        add_action('admin_post_vw_delete_location', [$this, 'handle_delete_location']);
    }

    /**
     * Rendert die Verwaltungsseite im Backend.
     */
    public static function render_admin_page() {
        if (!current_user_can('edit_posts')) return;
        
        $repo = new EventRepository();
        
        $edit_id       = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
        $edit_location = $edit_id ? $repo->get_location($edit_id) : null;
        $locations     = $repo->get_all_locations();
        
        include VW_PLUGIN_DIR . 'app/Locations/views/admin-page.php';
    }

    /**
     * Verarbeitet das Speichern/Update eines Ortes.
     */
    public function handle_save_location() {
        if (!current_user_can('edit_posts')) wp_die(esc_html__('Keine ausreichenden Rechte.', 'veranstaltungswart'));
        check_admin_referer('vw_location_action', 'vw_location_nonce');
        
        $repo = new EventRepository();
        $id   = intval($_POST['location_id']);
        
        $data = [
            'name'             => sanitize_text_field($_POST['name']),
            'address'          => sanitize_textarea_field($_POST['address']),
            'default_capacity' => max(0, intval($_POST['default_capacity']))
        ];
        
        $repo->save_location($id, $data);
        wp_redirect(admin_url('admin.php?page=vw_locations&status=success'));
        exit;
    }

    /**
     * Verarbeitet das Löschen eines Ortes.
     */
    public function handle_delete_location() {
        if (!current_user_can('edit_posts')) wp_die(esc_html__('Keine ausreichenden Rechte.', 'veranstaltungswart'));
        
        $id = intval($_GET['id']);
        check_admin_referer('vw_delete_nonce_' . $id);
        
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'vw_locations', ['id' => $id], ['%d']);
        
        wp_redirect(admin_url('admin.php?page=vw_locations&status=deleted'));
        exit;
    }
}