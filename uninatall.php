<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * This file removes all custom database tables and scheduled cron jobs
 * to ensure the plugin leaves no orphaned data behind.
 */

// Sicherheitscheck: Verhindert direkten Aufruf. 
// WordPress setzt diese Konstante NUR, wenn das Plugin über das Backend gelöscht wird.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// 1. Cronjob restlos entfernen
$cron_hook = 'vw_daily_gdpr_cleanup';
$timestamp = wp_next_scheduled($cron_hook);
if ($timestamp) {
    wp_unschedule_event($timestamp, $cron_hook);
}

// 2. Eigene Datenbanktabellen löschen
global $wpdb;

// Array mit allen Tabellen, die dein Plugin angelegt hat
$custom_tables = [
    $wpdb->prefix . 'vw_registrations',
    $wpdb->prefix . 'vw_person_list',
    $wpdb->prefix . 'vw_lists',
    $wpdb->prefix . 'vw_persons',
    $wpdb->prefix . 'vw_locations'
];

foreach ($custom_tables as $table) {
    // DROP TABLE IF EXISTS verhindert Fehlermeldungen, falls eine Tabelle schon weg ist
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// 3. (Optional) Custom Capabilities von den Rollen entfernen
$roles = ['administrator', 'author'];
foreach ($roles as $role_name) {
    $role = get_role($role_name);
    if ($role) {
        // Wir entfernen nur die rein Plugin-spezifische Berechtigung
        $role->remove_cap('manage_vw_events');
        
        // Hinweis: Die Standard-Rechte wie 'edit_others_posts' lassen wir unangetastet, 
        // da sie zur WordPress-Kernfunktionalität gehören und auch für andere Dinge gebraucht werden.
    }
}