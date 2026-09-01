<?php
defined('ABSPATH') || exit;

/**
 * secure.8.3.0 Stability Freeze
 *
 * Operational safety only: health diagnostics, bounded error history, schema
 * bookkeeping, expired-lock inspection, and cross-engine read-only guards.
 * This module never mutates Woo products, variations, prices, downloads,
 * orders, customer permissions, or Athtyp product commerce metadata.
 */

if (!defined('AUTHENTYPE_SPECIMEN_DATA_SCHEMA')) {
    define('AUTHENTYPE_SPECIMEN_DATA_SCHEMA', '8.3.0');
}

function ath_specimen_stability_option_name($suffix) {
    return 'authentype_specimen_stability_' . sanitize_key((string) $suffix);
}

function ath_specimen_stability_sanitize_context($context) {
    $clean = array();
    if (!is_array($context)) return $clean;
    $allowed = array('post_id', 'product_id', 'variation_id', 'action', 'code', 'file', 'line', 'phase', 'schema', 'version');
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $context)) continue;
        $value = $context[$key];
        if (in_array($key, array('post_id', 'product_id', 'variation_id', 'line'), true)) {
            $clean[$key] = absint($value);
        } else {
            $clean[$key] = sanitize_text_field((string) $value);
        }
    }
    return $clean;
}

function ath_specimen_stability_log($level, $code, $message, $context = array()) {
    $level = sanitize_key((string) $level);
    if (!in_array($level, array('info', 'warning', 'error', 'critical'), true)) $level = 'info';
    $code = sanitize_key((string) $code);
    $message = sanitize_text_field(wp_strip_all_tags((string) $message));
    if (!$code || !$message) return false;

    $option = ath_specimen_stability_option_name('events');
    $events = get_option($option, array());
    if (!is_array($events)) $events = array();
    $events[] = array(
        'time' => time(),
        'level' => $level,
        'code' => $code,
        'message' => substr($message, 0, 500),
        'context' => ath_specimen_stability_sanitize_context($context),
    );
    if (count($events) > 60) $events = array_slice($events, -60);
    return update_option($option, $events, false) || maybe_serialize(get_option($option, array())) === maybe_serialize($events);
}

function ath_specimen_stability_record_error($operation, $error, $context = array()) {
    if (!is_wp_error($error)) return;
    $context = is_array($context) ? $context : array();
    $context['code'] = $error->get_error_code();
    $context['action'] = sanitize_key((string) $operation);
    ath_specimen_stability_log('error', 'operation_failed', $error->get_error_message(), $context);
}

function ath_specimen_stability_relative_plugin_file($file) {
    $file = wp_normalize_path((string) $file);
    $root = defined('AUTHENTYPE_SPECIMEN_PATH') ? trailingslashit(wp_normalize_path(AUTHENTYPE_SPECIMEN_PATH)) : '';
    if ($root && 0 === strpos($file, $root)) return ltrim(substr($file, strlen($root)), '/');
    return wp_basename($file);
}

function ath_specimen_stability_register_fatal_capture() {
    register_shutdown_function(function () {
        $error = error_get_last();
        if (!is_array($error) || empty($error['type']) || empty($error['file'])) return;
        if (!in_array((int) $error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR), true)) return;
        $file = wp_normalize_path((string) $error['file']);
        $root = defined('AUTHENTYPE_SPECIMEN_PATH') ? trailingslashit(wp_normalize_path(AUTHENTYPE_SPECIMEN_PATH)) : '';
        if (!$root || 0 !== strpos($file, $root)) return;
        $context = array(
            'file' => ath_specimen_stability_relative_plugin_file($file),
            'line' => !empty($error['line']) ? absint($error['line']) : 0,
            'action' => isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '',
            'post_id' => isset($_REQUEST['post_id']) ? absint($_REQUEST['post_id']) : 0,
            'product_id' => isset($_REQUEST['product_id']) ? absint($_REQUEST['product_id']) : 0,
        );
        ath_specimen_stability_log('critical', 'php_fatal', isset($error['message']) ? (string) $error['message'] : 'Plugin fatal error', $context);
    });
}
ath_specimen_stability_register_fatal_capture();

function ath_specimen_stability_memory_bytes($value) {
    $value = trim((string) $value);
    if ('' === $value || '-1' === $value) return PHP_INT_MAX;
    $last = strtolower(substr($value, -1));
    $number = (float) $value;
    if ('g' === $last) $number *= 1024;
    if (in_array($last, array('g', 'm'), true)) $number *= 1024;
    if (in_array($last, array('g', 'm', 'k'), true)) $number *= 1024;
    return (int) $number;
}

function ath_specimen_stability_build_lock_record($post_id) {
    $post_id = absint($post_id);
    if (!$post_id) return array();
    $record = get_option('ath_pkg_mutex_' . md5((string) $post_id), array());
    return is_array($record) ? $record : array();
}

function ath_specimen_stability_build_busy($post_id) {
    $record = ath_specimen_stability_build_lock_record($post_id);
    return !empty($record['token']) && (!empty($record['expires']) && (int) $record['expires'] > time());
}

function ath_specimen_stability_acquire_post_lock($post_id, $purpose = 'admin_edit', $ttl = 0) {
    $post_id = absint($post_id);
    if (!$post_id) return new WP_Error('ath_stability_post_lock', __('Invalid Athtyp product lock request.', 'authentype-font-specimen'));
    $ttl = max(MINUTE_IN_SECONDS, (int) ($ttl ?: 5 * MINUTE_IN_SECONDS));
    $option = 'ath_pkg_mutex_' . md5((string) $post_id);
    $token = sanitize_key((string) $purpose) . '-' . wp_generate_password(28, false, false);
    $record = array('token' => $token, 'expires' => time() + $ttl, 'purpose' => sanitize_key((string) $purpose));
    if (add_option($option, $record, '', false)) return $token;
    $current = get_option($option, array());
    if (!is_array($current) || empty($current['expires']) || (int) $current['expires'] <= time()) {
        delete_option($option);
        if (add_option($option, $record, '', false)) return $token;
    }
    return new WP_Error('ath_stability_build_busy', __('Another Athtyp asset/commerce edit is already running for this product. Finish it before saving overlapping commerce data.', 'authentype-font-specimen'));
}

function ath_specimen_stability_release_post_lock($post_id, $token) {
    $post_id = absint($post_id);
    $token = sanitize_text_field((string) $token);
    if (!$post_id || !$token) return;
    $option = 'ath_pkg_mutex_' . md5((string) $post_id);
    $current = get_option($option, array());
    if (is_array($current) && !empty($current['token']) && hash_equals((string) $current['token'], $token)) delete_option($option);
}

function ath_specimen_stability_adoption_lock_record($product_id) {
    $product_id = absint($product_id);
    if (!$product_id) return array();
    $record = get_option('ath_adopt_mutex_' . $product_id, array());
    return is_array($record) ? $record : array();
}

function ath_specimen_stability_adoption_busy($product_id) {
    $record = ath_specimen_stability_adoption_lock_record($product_id);
    if (empty($record['time'])) return false;
    return (int) $record['time'] >= time() - 10 * MINUTE_IN_SECONDS;
}

function ath_specimen_stability_woo_lock_option($product_id) {
    $product_id = absint($product_id);
    if (!$product_id) return '';
    $lock_key = 'ath_woo_lock_product_' . md5((string) $product_id);
    return 'ath_woo_mutex_' . md5($lock_key);
}

function ath_specimen_stability_woo_lock_record($product_id) {
    $option = ath_specimen_stability_woo_lock_option($product_id);
    if (!$option) return array();
    $record = get_option($option, array());
    return is_array($record) ? $record : array();
}

function ath_specimen_stability_woo_busy($product_id) {
    $record = ath_specimen_stability_woo_lock_record($product_id);
    return !empty($record['token']) && (empty($record['expires']) || (int) $record['expires'] > time());
}

function ath_specimen_stability_cross_engine_guard($post_id = 0, $product_id = 0, $ignore = array()) {
    $post_id = absint($post_id);
    $product_id = absint($product_id);
    $ignore = array_map('sanitize_key', (array) $ignore);
    if ($post_id && !in_array('build', $ignore, true) && ath_specimen_stability_build_busy($post_id)) {
        return new WP_Error('ath_stability_build_busy', __('Secure Assets build is currently running for this Athtyp product. Finish the build before starting another commerce mutation.', 'authentype-font-specimen'));
    }
    if ($product_id && !in_array('adoption', $ignore, true) && ath_specimen_stability_adoption_busy($product_id)) {
        return new WP_Error('ath_stability_migration_busy', __('A catalog adoption/migration operation is currently running for this Woo product. Finish it before starting another commerce mutation.', 'authentype-font-specimen'));
    }
    if ($product_id && !in_array('woo', $ignore, true) && ath_specimen_stability_woo_busy($product_id)) {
        return new WP_Error('ath_stability_woo_busy', __('Woo Sync or another Woo reconciliation operation is currently running for this product. Finish it before starting another commerce mutation.', 'authentype-font-specimen'));
    }
    return true;
}

function ath_specimen_stability_lock_inventory() {
    global $wpdb;
    $items = array();
    $prefixes = array('ath_pkg_mutex_' => 'build', 'ath_woo_mutex_' => 'woo', 'ath_adopt_mutex_' => 'adoption');
    foreach ($prefixes as $prefix => $type) {
        $like = $wpdb->esc_like($prefix) . '%';
        $names = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 500", $like));
        foreach ((array) $names as $name) {
            $record = get_option($name, array());
            if (!is_array($record)) $record = array();
            $active = false;
            if ('adoption' === $type) {
                $active = !empty($record['time']) && (int) $record['time'] >= time() - 10 * MINUTE_IN_SECONDS;
            } else {
                $active = !empty($record['token']) && (empty($record['expires']) || (int) $record['expires'] > time());
            }
            $items[] = array('name' => $name, 'type' => $type, 'active' => $active, 'record' => $record);
        }
    }
    return $items;
}

function ath_specimen_stability_health_checks() {
    $checks = array();
    $add = function ($key, $label, $status, $detail) use (&$checks) {
        $checks[$key] = array('label' => $label, 'status' => $status, 'detail' => $detail);
    };

    $add('php', __('PHP runtime', 'authentype-font-specimen'), PHP_VERSION_ID >= 70400 ? 'good' : (PHP_VERSION_ID >= 70200 ? 'warning' : 'bad'), sprintf(__('PHP %s. PHP 7.4+ is recommended for this plugin release.', 'authentype-font-specimen'), PHP_VERSION));

    $required_files = array(
        'includes/helpers.php', 'includes/stability.php', 'includes/license-url-routing.php', 'includes/font-reader.php',
        'includes/server-render.php', 'includes/cpt-fonts.php', 'includes/free-downloads.php', 'includes/admin-metaboxes.php',
        'includes/catalog-adoption.php', 'includes/package-builder-v7.php', 'includes/shortcode-specimen.php', 'includes/ajax-cart.php',
        'assets/admin.js', 'assets/specimen.js', 'assets/adoption-admin.js', 'assets/admin.css', 'assets/specimen.css', 'assets/adoption-admin.css',
    );
    $missing_files = array();
    foreach ($required_files as $required_file) {
        $path = defined('AUTHENTYPE_SPECIMEN_PATH') ? AUTHENTYPE_SPECIMEN_PATH . $required_file : '';
        if (!$path || !is_file($path) || !is_readable($path)) $missing_files[] = $required_file;
    }
    $add('files', __('Plugin file integrity', 'authentype-font-specimen'), $missing_files ? 'bad' : 'good', $missing_files ? sprintf(__('Missing/unreadable required files: %s', 'authentype-font-specimen'), implode(', ', array_slice($missing_files, 0, 6))) : __('All required PHP, JavaScript, and CSS modules are present and readable.', 'authentype-font-specimen'));

    $woo = class_exists('WooCommerce') && function_exists('wc_get_product');
    $add('woo', __('WooCommerce', 'authentype-font-specimen'), $woo ? 'good' : 'bad', $woo ? __('WooCommerce APIs are available.', 'authentype-font-specimen') : __('WooCommerce is unavailable; commerce, checkout, catalog migration, and buyer delivery tools cannot run.', 'authentype-font-specimen'));
    $add('zip', __('ZIP support', 'authentype-font-specimen'), class_exists('ZipArchive') ? 'good' : 'bad', class_exists('ZipArchive') ? __('ZipArchive is available.', 'authentype-font-specimen') : __('PHP ZipArchive is missing; secure builds and ZIP inspection cannot run.', 'authentype-font-specimen'));
    $renderer = class_exists('Imagick') || function_exists('imagettftext');
    $add('renderer', __('Preview renderer', 'authentype-font-specimen'), $renderer ? 'good' : 'bad', $renderer ? (class_exists('Imagick') ? __('Imagick is available.', 'authentype-font-specimen') : __('GD/FreeType fallback is available.', 'authentype-font-specimen')) : __('Neither Imagick nor GD/FreeType font rendering is available.', 'authentype-font-specimen'));

    $uploads = wp_get_upload_dir();
    $upload_ok = empty($uploads['error']) && !empty($uploads['basedir']) && is_dir($uploads['basedir']) && is_writable($uploads['basedir']);
    $add('uploads', __('Uploads storage', 'authentype-font-specimen'), $upload_ok ? 'good' : 'bad', $upload_ok ? __('WordPress uploads directory is writable.', 'authentype-font-specimen') : __('WordPress uploads directory is unavailable or not writable.', 'authentype-font-specimen'));

    $temp_dir = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
    $temp_ok = $temp_dir && is_dir($temp_dir) && is_writable($temp_dir);
    $add('temp', __('Temporary storage', 'authentype-font-specimen'), $temp_ok ? 'good' : 'bad', $temp_ok ? __('PHP/WordPress temporary directory is writable for preview fonts and temporary admin ZIPs.', 'authentype-font-specimen') : __('Temporary directory is unavailable or not writable.', 'authentype-font-specimen'));

    $upload_raw = (string) ini_get('upload_max_filesize');
    $post_raw = (string) ini_get('post_max_size');
    $upload_bytes = ath_specimen_stability_memory_bytes($upload_raw);
    $post_bytes = ath_specimen_stability_memory_bytes($post_raw);
    $upload_status = $upload_bytes >= 64 * 1024 * 1024 && $post_bytes >= $upload_bytes ? 'good' : ($upload_bytes >= 16 * 1024 * 1024 && $post_bytes >= $upload_bytes ? 'warning' : 'bad');
    $add('upload_limits', __('Upload request limits', 'authentype-font-specimen'), $upload_status, sprintf(__('upload_max_filesize = %1$s; post_max_size = %2$s. Large master font ZIPs need enough headroom.', 'authentype-font-specimen'), $upload_raw ?: __('unknown', 'authentype-font-specimen'), $post_raw ?: __('unknown', 'authentype-font-specimen')));

    $input_vars = (int) ini_get('max_input_vars');
    $input_status = 0 === $input_vars || $input_vars >= 3000 ? 'good' : ($input_vars >= 1000 ? 'warning' : 'bad');
    $add('input_vars', __('PHP input variable limit', 'authentype-font-specimen'), $input_status, sprintf(__('max_input_vars = %s. Large Style × License admin matrices are safer with 3000+.', 'authentype-font-specimen'), 0 === $input_vars ? __('unlimited/unknown', 'authentype-font-specimen') : (string) $input_vars));

    $memory_raw = ini_get('memory_limit');
    $memory = ath_specimen_stability_memory_bytes($memory_raw);
    $memory_status = $memory === PHP_INT_MAX || $memory >= 256 * 1024 * 1024 ? 'good' : ($memory >= 128 * 1024 * 1024 ? 'warning' : 'bad');
    $add('memory', __('PHP memory limit', 'authentype-font-specimen'), $memory_status, sprintf(__('memory_limit = %s. 256M+ is preferred for large font families and legacy catalog work.', 'authentype-font-specimen'), $memory_raw ?: __('unknown', 'authentype-font-specimen')));

    $exec = (int) ini_get('max_execution_time');
    $exec_status = 0 === $exec || $exec >= 60 ? 'good' : ($exec >= 30 ? 'warning' : 'bad');
    $add('execution', __('PHP execution limit', 'authentype-font-specimen'), $exec_status, sprintf(__('max_execution_time = %s seconds. Bulk operations are AJAX-batched, but 60+ seconds per request is preferred.', 'authentype-font-specimen'), 0 === $exec ? __('unlimited', 'authentype-font-specimen') : (string) $exec));

    $schema = (string) get_option(ath_specimen_stability_option_name('data_schema'), '');
    $schema_ok = defined('AUTHENTYPE_SPECIMEN_DATA_SCHEMA') && $schema === AUTHENTYPE_SPECIMEN_DATA_SCHEMA;
    $add('schema', __('Plugin data schema', 'authentype-font-specimen'), $schema_ok ? 'good' : 'warning', sprintf(__('Stored schema: %1$s; expected: %2$s. The Stability bootstrap only records schema state and performs no automatic product migration.', 'authentype-font-specimen'), $schema ?: __('not recorded', 'authentype-font-specimen'), defined('AUTHENTYPE_SPECIMEN_DATA_SCHEMA') ? AUTHENTYPE_SPECIMEN_DATA_SCHEMA : '—'));

    if ($woo) {
        $method = (string) get_option('woocommerce_file_download_method', 'force');
        $method_status = in_array($method, array('force', 'xsendfile'), true) ? 'good' : 'warning';
        $add('woo_download', __('Woo download method', 'authentype-font-specimen'), $method_status, sprintf(__('WooCommerce file download method: %s. Force Downloads or X-Accel/X-Sendfile is preferred for protected font files.', 'authentype-font-specimen'), $method ?: 'force'));
    }

    $locks = ath_specimen_stability_lock_inventory();
    $expired = 0; $active = 0;
    foreach ($locks as $lock) { if (!empty($lock['active'])) $active++; else $expired++; }
    $add('locks', __('Operation locks', 'authentype-font-specimen'), $expired ? 'warning' : 'good', sprintf(__('%1$d active lock(s), %2$d expired lock(s). Expired locks can be cleaned from this page; active locks are never removed automatically.', 'authentype-font-specimen'), $active, $expired));

    return $checks;
}

function ath_specimen_stability_maybe_upgrade() {
    if (!is_admin() || !current_user_can('manage_options')) return;
    $expected = defined('AUTHENTYPE_SPECIMEN_DATA_SCHEMA') ? AUTHENTYPE_SPECIMEN_DATA_SCHEMA : '8.3.0';
    $stored = (string) get_option(ath_specimen_stability_option_name('data_schema'), '');
    $version = defined('AUTHENTYPE_SPECIMEN_VERSION') ? AUTHENTYPE_SPECIMEN_VERSION : '';
    if ($stored === $expected && (string) get_option(ath_specimen_stability_option_name('plugin_version'), '') === $version) return;

    $mutex = ath_specimen_stability_option_name('upgrade_mutex');
    $lock = array('time' => time(), 'version' => $version);
    if (!add_option($mutex, $lock, '', false)) {
        $current = get_option($mutex, array());
        if (is_array($current) && !empty($current['time']) && (int) $current['time'] < time() - 5 * MINUTE_IN_SECONDS) {
            delete_option($mutex);
            if (!add_option($mutex, $lock, '', false)) return;
        } else return;
    }
    try {
        update_option(ath_specimen_stability_option_name('previous_schema'), $stored, false);
        update_option(ath_specimen_stability_option_name('data_schema'), $expected, false);
        update_option(ath_specimen_stability_option_name('plugin_version'), $version, false);
        update_option(ath_specimen_stability_option_name('upgraded_at'), time(), false);
        ath_specimen_stability_log('info', 'schema_recorded', __('Stability schema/version bookkeeping completed. No Woo or Athtyp product data was migrated automatically.', 'authentype-font-specimen'), array('schema' => $expected, 'version' => $version));
    } finally {
        delete_option($mutex);
    }
}
add_action('admin_init', 'ath_specimen_stability_maybe_upgrade', 2);

function ath_specimen_stability_on_activate() {
    update_option(ath_specimen_stability_option_name('data_schema'), defined('AUTHENTYPE_SPECIMEN_DATA_SCHEMA') ? AUTHENTYPE_SPECIMEN_DATA_SCHEMA : '8.3.0', false);
    update_option(ath_specimen_stability_option_name('plugin_version'), defined('AUTHENTYPE_SPECIMEN_VERSION') ? AUTHENTYPE_SPECIMEN_VERSION : '', false);
    update_option(ath_specimen_stability_option_name('activated_at'), time(), false);
    ath_specimen_stability_log('info', 'plugin_activated', __('Authentype Font Specimen Commerce activated with Stability Freeze safeguards.', 'authentype-font-specimen'), array('version' => defined('AUTHENTYPE_SPECIMEN_VERSION') ? AUTHENTYPE_SPECIMEN_VERSION : ''));
}

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=ath_font',
        __('Stability & Diagnostics', 'authentype-font-specimen'),
        __('Stability', 'authentype-font-specimen'),
        'manage_options',
        'ath-stability',
        'ath_specimen_stability_render_page'
    );
});

add_action('admin_post_ath_specimen_stability_cleanup_locks', function () {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.', 'authentype-font-specimen'), '', array('response' => 403));
    check_admin_referer('ath_specimen_stability_cleanup_locks');
    $removed = 0;
    foreach (ath_specimen_stability_lock_inventory() as $lock) {
        if (!empty($lock['active'])) continue;
        if (delete_option($lock['name'])) $removed++;
    }
    ath_specimen_stability_log('info', 'expired_locks_cleaned', sprintf(__('%d expired plugin operation lock(s) were removed.', 'authentype-font-specimen'), $removed));
    wp_safe_redirect(add_query_arg(array('post_type' => 'ath_font', 'page' => 'ath-stability', 'locks_cleaned' => $removed), admin_url('edit.php')));
    exit;
});

add_action('admin_post_ath_specimen_stability_clear_log', function () {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.', 'authentype-font-specimen'), '', array('response' => 403));
    check_admin_referer('ath_specimen_stability_clear_log');
    delete_option(ath_specimen_stability_option_name('events'));
    wp_safe_redirect(add_query_arg(array('post_type' => 'ath_font', 'page' => 'ath-stability'), admin_url('edit.php')));
    exit;
});

add_action('admin_post_ath_specimen_stability_report', function () {
    if (!current_user_can('manage_options')) wp_die(esc_html__('Permission denied.', 'authentype-font-specimen'), '', array('response' => 403));
    check_admin_referer('ath_specimen_stability_report');
    $checks = ath_specimen_stability_health_checks();
    $events = get_option(ath_specimen_stability_option_name('events'), array());
    if (!is_array($events)) $events = array();
    $lines = array();
    $lines[] = 'Authentype Font Specimen Commerce — Stability Diagnostic Report';
    $lines[] = 'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC';
    $lines[] = 'Plugin: ' . (defined('AUTHENTYPE_SPECIMEN_VERSION') ? AUTHENTYPE_SPECIMEN_VERSION : 'unknown');
    $lines[] = 'Schema: ' . (defined('AUTHENTYPE_SPECIMEN_DATA_SCHEMA') ? AUTHENTYPE_SPECIMEN_DATA_SCHEMA : 'unknown');
    $lines[] = '';
    $lines[] = 'HEALTH CHECKS';
    foreach ($checks as $check) $lines[] = strtoupper($check['status']) . ' | ' . $check['label'] . ' | ' . wp_strip_all_tags($check['detail']);
    $lines[] = '';
    $lines[] = 'RECENT PLUGIN EVENTS';
    foreach (array_reverse(array_slice($events, -30)) as $event) {
        $ctx = !empty($event['context']) && is_array($event['context']) ? wp_json_encode($event['context']) : '{}';
        $lines[] = gmdate('Y-m-d H:i:s', (int) ($event['time'] ?? 0)) . ' | ' . strtoupper((string) ($event['level'] ?? 'info')) . ' | ' . (string) ($event['code'] ?? '') . ' | ' . (string) ($event['message'] ?? '') . ' | ' . $ctx;
    }
    while (ob_get_level()) @ob_end_clean();
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="athtyp-stability-report-' . gmdate('Ymd-His') . '.txt"');
    echo implode("\n", $lines); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text admin download.
    exit;
});

function ath_specimen_stability_render_page() {
    if (!current_user_can('manage_options')) return;
    $checks = ath_specimen_stability_health_checks();
    $events = get_option(ath_specimen_stability_option_name('events'), array());
    if (!is_array($events)) $events = array();
    $status_labels = array('good' => __('Good', 'authentype-font-specimen'), 'warning' => __('Warning', 'authentype-font-specimen'), 'bad' => __('Critical', 'authentype-font-specimen'));
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Stability & Diagnostics', 'authentype-font-specimen'); ?></h1>
        <p><?php esc_html_e('secure.8.3.0 is a stability freeze: this page checks the runtime and plugin locks. It does not scan or modify Woo products, Athtyp pricing, variations, downloads, orders, or customer permissions.', 'authentype-font-specimen'); ?></p>
        <?php if (isset($_GET['locks_cleaned'])) : ?><div class="notice notice-success"><p><?php echo esc_html(sprintf(__('%d expired lock(s) cleaned.', 'authentype-font-specimen'), absint($_GET['locks_cleaned']))); ?></p></div><?php endif; ?>
        <table class="widefat striped" style="max-width:1100px">
            <thead><tr><th><?php esc_html_e('Check', 'authentype-font-specimen'); ?></th><th><?php esc_html_e('Status', 'authentype-font-specimen'); ?></th><th><?php esc_html_e('Details', 'authentype-font-specimen'); ?></th></tr></thead>
            <tbody>
            <?php foreach ($checks as $check) : ?>
                <tr><td><strong><?php echo esc_html($check['label']); ?></strong></td><td><?php echo esc_html($status_labels[$check['status']] ?? $check['status']); ?></td><td><?php echo esc_html($check['detail']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p>
            <a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ath_specimen_stability_report'), 'ath_specimen_stability_report')); ?>"><?php esc_html_e('Download Diagnostic Report', 'authentype-font-specimen'); ?></a>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ath_specimen_stability_cleanup_locks'), 'ath_specimen_stability_cleanup_locks')); ?>"><?php esc_html_e('Clean Expired Locks', 'authentype-font-specimen'); ?></a>
            <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ath_specimen_stability_clear_log'), 'ath_specimen_stability_clear_log')); ?>"><?php esc_html_e('Clear Diagnostic Log', 'authentype-font-specimen'); ?></a>
        </p>
        <h2><?php esc_html_e('Recent Plugin Events', 'authentype-font-specimen'); ?></h2>
        <table class="widefat striped" style="max-width:1100px">
            <thead><tr><th><?php esc_html_e('Time', 'authentype-font-specimen'); ?></th><th><?php esc_html_e('Level', 'authentype-font-specimen'); ?></th><th><?php esc_html_e('Event', 'authentype-font-specimen'); ?></th><th><?php esc_html_e('Message', 'authentype-font-specimen'); ?></th></tr></thead>
            <tbody>
            <?php foreach (array_reverse(array_slice($events, -30)) as $event) : ?>
                <tr><td><?php echo esc_html(wp_date('Y-m-d H:i:s', (int) ($event['time'] ?? 0))); ?></td><td><?php echo esc_html(strtoupper((string) ($event['level'] ?? 'info'))); ?></td><td><code><?php echo esc_html((string) ($event['code'] ?? '')); ?></code></td><td><?php echo esc_html((string) ($event['message'] ?? '')); ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$events) : ?><tr><td colspan="4"><?php esc_html_e('No plugin errors have been recorded.', 'authentype-font-specimen'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}
