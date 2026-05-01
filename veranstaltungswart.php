<?php
/**
 * Plugin Name: VeranstaltungsWart
 * Description: Der Veranstatungs-Manager für Vereine und politische Organisationen. Erstelle und verwalte Veranstaltungen, Teilnehmer und E-Mail-Vorlagen mit Leichtigkeit.
 * Version:     2.0.0
 * Author:      Sven Epple
 * Text Domain: veranstaltungswart
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

// Sicherheitscheck: Verhindert direkten Aufruf der Datei
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Globale Pfad-Konstanten für das gesamte Plugin.
 */
define('VW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VW_VERSION', '1.0.0');

/**
 * Loader einbinden und Autoloader registrieren.
 * Dies muss vor allen anderen Klassenaufrufen geschehen.
 */
require_once VW_PLUGIN_DIR . 'app/Core/Loader.php';
\VW\Core\Loader::register();

/**
 * Hauptklasse VeranstaltungsWart
 * Fungiert als "Bootstrapper", der alle Sub-Module initialisiert.
 */
class VeranstaltungsWart {

    /**
     * @var array Speichert die Instanzen der Module.
     */
    private $modules = [];

    /**
     * Konstruktor: Startet das Plugin-System.
     */
    public function __construct() {
        // Registrierung der Aktivierungs- und Deaktivierungs-Hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Initialisierung der Module
        $this->init_modules();
        
        // Hooks für allgemeine Plugin-Aufgaben
        add_action('init', [$this, 'ensure_capabilities']);
    }

    /**
     * Wird beim Aktivieren des Plugins ausgeführt.
     */
    public function activate() {
        // 1. Datenbank-Schema initialisieren/aktualisieren
        $schema = new \VW\Database\Schema();
        $schema->create_tables();

        // 2. Standard E-Mail Vorlagen erstellen
        $template_manager = new \VW\Mails\MailTemplateManager();
        $template_manager->create_default_templates();

        // 3. Berechtigungen für Rollen setzen
        $this->ensure_capabilities();
        
        // 4. Permalinks neu generieren (wichtig für CPTs)
        // ZUERST den Post Type und die Taxonomie registrieren, damit WordPress sie beim Flushen kennt!
        $event_post_type = new \VW\Events\EventPostType();
        $event_post_type->create_post_type();
        $event_post_type->create_event_taxonomy();

        // DANN die Landkarte neu speichern
        flush_rewrite_rules();
    }

    /**
     * Wird beim Deaktivieren ausgeführt.
     */
    public function deactivate() {
        // Zeichne die Landkarte ohne die Plugin-URLs neu
        flush_rewrite_rules();
    }

    /**
     * Stellt sicher, dass Autoren und Admins die nötigen Rechte für das Plugin haben.
     */
    public function ensure_capabilities() {
        $roles = ['administrator', 'author'];
        
        foreach ($roles as $role_name) {
            $role = get_role($role_name);
            if (!$role) continue;

            // Rechte für Event-Management
            $role->add_cap('edit_others_posts');
            $role->add_cap('edit_published_posts');
            $role->add_cap('publish_posts');
            $role->add_cap('delete_posts');
            $role->add_cap('delete_published_posts');
            $role->add_cap('delete_others_posts');
            
            // Eigene Capabilities
            $role->add_cap('manage_vw_events');
        }
    }

    /**
     * Initialisiert alle Kern-Module des Plugins.
     */
    private function init_modules() {
        // 1. Dashboard & Menüsteuerung (Zentrale Verwaltung & Quick-Actions)
        $this->modules['dashboard'] = new \VW\Dashboard\DashboardManager();
        $this->modules['dashboard']->register();

        // 2. Veranstaltungs-CPT (Custom Post Type & Meta-Boxen)
        $this->modules['events'] = new \VW\Events\EventPostType();
        $this->modules['events']->register();

        // 3. Veranstaltungsorte (CRUD Logik & Admin-Seite)
        $this->modules['locations'] = new \VW\Locations\LocationManager();
        $this->modules['locations']->register(); // Registriert Speicher/Lösch-Hooks

        // 4. Personen-Verwaltung (Teilnehmerdatenbank & Status-Updates)
        $this->modules['persons'] = new \VW\Persons\PersonManager();
        $this->modules['persons']->register(); // Registriert Speicher/Status-Hooks

        // 5. E-Mail-Vorlagen (System-Mails CPT)
        $this->modules['mails'] = new \VW\Mails\MailTemplateManager();
        $this->modules['mails']->register();

        // 6. Registrierungs-System (Frontend Shortcode)
        $this->modules['shortcode'] = new \VW\Registrations\RegistrationShortcode();
        $this->modules['shortcode']->init();

        // 7. Formular-Handler (Verarbeitet Formular-Submissions & Stornos)
        $this->modules['handler'] = new \VW\Registrations\RegistrationHandler();
        $this->modules['handler']->register();
    }
}

/**
 * Plugin-Instanz starten.
 */
function run_veranstaltungswart() {
    return new VeranstaltungsWart();
}

run_veranstaltungswart();