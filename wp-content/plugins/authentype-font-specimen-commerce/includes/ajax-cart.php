<?php
defined('ABSPATH') || exit;

function ath_specimen_find_variation($product_id, $style_attr, $license_attr, $style_value, $license_value) {
    if (!function_exists('wc_get_product')) return array();

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) return array();

    $style_attr = ath_specimen_normalize_attr_key($style_attr);
    $license_attr = ath_specimen_normalize_attr_key($license_attr);
    $style_value = ath_specimen_slug($style_value);
    $license_value = ath_specimen_slug($license_value);

    // Fast path for large families: ask WooCommerce's product data store for
    // the matching variation instead of materializing every available variation.
    if (class_exists('WC_Data_Store')) {
        try {
            $data_store = WC_Data_Store::load('product');
            if ($data_store && method_exists($data_store, 'find_matching_product_variation')) {
                $match = array(
                    $style_attr => ath_specimen_prepare_attribute_value(str_replace('attribute_', '', $style_attr), $style_value),
                    $license_attr => ath_specimen_prepare_attribute_value(str_replace('attribute_', '', $license_attr), $license_value),
                );
                $variation_id = (int) $data_store->find_matching_product_variation($product, $match);
                if ($variation_id) {
                    $variation_product = wc_get_product($variation_id);
                    $attributes = array();
                    if ($variation_product) {
                        foreach ($variation_product->get_attributes() as $name => $value) {
                            $attributes[ath_specimen_normalize_attr_key($name)] = ath_specimen_prepare_attribute_value($name, $value);
                        }
                    }
                    return array('id' => $variation_id, 'attributes' => $attributes);
                }
            }
        } catch (Throwable $e) {
            // Fall through to the compatibility path below.
        }
    }

    $raw_style_attr = str_replace('attribute_', '', $style_attr);
    $raw_license_attr = str_replace('attribute_', '', $license_attr);
    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation) continue;
        $raw_attributes = $variation->get_attributes();
        $variation_style = !empty($raw_attributes[$raw_style_attr]) ? ath_specimen_slug($raw_attributes[$raw_style_attr]) : '';
        $variation_license = !empty($raw_attributes[$raw_license_attr]) ? ath_specimen_slug($raw_attributes[$raw_license_attr]) : '';
        if ($variation_style === $style_value && $variation_license === $license_value) {
            $attributes = array();
            foreach ($raw_attributes as $name => $value) {
                $attributes[ath_specimen_normalize_attr_key($name)] = ath_specimen_prepare_attribute_value($name, $value);
            }
            return array('id' => (int) $variation_id, 'attributes' => $attributes);
        }
    }

    return array();
}

function ath_specimen_cart_font_post_for_product($product_id) {
    static $cache = array();
    $product_id = absint($product_id);
    if (!$product_id) return 0;
    if (array_key_exists($product_id, $cache)) return (int) $cache[$product_id];

    $font_posts = get_posts(array(
        'post_type' => 'ath_font',
        'post_status' => array('publish'),
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => array(
            array(
                'key' => '_ath_linked_product',
                'value' => $product_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ),
        ),
    ));

    $cache[$product_id] = !empty($font_posts[0]) ? (int) $font_posts[0] : 0;
    return (int) $cache[$product_id];
}

function ath_specimen_cart_allowed_styles($font_post_id) {
    static $cache = array();
    $font_post_id = absint($font_post_id);
    if (isset($cache[$font_post_id])) return $cache[$font_post_id];
    $rows = ath_specimen_get_meta($font_post_id, '_ath_font_styles', array());
    $allowed = array();

    if (!is_array($rows)) return $cache[$font_post_id] = $allowed;

    foreach ($rows as $row) {
        if (empty($row['style_name']) && empty($row['style_variation_value'])) continue;
        $value = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : ath_specimen_slug($row['style_name']);
        if ($value) $allowed[$value] = true;
    }

    $cache[$font_post_id] = $allowed;
    return $allowed;
}

function ath_specimen_cart_allowed_licenses($font_post_id) {
    static $cache = array();
    $font_post_id = absint($font_post_id);
    if (isset($cache[$font_post_id])) return $cache[$font_post_id];
    $rows = ath_specimen_get_meta($font_post_id, '_ath_license_options', array());
    $allowed = array();

    if (!is_array($rows)) return $cache[$font_post_id] = $allowed;

    foreach ($rows as $row) {
        if (empty($row['license_variation_value'])) continue;
        $value = ath_specimen_slug($row['license_variation_value']);
        if ($value) $allowed[$value] = true;
    }

    $cache[$font_post_id] = $allowed;
    return $allowed;
}

function ath_specimen_complete_variation_attributes($product, $variation_attributes, $style_attr, $license_attr, $style_value, $license_value) {
    if (!$product || !$product->is_type('variable')) return $variation_attributes;

    $completed = is_array($variation_attributes) ? $variation_attributes : array();
    $defaults = $product->get_default_attributes();
    $style_attr = ath_specimen_normalize_attr_key($style_attr);
    $license_attr = ath_specimen_normalize_attr_key($license_attr);

    foreach ($product->get_variation_attributes() as $attribute_name => $options) {
        $attribute_key = ath_specimen_normalize_attr_key($attribute_name);
        $attribute_name = str_replace('attribute_', '', $attribute_key);

        if (!array_key_exists($attribute_key, $completed)) {
            $completed[$attribute_key] = '';
        }

        if ('' !== $completed[$attribute_key]) {
            continue;
        }

        if ($attribute_key === $style_attr) {
            $completed[$attribute_key] = ath_specimen_prepare_attribute_value($attribute_name, $style_value);
            continue;
        }

        if ($attribute_key === $license_attr) {
            $completed[$attribute_key] = ath_specimen_prepare_attribute_value($attribute_name, $license_value);
            continue;
        }

        if (!empty($defaults[$attribute_name])) {
            $completed[$attribute_key] = ath_specimen_prepare_attribute_value($attribute_name, $defaults[$attribute_name]);
            continue;
        }

        if (is_array($options) && !empty($options)) {
            $completed[$attribute_key] = ath_specimen_prepare_attribute_value($attribute_name, reset($options));
        }
    }

    return $completed;
}

function ath_specimen_prepare_attribute_value($attribute_name, $value) {
    $value = is_scalar($value) ? (string) $value : '';
    if ('' === $value) return '';

    if (function_exists('taxonomy_exists') && taxonomy_exists($attribute_name)) {
        return sanitize_title($value);
    }

    return function_exists('wc_clean') ? wc_clean($value) : sanitize_text_field($value);
}

function ath_specimen_repair_cart_item_variation($cart_item) {
    if (empty($cart_item['product_id']) || empty($cart_item['variation_id']) || empty($cart_item['variation']) || !function_exists('wc_get_product')) {
        return $cart_item;
    }

    $product = wc_get_product((int) $cart_item['product_id']);
    $variation_product = wc_get_product((int) $cart_item['variation_id']);
    if (!$product || !$product->is_type('variable') || !$variation_product) {
        return $cart_item;
    }

    $cart_item['variation'] = ath_specimen_complete_variation_attributes(
        $product,
        $cart_item['variation'],
        '',
        '',
        '',
        ''
    );

    foreach ($variation_product->get_attributes() as $attribute_name => $attribute_value) {
        if ('' === $attribute_value) continue;

        $attribute_key = ath_specimen_normalize_attr_key($attribute_name);
        if (empty($cart_item['variation'][$attribute_key])) {
            $cart_item['variation'][$attribute_key] = ath_specimen_prepare_attribute_value($attribute_name, $attribute_value);
        }
    }

    return $cart_item;
}

add_filter('woocommerce_get_cart_item_from_session', function ($cart_item) {
    return ath_specimen_repair_cart_item_variation($cart_item);
}, 20);

function ath_specimen_clear_product_option_notices() {
    if (!function_exists('wc_get_notices') || !function_exists('wc_set_notices')) {
        return;
    }

    $notices = wc_get_notices();
    if (empty($notices['error']) || !is_array($notices['error'])) {
        return;
    }

    $notices['error'] = array_values(array_filter($notices['error'], function ($notice) {
        $message = is_array($notice) && isset($notice['notice']) ? $notice['notice'] : $notice;
        $message = wp_strip_all_tags((string) $message);

        return false === strpos($message, 'Please choose product options by visiting');
    }));

    wc_set_notices($notices);
}

add_action('template_redirect', function () {
    if ((function_exists('is_cart') && is_cart()) || (function_exists('is_checkout') && is_checkout())) {
        ath_specimen_clear_product_option_notices();
    }
}, 5);

function ath_specimen_commerce_is_synced($font_post_id) {
    static $cache = array();
    $font_post_id = absint($font_post_id);
    if (!$font_post_id || !function_exists('ath_specimen_woo_sync_signature')) return false;
    if (array_key_exists($font_post_id, $cache)) return (bool) $cache[$font_post_id];
    $style_attr = (string) ath_specimen_get_meta($font_post_id, '_ath_style_attribute', 'pa_style');
    $license_attr = (string) ath_specimen_get_meta($font_post_id, '_ath_license_attribute', 'pa_license');
    $current = ath_specimen_woo_sync_signature($font_post_id, $style_attr, $license_attr);
    $synced = (string) ath_specimen_get_meta($font_post_id, '_ath_woo_synced_signature', '');
    $cache[$font_post_id] = (bool) ($synced && $current && hash_equals($synced, $current));
    return $cache[$font_post_id];
}

/**
 * Storefront safety must distinguish an active Woo mutation from a stale
 * whole-product receipt. The exact sync signature remains useful for admin
 * diagnostics, but it is intentionally broader than one checkout selection
 * and can become stale after harmless metadata normalization. Runtime checkout
 * validates the requested variation's price + downloads directly below.
 */
function ath_specimen_commerce_sync_in_progress($font_post_id) {
    static $cache = array();
    $font_post_id = absint($font_post_id);
    if (!$font_post_id) return false;
    if (array_key_exists($font_post_id, $cache)) return (bool) $cache[$font_post_id];

    if (function_exists('ath_specimen_stability_build_busy') && ath_specimen_stability_build_busy($font_post_id)) {
        return $cache[$font_post_id] = true;
    }

    $product_id = absint(ath_specimen_get_meta($font_post_id, '_ath_linked_product', 0));
    if (!$product_id || !function_exists('ath_specimen_woo_sync_lock_key') || !function_exists('ath_specimen_woo_sync_read_lock')) {
        return $cache[$font_post_id] = false;
    }

    $lock_key = ath_specimen_woo_sync_lock_key($font_post_id, $product_id);
    $cache[$font_post_id] = (bool) ath_specimen_woo_sync_read_lock($lock_key);
    return $cache[$font_post_id];
}

function ath_specimen_variation_attribute_value($variation, $attribute) {
    if (!$variation) return '';
    $wanted = str_replace('attribute_', '', ath_specimen_normalize_attr_key($attribute));
    foreach ((array) $variation->get_attributes() as $name => $value) {
        $key = str_replace('attribute_', '', ath_specimen_normalize_attr_key($name));
        if ($key === $wanted) return ath_specimen_slug($value);
    }
    return '';
}

function ath_specimen_variation_license_value($variation, $license_attr) {
    return ath_specimen_variation_attribute_value($variation, $license_attr);
}

function ath_specimen_contact_license_values($font_post_id) {
    static $cache = array();
    $font_post_id = absint($font_post_id);
    if (isset($cache[$font_post_id])) return $cache[$font_post_id];
    $values = array();
    foreach ((array) ath_specimen_get_meta($font_post_id, '_ath_license_options', array()) as $row) {
        $value = !empty($row['license_variation_value']) ? ath_specimen_slug($row['license_variation_value']) : '';
        if ($value && function_exists('ath_specimen_license_is_contact_sales') && ath_specimen_license_is_contact_sales($row)) $values[$value] = true;
    }
    $cache[$font_post_id] = $values;
    return $values;
}


/**
 * Compare one Woo variation with the current Athtyp pricing + delivery authority.
 * This catches manual Woo edits after a successful sync without scanning the
 * entire variation catalog on every storefront request.
 */
function ath_specimen_variation_mirror_status($font_post_id, $variation, $style = '', $license = '') {
    static $cache = array();
    $font_post_id = absint($font_post_id);
    if (!$font_post_id || !$variation || !method_exists($variation, 'get_id')) {
        return new WP_Error('ath_mirror_variation', __('WooCommerce variation is unavailable.', 'authentype-font-specimen'));
    }

    $style_attr = (string) ath_specimen_get_meta($font_post_id, '_ath_style_attribute', 'pa_style');
    $license_attr = (string) ath_specimen_get_meta($font_post_id, '_ath_license_attribute', 'pa_license');
    $style = $style ? ath_specimen_slug($style) : ath_specimen_variation_attribute_value($variation, $style_attr);
    $license = $license ? ath_specimen_slug($license) : ath_specimen_variation_attribute_value($variation, $license_attr);
    $cache_key = $font_post_id . '|' . (int) $variation->get_id() . '|' . $style . '|' . $license;
    if (array_key_exists($cache_key, $cache)) return $cache[$cache_key];

    if (!$style || !$license) {
        return $cache[$cache_key] = new WP_Error('ath_mirror_attributes', __('WooCommerce variation attributes do not match the Athtyp Style × License model.', 'authentype-font-specimen'));
    }

    $matrix = ath_specimen_get_meta($font_post_id, '_ath_price_matrix', array());
    $matrix = is_array($matrix) ? $matrix : array();
    $expected_price = function_exists('ath_specimen_matrix_price_values')
        ? ath_specimen_matrix_price_values($matrix, $style, $license)
        : array('regular' => '', 'sale' => '', 'active' => '');

    if ('' === (string) $expected_price['active']) {
        return $cache[$cache_key] = new WP_Error('ath_mirror_unpriced', __('This Style × License combination has no active Athtyp price.', 'authentype-font-specimen'));
    }

    $actual_regular = (string) $variation->get_regular_price('edit');
    $actual_sale = (string) $variation->get_sale_price('edit');
    $actual_active = (string) $variation->get_price('edit');
    if ($actual_regular !== (string) $expected_price['regular'] || $actual_sale !== (string) $expected_price['sale'] || $actual_active !== (string) $expected_price['active']) {
        return $cache[$cache_key] = new WP_Error(
            'ath_mirror_price',
            __('WooCommerce pricing no longer matches the Athtyp Price Matrix. Sync the linked product again.', 'authentype-font-specimen'),
            array(
                'expected' => $expected_price,
                'actual' => array('regular' => $actual_regular, 'sale' => $actual_sale, 'active' => $actual_active),
            )
        );
    }

    $rows = ath_specimen_get_meta($font_post_id, '_ath_product_downloads', array());
    $rows = is_array($rows) ? $rows : array();
    $expected_downloads = function_exists('ath_specimen_build_wc_downloads') ? ath_specimen_build_wc_downloads($rows, $style, $license) : array();
    if (empty($expected_downloads)) {
        return $cache[$cache_key] = new WP_Error('ath_mirror_delivery_missing', __('This Style × License combination has no current secure delivery mapping.', 'authentype-font-specimen'));
    }

    $actual_downloads = method_exists($variation, 'get_downloads') ? $variation->get_downloads('edit') : array();
    $expected_signature = function_exists('ath_specimen_wc_download_signature') ? ath_specimen_wc_download_signature($expected_downloads) : array();
    $actual_signature = function_exists('ath_specimen_wc_download_signature') ? ath_specimen_wc_download_signature($actual_downloads) : array();
    if ($expected_signature !== $actual_signature || !$variation->get_downloadable('edit')) {
        return $cache[$cache_key] = new WP_Error(
            'ath_mirror_delivery',
            __('WooCommerce downloads no longer match the Athtyp secure delivery mapping. Sync the linked product again.', 'authentype-font-specimen')
        );
    }

    return $cache[$cache_key] = true;
}

// Make the variation itself non-purchasable when Athtyp and Woo are out of
// sync. Store API / block-cart requests still consult product purchasability,
// so this closes paths that may not use the classic add-to-cart validation hook.
add_filter('woocommerce_variation_is_purchasable', function ($purchasable, $variation) {
    if (!$purchasable || !$variation || !method_exists($variation, 'get_parent_id')) return $purchasable;
    $font_post_id = ath_specimen_cart_font_post_for_product((int) $variation->get_parent_id());
    if (!$font_post_id) return $purchasable;
    // Do not reject a valid variation only because the broad admin sync receipt
    // is stale. Block only while Woo is actively mutating, then verify this
    // variation's exact Athtyp price/download mirror below.
    if (ath_specimen_commerce_sync_in_progress($font_post_id)) return false;
    if ('1' === (string) $variation->get_meta('_ath_disabled_by_sync', true)) return false;
    if ('1' === (string) $variation->get_meta('_ath_delivery_missing', true)) return false;

    $style_attr = (string) ath_specimen_get_meta($font_post_id, '_ath_style_attribute', 'pa_style');
    $license_attr = (string) ath_specimen_get_meta($font_post_id, '_ath_license_attribute', 'pa_license');
    $style = ath_specimen_variation_attribute_value($variation, $style_attr);
    $license = ath_specimen_variation_attribute_value($variation, $license_attr);
    $allowed_styles = ath_specimen_cart_allowed_styles($font_post_id);
    $allowed_licenses = ath_specimen_cart_allowed_licenses($font_post_id);
    if (!$style || !$license || empty($allowed_styles[$style]) || empty($allowed_licenses[$license])) return false;
    $contact = ath_specimen_contact_license_values($font_post_id);
    if (!empty($contact[$license])) return false;
    $mirror = ath_specimen_variation_mirror_status($font_post_id, $variation, $style, $license);
    if (is_wp_error($mirror)) return false;
    return $purchasable;
}, 20, 2);

// Harden every Woo add-to-cart path, including native Woo requests that bypass
// the Athtyp modal. This does not alter the UI or normal synced purchase flow.
add_filter('woocommerce_add_to_cart_validation', function ($passed, $product_id, $quantity, $variation_id = 0, $variations = array()) {
    if (!$passed) return false;
    $font_post_id = ath_specimen_cart_font_post_for_product($product_id);
    if (!$font_post_id) return $passed;

    if (ath_specimen_commerce_sync_in_progress($font_post_id)) {
        if (function_exists('wc_add_notice')) wc_add_notice(__('Pricing is being updated. Please try again shortly.', 'authentype-font-specimen'), 'error');
        return false;
    }

    if ($variation_id) {
        $variation = wc_get_product((int) $variation_id);
        if (!$variation || '1' === (string) $variation->get_meta('_ath_disabled_by_sync', true) || '1' === (string) $variation->get_meta('_ath_delivery_missing', true)) {
            if (function_exists('wc_add_notice')) wc_add_notice(__('This license option is no longer available.', 'authentype-font-specimen'), 'error');
            return false;
        }
        $style_attr = (string) ath_specimen_get_meta($font_post_id, '_ath_style_attribute', 'pa_style');
        $license_attr = (string) ath_specimen_get_meta($font_post_id, '_ath_license_attribute', 'pa_license');
        $style = ath_specimen_variation_attribute_value($variation, $style_attr);
        $license = ath_specimen_variation_license_value($variation, $license_attr);
        $contact = ath_specimen_contact_license_values($font_post_id);
        if ($license && !empty($contact[$license])) {
            if (function_exists('wc_add_notice')) wc_add_notice(__('This license requires a custom quote and cannot be purchased directly.', 'authentype-font-specimen'), 'error');
            return false;
        }
        $mirror = ath_specimen_variation_mirror_status($font_post_id, $variation, $style, $license);
        if (is_wp_error($mirror)) {
            if (function_exists('wc_add_notice')) wc_add_notice(__('This font option changed in WooCommerce after the last Athtyp sync. Please sync the linked product again before purchase.', 'authentype-font-specimen'), 'error');
            return false;
        }
    }
    return $passed;
}, 20, 5);

// A price can change while a shopper already has the old variation in cart.
// Block checkout until Woo has received the plugin-authoritative pricing rather
// than silently charging a stale amount.
add_action('woocommerce_check_cart_items', function () {
    if (!function_exists('WC') || !WC()->cart || !function_exists('wc_add_notice')) return;
    $sync_notices = array();
    $drift_notices = array();
    foreach ((array) WC()->cart->get_cart() as $item) {
        $product_id = !empty($item['product_id']) ? absint($item['product_id']) : 0;
        if (!$product_id) continue;
        $font_post_id = ath_specimen_cart_font_post_for_product($product_id);
        if (!$font_post_id) continue;
        if (ath_specimen_commerce_sync_in_progress($font_post_id)) {
            if (empty($sync_notices[$product_id])) {
                wc_add_notice(__('A font product in your cart is currently receiving a pricing update. Please try checkout again shortly.', 'authentype-font-specimen'), 'error');
                $sync_notices[$product_id] = true;
            }
            continue;
        }

        $variation_id = !empty($item['variation_id']) ? absint($item['variation_id']) : 0;
        $variation = $variation_id ? wc_get_product($variation_id) : null;
        if (!$variation || !empty($drift_notices[$product_id])) continue;
        $mirror = ath_specimen_variation_mirror_status($font_post_id, $variation);
        if (is_wp_error($mirror)) {
            wc_add_notice(__('A font option in your cart no longer matches the Athtyp price/download mirror. Please sync the linked WooCommerce product before checkout.', 'authentype-font-specimen'), 'error');
            $drift_notices[$product_id] = true;
        }
    }
});

function ath_specimen_ajax_add_to_cart() {
    check_ajax_referer('ath_specimen_cart', 'nonce');

    if (!function_exists('WC') || !WC()->cart) {
        wp_send_json_error(array('message' => __('WooCommerce cart is unavailable.', 'authentype-font-specimen')), 400);
    }

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $styles = array();
    foreach (isset($_POST['styles']) && is_array($_POST['styles']) ? wp_unslash($_POST['styles']) : array() as $raw_style) {
        if (!is_scalar($raw_style)) continue;
        $value = ath_specimen_slug((string) $raw_style);
        if ($value) $styles[$value] = $value;
    }
    $styles = array_values($styles);
    $licenses = array();
    foreach (isset($_POST['licenses']) && is_array($_POST['licenses']) ? wp_unslash($_POST['licenses']) : array() as $raw_license) {
        if (!is_scalar($raw_license)) continue;
        $value = ath_specimen_slug((string) $raw_license);
        if ($value) $licenses[$value] = $value;
    }
    $licenses = array_values($licenses);
    // Backward compatibility with secure.7.3.8 clients.
    if (empty($licenses) && isset($_POST['license'])) {
        $legacy_license = ath_specimen_slug(wp_unslash($_POST['license']));
        if ($legacy_license) $licenses[] = $legacy_license;
    }

    if (!$product_id || empty($licenses) || empty($styles)) {
        wp_send_json_error(array('message' => __('Choose at least one style and one purchasable license.', 'authentype-font-specimen')), 400);
    }
    $max_styles = max(1, (int) apply_filters('ath_specimen_cart_max_styles', 50, $product_id));
    $max_licenses = max(1, (int) apply_filters('ath_specimen_cart_max_licenses', 10, $product_id));
    $max_combinations = max(1, (int) apply_filters('ath_specimen_cart_max_combinations', 100, $product_id));
    if (count($licenses) > $max_licenses || count($styles) > $max_styles || (count($licenses) * count($styles)) > $max_combinations) {
        wp_send_json_error(array(
            'message' => sprintf(
                __('Choose no more than %1$d styles, %2$d licenses, or %3$d total combinations at once.', 'authentype-font-specimen'),
                $max_styles,
                $max_licenses,
                $max_combinations
            ),
        ), 400);
    }

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        wp_send_json_error(array('message' => __('Select a variable WooCommerce product.', 'authentype-font-specimen')), 400);
    }

    $font_post_id = ath_specimen_cart_font_post_for_product($product_id);
    if (!$font_post_id) {
        wp_send_json_error(array('message' => __('This product is not linked to a valid Athtyp font record.', 'authentype-font-specimen')), 403);
    }
    // A stale whole-product signature must not hide a valid, already mirrored
    // Woo variation from the license popup. Active batch sync is still blocked;
    // every requested Style × License is then verified against authoritative
    // Athtyp regular/sale/active price and secure download mapping before the
    // cart is changed.
    if (ath_specimen_commerce_sync_in_progress($font_post_id)) {
        wp_send_json_error(array('message' => __('Pricing is being updated. Please try again shortly.', 'authentype-font-specimen')), 409);
    }

    $style_attr = ath_specimen_get_meta($font_post_id, '_ath_style_attribute', 'pa_style');
    $license_attr = ath_specimen_get_meta($font_post_id, '_ath_license_attribute', 'pa_license');
    $allowed_styles = ath_specimen_cart_allowed_styles($font_post_id);
    $allowed_licenses = ath_specimen_cart_allowed_licenses($font_post_id);

    $contact_licenses = array();
    foreach ((array) ath_specimen_get_meta($font_post_id, '_ath_license_options', array()) as $row) {
        $value = !empty($row['license_variation_value']) ? ath_specimen_slug($row['license_variation_value']) : '';
        if ($value && function_exists('ath_specimen_license_is_contact_sales') && ath_specimen_license_is_contact_sales($row)) {
            $contact_licenses[$value] = true;
        }
    }

    foreach ($licenses as $license) {
        if (empty($allowed_licenses[$license])) {
            wp_send_json_error(array('message' => __('Invalid license selection.', 'authentype-font-specimen')), 403);
        }
        if (!empty($contact_licenses[$license])) {
            wp_send_json_error(array('message' => __('A contact-sales license cannot be added directly to cart.', 'authentype-font-specimen')), 403);
        }
    }
    foreach ($styles as $style) {
        if (empty($allowed_styles[$style])) {
            wp_send_json_error(array('message' => __('Invalid style selection.', 'authentype-font-specimen')), 403);
        }
    }

    $style_attr_key = ath_specimen_normalize_attr_key($style_attr);
    $license_attr_key = ath_specimen_normalize_attr_key($license_attr);
    $queue = array();
    $missing = array();

    // Validate every requested combination before mutating the cart. This keeps
    // multi-license checkout atomic when a Woo variation is missing.
    foreach ($licenses as $license) {
        foreach ($styles as $style) {
            $matched_variation = ath_specimen_find_variation($product_id, $style_attr_key, $license_attr_key, $style, $license);
            $variation_id = !empty($matched_variation['id']) ? (int) $matched_variation['id'] : 0;
            if (!$variation_id) {
                $missing[] = array('style' => $style, 'license' => $license);
                continue;
            }
            $variation_product = wc_get_product($variation_id);
            if (!$variation_product) {
                $missing[] = array('style' => $style, 'license' => $license, 'reason' => 'variation_missing');
                continue;
            }
            $mirror = ath_specimen_variation_mirror_status($font_post_id, $variation_product, $style, $license);
            if (is_wp_error($mirror)) {
                $mirror_code = $mirror->get_error_code();
                $reason = 'mirror_drift';
                if ('ath_mirror_price' === $mirror_code || 'ath_mirror_unpriced' === $mirror_code) $reason = 'price_mirror_drift';
                if ('ath_mirror_delivery' === $mirror_code || 'ath_mirror_delivery_missing' === $mirror_code) $reason = 'delivery_mirror_drift';
                $missing[] = array('style' => $style, 'license' => $license, 'reason' => $reason);
                continue;
            }
            $active_price = (string) $variation_product->get_price('edit');
            if ('' === $active_price) {
                $missing[] = array('style' => $style, 'license' => $license, 'reason' => 'active_price_missing');
                continue;
            }
            if (!$variation_product->is_purchasable()) {
                $reason = 'not_purchasable';
                if ('publish' !== (string) $variation_product->get_status('edit')) $reason = 'variation_not_published';
                elseif ('1' === (string) $variation_product->get_meta('_ath_delivery_missing', true)) $reason = 'delivery_missing';
                elseif ('1' === (string) $variation_product->get_meta('_ath_disabled_by_sync', true)) $reason = 'disabled_by_sync';
                $missing[] = array('style' => $style, 'license' => $license, 'reason' => $reason);
                continue;
            }
            $variation = ath_specimen_complete_variation_attributes(
                $product,
                !empty($matched_variation['attributes']) ? $matched_variation['attributes'] : array(),
                $style_attr_key,
                $license_attr_key,
                $style,
                $license
            );
            $queue[] = array(
                'style' => $style,
                'license' => $license,
                'variation_id' => $variation_id,
                'variation' => $variation,
            );
        }
    }

    if (!empty($missing) || empty($queue)) {
        $reasons = array_values(array_unique(array_filter(array_map(function ($row) {
            return !empty($row['reason']) ? (string) $row['reason'] : '';
        }, $missing))));
        $message = __('One or more selected style/license combinations are not available. Nothing was added to cart.', 'authentype-font-specimen');
        if (in_array('price_mirror_drift', $reasons, true)) {
            $message = __('WooCommerce pricing no longer matches the Athtyp Price Matrix. Sync the linked WooCommerce product again.', 'authentype-font-specimen');
        } elseif (in_array('delivery_mirror_drift', $reasons, true)) {
            $message = __('WooCommerce downloads no longer match the Athtyp secure delivery mapping. Sync the linked WooCommerce product again.', 'authentype-font-specimen');
        } elseif (in_array('mirror_drift', $reasons, true)) {
            $message = __('WooCommerce no longer matches the Athtyp commerce mirror. Sync the linked WooCommerce product again.', 'authentype-font-specimen');
        } elseif (in_array('active_price_missing', $reasons, true)) {
            $message = __('WooCommerce price mirror is incomplete for this option. Please sync the linked WooCommerce product again.', 'authentype-font-specimen');
        } elseif (in_array('delivery_missing', $reasons, true)) {
            $message = __('The selected option has no current downloadable delivery file. Please rebuild secure assets and sync WooCommerce again.', 'authentype-font-specimen');
        } elseif (in_array('variation_not_published', $reasons, true)) {
            $message = __('The selected WooCommerce variation is not published. Please review and sync the linked WooCommerce product.', 'authentype-font-specimen');
        }
        wp_send_json_error(array(
            'message' => $message,
            'missing' => $missing,
        ), 404);
    }

    ath_specimen_clear_product_option_notices();
    $cart_before = array();
    foreach ((array) WC()->cart->get_cart() as $existing_key => $existing_item) {
        $cart_before[$existing_key] = isset($existing_item['quantity']) ? (int) $existing_item['quantity'] : 0;
    }
    $added = array();
    foreach ($queue as $item) {
        $cart_key = WC()->cart->add_to_cart($product_id, 1, $item['variation_id'], $item['variation']);
        ath_specimen_clear_product_option_notices();
        if (!$cart_key) {
            // Restore the exact pre-request cart state. If a selected variation
            // was already in cart, add_to_cart may have increased its quantity;
            // removing it outright would destroy the customer's existing item.
            foreach ((array) WC()->cart->get_cart() as $current_key => $current_item) {
                if (array_key_exists($current_key, $cart_before)) {
                    WC()->cart->set_quantity($current_key, $cart_before[$current_key], false);
                } else {
                    WC()->cart->remove_cart_item($current_key);
                }
            }
            WC()->cart->calculate_totals();
            ath_specimen_clear_product_option_notices();
            wp_send_json_error(array('message' => __('Could not add all selected font items. The cart was left unchanged.', 'authentype-font-specimen')), 409);
        }
        $added[] = array('style' => $item['style'], 'license' => $item['license'], 'variation_id' => $item['variation_id']);
    }

    ath_specimen_clear_product_option_notices();
    $count = count($added);
    wp_send_json_success(array(
        'message' => sprintf(_n('%d font item was added to cart.', '%d font items were added to cart.', $count, 'authentype-font-specimen'), $count),
        'added' => $added,
        'cart_url' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
        'product_url' => get_permalink($product_id),
    ));
}

add_action('wp_ajax_ath_specimen_add_to_cart', 'ath_specimen_ajax_add_to_cart');
add_action('wp_ajax_nopriv_ath_specimen_add_to_cart', 'ath_specimen_ajax_add_to_cart');
?>
