<?php
namespace VW\Core;

/**
 * Kernmodul: PSR-4 Autoloader
 * * Diese Klasse ist verantwortlich für das automatische Laden von Klassendateien
 * basierend auf ihrem Namespace. Sie bildet die Brücke zwischen der 
 * Dateistruktur im "app/"-Ordner und den PHP-Namespaces.
 */
class Loader {

    /**
     * Registriert den Autoloader im PHP-System.
     * Wird in der Hauptdatei (veranstaltungswart.php) als allererstes aufgerufen.
     */
    public static function register() {
        spl_autoload_register(function ($class) {
            
            // Der Namespace-Präfix für dieses Plugin
            $prefix = 'VW\\';

            // Basisverzeichnis für den Namespace-Präfix (app-Ordner)
            $base_dir = VW_PLUGIN_DIR . 'app/';

            // Prüfen, ob die aufgerufene Klasse unseren Präfix nutzt
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                // Nicht unsere Klasse, Autoloader für andere Plugins freigeben
                return;
            }

            // Den relativen Klassennamen extrahieren (z.B. Events\EventPostType)
            $relative_class = substr($class, $len);

            /**
             * Pfad zusammenbauen:
             * 1. Backslashes im Namespace durch Pfadtrenner des Betriebssystems ersetzen
             * 2. .php anhängen
             * DIRECTORY_SEPARATOR sorgt für Kompatibilität zwischen Linux (/) und Windows (\)
             */
            $file = $base_dir . str_replace('\\', DIRECTORY_SEPARATOR, $relative_class) . '.php';

            // Datei laden, falls sie existiert
            if (file_exists($file)) {
                require_once $file;
            }
        });
    }
}