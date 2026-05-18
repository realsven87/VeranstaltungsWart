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
    <h1><?php esc_html_e('Hilfe & Dokumentation', 'veranstaltungswart'); ?></h1>
    <p><?php echo wp_kses_post(__('Willkommen beim <strong>VeranstaltungsWart</strong>. Hier findest du alle Informationen zur Einrichtung deines Buchungssystems.', 'veranstaltungswart')); ?></p>

    <div class="vw-help-grid">
        
        <div class="vw-help-card">
            <h2 style="margin-top:0;"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Erste Schritte', 'veranstaltungswart'); ?></h2>
            <ol>
                <li><strong><?php esc_html_e('E-Mail-Vorlagen:', 'veranstaltungswart'); ?></strong> <?php esc_html_e('Das System benötigt Vorlagen mit diesen technischen Namen (Slugs):', 'veranstaltungswart'); ?>
<ul style="list-style: disc; margin-left: 20px; margin-top: 5px; font-family: monospace;">
                        <li>eingangsbestaetigung <span style="color:#646970; font-family: sans-serif;">(<?php esc_html_e('Eingangsbestätigung für den Teilnehmer', 'veranstaltungswart'); ?>)</span></li>
                        <li>freigabe-info <span style="color:#646970; font-family: sans-serif;">(<?php esc_html_e('Benachrichtigung an den Admin über neue Anmeldung', 'veranstaltungswart'); ?>)</span></li>
                        <li>warteliste-info <span style="color:#646970; font-family: sans-serif;">(<?php esc_html_e('Info an Teilnehmer: Platz auf der Warteliste', 'veranstaltungswart'); ?>)</span></li>
                        <li>anmeldebestaetigung <span style="color:#646970; font-family: sans-serif;">(<?php esc_html_e('Finale Zusage für die Veranstaltung', 'veranstaltungswart'); ?>)</span></li>
                        <li>ablehnung-info <span style="color:#646970; font-family: sans-serif;">(<?php esc_html_e('Manuelle Ablehnung durch den Admin', 'veranstaltungswart'); ?>)</span></li>
                        <li>storno-bestaetigung <span style="color:#646970; font-family: sans-serif;">(<?php esc_html_e('Bestätigung an Teilnehmer nach eigener Stornierung', 'veranstaltungswart'); ?>)</span></li>
                        <li>event-abgesagt <span style="color:#646970; font-family: sans-serif;">(<?php esc_html_e('Info an alle Teilnehmer, wenn Veranstaltung komplett abgesagt wird', 'veranstaltungswart'); ?>)</span></li>
                    </ul>
                </li>
                <li><strong><?php esc_html_e('Orte:', 'veranstaltungswart'); ?></strong> <?php esc_html_e('Unter "Veranstaltungsorte" Kapazitäten festlegen.', 'veranstaltungswart'); ?></li>
                <li><strong><?php esc_html_e('Event:', 'veranstaltungswart'); ?></strong> <?php esc_html_e('Veranstaltung erstellen und Shortcode einfügen.', 'veranstaltungswart'); ?></li>
            </ol>
        </div>

        <div class="vw-help-card">
            <h2 style="margin-top:0;"><span class="dashicons dashicons-shortcode"></span> <?php esc_html_e('Shortcodes', 'veranstaltungswart'); ?></h2>
            <p><?php esc_html_e('Füge diesen Shortcode in den Inhalt (Editor) deiner Veranstaltung ein, um das Anmeldeformular anzuzeigen:', 'veranstaltungswart'); ?></p>
            <code style="display: block; padding: 15px; background: #f0f0f1; font-size: 1.3em; text-align: center; border: 1px dashed #ccc; color: #2271b1;">[event_registration]</code>
            <p><small><em><?php esc_html_e('Hinweis: Der Shortcode erkennt automatisch, für welche Veranstaltung er aufgerufen wird.', 'veranstaltungswart'); ?></em></small></p>
        </div>

        <div class="vw-help-card vw-help-card-full">
            <h2 style="margin-top:0;"><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e('E-Mail Platzhalter', 'veranstaltungswart'); ?></h2>
            <p><?php esc_html_e('Diese Platzhalter kannst du in den Betreff oder Inhalt deiner E-Mail-Vorlagen einbauen:', 'veranstaltungswart'); ?></p>
            
            <div class="vw-table-responsive">
                <table class="widefat fixed striped" style="min-width: 600px;">
                    <thead>
                        <tr>
                            <th style="width: 25%;"><?php esc_html_e('Platzhalter', 'veranstaltungswart'); ?></th>
                            <th style="width: 35%;"><?php esc_html_e('Beschreibung', 'veranstaltungswart'); ?></th>
                            <th style="width: 40%;"><?php esc_html_e('Beispiel', 'veranstaltungswart'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><code>{vorname}</code></td><td><?php esc_html_e('Vorname des Teilnehmers', 'veranstaltungswart'); ?></td><td><?php esc_html_e('Hallo {vorname},', 'veranstaltungswart'); ?></td></tr>
                        <tr><td><code>{nachname}</code></td><td><?php esc_html_e('Nachname des Teilnehmers', 'veranstaltungswart'); ?></td><td><?php esc_html_e('Familie {nachname}', 'veranstaltungswart'); ?></td></tr>
                        <tr><td><code>{email}</code></td><td><?php esc_html_e('E-Mail des Teilnehmers', 'veranstaltungswart'); ?></td><td><?php esc_html_e('Kontakt: {email}', 'veranstaltungswart'); ?></td></tr>
                        <tr><td><code>{event_name}</code></td><td><?php esc_html_e('Name der Veranstaltung', 'veranstaltungswart'); ?></td><td><?php esc_html_e('Deine Buchung für {event_name}', 'veranstaltungswart'); ?></td></tr>
                        <tr><td><code>{event_datum}</code> / <code>{event_zeit}</code></td><td><?php esc_html_e('Termindaten', 'veranstaltungswart'); ?></td><td><?php esc_html_e('Am {event_datum} um {event_zeit}', 'veranstaltungswart'); ?></td></tr>
                        <tr><td><code>{event_adresse}</code></td><td><?php esc_html_e('Anschrift des Ortes', 'veranstaltungswart'); ?></td><td><?php esc_html_e('Ort: {event_adresse}', 'veranstaltungswart'); ?></td></tr>
                        <tr><td><code>{event_plaetze}</code></td><td><?php esc_html_e('Anzahl gebuchter Plätze', 'veranstaltungswart'); ?></td><td><?php esc_html_e('Du hast {event_plaetze} Plätze reserviert.', 'veranstaltungswart'); ?></td></tr>
                        <tr><td><code>{storno_link}</code></td><td><?php esc_html_e('Abmelde-Link (HTML)', 'veranstaltungswart'); ?></td><td><?php esc_html_e('Klicke hier: {storno_link}', 'veranstaltungswart'); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>