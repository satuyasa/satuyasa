<?php
/*
Plugin Name: Authentype Font Specimen Commerce
Description: Standalone font specimen, style selector, license modal, and WooCommerce variation add-to-cart for Authentype.
Version: 1.0.7-secure.8.4.4-preview-reliability
Requires at least: 6.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Author: Authentype Studio
Text Domain: authentype-font-specimen
*/

defined('ABSPATH') || exit;

define('AUTHENTYPE_SPECIMEN_VERSION', '1.0.7-secure.8.4.4-preview-reliability');
define('AUTHENTYPE_SPECIMEN_DATA_SCHEMA', '8.3.0');
define('AUTHENTYPE_SPECIMEN_PATH', plugin_dir_path(__FILE__));
define('AUTHENTYPE_SPECIMEN_URL', plugin_dir_url(__FILE__));

// Stability Freeze bootstrap: never run a partially installed operational
// module set. A missing/corrupt PHP file puts the plugin into a non-commerce
// degraded mode with an admin notice instead of continuing into unpredictable
// partial hooks. Existing Woo/Athtyp data is never changed by this check.
$authentype_specimen_core_modules = array(
    'includes/helpers.php',
    'includes/stability.php',
);
$authentype_specimen_operation_modules = array(
    'includes/license-url-routing.php',
    'includes/font-reader.php',
    'includes/server-render.php',
    'includes/cpt-fonts.php',
    'includes/free-downloads.php',
    'includes/admin-metaboxes.php',
    'includes/catalog-adoption.php',
    'includes/package-builder-v7.php',
    'includes/shortcode-specimen.php',
    'includes/ajax-cart.php',
);
$authentype_specimen_missing_modules = array();
foreach ($authentype_specimen_core_modules as $authentype_specimen_module) {
    $authentype_specimen_module_path = AUTHENTYPE_SPECIMEN_PATH . $authentype_specimen_module;
    if (!is_file($authentype_specimen_module_path) || !is_readable($authentype_specimen_module_path)) {
        $authentype_specimen_missing_modules[] = $authentype_specimen_module;
        continue;
    }
    require_once $authentype_specimen_module_path;
}
foreach ($authentype_specimen_operation_modules as $authentype_specimen_module) {
    $authentype_specimen_module_path = AUTHENTYPE_SPECIMEN_PATH . $authentype_specimen_module;
    if (!is_file($authentype_specimen_module_path) || !is_readable($authentype_specimen_module_path)) {
        $authentype_specimen_missing_modules[] = $authentype_specimen_module;
    }
}
if (!$authentype_specimen_missing_modules && count($authentype_specimen_core_modules) === 2 && function_exists('ath_specimen_stability_cross_engine_guard')) {
    foreach ($authentype_specimen_operation_modules as $authentype_specimen_module) {
        require_once AUTHENTYPE_SPECIMEN_PATH . $authentype_specimen_module;
    }
} else {
    if (!defined('AUTHENTYPE_SPECIMEN_DEGRADED')) define('AUTHENTYPE_SPECIMEN_DEGRADED', true);
    $authentype_specimen_missing_modules = array_values(array_unique($authentype_specimen_missing_modules));
    if (function_exists('ath_specimen_stability_log')) {
        ath_specimen_stability_log('critical', 'plugin_files_missing', __('One or more required plugin PHP files are missing or unreadable. Operational modules were not loaded.', 'authentype-font-specimen'));
    }
    add_action('admin_notices', function () use ($authentype_specimen_missing_modules) {
        if (!current_user_can('manage_options')) return;
        echo '<div class="notice notice-error"><p><strong>' . esc_html__('Authentype Font Specimen Commerce is in safe degraded mode.', 'authentype-font-specimen') . '</strong> ';
        echo esc_html__('Required plugin files are missing or unreadable. Reinstall the complete plugin ZIP before using Build, Pricing, Woo Sync, Checkout, or Catalog tools.', 'authentype-font-specimen');
        if ($authentype_specimen_missing_modules) echo ' <code>' . esc_html(implode(', ', $authentype_specimen_missing_modules)) . '</code>';
        echo '</p></div>';
    });
}
unset($authentype_specimen_core_modules, $authentype_specimen_operation_modules, $authentype_specimen_module, $authentype_specimen_module_path);

function authentype_specimen_activate() {
    if (function_exists('ath_specimen_stability_on_activate')) {
        ath_specimen_stability_on_activate();
    }
    if (function_exists('authentype_specimen_register_post_types')) {
        authentype_specimen_register_post_types();
    }
    flush_rewrite_rules();
    update_option('authentype_specimen_rewrite_version', AUTHENTYPE_SPECIMEN_VERSION);
}
register_activation_hook(__FILE__, 'authentype_specimen_activate');

register_deactivation_hook(__FILE__, 'flush_rewrite_rules');

add_action('admin_init', function () {
    if (!current_user_can('manage_options')) return;
    if (get_option('authentype_specimen_rewrite_version') === AUTHENTYPE_SPECIMEN_VERSION) return;

    flush_rewrite_rules();
    update_option('authentype_specimen_rewrite_version', AUTHENTYPE_SPECIMEN_VERSION);
});

function authentype_specimen_can_manage_internal() {
    return current_user_can(apply_filters('authentype_specimen_internal_capability', 'manage_options'));
}

function authentype_specimen_can_upload_builder_files() {
    return is_admin() && current_user_can('upload_files') && authentype_specimen_can_manage_internal();
}

/** Make the hard commerce dependency visible before an administrator builds data. */
add_action('admin_notices', function () {
    if (!current_user_can('activate_plugins') || class_exists('WooCommerce')) return;
    echo '<div class="notice notice-error"><p><strong>' . esc_html__('Authentype requires WooCommerce.', 'authentype-font-specimen') . '</strong> ';
    echo esc_html__('Activate WooCommerce before building assets, pricing, variations, checkout, or delivery.', 'authentype-font-specimen');
    echo '</p></div>';
});

/**
 * Font MIME types used by secure shared assets. WooCommerce validates every
 * downloadable file against its allowlist, so these must be available during
 * both admin sync and customer-side product hydration/download handling.
 */
function authentype_specimen_font_download_mimes() {
    $php_7_ttf_mime = PHP_VERSION_ID >= 70300 ? 'application/font-sfnt' : 'application/x-font-ttf';

    return array(
        'otf'   => 'application/vnd.ms-opentype',
        'ttf'   => PHP_VERSION_ID >= 70400 ? 'font/sfnt' : $php_7_ttf_mime,
        'woff'  => PHP_VERSION_ID >= 80112 ? 'font/woff' : 'application/font-woff',
        'woff2' => PHP_VERSION_ID >= 80112 ? 'font/woff2' : 'application/font-woff2',
    );
}

add_filter('woocommerce_downloadable_file_allowed_mime_types', function ($mimes) {
    return array_merge((array) $mimes, authentype_specimen_font_download_mimes());
});

add_filter('upload_mimes', function ($mimes) {
    if (authentype_specimen_can_upload_builder_files()) {
        $mimes['csv'] = 'text/csv';
        $mimes['zip'] = 'application/zip';
        foreach (authentype_specimen_font_download_mimes() as $ext => $mime) {
            $mimes[$ext] = $mime;
        }
    }
    return $mimes;
});

add_filter('wp_check_filetype_and_ext', function ($data, $file, $filename, $mimes, $real_mime) {
    if (!authentype_specimen_can_upload_builder_files()) {
        return $data;
    }

    $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
    if ('zip' === $ext) {
        $zip_mimes = array(
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
            'application/octet-stream',
            'multipart/x-zip',
        );

        if (empty($real_mime) || in_array($real_mime, $zip_mimes, true)) {
            $data['ext'] = 'zip';
            $data['type'] = 'application/zip';
            $data['proper_filename'] = false;
        }
    }

    if ('csv' === $ext) {
        $csv_mimes = array(
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'application/octet-stream',
        );

        if (empty($real_mime) || in_array($real_mime, $csv_mimes, true)) {
            $data['ext'] = 'csv';
            $data['type'] = 'text/csv';
            $data['proper_filename'] = false;
        }
    }

    return $data;
}, 10, 5);

add_action('wp_enqueue_scripts', function () {
    wp_register_style(
        'authentype-font-specimen',
        AUTHENTYPE_SPECIMEN_URL . 'assets/specimen.css',
        array(),
        AUTHENTYPE_SPECIMEN_VERSION
    );

    wp_register_script(
        'authentype-font-specimen',
        AUTHENTYPE_SPECIMEN_URL . 'assets/specimen.js',
        array(),
        AUTHENTYPE_SPECIMEN_VERSION,
        true
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (!authentype_specimen_can_manage_internal()) {
        return;
    }

    if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->post_type, array('ath_font', 'ath_free_download'), true)) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'authentype-font-specimen-admin',
        AUTHENTYPE_SPECIMEN_URL . 'assets/admin.js',
        array(),
        AUTHENTYPE_SPECIMEN_VERSION,
        true
    );
    wp_localize_script('authentype-font-specimen-admin', 'AthSpecimenAdmin', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ath_specimen_admin'),
        'i18n' => array(
            'selectProduct' => __('Select a linked product first.', 'authentype-font-specimen'),
            'synced' => __('Synced from WooCommerce variations.', 'authentype-font-specimen'),
            'failed' => __('Could not sync variations.', 'authentype-font-specimen'),
        ),
    ));
    wp_enqueue_style(
        'authentype-font-specimen-admin',
        AUTHENTYPE_SPECIMEN_URL . 'assets/admin.css',
        array(),
        AUTHENTYPE_SPECIMEN_VERSION
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (!authentype_specimen_can_manage_internal()) return;
    if ('ath_font_page_ath-catalog-adoption' !== $hook) return;

    wp_enqueue_style(
        'authentype-font-specimen-adoption',
        AUTHENTYPE_SPECIMEN_URL . 'assets/adoption-admin.css',
        array(),
        AUTHENTYPE_SPECIMEN_VERSION
    );
    wp_enqueue_script(
        'authentype-font-specimen-adoption',
        AUTHENTYPE_SPECIMEN_URL . 'assets/adoption-admin.js',
        array(),
        AUTHENTYPE_SPECIMEN_VERSION,
        true
    );
    wp_localize_script('authentype-font-specimen-adoption', 'AthSpecimenAdoption', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ath_specimen_adoption'),
        'i18n' => array(
            'adopting' => __('Adopting…', 'authentype-font-specimen'),
            'adopted' => __('Already Adopted', 'authentype-font-specimen'),
            'openFont' => __('Open Athtyp', 'authentype-font-specimen'),
            'failed' => __('Adoption failed.', 'authentype-font-specimen'),
            'paused' => __('Paused. Select remaining products and continue when ready.', 'authentype-font-specimen'),
            'restoreConfirm' => __('Restore the captured pre-adoption Woo state? Legacy variations will be restored and Athtyp-created variations will be disabled, not deleted.', 'authentype-font-specimen'),
            'restoring' => __('Restoring Woo snapshot…', 'authentype-font-specimen'),
            'progress' => __('Adopting %c of %t…', 'authentype-font-specimen'),
            'complete' => __('Batch complete: %d adopted, %f failed.', 'authentype-font-specimen'),
            'loadingCatalog' => __('Loading Woo catalog IDs…', 'authentype-font-specimen'),
            'scanProgress' => __('Scanning legacy product %c of %t…', 'authentype-font-specimen'),
            'scanComplete' => __('Scan complete: %r ready, %v review, %a already adopted.', 'authentype-font-specimen'),
            'scanPaused' => __('Scan paused. Ready products found so far remain available.', 'authentype-font-specimen'),
            'scanFailed' => __('Catalog scan failed.', 'authentype-font-specimen'),
            'noCatalogProducts' => __('No Woo products match this catalog search.', 'authentype-font-specimen'),
            'bulkReadyComplete' => __('Bulk legacy adoption complete: %d adopted, %f moved to review.', 'authentype-font-specimen'),
            'readinessLoading' => __('Loading adopted Woo product IDs…', 'authentype-font-specimen'),
            'readinessProgress' => __('Auditing commerce readiness %c of %t…', 'authentype-font-specimen'),
            'readinessComplete' => __('Commerce audit complete: %s shop ready, %y need sync, %p need pricing, %m missing delivery, %r review.', 'authentype-font-specimen'),
            'readinessPaused' => __('Commerce readiness audit paused. Results found so far are preserved.', 'authentype-font-specimen'),
            'readinessFailed' => __('Commerce readiness audit failed.', 'authentype-font-specimen'),
            'noAdoptedProducts' => __('No linked Athtyp/Woo products were found to audit.', 'authentype-font-specimen'),
            'hydrationLoading' => __('Loading linked Woo product IDs for legacy delivery preview…', 'authentype-font-specimen'),
            'hydrationPreviewProgress' => __('Previewing legacy delivery %c of %t…', 'authentype-font-specimen'),
            'hydrationPreviewComplete' => __('Hydration preview complete: %e eligible products, %p missing pairs, %f Woo files; %b blocked, %s non-target.', 'authentype-font-specimen'),
            'hydrationProgress' => __('Hydrating legacy delivery %c of %t…', 'authentype-font-specimen'),
            'hydrationComplete' => __('Legacy delivery hydration complete: %d products hydrated, %f failed. Run Commerce Readiness Audit again before any Woo Sync.', 'authentype-font-specimen'),
            'hydrationPaused' => __('Legacy delivery hydration paused. Remaining preview candidates are preserved.', 'authentype-font-specimen'),
            'hydrationFailed' => __('Legacy delivery hydration failed.', 'authentype-font-specimen'),
            'hydratedRow' => __('Legacy Delivery Hydrated — Re-audit Required — %f Woo file(s) copied into %p missing pair(s). Safety snapshot: %s.', 'authentype-font-specimen'),
            'hydrationPreviewRequestFailed' => __('Preview request failed: %s', 'authentype-font-specimen'),
            'pricingHydrationLoading' => __('Loading linked Woo product IDs for legacy pricing preview…', 'authentype-font-specimen'),
            'pricingHydrationPreviewProgress' => __('Previewing legacy pricing %c of %t…', 'authentype-font-specimen'),
            'pricingHydrationPreviewComplete' => __('Pricing preview complete: %e eligible products, %p empty price cells, %l sale cells; %b blocked, %s non-target.', 'authentype-font-specimen'),
            'pricingHydrationProgress' => __('Hydrating legacy pricing %c of %t…', 'authentype-font-specimen'),
            'pricingHydrationComplete' => __('Legacy pricing hydration complete: %d products hydrated, %f failed. Run Commerce Readiness Audit again before any Woo Sync.', 'authentype-font-specimen'),
            'pricingHydrationPaused' => __('Legacy pricing hydration paused. Remaining preview candidates are preserved.', 'authentype-font-specimen'),
            'pricingHydrationFailed' => __('Legacy pricing hydration failed.', 'authentype-font-specimen'),
            'pricingHydratedRow' => __('Legacy Pricing Hydrated — Re-audit Required — %p empty price cell(s) copied from Woo. Safety snapshot: %s.', 'authentype-font-specimen'),
            'pricingHydrationPreviewRequestFailed' => __('Pricing preview request failed: %s', 'authentype-font-specimen'),
            'wooReconcileLoading' => __('Loading linked Woo product IDs for reconciliation preview…', 'authentype-font-specimen'),
            'wooReconcilePreviewProgress' => __('Previewing stale Woo pairs %c of %t…', 'authentype-font-specimen'),
            'wooReconcilePreviewComplete' => __('Woo reconciliation preview complete: %e eligible products, %p variation actions, %r pair remaps, %u price updates; %b blocked, %s non-target.', 'authentype-font-specimen'),
            'wooReconcileProgress' => __('Reconciling legacy Woo variations %c of %t…', 'authentype-font-specimen'),
            'wooReconcileComplete' => __('Legacy Woo reconciliation complete: %d products reconciled, %f failed. Run Commerce Readiness Audit again.', 'authentype-font-specimen'),
            'wooReconcilePaused' => __('Legacy Woo reconciliation paused. Remaining preview candidates are preserved.', 'authentype-font-specimen'),
            'wooReconcileFailed' => __('Legacy Woo reconciliation failed.', 'authentype-font-specimen'),
            'wooReconcileConfirm' => __('Reconcile only the previewed safe Woo variations now? Existing variation IDs and all download IDs/names/files will be preserved; only Style/License attributes and Athtyp-authoritative prices may change.', 'authentype-font-specimen'),
            'wooReconciledRow' => __('Legacy Woo Reconciled — Re-audit Required — %p existing variation(s), %r pair remap(s), %u price update(s). Downloads preserved. Safety snapshot: %s.', 'authentype-font-specimen'),
            'wooReconcilePreviewRequestFailed' => __('Woo reconciliation preview request failed: %s', 'authentype-font-specimen'),
        ),
    ));
});

?>
