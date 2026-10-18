<?php
// Run only in an isolated WordPress installation: wp eval-file tests/integration.php
use VW\Events\EventSettings;
use VW\Events\EventRepository;
use VW\Events\EventPresentation;
use VW\Core\Capabilities;
function vw_assert($test, $message) {
    if (!$test) throw new RuntimeException($message);
    echo "PASS: $message\n";
}
add_filter('pre_wp_mail', '__return_true');
$repo = new EventRepository();
$admin = get_user_by('login', 'admin');
wp_set_current_user($admin->ID);
$id = wp_insert_post(['post_type' => 'vw_event', 'post_status' => 'publish', 'post_title' => 'Integration event', 'post_author' => $admin->ID, 'post_content' => 'Datum: {event_datum}. Ort: {event_ort}. [event_registration]']);
$start = current_datetime()->modify('+30 days')->format('Y-m-d H:i:s');
$repo->save_location(0, ['name' => 'Geheimer Ort', 'address' => 'Geheime Straße 1', 'default_capacity' => 2]);
global $wpdb;
$location = $wpdb->insert_id;
vw_assert(EventSettings::save($id, ['vw_event_date' => $start, 'vw_max_capacity' => 2, 'vw_location_id' => $location]) === true, 'Settings saved with lock');
vw_assert(is_wp_error(EventSettings::save($id, ['vw_event_end' => '2020-01-01T12:00'])), 'Invalid end rejected');
vw_assert(EventSettings::date('2026-02-30T12:00') === null, 'Invalid date rejected');
vw_assert(EventSettings::date('2026-03-29T02:30') === null, 'DST gap rejected');
vw_assert(EventPresentation::values($id)['event_adresse'] === '', 'Private address is hidden');
vw_assert(EventPresentation::values($id, true)['event_adresse'] === 'Geheime Straße 1', 'Confirmed context reveals address');
$person1 = $repo->get_or_create_person_id('Test', 'One', 'one@example.org');
$person2 = $repo->get_or_create_person_id('Test', 'Two', 'two@example.org');
$person3 = $repo->get_or_create_person_id('Test', 'Three', 'three@example.org');
$repo->update_person($person2, ['trust_status' => 'freigegeben']);
$base = ['event_post_id' => $id, 'guest_count' => 0, 'seats_total' => 1, 'message' => '', 'registered_at' => current_time('mysql')];
$r1 = $repo->create_registration(array_merge($base, ['person_id' => $person1, 'status' => 'bestätigt', 'seats_total' => 2, 'guest_count' => 1]));
$r2 = $repo->create_registration(array_merge($base, ['person_id' => $person2, 'status' => 'warteliste']));
$r3 = $repo->create_registration(array_merge($base, ['person_id' => $person3, 'status' => 'eingegangen']));
$stats = $repo->get_registration_stats($id);
vw_assert($stats['current'] === 2 && $stats['waitlist'] === 1 && $stats['pending_count'] === 1, 'Separate confirmed, waitlist and pending statistics');
vw_assert(is_wp_error($repo->change_status($r3, 'bestätigt')), 'Manual confirmation cannot overbook');
vw_assert(is_wp_error(EventSettings::save($id, ['vw_max_capacity' => 1])), 'Capacity cannot drop below confirmed seats');
vw_assert($repo->change_status($r1, 'storniert') === true, 'Cancellation succeeds');
vw_assert($repo->get_registration($r2)->status === 'bestätigt', 'Trusted waitlist person moves up');
vw_assert($repo->change_status($r1, 'storniert') === false, 'Repeated cancellation is idempotent');
vw_assert($repo->get_registration($r3)->status === 'eingegangen', 'Untrusted person requires approval');
vw_assert((int) $repo->active_registration($id, $person2) === (int) $r2, 'Duplicate lookup finds active registration');
$author_id = wp_insert_user(['user_login' => 'testauthor', 'user_pass' => 'test', 'role' => 'author']);
wp_set_current_user($author_id);
vw_assert(!Capabilities::can_manage_event($id), 'Author cannot manage someone else’s event');
$own = wp_insert_post(['post_type' => 'vw_event', 'post_status' => 'draft', 'post_title' => 'Private draft', 'post_author' => $author_id]);
vw_assert(Capabilities::can_manage_event($own), 'Author can manage own event');
$rest = new WP_REST_Request('GET', '/veranstaltungswart/v1/settings/' . $id);
vw_assert(rest_do_request($rest)->get_status() === 403, 'REST denies foreign settings');
wp_set_current_user(0);
vw_assert(rest_do_request(new WP_REST_Request('GET', '/veranstaltungswart/v1/settings/' . $id))->get_status() >= 400, 'Anonymous settings inaccessible');
$card = (new VW\Blocks\BlockManager())->card($id);
vw_assert(strpos($card, 'Geheime Straße') === false && strpos($card, 'Geheimer Ort') === false, 'Card respects hidden location');
vw_assert((new VW\Blocks\BlockManager())->card($own) === '', 'Draft event cannot be embedded publicly');
$form = do_shortcode('[event_registration id="' . $id . '"]');
vw_assert(strpos($form, 'event_id') !== false, 'Shortcode with ID works outside an event');
$block = '<!-- wp:veranstaltungswart/event-card {"eventId":' . $id . '} /-->';
vw_assert(strpos(do_blocks($block), 'Integration event') !== false, 'Server-rendered card block works');
vw_assert(strpos(EventPresentation::replace('{event_datum} {event_zeit}', $id), '{event_') === false, 'Text placeholders are resolved');
// Fixture for actual HTTP endpoint tests: empty event, guests disabled, required message.
wp_set_current_user($admin->ID);
$http = wp_insert_post(['post_type' => 'vw_event', 'post_status' => 'publish', 'post_title' => 'HTTP fixture', 'post_author' => $admin->ID, 'post_content' => '[event_registration]']);
EventSettings::save($http, ['vw_event_date' => $start, 'vw_max_capacity' => 2, 'vw_allow_guests' => '0', 'vw_allow_message' => '1', 'vw_message_required' => '1']);
$http_trusted = $repo->get_or_create_person_id('HTTP', 'Trusted', 'trusted@example.org');
$repo->update_person($http_trusted, ['trust_status' => 'freigegeben']);
foreach (['race1', 'race2', 'race3'] as $name) {
    $pid = $repo->get_or_create_person_id('Race', $name, $name . '@example.org');
    $repo->update_person($pid, ['trust_status' => 'freigegeben']);
}
file_put_contents('/tmp/vw-http-fixture.json', wp_json_encode(['id' => $http, 'confirmed' => $r2, 'confirmed_hash' => wp_hash('two@example.org' . $r2)]));
echo "Integration tests complete.\n";

// The dashboard must use exactly the permissions enforced by registration actions.
$dashboard = new VW\Dashboard\DashboardManager();
$owned_event = wp_insert_post(['post_type' => 'vw_event', 'post_status' => 'publish', 'post_title' => 'Author-only event', 'post_author' => $author_id]);
EventSettings::save($owned_event, ['vw_event_date' => $start]);
$owned_reg = $repo->create_registration(array_merge($base, ['event_post_id' => $owned_event, 'person_id' => $person3, 'status' => 'eingegangen']));
wp_set_current_user($author_id);
$author_dashboard = $dashboard->get_dashboard_data();
vw_assert($author_dashboard['total_pending_count'] === 1 && $author_dashboard['active_events_count'] === 1, 'Author dashboard counts only own permitted events');
vw_assert(count($author_dashboard['pending']) === 1 && (int) $author_dashboard['pending'][0]->id === (int) $owned_reg, 'Author dashboard excludes foreign participant details');
$deny = function($caps, $cap, $user_id, $args) use ($owned_event) {
    return $cap === 'edit_post' && (int) ($args[0] ?? 0) === (int) $owned_event ? ['do_not_allow'] : $caps;
};
add_filter('map_meta_cap', $deny, 20, 4);
vw_assert($dashboard->get_dashboard_data()['total_pending_count'] === 0, 'Dashboard honours per-event permission filters');
remove_filter('map_meta_cap', $deny, 20);
wp_set_current_user($admin->ID);
$admin_dashboard = $dashboard->get_dashboard_data();
vw_assert($admin_dashboard['total_pending_count'] === 2, 'Administrator dashboard includes both authors');
$editor = wp_insert_user(['user_login' => 'testeditor', 'user_pass' => 'test', 'role' => 'editor']);
wp_set_current_user($editor);
vw_assert($dashboard->get_dashboard_data()['total_pending_count'] === 2, 'Editor dashboard includes events they can manage');
wp_set_current_user(0);
vw_assert($dashboard->get_dashboard_data()['pending'] === [], 'Anonymous dashboard contains no participants');
wp_set_current_user($admin->ID);
$capacity_event = wp_insert_post(['post_type' => 'vw_event', 'post_status' => 'draft', 'post_title' => 'Capacity test', 'post_author' => $admin->ID]);
vw_assert(EventSettings::save($capacity_event, ['vw_location_id' => $location]) === true && (int) EventSettings::get($capacity_event, 'vw_max_capacity') === 2, 'Location supplies capacity when none explicitly provided');
EventSettings::save($capacity_event, ['vw_max_capacity' => 9]);
EventSettings::save($capacity_event, ['vw_location_id' => $location]);
vw_assert((int) EventSettings::get($capacity_event, 'vw_max_capacity') === 9, 'Unchanged location preserves manual capacity');
$repo->save_location(0, ['name' => 'Unlimited test location', 'address' => '', 'default_capacity' => 0]);
$unlimited_location = $wpdb->insert_id;
EventSettings::save($capacity_event, ['vw_location_id' => $unlimited_location]);
vw_assert((int) EventSettings::get($capacity_event, 'vw_max_capacity') === 0, 'Location capacity zero is inherited as unlimited');
EventSettings::save($capacity_event, ['vw_location_id' => $location, 'vw_max_capacity' => 17]);
vw_assert((int) EventSettings::get($capacity_event, 'vw_max_capacity') === 17, 'Explicit override wins over newly selected location');
echo "Dashboard and capacity regression tests complete.\n";

// Give the HTTP/editor fixture a real optional featured image for responsive layout tests.
$upload = wp_upload_bits('vw-layout-test.png', null, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ9sAAAAASUVORK5CYII='));
vw_assert(!$upload['error'], 'Layout fixture image uploaded');
$attachment = wp_insert_attachment(['post_title' => 'Layout image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], $upload['file'], $http);
wp_update_attachment_metadata($attachment, ['width' => 1, 'height' => 1, 'file' => _wp_relative_upload_path($upload['file'])]);
set_post_thumbnail($http, $attachment);
vw_assert(strpos((new VW\Blocks\BlockManager())->card($http, ['showImage' => false]), 'vw-card-image') === false, 'Optional image can still be hidden');
