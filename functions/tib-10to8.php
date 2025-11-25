<?php
/**
 * Talk in the Bay — 10to8 helpers (simplified, robust)
 *
 * Optional constants (wp-config.php):
 *   define('TIB_10TO8_API_KEY', 'xxx');              // required for live calls
 *   define('TIB_10TO8_BOOK_SLUG', 'your-book-slug'); // optional, for public booking URLs
 *   define('TIB_10TO8_DEBUG', true);                 // log debug + emit small HTML comments
 *   define('TIB_10TO8_DISABLE_CACHE', false);        // force live (not recommended for lists)
 *   define('TIB_10TO8_SLOT_RATE_PER_MIN', 45);       // global budget per minute for slot calls
 */

/* ========== tiny utils ========== */


function tib_10to8_set_call_cap(int $cap): void {
    $GLOBALS['tib10to8_call_cap_override'] = max(1, $cap);
}


function tib_10to8_dbg(string $msg): void {
    if (defined('TIB_10TO8_DEBUG') && TIB_10TO8_DEBUG) error_log('[tib-10to8] '.$msg);
}
function tib_10to8_now(): int { return time(); }

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
    if (tib_10to8_cache_disabled()) return false;
    return set_transient($key, $value, $ttl);
}

/* ========== global throttle (from 429s) ========== */

function tib_10to8_throttle_key(): string { return 'tib_10to8_throttle_until'; }
function tib_10to8_throttle_active(): bool {
    $until = (int) get_transient(tib_10to8_throttle_key());
    return $until > tib_10to8_now();
}
function tib_10to8_throttle_mark(int $seconds): void {
    $seconds = max(10, min(300, $seconds ?: 60));
    set_transient(tib_10to8_throttle_key(), tib_10to8_now() + $seconds, $seconds);
    tib_10to8_dbg('THROTTLE set for '.$seconds.'s');
}
function tib_10to8_parse_retry_seconds(?string $raw): int {
    if (!$raw) return 60;
    return (preg_match('~(\d+)\s*seconds?~i', $raw, $m)) ? (int)$m[1] : 60;
}

/* ========== per-minute global budget (slot calls only) ========== */

function tib_10to8_budget_key(): string { return 'tib_10to8_slot_budget'; }
function tib_10to8_budget_take(int $n = 1): bool {
    $max = defined('TIB_10TO8_SLOT_RATE_PER_MIN') ? (int) TIB_10TO8_SLOT_RATE_PER_MIN : 45;
    if ($max <= 0) return true;
    $now = tib_10to8_now(); $win=60;
    $entry = get_transient(tib_10to8_budget_key());
    if (!is_array($entry) || ($now - ($entry['t'] ?? 0)) >= $win) $entry = ['t'=>$now,'c'=>0];
    if ($entry['c'] + $n > $max) { tib_10to8_dbg('BUDGET exhausted: c='.$entry['c'].' max='.$max); return false; }
    $entry['c'] += $n;
    $ttl = max(1, $win - ($now - $entry['t']));
    set_transient(tib_10to8_budget_key(), $entry, $ttl);
    return true;
}

/* ========== ID + URL helpers ========== */

function tib_10to8_extract_id($value): ?string {
    if (!$value) return null;
    $s = trim((string) $value);
    if ($s === '') return null;
    if (ctype_digit($s)) return $s;
    return (preg_match('~/(\d+)/?$~', $s, $m)) ? $m[1] : null;
}
function tib_10to8_staff_to_uri($value): ?string {
    $id = tib_10to8_extract_id($value);
    return $id ? "https://app.10to8.com/api/booking/v2/staff/{$id}/" : null;
}
function tib_10to8_service_to_uri($value): ?string {
    $id = tib_10to8_extract_id($value);
    return $id ? "https://app.10to8.com/api/booking/v2/service/{$id}/" : null;
}
function tib_10to8_staff_booking_url(string $staff_id): ?string {
    if (!defined('TIB_10TO8_BOOK_SLUG') || !TIB_10TO8_BOOK_SLUG) return null;
    return sprintf('https://app.10to8.com/book/%s/staff/%s/', TIB_10TO8_BOOK_SLUG, $staff_id);
}

/* ========== service list (override with a filter) ========== */

function tib_10to8_get_service_uris(): array {
    $services = [
        'https://app.10to8.com/api/booking/v2/service/1886311/', // Individual (Cardiff)
        'https://app.10to8.com/api/booking/v2/service/1767089/', // Individual (Swansea)
        'https://app.10to8.com/api/booking/v2/service/1767110/', // Individual (Online)
        'https://app.10to8.com/api/booking/v2/service/1956384/', // Young Person (Swansea)
        'https://app.10to8.com/api/booking/v2/service/1050705/', // Young Person (Cardiff)
        'https://app.10to8.com/api/booking/v2/service/1889844/', // Young Person (Online)
        'https://app.10to8.com/api/booking/v2/service/1943583/', // Couples (Swansea)
        'https://app.10to8.com/api/booking/v2/service/1889847/', // Couples (Cardiff)
        'https://app.10to8.com/api/booking/v2/service/1889848/', // Couples (Online)
    ];
    return apply_filters('tib_10to8_service_uris', $services);
}

/* ========== cache key builder ========== */

function tib_10to8_normalize_service_uris($service_ids): array {
    $raw = is_array($service_ids) ? $service_ids : explode(',', (string) $service_ids);
    $uris = [];
    foreach ($raw as $sv) {
        $sv = trim((string)$sv); if ($sv==='') continue;
        if (stripos($sv, '/api/booking/v2/service/') !== false) $uris[] = rtrim($sv, '/') . '/';
        else {
            $id = tib_10to8_extract_id($sv);
            if ($id) $uris[] = "https://app.10to8.com/api/booking/v2/service/{$id}/";
        }
    }
    $uris = array_values(array_unique($uris));
    sort($uris, SORT_STRING);
    return $uris;
}
function tib_10to8_build_cache_key($service_ids, $staff_id, int $days): array {
    $service_uris = tib_10to8_normalize_service_uris($service_ids);
    $staff_uri    = tib_10to8_staff_to_uri($staff_id);
    $days         = max(1, (int) $days);
    $key          = 'tib_10to8_nextslot_multi_' . md5(implode('|', $service_uris) . '|' . $staff_uri . '|' . $days);
    return [$key, $service_uris, $staff_uri, $days];
}

/* ========== service meta (locations, staff), memo + 10min cache ========== */

function tib_10to8_fetch_service_meta(array $service_uris): array {
    $api_key = defined('TIB_10TO8_API_KEY') ? TIB_10TO8_API_KEY : '';
    if (!$api_key) return [];
    static $req_meta = []; // request-local memo

    $headers = ['Authorization' => 'Token ' . $api_key, 'Accept' => 'application/json'];
    $out = [];

    foreach ($service_uris as $svc) {
        if (array_key_exists($svc, $req_meta)) { $out[$svc] = $req_meta[$svc] ?? []; continue; }

        $ckey = 'tib_10to8_service_meta_' . md5($svc);
        $cached = tib_10to8_get_transient($ckey);
        if ($cached !== false) { $req_meta[$svc] = $cached; $out[$svc] = $cached; continue; }

        $resp = wp_remote_get($svc, ['headers'=>$headers, 'timeout'=>10, 'decompress'=>false]);
        if (is_wp_error($resp)) { tib_10to8_dbg('service meta WP_Error url='.$svc.' msg='.$resp->get_error_message()); $req_meta[$svc]=null; continue; }

        $code = wp_remote_retrieve_response_code($resp);
        $body_raw = wp_remote_retrieve_body($resp);
        $body = json_decode($body_raw, true);

        if ($code === 429) {
            tib_10to8_dbg('service meta 429 url='.$svc);
            tib_10to8_throttle_mark(tib_10to8_parse_retry_seconds($body_raw));
            $req_meta[$svc] = null; // don't hammer this request
            continue;
        }
        if ($code !== 200 || !is_array($body)) { tib_10to8_dbg('service meta fail code='.$code.' url='.$svc); $req_meta[$svc]=null; continue; }

        $locations = isset($body['locations']) && is_array($body['locations']) ? array_values(array_filter($body['locations'])) : [];
        foreach ($locations as &$L) { $L = rtrim($L, '/') . '/'; } unset($L);

        $val = ['locations' => $locations, 'staff' => (isset($body['staff']) && is_array($body['staff'])) ? $body['staff'] : []];
        tib_10to8_set_transient($ckey, $val, 10 * MINUTE_IN_SECONDS);
        $req_meta[$svc] = $val;
        $out[$svc] = $val;
    }
    return $out;
}

function tib_10to8_services_offered_by_staff(string $staff_uri, array $service_uris): array {
    $meta = tib_10to8_fetch_service_meta($service_uris);
    $needle = tib_10to8_extract_id($staff_uri);
    if (!$needle) return [];
    $out = [];
    foreach ($service_uris as $svc) {
        if (empty($meta[$svc]['staff'])) continue;
        foreach ($meta[$svc]['staff'] as $s) {
            if (tib_10to8_extract_id($s) === $needle) {
                $locs = $meta[$svc]['locations'] ?? [];
                if ($locs) $out[$svc] = ['locations' => array_values(array_filter($locs))];
                break;
            }
        }
    }
    tib_10to8_dbg('prefilter map size='.count($out).' of '.count($service_uris).' services for staff_id='.$needle);
    return $out;
}

/* ========== request-local memo for final slot result ========== */
function tib_10to8_request_memo_get(string $key) {
    return $GLOBALS['tib10to8_request_memo'][$key] ?? null;
}
function tib_10to8_request_memo_set(string $key, $val): void {
    $GLOBALS['tib10to8_request_memo'][$key] = $val;
}

/* ========== core: earliest slot (multi-service, across locations) ========== */

if (!function_exists('tib_get_next_10to8_slot_multi')) {
    function tib_get_next_10to8_slot_multi($service_ids, $staff_id, $days_ahead = 60) {
        $api_key = defined('TIB_10TO8_API_KEY') ? TIB_10TO8_API_KEY : '';
        if (!$api_key) return new WP_Error('tib_10to8_config', 'Missing API key');

        $debug = defined('TIB_10TO8_DEBUG') && TIB_10TO8_DEBUG;

        // Normalize and key
        [$cache_key, $service_uris, $staff_uri, $days_ahead] = tib_10to8_build_cache_key($service_ids, $staff_id, (int)$days_ahead);
        if (!$staff_uri || !$service_uris) return new WP_Error('tib_10to8_config', 'Bad staff or service list');

        tib_10to8_dbg('key='.$cache_key.' staff='.$staff_uri.' off='.(tib_10to8_cache_disabled()?'1':'0').' flush='.(tib_10to8_request_flush()?'1':'0'));

        // Request-local memo
        if (($memo = tib_10to8_request_memo_get($cache_key)) !== null) { tib_10to8_dbg('MEMO HIT -> '.$cache_key); return $memo; }

        // Cache read
        if (!$debug && !$GLOBALS['_tib_emit_cache_msg_once'] && $debug) { $GLOBALS['_tib_emit_cache_msg_once']=true; }
        $cached = tib_10to8_get_transient($cache_key);
        if ($cached !== false) {
            if ($debug) echo "\n<!-- 10to8[MULTI] cache HIT key=$cache_key -->\n";
            return $cached;
        } else {
            if ($debug) echo tib_10to8_request_flush()
                ? "\n<!-- 10to8[MULTI] cache FLUSH (bypass read) -->\n"
                : "\n<!-- 10to8[MULTI] cache MISS key=$cache_key -->\n";
        }

        // Global throttle guard
        if (tib_10to8_throttle_active()) {
            tib_10to8_dbg('GLOBAL THROTTLE active — skip API for '.$cache_key);
            return null; // do NOT write null
        }

        // Prefilter services for this staff (reduces 400s)
        $svc_map = tib_10to8_services_offered_by_staff($staff_uri, $service_uris);
        if (empty($svc_map)) {
            tib_10to8_dbg('prefilter empty; falling back to all services');
            $meta_all = tib_10to8_fetch_service_meta($service_uris);
            foreach ($service_uris as $svc_uri) {
                $locs = $meta_all[$svc_uri]['locations'] ?? [];
                if ($locs) $svc_map[$svc_uri] = ['locations' => $locs];
            }
            if (empty($svc_map)) {
                // Nothing we can call; do not poison cache
                return null;
            }
        }

        // Request bits
        $headers = ['Authorization' => 'Token ' . $api_key, 'Accept' => 'application/json'];
        $from = gmdate('Y-m-d');
        $to   = gmdate('Y-m-d', strtotime('+'.(int)$days_ahead.' days'));
        $slot_base = 'https://app.10to8.com/api/booking/v2/slot/';

        $parse_rows = function ($raw) {
            $body = json_decode($raw, true);
            if (!is_array($body)) return [];
            if (isset($body['results']) && is_array($body['results'])) return $body['results'];
            if (array_values($body) === $body) return $body;
            return [];
        };

        $all_slots = [];
        $saw_200   = false;
        $made_calls = 0;
        $last_code = null;
        $last_count = null;

        foreach ($svc_map as $service_uri => $info) {
            $locations = isset($info['locations']) && is_array($info['locations']) ? $info['locations'] : [];
            if (!$locations) continue;

            foreach ($locations as $loc_uri) {
                if (tib_10to8_throttle_active()) { tib_10to8_dbg('GLOBAL THROTTLE mid-loop'); break 2; }
                if (!tib_10to8_budget_take(1))   { tib_10to8_dbg('BUDGET mid-loop stop');   break 2; }

                $url = add_query_arg([
                    'service'    => $service_uri,
                    'staff'      => $staff_uri,
                    'location'   => $loc_uri,
                    'start_date' => $from,
                    'end_date'   => $to,
                    'page_size'  => 50,
                ], $slot_base);

                $t0 = microtime(true);
                $resp = wp_remote_get($url, ['headers'=>$headers, 'timeout'=>5, 'decompress'=>false]);
                $ms  = (int)((microtime(true) - $t0) * 1000);

                if (is_wp_error($resp)) { tib_10to8_dbg('SLOT GET WP_Error ms='.$ms.' url='.$url.' msg='.$resp->get_error_message()); continue; }

                $code = wp_remote_retrieve_response_code($resp);
                $raw  = wp_remote_retrieve_body($resp);
                tib_10to8_dbg('SLOT GET code='.$code.' ms='.$ms.' url='.$url);

                if ($code === 429) {
                    $sec = tib_10to8_parse_retry_seconds($raw);
                    tib_10to8_dbg('SLOT 429 — backoff '.$sec.'s');
                    tib_10to8_throttle_mark($sec);
                    return null; // no cache write on throttle
                }

                if ($code !== 200) {
                    tib_10to8_dbg('SLOT GET NON200 code='.$code.' peek='.substr(trim((string)$raw), 0, 160));
                    continue;
                }

                $saw_200 = true; // <-- mark

                $rows = $parse_rows($raw);
                $cnt  = is_array($rows) ? count($rows) : 0;
                if ($debug) echo "\n<!-- 10to8[MULTI] 200 OK | results: $cnt | URL: $url -->\n";
                $last_code  = 200; $last_count = $cnt; $made_calls++;

                if ($cnt > 0) {
                    foreach ($rows as &$r) {
                        $r['_tib_location'] = $r['_tib_location'] ?? $loc_uri;
                        $r['_tib_service']  = $r['_tib_service']  ?? $service_uri;
                    }
                    unset($r);
                    $all_slots = array_merge($all_slots, $rows);
                }
            }
        }

        // No slots found
        if (empty($all_slots)) {
            // Only negative-cache when we truly observed 200 OK responses with zero results.
            if ($saw_200) {
                tib_10to8_set_transient($cache_key, null, 5 * MINUTE_IN_SECONDS);
                tib_10to8_dbg('WRITE null -> '.$cache_key.' (ttl 5m)');
            }
            return null;
        }

        // Detect time fields (tenant may use start_datetime)
        $first = $all_slots[0];
        $detect_key = function(array $row, array $cands){
            $lower = array_change_key_case($row, CASE_LOWER);
            foreach ($cands as $c) {
                $c2 = strtolower($c);
                if (array_key_exists($c2, $lower))
                    foreach ($row as $k=>$v) if (strtolower($k) === $c2) return $k;
            }
            return null;
        };
        $start_key = $detect_key($first, ['start_datetime','start','start_dt','start_at','datetime','begin']);
        $end_key   = $detect_key($first,   ['end_datetime','end','end_dt','end_at','datetime_end','finish']);
        if (!$start_key) { tib_10to8_dbg('no start key detected'); return null; }

        usort($all_slots, function($a,$b) use($start_key){
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
            'time'        => wp_date('H:i',      $when->getTimestamp(), $tz),
            'raw'         => $next,
        ];

        tib_10to8_request_memo_set($cache_key, $out);
        tib_10to8_dbg('WRITE slot  -> '.$cache_key.' '.$out['start_iso'].' ('.$out['date'].' '.$out['time'].')');
        tib_10to8_set_transient($cache_key, $out, 10 * MINUTE_IN_SECONDS);
        return $out;
    }}
/* ========== cached getter (no network) ========== */

function tib_get_next_10to8_slot_multi_cached($service_ids, $staff_id, $days_ahead = 60) {
    [$cache_key] = tib_10to8_build_cache_key($service_ids, $staff_id, (int)$days_ahead);
    $cached = tib_10to8_get_transient($cache_key);
    return ($cached !== false) ? $cached : null;
}

/* ========== renderers ========== */

/**
 * LISTS: read cache only (safe under rate limits).
 */
function tib_render_next_slot_multi_cached($service_ids, $staff_id, $days_ahead = 60, $empty_text = 'Check availability', $soft_fetch = false, $soft_cap = 16)
          {
          $cache_off = tib_10to8_cache_disabled();

    if ($cache_off) {
        // Live path when cache is disabled
        $slot = tib_get_next_10to8_slot_multi($service_ids, $staff_id, $days_ahead);
        if (is_array($slot)) {
            return sprintf(
                '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
                esc_attr($slot['start_iso']),
                esc_html($slot['date']),
                esc_html($slot['time'])
            );
        }
        return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
    }

    // Cache ON: build key exactly like the online getter
    [$cache_key, $service_uris, $staff_uri, $days_ahead] =
        tib_10to8_build_cache_key($service_ids, $staff_id, (int)$days_ahead);

    if (!$staff_uri || empty($service_uris)) {
        return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
    }

    // Read via helper (respects ?tib10to8_flush)
    $cached = tib_10to8_get_transient($cache_key);

    // 1) Cache HIT with a real slot
    if (is_array($cached)) {
        return sprintf(
            '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
            esc_attr($cached['start_iso']),
            esc_html($cached['date']),
            esc_html($cached['time'])
        );
    }

    // 2) Cache HIT with explicit NULL (negative cache)
    //    Previously we returned immediately (no soft fetch) – that's the bug.
    if ($cached === null && $soft_fetch) {
        if (!empty($soft_cap)) tib_10to8_set_call_cap((int)$soft_cap);
        // Try a live fill (honours global throttle/budget)
        tib_get_next_10to8_slot_multi($service_ids, $staff_id, $days_ahead);

        // Re-read the raw transient (don’t use the helper to avoid read-bypass)
        $fresh = get_transient($cache_key);
        if (is_array($fresh)) {
            return sprintf(
                '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
                esc_attr($fresh['start_iso']),
                esc_html($fresh['date']),
                esc_html($fresh['time'])
            );
        }
        // Still null or miss → show empty text
        return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
    }

    // 3) Cache MISS (false): do an optional soft fetch, then read once
    if ($cached === false && $soft_fetch) {
        if (!empty($soft_cap)) tib_10to8_set_call_cap((int)$soft_cap);
        tib_get_next_10to8_slot_multi($service_ids, $staff_id, $days_ahead);
        $fresh = get_transient($cache_key);
        if (is_array($fresh)) {
            return sprintf(
                '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
                esc_attr($fresh['start_iso']),
                esc_html($fresh['date']),
                esc_html($fresh['time'])
            );
        }
    }

    // Fallback
    return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
}

/**
 * SINGLES: live call (writes cache on success; preserves it on errors/429).
 */
function tib_render_next_slot_multi_online($service_ids, $staff_id, $days_ahead = 60, $empty_text = 'No availability') {
    $slot = tib_get_next_10to8_slot_multi($service_ids, $staff_id, $days_ahead);
    if (defined('TIB_10TO8_DEBUG') && TIB_10TO8_DEBUG && is_wp_error($slot)) {
        echo "\n<!-- tib_render_next_slot_multi_online error: ".esc_html($slot->get_error_message())." -->\n";
    }
    if (is_wp_error($slot) || !$slot) {
        return '<span class="tib-slot tib-slot--none">'.esc_html($empty_text).'</span>';
    }
    return sprintf(
        '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
        esc_attr($slot['start_iso']),
        esc_html($slot['date']),
        esc_html($slot['time'])
    );
}

function tib_render_next_slot_multi_hybrid($services, $staff_id, $days_ahead = 60, $empty_text = 'No current availability') {
    // 1) Try live (this also writes to cache on success)
    $slot = tib_get_next_10to8_slot_multi($services, $staff_id, $days_ahead);

    if (is_array($slot)) {
        return sprintf(
            '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
            esc_attr($slot['start_iso']),
            esc_html($slot['date']),
            esc_html($slot['time'])
        );
    }

    // 2) Live failed (429, network, etc.) — try cached read
    $cached = tib_get_next_10to8_slot_multi_cached($services, $staff_id, $days_ahead);
    if (is_array($cached)) {
        return sprintf(
            '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
            esc_attr($cached['start_iso']),
            esc_html($cached['date']),
            esc_html($cached['time'])
        );
    }

    // 3) Nothing available
    return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
}

/** Convenience echo wrappers */
function tib_echo_next_slot_multi_cached($service_ids, $staff_id, $days_ahead = 60, $empty_text = 'Check availability') {
    echo tib_render_next_slot_multi_cached($service_ids, $staff_id, $days_ahead, $empty_text);
}
function tib_echo_next_slot_multi_online($service_ids, $staff_id, $days_ahead = 60, $empty_text = 'No availability') {
    echo tib_render_next_slot_multi_online($service_ids, $staff_id, $days_ahead, $empty_text);
}

add_action('tib_10to8_warm_slots_event', function () {
    $services = tib_10to8_get_service_uris();
    $staffs   = get_option('tib_10to8_warm_queue', []);
    if (!$staffs) return;

    $N = 10; // warm more per minute now that we control rate
    $done = 0;

    while ($done < $N && $staffs) {
        $staff_id = array_shift($staffs);
        if (!tib_10to8_throttle_active() && tib_10to8_budget_take(1)) {
            tib_get_next_10to8_slot_multi($services, $staff_id, 28); // writes cache
        }
        $done++;
    }
    update_option('tib_10to8_warm_queue', $staffs, false);
});



// 1a) add a 60s schedule
add_filter('cron_schedules', function ($s) {
    $s['tib_every_minute'] = [
        'interval' => 60,
        'display'  => 'Every Minute (TIB)'
    ];
    return $s;
});

// 1b) clear any bad schedules once
add_action('init', function () {
    if (isset($_GET['tib10to8_reset_cron']) && current_user_can('manage_options')) {
        wp_clear_scheduled_hook('tib_10to8_warm_slots_event');
    }
    if (!wp_next_scheduled('tib_10to8_warm_slots_event')) {
        wp_schedule_event(time() + 15, 'tib_every_minute', 'tib_10to8_warm_slots_event');
    }
});

// TEMP: drop in (functions.php), run once, then remove
add_action('admin_init', function () {
    if (!current_user_can('manage_options') || !isset($_GET['tib_seed_warm'])) return;

    $ptype = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'therapist';

    // First pass: only posts that actually have the 'staff_link' meta row
    $ids_with_meta = get_posts([
        'post_type'      => $ptype === 'any' ? 'any' : $ptype,
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_query'     => [[ 'key' => 'staff_link', 'compare' => 'EXISTS' ]],
    ]);

    // If nothing matched, do a broader sweep and test each post (covers cases where the meta row is empty/serialized/weird)
    $ids = $ids_with_meta;
    if (!$ids) {
        $ids = get_posts([
            'post_type'      => $ptype === 'any' ? 'any' : $ptype,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
    }

    $staff = [];
    foreach ($ids as $pid) {
        $raw = get_post_meta($pid, 'staff_link', true);
        if (!$raw && function_exists('get_field')) {
            $raw = get_field('staff_link', $pid);
        }
        if (!$raw) continue;

        $sid = function_exists('tib_10to8_extract_id') ? tib_10to8_extract_id($raw) : null;
        if ($sid) $staff[] = $sid;
    }

    $staff = array_values(array_unique(array_filter($staff)));
    update_option('tib_10to8_warm_queue', $staff, false);

    wp_die(
        sprintf(
            'Scanned %d posts (%d had a staff_link row). Seeded %d staff into tib_10to8_warm_queue.',
            count($ids),
            count($ids_with_meta),
            count($staff)
        )
    );
});
