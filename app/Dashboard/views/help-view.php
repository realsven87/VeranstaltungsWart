<?php if (!defined('ABSPATH')) exit; ?>

<style>
    /* Layout für die Hilfe-Karten */
    .vw-help-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }
    .vw-help-card {
        background: #fff;
        padding: 20px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }
    .vw-help-card-full {
        grid-column: span 2;
    }
    
    /* Scrollbare Tabelle für die E-Mail Platzhalter */
    .vw-table-responsive {
        overflow-x: auto;
        display: block;
        width: 100%;
    }

    /* WordPress Standard-Breakpoint für Mobile Admin-Ansichten */
    @media (max-width: 782px) {
        .vw-help-grid {
            grid-template-columns: 1fr; /* Alles untereinander auf dem Smartphone */
        }
        .vw-help-card-full {
            grid-column: span 1; /* Volle Breite auch für die ehemals zweispaltige Box */
        }
    }
</style>

<div class="wrap">
    <h1>Hilfe & Dokumentation</h1>
    <p>Willkommen beim <strong>VeranstaltungsWart</strong>. Hier finden Sie alle Informationen zur Einrichtung Ihres Buchungssystems.</p>

    <div class="vw-help-grid">
        
        <div class="vw-help-card">
            <h2 style="margin-top:0;"><span class="dashicons dashicons-list-view"></span> Erste Schritte</h2>
            <ol>
                <li><strong>E-Mail-Vorlagen:</strong> Das System benötigt Vorlagen mit diesen technischen Namen (Slugs):
                    <ul style="list-style: disc; margin-left: 20px; margin-top: 5px; font-family: monospace;">
                        <li>anmelde-eingang <span style="color:#646970; font-family: sans-serif;">(Eingangsbestätigung)</span></li>
                        <li>admin-info-neu <span style="color:#646970; font-family: sans-serif;">(Admin-Benachrichtigung)</span></li>
                        <li>warteliste-info <span style="color:#646970; font-family: sans-serif;">(Wartelisten-Platz)</span></li>
                        <li>anmeldebestaetigung <span style="color:#646970; font-family: sans-serif;">(Freigabe)</span></li>
                        <li>event-absage <span style="color:#646970; font-family: sans-serif;">(Storno/Ablehnung)</span></li>
                    </ul>
                </li>
                <li><strong>Orte:</strong> Unter "Veranstaltungsorte" Kapazitäten festlegen.</li>
                <li><strong>Event:</strong> Veranstaltung erstellen und Shortcode einfügen.</li>
            </ol>
        </div>

        <div class="vw-help-card">
            <h2 style="margin-top:0;"><span class="dashicons dashicons-shortcode"></span> Shortcodes</h2>
            <p>Fügen Sie diesen Shortcode in den Inhalt (Editor) Ihrer Veranstaltung ein:</p>
            <code style="display: block; padding: 15px; background: #f0f0f1; font-size: 1.3em; text-align: center; border: 1px dashed #ccc; color: #2271b1;">[event_registration]</code>
            <p><small><em>Hinweis: Der Shortcode erkennt automatisch, für welches Event er aufgerufen wird.</em></small></p>
        </div>

        <div class="vw-help-card vw-help-card-full">
            <h2 style="margin-top:0;"><span class="dashicons dashicons-email-alt"></span> E-Mail Platzhalter</h2>
            <p>Diese Platzhalter können Sie in den Betreff oder Inhalt Ihrer E-Mail-Vorlagen einbauen:</p>
            
            <div class="vw-table-responsive">
                <table class="widefat fixed striped" style="min-width: 600px;">
                    <thead>
                        <tr>
                            <th style="width: 25%;">Platzhalter</th>
                            <th style="width: 35%;">Beschreibung</th>
                            <th style="width: 40%;">Beispiel</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>{vorname}</code></td><td>Vorname des Teilnehmers</td><td>Hallo {vorname},</td></tr>
                        <tr><td><code>{nachname}</code></td><td>Nachname des Teilnehmers</td><td>Familie {nachname}</td></tr>
                        <tr><td><code>{email}</code></td><td>E-Mail des Teilnehmers</td><td>Kontakt: {email}</td></tr>
                        <tr><td><code>{event_name}</code></td><td>Name der Veranstaltung</td><td>Ihre Buchung für {event_name}</td></tr>
                        <tr><td><code>{event_datum}</code> / <code>{event_zeit}</code></td><td>Termindaten</td><td>Am {event_datum} um {event_zeit}</td></tr>
                        <tr><td><code>{event_adresse}</code></td><td>Anschrift des Ortes</td><td>Ort: {event_adresse}</td></tr>
                        <tr><td><code>{event_plaetze}</code></td><td>Anzahl gebuchter Plätze</td><td>Sie haben {event_plaetze} Plätze reserviert.</td></tr>
                        <tr><td><code>{storno_link}</code></td><td>Abmelde-Link (HTML)</td><td>Klicken Sie hier: {storno_link}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>