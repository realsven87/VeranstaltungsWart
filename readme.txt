=== VeranstaltungsWart ===
Contributors: realsven87
Tags: events, event management, bookings, registration, email templates
Requires at least: 5.8
Tested up to: 6.5
Stable tag: 2.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Der Veranstaltungs-Manager für Vereine und politische Organisationen. Erstelle und verwalte Veranstaltungen, Teilnehmer und E-Mail-Vorlagen mit Leichtigkeit.

== Description ==

Der **VeranstaltungsWart** ist ein leichtgewichtiges Plugin zur Verwaltung von Veranstaltungen, Teilnehmern und Veranstaltungsorten. Es wurde speziell für die Anforderungen von Vereinen, politischen Organisationen und kleinen bis mittleren Veranstaltern entwickelt.

Verabschiede dich von unübersichtlichen Excel-Listen! Mit dem VeranstaltungsWart integrierst du in Sekunden ein DSGVO-konformes Anmeldeformular auf deiner Website und verwaltest deine Gäste übersichtlich im WordPress-Backend.

### Hauptfunktionen:
*   **Veranstaltungs-Verwaltung:** Erstelle Veranstaltungen mit Datum, Uhrzeit, Ort und maximaler Teilnehmerzahl.
*   **Wartelisten-Automatik:** Wenn eine Veranstaltung ausgebucht ist, landen neue Anmeldungen automatisch auf der Warteliste. Wird ein Platz frei (z.B. durch Storno), rücken Personen automatisch nach.
*   **Personen & Vertrauensstatus:** Verwalte alle deine Kontakte an einem Ort. Personen können als "freigegeben" markiert werden, sodass ihre zukünftigen Anmeldungen automatisch bestätigt werden.
*   **Orts-Verwaltung:** Speichere Veranstaltungsorte inklusive Adressen und Standard-Kapazitäten ab, um sie bei neuen Veranstaltungen mit einem Klick auszuwählen.
*   **Individuelle E-Mail-Vorlagen:** Erstelle eigene Bestätigungs-, Absage- oder Info-Nachrichten direkt im WordPress-Editor. Nutze praktische Platzhalter wie `{vorname}`, `{event_name}` oder `{storno_link}`.
*   **Teilnehmer-Export:** Exportiere die Anmeldungen einer Veranstaltung jederzeit als saubere CSV-Datei (perfekt für Einlasskontrollen).
*   **DSGVO-konform:** Das Plugin integriert sich nahtlos in die WordPress-eigenen Werkzeuge zum Exportieren und Löschen von personenbezogenen Daten. Ein automatischer Cronjob löscht zudem veraltete Daten nach 12 Monaten.

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
Sobald die definierte "Max. Teilnehmer"-Zahl einesr Veranstaltung erreicht ist, schaltet das Formular automatisch in den Wartelisten-Modus. Wenn du eine bestätigte Anmeldung stornierst oder löschst, berechnet das System die Plätze neu und schlägt dir die nächste Person auf der Warteliste zum Nachrücken vor.

= Kann ich die E-Mails testen, bevor sie an Teilnehmer rausgehen? =
Ja! Gehe im Dashboard auf "E-Mail-Vorlagen", öffne eine Vorlage und klicke in der rechten Seitenleiste auf den Button "Test-Mail jetzt senden". Du erhältst dann eine E-Mail mit Beispieldaten an die E-Mail-Adresse des Administrators.

= Was hat es mit dem "Vertrauensstatus" auf sich? =
Du kannst in der Personenverwaltung entscheiden, ob Anmeldungen einer Person sofort automatisch auf "Bestätigt" gesetzt werden sollen ("Freigegeben") oder ob sie manuell von dir freigeschaltet werden müssen ("Prüfung erforderlich").

= Sind die Daten DSGVO-konform gesichert? =
Ja. Der VeranstaltungsWart unterstützt die nativen WordPress-Tools zum Export und zur Löschung von personenbezogenen Daten. Zudem fragt das Anmeldeformular aktiv die Zustimmung zur Datenschutzerklärung ab.

== Screenshots ==

1. Das übersichtliche Dashboard mit den aktuellen Warteschlangen.
2. Die detaillierte Event-Ansicht inklusive Teilnehmer-Tabelle und CSV-Export.
3. Verwaltung der E-Mail-Vorlagen mit dynamischen Platzhaltern.
4. Die Personenverwaltung zur schnellen Freigabe von Teilnehmern.
5. Das Anmeldeformular für Teilnehmer auf der Website.

== Changelog ==

= 2.0.0 =
* Initialer Release für das WordPress Plugin-Verzeichnis.
* Vollständige Internationalisierung (i18n) hinzugefügt.
* Code-Security (Nonces, Escaping, Sanitization) nach WordPress.org-Standards implementiert.
* DSGVO-Routinen optimiert.