<?php
namespace VW\Events;
if (!defined('ABSPATH')) exit;

/** Event-specific questions and immutable answer snapshots, without guest identities. */
class RegistrationQuestions {
    public static function get($id) {
        $value = get_post_meta($id, 'vw_questions', true);
        return is_array($value) ? $value : [];
    }
    public static function version($id) { return hash('sha256', wp_json_encode(self::get($id))); }
    private static function error() {
        return new \WP_Error('invalid_questions', __('Bitte Fragen und Optionen prüfen: maximal 20 Fragen, 30 Optionen je Frage, eindeutige Kennungen und mindestens eine aktive Option je aktiver Auswahlfrage. Typ und Geltungsbereich bestehender Fragen bleiben unverändert.', 'veranstaltungswart'));
    }
    private static function text($value, $limit) {
        return is_string($value) && strlen($value) <= $limit ? sanitize_textarea_field($value) : null;
    }
    private static function valid_id($value) { return is_string($value) && preg_match('/^[a-zA-Z0-9_-]{1,64}$/D', $value); }
    public static function normalize($input, $old = []) {
        if (!is_array($input) || count($input) > 20) return self::error();
        $result = []; $seen = [];
        $previous = array_column($old, null, 'id');
        foreach ($input as $q) {
            if (!is_array($q) || !self::valid_id($q['id'] ?? null) || isset($seen[$q['id']])) return self::error();
            $seen[$q['id']] = true;
            $label = self::text($q['label'] ?? null, 200);
            $hint = self::text($q['hint'] ?? '', 1000);
            if (!$label || $hint === null || !in_array($q['type'] ?? '', ['single', 'multiple', 'text'], true) || !in_array($q['scope'] ?? '', ['registration', 'person'], true)) return self::error();
            $before = $previous[$q['id']] ?? null;
            if ($before && ($before['type'] !== $q['type'] || $before['scope'] !== $q['scope'])) return self::error();
            $item = ['id' => $q['id'], 'label' => $label, 'hint' => $hint, 'type' => $q['type'], 'scope' => $q['scope'], 'required' => !empty($q['required']), 'active' => !empty($q['active']), 'options' => []];
            $options = $q['options'] ?? [];
            if (!is_array($options) || count($options) > 30) return self::error();
            $option_ids = [];
            foreach ($options as $option) {
                if (!is_array($option) || !self::valid_id($option['id'] ?? null) || isset($option_ids[$option['id']])) return self::error();
                $name = self::text($option['label'] ?? null, 200);
                if (!$name) return self::error();
                $option_ids[$option['id']] = true;
                $item['options'][] = ['id' => $option['id'], 'label' => $name, 'active' => !empty($option['active'])];
            }
            // Missing records are archived, never silently erased by an editor.
            foreach (($before['options'] ?? []) as $option) if (!isset($option_ids[$option['id']])) { $option['active'] = false; $item['options'][] = $option; }
            if (count($item['options']) > 30 || ($item['active'] && $item['type'] !== 'text' && !array_filter($item['options'], function($o) { return $o['active']; }))) return self::error();
            $result[] = $item;
        }
        foreach ($old as $q) if (!isset($seen[$q['id']])) { $q['active'] = false; $result[] = $q; }
        return count($result) <= 20 ? $result : self::error();
    }
    public static function validate_answers($id, $guests, $raw, $version) {
        $questions = array_filter(self::get($id), function($q) { return $q['active']; });
        if (!$questions) return [];
        $error = new \WP_Error('invalid_answers', __('Bitte alle Zusatzfragen vollständig beantworten. Falls die Fragen geändert wurden, lade das Formular neu.', 'veranstaltungswart'));
        if (!is_string($version) || !hash_equals(self::version($id), $version) || !is_string($raw) || strlen($raw) > 200000) return $error;
        $input = json_decode($raw, true);
        if (!is_array($input)) return $error;
        $snapshot = []; $allowed = [];
        foreach ($questions as $q) {
            $allowed[] = $q['id'];
            $answers = $input[$q['id']] ?? [];
            if (!is_array($answers)) return $error;
            $keys = $q['scope'] === 'person' ? range(0, $guests) : ['registration'];
            foreach (array_keys($answers) as $key) if (!in_array((string) $key, array_map('strval', $keys), true)) return $error;
            $choices = array_column(array_filter($q['options'], function($o) { return $o['active']; }), 'label', 'id');
            $rows = [];
            foreach ($keys as $key) {
                $value = $answers[$key] ?? ($q['type'] === 'multiple' ? [] : '');
                if ($q['type'] === 'text') {
                    $text = self::text($value, 5000);
                    if ($text === null || ($q['required'] && trim($text) === '')) return $error;
                    $rows[(string) $key] = $text;
                } else {
                    if ($q['type'] === 'single') {
                        if (!is_string($value)) return $error;
                        $value = $value === '' ? [] : [$value];
                    }
                    if (!is_array($value) || count($value) > 30 || ($q['required'] && !$value)) return $error;
                    $selected = [];
                    foreach ($value as $option_id) {
                        if (!is_string($option_id) || !isset($choices[$option_id]) || isset($selected[$option_id])) return $error;
                        $selected[$option_id] = $choices[$option_id];
                    }
                    $rows[(string) $key] = $selected;
                }
            }
            $snapshot[] = ['id' => $q['id'], 'label' => $q['label'], 'type' => $q['type'], 'scope' => $q['scope'], 'answers' => $rows];
        }
        foreach (array_keys($input) as $key) if (!in_array($key, $allowed, true)) return $error;
        // Bound the final stored snapshot as labels are copied for every person.
        return strlen(wp_json_encode($snapshot)) <= 500000 ? $snapshot : $error;
    }
    public static function person_label($key, $public = false) {
        if ((string) $key === 'registration') return __('Gesamte Anmeldung', 'veranstaltungswart');
        if ((int) $key === 0) return $public ? __('Du', 'veranstaltungswart') : __('Hauptperson', 'veranstaltungswart');
        /* translators: %d: guest number, not a name. */
        return sprintf(__('Begleitperson %d', 'veranstaltungswart'), (int) $key);
    }
    public static function decode($raw) { $data = json_decode($raw ?: '[]', true); return is_array($data) ? $data : []; }
    public static function answer_text($raw) {
        $lines = [];
        foreach (self::decode($raw) as $q) foreach ($q['answers'] as $key => $answer) {
            $lines[] = self::person_label($key) . ' – ' . $q['label'] . ': ' . (is_array($answer) ? implode(', ', $answer) : $answer);
        }
        return implode("\n", $lines);
    }
    public static function totals($registrations) {
        $totals = [];
        foreach ($registrations as $reg) {
            if (!in_array($reg->status, ['bestätigt', 'warteliste', 'eingegangen'], true)) continue;
            foreach (self::decode($reg->answers ?? '') as $q) {
                if ($q['type'] === 'text') continue;
                foreach ($q['answers'] as $answer) foreach ($answer as $option_id => $label) {
                    // Keep renamed labels distinct so old responses retain their meaning.
                    $key = hash('sha256', wp_json_encode([$q['id'], $q['label'], $q['scope'], $option_id, $label]));
                    if (!isset($totals[$key])) $totals[$key] = ['question' => $q['label'], 'option' => $label, 'scope' => $q['scope'], 'bestätigt' => 0, 'warteliste' => 0, 'eingegangen' => 0];
                    $totals[$key][$reg->status]++;
                }
            }
        }
        return array_values($totals);
    }
    public static function render_totals($id) {
        if (!\VW\Core\Capabilities::can_manage_event($id)) return;
        $totals = self::totals((new EventRepository())->get_registrations_for_event($id));
        echo '<h3>' . esc_html__('Auswertung der Zusatzfragen', 'veranstaltungswart') . '</h3>';
        echo '<p>' . esc_html__('Gezählt werden ausgewählte Optionen, pro Person oder pro Anmeldung. Stornierte und abgelehnte Anmeldungen zählen nicht. Freitext steht bei der jeweiligen Anmeldung.', 'veranstaltungswart') . '</p>';
        if (!$totals) { echo '<p>' . esc_html__('Noch keine Auswahlantworten vorhanden.', 'veranstaltungswart') . '</p>'; return; }
        echo '<table class="widefat striped"><thead><tr>';
        foreach ([__('Frage', 'veranstaltungswart'), __('Option', 'veranstaltungswart'), __('Abfrage', 'veranstaltungswart'), __('Bestätigt', 'veranstaltungswart'), __('Warteliste', 'veranstaltungswart'), __('Prüfung offen', 'veranstaltungswart')] as $label) echo '<th>' . esc_html($label) . '</th>';
        echo '</tr></thead><tbody>';
        foreach ($totals as $row) echo '<tr><td>' . esc_html($row['question']) . '</td><td>' . esc_html($row['option']) . '</td><td>' . esc_html($row['scope'] === 'person' ? __('Pro Person', 'veranstaltungswart') : __('Pro Anmeldung', 'veranstaltungswart')) . '</td><td>' . (int) $row['bestätigt'] . '</td><td>' . (int) $row['warteliste'] . '</td><td>' . (int) $row['eingegangen'] . '</td></tr>';
        echo '</tbody></table>';
    }
    public static function render_fields($questions, $scope, $person, $uid) {
        foreach ($questions as $q) {
            if (!$q['active'] || $q['scope'] !== $scope) continue;
            $field_id = $uid . '-' . $q['id'] . '-' . $person;
            $required = $q['required'] ? ' required' : '';
            $data = ' data-vw-question="' . esc_attr($q['id']) . '" data-vw-person="' . esc_attr($person) . '"';
            echo '<fieldset class="vw-question" data-required="' . ($q['required'] ? '1' : '0') . '"><legend>' . esc_html($q['label'] . ($q['required'] ? ' *' : '')) . '</legend>';
            if ($q['hint']) echo '<p id="' . esc_attr($field_id . '-hint') . '">' . esc_html($q['hint']) . '</p>';
            $data .= $q['hint'] ? ' aria-describedby="' . esc_attr($field_id . '-hint') . '"' : '';
            if ($q['type'] !== 'multiple') $data .= ' aria-label="' . esc_attr($q['label']) . '"';
            if ($q['type'] === 'text') {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes escaped above; required is a constant string.
                echo '<textarea rows="3" maxlength="5000"' . $data . $required . '></textarea>';
            } elseif ($q['type'] === 'single') {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes escaped above; required is a constant string.
                echo '<select' . $data . $required . '><option value="">' . esc_html__('Bitte auswählen', 'veranstaltungswart') . '</option>';
                foreach ($q['options'] as $option) if ($option['active']) echo '<option value="' . esc_attr($option['id']) . '">' . esc_html($option['label']) . '</option>';
                echo '</select>';
            } else {
                foreach ($q['options'] as $option) if ($option['active']) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Data attributes escaped above; remaining values escaped at output.
                    echo '<label class="vw-choice"><input type="checkbox" value="' . esc_attr($option['id']) . '"' . $data . '><span>' . esc_html($option['label']) . '</span></label>';
                }
            }
            echo '</fieldset>';
        }
    }
}
