<?php
defined('ABSPATH') || exit;

/**
 * secure.7 server-side preview renderer.
 *
 * Font bytes never leave PHP. The browser receives only raster preview images
 * and safe metadata. Imagick is preferred because it can render OTF/TTF on
 * common WordPress hosts; GD/FreeType is used as a TTF fallback.
 */

function ath_specimen_preview_token($post_id, $kind, $index) {
    $post_id = absint($post_id);
    $kind = 'pair' === $kind ? 'pair' : 'style';
    $index = absint($index);
    $payload = $post_id . '|' . $kind . '|' . $index;
    $mac = hash_hmac('sha256', $payload, wp_salt('auth'));
    return $kind . '-' . $index . '-' . substr($mac, 0, 24);
}

function ath_specimen_preview_record($post_id, $token) {
    $post_id = absint($post_id);
    $token = sanitize_text_field((string) $token);
    if (!$post_id || !preg_match('/^(style|pair)-(\d+)-([a-f0-9]{24})$/', $token, $match)) return array();

    $kind = $match[1];
    $index = absint($match[2]);
    if ($index < 1 || !hash_equals(ath_specimen_preview_token($post_id, $kind, $index), $token)) return array();

    if ('pair' === $kind) {
        $rows = ath_specimen_get_meta($post_id, '_ath_pairing_fonts', array());
        $row = is_array($rows) && isset($rows[$index - 1]) ? $rows[$index - 1] : array();
        if (empty($row['font_file'])) return array();
        return array(
            'kind' => 'pair',
            'index' => $index,
            'file' => $row['font_file'],
            'label' => !empty($row['pair_name']) ? (string) $row['pair_name'] : ('Pair ' . $index),
        );
    }

    $rows = ath_specimen_get_meta($post_id, '_ath_font_styles', array());
    $row = is_array($rows) && isset($rows[$index - 1]) ? $rows[$index - 1] : array();
    if (empty($row['font_file']) || !empty($row['is_package'])) return array();
    return array(
        'kind' => 'style',
        'index' => $index,
        'file' => $row['font_file'],
        'label' => !empty($row['style_name']) ? (string) $row['style_name'] : ('Style ' . $index),
    );
}

function ath_specimen_rate_counter_increment($key, $ttl) {
    $key = sanitize_key($key);
    $ttl = max(60, (int) $ttl);
    if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
        $group = 'ath_specimen_rate';
        if (wp_cache_add($key, 1, $group, $ttl)) return 1;
        $count = wp_cache_incr($key, 1, $group);
        if (false !== $count) return (int) $count;
    }
    $transient = 'ath_rl_' . md5($key);
    $count = (int) get_transient($transient) + 1;
    set_transient($transient, $count, $ttl);
    return $count;
}


function ath_specimen_rate_db_mutex($identity, $acquire = true) {
    global $wpdb;
    if (empty($wpdb) || !is_object($wpdb) || !method_exists($wpdb, 'get_var') || !method_exists($wpdb, 'prepare')) return false;
    $name = 'athrl_' . substr(hash('sha256', (string) $identity), 0, 40);
    if ($acquire) {
        $result = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, 1));
        return '1' === (string) $result;
    }
    $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    return true;
}

function ath_specimen_render_cache_mutex($cache_key, $acquire = true) {
    global $wpdb;
    static $file_handles = array();
    $name = 'athrender_' . substr(hash('sha256', (string) $cache_key), 0, 32);
    if (!$acquire && !empty($file_handles[$name]) && is_resource($file_handles[$name])) {
        @flock($file_handles[$name], LOCK_UN);
        @fclose($file_handles[$name]);
        unset($file_handles[$name]);
        return true;
    }
    if (!empty($wpdb) && is_object($wpdb) && method_exists($wpdb, 'get_var') && method_exists($wpdb, 'prepare')) {
        if (!$acquire) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
            return true;
        }
        $wait = max(0, min(8, (int) apply_filters('authentype_specimen_render_lock_wait', 4)));
        $result = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, $wait));
        if (null !== $result) return '1' === (string) $result;
    }

    // Filesystem fallback for database engines that do not expose GET_LOCK().
    $lock_path = trailingslashit(sys_get_temp_dir()) . $name . '.lock';
    if (!$acquire) {
        return true;
    }
    $handle = @fopen($lock_path, 'c');
    if (!$handle || !@flock($handle, LOCK_EX | LOCK_NB)) {
        if (is_resource($handle)) @fclose($handle);
        return false;
    }
    $file_handles[$name] = $handle;
    return true;
}

function ath_specimen_global_render_rate_limit_ok($kind, $window, $ttl) {
    $defaults = array('render' => 600, 'metadata' => 400, 'glyph' => 180);
    $limits = apply_filters('authentype_specimen_global_render_rate_limits', $defaults);
    $maximum = max(40, (int) ($limits[$kind] ?? $defaults[$kind]));
    $key = 'server|' . $kind . '|' . $window;

    if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
        return ath_specimen_rate_counter_increment($key, $ttl + 30) <= $maximum;
    }

    $mutex = ath_specimen_rate_db_mutex($key, true);
    $transient = 'ath_grl_' . md5($key);
    $count = (int) get_transient($transient) + 1;
    set_transient($transient, $count, $ttl + 30);
    if ($mutex) ath_specimen_rate_db_mutex($key, false);
    return $count <= $maximum;
}

function ath_specimen_render_rate_limit_ok($post_id, $kind = 'render') {
    $post_id = absint($post_id);
    $kind = in_array($kind, array('render', 'metadata', 'glyph'), true) ? $kind : 'render';
    $remote = function_exists('ath_specimen_client_ip') ? ath_specimen_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');
    $identity = hash('sha256', ($remote ?: 'unknown') . '|' . wp_salt('nonce'));
    $ttl = 10 * MINUTE_IN_SECONDS;
    $legacy_render_max = max(60, (int) apply_filters('authentype_specimen_render_requests_per_10min', 180));
    $defaults = array(
        'render' => array('global' => $legacy_render_max, 'post' => min($legacy_render_max, 160)),
        'metadata' => array('global' => 120, 'post' => 100),
        'glyph' => array('global' => 90, 'post' => 80),
    );
    $limits = apply_filters('authentype_specimen_render_rate_limits', $defaults, $kind, $post_id);
    $global_max = max(20, (int) ($limits[$kind]['global'] ?? $defaults[$kind]['global']));
    $post_max = max(20, (int) ($limits[$kind]['post'] ?? $defaults[$kind]['post']));
    $window = (int) floor(time() / $ttl);

    // This server-wide ceiling protects PHP workers when traffic comes from
    // many different IP addresses. Per-visitor limits below remain unchanged.
    if (!ath_specimen_global_render_rate_limit_ok($kind, $window, $ttl)) return false;

    if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
        $global_count = ath_specimen_rate_counter_increment('g|' . $kind . '|' . $identity . '|' . $window, $ttl + 30);
        if ($global_count > $global_max) return false;
        $post_count = ath_specimen_rate_counter_increment('p|' . $kind . '|' . $post_id . '|' . $identity . '|' . $window, $ttl + 30);
        return $post_count <= $post_max;
    }

    // Shared-host fallback: keep global and per-post counts in one transient so
    // each preview request performs one state write rather than two.
    $state_key = 'ath_rls_' . md5($identity . '|' . $window);
    $mutex_identity = $identity . '|' . $window;
    $mutex = ath_specimen_rate_db_mutex($mutex_identity, true);
    $state = get_transient($state_key);
    $state = is_array($state) ? $state : array('global' => array(), 'posts' => array());
    $state['global'][$kind] = (int) ($state['global'][$kind] ?? 0) + 1;
    $post_key = (string) $post_id;
    if (empty($state['posts'][$post_key]) || !is_array($state['posts'][$post_key])) $state['posts'][$post_key] = array();
    $state['posts'][$post_key][$kind] = (int) ($state['posts'][$post_key][$kind] ?? 0) + 1;
    set_transient($state_key, $state, $ttl + 30);
    if ($mutex) ath_specimen_rate_db_mutex($mutex_identity, false);
    return $state['global'][$kind] <= $global_max && $state['posts'][$post_key][$kind] <= $post_max;
}

function ath_specimen_render_cache_dir($post_id) {
    $uploads = wp_get_upload_dir();
    if (empty($uploads['basedir'])) return '';
    $dir = trailingslashit($uploads['basedir']) . 'woocommerce_uploads/authentype-render-cache/' . absint($post_id);
    if (function_exists('ath_specimen_protect_download_dir')) {
        ath_specimen_protect_download_dir(trailingslashit($uploads['basedir']) . 'woocommerce_uploads');
        ath_specimen_protect_download_dir(trailingslashit($uploads['basedir']) . 'woocommerce_uploads/authentype-render-cache');
        ath_specimen_protect_download_dir($dir);
    } elseif (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    return is_dir($dir) && is_writable($dir) ? $dir : '';
}

function ath_specimen_render_prune_cache($dir, $force = false) {
    if (!$dir || !is_dir($dir)) return;
    $marker = trailingslashit($dir) . '.ath-prune';
    if (!$force && is_file($marker) && (time() - (int) @filemtime($marker)) < 5 * MINUTE_IN_SECONDS) return;
    @touch($marker);

    $ttl = max(HOUR_IN_SECONDS, (int) apply_filters('authentype_specimen_render_cache_ttl', DAY_IN_SECONDS));
    $max_files = max(80, (int) apply_filters('authentype_specimen_render_cache_max_files', 500));
    $max_bytes = max(16 * 1024 * 1024, (int) apply_filters('authentype_specimen_render_cache_max_bytes', 150 * 1024 * 1024));
    $now = time();
    $files = array();
    $total_bytes = 0;
    foreach ((array) glob(trailingslashit($dir) . '*.png') as $file) {
        if (!is_file($file)) continue;
        $mtime = @filemtime($file) ?: 0;
        if ($mtime && ($now - $mtime) > $ttl) {
            wp_delete_file($file);
            continue;
        }
        $size = max(0, (int) @filesize($file));
        $files[$file] = array('mtime' => $mtime, 'size' => $size);
        $total_bytes += $size;
    }
    if (count($files) <= $max_files && $total_bytes <= $max_bytes) return;
    uasort($files, function ($a, $b) { return ($a['mtime'] ?? 0) <=> ($b['mtime'] ?? 0); });
    foreach ($files as $file => $meta) {
        if (count($files) <= $max_files && $total_bytes <= $max_bytes) break;
        $total_bytes -= (int) ($meta['size'] ?? 0);
        wp_delete_file($file);
        unset($files[$file]);
    }
}

function ath_specimen_render_prune_global_cache($force = false) {
    $uploads = wp_get_upload_dir();
    if (empty($uploads['basedir'])) return;
    $root = trailingslashit($uploads['basedir']) . 'woocommerce_uploads/authentype-render-cache';
    if (!is_dir($root)) return;
    $marker = trailingslashit($root) . '.ath-global-prune';
    if (!$force && is_file($marker) && (time() - (int) @filemtime($marker)) < 30 * MINUTE_IN_SECONDS) return;
    @touch($marker);

    $max_files = max(500, (int) apply_filters('authentype_specimen_render_cache_global_max_files', 5000));
    $max_bytes = max(128 * 1024 * 1024, (int) apply_filters('authentype_specimen_render_cache_global_max_bytes', 1024 * 1024 * 1024));
    $scan_cap = max($max_files + 1000, (int) apply_filters('authentype_specimen_render_cache_global_scan_cap', 10000));
    $files = array();
    $total = 0;
    $seen = 0;

    try {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if (++$seen > $scan_cap) break;
            if (!$item->isFile() || 'png' !== strtolower($item->getExtension())) continue;
            $path = $item->getPathname();
            $size = max(0, (int) $item->getSize());
            $files[$path] = array('mtime' => (int) $item->getMTime(), 'size' => $size);
            $total += $size;
        }
    } catch (UnexpectedValueException $e) {
        return;
    }

    if (count($files) <= $max_files && $total <= $max_bytes) return;
    uasort($files, function ($a, $b) { return $a['mtime'] <=> $b['mtime']; });
    foreach ($files as $path => $meta) {
        if (count($files) <= $max_files && $total <= $max_bytes) break;
        $total -= (int) $meta['size'];
        wp_delete_file($path);
        unset($files[$path]);
    }
}

function ath_specimen_private_temp_dir() {
    static $resolved = null;
    if (null !== $resolved) return $resolved;
    $candidates = array(sys_get_temp_dir());
    if (function_exists('get_temp_dir')) $candidates[] = get_temp_dir();
    $uploads = wp_get_upload_dir();
    $public_roots = array(ABSPATH, defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR : '', !empty($uploads['basedir']) ? $uploads['basedir'] : '');
    foreach (array_unique(array_filter($candidates)) as $candidate) {
        $candidate = rtrim(wp_normalize_path($candidate), '/');
        if (!$candidate || !is_dir($candidate) || !is_writable($candidate)) continue;
        $public = false;
        foreach ($public_roots as $root) {
            $root = $root ? rtrim(wp_normalize_path($root), '/') : '';
            if ($root && (0 === strpos($candidate . '/', $root . '/') || 0 === strpos($root . '/', $candidate . '/'))) {
                // Reject a temp path inside, equal to, or directly enclosing a
                // known WordPress public tree. Normal system temp paths such as
                // /tmp do not overlap these roots and remain eligible.
                $public = true;
            }
        }
        if ($public) continue;
        $dir = $candidate . '/ath-specimen-' . substr(hash('sha256', home_url('/') . wp_salt('auth')), 0, 12);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) continue;
        @chmod($dir, 0700);
        if (is_dir($dir) && is_writable($dir)) {
            $resolved = $dir;
            return $resolved;
        }
    }
    $resolved = '';
    return '';
}

function ath_specimen_cleanup_private_temp_dir() {
    $dir = ath_specimen_private_temp_dir();
    if (!$dir) return;
    $marker = trailingslashit($dir) . '.ath-cleanup';
    if (is_file($marker) && (time() - (int) @filemtime($marker)) < HOUR_IN_SECONDS) return;
    @touch($marker);
    $cutoff = time() - HOUR_IN_SECONDS;
    foreach ((array) glob(trailingslashit($dir) . 'athg-*') as $file) {
        if (is_file($file) && (int) @filemtime($file) < $cutoff) wp_delete_file($file);
    }
}

function ath_specimen_register_temp_file_cleanup($path) {
    static $paths = array();
    static $registered = false;
    if ($path) $paths[$path] = true;
    if (!$registered) {
        $registered = true;
        register_shutdown_function(function () use (&$paths) {
            foreach (array_keys($paths) as $file) if (is_file($file)) @unlink($file);
        });
    }
}

function ath_specimen_hex_color($value, $fallback = '#111111') {
    $value = sanitize_hex_color($value);
    return $value ?: $fallback;
}


function ath_specimen_parse_glyph_items($raw) {
    if (!is_string($raw) || '' === $raw) return array();
    $decoded = json_decode(wp_unslash($raw), true);
    if (!is_array($decoded)) return array();
    $items = array();
    foreach ($decoded as $item) {
        if (!is_array($item)) continue;
        $label = isset($item['label']) ? sanitize_text_field((string) $item['label']) : '';
        $label = function_exists('mb_substr') ? mb_substr($label, 0, 32, 'UTF-8') : substr($label, 0, 32);
        $type = isset($item['type']) ? sanitize_key((string) $item['type']) : 'unencoded';
        if (!in_array($type, array('unicode', 'unencoded'), true)) $type = 'unencoded';
        $items[] = array(
            'gid' => isset($item['gid']) ? absint($item['gid']) : 0,
            'text' => '', // secure.7.3 always remaps the requested GID server-side.
            'label' => $label,
            'type' => $type,
        );
        if (count($items) >= 120) break;
    }
    return $items;
}


/**
 * Full Glyph Engine (secure.7.3)
 *
 * FreeType-based renderers normally accept Unicode text, not an arbitrary
 * glyph index. To render every glyph slot (including unencoded alternates,
 * ligatures, ornaments, and .notdef) without exposing outlines, secure.7.3
 * rebuilds a temporary server-only SFNT with a tiny cmap table that maps
 * Private Use codepoints to the requested Glyph IDs. The original outline
 * tables remain untouched. Imagick/GD then rasterize those temporary mappings
 * and the temporary font is deleted immediately after the PNG is generated.
 */
function ath_specimen_sfnt_u32_sum($data) {
    if (!is_string($data)) return 0;
    $pad = (4 - (strlen($data) % 4)) % 4;
    if ($pad) $data .= str_repeat("\0", $pad);
    $sum = 0;
    $length = strlen($data);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $value = unpack('Nvalue', substr($data, $offset, 4));
        $sum = ($sum + (int) ($value['value'] ?? 0)) & 0xffffffff;
    }
    return $sum;
}

/**
 * Text renderer cache revision.
 *
 * Preview PNGs are persisted server-side. Keep the algorithm revision in the
 * cache key so a preview-fit update can never reuse a raster generated by an
 * older sizing algorithm.
 */
function ath_specimen_text_renderer_revision() {
    return 'secure-8.3.9-text-fit-r2';
}

function ath_specimen_full_glyph_renderer_revision() {
    // Renderer-specific cache revision. This intentionally affects only Full
    // Glyph PNGs so normal specimen preview caches are left untouched.
    return 'gid-bmp-pua-format4-v2';
}

function ath_specimen_glyph_private_codepoint($index) {
    // secure.8.2.17: Full Glyph requests are capped at 120 items, so a compact
    // BMP Private Use range (U+E000..U+E077) is sufficient. BMP codepoints use
    // three-byte UTF-8 and are substantially more compatible with older
    // GD/FreeType stacks than supplementary-plane U+F0000 mappings.
    $index = max(0, min(119, (int) $index));
    return 0xE000 + $index;
}

function ath_specimen_build_gid_cmap($glyph_ids) {
    $glyph_ids = array_values((array) $glyph_ids);
    $count = count($glyph_ids);
    if ($count < 1 || $count > 120) return '';

    // secure.8.2.17: use a BMP cmap format 4 only. Each temporary codepoint gets
    // its own one-codepoint segment, so any requested GID can be mapped without
    // relying on supplementary-plane cmap selection in GD/FreeType.
    $end_codes = '';
    $start_codes = '';
    $id_deltas = '';
    $id_range_offsets = '';

    foreach ($glyph_ids as $index => $gid) {
        $codepoint = ath_specimen_glyph_private_codepoint($index);
        $gid = max(0, (int) $gid);
        $delta = ($gid - $codepoint) & 0xffff;
        $end_codes .= pack('n', $codepoint);
        $start_codes .= pack('n', $codepoint);
        $id_deltas .= pack('n', $delta);
        $id_range_offsets .= pack('n', 0);
    }

    // Required terminal segment. U+FFFF + delta 1 maps back to glyph 0.
    $end_codes .= pack('n', 0xffff);
    $start_codes .= pack('n', 0xffff);
    $id_deltas .= pack('n', 1);
    $id_range_offsets .= pack('n', 0);

    $seg_count = $count + 1;
    $seg_count_x2 = $seg_count * 2;
    $power = 1;
    $entry_selector = 0;
    while (($power * 2) <= $seg_count) {
        $power *= 2;
        $entry_selector++;
    }
    $search_range = $power * 2;
    $range_shift = $seg_count_x2 - $search_range;
    $length = 16 + ($seg_count * 8);

    $subtable = pack(
        'nnnnnnn',
        4,
        $length,
        0,
        $seg_count_x2,
        $search_range,
        $entry_selector,
        $range_shift
    )
        . $end_codes
        . pack('n', 0)
        . $start_codes
        . $id_deltas
        . $id_range_offsets;

    // Two records point to the same BMP subtable: Unicode BMP and Microsoft
    // Unicode BMP. This avoids advertising a UCS-4 charmap that older stacks
    // may prefer even though the temporary characters are all inside the BMP.
    $subtable_offset = 4 + (2 * 8);
    return pack('nn', 0, 2)
        . pack('nnN', 0, 3, $subtable_offset)
        . pack('nnN', 3, 1, $subtable_offset)
        . $subtable;
}

function ath_specimen_source_sfnt_flavor($font_data) {
    if (!is_string($font_data) || strlen($font_data) < 8) return '';
    $signature = substr($font_data, 0, 4);
    if ('wOFF' === $signature) return substr($font_data, 4, 4);
    if ('wOF2' === $signature) return '';
    return $signature;
}

function ath_specimen_rebuild_sfnt_with_cmap($font_data, $glyph_ids) {
    if (!is_string($font_data) || strlen($font_data) < 12) {
        return new WP_Error('ath_glyph_font_invalid', __('The preview font is not a valid OpenType/TrueType source.', 'authentype-font-specimen'));
    }

    $flavor = ath_specimen_source_sfnt_flavor($font_data);
    if (!$flavor) {
        return new WP_Error('ath_glyph_font_woff2', __('Full Glyph Engine requires an OTF, TTF, or WOFF preview source. WOFF2 preview sources cannot be remapped server-side.', 'authentype-font-specimen'));
    }

    $tables = ath_specimen_get_table_map($font_data);
    if (empty($tables['head']) || empty($tables['maxp'])) {
        return new WP_Error('ath_glyph_font_tables', __('The preview font is missing required OpenType tables.', 'authentype-font-specimen'));
    }
    if (empty($tables['glyf']) && empty($tables['CFF ']) && empty($tables['CFF2'])) {
        return new WP_Error('ath_glyph_font_outline', __('The preview font does not contain a supported outline table.', 'authentype-font-specimen'));
    }

    $glyph_count = strlen($tables['maxp']) >= 6 ? ath_specimen_u16($tables['maxp'], 4) : 0;
    if ($glyph_count < 1) {
        return new WP_Error('ath_glyph_count_missing', __('The preview font does not report a valid glyph count.', 'authentype-font-specimen'));
    }

    $valid_ids = array();
    foreach ((array) $glyph_ids as $gid) {
        $gid = (int) $gid;
        if ($gid < 0 || $gid >= $glyph_count) {
            return new WP_Error('ath_glyph_id_invalid', __('A requested Glyph ID is outside the font glyph range.', 'authentype-font-specimen'));
        }
        $valid_ids[] = $gid;
    }
    if (empty($valid_ids) || count($valid_ids) > 120) {
        return new WP_Error('ath_glyph_page_invalid', __('The requested glyph page is invalid.', 'authentype-font-specimen'));
    }

    $new_cmap = ath_specimen_build_gid_cmap($valid_ids);
    if (!$new_cmap) {
        return new WP_Error('ath_glyph_cmap_failed', __('The temporary glyph map could not be built.', 'authentype-font-specimen'));
    }
    $tables['cmap'] = $new_cmap;

    // OpenType checksumAdjustment is calculated with this field zeroed.
    if (strlen($tables['head']) >= 12) {
        $tables['head'] = substr_replace($tables['head'], "\0\0\0\0", 8, 4);
    }

    ksort($tables, SORT_STRING);
    $num_tables = count($tables);
    if ($num_tables < 1 || $num_tables > 256) {
        return new WP_Error('ath_glyph_table_count', __('The preview font table count is invalid.', 'authentype-font-specimen'));
    }

    $power = 1;
    $entry_selector = 0;
    while (($power * 2) <= $num_tables) {
        $power *= 2;
        $entry_selector++;
    }
    $search_range = $power * 16;
    $range_shift = ($num_tables * 16) - $search_range;

    $header = $flavor . pack('nnnn', $num_tables, $search_range, $entry_selector, $range_shift);
    $directory = '';
    $body = '';
    $offset = 12 + ($num_tables * 16);
    $records = array();

    foreach ($tables as $tag => $table_data) {
        if (4 !== strlen($tag) || !is_string($table_data)) continue;
        $length = strlen($table_data);
        $padding = (4 - ($length % 4)) % 4;
        $checksum = ath_specimen_sfnt_u32_sum($table_data);
        $records[$tag] = array('offset' => $offset, 'length' => $length, 'checksum' => $checksum);
        $directory .= $tag . pack('NNN', $checksum, $offset, $length);
        $body .= $table_data . ($padding ? str_repeat("\0", $padding) : '');
        $offset += $length + $padding;
    }

    $rebuilt = $header . $directory . $body;
    if (!isset($records['head'])) {
        return new WP_Error('ath_glyph_head_missing', __('The temporary font could not locate its head table.', 'authentype-font-specimen'));
    }

    $whole_sum = ath_specimen_sfnt_u32_sum($rebuilt);
    $adjustment = (0xB1B0AFBA - $whole_sum) & 0xffffffff;
    $rebuilt = substr_replace($rebuilt, pack('N', $adjustment), $records['head']['offset'] + 8, 4);

    return array(
        'data' => $rebuilt,
        'flavor' => $flavor,
        'glyph_count' => $glyph_count,
    );
}

function ath_specimen_prepare_gid_render_font($font_path, $glyph_items) {
    if (!$font_path || !is_file($font_path) || !is_readable($font_path)) {
        return new WP_Error('ath_glyph_font_missing', __('The protected preview font could not be read.', 'authentype-font-specimen'));
    }

    $glyph_ids = array();
    $render_items = array();
    foreach (array_values((array) $glyph_items) as $index => $item) {
        if (!is_array($item)) continue;
        $gid = isset($item['gid']) ? (int) $item['gid'] : -1;
        $glyph_ids[] = $gid;
        $item['text'] = ath_specimen_codepoint_to_utf8(ath_specimen_glyph_private_codepoint(count($glyph_ids) - 1));
        $render_items[] = $item;
    }
    if (empty($glyph_ids)) {
        return new WP_Error('ath_glyph_page_empty', __('No glyphs were supplied for this page.', 'authentype-font-specimen'));
    }

    $max_bytes = max(1024 * 1024, (int) apply_filters('authentype_specimen_glyph_source_max_bytes', 64 * 1024 * 1024));
    $size = @filesize($font_path);
    if (!$size || $size > $max_bytes) {
        return new WP_Error('ath_glyph_font_too_large', __('The preview font is too large for the Full Glyph Engine.', 'authentype-font-specimen'));
    }
    $font_data = @file_get_contents($font_path);
    if (!is_string($font_data) || '' === $font_data) {
        return new WP_Error('ath_glyph_font_read', __('The preview font could not be loaded for glyph rendering.', 'authentype-font-specimen'));
    }

    $rebuilt = ath_specimen_rebuild_sfnt_with_cmap($font_data, $glyph_ids);
    unset($font_data);
    if (is_wp_error($rebuilt)) return $rebuilt;

    $extension = 'OTTO' === $rebuilt['flavor'] ? '.otf' : '.ttf';
    $private_tmp = ath_specimen_private_temp_dir();
    if (!$private_tmp) {
        return new WP_Error('ath_glyph_private_temp', __('Full Glyph rendering requires a private writable system temporary directory outside the public WordPress tree.', 'authentype-font-specimen'));
    }
    ath_specimen_cleanup_private_temp_dir();
    $base_tmp = @tempnam($private_tmp, 'athg-');
    if (!$base_tmp) {
        return new WP_Error('ath_glyph_temp_failed', __('A temporary server font could not be created.', 'authentype-font-specimen'));
    }
    @chmod($base_tmp, 0600);
    $temp_path = $base_tmp . $extension;
    if (@rename($base_tmp, $temp_path)) @chmod($temp_path, 0600); else $temp_path = $base_tmp;
    ath_specimen_register_temp_file_cleanup($temp_path);
    $written = @file_put_contents($temp_path, $rebuilt['data'], LOCK_EX);
    if (false === $written || $written !== strlen($rebuilt['data'])) {
        @wp_delete_file($temp_path);
        return new WP_Error('ath_glyph_temp_write', __('The temporary glyph font could not be written.', 'authentype-font-specimen'));
    }

    return array(
        'path' => $temp_path,
        'items' => $render_items,
        'glyph_count' => (int) $rebuilt['glyph_count'],
    );
}

function ath_specimen_render_split_lines($text, $font_path, $font_size, $max_width, $engine = 'imagick') {
    $paragraphs = preg_split('/\R/u', (string) $text);
    $lines = array();
    $probe = null;
    $measure_draw = null;
    if ('imagick' === $engine && class_exists('ImagickDraw') && class_exists('Imagick')) {
        $measure_draw = new ImagickDraw();
        $measure_draw->setFont($font_path);
        $measure_draw->setFontSize($font_size);
        $probe = new Imagick();
    }
    $measure = static function ($value) use ($probe, $measure_draw, $font_path, $font_size) {
        if ($probe && $measure_draw) {
            $metrics = $probe->queryFontMetrics($measure_draw, $value, false);
            return !empty($metrics['textWidth']) ? (float) $metrics['textWidth'] : 0.0;
        }
        if (function_exists('imagettfbbox')) {
            $box = @imagettfbbox($font_size, 0, $font_path, $value);
            if (is_array($box)) return (float) abs($box[2] - $box[0]);
        }
        return 0.0;
    };
    $split_long_word = static function ($word) use ($measure, $max_width) {
        if ($measure($word) <= $max_width) return array($word);
        $characters = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($characters) || empty($characters)) return array($word);
        $parts = array();
        $part = '';
        foreach ($characters as $character) {
            $candidate = $part . $character;
            if ('' !== $part && $measure($candidate) > $max_width) {
                $parts[] = $part;
                $part = $character;
            } else {
                $part = $candidate;
            }
        }
        if ('' !== $part) $parts[] = $part;
        return $parts ?: array($word);
    };
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ('' === $paragraph) {
            $lines[] = '';
            continue;
        }
        $words = preg_split('/\s+/u', $paragraph);
        $current = '';
        foreach ($words as $word) {
            foreach ($split_long_word($word) as $part) {
                $test = '' === $current ? $part : $current . ' ' . $part;
                if ($current && $measure($test) > $max_width) {
                    $lines[] = $current;
                    $current = $part;
                } else {
                    $current = $test;
                }
            }
        }
        if ('' !== $current) $lines[] = $current;
    }
    if ($probe) $probe->clear();
    return $lines ?: array('');
}


/**
 * Fit the current specimen text to one line without ever enlarging above the
 * requested size. This is used only for the initial/default preview. Once the
 * visitor moves the size slider the frontend disables auto-fit and the chosen
 * size is respected exactly.
 */
function ath_specimen_fit_single_line_font_size($font_path, $text, $requested_size, $max_width, $engine = 'imagick') {
    $requested_size = max(12, (int) $requested_size);
    // secure.8.3.9 — cache-safe, ink-safe universal default fit.
    // The UI still targets 38px. Only untouched default proof text may shrink.
    $min_size = max(4, min($requested_size, (int) apply_filters('authentype_specimen_autofit_min_size', 4)));
    $max_width = max(80, (float) $max_width);
    // Use a deliberately conservative width. This absorbs italic overhang,
    // broad side bearings, hinting differences, and raster metric variance.
    $fit_width = max(72, $max_width * 0.94);
    $text = preg_replace('/\s+/u', ' ', trim((string) $text));
    if ('' === $text) return $requested_size;

    $measure = static function ($size) use ($font_path, $text, $engine) {
        if ('imagick' === $engine && class_exists('Imagick') && class_exists('ImagickDraw')) {
            try {
                $draw = new ImagickDraw();
                $draw->setFont($font_path);
                $draw->setFontSize($size);
                $draw->setTextAntialias(true);
                // Some ImageMagick builds return incomplete/zero metrics when
                // queryFontMetrics() is called on an image with no canvas.
                // Initialising a tiny canvas makes the measurement deterministic.
                $probe = new Imagick();
                $probe->newImage(2, 2, new ImagickPixel('transparent'), 'png');
                $metrics = $probe->queryFontMetrics($draw, $text, false);
                $probe->clear();

                $advance = !empty($metrics['textWidth']) ? (float) $metrics['textWidth'] : 0.0;
                $ink_right = 0.0;
                if (!empty($metrics['boundingBox']) && is_array($metrics['boundingBox'])) {
                    $bbox = $metrics['boundingBox'];
                    if (isset($bbox['x2'])) $ink_right = (float) $bbox['x2'];
                }
                return max($advance, $ink_right);
            } catch (Throwable $e) {
                return 0.0;
            }
        }
        if (function_exists('imagettfbbox')) {
            $box = @imagettfbbox($size, 0, $font_path, $text);
            if (is_array($box)) {
                // Right-most ink/advance approximation, not only abs(width), so
                // fonts with unusual bearings do not pass the fit test too early.
                $xs = array((float) $box[0], (float) $box[2], (float) $box[4], (float) $box[6]);
                return max($xs) - min(0.0, min($xs));
            }
        }
        return 0.0;
    };

    $width = $measure($requested_size);
    if ($width <= 0) {
        // Never silently skip fitting when a host cannot report metrics.
        // A conservative proportional fallback keeps the default proof inside
        // typical product columns while leaving manual/user text untouched.
        return max($min_size, min($requested_size, (int) floor($requested_size * 0.78)));
    }
    if ($width <= $fit_width) return $requested_size;

    $size = (int) floor($requested_size * ($fit_width / $width) * 0.985);
    $size = max($min_size, min($requested_size, $size));
    while ($size > $min_size && $measure($size) > $fit_width) $size--;
    return $size;
}

function ath_specimen_render_with_imagick($font_path, $text, $width, $font_size, $line_height, $text_color, $bg_color, $mode, $glyph_items = array(), $fit_single_line = false) {
    if (!class_exists('Imagick') || !class_exists('ImagickDraw')) return new WP_Error('ath_no_imagick', __('Imagick is not available.', 'authentype-font-specimen'));

    try {
        $padding = 'glyph-grid' === $mode ? 18 : 24;
        $image = new Imagick();
        $draw = new ImagickDraw();
        $draw->setFont($font_path);
        $draw->setFontSize($font_size);
        $draw->setFillColor(new ImagickPixel($text_color));
        $draw->setTextAntialias(true);

        if ('glyph-grid' === $mode) {
            $items = is_array($glyph_items) && !empty($glyph_items) ? array_values($glyph_items) : array();
            if (empty($items)) {
                $chars = preg_split('//u', preg_replace('/\s+/u', '', $text), -1, PREG_SPLIT_NO_EMPTY);
                $chars = array_values(array_unique($chars));
                foreach ($chars as $char) {
                    $items[] = array('gid' => 0, 'text' => $char, 'label' => '', 'type' => 'unicode');
                }
            }
            $items = array_slice($items, 0, 120);
            $cell = max(84, min(132, (int) round($font_size * 2.6)));
            $label_h = 20;
            $cols = max(3, (int) floor(($width - ($padding * 2)) / $cell));
            $rows = max(1, (int) ceil(count($items) / $cols));
            $height = min(5000, ($rows * ($cell + $label_h)) + ($padding * 2));
            $image->newImage($width, $height, new ImagickPixel($bg_color), 'png');
            $grid = new ImagickDraw();
            $grid->setStrokeColor(new ImagickPixel('#dfe3e8'));
            $grid->setStrokeWidth(1);
            $grid->setFillOpacity(0);
            $label_draw = new ImagickDraw();
            $label_draw->setFontSize(max(10, (int) round($font_size * 0.28)));
            $label_draw->setFillColor(new ImagickPixel('#6b7280'));
            $label_draw->setTextAntialias(true);
            foreach ($items as $i => $item) {
                $col = $i % $cols;
                $row = (int) floor($i / $cols);
                $x = $padding + ($col * $cell);
                $y = $padding + ($row * ($cell + $label_h));
                $grid->rectangle($x, $y, $x + $cell, $y + $cell);
                $glyph = isset($item['text']) ? (string) $item['text'] : '';
                $label = isset($item['label']) ? (string) $item['label'] : '';
                if ('' !== $glyph) {
                    $metrics = $image->queryFontMetrics($draw, $glyph, false);
                    $tw = !empty($metrics['textWidth']) ? $metrics['textWidth'] : 0;
                    $th = !empty($metrics['textHeight']) ? $metrics['textHeight'] : $font_size;
                    $tx = $x + (($cell - $tw) / 2);
                    $ty = $y + (($cell + $th) / 2) - max(6, $th * .08);
                    $image->annotateImage($draw, $tx, $ty, 0, $glyph);
                } else {
                    $placeholder = new ImagickDraw();
                    $placeholder->setStrokeColor(new ImagickPixel('#9aa3af'));
                    $placeholder->setStrokeWidth(1);
                    $placeholder->setFillOpacity(0);
                    $placeholder->rectangle($x + 22, $y + 18, $x + $cell - 22, $y + $cell - 26);
                    $placeholder->line($x + 22, $y + 18, $x + $cell - 22, $y + $cell - 26);
                    $image->drawImage($placeholder);
                }
                if ($label) {
                    $lm = $image->queryFontMetrics($label_draw, $label, false);
                    $lw = !empty($lm['textWidth']) ? $lm['textWidth'] : 0;
                    $lx = $x + max(4, ($cell - $lw) / 2);
                    $ly = $y + $cell + 15;
                    $image->annotateImage($label_draw, $lx, $ly, 0, $label);
                }
            }
            $image->drawImage($grid);
        } else {
            $text_left = 'style-text' === $mode ? 8 : $padding;
            $text_right = $padding;
            // Keep a small ink-overhang allowance so italic/wide edge glyphs
            // cannot touch or be clipped by the right edge of the bitmap.
            $max_text_width = max(80, $width - $text_left - $text_right - 4);
            if ($fit_single_line) {
                $font_size = ath_specimen_fit_single_line_font_size($font_path, $text, $font_size, $max_text_width, 'imagick');
                $draw->setFontSize($font_size);
                $lines = array(preg_replace('/\s+/u', ' ', trim((string) $text)));
            } else {
                $lines = ath_specimen_render_split_lines($text, $font_path, $font_size, $max_text_width, 'imagick');
            }
            $line_px = max($font_size, $font_size * $line_height);
            $height = max(76, min(2200, (int) ceil(($padding * 2) + (count($lines) * $line_px))));
            $image->newImage($width, $height, new ImagickPixel($bg_color), 'png');
            $baseline = $padding + $font_size;
            foreach ($lines as $line) {
                $image->annotateImage($draw, $text_left, $baseline, 0, $line);
                $baseline += $line_px;
            }
        }

        $image->setImageFormat('png');
        $image->setImageCompressionQuality(88);
        $blob = $image->getImagesBlob();
        $image->clear();
        return $blob;
    } catch (Throwable $e) {
        return new WP_Error('ath_imagick_render', __('Server font rendering failed.', 'authentype-font-specimen'));
    }
}

function ath_specimen_render_with_gd($font_path, $text, $width, $font_size, $line_height, $text_color, $bg_color, $mode, $glyph_items = array(), $fit_single_line = false) {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) {
        return new WP_Error('ath_no_gd', __('GD FreeType is not available.', 'authentype-font-specimen'));
    }
    if ('ttf' !== strtolower(pathinfo($font_path, PATHINFO_EXTENSION))) {
        return new WP_Error('ath_gd_ttf_only', __('GD fallback requires a TTF preview font.', 'authentype-font-specimen'));
    }

    $padding = 'glyph-grid' === $mode ? 18 : 24;
    $rgb = function ($hex) {
        $hex = ltrim($hex, '#');
        return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    };
    $fg = $rgb($text_color);
    $bg = $rgb($bg_color);

    if ('glyph-grid' === $mode) {
        $items = is_array($glyph_items) && !empty($glyph_items) ? array_values($glyph_items) : array();
        if (empty($items)) {
            $chars = preg_split('//u', preg_replace('/\s+/u', '', $text), -1, PREG_SPLIT_NO_EMPTY);
            $chars = array_values(array_unique($chars));
            foreach ($chars as $char) {
                $items[] = array('gid' => 0, 'text' => $char, 'label' => '', 'type' => 'unicode');
            }
        }
        $items = array_slice($items, 0, 120);
        $cell = max(84, min(132, (int) round($font_size * 2.6)));
        $label_h = 20;
        $cols = max(3, (int) floor(($width - ($padding * 2)) / $cell));
        $rows = max(1, (int) ceil(count($items) / $cols));
        $height = min(5000, ($rows * ($cell + $label_h)) + ($padding * 2));
        $im = imagecreatetruecolor($width, $height);
        $bgc = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
        $fgc = imagecolorallocate($im, $fg[0], $fg[1], $fg[2]);
        $gridc = imagecolorallocate($im, 223, 227, 232);
        $muted = imagecolorallocate($im, 107, 114, 128);
        $placeholder = imagecolorallocate($im, 154, 163, 175);
        imagefill($im, 0, 0, $bgc);
        foreach ($items as $i => $item) {
            $col = $i % $cols;
            $row = (int) floor($i / $cols);
            $x = $padding + ($col * $cell);
            $y = $padding + ($row * ($cell + $label_h));
            imagerectangle($im, $x, $y, $x + $cell, $y + $cell, $gridc);
            $glyph = isset($item['text']) ? (string) $item['text'] : '';
            if ('' !== $glyph) {
                $box = @imagettfbbox($font_size, 0, $font_path, $glyph);
                $tw = is_array($box) ? abs($box[2] - $box[0]) : $font_size;
                $tx = (int) ($x + (($cell - $tw) / 2));
                $ty = (int) ($y + ($cell * .60));
                @imagettftext($im, $font_size, 0, $tx, $ty, $fgc, $font_path, $glyph);
            } else {
                imagerectangle($im, $x + 22, $y + 18, $x + $cell - 22, $y + $cell - 26, $placeholder);
                imageline($im, $x + 22, $y + 18, $x + $cell - 22, $y + $cell - 26, $placeholder);
            }
            $label = isset($item['label']) ? (string) $item['label'] : '';
            if ($label) {
                // GD bitmap labels are byte-oriented. Preserve the same visible
                // middle-dot separator without exposing its UTF-8 bytes as Â·.
                $gd_label = str_replace("\xC2\xB7", "\xB7", $label);
                imagestring($im, 2, (int) ($x + 6), (int) ($y + $cell + 4), $gd_label, $muted);
            }
        }
    } else {
        $text_left = 'style-text' === $mode ? 8 : $padding;
        $text_right = $padding;
        // Match the Imagick path's right-edge ink-overhang allowance.
        $max_text_width = max(80, $width - $text_left - $text_right - 4);
        if ($fit_single_line) {
            $font_size = ath_specimen_fit_single_line_font_size($font_path, $text, $font_size, $max_text_width, 'gd');
            $lines = array(preg_replace('/\s+/u', ' ', trim((string) $text)));
        } else {
            $lines = ath_specimen_render_split_lines($text, $font_path, $font_size, $max_text_width, 'gd');
        }
        $line_px = max($font_size, $font_size * $line_height);
        $height = max(76, min(2200, (int) ceil(($padding * 2) + (count($lines) * $line_px))));
        $im = imagecreatetruecolor($width, $height);
        $bgc = imagecolorallocate($im, $bg[0], $bg[1], $bg[2]);
        $fgc = imagecolorallocate($im, $fg[0], $fg[1], $fg[2]);
        imagefill($im, 0, 0, $bgc);
        $baseline = $padding + $font_size;
        foreach ($lines as $line) {
            @imagettftext($im, $font_size, 0, $text_left, (int) $baseline, $fgc, $font_path, $line);
            $baseline += $line_px;
        }
    }

    ob_start();
    imagepng($im, null, 7);
    $blob = ob_get_clean();
    imagedestroy($im);
    return $blob;
}

function ath_specimen_server_render_image($font_path, $text, $width, $font_size, $line_height, $text_color, $bg_color, $mode, $glyph_items = array(), $fit_single_line = false) {
    if (class_exists('Imagick') && class_exists('ImagickDraw')) {
        $result = ath_specimen_render_with_imagick($font_path, $text, $width, $font_size, $line_height, $text_color, $bg_color, $mode, $glyph_items, $fit_single_line);
        if (!is_wp_error($result)) return $result;
    }
    return ath_specimen_render_with_gd($font_path, $text, $width, $font_size, $line_height, $text_color, $bg_color, $mode, $glyph_items, $fit_single_line);
}

function ath_specimen_ajax_render_preview() {
    check_ajax_referer('ath_specimen_render_preview', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $token = isset($_POST['font_token']) ? sanitize_text_field(wp_unslash($_POST['font_token'])) : '';
    $post = $post_id ? get_post($post_id) : null;
    if (!$post || 'ath_font' !== $post->post_type || ('publish' !== $post->post_status && !authentype_specimen_can_manage_internal())) {
        wp_send_json_error(array('message' => __('Font preview is not available.', 'authentype-font-specimen')), 404);
    }
    $record = ath_specimen_preview_record($post_id, $token);
    $font_path = !empty($record['file']) ? ath_specimen_local_upload_path($record['file']) : '';
    if (!$font_path) wp_send_json_error(array('message' => __('Preview font could not be resolved.', 'authentype-font-specimen')), 404);

    $text = isset($_POST['text']) ? wp_unslash($_POST['text']) : '';
    $text = trim(wp_strip_all_tags((string) $text));
    if ('' === $text) $text = 'The quick brown fox jumps over the lazy dog';
    $text = function_exists('mb_substr') ? mb_substr($text, 0, 1200, 'UTF-8') : substr($text, 0, 1200);
    $requested_mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'text';
    $mode = in_array($requested_mode, array('text', 'style-text', 'glyph-grid'), true) ? $requested_mode : 'text';
    $width = isset($_POST['width']) ? min(1800, max(280, absint($_POST['width']))) : 1000;
    $font_size = isset($_POST['font_size']) ? min(160, max(12, absint($_POST['font_size']))) : 38;
    $fit_single_line = !empty($_POST['fit_single_line']) && '1' === (string) wp_unslash($_POST['fit_single_line']) && in_array($mode, array('text', 'style-text'), true);
    $line_height = isset($_POST['line_height']) ? (float) wp_unslash($_POST['line_height']) : 1.18;
    $line_height = min(2.2, max(.9, $line_height));
    $text_color = ath_specimen_hex_color(isset($_POST['text_color']) ? wp_unslash($_POST['text_color']) : '#111111', '#111111');
    $bg_color = ath_specimen_hex_color(isset($_POST['bg_color']) ? wp_unslash($_POST['bg_color']) : '#ffffff', '#ffffff');
    $glyph_items = ath_specimen_parse_glyph_items(isset($_POST['glyph_items']) ? wp_unslash($_POST['glyph_items']) : '');

    $stat = @stat($font_path);
    $fingerprint = $font_path . '|' . (!empty($stat['size']) ? $stat['size'] : 0) . '|' . (!empty($stat['mtime']) ? $stat['mtime'] : 0);
    $hash_payload = array($fingerprint, $text, $width, $font_size, $fit_single_line ? 1 : 0, $line_height, $text_color, $bg_color, $mode, $glyph_items);
    if ('glyph-grid' === $mode) {
        array_unshift($hash_payload, ath_specimen_full_glyph_renderer_revision());
    } else {
        array_unshift($hash_payload, ath_specimen_text_renderer_revision());
    }
    $hash = hash('sha256', wp_json_encode($hash_payload));
    $cache_dir = ath_specimen_render_cache_dir($post_id);
    $cache_file = $cache_dir ? trailingslashit($cache_dir) . $hash . '.png' : '';
    $blob = $cache_file && is_file($cache_file) ? file_get_contents($cache_file) : false;
    $cache_hit = false !== $blob && '' !== $blob;

    // Cached pixels are cheap and do not consume a PHP font-rendering slot.
    // Applying the global renderer ceiling before this lookup caused a 20-row
    // archive to exhaust the entire site allowance after only ~30 visits.
    if (false === $blob || '' === $blob) {
        if (!ath_specimen_render_rate_limit_ok($post_id, 'render')) {
            status_header(429);
            header('Retry-After: 60');
            wp_send_json_error(array('message' => __('Too many preview requests. Please try again shortly.', 'authentype-font-specimen')), 429);
        }
    }

    $render_lock = false;
    if (false === $blob || '' === $blob) {
        $render_lock = ath_specimen_render_cache_mutex($hash, true);
        if (!$render_lock) {
            if ($cache_file && is_file($cache_file)) $blob = file_get_contents($cache_file);
            if (false === $blob || '' === $blob) {
                status_header(503);
                header('Retry-After: 2');
                wp_send_json_error(array('message' => __('Preview is being prepared. Please try again.', 'authentype-font-specimen')), 503);
            }
        } else {
            // Another request may have completed while this request waited.
            if ($cache_file && is_file($cache_file)) $blob = file_get_contents($cache_file);
        }
    }

    if ((false === $blob || '' === $blob) && $render_lock) {
        $render_font_path = $font_path;
        $render_glyph_items = $glyph_items;
        $temporary_font = '';

        if ('glyph-grid' === $mode && !empty($glyph_items)) {
            $prepared = ath_specimen_prepare_gid_render_font($font_path, $glyph_items);
            if (is_wp_error($prepared)) {
                ath_specimen_render_cache_mutex($hash, false);
                $render_lock = false;
                wp_send_json_error(array(
                    'message' => $prepared->get_error_message(),
                    'renderer' => 'full-glyph-engine',
                ), 503);
            }
            $render_font_path = $prepared['path'];
            $render_glyph_items = $prepared['items'];
            $temporary_font = $prepared['path'];
        }

        try {
            $blob = ath_specimen_server_render_image($render_font_path, $text, $width, $font_size, $line_height, $text_color, $bg_color, $mode, $render_glyph_items, $fit_single_line);
        } finally {
            if ($temporary_font && is_file($temporary_font)) @wp_delete_file($temporary_font);
        }

        if (is_wp_error($blob)) {
            ath_specimen_render_cache_mutex($hash, false);
            $render_lock = false;
            wp_send_json_error(array(
                'message' => $blob->get_error_message(),
                'renderer' => class_exists('Imagick') ? 'imagick-error' : (function_exists('imagettftext') ? 'gd' : 'unavailable'),
            ), 503);
        }
        if ($cache_file && $blob) @file_put_contents($cache_file, $blob, LOCK_EX);
    }

    if ($render_lock) ath_specimen_render_cache_mutex($hash, false);

    ath_specimen_render_prune_cache($cache_dir);
    ath_specimen_render_prune_global_cache();

    // Return the raster directly instead of Base64-in-JSON. This avoids the
    // ~33% Base64 transfer overhead and still exposes only pixels, never the
    // source OTF/TTF/WOFF bytes.
    nocache_headers();
    header('Content-Type: image/png');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="font-preview.png"');
    header('X-Ath-Preview-Cache: ' . ($cache_hit ? 'HIT' : 'MISS'));
    echo $blob; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary PNG generated server-side.
    wp_die();
}
add_action('wp_ajax_ath_specimen_render_preview', 'ath_specimen_ajax_render_preview');
add_action('wp_ajax_nopriv_ath_specimen_render_preview', 'ath_specimen_ajax_render_preview');

function ath_specimen_ajax_font_metadata() {
    check_ajax_referer('ath_specimen_render_preview', 'nonce');
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id || !ath_specimen_render_rate_limit_ok($post_id, 'metadata')) {
        wp_send_json_error(array('message' => __('Too many preview requests. Please try again shortly.', 'authentype-font-specimen')), 429);
    }
    $token = isset($_POST['font_token']) ? sanitize_text_field(wp_unslash($_POST['font_token'])) : '';
    $record = ath_specimen_preview_record($post_id, $token);
    if (empty($record['file'])) wp_send_json_error(array('message' => __('Invalid font metadata request.', 'authentype-font-specimen')), 400);

    $post = get_post($post_id);
    if (!$post || 'ath_font' !== $post->post_type || ('publish' !== $post->post_status && !authentype_specimen_can_manage_internal())) {
        wp_send_json_error(array('message' => __('Font metadata is not available.', 'authentype-font-specimen')), 404);
    }

    $cache_key = $record['kind'] . '-' . $record['index'];
    $info = function_exists('ath_specimen_get_font_info_cached')
        ? ath_specimen_get_font_info_cached($post_id, $cache_key, $record['file'], true)
        : ath_specimen_get_font_info($record['file']);

    $glyph_total = !empty($info['tech']['glyph_count']) ? (int) $info['tech']['glyph_count'] : 0;
    $glyph_unicode = !empty($info['glyph_codepoints']) && is_array($info['glyph_codepoints']) ? count($info['glyph_codepoints']) : 0;
    $safe = array(
        'features' => !empty($info['features']) ? array_values($info['features']) : array(),
        'ligatures' => !empty($info['ligatures']) ? array_values($info['ligatures']) : array(),
        'tech' => !empty($info['tech']) && is_array($info['tech']) ? $info['tech'] : array(),
        'languages' => !empty($info['languages']) && is_array($info['languages']) ? array_values($info['languages']) : array(),
        'scripts' => !empty($info['scripts']) && is_array($info['scripts']) ? array_values($info['scripts']) : array(),
        'glyph_total' => $glyph_total,
        // secure.7.3 renders every valid GID through a temporary server-only
        // cmap remap, so renderable count is the actual maxp glyph count.
        'glyph_renderable' => $glyph_total,
        'glyph_unicode' => $glyph_unicode,
        'glyph_unencoded' => max(0, $glyph_total - $glyph_unicode),
        'glyph_engine' => 'gid-remap-raster',
    );
    wp_send_json_success($safe);
}
add_action('wp_ajax_ath_specimen_font_metadata', 'ath_specimen_ajax_font_metadata');
add_action('wp_ajax_nopriv_ath_specimen_font_metadata', 'ath_specimen_ajax_font_metadata');


function ath_specimen_ajax_glyph_page() {
    check_ajax_referer('ath_specimen_render_preview', 'nonce');
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!$post_id || !ath_specimen_render_rate_limit_ok($post_id, 'glyph')) {
        wp_send_json_error(array('message' => __('Too many preview requests. Please try again shortly.', 'authentype-font-specimen')), 429);
    }
    $token = isset($_POST['font_token']) ? sanitize_text_field(wp_unslash($_POST['font_token'])) : '';
    $record = ath_specimen_preview_record($post_id, $token);
    if (empty($record['file'])) wp_send_json_error(array('message' => __('Invalid glyph request.', 'authentype-font-specimen')), 400);

    $post = get_post($post_id);
    if (!$post || 'ath_font' !== $post->post_type || ('publish' !== $post->post_status && !authentype_specimen_can_manage_internal())) {
        wp_send_json_error(array('message' => __('Glyph data is not available.', 'authentype-font-specimen')), 404);
    }

    $cache_key = $record['kind'] . '-' . $record['index'];
    $info = function_exists('ath_specimen_get_font_info_cached')
        ? ath_specimen_get_font_info_cached($post_id, $cache_key, $record['file'], true)
        : ath_specimen_get_font_info($record['file']);

    $glyph_total = !empty($info['tech']['glyph_count']) ? max(0, (int) $info['tech']['glyph_count']) : 0;
    $glyph_codepoints = !empty($info['glyph_codepoints']) && is_array($info['glyph_codepoints']) ? $info['glyph_codepoints'] : array();
    $normalized_map = array();
    foreach ($glyph_codepoints as $gid => $cp) {
        $gid = (int) $gid;
        $cp = (int) $cp;
        if ($gid >= 0 && $gid < $glyph_total && $cp >= 0 && $cp <= 0x10ffff) $normalized_map[$gid] = $cp;
    }
    ksort($normalized_map, SORT_NUMERIC);

    $filter = isset($_POST['glyph_filter']) ? sanitize_key(wp_unslash($_POST['glyph_filter'])) : 'all';
    if (!in_array($filter, array('all', 'unicode', 'unencoded'), true)) $filter = 'all';
    $per_page = isset($_POST['per_page']) ? absint($_POST['per_page']) : 60;
    $per_page = min(120, max(24, $per_page));
    $page = isset($_POST['page']) ? absint($_POST['page']) : 1;

    $selected_gids = array();
    if ('unicode' === $filter) {
        $all_gids = array_keys($normalized_map);
        $total = count($all_gids);
        $pages = max(1, (int) ceil(($total ?: 1) / $per_page));
        if ($page > $pages) $page = $pages;
        $offset = max(0, ($page - 1) * $per_page);
        $selected_gids = array_slice($all_gids, $offset, $per_page);
    } elseif ('unencoded' === $filter) {
        $encoded_lookup = array_fill_keys(array_keys($normalized_map), true);
        $total = max(0, $glyph_total - count($normalized_map));
        $pages = max(1, (int) ceil(($total ?: 1) / $per_page));
        if ($page > $pages) $page = $pages;
        $wanted_start = max(0, ($page - 1) * $per_page);
        $seen = 0;
        for ($gid = 0; $gid < $glyph_total && count($selected_gids) < $per_page; $gid++) {
            if (isset($encoded_lookup[$gid])) continue;
            if ($seen++ < $wanted_start) continue;
            $selected_gids[] = $gid;
        }
    } else {
        $total = $glyph_total;
        $pages = max(1, (int) ceil(($total ?: 1) / $per_page));
        if ($page > $pages) $page = $pages;
        $offset = max(0, ($page - 1) * $per_page);
        $end_gid = min($glyph_total, $offset + $per_page);
        for ($gid = $offset; $gid < $end_gid; $gid++) $selected_gids[] = $gid;
    }

    $public_items = array();
    foreach ($selected_gids as $gid) {
        $gid = (int) $gid;
        $label = 'GID ' . $gid;
        $type = isset($normalized_map[$gid]) ? 'unicode' : 'unencoded';
        if (0 === $gid) {
            $label .= ' · .notdef';
        } elseif ('unicode' === $type) {
            $label .= ' · ' . ath_specimen_unicode_label($normalized_map[$gid]);
        }
        $public_items[] = array('gid' => $gid, 'label' => $label, 'type' => $type);
    }
    $offset = max(0, ($page - 1) * $per_page);
    $font_total = $glyph_total;

    wp_send_json_success(array(
        'items' => $public_items,
        'total' => $total,
        'font_total' => $font_total,
        'filter' => $filter,
        'page' => $page,
        'per_page' => $per_page,
        'pages' => $pages,
        'from' => $total ? ($offset + 1) : 0,
        'to' => $total ? min($total, $offset + count($public_items)) : 0,
    ));
}
add_action('wp_ajax_ath_specimen_glyph_page', 'ath_specimen_ajax_glyph_page');
add_action('wp_ajax_nopriv_ath_specimen_glyph_page', 'ath_specimen_ajax_glyph_page');
