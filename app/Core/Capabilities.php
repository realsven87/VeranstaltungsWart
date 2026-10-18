<?php
namespace VW\Core;
if (!defined('ABSPATH')) exit;

class Capabilities {
    public static function register() {
        add_action('admin_notices', function() {
            $role = get_role('author');
            if (!current_user_can('manage_options') || !$role || (!$role->has_cap('edit_others_posts') && !$role->has_cap('delete_others_posts'))) return;
            $url = wp_nonce_url(admin_url('admin-post.php?action=vw_repair_author_rights'), 'vw_repair_author_rights');
            echo '<div class="notice notice-warning"><p>VeranstaltungsWart 2.0 hat Autoren zusätzliche Rechte für fremde Blogbeiträge erteilt. Falls diese Rechte nicht bewusst benötigt werden: <a href="' . esc_url($url) . '">Zusätzliche Autorenrechte für fremde Beiträge entfernen</a>. Veranstaltungsrechte bleiben erhalten.</p></div>';
        });
        add_action('admin_post_vw_repair_author_rights', function() {
            if (!current_user_can('manage_options')) wp_die(esc_html__('Keine Berechtigung.', 'veranstaltungswart'));
            check_admin_referer('vw_repair_author_rights');
            $role = get_role('author');
            if ($role) { $role->remove_cap('edit_others_posts'); $role->remove_cap('delete_others_posts'); }
            wp_safe_redirect(admin_url('admin.php?page=vw_dashboard')); exit;
        });
    }
    public static function install() {
        if (get_option('vw_capabilities_version') === '3.0.0') return;
        foreach (['administrator', 'editor', 'author'] as $name) {
            $role = get_role($name);
            if (!$role) continue;
            foreach (['edit_vw_events', 'edit_published_vw_events', 'publish_vw_events', 'delete_vw_events', 'delete_published_vw_events', 'manage_vw_events'] as $cap) $role->add_cap($cap);
            if ($name !== 'author') {
                foreach (['edit_others_vw_events', 'delete_others_vw_events', 'read_private_vw_events', 'edit_private_vw_events', 'delete_private_vw_events'] as $cap) $role->add_cap($cap);
            }
        }
        // Existing generic author rights may have been assigned by the site owner.
        // Do not silently remove them; provide an explicit administrator-only repair.
        update_option('vw_capabilities_version', '3.0.0');
    }
    public static function can_manage_event($id) {
        return get_post_type($id) === 'vw_event' && current_user_can('edit_post', $id);
    }
}
