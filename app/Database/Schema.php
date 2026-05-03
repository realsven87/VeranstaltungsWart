<?php
namespace VW\Database;

// Sicherheitscheck: Verhindert direkten Aufruf
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Modul: Datenbank-Schema
 * Diese Klasse definiert die Tabellenstruktur des Plugins. 
 * Sie nutzt dbDelta, um Tabellen sicher zu erstellen oder zu modifizieren.
 */
class Schema {

    /**
     * Erstellt oder aktualisiert die Tabellenstruktur.
     * Wird beim Aktivieren des Plugins aufgerufen.
     */
    public function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        
        $table_locations     = $wpdb->prefix . 'vw_locations';
        $table_persons       = $wpdb->prefix . 'vw_persons';
        $table_lists         = $wpdb->prefix . 'vw_lists';
        $table_person_list   = $wpdb->prefix . 'vw_person_list';
        $table_registrations = $wpdb->prefix . 'vw_registrations';

        $sql = [];

        // 1. Veranstaltungsorte
        $sql[] = "CREATE TABLE $table_locations (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            address text DEFAULT '',
            default_capacity int(11) DEFAULT 0,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 2. Personen (Teilnehmer-Stammdaten)
        $sql[] = "CREATE TABLE $table_persons (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            first_name varchar(100) DEFAULT '',
            last_name varchar(100) DEFAULT '',
            trust_status varchar(20) DEFAULT 'nicht_freigegeben',
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email)
        ) $charset_collate;";

        // 3. Mailing-Listen
        $sql[] = "CREATE TABLE $table_lists (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text DEFAULT '',
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 4. n:m Beziehung (Personen zu Listen)
        $sql[] = "CREATE TABLE $table_person_list (
            person_id mediumint(9) NOT NULL,
            list_id mediumint(9) NOT NULL,
            PRIMARY KEY  (person_id, list_id)
        ) $charset_collate;";

        // 5. Anmeldungen (Verknüpfung Event <-> Person)
        $sql[] = "CREATE TABLE $table_registrations (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_post_id bigint(20) NOT NULL,
            person_id mediumint(9) NOT NULL,
            guest_count int(11) DEFAULT 0,
            message text DEFAULT '',
            status varchar(20) DEFAULT 'eingegangen',
            seats_total int(11) DEFAULT 1,
            registered_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id),
            KEY event_post_id (event_post_id),
            KEY person_id (person_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // dbDelta verarbeitet das Array an SQL-Befehlen
        dbDelta($sql);
    }
}