# Direktlink-Sichtbarkeit (3.3.0)

In den zentralen Veranstaltungseinstellungen bzw. im klassischen Editor aktiviert die Checkbox **Nur über Direktlink erreichbar** das Metafeld `vw_direct_link_only` (`1`, sonst `0`). Ohne Metafeld bleibt das bisherige Verhalten erhalten. Der Block-Editor speichert die zentralen Einstellungen mit seinem eigenen Speicherbutton. Im Backend kennzeichnet **Nur Direktlink** solche Veranstaltungen.

Veröffentlichungsstatus, URL, Details, öffentliche Einzelabrufe der REST-API, Anmeldung und explizit eingebundene Veranstaltungskarten bleiben erhalten. Dies ist kein Zugriffsschutz: Jeder mit dem Link kann die Veranstaltung öffnen und weitergeben.

## Abfragen

`EventVisibility` ergänzt öffentliche WP_Query-Abfragen einschließlich get_posts, Archive, Taxonomien, Suche, Feeds, sekundäre Listen und WordPress Query-Loop/Widgets um einen SQL-NOT-EXISTS-Ausschluss. Die Bedingung gilt ausschließlich für `vw_event`. Bestehende Bedingungen bleiben erhalten; LIMIT, Pagination und Trefferzahlen greifen erst danach. Vorherige/nächste Beitragslinks werden ebenfalls gefiltert. Das Plugin hat keine separate Related-Events-Engine.

Plugin-Listen, Core-Sitemap und öffentliche REST-Collections setzen explizit `EventVisibility::public_args()`. Öffentliche REST-Suche wird ebenfalls gefiltert. Berechtigte REST-Abfragen mit `context=edit`, Backend, Cron und CLI bleiben unbeschränkt. Der interne Veranstaltungswähler zeigt nicht gelistete Veranstaltungen nur ihren berechtigten Bearbeitern. Öffentliche Listen bleiben auch für angemeldete Administratoren ausgefiltert. Gezielt eingebundene Karten verwenden Einzelabrufe statt Listen.

Eigene öffentliche AJAX-/REST-Endpunkte und Drittanbieter mit eigenen SQL-Abfragen müssen `EventVisibility::public_args($args)` verwenden bzw. denselben Ausschluss implementieren. Manuell verfasste Links werden nicht entfernt.

## Suchmaschinen und Caches

- WordPress: bestehender `wp_robots`-Filter setzt `noindex`; `wp_sitemaps_posts_query_args` filtert Sitemap-Einträge und deren Seitenzählung.
- Yoast SEO: `wpseo_robots_array` und `wpseo_exclude_from_sitemap_by_post_ids`.
- Rank Math: `rank_math/frontend/robots` sowie SQL-Filter `rank_math/sitemap/get_posts/where` und `rank_math/sitemap/post_count/where`.
- Es werden keine zusätzlichen Meta-Tags oder HTTP-Robots-Header ausgegeben. Die vorhandene Robots-Ausgabe wird angepasst. Andere SEO-Einstellungen bleiben erhalten. Deaktivieren der Checkbox beendet ausschließlich den hier gesetzten Ausschluss; ein anderweitiges noindex bleibt bestehen.
- Änderungen invalidieren WordPress-Post-/Query-Caches und die Sitemap-Caches von Yoast und Rank Math. Die Integration schreibt keine fremden SEO-Metafelder.
- Externe Vollseiten-/CDN-Caches können vor WordPress antworten. Nach einer Sichtbarkeitsänderung dort Detailseite, Archive, Suche, Feeds und Sitemaps leeren. Der Hook `vw_event_visibility_changed($id, $hidden)` steht für eine hostingabhängige Purge-Anbindung bereit.

Nicht ausdrücklich unterstützt sind andere SEO-/Sitemap-Plugins (etwa AIOSEO/SEOPress), statisch erzeugte externe Sitemaps oder Plugins, die Robots-Tags unabhängig von den genannten Filtern erzeugen. Mehrere gleichzeitig aktive SEO-Plugins werden nicht unterstützt. Suchmaschinen müssen eine bereits indexierte URL erneut abrufen, bevor noindex wirksam wird.

## Absicherung und Tests

Speicherung über die bestehende Settings-REST-Route (WordPress-Authentifizierung/REST-Nonce und edit_post-Berechtigung) oder klassischen Nonce-geschützten Save-Hook. Ungültige Werte, fremde Veranstaltungen, Autosaves und Revisionen werden nicht als Sichtbarkeitsänderung übernommen.

Tests: Standardwerte, gemischte Beitragstypen, Pagination, Cache-Aktualisierung beim Zurückschalten, direkte Karte, öffentliche und administrative REST-Collections, Einzelabruf, Autorberechtigungen, Backend-Kennzeichnung, Core-Sitemap. HTTP-Prüfungen öffnen echte Archive, Suche, Taxonomie und Feed und melden einen anonymen Besucher zur versteckten Veranstaltung an. Separate SEO-Smoke-Tests prüfen die tatsächliche Robots-Ausgabe und XML-Sitemaps mit einzeln aktivierten Yoast und Rank Math.
