# WordPress.org submission — VeranstaltungsWart 3.1.0

## Submission package

Use the inner `veranstaltungswart-3.1.0.zip` from the successful GitHub Actions artifact, not the GitHub source-code archive or the outer artifact ZIP. The release contains runtime PHP, CSS and JavaScript, the translation template, readme and GPL license. Development files, tests and the unused repository logo are excluded.

The supported baseline is WordPress 6.5 / PHP 7.4. The second CI job tests the latest stable WordPress with PHP 8.3. The readme's `Tested up to` is 7.1, verified against WordPress 7.1.3 during preparation.

## Owner steps before submission

1. Confirm that `realsven87` is the WordPress.org account to credit in Contributors; this entry is not inferred proof of account ownership.
2. Test the release on a staging copy of the intended site, including email delivery through the actual configured mail service. Automated tests suppress outbound mail and cannot verify hosting deliverability.
3. Submit the inner ZIP at https://wordpress.org/plugins/developers/add/ . Request `veranstaltungswart` as the slug if available. Availability and final review approval are determined by WordPress.org.
4. Respond to review feedback and publish via the assigned SVN repository after approval. No WordPress.org submission or SVN upload is performed by this change.

GitHub may remain the development repository. Release deployment to SVN can be automated after repository access is granted. Do not store SVN passwords or WordPress.org credentials in the repository.

## Review evidence

Each CI run publishes a `wordpress-org-check` artifact. The official Plugin Check includes static and runtime checks. Its warnings require review; a passing check is not a guarantee of directory approval or an independent security audit.

Database queries use WordPress's database API. Booking state must be read fresh while holding the per-event MySQL advisory lock, so booking reads deliberately avoid persistent caching. SQL sort direction is limited to literal ASC/DESC values and columns to a fixed allowlist.

Views are included inside controller methods; their local variables are not public globals despite static-analysis warnings about include files. Existing `VW` namespaces and identifiers are retained for compatibility. Read-only filters and status notices do not mutate data; mutation handlers check permissions and nonces. Public cancellation/calendar links are authenticated by a registration-specific hash; cancellation also requires a POST confirmation and nonce.

Two narrow filesystem exceptions use PHP streams: verified temporary CSV uploads are parsed record-by-record with `fgetcsv`, and CSV exports write to `php://output`. Neither writes arbitrary filesystem paths. Calendar responses use RFC 5545 escaping rather than HTML escaping. The cancellation form's values are escaped while assembling the HTML.

## Translation maintenance

New PHP and JavaScript interface strings use the `veranstaltungswart` text domain. The editor calls `wp_set_script_translations`. German remains the source language; no complete English UI translation is bundled. The directory description and setup instructions are in English.

Regenerate the template with:

```sh
wp i18n make-pot . languages/veranstaltungswart.pot --domain=veranstaltungswart --exclude=tests,node_modules,.git,.github
```

Custom user-authored event content and saved email templates are not translated automatically.

## Optional directory screenshots

The `wordpress-org-screenshots` CI artifact contains captures of the real test installation using synthetic registrations only:

- `screenshot-1.png`: permissions-scoped dashboard.
- `screenshot-2.png`: event settings in the block editor.
- `screenshot-3.png`: public registration form.

Review these images before publication. If used, upload them to the SVN root `assets/` directory and add corresponding numbered captions to `readme.txt`. Screenshots are intentionally not advertised in the readme before they are uploaded to WordPress.org. A banner and icon are optional and are not bundled.

## Data behaviour

The readme documents fields stored, WordPress mail delivery, privacy export/erasure, and daily cleanup. Cleanup currently uses the last registration timestamp (not event date) and a twelve-month retention period. No automatic GDPR compliance is claimed. The plugin supplies suggested privacy-policy text for the site owner to adapt.

Deactivation preserves data. Uninstall deletes custom tables and plugin rights; event and email-template posts remain in the WordPress posts table. Never uninstall to perform an update. Multisite network activation is not supported or tested.
