# Datenschutz: Aufbewahrung und Löschung

Seit Version 3.4.0: **VeranstaltungsWart → Einstellungen → Datenschutz und automatische Löschung**. Nur Administratoren mit `manage_options` können die Frist ändern. Speicherung über die WordPress Settings API mit Nonce und Berechtigungsprüfung; ganze Zahlen von 1 bis 3650, Standard 90 Tage. Ungültige Werte behalten die bisherige Einstellung bei. Keine Option für unbegrenzte Speicherung.

Die Frist beginnt am Veranstaltungsende, ersatzweise um 23:59:59 Uhr am Veranstaltungstag in der Website-Zeitzone. Beispiel: Ende am 10. Januar um 20 Uhr, Frist 30 Tage → fällig am 9. Februar um 20 Uhr; Löschung beim nächsten erfolgreichen täglichen Lauf. Kalendertage berücksichtigen Sommerzeitwechsel.

Gelöscht werden die vollständigen Anmeldungen aller Status einschließlich Freitext und Zusatzantworten für Teilnehmer und nummerierte Begleitpersonen. Veranstaltungen bleiben bestehen. Eine neuere Anmeldung verlängert die Speicherung älterer Anmeldungen nicht; spätere und noch laufende Veranstaltungen bleiben geschützt. Es wird keine personenbezogene Historie oder Statistik-Kopie angelegt. Auswertungen enthalten die gelöschten Anmeldungen anschließend nicht mehr.

Kontakte ohne verbleibende Anmeldung und ohne Listenzuordnung werden nach derselben Frist ab Anlage entfernt, auch ohne bisherige Anmeldung. Vertrauensstatus allein schützt nicht vor Löschung. Listenzuordnungen erhalten den Kontakt; diese Daten benötigen eine separate zweckbezogene Prüfung. Kontakte mit ungültigem Anlagezeitpunkt werden nicht automatisch gelöscht.

Fehlt ein gültiger Beginn, ist das Ende ungültig oder liegt es vor/beim Beginn, wird die Veranstaltung übersprungen. Auch Anmeldungen zu endgültig gelöschten Veranstaltungen werden zur manuellen Prüfung behalten. Die Einstellungen zeigen die Anzahl übersprungener Veranstaltungen, Fehler und gelöschter Datensätze sowie den letzten und nächsten Lauf. Das Protokoll enthält nur den letzten Lauf und keine Namen, E-Mail-Adressen oder Antworten.

Die neue Regel ersetzt die bisherige Zwölfmonatsregel und gilt sofort auch für Bestandsdaten. Kürzere Fristen können beim nächsten Lauf unwiderrufliche Löschungen auslösen. Eine spätere Verlängerung stellt keine Daten wieder her. Einstellungen lösen selbst keinen Löschlauf aus.

WordPress-Cron muss funktionieren; für verlässliche Ausführung empfiehlt sich ein Hosting-Zeitplan. Maximal 100 fällige Veranstaltungen pro Lauf, weitere im nächsten Lauf. Große Datenbestände können daher mehrere Tage benötigen. Veranstaltungssperren verhindern Konflikte mit Terminänderungen und Anmeldungen; eine gemeinsame Sperre schützt den Kontaktabruf der öffentlichen Anmeldung vor gleichzeitiger Kontaktbereinigung. Datenbank-/Sperrfehler werden gezählt und beim nächsten Lauf erneut versucht.

CSV-Dateien, E-Mail-Postfächer, Backups und Daten externer Integrationen sind nicht erfasst. Nach einer Wiederherstellung muss die Bereinigung erneut ausgeführt werden. Frist und tatsächliche Abläufe müssen in die Datenschutzhinweise übernommen werden; der Standardwert ist keine gesetzliche Frist.

## Prüfung

`tests/retention.php` prüft Standardwert/Validierung, Ende des Veranstaltungstags, mehrtägige Veranstaltungen, ungültige Termine, Friständerung, alle Anmeldestatus einschließlich Antworten, Kontakte mit zukünftiger Anmeldung/Listenzuordnung, ungenutzte Kontakte, wiederholte Bereinigung, Status und geschützte Einstellungsdarstellung. Die vorhandenen HTTP-Anmeldetests prüfen weiterhin die Anmeldung einschließlich der neuen gemeinsamen Kontaktsperre.

`tests/retention.cjs` prüft im Browser Speichern und Neuladen der Einstellung sowie die Ablehnung ungültiger Werte und Nonces am echten WordPress-Endpunkt.
