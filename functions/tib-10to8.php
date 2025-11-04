<?php

/**
 * Talk in the Bay — 10to8 (Sign In Scheduling) helpers
 * - Normalises IDs/URLs for staff & services
 * - Finds the earliest slot across many services & all their locations
 * - ACF select for staff (stores numeric staff ID)
 * - Simple render/echo helpers for templates
 *
 * Assumptions:
 * - Auth uses "Token <API_KEY>" (swap to Bearer if your tenant requires)
 * - Slot endpoint accepts start_date / end_date
 * - Responses may be an array or {results:[...]}
 *
 * Optional constants (set in wp-config.php):
 *   define('TIB_10TO8_API_KEY', 'xxx');
 *   define('TIB_10TO8_BOOK_SLUG', 'kyvakywdvslaodmqbc'); // your org slug
 *   define('TIB_10TO8_DEBUG', true); // emit HTML comments in page source while testing
 */

/* ---------------------------
 * Normalisers & small helpers
 * --------------------------- */

/**
 * Extract a numeric ID from:
 *  - "251651"
 *  - "https://app.10to8.com/api/booking/v2/staff/251651/"
 *  - "https://app.10to8.com/book/SLUG/staff/251651/"
 *  - or any .../<digits>/ tail.
 */
function tib_10to8_extract_id($value): ?string
{
    if (!$value) return null;
    $s = trim((string)$value);
    if ($s === '') return null;
    if (ctype_digit($s)) return $s;
    if (preg_match('~/(\d+)/?$~', $s, $m)) return $m[1];
    return null;
}

/** Staff: ID/URL -> full API URI */
function tib_10to8_staff_to_uri($value): ?string
{
    $id = tib_10to8_extract_id($value);
    return $id ? "https://app.10to8.com/api/booking/v2/staff/{$id}/" : null;
}

/** Service: ID/URL -> full API URI */
function tib_10to8_service_to_uri($value): ?string
{
    $id = tib_10to8_extract_id($value);
    return $id ? "https://app.10to8.com/api/booking/v2/service/{$id}/" : null;
}

/** Build booking URL for a staff ID (needs TIB_10TO8_BOOK_SLUG) */
function tib_10to8_staff_booking_url(string $staff_id): ?string
{
    if (!defined('TIB_10TO8_BOOK_SLUG') || !TIB_10TO8_BOOK_SLUG) return null;
    return sprintf('https://app.10to8.com/book/%s/staff/%s/', TIB_10TO8_BOOK_SLUG, $staff_id);
}

/** Build full API URI for a staff ID */
function tib_10to8_staff_api_uri(string $staff_id): string
{
    return sprintf('https://app.10to8.com/api/booking/v2/staff/%s/', $staff_id);
}



/**
 * Normalise a list of services (IDs or URLs) to sorted unique API URIs.
 */
function tib_10to8_normalize_service_uris($service_ids): array {
    $raw = is_array($service_ids)
        ? $service_ids
        : array_map('trim', explode(',', (string)$service_ids));

    $uris = [];
    foreach ($raw as $sv) {
        if (!$sv) continue;
        $sv = trim($sv);
        if (stripos($sv, '/api/booking/v2/service/') !== false) {
            $uris[] = rtrim($sv, '/') . '/';
        } elseif (preg_match('~/service/(\d+)/?~', $sv, $m) || preg_match('~^\d+$~', $sv)) {
            $id = isset($m[1]) ? $m[1] : $sv;
            $uris[] = "https://app.10to8.com/api/booking/v2/service/{$id}/";
        }
    }
    $uris = array_values(array_unique($uris));
    sort($uris, SORT_STRING); // IMPORTANT: stable order for cache key
    return $uris;
}

/**
 * Build a consistent cache key and return the normalised parts too.
 */
function tib_10to8_build_cache_key($service_ids, $staff_id, int $days): array {
    $service_uris = tib_10to8_normalize_service_uris($service_ids);
    $staff_uri    = tib_10to8_staff_to_uri($staff_id);
    $days         = max(1, (int)$days);
    $key          = 'tib_10to8_nextslot_multi_' . md5(implode('|', $service_uris) . '|' . $staff_uri . '|' . $days);
    return [$key, $service_uris, $staff_uri, $days];
}

/* ------------------------------------
 * Services list (override friendly)
 * ------------------------------------ */

/**
 * Default service URIs used for next-slot lookups.
 * You can override via the 'tib_10to8_service_uris' filter, or later move to wp_options.
 */
function tib_10to8_get_service_uris(): array
{
    $services = [
        'https://app.10to8.com/api/booking/v2/service/1886311/',
        // Individual Session (Cardiff)
        'https://app.10to8.com/api/booking/v2/service/1767089/',
        // Individual Session (Swansea)
        'https://app.10to8.com/api/booking/v2/service/1767110/',
        // Individual Session (Online)
        'https://app.10to8.com/api/booking/v2/service/1956384/',
        // Young Person Session (Swansea)
        'https://app.10to8.com/api/booking/v2/service/1050705/',
        // Young Person Session (Cardiff)
        'https://app.10to8.com/api/booking/v2/service/1889844/',
        // Young Person Session (Online)
        'https://app.10to8.com/api/booking/v2/service/1943583/',
        // Relationships/Couples Session (Swansea)
        'https://app.10to8.com/api/booking/v2/service/1889847/',
        // Relationships/Couples Session (Cardiff)
        'https://app.10to8.com/api/booking/v2/service/1889848/',
        // Relationships/Couples Session (Online)
    ];
    return apply_filters('tib_10to8_service_uris', $services);
}

/* ---------------------------------------------------------
 * Core: earliest slot across services & all their locations
 * --------------------------------------------------------- */

/**
 * Get the earliest slot for (one or many services, one staff) across all service locations.
 * @param string|array $service_ids IDs, API URLs, booking URLs, or mix
 * @param string       $staff_id    ID, API URL, or booking URL
 * @param int          $days_ahead  Window in days (default 60)
 * @return array|null|WP_Error      ['slot_id','start_iso','end_iso','start_local','date','time','raw'] or null if none
 */
if (!function_exists('tib_get_next_10to8_slot_multi')) {
    function tib_get_next_10to8_slot_multi($service_ids, $staff_id, $days_ahead = 60) {
        $api_key = defined('TIB_10TO8_API_KEY') ? TIB_10TO8_API_KEY : '';
        if (!$api_key) return new WP_Error('tib_10to8_config', 'Missing API key');

        $debug = defined('TIB_10TO8_DEBUG') && TIB_10TO8_DEBUG;
        $CALL_CAP = defined('TIB_10TO8_CALL_CAP') ? (int)TIB_10TO8_CALL_CAP : 64;
        static $tib10to8_api_calls = 0;
        $made_api_calls = 0;

        // Normalise + build shared cache key (identical everywhere)
        [$cache_key, $service_uris, $staff_uri, $days_ahead] = tib_10to8_build_cache_key($service_ids, $staff_id, (int)$days_ahead);
        if (!$staff_uri || empty($service_uris)) return new WP_Error('tib_10to8_config', 'Bad staff or service list');

        $disable_cache = isset($_GET['tib10to8_flush']); // bypass READ only
        if (!$disable_cache) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                if ($debug) echo "\n<!-- 10to8[MULTI] cache HIT key=$cache_key -->\n";
                return $cached;
            } elseif ($debug) {
                echo "\n<!-- 10to8[MULTI] cache MISS key=$cache_key -->\n";
            }
        } else {
            if ($debug) echo "\n<!-- 10to8[MULTI] cache BYPASSED -->\n";
        }
        // --- NORMALISE STAFF to API URI ---
        $staff_uri = function_exists('tib_10to8_staff_to_uri') ? tib_10to8_staff_to_uri($staff_id) : null;
        if (!$staff_uri) {
            // minimal inline fallback
            $sid = preg_match('~/(\d+)/?$~', (string)$staff_id, $m) ? $m[1] : (ctype_digit((string)$staff_id) ? (string)$staff_id : null);
            if ($sid) $staff_uri = "https://app.10to8.com/api/booking/v2/staff/{$sid}/";
        }
        if (!$staff_uri) return new WP_Error('tib_10to8_bad_staff', 'Invalid staff value (expected ID or staff URL)');

        // --- NORMALISE SERVICES to API URIs (accept array / CSV / IDs / URLs) ---
        $services_in = is_array($service_ids)
            ? array_values(array_filter(array_map('trim', $service_ids)))
            : array_values(array_filter(array_map('trim', explode(',', (string)$service_ids))));

        $service_uris = [];
        foreach ($services_in as $sv) {
            if (stripos($sv, '/api/booking/v2/service/') !== false) {
                $service_uris[] = rtrim($sv, '/') . '/';
            } else {
                if (function_exists('tib_10to8_service_to_uri')) {
                    $uri = tib_10to8_service_to_uri($sv);
                } else {
                    $sid = preg_match('~/(\d+)/?$~', (string)$sv, $m) ? $m[1] : (ctype_digit((string)$sv) ? (string)$sv : null);
                    $uri = $sid ? "https://app.10to8.com/api/booking/v2/service/{$sid}/" : null;
                }
                if ($uri) $service_uris[] = $uri;
            }
        }
        $service_uris = array_values(array_unique($service_uris));
        sort($service_uris, SORT_STRING); // stable order for cache keys
        if (empty($service_uris)) return new WP_Error('tib_10to8_bad_service', 'No valid services');

        // --- CACHE KEY (built from normalised values) ---
        $cache_key = 'tib_10to8_nextslot_multi_' . md5(implode('|', $service_uris) . '|' . $staff_uri . '|' . (int)$days_ahead);
        $disable_cache = isset($_GET['tib10to8_flush']);

        if (!$disable_cache) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                if ($debug) echo "\n<!-- 10to8[MULTI] cache HIT key=$cache_key -->\n";
                return $cached;
            } elseif ($debug) {
                echo "\n<!-- 10to8[MULTI] cache MISS key=$cache_key -->\n";
            }
        } elseif ($debug) {
            echo "\n<!-- 10to8[MULTI] cache BYPASSED -->\n";
        }

        // --- Request bits ---
        $headers = [
            'Authorization' => 'Token ' . $api_key,
            'Accept'        => 'application/json',
        ];
        $from = gmdate('Y-m-d');
        $to   = gmdate('Y-m-d', strtotime('+' . (int)$days_ahead . ' days'));
        $slot_base = 'https://app.10to8.com/api/booking/v2/slot/';

        $parse_rows = function ($raw) {
            $body = json_decode($raw, true);
            if (!is_array($body)) return [];
            if (isset($body['results']) && is_array($body['results'])) return $body['results'];
            if (array_values($body) === $body) return $body; // list
            return [];
        };

        // Fetch service->locations (10 min cache)
        $fetch_service_locations = function (string $service_uri) use ($headers, $debug) {
            $svc_key = 'tib_10to8_service_locations_' . md5($service_uri);
            $locs = get_transient($svc_key);
            if ($locs !== false) {
                if ($debug) echo "\n<!-- 10to8[MULTI] service locations (cached) for $service_uri: " . count((array)$locs) . " -->\n";
                return is_array($locs) ? $locs : [];
            }
            $resp = wp_remote_get($service_uri, ['headers'=>$headers, 'timeout'=>10, 'decompress'=>false]);
            if (is_wp_error($resp)) {
                if ($debug) echo "\n<!-- 10to8[MULTI] service fetch WP_Error: $service_uri | " . esc_html($resp->get_error_message()) . " -->\n";
                return [];
            }
            $code = wp_remote_retrieve_response_code($resp);
            $raw  = wp_remote_retrieve_body($resp);
            if ($code !== 200) {
                if ($debug) echo "\n<!-- 10to8[MULTI] service fetch HTTP $code: $service_uri | body: " . esc_html(substr($raw ?? '',0,200)) . " -->\n";
                return [];
            }
            $svc = json_decode($raw, true);
            $locs = isset($svc['locations']) && is_array($svc['locations']) ? array_values(array_filter($svc['locations'])) : [];
            set_transient($svc_key, $locs, 10 * MINUTE_IN_SECONDS);
            if ($debug) echo "\n<!-- 10to8[MULTI] service locations NEW for $service_uri: " . count($locs) . " -->\n";
            return $locs;
        };

        // --- Collect across (service × location) ---
        $all_slots = [];
        $svc_idx = 0;

        foreach ($service_uris as $service_uri) {
            $svc_idx++;
            $locations = $fetch_service_locations($service_uri);
            if (empty($locations)) {
                if ($debug) echo "\n<!-- 10to8[MULTI] no locations for service#$svc_idx: $service_uri -->\n";
                continue;
            }

            $loc_idx = 0;
            foreach ((array)$locations as $loc_uri) {
                $loc_idx++;

                // cap before calling
                if ($tib10to8_api_calls >= $CALL_CAP) {
                    if ($debug) echo "\n<!-- 10to8[MULTI] call cap reached ({$CALL_CAP}) — stopping -->\n";
                    break 2;
                }

                $url = add_query_arg([
                    'service'    => $service_uri,
                    'staff'      => $staff_uri,
                    'location'   => $loc_uri,
                    'start_date' => $from,
                    'end_date'   => $to,
                    'page_size'  => 50,
                ], $slot_base);

                $tib10to8_api_calls++;
                $made_api_calls++;

                $resp = wp_remote_get($url, [
                    'headers'    => $headers,
                    'timeout'    => 5,
                    'decompress' => false,
                ]);
                if (is_wp_error($resp)) {
                    if ($debug) echo "\n<!-- 10to8[MULTI] svc#$svc_idx loc#$loc_idx: WP_Error | URL: $url | msg: " . esc_html($resp->get_error_message()) . " -->\n";
                    continue;
                }
                $code = wp_remote_retrieve_response_code($resp);
                $raw  = wp_remote_retrieve_body($resp);

                if ($code !== 200) {
                    if ($debug) echo "\n<!-- 10to8[MULTI] svc#$svc_idx loc#$loc_idx: HTTP $code | URL: $url | body: " . esc_html(substr($raw ?? '',0,200)) . " -->\n";
                    continue;
                }

                $rows = $parse_rows($raw);
                $count = is_array($rows) ? count($rows) : 0;
                if ($debug) echo "\n<!-- 10to8[MULTI] svc#$svc_idx loc#$loc_idx: 200 OK | results: $count | URL: $url -->\n";

                if ($count > 0) {
                    foreach ($rows as &$r) {
                        if (!isset($r['_tib_location'])) $r['_tib_location'] = $loc_uri;
                        if (!isset($r['_tib_service']))  $r['_tib_service']  = $service_uri;
                    }
                    unset($r);
                    $all_slots = array_merge($all_slots, $rows);
                }
            }
        }

        if (empty($all_slots)) {
            if ($made_api_calls > 0) {
                set_transient($cache_key, null, 10 * MINUTE_IN_SECONDS); // write-through even on flush
                if ($debug) echo "\n<!-- 10to8[MULTI] wrote NULL to cache key=$cache_key (ttl 10m) -->\n";
            }
            return null;
        }

        // --- Detect time keys (tenant uses start_datetime / end_datetime) ---
        $first = $all_slots[0];
        $detect_key = function(array $row, array $cands) {
            $lower = array_change_key_case($row, CASE_LOWER);
            foreach ($cands as $c) {
                $c2 = strtolower($c);
                if (array_key_exists($c2, $lower)) {
                    foreach ($row as $k => $v) if (strtolower($k) === $c2) return $k;
                }
            }
            return null;
        };
        $start_key = $detect_key($first, ['start_datetime','start','start_dt','start_at','datetime','begin']);
        $end_key   = $detect_key($first,   ['end_datetime','end','end_dt','end_at','datetime_end','finish']);
        if (!$start_key) {
            if ($debug) echo "\n<!-- 10to8[MULTI] could not detect start key; sample: " . esc_html(substr(json_encode($first),0,400)) . " -->\n";
            if (!$disable_cache && $made_api_calls > 0) set_transient($cache_key, null, 10 * MINUTE_IN_SECONDS);
            return null;
        }

        // --- Earliest across all services/locations ---
        usort($all_slots, function($a,$b) use($start_key){
            $as = isset($a[$start_key]) ? strtotime($a[$start_key]) : PHP_INT_MAX;
            $bs = isset($b[$start_key]) ? strtotime($b[$start_key]) : PHP_INT_MAX;
            return $as <=> $bs;
        });

        $next = $all_slots[0];
        $start_iso = $next[$start_key];
        $end_iso   = ($end_key && isset($next[$end_key])) ? $next[$end_key] : null;

        // Display timezone (use site tz if set; otherwise London)
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Europe/London');
        try { $when = (new DateTimeImmutable($start_iso))->setTimezone($tz); }
        catch (Exception $e) { $when = (new DateTimeImmutable($start_iso.'Z'))->setTimezone($tz); }

        $out = [
            'slot_id'     => $next['id'] ?? null,
            'start_iso'   => $start_iso,
            'end_iso'     => $end_iso,
            'start_local' => $when->format('Y-m-d H:i'),
            'date'        => wp_date('D j M Y', $when->getTimestamp(), $tz),
            'time'        => wp_date('H:i',        $when->getTimestamp(), $tz),
            'raw'         => $next,
        ];



        if ($debug) {
            $chosen_svc = $next['_tib_service']  ?? 'unknown-service';
            $chosen_loc = $next['_tib_location'] ?? 'unknown-location';
            echo "\n<!-- 10to8[MULTI] chosen: service=$chosen_svc | location=$chosen_loc | start_iso={$out['start_iso']} | local={$out['date']} {$out['time']} -->\n";
        }

        set_transient($cache_key, $out, 10 * MINUTE_IN_SECONDS); // write-through even on flush
        if ($debug) echo "\n<!-- 10to8[MULTI] wrote cache key=$cache_key (ttl 10m) -->\n";
        return $out;
    }
}

/**
 * Get cached next-slot only (no network). Returns array|null.
 * Must compute the SAME cache key as the online version.
 */
function tib_get_next_10to8_slot_multi_cached($service_ids, $staff_id, $days_ahead = 60) {
    // Normalise staff
    $staff_uri = function_exists('tib_10to8_staff_to_uri') ? tib_10to8_staff_to_uri($staff_id) : null;
    if (!$staff_uri) {
        $sid = preg_match('~/(\d+)/?$~', (string)$staff_id, $m) ? $m[1] : (ctype_digit((string)$staff_id) ? (string)$staff_id : null);
        if ($sid) $staff_uri = "https://app.10to8.com/api/booking/v2/staff/{$sid}/";
    }
    if (!$staff_uri) return null;

    // Normalise & sort services
    $services_in = is_array($service_ids)
        ? array_values(array_filter(array_map('trim', $service_ids)))
        : array_values(array_filter(array_map('trim', explode(',', (string)$service_ids))));
    $service_uris = [];
    foreach ($services_in as $sv) {
        if (stripos($sv, '/api/booking/v2/service/') !== false) $service_uris[] = rtrim($sv, '/') . '/';
        else {
            $uri = function_exists('tib_10to8_service_to_uri') ? tib_10to8_service_to_uri($sv) : null;
            if (!$uri) {
                $sid = preg_match('~/(\d+)/?$~', (string)$sv, $m) ? $m[1] : (ctype_digit((string)$sv) ? (string)$sv : null);
                $uri = $sid ? "https://app.10to8.com/api/booking/v2/service/{$sid}/" : null;
            }
            if ($uri) $service_uris[] = $uri;
        }
    }
    $service_uris = array_values(array_unique($service_uris));
    if (empty($service_uris)) return null;
    sort($service_uris, SORT_STRING);

    $cache_key = 'tib_10to8_nextslot_multi_' . md5(implode('|', $service_uris) . '|' . $staff_uri . '|' . (int)$days_ahead);
    $cached = get_transient($cache_key);
    return ($cached !== false) ? $cached : null;
}

/**
 * Render cached; if missing, optionally do a tiny online fetch to fill it.
 */
function tib_render_next_slot_multi_cached($service_ids, $staff_id, $days_ahead = 60, $empty_text = 'Check availability', $soft_fetch = false, $soft_cap = 16) {
    [$cache_key, $service_uris, $staff_uri, $days_ahead] = tib_10to8_build_cache_key($service_ids, $staff_id, (int)$days_ahead);
    if (!$staff_uri || empty($service_uris)) {
        return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
    }

    // Read cache
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        if (is_array($cached)) {
            return sprintf(
                '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
                esc_attr($cached['start_iso']),
                esc_html($cached['date']),
                esc_html($cached['time'])
            );
        }
        // cached null → show empty state
        return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
    }

    // Optional soft fetch (one quick online attempt that WRITES the cache immediately)
    if ($soft_fetch && defined('TIB_10TO8_API_KEY') && TIB_10TO8_API_KEY) {
        // Temporarily increase the per-request cap for this one attempt (use a global, not a constant)
        $GLOBALS['tib10to8_soft_cap'] = max(8, (int)$soft_cap);

        // Call the online getter – it uses the same key and always writes the transient now
        tib_get_next_10to8_slot_multi($service_uris, $staff_uri, $days_ahead);

        // Re-read whatever got written
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return sprintf(
                '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
                esc_attr($cached['start_iso']),
                esc_html($cached['date']),
                esc_html($cached['time'])
            );
        }
    }

    return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
}

/**
 * ONLINE renderer (hits API via tib_get_next_10to8_slot_multi, then caches).
 * Returns an HTML string (safe to echo or assign).
 */
if (!function_exists('tib_render_next_slot_multi')) {
    function tib_render_next_slot_multi($service_ids, $staff_id, $days_ahead = 60, $empty_text = 'No availability') {
        $slot = tib_get_next_10to8_slot_multi($service_ids, $staff_id, $days_ahead);

        // Optional lightweight debug (you'll see API debug from the getter if TIB_10TO8_DEBUG is true)
        if (defined('TIB_10TO8_DEBUG') && TIB_10TO8_DEBUG && is_wp_error($slot)) {
            echo "\n<!-- tib_render_next_slot_multi error: " . esc_html($slot->get_error_message()) . " -->\n";
        }

        if (is_wp_error($slot) || !$slot) {
            return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
        }

        return sprintf(
            '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
            esc_attr($slot['start_iso']),
            esc_html($slot['date']),
            esc_html($slot['time'])
        );
    }
}

/** Convenience echo wrapper (optional). */
if (!function_exists('tib_echo_next_slot_multi')) {
    function tib_echo_next_slot_multi($service_ids, $staff_id, $days_ahead = 60, $empty_text = 'No availability') {
        echo tib_render_next_slot_multi($service_ids, $staff_id, $days_ahead, $empty_text);
    }
}
