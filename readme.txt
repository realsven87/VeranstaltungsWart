=== VeranstaltungsWart ===
Contributors: realsven87
Tags: events, event management, bookings, registration
Requires at least: 6.5
Tested up to: 7.1
Stable tag: 3.2.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage events, registrations, waiting lists and venues with three native blocks, approval workflows and optional private locations.

== Description ==

VeranstaltungsWart helps clubs, associations and event organisers manage events and registrations in WordPress. Registration data is stored in your site's database. No external account or paid service is required.

Features:

* Three dynamic blocks: registration form, event card and event list. Embed events in posts and pages.
* Event date, optional end time and registration deadline.
* Optional accompanying guests and a configurable free-text field, including required input.
* Venue capacity copied when a venue is selected, with a manual override.
* Manual approval and automatic confirmation for trusted contacts. Confirmed seats count towards capacity; pending requests do not reserve seats.
* Waiting lists with automatic promotion of eligible trusted contacts when enough seats become available for their entire group.
* Authors manage their own events. Administrators and editors can manage other authors' events. Dashboard counters use the same permissions.
* Optional private locations, disclosed only in generated information for confirmed registrations.
* Email templates, cancellation links, calendar downloads and participant CSV exports.
* Event images shown without cropping: left on desktop, above the text on mobile.
* WordPress personal-data export and erasure integration.

The interface currently uses German source strings and supports translations. This plugin does not process payments or sell tickets.

= Data and privacy =

The plugin stores names, email addresses, registration status, guest counts, free-text messages, registration timestamps, contact trust status and optional contact-list membership in the local WordPress database. Email content may contain these details and is sent through your site's configured WordPress mail service. The plugin does not include analytics or send data to the developer.

A daily WordPress cron task removes contacts and their registrations when their most recent registration is older than twelve months. This is based on the registration timestamp, not the event date; contacts without registrations are not selected by this task. Configure your site's privacy notice to explain the actual processing and retention. Avoid collecting unnecessary sensitive information in the free-text field.

Deactivation stops scheduled tasks and preserves data. Deleting the plugin removes its custom tables (including registrations, contacts, lists and venues), plugin capabilities and reminder markers. Event posts and email-template posts are retained in the WordPress posts table. Back up before deletion or upgrades. Do not uninstall as an update procedure.

No external service is required by this plugin. Your hosting, mail delivery and any other plugins remain separate services managed by you.

= Requirements and limitations =

* WordPress 6.5 or newer; PHP 7.4 or newer.
* MySQL/MariaDB must support GET_LOCK. If a booking lock cannot be acquired, the request fails safely instead of booking without protection.
* WordPress cron must run for reminders and cleanup to execute. Timing depends on site traffic or a configured server cron.
* Configure and test WordPress email delivery. A saved registration does not guarantee email delivery.
* Exclude pages containing registration forms from long-lived full-page caching so that form tokens remain valid.
* Multisite network activation is not supported; use individual site activation and verify on a test site.

== Installation ==

1. Install the plugin ZIP under Plugins > Add New > Upload Plugin, then activate it.
2. As an administrator, create a venue under VeranstaltungsWart > Orte.
3. Create an event and set its date, venue, capacity and registration rules.
4. In the block editor, open the VeranstaltungsWart document panel or the registration block settings. Click “Veranstaltungseinstellungen speichern” to save these settings separately from the post. In the classic editor, settings save with the event.
5. Add the “VeranstaltungsWart – Anmeldung” block to the event and publish it.
6. Add event cards or event lists to any post or page. Select an event explicitly when embedding an individual event in another post.
7. Review email templates and run a test registration and test email before accepting real registrations.
8. Publish your privacy notice and select it in WordPress Settings > Privacy.

== Frequently Asked Questions ==

= Can I keep using shortcodes? =

Yes. Use [event_registration] inside an event or [event_registration id="123"] in another post or page. Existing shortcodes continue to work.

= Which event placeholders are available? =

Inside event content: {event_name}, {event_datum}, {event_wochentag}, {event_zeit}, {event_ende}, {event_ort}, {event_adresse}, {event_hinweis}.

The paragraph toolbar offers “Veranstaltungsdaten einfügen”. Elsewhere, use [event_data id="123" field="event_datum"]. Dates and times follow your site's formats and timezone.

= How does the waiting list work? =

Only confirmed seats occupy capacity. Trusted contacts can be confirmed automatically; unreviewed contacts require manual approval. When seats become available, eligible trusted waiting-list contacts move up in registration order if their entire group fits. Groups that do not fit are skipped. A waiting-list message is not a confirmation.

= How are private locations protected? =

Locations are private by default. Generated public event information hides the venue and address until confirmation. This does not hide addresses you manually include in text or images. Calendar downloads require a confirmed registration and its private link.

= What happens when I change the venue? =

The new venue's capacity is copied into the event. Zero means unlimited. You may then override the capacity manually. A capacity lower than already confirmed seats is rejected.

= Can I hide empty lists and change images? =

Leave the event list's empty-message setting blank to hide empty lists. Both card and list blocks can hide featured images. When displayed, images retain their aspect ratio without cropping, left on desktop and above the text on mobile.

= How do I upgrade from 2.0? =

Back up and test the update first. Existing database records and shortcodes are retained. Do not delete the plugin before updating.

Version 2.0 could grant authors general rights over other authors' blog posts. An administrator-only notice offers an explicit repair. The plugin does not silently remove those general rights, as they may have been assigned intentionally.

= Does the plugin guarantee privacy compliance or email delivery? =

No. It provides export/erasure tools and a privacy-consent field. Site owners must configure their own notices, access rules, retention processes and email delivery appropriately.

== Upgrade Notice ==

= 3.1.0 =
WordPress.org preparation: WordPress 6.5 minimum, translation support, metadata and documentation updates. Back up before updating; do not uninstall.

== Changelog ==

= 3.2.0 =
* Configure event-specific single-choice, multiple-choice and text questions per registration or per person.
* Numbered guests need no names; answers remain intact when questions or options are deactivated or renamed.
* Review answers in the dashboard and participant list, export them as CSV, and count choices by registration status.
* Upgrade existing installations automatically without losing registrations.

= 3.1.2 =
* Preserve HTML image aspect-ratio hints for native lazy auto-sizes; prevent oversized card containers.
* Reproduce the previous height bug with responsive raster images on desktop and mobile.

= 3.1.1 =
* Isolate card geometry from theme layout rules; support picture-wrapped images without cropping.
* Test desktop and mobile cards with both the default theme and Kadence.

= 3.1.0 =
* Prepare metadata and documentation for WordPress.org submission.
* Make new event settings, blocks and registration messages translatable.
* Add official Plugin Check to the release verification workflow.
* Align the minimum WordPress version with the tested 6.5 baseline.

= 3.0.2 =
* Veranstaltungsbilder in Karten und Listen vollständig im ursprünglichen Seitenverhältnis anzeigen, ohne Beschnitt oder Verzerrung.

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

== Registration questions ==

In the event settings (block sidebar, registration block, or classic editor), add up to 20 questions with up to 30 options each. Choose single choice, multiple choice or text; optional or required; and per person or once per registration. Save the event settings separately in the block editor.

Guests are numbered only; no guest names are requested. JavaScript is required when custom questions are active. Removed guest fields are excluded from submission. Question type and scope are fixed after saving; deactivate an existing question and add a new one to change them. Deactivate unused options instead of deleting historical answers.

Answers keep the labels from the time of registration. Dashboard and event participant list show individual answers; CSV includes them. Choice totals distinguish confirmed, waiting-list and pending registrations, excluding cancelled/rejected registrations. Each selected option counts once per answer; group-level answers are not multiplied by the guest count. Renamed labels are reported separately. Free text is shown individually. Existing retention/deletion rules apply to the saved answers.
