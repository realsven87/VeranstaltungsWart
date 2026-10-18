=== VeranstaltungsWart ===
Contributors: realsven87
Tags: events, event management, bookings, registration
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 3.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Der Veranstaltungs-Manager für Vereine und Organisationen. Erstelle und verwalte Veranstaltungen, Anmeldungen und E-Mail-Vorlagen mit Leichtigkeit.

== Description ==

Der **VeranstaltungsWart** ist ein leichtgewichtiges Plugin zur Verwaltung von Veranstaltungen, Anmeldungen und Veranstaltungsorten. Es wurde für die Anforderungen von Vereinen, Organisationen und kleinen bis mittleren Veranstaltern entwickelt.

Verabschiede dich von unübersichtlichen Excel-Listen! Mit dem VeranstaltungsWart integrierst du in Sekunden ein Anmeldeformular auf deiner Website und verwaltest deine Gäste übersichtlich im WordPress-Backend.

### Hauptfunktionen:
*   **Veranstaltungs-Verwaltung:** Erstelle Veranstaltungen mit Datum, Uhrzeit, Ort und maximaler Teilnehmerzahl.
*   **Wartelisten-Automatik:** Wenn eine Veranstaltung ausgebucht ist, landen neue Anmeldungen automatisch auf der Warteliste. Wird ein Platz frei (z.B. durch Storno), rücken Personen automatisch nach.
*   **Personen & Vertrauensstatus:** Verwalte alle deine Kontakte an einem Ort. Personen können als "freigegeben" markiert werden, sodass ihre zukünftigen Anmeldungen automatisch bestätigt werden.
*   **Veranstaltungsorts-Verwaltung:** Speichere Veranstaltungsorte inklusive Adressen und Standard-Kapazitäten ab, um sie bei neuen Veranstaltungen mit einem Klick auszuwählen.
*   **Individuelle E-Mail-Vorlagen:** Erstelle eigene Bestätigungs-, Absage- oder Info-Nachrichten direkt im WordPress-Editor. Nutze praktische Platzhalter wie `{vorname}`, `{event_name}` oder `{storno_link}`.
*   **Teilnehmer-Export:** Exportiere die Anmeldungen einer Veranstaltung jederzeit als saubere CSV-Datei (perfekt für Einlasskontrollen).
*   **Datenschutzfreundlich:** Das Plugin integriert sich nahtlos in die WordPress-eigenen Werkzeuge zum Exportieren und Löschen von personenbezogenen Daten. Ein automatischer Cronjob löscht zudem veraltete Daten nach 12 Monaten.

== Installation ==

1. Lade den Ordner `veranstaltungswart` in das Verzeichnis `/wp-content/plugins/` hoch oder installiere das Plugin direkt als ZIP-Datei über das WordPress-Backend.
2. Aktiviere das Plugin im Menü "Plugins" in WordPress.
3. Gehe im neuen Menü "VeranstaltungsWart" auf **Orte** und lege deinen ersten Veranstaltungsort an.
4. Erstelle unter **Veranstaltungen** eine neue Veranstaltung und wähle den Ort sowie die Kapazität aus.
5. Füge den Shortcode `[event_registration]` in den Textbereich deiner Veranstaltung ein. Das Anmeldeformular erscheint nun automatisch auf deiner Seite!
6. (Optional) Passe unter **E-Mail-Vorlagen** die Texte für Bestätigungen und Wartelisten-Infos an.

== Frequently Asked Questions ==

= Wie binde ich das Anmeldeformular ein? =
Füge den Shortcode `[event_registration]` in den Standard-Editor (Gutenberg) deiner Veranstaltung ein. Das System erkennt automatisch, auf welcher Veranstaltungsseite es sich befindet.

= Wie funktioniert die Warteliste? =
Sobald die definierte "Max. Teilnehmer"-Zahl einesr Veranstaltung erreicht ist, schaltet das Formular automatisch in den Wartelisten-Modus. Wenn du eine bestätigte Anmeldung stornierst oder löschst, berechnet das System die Plätze neu und bestätigt passende freigegebene Personen automatisch. Ungeprüfte Personen benötigen weiterhin deine Freigabe.

= Kann ich die E-Mails testen, bevor sie an Teilnehmer rausgehen? =
Ja! Gehe im Dashboard auf "E-Mail-Vorlagen", öffne eine Vorlage und klicke in der rechten Seitenleiste auf den Button "Test-Mail jetzt senden". Du erhältst dann eine E-Mail mit Beispieldaten an die E-Mail-Adresse des Administrators.

= Was hat es mit dem "Vertrauensstatus" auf sich? =
Du kannst in der Personenverwaltung entscheiden, ob Anmeldungen einer Person sofort automatisch auf "Bestätigt" gesetzt werden sollen ("Freigegeben") oder ob sie manuell von dir freigeschaltet werden müssen ("Prüfung erforderlich").

= Sind die Daten Datenschutzfreundlich gesichert? =
Ja. Der VeranstaltungsWart unterstützt die nativen WordPress-Tools zum Export und zur Löschung von personenbezogenen Daten. Zudem fragt das Anmeldeformular aktiv die Zustimmung zur Datenschutzerklärung ab.

== Screenshots ==

1. Das übersichtliche Dashboard mit den aktuellen Warteschlangen.
2. Die detaillierte Event-Ansicht inklusive Teilnehmer-Tabelle und CSV-Export.
3. Verwaltung der E-Mail-Vorlagen mit dynamischen Platzhaltern.
4. Die Personenverwaltung zur schnellen Freigabe von Teilnehmern.
5. Das Anmeldeformular für Teilnehmer auf der Website.

== Version 3.0: Blöcke und dynamische Daten ==

Drei native Blöcke stehen im Block-Editor zur Verfügung:
* VeranstaltungsWart – Anmeldung
* VeranstaltungsWart – Veranstaltungskarte
* VeranstaltungsWart – Veranstaltungsliste

Im Anmeldeblock kann eine Veranstaltung ausgewählt werden. Ohne Auswahl wird die
aktuelle Veranstaltung verwendet. So funktionieren Formulare auch auf Beiträgen
und Seiten. Zentral gespeicherte Formularregeln gelten für alle Einbettungen.

Die Veranstaltungseinstellungen befinden sich im Block und in der Dokument-Seitenleiste.
Mit „Veranstaltungseinstellungen speichern“ werden sie SOFORT separat gespeichert.
Die Veröffentlichung des Beitrags speichert diese Einstellungen nicht automatisch.
Im klassischen Editor werden die Felder weiterhin zusammen mit dem Beitrag gespeichert.

Platzhalter im Veranstaltungstext: {event_name}, {event_datum}, {event_wochentag},
{event_zeit}, {event_ende}, {event_ort}, {event_adresse}, {event_hinweis}.
Im Absatz-Editor gibt es ein Menü „Veranstaltungsdaten einfügen“.
In anderen Beiträgen: [event_data id="123" field="event_datum"]
Anmeldung in anderen Beiträgen: [event_registration id="123"]

Orte sind standardmäßig nicht öffentlich. Die Einstellung „Ort erst nach bestätigter
Anmeldung mitteilen“ schützt generierte Ortsangaben in Karten, Platzhaltern und
E-Mails. Ein Kalenderdownload ist nur mit bestätigter Anmeldung möglich.
Bereits manuell in Texte oder Bilder geschriebene Adressen werden nicht entfernt.

Endzeit und Anmeldeschluss sind optional. Ohne Anmeldeschluss endet die Anmeldung
mit Beginn. Ohne Endzeit wird keine Dauer für den Kalenderdownload erfunden.

Die Blöcke unterstützen Theme-Farben und Abstände. Die Liste filtert nach Kategorie,
Anzahl, vergangenen und abgesagten Veranstaltungen. Ein leerer Hinweistext blendet
Listen ohne Treffer vollständig aus.

== Upgrade von 2.0 ==

Datenbank und bestehende Shortcodes bleiben erhalten. Vor dem Update sichern und
auf einer Testinstallation prüfen. Nicht deinstallieren: uninstall.php löscht Daten!
Autoren bearbeiten nur eigene Veranstaltungen; Administratoren und Redakteure
können auch fremde Veranstaltungen bearbeiten. Personen und Orte verwalten Admins.
Falls Version 2.0 Autoren allgemeine Rechte für fremde Blogbeiträge erteilt hat,
zeigt ein Admin-Hinweis eine ausdrücklich auslösbare Reparatur an. Diese Rechte
werden nicht automatisch gelöscht, da sie auch bewusst vergeben worden sein können.

Nur bestätigte Plätze belegen die Kapazität. Eingegangene Anmeldungen reservieren
keine Plätze. Die automatische Warteliste verarbeitet freigegebene Personen nach
Eingangszeit und überspringt Gruppen, die nicht vollständig passen. Ungeprüfte
Personen benötigen weiterhin eine manuelle Bestätigung.

Das Datenbanksystem muss MySQL/MariaDB GET_LOCK unterstützen. Bei nicht verfügbarem
Lock wird die Anmeldung mit einer Fehlermeldung abgelehnt, statt ungesichert zu buchen.
Seiten mit Formularen sollten von lang laufendem Full-Page-Caching ausgeschlossen
werden, damit die WordPress-Formulartokens aktuell bleiben.

== Changelog ==

= 3.0.1 =
* Optionale Bilder in Veranstaltungskarten und -listen: Desktop links, mobil oberhalb.
* Dashboard einschließlich Zähler und Menü-Badge auf bearbeitbare Veranstaltungen begrenzt.
* Ortswechsel übernimmt die Ortskapazität; manuelle Anpassungen bleiben möglich.
* Zusammengehörige Beschriftungen und Felder mit klaren Abständen im Editor.

= 3.0.0 =
* Dynamische Anmelde-, Veranstaltungs- und Listenblöcke.
* Zentrale Einstellungen über authentifizierte REST-Endpunkte und Editor-Seitenleiste.
* Veranstaltungsplatzhalter, Endzeit, Anmeldeschluss und private Ortsangaben.
* Einheitliche Wartelistenstatus, getrennte Statistiken und gesicherte Platzvergabe.
* Serverseitige Formularvalidierung und Schutz vor doppelten aktiven Anmeldungen.
* Eigene Veranstaltungsrechte und veranstaltungsbezogene Zugriffskontrollen.
* Stornierung mit Bestätigungsseite; korrigierter Kalenderexport.
* Automatisierte Syntax-, WordPress- und HTTP-Integrationstests.
