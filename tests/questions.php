<?php
// Runs after the existing integration suite in isolated WordPress.
use VW\Events\RegistrationQuestions as Q;
use VW\Events\EventSettings;
use VW\Events\EventRepository;
function q_assert($ok, $message) { if (!$ok) throw new RuntimeException($message); echo "PASS: $message\n"; }
$repo = new EventRepository();
$id = wp_insert_post(['post_type' => 'vw_event', 'post_status' => 'publish', 'post_title' => 'Questions fixture', 'post_author' => 1, 'post_content' => '[event_registration]']);
$questions = [
 ['id'=>'food','label'=>'Essen','hint'=>'Bitte wählen','type'=>'single','scope'=>'person','required'=>true,'active'=>true,'options'=>[['id'=>'vegan','label'=>'Vegan','active'=>true],['id'=>'meat','label'=>'Fleisch','active'=>true],['id'=>'old','label'=>'Alt','active'=>false]]],
 ['id'=>'bus','label'=>'Extras','hint'=>'','type'=>'multiple','scope'=>'person','required'=>true,'active'=>true,'options'=>[['id'=>'bus','label'=>'Bus','active'=>true],['id'=>'tour','label'=>'Führung','active'=>true]]],
 ['id'=>'note','label'=>'Gruppennotiz','hint'=>'','type'=>'text','scope'=>'registration','required'=>false,'active'=>true,'options'=>[]],
 ['id'=>'group','label'=>'Gruppenoption','hint'=>'','type'=>'single','scope'=>'registration','required'=>true,'active'=>true,'options'=>[['id'=>'yes','label'=>'Ja','active'=>true]]]
];
q_assert(EventSettings::save($id, ['vw_event_date'=>gmdate('Y-m-d H:i:s', time()+86400*30), 'vw_allow_guests'=>'1','vw_max_guests'=>3,'vw_questions'=>$questions]) === true, 'Save all question types and scopes');
$values = ['food'=>['vegan','meat','vegan'], 'bus'=>[['bus'],['tour'],['bus','tour']], 'note'=>['registration'=>'No names needed'], 'group'=>['registration'=>'yes']];
$validate = function($answers) use ($id) { return Q::validate_answers($id, 2, wp_json_encode($answers), Q::version($id)); };
$answers = $validate($values);
q_assert(!is_wp_error($answers), 'Each numbered person and group receives their own answers');
$bad = $values; unset($bad['food'][2]); q_assert(is_wp_error($validate($bad)), 'Required guest answer cannot be omitted');
$bad = $values; $bad['food'][1]='old'; q_assert(is_wp_error($validate($bad)), 'Inactive option rejected');
$bad = $values; $bad['food'][1]=['vegan']; q_assert(is_wp_error($validate($bad)), 'Single-choice array injection rejected');
$bad = $values; $bad['bus'][0]=['bus','bus']; q_assert(is_wp_error($validate($bad)), 'Duplicate options cannot inflate totals');
$bad = $values; $bad['bus'][0]=[]; q_assert(is_wp_error($validate($bad)), 'Required multiple choice enforced');
$bad = $values; $bad['note']['registration']=str_repeat('x',5001); q_assert(is_wp_error($validate($bad)), 'Text length enforced');
$bad = $values; $bad['food'][3]='vegan'; q_assert(is_wp_error($validate($bad)), 'Extra ghost guest rejected');
q_assert(is_wp_error(Q::validate_answers($id,2,wp_json_encode($values),'stale')), 'Stale form version rejected');
$person = $repo->get_or_create_person_id('Question', 'Tester', 'questions@example.org');
$reg = $repo->create_registration(['event_post_id'=>$id,'person_id'=>$person,'guest_count'=>2,'seats_total'=>3,'status'=>'bestätigt','answers'=>wp_json_encode($answers),'registered_at'=>current_time('mysql')]);
q_assert($reg > 0, 'Answer snapshot saved with registration');
$original = $repo->get_registration($reg)->answers;
$modified = $questions; $modified[0]['options'][0]['label']='Pflanzlich'; $modified[0]['options'][1]['active']=false;
q_assert(EventSettings::save($id,['vw_questions'=>$modified]) === true, 'Options can be renamed and deactivated');
q_assert($repo->get_registration($reg)->answers === $original && strpos(Q::answer_text($original),'Vegan') !== false, 'Historical labels remain unchanged');
$changed = $modified; $changed[0]['scope']='registration'; q_assert(is_wp_error(EventSettings::save($id,['vw_questions'=>$changed])), 'Existing question scope cannot change');
$omitted = $modified; array_pop($omitted[0]['options']); array_pop($omitted);
q_assert(EventSettings::save($id,['vw_questions'=>$omitted]) === true && count(Q::get($id)) === 4 && !Q::get($id)[3]['active'], 'Omitted questions retained as inactive');
EventSettings::save($id,['vw_questions'=>$questions]);
$rows = [$repo->get_registration($reg)];
foreach (['warteliste','eingegangen','storniert','abgelehnt'] as $status) { $row=clone $rows[0]; $row->status=$status; $rows[]=$row; }
$totals=Q::totals($rows);
$vegan=array_values(array_filter($totals,function($r){return $r['option']==='Vegan';}))[0];
q_assert($vegan['bestätigt']===2 && $vegan['warteliste']===2 && $vegan['eingegangen']===2, 'Totals separate active statuses and exclude rejected/cancelled');
$group=array_values(array_filter($totals,function($r){return $r['question']==='Gruppenoption';}))[0];
q_assert($group['bestätigt']===1, 'Group choice is counted once, not per seat');
$repo->update_registration_status($reg,'warteliste');
$repo->update_person($person,['trust_status'=>'freigegeben']);
$repo->process_waitlist_move_up($id,0);
q_assert($repo->get_registration($reg)->status==='bestätigt' && $repo->get_registration($reg)->answers===$original, 'Waitlist promotion preserves answers');
// Upgrade path: remove only the new column in the isolated fixture database.
global $wpdb;
$wpdb->query("ALTER TABLE {$wpdb->prefix}vw_registrations DROP COLUMN answers");
(new VW\Database\Schema())->create_tables();
q_assert($repo->get_registration($reg)->guest_count == 2 && get_option('vw_schema_version')==='3.2.0', 'Schema upgrade retains existing registrations');
$wpdb->update($wpdb->prefix.'vw_registrations',['answers'=>$original],['id'=>$reg]);
(new VW\Database\Schema())->create_tables();
q_assert($repo->get_registration($reg)->answers===$original, 'Repeated schema upgrade preserves answers');
file_put_contents('/tmp/vw-questions-fixture.json',wp_json_encode(['id'=>$id,'reg'=>$reg]));
