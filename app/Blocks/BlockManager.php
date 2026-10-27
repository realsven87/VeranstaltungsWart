<?php
namespace VW\Blocks;
if (!defined('ABSPATH')) exit;
use VW\Events\EventSettings;
use VW\Events\EventRepository;
use VW\Events\EventPresentation;
use VW\Core\Capabilities;

class BlockManager {
    public function register() {
        add_action('init', [$this, 'blocks']);
        add_action('admin_enqueue_scripts', function() {
            $screen = get_current_screen();
            if ($screen && $screen->post_type === 'vw_event') {
                wp_enqueue_style('vw-editor-settings');
                wp_enqueue_style('wp-components');
                wp_enqueue_script('vw-question-editor');
            }
        });
        add_action('rest_api_init', [$this, 'routes']);
    }
    public function blocks() {
        wp_register_style('vw-blocks', VW_PLUGIN_URL . 'assets/blocks.css', [], VW_VERSION);
        wp_register_style('vw-editor-settings', VW_PLUGIN_URL . 'assets/editor.css', [], VW_VERSION);
        wp_register_script('vw-registration', VW_PLUGIN_URL . 'assets/registration.js', [], VW_VERSION, true);
        wp_register_script('vw-question-editor', VW_PLUGIN_URL . 'assets/questions-editor.js', ['wp-element', 'wp-components', 'wp-i18n'], VW_VERSION, true);
        wp_set_script_translations('vw-question-editor', 'veranstaltungswart', VW_PLUGIN_DIR . 'languages');
        wp_register_script('vw-editor', VW_PLUGIN_URL . 'assets/editor.js', ['vw-question-editor', 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-rich-text', 'wp-i18n', 'wp-plugins', 'wp-edit-post'], VW_VERSION, true);
        wp_set_script_translations('vw-editor', 'veranstaltungswart', VW_PLUGIN_DIR . 'languages');
        wp_localize_script('vw-editor', 'vwEditor', ['fields' => EventSettings::fields()]);
        $shared = ['eventId' => ['type' => 'integer', 'default' => 0]];
        $definitions = [
            'registration' => ['heading' => ['type' => 'string', 'default' => __('Anmeldung', 'veranstaltungswart')], 'button' => ['type' => 'string', 'default' => __('Jetzt anmelden', 'veranstaltungswart')], 'showPlaces' => ['type' => 'boolean', 'default' => true]],
            'event-card' => ['showImage' => ['type' => 'boolean', 'default' => true], 'showLocation' => ['type' => 'boolean', 'default' => true], 'button' => ['type' => 'string', 'default' => __('Zur Veranstaltung', 'veranstaltungswart')]],
            'event-list' => ['count' => ['type' => 'integer', 'default' => 6], 'columns' => ['type' => 'integer', 'default' => 3], 'category' => ['type' => 'integer', 'default' => 0], 'showPast' => ['type' => 'boolean', 'default' => false], 'showCanceled' => ['type' => 'boolean', 'default' => true], 'emptyText' => ['type' => 'string', 'default' => ''], 'showImage' => ['type' => 'boolean', 'default' => true], 'showLocation' => ['type' => 'boolean', 'default' => true]],
        ];
        foreach ($definitions as $name => $attributes) register_block_type('veranstaltungswart/' . $name, [
            'api_version' => 2, 'editor_script' => 'vw-editor', 'editor_style' => 'vw-editor-settings', 'style' => 'vw-blocks',
            'attributes' => array_merge($shared, $attributes),
            'supports' => ['html' => false, 'align' => ['wide', 'full'], 'color' => ['text' => true, 'background' => true], 'spacing' => ['padding' => true, 'margin' => true]],
            'uses_context' => ['postId'],
            'render_callback' => function($attributes, $content, $block) use ($name) {
                $id = absint($attributes['eventId'] ?? 0) ?: absint($block->context['postId'] ?? get_the_ID());
                if ($name === 'event-list') $html = $this->listing($attributes);
                elseif ($name === 'event-card') $html = $this->card($id, $attributes);
                else $html = (new \VW\Registrations\RegistrationShortcode())->render_form(['id' => $id, 'heading' => $attributes['heading'] ?? __('Anmeldung', 'veranstaltungswart'), 'button' => $attributes['button'] ?? __('Jetzt anmelden', 'veranstaltungswart'), 'show_places' => $attributes['showPlaces'] ?? true]);
                return $html === '' ? '' : '<div ' . get_block_wrapper_attributes(['class' => 'vw-block']) . '>' . $html . '</div>';
            }
        ]);
    }
    public function routes() {
        register_rest_route('veranstaltungswart/v1', '/events', ['methods' => 'GET', 'permission_callback' => function() { return current_user_can('edit_posts') || current_user_can('edit_vw_events'); }, 'callback' => function($request) {
            $query = ['post_type' => 'vw_event', 'post_status' => ['publish', 'draft', 'future', 'private'], 'posts_per_page' => 20, 's' => sanitize_text_field($request->get_param('search') ?: ''), 'orderby' => 'date', 'order' => 'DESC'];
            $events = [];
            foreach (get_posts($query) as $post) {
                if (\VW\Events\EventVisibility::hidden($post->ID) && !Capabilities::can_manage_event($post->ID)) continue;
                if (!EventSettings::is_public($post->ID) && !Capabilities::can_manage_event($post->ID)) continue;
                $events[] = ['id' => $post->ID, 'title' => $post->post_title ?: __('(Ohne Titel)', 'veranstaltungswart'), 'editable' => Capabilities::can_manage_event($post->ID)];
            }
            return rest_ensure_response($events);
        }]);
        register_rest_route('veranstaltungswart/v1', '/settings/(?P<id>\d+)', [
            'methods' => ['GET', 'POST'],
            'permission_callback' => function($request) { return Capabilities::can_manage_event((int) $request['id']); },
            'callback' => function($request) {
                $id = (int) $request['id'];
                if ($request->get_method() === 'POST') {
                    $input = $request->get_json_params();
                    if (!is_array($input)) return new \WP_Error('invalid_body', __('Ungültige Einstellungen.', 'veranstaltungswart'), ['status' => 400]);
                    $result = EventSettings::save($id, $input);
                    if (is_wp_error($result)) return $result;
                }
                return rest_ensure_response(EventSettings::all($id));
            }
        ]);
        register_rest_route('veranstaltungswart/v1', '/locations', ['methods' => 'GET', 'permission_callback' => function() { return current_user_can('manage_vw_events'); }, 'callback' => function() {
            return rest_ensure_response(array_map(function($row) { return ['id' => (int) $row->id, 'name' => $row->name, 'default_capacity' => max(0, (int) $row->default_capacity)]; }, (new EventRepository())->get_all_locations()));
        }]);
        register_rest_route('veranstaltungswart/v1', '/preview/(?P<id>\d+)', ['methods' => 'GET', 'permission_callback' => function($request) { return Capabilities::can_manage_event((int) $request['id']) || (EventSettings::is_public((int) $request['id']) && current_user_can('edit_posts')); }, 'callback' => function($request) {
            return rest_ensure_response(EventPresentation::values((int) $request['id']));
        }]);
    }
    public function card($id, $atts = []) {
        if (!EventSettings::is_public($id)) return '';
        $v = EventPresentation::values($id);
        $stats = (new EventRepository())->get_registration_stats($id);
        $cancelled = get_post_meta($id, 'vw_event_canceled', true) === '1';
        $status = $cancelled ? __('Abgesagt', 'veranstaltungswart') : (EventSettings::closed_reason($id) ? __('Anmeldung geschlossen', 'veranstaltungswart') : ($stats['is_full'] ? __('Ausgebucht · Warteliste', 'veranstaltungswart') : __('Anmeldung geöffnet', 'veranstaltungswart')));
        $has_image = ($atts['showImage'] ?? true) && has_post_thumbnail($id);
        $html = '<article class="vw-event-card' . ($has_image ? ' vw-event-card--has-image' : '') . '">';
        if ($has_image) $html .= '<div class="vw-card-image">' . get_the_post_thumbnail($id, 'medium_large') . '</div>';
        $html .= '<div class="vw-card-body"><p class="vw-event-status">' . esc_html($status) . '</p><h3><a href="' . esc_url(get_permalink($id)) . '">' . esc_html($v['event_name']) . '</a></h3>';
        $html .= '<p>' . esc_html($v['event_datum'] . ' · ' . $v['event_zeit']) . '</p>';
        if ($atts['showLocation'] ?? true) $html .= '<p>' . esc_html($v['event_ort']) . '</p>';
        $html .= '<a class="wp-element-button" href="' . esc_url(get_permalink($id)) . '">' . esc_html($atts['button'] ?? __('Zur Veranstaltung', 'veranstaltungswart')) . '</a></div></article>';
        return $html;
    }
    public function listing($atts) {
        $meta = [];
        if (empty($atts['showPast'])) $meta[] = ['key' => 'vw_event_date', 'value' => current_time('mysql'), 'compare' => '>=', 'type' => 'DATETIME'];
        if (empty($atts['showCanceled'])) $meta[] = ['relation' => 'OR', ['key' => 'vw_event_canceled', 'compare' => 'NOT EXISTS'], ['key' => 'vw_event_canceled', 'value' => '1', 'compare' => '!=']];
        $args = ['post_type' => 'vw_event', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => max(1, min(50, (int) ($atts['count'] ?? 6))), 'meta_key' => 'vw_event_date', 'orderby' => 'meta_value', 'order' => 'ASC', 'meta_query' => $meta, 'no_found_rows' => true];
        if (!empty($atts['category'])) $args['tax_query'] = [['taxonomy' => 'vw_event_category', 'field' => 'term_id', 'terms' => absint($atts['category'])]];
        $posts = get_posts(\VW\Events\EventVisibility::public_args($args));
        if (!$posts) return empty($atts['emptyText']) ? '' : '<p>' . esc_html($atts['emptyText']) . '</p>';
        $html = '<div class="vw-event-list vw-columns-' . max(1, min(3, (int) ($atts['columns'] ?? 3))) . '">';
        foreach ($posts as $post) $html .= $this->card($post->ID, $atts);
        return $html . '</div>';
    }
}
