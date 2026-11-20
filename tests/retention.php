<?php
use VW\Privacy\Retention;
use VW\Events\EventRepository;
function retention_assert($ok, $message) { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
global $wpdb;
$repo = new EventRepository();
$settings = new Retention();
delete_option(Retention::OPTION);
retention_assert(Retention::days() === 90, 'Default retention is 90 days');
update_option(Retention::OPTION, 30);
foreach (['', 0, -1, '1.5', 'abc', [], 3651, true] as $bad) retention_assert($settings->sanitize($bad) === 30, 'Invalid retention preserves current setting');
retention_assert($settings->sanitize('365') === 365, 'Valid integer setting accepted');
$now = current_datetime();
$event = function($start, $end = '') {
    $id = wp_insert_post(['post_type'=>'vw_event','post_status'=>'publish','post_title'=>'Retention fixture']);
    update_post_meta($id, 'vw_event_date', $start);
    update_post_meta($id, 'vw_event_end', $end);
    return $id;
};
$old = $event($now->modify('-60 days')->format('Y-m-d H:i:s'));
$future = $event($now->modify('+30 days')->format('Y-m-d H:i:s'));
$ongoing = $event($now->modify('-60 days')->format('Y-m-d H:i:s'), $now->modify('+2 days')->format('Y-m-d H:i:s'));
$missing = $event('');
$invalid = $event('2026-02-30 10:00:00');
$bad_end = $event($now->modify('-60 days')->format('Y-m-d H:i:s'), 'invalid');
retention_assert(Retention::deadline($old)->format('H:i:s') === '23:59:59', 'Missing end uses end of local event day');
retention_assert(Retention::deadline($ongoing) > $now && Retention::deadline($invalid) === null && Retention::deadline($bad_end) === null, 'Multi-day and invalid dates protected');
$person = function($label, $days = 100) use ($repo, $wpdb, $now) {
    $id = $repo->get_or_create_person_id('Retention', $label, 'retention-'.$label.'@example.org');
    $wpdb->update($wpdb->prefix.'vw_persons', ['created_at'=>$now->modify('-'.$days.' days')->format('Y-m-d H:i:s')], ['id'=>$id]);
    return $id;
};
$shared = $person('shared'); $expired = $person('expired'); $orphan = $person('orphan'); $listed = $person('listed'); $recent = $person('recent', 1);
$wpdb->insert($wpdb->prefix.'vw_person_list', ['person_id'=>$listed,'list_id'=>999999]);
$reg = function($event, $person, $status = 'eingegangen') use ($repo) {
    return $repo->create_registration(['event_post_id'=>$event,'person_id'=>$person,'status'=>$status,'message'=>'Private text','answers'=>'{"private":"answer"}','registered_at'=>'2020-01-01 00:00:00']);
};
$old_regs = [];
foreach (['eingegangen','bestätigt','warteliste','storniert','abgelehnt'] as $status) $old_regs[] = $reg($old, $expired, $status);
$old_regs[] = $reg($old, $shared);
$keep = [$reg($future,$shared),$reg($ongoing,$shared),$reg($missing,$shared),$reg($invalid,$shared),$reg($bad_end,$shared),$reg(99999999,$shared)];
// Existing registrations disappear only when the chosen period has elapsed.
update_option(Retention::OPTION, 365);
Retention::cleanup();
retention_assert((bool)$repo->get_registration($old_regs[0]), 'Longer retention preserves old registrations');
update_option(Retention::OPTION, 30);
$result = (new VW\Persons\PersonManager())->run_gdpr_cleanup();
foreach ($old_regs as $id) retention_assert(!$repo->get_registration($id), 'Expired registration including answers removed for every status');
foreach ($keep as $id) retention_assert((bool)$repo->get_registration($id), 'Future, ongoing, missing and invalid events retained');
retention_assert(!$repo->get_person($expired) && !$repo->get_person($orphan), 'Unused old contacts removed');
retention_assert($repo->get_person($shared) && $repo->get_person($listed) && $repo->get_person($recent), 'Referenced, listed and recent contacts retained');
retention_assert($result['errors'] === 0 && $result['skipped'] >= 4, 'Cleanup records skipped events without personal data');
retention_assert(Retention::cleanup()['registrations'] === 0, 'Repeated cleanup is idempotent');
wp_set_current_user(1);
ob_start(); $settings->render(); $html=ob_get_clean();
retention_assert(strpos($html, 'name="vw_retention_days"') !== false && strpos($html, '_wpnonce') !== false && strpos($html, 'options.php') !== false, 'Settings use WordPress nonce and protected options endpoint');
wp_set_current_user(0);
ob_start(); $settings->render(); $html=ob_get_clean();
retention_assert($html === '', 'Unauthorized settings rendering blocked');
// Remove own fixtures so later HTTP/browser tests stay independent.
foreach (array_merge($old_regs,$keep) as $id) $repo->delete_registration($id);
foreach ([$old,$future,$ongoing,$missing,$invalid,$bad_end] as $id) wp_delete_post($id,true);
foreach ([$shared,$expired,$orphan,$listed,$recent] as $id) $wpdb->delete($wpdb->prefix.'vw_persons',['id'=>$id]);
$wpdb->delete($wpdb->prefix.'vw_person_list',['person_id'=>$listed]);
delete_option(Retention::OPTION);
