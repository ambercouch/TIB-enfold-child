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

// --- Per-request call budget + memo ---

function tib_10to8_call_count(): int {
    if (!isset($GLOBALS['tib10to8_call_count'])) $GLOBALS['tib10to8_call_count'] = 0;
    return (int)$GLOBALS['tib10to8_call_count'];
}

function tib_10to8_inc_call_count(int $n = 1): void {
    if (!isset($GLOBALS['tib10to8_call_count'])) $GLOBALS['tib10to8_call_count'] = 0;
    $GLOBALS['tib10to8_call_count'] += $n;
}

function tib_10to8_call_cap(): int {
    // Global cap from wp-config, default 64
    $cap = defined('TIB_10TO8_CALL_CAP') ? (int)TIB_10TO8_CALL_CAP : 64;
    // Optional request-time override
    if (isset($GLOBALS['tib10to8_call_cap_override'])) {
        $cap = max(1, (int)$GLOBALS['tib10to8_call_cap_override']);
    }
    return $cap;
}

/**
 * Allow templates or hooks to raise the cap for the current request (e.g., archive pages).
 * Example in functions.php (archive template): tib_10to8_set_call_cap(256);
 */
function tib_10to8_set_call_cap(int $cap): void {
    $GLOBALS['tib10to8_call_cap_override'] = max(1, $cap);
}

/** Request-local memo (avoids re-calling in the same PHP request) */
function tib_10to8_request_memo_get(string $key) {
    return $GLOBALS['tib10to8_request_memo'][$key] ?? null;
}
function tib_10to8_request_memo_set(string $key, $val): void {
    $GLOBALS['tib10to8_request_memo'][$key] = $val;
}


function tib_10to8_dbg(string $msg): void {
    if (defined('TIB_10TO8_DEBUG') && TIB_10TO8_DEBUG) {
        error_log('[tib-10to8] ' . $msg);
    }
}

/**
 * Pre-warm next-slot cache for many therapists in one go.
 * $staff_list: array of raw staff values (IDs or URLs).
 * $services:   array of services (IDs or URLs).
 * Returns number of warmed entries.
 */
function tib_10to8_prime_next_slots(array $staff_list, array $services, int $days = 28, int $soft_cap = 100): int {
    if (tib_10to8_cache_disabled()) return 0; // respect global off

    $warmed = 0;
    $GLOBALS['tib10to8_soft_cap'] = max(8, (int)$soft_cap); // temporary higher budget for this run

    foreach ($staff_list as $raw_staff) {
        // Run the online getter; it will write the transient (or null) using our unified key.
        tib_get_next_10to8_slot_multi($services, $raw_staff, $days);
        $warmed++;
        // tiny throttle to be nice to 10to8
        usleep(120000); // 0.12s
    }
    return $warmed;
}


/* ---------------------------
 * Normalisers & small helpers
 * --------------------------- */

function tib_10to8_cache_disabled(): bool {
    return (defined('TIB_10TO8_DISABLE_CACHE') && TIB_10TO8_DISABLE_CACHE);
}

function tib_10to8_request_flush(): bool {
    return isset($_GET['tib10to8_flush']);
}

function tib_10to8_get_transient(string $key) {
    if (tib_10to8_cache_disabled() || tib_10to8_request_flush()) return false;
    return get_transient($key);
}

function tib_10to8_set_transient(string $key, $value, int $ttl): bool {
    if (tib_10to8_cache_disabled()) return false; // global OFF = no writes
    return set_transient($key, $value, $ttl);      // flush still writes
}

function tib_10to8_delete_transient(string $key): bool {
    return delete_transient($key);
}

function tib_10to8_effective_call_cap(): int {
    if (isset($GLOBALS['tib10to8_soft_cap']) && (int)$GLOBALS['tib10to8_soft_cap'] > 0) {
        return (int)$GLOBALS['tib10to8_soft_cap'];
    }
    if (defined('TIB_10TO8_CALL_CAP')) return (int)TIB_10TO8_CALL_CAP;
    return 64;
}


/**
 * Fetch minimal service metadata (locations, staff) for a list of services.
 * Returns: [ service_uri => ['locations' => [...], 'staff' => [...]] ]
 */
function tib_10to8_fetch_service_meta(array $service_uris): array {
    $api_key = defined('TIB_10TO8_API_KEY') ? TIB_10TO8_API_KEY : '';
    if (!$api_key) return [];

    $headers = ['Authorization' => 'Token ' . $api_key, 'Accept' => 'application/json'];
    $out = [];

    foreach ($service_uris as $svc) {
        $ckey = 'tib_10to8_service_meta_' . md5($svc);
        $cached = tib_10to8_get_transient($ckey);
        if ($cached !== false) { $out[$svc] = $cached; continue; }

        $resp = wp_remote_get($svc, ['headers' => $headers, 'timeout' => 10, 'decompress' => false]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            tib_10to8_dbg('service meta fetch failed code='.(is_wp_error($resp)?'WP_Error':wp_remote_retrieve_response_code($resp)).' url='.$svc);
            continue;
        }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) continue;

        // Normalise arrays
        $locations = isset($body['locations']) && is_array($body['locations']) ? array_values(array_filter($body['locations'])) : [];
        // Ensure trailing slash for location URIs to match how we build slot queries
        foreach ($locations as &$L) { $L = rtrim($L, '/') . '/'; } unset($L);

        $raw_staff = isset($body['staff']) && is_array($body['staff']) ? $body['staff'] : [];
        // Keep raw staff list (mixed forms), we’ll match by ID later
        $val = ['locations' => $locations, 'staff' => $raw_staff];

        tib_10to8_set_transient($ckey, $val, 10 * MINUTE_IN_SECONDS);
        $out[$svc] = $val;
    }
    return $out;
}

/**
 * Given a staff URI and candidate services, return only services that staff can deliver,
 * plus each service's valid location list (in your tenant, usually exactly one).
 * Returns: [ service_uri => ['locations' => [...]] ]
 */
function tib_10to8_services_offered_by_staff(string $staff_uri, array $service_uris): array {
    $meta = tib_10to8_fetch_service_meta($service_uris);

    // Compare using numeric IDs so we don't care whether meta returns IDs or URLs.
    $needle_id = tib_10to8_extract_id($staff_uri);
    if (!$needle_id) return [];

    $out = [];
    foreach ($service_uris as $svc) {
        if (!isset($meta[$svc])) continue;

        $staff_list = $meta[$svc]['staff'] ?? [];
        if (!$staff_list) continue;

        // Normalise the meta staff list to plain IDs
        $staff_ids = [];
        foreach ($staff_list as $s) {
            $id = tib_10to8_extract_id($s);
            if ($id) $staff_ids[$id] = true;
        }

        if (isset($staff_ids[$needle_id])) {
            $locs = $meta[$svc]['locations'] ?? [];
            $out[$svc] = ['locations' => array_values(array_filter($locs))];
        }
    }

    // Debug counts to the error log
    tib_10to8_dbg('prefilter map size='.count($out).' of '.count($service_uris).' services for staff_id='.$needle_id);
    return $out;
}


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

        // Build key + normalised parts first (needed for memo/cache)
        [$cache_key, $service_uris, $staff_uri, $days_ahead] =
            tib_10to8_build_cache_key($service_ids, $staff_id, (int) $days_ahead);
        if (!$staff_uri || empty($service_uris)) {
            return new WP_Error('tib_10to8_config', 'Bad staff or service list');
        }

        // Request flags
        $cache_off = tib_10to8_cache_disabled();
        $flush     = function_exists('tib_10to8_request_flush') ? tib_10to8_request_flush() : isset($_GET['tib10to8_flush']);
        tib_10to8_dbg("key=$cache_key staff=$staff_uri off=".($cache_off?'1':'0')." flush=".($flush?'1':'0'));

        if ($debug) {
            echo "\n<!-- 10to8[MULTI] key=$cache_key staff=$staff_uri cache_off=".($cache_off?'1':'0')." flush=".($flush?'1':'0')." -->\n";
        }

        // Request-local memo (works regardless of cache toggle)
        if (($memo = tib_10to8_request_memo_get($cache_key)) !== null) {
            tib_10to8_dbg("MEMO HIT -> $cache_key");
            return $memo;
        }

        // Cache read (skipped when disabled; flush bypasses read in tib_10to8_get_transient)
        if (!$cache_off) {
            $cached = tib_10to8_get_transient($cache_key);
            if ($cached !== false) {
                if ($debug) echo "\n<!-- 10to8[MULTI] cache HIT key=$cache_key -->\n";
                return $cached;
            }
            if ($debug) {
                echo $flush
                    ? "\n<!-- 10to8[MULTI] cache FLUSH (bypass read) -->\n"
                    : "\n<!-- 10to8[MULTI] cache MISS key=$cache_key -->\n";
            }
        } else {
            if ($debug) echo "\n<!-- 10to8[MULTI] cache: OFF (global) -->\n";
        }

        // Prefilter services by staff where metadata allows; fall back to all services' locations
        $svc_map = tib_10to8_services_offered_by_staff($staff_uri, $service_uris);
        if (empty($svc_map)) {
            tib_10to8_dbg("prefilter empty; falling back to all services");
            $meta_all = tib_10to8_fetch_service_meta($service_uris);
            foreach ($service_uris as $svc_uri) {
                $svc_map[$svc_uri] = ['locations' => ($meta_all[$svc_uri]['locations'] ?? [])];
            }
            if (empty($svc_map)) {
                tib_10to8_dbg("fallback also empty for key=$cache_key");
                tib_10to8_request_memo_set($cache_key, null);
                tib_10to8_set_transient($cache_key, null, 3 * MINUTE_IN_SECONDS);
                return null;
            }
        }

        // Request bits
        $headers = [
            'Authorization' => 'Token ' . $api_key,
            'Accept'        => 'application/json',
        ];
        $from      = gmdate('Y-m-d');
        $to        = gmdate('Y-m-d', strtotime('+' . (int)$days_ahead . ' days'));
        $slot_base = 'https://app.10to8.com/api/booking/v2/slot/';

        $parse_rows = function ($raw) {
            $body = json_decode($raw, true);
            if (!is_array($body)) return [];
            if (isset($body['results']) && is_array($body['results'])) return $body['results'];
            if (array_values($body) === $body) return $body; // pure list
            return [];
        };

        // Iterate across (service × location) with a request-global budget
        $all_slots = [];
        $made_api_calls = 0;

        $svc_idx = 0;
        foreach ($svc_map as $service_uri => $info) {
            $svc_idx++;
            $locations = isset($info['locations']) && is_array($info['locations']) ? $info['locations'] : [];
            if (empty($locations)) {
                if ($debug) echo "\n<!-- 10to8[MULTI] service#$svc_idx has no locations: $service_uri -->\n";
                continue;
            }

            $loc_idx = 0;
            foreach ($locations as $loc_uri) {
                $loc_idx++;

                // Budget check
                $cap  = tib_10to8_call_cap();
                $used = tib_10to8_call_count();
                if ($used >= $cap) {
                    tib_10to8_dbg("CALL CAP REACHED used=$used cap=$cap (key=$cache_key) — stopping");
                    if ($debug) echo "\n<!-- 10to8[MULTI] CALL CAP REACHED used=$used cap=$cap -->\n";
                    break 2;
                }

                // Build URL
                $url = add_query_arg([
                    'service'    => $service_uri,
                    'staff'      => $staff_uri,
                    'location'   => $loc_uri,
                    'start_date' => $from,
                    'end_date'   => $to,
                    'page_size'  => 50,
                ], $slot_base);

                // Call
                tib_10to8_inc_call_count();
                $t0   = microtime(true);
                $resp = wp_remote_get($url, ['headers' => $headers, 'timeout' => 5, 'decompress' => false]);
                $ms   = (int)((microtime(true) - $t0) * 1000);

                if (is_wp_error($resp)) {
                    tib_10to8_dbg("SLOT GET ERR code=WP_Error ms={$ms} url=$url msg=" . $resp->get_error_message());
                    continue;
                }

                $code = wp_remote_retrieve_response_code($resp);
                $raw  = wp_remote_retrieve_body($resp);
                tib_10to8_dbg("SLOT GET code=$code ms={$ms} url=$url");

                if ($code !== 200) {
                    tib_10to8_dbg("SLOT GET NON200 code=$code peek=" . substr(trim((string)$raw), 0, 160));
                    continue;
                }

                $rows  = $parse_rows($raw);
                $count = is_array($rows) ? count($rows) : 0;

                if ($debug) echo "\n<!-- 10to8[MULTI] svc#$svc_idx loc#$loc_idx: 200 OK | results: $count -->\n";

                if ($count > 0) {
                    foreach ($rows as &$r) {
                        if (!isset($r['_tib_location'])) $r['_tib_location'] = $loc_uri;
                        if (!isset($r['_tib_service']))  $r['_tib_service']  = $service_uri;
                    }
                    unset($r);
                    $all_slots = array_merge($all_slots, $rows);
                }

                $made_api_calls++;
            }
        }

        // Nothing found
        if (empty($all_slots)) {
            if ($made_api_calls > 0) {
                tib_10to8_request_memo_set($cache_key, null);
                tib_10to8_set_transient($cache_key, null, 5 * MINUTE_IN_SECONDS);
                tib_10to8_dbg("WRITE null -> $cache_key (ttl 5m)");
                if ($debug) echo "\n<!-- 10to8[MULTI] wrote NULL to cache key=$cache_key (ttl 5m) -->\n";
            }
            return null;
        }

        // Detect time keys (tenant uses start_datetime / end_datetime)
        $first = $all_slots[0];
        $detect_key = function (array $row, array $cands) {
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
            if ($debug) echo "\n<!-- 10to8[MULTI] could not detect start key; sample: " . esc_html(substr(json_encode($first), 0, 400)) . " -->\n";
            tib_10to8_request_memo_set($cache_key, null);
            tib_10to8_set_transient($cache_key, null, 5 * MINUTE_IN_SECONDS);
            return null;
        }

        // Earliest slot across all valid services/locations
        usort($all_slots, function ($a, $b) use ($start_key) {
            $as = isset($a[$start_key]) ? strtotime($a[$start_key]) : PHP_INT_MAX;
            $bs = isset($b[$start_key]) ? strtotime($b[$start_key]) : PHP_INT_MAX;
            return $as <=> $bs;
        });

        $next      = $all_slots[0];
        $start_iso = $next[$start_key];
        $end_iso   = ($end_key && isset($next[$end_key])) ? $next[$end_key] : null;

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

        tib_10to8_request_memo_set($cache_key, $out);
        tib_10to8_dbg("WRITE slot  -> $cache_key {$out['start_iso']} ({$out['date']} {$out['time']})");

        // Writes only if cache enabled (wrapper no-ops when disabled)
        tib_10to8_set_transient($cache_key, $out, 10 * MINUTE_IN_SECONDS);
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
    $cached = tib_10to8_get_transient($cache_key);
    return ($cached !== false) ? $cached : null;
}

/**
 * Render cached; if missing, optionally do a tiny online fetch to fill it.
 */
function tib_render_next_slot_multi_cached($service_ids, $staff_id, $days_ahead = 60, $empty_text = 'Check availability', $soft_fetch = false, $soft_cap   = 16
) {
    $cache_off = tib_10to8_cache_disabled();
    $flush     = tib_10to8_request_flush();

    // When global cache is OFF: always go live, no transients used.
    if ($cache_off) {
        if (defined('TIB_10TO8_DEBUG') && TIB_10TO8_DEBUG) {
            echo "\n<!-- 10to8[RENDER] cache OFF → live fetch -->\n";
        }
        $slot = tib_get_next_10to8_slot_multi($service_ids, $staff_id, $days_ahead);
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

    // Cache ON path (flush only bypasses reads)
    [$cache_key, $service_uris, $staff_uri, $days_ahead] =
        tib_10to8_build_cache_key($service_ids, $staff_id, (int)$days_ahead);

    if (!$staff_uri || empty($service_uris)) {
        return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
    }

    $cached = tib_10to8_get_transient($cache_key); // respects flush (bypass read)
    if ($cached !== false) {
        if (is_array($cached)) {
            return sprintf(
                '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
                esc_attr($cached['start_iso']),
                esc_html($cached['date']),
                esc_html($cached['time'])
            );
        }
        // cached null → explicitly "no availability"
        return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
    }

    // If we get here we either had a miss or a flush (read bypass).
    if ($soft_fetch && defined('TIB_10TO8_API_KEY') && TIB_10TO8_API_KEY) {
        if (!empty($soft_cap)) tib_10to8_set_call_cap((int)$soft_cap);
        // Do a live call; with cache ON it will write; with flush it will still write.
        tib_get_next_10to8_slot_multi($service_ids, $staff_id, $days_ahead);

        // Try to read what was just written
        $cached = get_transient($cache_key); // read raw; we want the fresh write
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

        if (defined('TIB_10TO8_DEBUG') && TIB_10TO8_DEBUG) {
            echo "\n<!-- 10to8[GET] key=$cache_key | cache_off=".(tib_10to8_cache_disabled()?'1':'0')." | flush=".(tib_10to8_request_flush()?'1':'0')." -->\n";
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
