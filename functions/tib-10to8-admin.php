<?php
// Set these once (wp-config.php is ideal)
if (!defined('TIB_10TO8_BOOK_SLUG')) define('TIB_10TO8_BOOK_SLUG', 'kyvakywdvslaodmqbc'); // your org slug

/**
 * Fetch staff list from 10to8 and return array: [ id => "Name" ]
 */
function tib_10to8_get_staff_choices(): array {
    $api_key = defined('TIB_10TO8_API_KEY') ? TIB_10TO8_API_KEY : '';
    if (!$api_key) return [];

    // bump cache key to invalidate the old single-page result
    $cache_key = 'tib_10to8_staff_choices_v2';
    $ttl = defined('TIB_10TO8_LOOKUP_TTL')
        ? (int) TIB_10TO8_LOOKUP_TTL
        : (defined('MINUTE_IN_SECONDS') ? 10 * MINUTE_IN_SECONDS : 600);

    $cached = get_transient($cache_key);
    if ($cached !== false && is_array($cached)) return $cached;

    $headers = [
        'Authorization' => 'Token ' . $api_key, // switch to 'Bearer ' . $api_key if needed
        'Accept'        => 'application/json',
    ];

    $base = 'https://app.10to8.com/api/booking/v2/staff/';
    // ask for a big page, but we’ll still follow pagination
    $url  = add_query_arg(['page_size' => 100, 'page' => 1], $base);

    $choices = [];
    $guard   = 0; // safety to avoid infinite loops

    while ($url && $guard++ < 20) {
        $resp = wp_remote_get($url, ['headers' => $headers, 'timeout' => 15, 'decompress' => false]);
        if (is_wp_error($resp)) break;

        $code = wp_remote_retrieve_response_code($resp);
        $raw  = wp_remote_retrieve_body($resp);

        if ($code === 429) {
            // optional: integrate with your global throttle
            if (function_exists('tib_10to8_parse_retry_seconds')) {
                $sec = tib_10to8_parse_retry_seconds($raw);
                if (function_exists('tib_10to8_throttle_mark')) tib_10to8_throttle_mark($sec);
            }
            break;
        }
        if ($code !== 200) break;

        $data = json_decode($raw, true);
        $rows = [];
        $next = null;

        if (is_array($data)) {
            if (isset($data['results']) && is_array($data['results'])) {
                $rows = $data['results'];
                $next = $data['next'] ?? null;
            } elseif (array_values($data) === $data) {
                // bare list; no pagination metadata
                $rows = $data;
                $next = null;
            }
        }

        foreach ($rows as $row) {
            $name = isset($row['name']) ? trim($row['name']) : '';
            $uri  = $row['resource_uri'] ?? '';
            if (!$name || !$uri) continue;
            if (preg_match('~/staff/(\d+)/?~', $uri, $m)) {
                $choices[$m[1]] = $name;
            }
        }

        $url = $next ?: null;
    }

    if ($choices) {
        asort($choices, SORT_NATURAL | SORT_FLAG_CASE);
        set_transient($cache_key, $choices, $ttl);
        return $choices;
    }

    // brief negative cache to avoid hammering if something’s off
    set_transient($cache_key, [], min($ttl, defined('MINUTE_IN_SECONDS') ? 5 * MINUTE_IN_SECONDS : 300));
    return [];
}


/**
 * ACF: populate choices for the select field "staff_link"
 */
add_filter('acf/load_field/name=staff_link', function($field) {
    $choices = tib_10to8_get_staff_choices();
    // Show "(ID)" next to name to help avoid mistakes
    $field['choices'] = array_map(
        function($name, $id){ return $name . ' (' . $id . ')'; },
        $choices,
        array_keys($choices)
    );
    return $field;
});

/**
 * Optional: button to force refresh (bypass cache) via URL param on the edit screen:
 * add ?tib10to8_staff_refresh=1 to the admin edit URL to refresh choices.
 */
add_action('load-post.php', function () {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['tib10to8_staff_refresh'])) return;
    delete_transient('tib_10to8_staff_choices_v1');
});
