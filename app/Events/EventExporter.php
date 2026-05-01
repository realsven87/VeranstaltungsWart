<?php
namespace VW\Events;

/**
 * Modul: EventExporter
 * Verantwortlich für die Generierung und den Download von Teilnehmerlisten.
 */
class EventExporter {

    /**
     * Erzeugt eine CSV-Datei der Teilnehmer und sendet sie an den Browser.
     */
    public function download_participants_csv($event_id) {
        if (!current_user_can('manage_options')) {
            wp_die('Zugriff verweigert.');
        }

        $repo = new EventRepository();
        $event_title = get_the_title($event_id);
        
        // Alle Registrierungen holen (alphabetisch nach Nachnamen)
        $registrations = $repo->get_registrations_for_event($event_id, 'last_name', 'ASC');

        // Filter: Nur aktive Teilnehmer (keine Abgelehnten/Stornierten)
        $active_registrations = array_filter($registrations, function($reg) {
            return !in_array($reg->status, ['storniert', 'abgelehnt']);
        });

        // Optional: Nach Status sortieren (Bestätigte zuerst), falls gewünscht
        usort($active_registrations, function($a, $b) {
            if ($a->status === $b->status) return 0;
            return ($a->status === 'bestätigt') ? -1 : 1;
        });

        $filename = 'teilnehmer-' . sanitize_title($event_title) . '-' . date('Y-m-d') . '.csv';

        // Header für Datei-Download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM für Excel-Kompatibilität
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); 

        // Spaltenüberschriften
        fputcsv($output, [
            'Status',
            'Nachname', 
            'Vorname', 
            'E-Mail', 
            'Plätze', 
            'Angemeldet am'
        ], ';');

        // Datenzeilen schreiben
        foreach ($active_registrations as $reg) {
            fputcsv($output, [
                ucfirst($reg->status),
                $reg->last_name,
                $reg->first_name,
                $reg->email,
                $reg->seats_total,
                date_i18n('d.m.Y H:i', strtotime($reg->registered_at))
            ], ';');
        }

        fclose($output);
        exit;
    }
}