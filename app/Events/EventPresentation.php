<?php
namespace VW\Events;
if (!defined('ABSPATH')) exit;

class EventPresentation {
    public function register() {
        add_filter('the_content', [$this, 'content'], 20);
        add_shortcode('event_data', [$this, 'shortcode']);
    }
    public static function values($id, $reveal_location = false) {
        $start = EventSettings::date(EventSettings::get($id, 'vw_event_date'));
        $end = EventSettings::date(EventSettings::get($id, 'vw_event_end'));
        $location = (new EventRepository())->get_location_by_event($id);
        $show = $reveal_location || EventSettings::get($id, 'vw_location_private') === '0';
        return [
            'event_name' => get_the_title($id),
            'event_datum' => $start ? wp_date(get_option('date_format'), $start->getTimestamp(), wp_timezone()) : '',
            'event_wochentag' => $start ? wp_date('l', $start->getTimestamp(), wp_timezone()) : '',
            'event_zeit' => $start ? wp_date(get_option('time_format'), $start->getTimestamp(), wp_timezone()) : '',
            'event_ende' => $end ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), $end->getTimestamp(), wp_timezone()) : '',
            'event_ort' => $show && $location ? $location->name : __('Ort wird nach Bestätigung mitgeteilt', 'veranstaltungswart'),
            'event_adresse' => $show && $location ? $location->address : '',
            'event_hinweis' => EventSettings::get($id, 'vw_event_notes'),
        ];
    }
    public static function replace($text, $id, $reveal_location = false) {
        $map = [];
        foreach (self::values($id, $reveal_location) as $key => $value) $map['{' . $key . '}'] = esc_html($value);
        return strtr($text, $map);
    }
    public function content($content) {
        return get_post_type() === 'vw_event' ? self::replace($content, get_the_ID()) : $content;
    }
    public function shortcode($atts) {
        $atts = shortcode_atts(['id' => 0, 'field' => 'event_datum'], $atts, 'event_data');
        $id = absint($atts['id']) ?: get_the_ID();
        if (!EventSettings::is_public($id)) return '';
        $values = self::values($id);
        return isset($values[$atts['field']]) ? esc_html($values[$atts['field']]) : '';
    }
}
