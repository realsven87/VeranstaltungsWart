<?php
namespace VW\Events;
if (!defined('ABSPATH')) exit;

/** Listing visibility only: published single events remain publicly accessible. */
class EventVisibility {
    const META = 'vw_direct_link_only';
    public static function hidden($id) { return get_post_type($id) === 'vw_event' && get_post_meta($id, self::META, true) === '1'; }
    public function register() {
        add_action('pre_get_posts', [$this, 'prepare_query'], 90);
        add_filter('posts_where', [$this, 'where'], 90, 2);
        add_filter('rest_vw_event_query', [$this, 'rest_query'], 90, 2);
        add_filter('rest_post_search_query', function($args) { return self::public_args($args); }, 90);
        add_filter('wp_sitemaps_posts_query_args', function($args, $type) { return $type === 'vw_event' ? self::public_args($args) : $args; }, 90, 2);
        foreach (['get_previous_post_where', 'get_next_post_where'] as $hook) add_filter($hook, function($where) { return is_admin() ? $where : $where . self::exclusion_sql('p'); }, 90);
        add_filter('wp_robots', [$this, 'robots'], 90);
        add_filter('wpseo_robots_array', [$this, 'seo_robots'], 90);
        add_filter('rank_math/frontend/robots', [$this, 'seo_robots'], 90);
        add_filter('wpseo_exclude_from_sitemap_by_post_ids', [$this, 'yoast_excluded'], 90);
        foreach (['rank_math/sitemap/get_posts/where', 'rank_math/sitemap/post_count/where'] as $hook) add_filter($hook, function($where) { return $where . self::exclusion_sql('p'); }, 90);
        add_filter('display_post_states', function($states, $post) { if (self::hidden($post->ID)) $states['vw_direct_link'] = __('Nur Direktlink', 'veranstaltungswart'); return $states; }, 10, 2);
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) add_action($hook, [$this, 'changed'], 10, 4);
    }
    public static function public_args($args) { $args['vw_public_listing'] = true; $args['suppress_filters'] = false; return $args; }
    public function rest_query($args, $request) {
        // Core verifies edit-context capability; authors retain their existing backend access.
        if ($request->get_param('context') === 'edit' && current_user_can('edit_vw_events')) return $args;
        return self::public_args($args);
    }
    public function prepare_query($query) {
        if ($query->get('vw_public_listing')) { $query->set('suppress_filters', false); return; }
        if (is_admin() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI) || (defined('REST_REQUEST') && REST_REQUEST)) return;
        if ($query->is_singular()) return;
        $types = $query->get('post_type');
        if ($types && $types !== 'any' && !in_array('vw_event', (array) $types, true)) return;
        // Applies to main and secondary queries, including get_posts with suppressed filters.
        $query->set('vw_public_listing', true);
        $query->set('suppress_filters', false);
    }
    private static function exclusion_sql($alias) {
        global $wpdb;
        // Alias is supplied internally only: the core posts table or Rank Math's literal p.
        return $wpdb->prepare(" AND ($alias.post_type <> %s OR NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} vw_visibility WHERE vw_visibility.post_id = $alias.ID AND vw_visibility.meta_key = %s AND vw_visibility.meta_value = %s))", 'vw_event', self::META, '1');
    }
    public function where($where, $query) { global $wpdb; return $query->get('vw_public_listing') ? $where . self::exclusion_sql($wpdb->posts) : $where; }
    private function hidden_single() { return is_singular('vw_event') && self::hidden(get_queried_object_id()); }
    public function robots($robots) {
        if ($this->hidden_single()) { unset($robots['index']); $robots['noindex'] = true; }
        return $robots;
    }
    public function seo_robots($robots) {
        if ($this->hidden_single()) { unset($robots['noindex']); $robots['index'] = 'noindex'; }
        return $robots;
    }
    public function yoast_excluded($ids) {
        global $wpdb;
        // Direct lookup also works during sitemap generation in CLI/admin contexts.
        $hidden = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id WHERE p.post_type = %s AND pm.meta_key = %s AND pm.meta_value = %s", 'vw_event', self::META, '1'));
        return array_values(array_unique(array_merge($ids, array_map('intval', $hidden))));
    }
    public function changed($meta_id, $id, $key, $value) {
        if ($key !== self::META || get_post_type($id) !== 'vw_event') return;
        // Invalidate cached WP_Query results as well as the individual post/meta cache.
        clean_post_cache($id);
        if (is_callable(['WPSEO_Sitemaps_Cache', 'clear'])) \WPSEO_Sitemaps_Cache::clear();
        if (is_callable(['RankMath\\Sitemap\\Cache', 'invalidate_storage'])) \RankMath\Sitemap\Cache::invalidate_storage();
        /** Hosts and page-cache integrations can purge archives, searches and detail pages here. */
        do_action('vw_event_visibility_changed', $id, self::hidden($id));
    }
}
