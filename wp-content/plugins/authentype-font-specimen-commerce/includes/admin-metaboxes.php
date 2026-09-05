<?php
defined('ABSPATH') || exit;

function ath_specimen_product_options($selected = 0) {
    $products = get_posts(array(
        'post_type' => 'product',
        'post_status' => array('publish', 'draft', 'private'),
        'posts_per_page' => 200,
        'orderby' => 'title',
        'order' => 'ASC',
    ));

    // Large-catalog safety: an Athtyp record adopted from product #201+ must
    // keep showing its linked Woo product even when it falls outside this
    // lightweight legacy selector. The Catalog Adoption screen is the scalable
    // search/pagination path for large stores.
    $selected = absint($selected);
    $listed = array_map(function ($product) { return (int) $product->ID; }, $products);
    if ($selected && !in_array($selected, $listed, true)) {
        $selected_post = get_post($selected);
        if ($selected_post && 'product' === $selected_post->post_type) {
            array_unshift($products, $selected_post);
        }
    }

    echo '<option value="0">' . esc_html__('Select product', 'authentype-font-specimen') . '</option>';
    foreach ($products as $product) {
        echo '<option value="' . esc_attr($product->ID) . '" ' . selected($selected, (int) $product->ID, false) . '>' . esc_html($product->post_title) . '</option>';
    }
}

function ath_specimen_style_row($index, $row = array()) {
    $row = wp_parse_args($row, array(
        'style_name' => '',
        'font_file' => '',
        'font_weight' => 400,
        'font_style' => 'normal',
        'style_variation_value' => '',
        'default_selected' => '',
        'is_package' => '',
    ));
    ?>
    <tr class="ath-admin-row">
        <td class="ath-row-order">
            <button type="button" class="button ath-move-row ath-move-up" aria-label="<?php esc_attr_e('Move up', 'authentype-font-specimen'); ?>">↑</button>
            <button type="button" class="button ath-move-row ath-move-down" aria-label="<?php esc_attr_e('Move down', 'authentype-font-specimen'); ?>">↓</button>
        </td>
        <td><input type="text" name="ath_font_styles[<?php echo esc_attr($index); ?>][style_name]" value="<?php echo esc_attr($row['style_name']); ?>" placeholder="Regular"></td>
        <td>
            <input type="url" class="ath-font-url" name="ath_font_styles[<?php echo esc_attr($index); ?>][font_file]" value="<?php echo esc_attr($row['font_file']); ?>" placeholder="https://.../font.woff">
            <button type="button" class="button ath-upload-font"><?php esc_html_e('Upload', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button ath-detect-style"><?php esc_html_e('Detect', 'authentype-font-specimen'); ?></button>
        </td>
        <td><input type="number" name="ath_font_styles[<?php echo esc_attr($index); ?>][font_weight]" value="<?php echo esc_attr($row['font_weight']); ?>" min="1" max="1000"></td>
        <td>
            <select name="ath_font_styles[<?php echo esc_attr($index); ?>][font_style]">
                <option value="normal" <?php selected($row['font_style'], 'normal'); ?>>Normal</option>
                <option value="italic" <?php selected($row['font_style'], 'italic'); ?>>Italic</option>
                <option value="oblique" <?php selected($row['font_style'], 'oblique'); ?>>Oblique</option>
            </select>
        </td>
        <td><input type="text" name="ath_font_styles[<?php echo esc_attr($index); ?>][style_variation_value]" value="<?php echo esc_attr($row['style_variation_value']); ?>" placeholder="regular"></td>
        <td><input type="checkbox" name="ath_font_styles[<?php echo esc_attr($index); ?>][default_selected]" value="1" <?php checked(!empty($row['default_selected'])); ?>></td>
        <td><input type="checkbox" name="ath_font_styles[<?php echo esc_attr($index); ?>][is_package]" value="1" <?php checked(!empty($row['is_package'])); ?>></td>
        <td><button type="button" class="button ath-remove-row"><?php esc_html_e('Remove', 'authentype-font-specimen'); ?></button></td>
    </tr>
    <?php
}

function ath_specimen_license_row($index, $row = array()) {
    $row = wp_parse_args($row, array(
        'license_label' => '',
        'license_variation_value' => '',
        'license_description' => '',
        'license_group' => '',
        'license_featured' => 0,
        'license_checkout_type' => '',
        'license_icon' => '',
    ));
    $group = !empty($row['license_group']) && in_array($row['license_group'], ath_specimen_license_group_options(), true) ? $row['license_group'] : '';
    ?>
    <tr class="ath-admin-row">
        <td class="ath-row-order">
            <button type="button" class="button ath-move-row ath-move-up" aria-label="<?php esc_attr_e('Move up', 'authentype-font-specimen'); ?>">↑</button>
            <button type="button" class="button ath-move-row ath-move-down" aria-label="<?php esc_attr_e('Move down', 'authentype-font-specimen'); ?>">↓</button>
        </td>
        <td><input type="text" name="ath_license_options[<?php echo esc_attr($index); ?>][license_label]" value="<?php echo esc_attr($row['license_label']); ?>" placeholder="Desktop"></td>
        <td><input type="text" name="ath_license_options[<?php echo esc_attr($index); ?>][license_variation_value]" value="<?php echo esc_attr($row['license_variation_value']); ?>" placeholder="desktop"></td>
        <td>
            <?php $icon = !empty($row['license_icon']) && isset(ath_specimen_license_icon_options()[$row['license_icon']]) ? $row['license_icon'] : ''; ?>
            <select class="ath-license-icon-select" name="ath_license_options[<?php echo esc_attr($index); ?>][license_icon]" aria-label="<?php esc_attr_e('License icon', 'authentype-font-specimen'); ?>">
                <option value="" <?php selected($icon, ''); ?>><?php esc_html_e('Auto', 'authentype-font-specimen'); ?></option>
                <?php foreach (ath_specimen_license_icon_options() as $icon_key => $icon_label) : ?>
                    <option value="<?php echo esc_attr($icon_key); ?>" <?php selected($icon, $icon_key); ?>><?php echo esc_html($icon_label); ?></option>
                <?php endforeach; ?>
            </select>
            <small class="ath-license-icon-note"><?php esc_html_e('Internal icon set', 'authentype-font-specimen'); ?></small>
        </td>
        <td>
            <select name="ath_license_options[<?php echo esc_attr($index); ?>][license_group]">
                <option value="" <?php selected($group, ''); ?>><?php esc_html_e('Auto', 'authentype-font-specimen'); ?></option>
                <option value="common" <?php selected($group, 'common'); ?>><?php esc_html_e('Common', 'authentype-font-specimen'); ?></option>
                <option value="extended" <?php selected($group, 'extended'); ?>><?php esc_html_e('Extended', 'authentype-font-specimen'); ?></option>
                <option value="business" <?php selected($group, 'business'); ?>><?php esc_html_e('Business', 'authentype-font-specimen'); ?></option>
                <option value="custom" <?php selected($group, 'custom'); ?>><?php esc_html_e('Custom', 'authentype-font-specimen'); ?></option>
            </select>
        </td>
        <td class="ath-license-featured-cell"><label><input type="checkbox" name="ath_license_options[<?php echo esc_attr($index); ?>][license_featured]" value="1" <?php checked(!empty($row['license_featured'])); ?>> <?php esc_html_e('Recommended', 'authentype-font-specimen'); ?></label></td>
        <td>
            <?php $checkout_type = !empty($row['license_checkout_type']) && in_array($row['license_checkout_type'], ath_specimen_license_checkout_type_options(), true) ? $row['license_checkout_type'] : ''; ?>
            <select name="ath_license_options[<?php echo esc_attr($index); ?>][license_checkout_type]">
                <option value="" <?php selected($checkout_type, ''); ?>><?php esc_html_e('Auto', 'authentype-font-specimen'); ?></option>
                <option value="pay_once" <?php selected($checkout_type, 'pay_once'); ?>><?php esc_html_e('Pay once', 'authentype-font-specimen'); ?></option>
                <option value="annual" <?php selected($checkout_type, 'annual'); ?>><?php esc_html_e('Annual', 'authentype-font-specimen'); ?></option>
                <option value="contact" <?php selected($checkout_type, 'contact'); ?>><?php esc_html_e('Contact sales', 'authentype-font-specimen'); ?></option>
            </select>
        </td>
        <td><textarea name="ath_license_options[<?php echo esc_attr($index); ?>][license_description]" rows="2" placeholder="For local desktop use."><?php echo esc_textarea($row['license_description']); ?></textarea></td>
        <td><button type="button" class="button ath-remove-row"><?php esc_html_e('Remove', 'authentype-font-specimen'); ?></button></td>
    </tr>
    <?php
}

function ath_specimen_product_download_row($index, $row = array()) {
    $row = wp_parse_args($row, array(
        'download_id' => '',
        'legacy_download' => 0,
        'download_name' => '',
        'download_file' => '',
        'style_variation_value' => '',
        'license_variation_value' => '',
    ));
    ?>
    <tr class="ath-admin-row">
        <td class="ath-row-order">
            <input type="hidden" name="ath_product_downloads[<?php echo esc_attr($index); ?>][download_id]" value="<?php echo esc_attr($row['download_id']); ?>">
            <input type="hidden" name="ath_product_downloads[<?php echo esc_attr($index); ?>][legacy_download]" value="<?php echo !empty($row['legacy_download']) ? '1' : '0'; ?>">
            <button type="button" class="button ath-move-row ath-move-up" aria-label="<?php esc_attr_e('Move up', 'authentype-font-specimen'); ?>">↑</button>
            <button type="button" class="button ath-move-row ath-move-down" aria-label="<?php esc_attr_e('Move down', 'authentype-font-specimen'); ?>">↓</button>
        </td>
        <td><input type="text" name="ath_product_downloads[<?php echo esc_attr($index); ?>][download_name]" value="<?php echo esc_attr($row['download_name']); ?>" placeholder="Regular Desktop Files"></td>
        <td>
            <input type="url" class="ath-download-url" name="ath_product_downloads[<?php echo esc_attr($index); ?>][download_file]" value="<?php echo esc_attr($row['download_file']); ?>" placeholder="https://.../download.zip">
            <button type="button" class="button ath-upload-download"><?php esc_html_e('Upload', 'authentype-font-specimen'); ?></button>
        </td>
        <td><input type="text" name="ath_product_downloads[<?php echo esc_attr($index); ?>][style_variation_value]" value="<?php echo esc_attr($row['style_variation_value']); ?>" placeholder="regular"></td>
        <td><input type="text" name="ath_product_downloads[<?php echo esc_attr($index); ?>][license_variation_value]" value="<?php echo esc_attr($row['license_variation_value']); ?>" placeholder="desktop"></td>
        <td><button type="button" class="button ath-remove-row"><?php esc_html_e('Remove', 'authentype-font-specimen'); ?></button></td>
    </tr>
    <?php
}

function ath_specimen_package_license_row($index, $row = array()) {
    $row = wp_parse_args($row, array(
        'license_label' => '',
        'license_variation_value' => '',
        'template_zip' => '',
    ));
    ?>
    <tr class="ath-admin-row">
        <td class="ath-row-order">
            <button type="button" class="button ath-move-row ath-move-up" aria-label="<?php esc_attr_e('Move up', 'authentype-font-specimen'); ?>">↑</button>
            <button type="button" class="button ath-move-row ath-move-down" aria-label="<?php esc_attr_e('Move down', 'authentype-font-specimen'); ?>">↓</button>
        </td>
        <td><input type="text" name="ath_package_builder[licenses][<?php echo esc_attr($index); ?>][license_label]" value="<?php echo esc_attr($row['license_label']); ?>" placeholder="Desktop"></td>
        <td><input type="text" name="ath_package_builder[licenses][<?php echo esc_attr($index); ?>][license_variation_value]" value="<?php echo esc_attr($row['license_variation_value']); ?>" placeholder="desktop"></td>
        <td>
            <input type="url" class="ath-package-template-zip" name="ath_package_builder[licenses][<?php echo esc_attr($index); ?>][template_zip]" value="<?php echo esc_attr($row['template_zip']); ?>" placeholder="https://.../DESKTOP.zip">
            <button type="button" class="button ath-upload-package-template"><?php esc_html_e('Upload', 'authentype-font-specimen'); ?></button>
        </td>
        <td><button type="button" class="button ath-remove-row"><?php esc_html_e('Remove', 'authentype-font-specimen'); ?></button></td>
    </tr>
    <?php
}

function ath_specimen_price_matrix_key($value) {
    return ath_specimen_slug($value);
}

function ath_specimen_price_input_value($price_matrix, $style_key, $license_key, $price_key) {
    return isset($price_matrix[$style_key][$license_key][$price_key]) ? $price_matrix[$style_key][$license_key][$price_key] : '';
}

function ath_specimen_package_builder_value($package_builder, $key, $default = '') {
    return isset($package_builder[$key]) ? $package_builder[$key] : $default;
}


function ath_specimen_package_builder_licenses($package_builder) {
    if (!empty($package_builder['licenses']) && is_array($package_builder['licenses'])) {
        return $package_builder['licenses'];
    }

    return array(
        array(
            'license_label' => 'Desktop',
            'license_variation_value' => 'desktop',
            'template_zip' => ath_specimen_package_builder_value($package_builder, 'desktop_template'),
        ),
        array(
            'license_label' => 'Webfont',
            'license_variation_value' => 'webfont',
            'template_zip' => ath_specimen_package_builder_value($package_builder, 'webfont_template'),
        ),
        array(
            'license_label' => 'App',
            'license_variation_value' => 'app',
            'template_zip' => ath_specimen_package_builder_value($package_builder, 'app_template'),
        ),
        array(
            'license_label' => 'ePub',
            'license_variation_value' => 'epub',
            'template_zip' => '',
        ),
        array(
            'license_label' => 'Server',
            'license_variation_value' => 'server',
            'template_zip' => '',
        ),
        array(
            'license_label' => 'Extended',
            'license_variation_value' => 'extended',
            'template_zip' => ath_specimen_package_builder_value($package_builder, 'extended_template'),
        ),
    );
}

/**
 * Hash only settings that can alter protected files/package delivery.
 * Pricing is intentionally excluded so a price edit can never make assets stale.
 */
function ath_specimen_asset_config_hash($settings) {
    $settings = is_array($settings) ? $settings : array();
    $licenses = array();
    foreach (ath_specimen_package_builder_licenses($settings) as $license) {
        $label = !empty($license['license_label']) ? sanitize_text_field($license['license_label']) : '';
        $value = !empty($license['license_variation_value']) ? ath_specimen_slug($license['license_variation_value']) : ath_specimen_slug($label);
        if (!$label || !$value) continue;
        $licenses[] = array(
            'label' => $label,
            'value' => $value,
            'template_zip' => !empty($license['template_zip']) ? esc_url_raw($license['template_zip']) : '',
            'extensions' => function_exists('ath_specimen_package_license_exts') ? array_values(ath_specimen_package_license_exts($value)) : array(),
        );
    }
    $payload = array(
        'family_name' => !empty($settings['family_name']) ? sanitize_text_field($settings['family_name']) : '',
        'preview_format' => !empty($settings['preview_format']) ? sanitize_key($settings['preview_format']) : 'auto',
        'licenses' => $licenses,
    );
    return hash('sha256', wp_json_encode($payload));
}

function ath_specimen_pricing_schema_signature($styles, $licenses) {
    $style_keys = array();
    foreach ((array) $styles as $style) {
        if (!is_array($style)) continue;
        $key = !empty($style['style_variation_value']) ? ath_specimen_slug($style['style_variation_value']) : ath_specimen_slug($style['style_name'] ?? '');
        if ($key) $style_keys[] = $key;
    }

    $license_keys = array();
    foreach ((array) $licenses as $license) {
        if (!is_array($license)) continue;
        $key = !empty($license['license_variation_value']) ? ath_specimen_slug($license['license_variation_value']) : ath_specimen_slug($license['license_label'] ?? '');
        if ($key) $license_keys[] = $key;
    }

    $style_keys = array_values(array_unique($style_keys));
    $license_keys = array_values(array_unique($license_keys));
    sort($style_keys, SORT_STRING);
    sort($license_keys, SORT_STRING);

    return hash('sha256', wp_json_encode(array(
        'styles' => $style_keys,
        'licenses' => $license_keys,
    )));
}

function ath_specimen_pricing_hash($matrix) {
    $matrix = ath_specimen_sanitize_price_matrix(is_array($matrix) ? $matrix : array());
    $sort_recursive = function (&$value) use (&$sort_recursive) {
        if (!is_array($value)) return;
        ksort($value, SORT_STRING);
        foreach ($value as &$child) $sort_recursive($child);
    };
    $sort_recursive($matrix);
    return hash('sha256', wp_json_encode($matrix));
}

/**
 * Preserve valid Price Matrix values while removing style/license dimensions
 * and optional Style × License combinations that no longer have protected
 * delivery in the current secure asset inventory. This is reconciliation only;
 * it does not calculate, infer, or change prices for combinations that remain.
 */
function ath_specimen_reconcile_price_matrix_dimensions($matrix, $styles, $licenses, $delivery_pairs = null) {
    $matrix = ath_specimen_sanitize_price_matrix(is_array($matrix) ? $matrix : array());
    $style_keys = array();
    foreach ((array) $styles as $style) {
        if (!is_array($style)) continue;
        $key = !empty($style['style_variation_value']) ? ath_specimen_slug($style['style_variation_value']) : ath_specimen_slug($style['style_name'] ?? '');
        if ($key) $style_keys[$key] = true;
    }
    $license_keys = array();
    foreach ((array) $licenses as $license) {
        if (!is_array($license)) continue;
        $key = !empty($license['license_variation_value']) ? ath_specimen_slug($license['license_variation_value']) : ath_specimen_slug($license['license_label'] ?? '');
        if ($key) $license_keys[$key] = true;
    }

    $clean = array();
    foreach ($matrix as $style_key => $license_rows) {
        $style_key = ath_specimen_slug($style_key);
        if (!$style_key || empty($style_keys[$style_key]) || !is_array($license_rows)) continue;
        foreach ($license_rows as $license_key => $prices) {
            $license_key = ath_specimen_slug($license_key);
            if (!$license_key || empty($license_keys[$license_key]) || !is_array($prices)) continue;
            if (is_array($delivery_pairs) && empty($delivery_pairs[$style_key . '|' . $license_key])) continue;
            if (!isset($clean[$style_key])) $clean[$style_key] = array();
            $clean[$style_key][$license_key] = $prices;
        }
    }
    return ath_specimen_sanitize_price_matrix($clean);
}

/**
 * Fingerprint the server-side commerce/builder state represented by the current
 * Athtyp edit page. AJAX asset/pricing/import actions can change these values
 * without a full page reload; a later normal WordPress Update from that stale
 * page must never write the old form values back over the newer server state.
 */
function ath_specimen_admin_workflow_signature($post_id) {
    $post_id = absint($post_id);
    if (!$post_id) return '';

    $keys = array(
        '_ath_linked_product',
        '_ath_style_attribute',
        '_ath_license_attribute',
        '_ath_default_specimen_style',
        '_ath_font_styles',
        '_ath_license_options',
        '_ath_price_matrix',
        '_ath_package_builder',
        '_ath_product_downloads',
        '_ath_package_architecture',
        '_ath_asset_config_hash',
        '_ath_asset_built_at',
    );
    $payload = array();
    foreach ($keys as $key) {
        $payload[$key] = ath_specimen_get_meta($post_id, $key, null);
    }
    return hash('sha256', wp_json_encode($payload));
}

function ath_specimen_current_pricing_hash($post_id) {
    $matrix = ath_specimen_get_meta($post_id, '_ath_price_matrix', array());
    $actual = ath_specimen_pricing_hash(is_array($matrix) ? $matrix : array());
    $stored = (string) ath_specimen_get_meta($post_id, '_ath_pricing_hash', '');
    if (!$stored || !hash_equals((string) $stored, (string) $actual)) {
        update_post_meta(absint($post_id), '_ath_pricing_hash', $actual);
    }
    return $actual;
}

function ath_specimen_queue_admin_guard_notice($post_id, $message) {
    $post_id = absint($post_id);
    $user_id = get_current_user_id();
    if (!$post_id || !$user_id || !$message) return;
    set_transient('ath_admin_guard_' . $user_id . '_' . $post_id, sanitize_text_field($message), 10 * MINUTE_IN_SECONDS);
}

add_action('admin_notices', function () {
    if (!is_admin()) return;
    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
    $user_id = get_current_user_id();
    if (!$post_id || !$user_id) return;
    $key = 'ath_admin_guard_' . $user_id . '_' . $post_id;
    $message = get_transient($key);
    if (!$message) return;
    delete_transient($key);
    echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html($message) . '</p></div>';
});

function ath_specimen_save_pricing_matrix($post_id, $matrix) {
    $post_id = absint($post_id);
    $clean = ath_specimen_sanitize_price_matrix(is_array($matrix) ? $matrix : array());
    update_post_meta($post_id, '_ath_price_matrix', $clean);
    $hash = ath_specimen_pricing_hash($clean);
    update_post_meta($post_id, '_ath_pricing_hash', $hash);
    update_post_meta($post_id, '_ath_pricing_saved_at', time());
    delete_post_meta($post_id, '_ath_pricing_needs_review');
    return array('matrix' => $clean, 'hash' => $hash);
}

/**
 * One-time compatibility bridge for secure.7.3.5 and older builds where prices
 * lived in Package Builder Step 2. Existing Price Matrix values always win.
 */
function ath_specimen_migrate_legacy_package_pricing($post_id, $package_builder, $styles) {
    $existing = ath_specimen_get_meta($post_id, '_ath_price_matrix', array());
    if (!empty($existing) || empty($package_builder['licenses']) || !is_array($package_builder['licenses']) || empty($styles)) return $existing;

    $matrix = array();
    $individual = array_values(array_filter($styles, function ($style) { return empty($style['is_package']); }));
    $style_count = max(1, count($individual));
    foreach ($package_builder['licenses'] as $license) {
        $license_key = !empty($license['license_variation_value']) ? ath_specimen_slug($license['license_variation_value']) : '';
        if (!$license_key) continue;
        $single = isset($license['single_price']) ? ath_specimen_sanitize_price_value($license['single_price']) : '';
        foreach ($individual as $style) {
            $style_key = !empty($style['style_variation_value']) ? ath_specimen_slug($style['style_variation_value']) : ath_specimen_slug($style['style_name'] ?? '');
            if ($style_key && '' !== $single) $matrix[$style_key][$license_key] = array('regular' => $single, 'sale' => '');
        }
        $full = isset($license['full_price']) ? ath_specimen_sanitize_price_value($license['full_price']) : '';
        $discount = isset($license['full_discount']) && '' !== $license['full_discount'] ? min(95, max(0, absint($license['full_discount']))) : '';
        if ('' !== $full) {
            $regular = $full;
            $sale = '';
            if ('' !== $discount && '' !== $single) {
                $undiscounted = ath_specimen_sanitize_price_value((float) $single * $style_count);
                if ('' !== $undiscounted && (float) $undiscounted > (float) $full) { $regular = $undiscounted; $sale = $full; }
            }
            $matrix['full-style'][$license_key] = array('regular' => $regular, 'sale' => $sale);
        }
    }
    if (!empty($matrix)) {
        $saved = ath_specimen_save_pricing_matrix($post_id, $matrix);
        return $saved['matrix'];
    }
    return $existing;
}

function ath_specimen_render_price_matrix($styles, $licenses, $price_matrix) {
    if (empty($styles) || !is_array($styles) || empty($licenses) || !is_array($licenses)) {
        echo '<p class="description">' . esc_html__('Save Font Styles and License Options first to build the price matrix.', 'authentype-font-specimen') . '</p>';
        return;
    }
    ?>
    <div class="ath-price-matrix-wrap">
        <table class="widefat striped ath-price-matrix">
            <thead>
                <tr>
                    <th><?php esc_html_e('Style', 'authentype-font-specimen'); ?></th>
                    <?php foreach ($licenses as $license) : ?>
                        <?php if (empty($license['license_label']) || empty($license['license_variation_value'])) continue; ?>
                        <th><?php echo esc_html($license['license_label']); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($styles as $style) : ?>
                    <?php
                    if (empty($style['style_name'])) continue;
                    $style_key = ath_specimen_price_matrix_key(!empty($style['style_variation_value']) ? $style['style_variation_value'] : $style['style_name']);
                    if (!$style_key) continue;
                    ?>
                    <tr>
                        <th>
                            <?php echo esc_html($style['style_name']); ?>
                            <code><?php echo esc_html($style_key); ?></code>
                        </th>
                        <?php foreach ($licenses as $license) : ?>
                            <?php
                            if (empty($license['license_label']) || empty($license['license_variation_value'])) continue;
                            $license_key = ath_specimen_price_matrix_key($license['license_variation_value']);
                            if (!$license_key) continue;
                            ?>
                            <td>
                                <label>
                                    <span><?php esc_html_e('Regular', 'authentype-font-specimen'); ?></span>
                                    <input type="number" step="0.01" min="0" name="ath_price_matrix[<?php echo esc_attr($style_key); ?>][<?php echo esc_attr($license_key); ?>][regular]" value="<?php echo esc_attr(ath_specimen_price_input_value($price_matrix, $style_key, $license_key, 'regular')); ?>">
                                </label>
                                <label>
                                    <span><?php esc_html_e('Sale', 'authentype-font-specimen'); ?></span>
                                    <input type="number" step="0.01" min="0" name="ath_price_matrix[<?php echo esc_attr($style_key); ?>][<?php echo esc_attr($license_key); ?>][sale]" value="<?php echo esc_attr(ath_specimen_price_input_value($price_matrix, $style_key, $license_key, 'sale')); ?>">
                                </label>
                                <?php
                                $regular_value = (float) ath_specimen_price_input_value($price_matrix, $style_key, $license_key, 'regular');
                                $sale_value = (float) ath_specimen_price_input_value($price_matrix, $style_key, $license_key, 'sale');
                                $discount_value = $regular_value > 0 && $sale_value > 0 && $sale_value < $regular_value ? round((($regular_value - $sale_value) / $regular_value) * 100, 2) : '';
                                ?>
                                <label class="ath-discount-helper-label">
                                    <span><?php esc_html_e('Discount %', 'authentype-font-specimen'); ?></span>
                                    <input type="number" class="ath-matrix-discount-input" step="0.01" min="0" max="95" value="<?php echo esc_attr($discount_value); ?>" placeholder="Optional helper">
                                </label>
                                <small class="ath-matrix-discount" data-ath-matrix-discount></small>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function ath_specimen_default_style_options($styles, $selected = '') {
    echo '<option value="regular" ' . selected($selected, 'regular', false) . '>' . esc_html__('Regular (auto)', 'authentype-font-specimen') . '</option>';

    if (!is_array($styles)) return;

    foreach ($styles as $row) {
        if (empty($row['style_name'])) continue;

        $value = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : ath_specimen_slug($row['style_name']);
        if (!$value) continue;

        echo '<option value="' . esc_attr($value) . '" ' . selected($selected, $value, false) . '>' . esc_html($row['style_name']) . '</option>';
    }
}

function ath_specimen_pairing_font_row($index, $row = array()) {
    $row = wp_parse_args($row, array(
        'pair_key' => '',
        'pair_name' => '',
        'font_file' => '',
        'font_weight' => 400,
        'font_style' => 'normal',
        'product_url' => '',
        'default_selected' => '',
        'use_title' => 1,
        'use_body' => 1,
        'default_title' => '',
        'default_body' => '',
    ));
    $default_title = !empty($row['default_title']);
    $default_body = !empty($row['default_body']) || (!array_key_exists('default_body', $row) && !empty($row['default_selected']));
    $pair_key = !empty($row['pair_key']) ? ath_specimen_slug($row['pair_key']) : ath_specimen_slug($row['pair_name']);
    ?>
    <tr class="ath-admin-row">
        <td class="ath-row-order">
            <button type="button" class="button ath-move-row ath-move-up" aria-label="<?php esc_attr_e('Move up', 'authentype-font-specimen'); ?>">↑</button>
            <button type="button" class="button ath-move-row ath-move-down" aria-label="<?php esc_attr_e('Move down', 'authentype-font-specimen'); ?>">↓</button>
        </td>
        <td>
            <input type="hidden" class="ath-pair-key" name="ath_pairing_fonts[<?php echo esc_attr($index); ?>][pair_key]" value="<?php echo esc_attr($pair_key); ?>">
            <input type="text" class="ath-pair-name" name="ath_pairing_fonts[<?php echo esc_attr($index); ?>][pair_name]" value="<?php echo esc_attr($row['pair_name']); ?>" placeholder="Body Sans">
        </td>
        <td>
            <input type="url" class="ath-font-url" name="ath_pairing_fonts[<?php echo esc_attr($index); ?>][font_file]" value="<?php echo esc_attr($row['font_file']); ?>" placeholder="https://.../font.woff">
            <button type="button" class="button ath-upload-font"><?php esc_html_e('Upload', 'authentype-font-specimen'); ?></button>
        </td>
        <td><input type="number" name="ath_pairing_fonts[<?php echo esc_attr($index); ?>][font_weight]" value="<?php echo esc_attr($row['font_weight']); ?>" min="1" max="1000"></td>
        <td>
            <select name="ath_pairing_fonts[<?php echo esc_attr($index); ?>][font_style]">
                <option value="normal" <?php selected($row['font_style'], 'normal'); ?>>Normal</option>
                <option value="italic" <?php selected($row['font_style'], 'italic'); ?>>Italic</option>
                <option value="oblique" <?php selected($row['font_style'], 'oblique'); ?>>Oblique</option>
            </select>
        </td>
        <td><input type="url" name="ath_pairing_fonts[<?php echo esc_attr($index); ?>][product_url]" value="<?php echo esc_attr($row['product_url']); ?>" placeholder="https://..."></td>
        <td><input type="checkbox" name="ath_pairing_fonts[<?php echo esc_attr($index); ?>][use_title]" value="1" <?php checked(!empty($row['use_title'])); ?>></td>
        <td><input type="checkbox" name="ath_pairing_fonts[<?php echo esc_attr($index); ?>][use_body]" value="1" <?php checked(!empty($row['use_body'])); ?>></td>
        <td><input type="checkbox" name="ath_pairing_fonts[<?php echo esc_attr($index); ?>][default_title]" value="1" <?php checked($default_title); ?>></td>
        <td><input type="checkbox" name="ath_pairing_fonts[<?php echo esc_attr($index); ?>][default_body]" value="1" <?php checked($default_body); ?>></td>
        <td><button type="button" class="button ath-remove-row"><?php esc_html_e('Remove', 'authentype-font-specimen'); ?></button></td>
    </tr>
    <?php
}

function ath_specimen_pairing_font_options($pairing_fonts, $selected = '') {
    echo '<option value="">' . esc_html__('Select font', 'authentype-font-specimen') . '</option>';
    if (!is_array($pairing_fonts)) return;

    foreach ($pairing_fonts as $row) {
        if (empty($row['pair_name'])) continue;
        $key = !empty($row['pair_key']) ? ath_specimen_slug($row['pair_key']) : ath_specimen_slug($row['pair_name']);
        if (!$key) continue;
        echo '<option value="' . esc_attr($key) . '" ' . selected($selected, $key, false) . '>' . esc_html($row['pair_name']) . '</option>';
    }
}

function ath_specimen_pair_card_row($index, $row = array(), $pairing_fonts = array()) {
    $row = wp_parse_args($row, array(
        'title_font' => '',
        'body_font' => '',
        'heading_text' => '',
        'body_text' => '',
        'product_url' => '',
    ));
    ?>
    <tr class="ath-admin-row">
        <td class="ath-row-order">
            <button type="button" class="button ath-move-row ath-move-up" aria-label="<?php esc_attr_e('Move up', 'authentype-font-specimen'); ?>">↑</button>
            <button type="button" class="button ath-move-row ath-move-down" aria-label="<?php esc_attr_e('Move down', 'authentype-font-specimen'); ?>">↓</button>
        </td>
        <td><select name="ath_pair_cards[<?php echo esc_attr($index); ?>][title_font]"><?php ath_specimen_pairing_font_options($pairing_fonts, $row['title_font']); ?></select></td>
        <td><select name="ath_pair_cards[<?php echo esc_attr($index); ?>][body_font]"><?php ath_specimen_pairing_font_options($pairing_fonts, $row['body_font']); ?></select></td>
        <td><input type="text" name="ath_pair_cards[<?php echo esc_attr($index); ?>][heading_text]" value="<?php echo esc_attr($row['heading_text']); ?>" placeholder="A modern voice for expressive brands"></td>
        <td><textarea name="ath_pair_cards[<?php echo esc_attr($index); ?>][body_text]" rows="2" placeholder="Pair a strong display style with a quieter supporting style."><?php echo esc_textarea($row['body_text']); ?></textarea></td>
        <td><input type="url" name="ath_pair_cards[<?php echo esc_attr($index); ?>][product_url]" value="<?php echo esc_attr($row['product_url']); ?>" placeholder="Optional override"></td>
        <td><button type="button" class="button ath-remove-row"><?php esc_html_e('Remove', 'authentype-font-specimen'); ?></button></td>
    </tr>
    <?php
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'ath_font_specimen_settings',
        __('Font Specimen Commerce', 'authentype-font-specimen'),
        'ath_specimen_render_metabox',
        'ath_font',
        'normal',
        'high'
    );
});

function ath_specimen_render_metabox($post) {
    wp_nonce_field('ath_specimen_save_meta', 'ath_specimen_meta_nonce');

    $product_id = (int) ath_specimen_get_meta($post->ID, '_ath_linked_product', 0);
    $style_attr = ath_specimen_get_meta($post->ID, '_ath_style_attribute', 'pa_style');
    $license_attr = ath_specimen_get_meta($post->ID, '_ath_license_attribute', 'pa_license');
    $styles = ath_specimen_get_meta($post->ID, '_ath_font_styles', array());
    $licenses = ath_specimen_get_meta($post->ID, '_ath_license_options', array());
    $price_matrix = ath_specimen_get_meta($post->ID, '_ath_price_matrix', array());
    $package_builder = ath_specimen_get_meta($post->ID, '_ath_package_builder', array());
    $package_builder = is_array($package_builder) ? $package_builder : array();
    $price_matrix = ath_specimen_migrate_legacy_package_pricing($post->ID, $package_builder, $styles);
    $package_licenses = ath_specimen_package_builder_licenses($package_builder);
    $asset_config_hash = ath_specimen_asset_config_hash($package_builder);
    $stored_asset_hash = (string) ath_specimen_get_meta($post->ID, '_ath_asset_config_hash', '');
    $asset_architecture = (string) ath_specimen_get_meta($post->ID, '_ath_package_architecture', '');
    if (!$stored_asset_hash && 'shared-assets-v7' === $asset_architecture) {
        // Migration: secure.7.3.5 assets are already valid. Adopt the delivery-only
        // config fingerprint without touching/rebuilding any files.
        $stored_asset_hash = $asset_config_hash;
        update_post_meta($post->ID, '_ath_asset_config_hash', $stored_asset_hash);
    }
    $assets_up_to_date = 'shared-assets-v7' === $asset_architecture && $stored_asset_hash && hash_equals($stored_asset_hash, $asset_config_hash);
    $pricing_needs_review = (bool) ath_specimen_get_meta($post->ID, '_ath_pricing_needs_review', false);
    $pricing_schema_signature = ath_specimen_pricing_schema_signature($styles, $licenses);
    $pricing_ready = !empty($styles) && is_array($styles) && !empty($licenses) && is_array($licenses);
    $pricing_configured = !empty($price_matrix) && is_array($price_matrix);
    $pricing_hash = ath_specimen_pricing_hash(is_array($price_matrix) ? $price_matrix : array());
    $stored_pricing_hash = (string) ath_specimen_get_meta($post->ID, '_ath_pricing_hash', '');
    if (!$stored_pricing_hash || !hash_equals((string) $stored_pricing_hash, (string) $pricing_hash)) {
        update_post_meta($post->ID, '_ath_pricing_hash', $pricing_hash);
        $stored_pricing_hash = $pricing_hash;
    }
    $pricing_revision = $pricing_hash;
    $saved_woo_signature = (string) ath_specimen_get_meta($post->ID, '_ath_woo_synced_signature', '');
    $current_woo_signature = function_exists('ath_specimen_woo_sync_signature') ? ath_specimen_woo_sync_signature($post->ID, $style_attr, $license_attr) : '';
    $woo_up_to_date = $saved_woo_signature && $current_woo_signature && hash_equals($saved_woo_signature, $current_woo_signature);
    $product_downloads = ath_specimen_get_meta($post->ID, '_ath_product_downloads', array());
    $pairing_fonts = ath_specimen_get_meta($post->ID, '_ath_pairing_fonts', array());
    $pair_cards = ath_specimen_get_meta($post->ID, '_ath_pair_cards', array());
    $default_specimen_style = ath_specimen_get_meta($post->ID, '_ath_default_specimen_style', 'regular');
    $free_downloads_enabled = ath_specimen_get_meta($post->ID, '_ath_free_downloads_below_pairs', '');
    $free_downloads_type = ath_specimen_get_meta($post->ID, '_ath_free_downloads_type', '');
    $free_downloads_limit = ath_specimen_get_meta($post->ID, '_ath_free_downloads_limit', '8');
    $adoption_source = (string) ath_specimen_get_meta($post->ID, '_ath_adoption_source', '');
    $adoption_source_product = (int) ath_specimen_get_meta($post->ID, '_ath_adoption_source_product', 0);
    $license_url_override = function_exists('ath_specimen_product_license_url_override') ? ath_specimen_product_license_url_override($post->ID) : '';
    $global_license_url = function_exists('ath_specimen_global_license_url_template') ? ath_specimen_global_license_url_template() : '';
    $workflow_signature = ath_specimen_admin_workflow_signature($post->ID);
    ?>
    <input type="hidden" name="ath_admin_workflow_signature" value="<?php echo esc_attr($workflow_signature); ?>">
    <div class="ath-admin-metabox">
        <section class="ath-admin-section ath-builder-flow">
            <h3><?php esc_html_e('Athtyp Product Builder', 'authentype-font-specimen'); ?></h3>
            <p class="description"><?php esc_html_e('Secure.7.3.6 flow: upload one family ZIP, define license delivery/templates, build protected assets once, then manage pricing independently and sync WooCommerce. Price or discount changes never rebuild font files or family ZIPs.', 'authentype-font-specimen'); ?></p>
            <?php if ('existing_woo_catalog' === $adoption_source) : ?>
                <div class="notice notice-info inline ath-adoption-import-notice"><p><strong><?php esc_html_e('Adopted from existing Woo catalog:', 'authentype-font-specimen'); ?></strong> <?php echo esc_html(sprintf(__('Woo product #%d was read-only imported into this draft. Existing prices/download mappings are preserved here for review. Build secure assets and confirm delivery before running Woo Sync; adoption itself did not modify WooCommerce.', 'authentype-font-specimen'), $adoption_source_product)); ?></p></div>
            <?php endif; ?>
            <?php if (!class_exists('Imagick') && !function_exists('imagettftext')) : ?>
                <div class="notice notice-error inline"><p><strong><?php esc_html_e('Server preview renderer unavailable:', 'authentype-font-specimen'); ?></strong> <?php esc_html_e('Enable the PHP Imagick extension (recommended) or GD with FreeType before publishing secure previews.', 'authentype-font-specimen'); ?></p></div>
            <?php elseif (!class_exists('Imagick') && function_exists('imagettftext')) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e('Secure preview is using the GD/FreeType fallback. Use TTF as the preview source for best compatibility; Imagick is recommended for OTF families.', 'authentype-font-specimen'); ?></p></div>
            <?php endif; ?>
            <?php if ('redirect' === get_option('woocommerce_file_download_method')) : ?>
                <div class="notice notice-warning inline"><p><strong><?php esc_html_e('WooCommerce download method:', 'authentype-font-specimen'); ?></strong> <?php esc_html_e('Redirect only is not recommended for protected commercial font assets. Use Force Downloads or an accelerated protected method such as X-Accel-Redirect/X-Sendfile.', 'authentype-font-specimen'); ?></p></div>
            <?php endif; ?>

            <h4><?php esc_html_e('Step 1: Upload Family ZIP', 'authentype-font-specimen'); ?></h4>
            <div class="ath-admin-grid">
                <label>
                    <span><?php esc_html_e('Font Family ZIP', 'authentype-font-specimen'); ?></span>
                    <input type="url" class="ath-package-font-zip" name="ath_package_builder[font_zip]" value="<?php echo esc_attr(ath_specimen_package_builder_value($package_builder, 'font_zip')); ?>" placeholder="https://.../Family.zip">
                    <button type="button" class="button ath-upload-package-font-zip"><?php esc_html_e('Upload ZIP', 'authentype-font-specimen'); ?></button>
                    <small style="display:block;margin-top:6px;color:#646970;"><?php esc_html_e('Accepted structures: direct OTF/TTF/WOFF/WOFF2 files, format folders, or one level of nested Single Style ZIPs inside the Family ZIP.', 'authentype-font-specimen'); ?></small>
                </label>
                <label>
                    <span><?php esc_html_e('Family Name', 'authentype-font-specimen'); ?></span>
                    <input type="text" class="ath-package-family-name" name="ath_package_builder[family_name]" value="<?php echo esc_attr(ath_specimen_package_builder_value($package_builder, 'family_name', get_the_title($post))); ?>" placeholder="Poppis">
                </label>
                <label>
                    <span><?php esc_html_e('Preview Font Format', 'authentype-font-specimen'); ?></span>
                    <?php $preview_format = ath_specimen_package_builder_value($package_builder, 'preview_format', 'auto'); ?>
                    <select class="ath-package-preview-format" name="ath_package_builder[preview_format]">
                        <option value="woff" <?php selected($preview_format, 'woff'); ?>><?php esc_html_e('Legacy: Prefer WOFF', 'authentype-font-specimen'); ?></option>
                        <option value="otf" <?php selected($preview_format, 'otf'); ?>><?php esc_html_e('Prefer OTF', 'authentype-font-specimen'); ?></option>
                        <option value="ttf" <?php selected($preview_format, 'ttf'); ?>><?php esc_html_e('Prefer TTF', 'authentype-font-specimen'); ?></option>
                        <option value="auto" <?php selected($preview_format, 'auto'); ?>><?php esc_html_e('Auto', 'authentype-font-specimen'); ?></option>
                    </select>
                    <small style="display:block;margin-top:6px;color:#646970;"><?php esc_html_e('Secure server rendering prefers OTF or TTF because the font stays on the server. The browser receives only PNG preview pixels, never the complete font file.', 'authentype-font-specimen'); ?></small>
                    <input type="hidden" class="ath-package-secure-token" name="ath_package_builder[secure_token]" value="<?php echo esc_attr(ath_specimen_package_builder_value($package_builder, 'secure_token')); ?>">
                </label>
            </div>

            <h4><?php esc_html_e('Step 2: License Delivery & Templates', 'authentype-font-specimen'); ?></h4>
            <p class="description"><?php esc_html_e('This step defines which files each license receives. Pricing is intentionally separated below; changing prices or discounts never rebuilds font files.', 'authentype-font-specimen'); ?></p>
            <table class="widefat striped ath-repeat-table ath-package-license-table" data-next-index="<?php echo esc_attr(max(1, count((array) $package_licenses))); ?>">
                <thead>
                    <tr>
                        <th>Order</th><th>License</th><th>Woo Value</th><th>Template ZIP</th><th></th>
                    </tr>
                </thead>
                <tbody id="ath-package-license-rows">
                    <?php foreach ($package_licenses as $index => $row) ath_specimen_package_license_row($index, $row); ?>
                </tbody>
            </table>
            <button type="button" class="button button-secondary ath-add-package-license"><?php esc_html_e('Add License', 'authentype-font-specimen'); ?></button>

            <h4><?php esc_html_e('Step 3: Build Secure Shared Assets', 'authentype-font-specimen'); ?></h4>
            <div class="ath-admin-state-row">
                <span class="ath-admin-state <?php echo $assets_up_to_date ? 'is-good' : 'is-warning'; ?>">
                    <strong><?php esc_html_e('Assets', 'authentype-font-specimen'); ?>:</strong>
                    <?php echo esc_html($assets_up_to_date ? __('Up to date', 'authentype-font-specimen') : __('Needs build / review', 'authentype-font-specimen')); ?>
                </span>
                <?php if ($assets_up_to_date) : ?><small><?php esc_html_e('Pricing changes do not affect this status.', 'authentype-font-specimen'); ?></small><?php endif; ?>
            </div>
            <p>
                <button type="button" class="button button-primary ath-build-packages"><?php esc_html_e('Build Secure Assets', 'authentype-font-specimen'); ?></button>
                <span class="ath-package-status" aria-live="polite"></span>
            </p>

            <h4><?php esc_html_e('Step 4: Pricing & Discounts', 'authentype-font-specimen'); ?></h4>
            <div class="ath-pricing-authority-note">
                <strong><?php esc_html_e('Pricing Authority: Athtyp Plugin', 'authentype-font-specimen'); ?></strong>
                <p><?php esc_html_e('Regular and Sale prices live only in this Price Matrix. Discount percentage is calculated from them. Saving pricing updates database metadata only—no font extraction, ZIP creation, package compression, or secure asset rebuild occurs.', 'authentype-font-specimen'); ?></p>
            </div>
            <div class="ath-admin-state-row">
                <span class="ath-admin-state <?php echo ($pricing_needs_review || !$pricing_configured) ? 'is-warning' : 'is-good'; ?>">
                    <strong><?php esc_html_e('Pricing', 'authentype-font-specimen'); ?>:</strong>
                    <?php
                    if ($pricing_needs_review) {
                        echo esc_html__('Needs review after asset change', 'authentype-font-specimen');
                    } elseif (!$pricing_configured) {
                        echo esc_html__('Not configured', 'authentype-font-specimen');
                    } else {
                        echo esc_html__('Saved independently', 'authentype-font-specimen');
                    }
                    ?>
                </span>
                <span class="ath-admin-state <?php echo $woo_up_to_date ? 'is-good' : 'is-warning'; ?>">
                    <strong><?php esc_html_e('WooCommerce', 'authentype-font-specimen'); ?>:</strong>
                    <?php echo esc_html($woo_up_to_date ? __('Synced', 'authentype-font-specimen') : __('Needs sync', 'authentype-font-specimen')); ?>
                </span>
            </div>
            <p class="description"><?php esc_html_e('Regular is the original price. Sale is optional and must be lower than Regular. Leave both empty to make that Style × License combination unpriced. The frontend reads this matrix directly; WooCommerce receives a copy only when you sync.', 'authentype-font-specimen'); ?></p>
            <?php ath_specimen_render_price_matrix($styles, $licenses, is_array($price_matrix) ? $price_matrix : array()); ?>
            <p>
                <button type="button" class="button button-primary ath-save-pricing" data-pricing-schema="<?php echo esc_attr($pricing_schema_signature); ?>" data-pricing-hash="<?php echo esc_attr($pricing_revision); ?>" <?php disabled(!$pricing_ready); ?>><?php esc_html_e('Save Pricing Only', 'authentype-font-specimen'); ?></button>
                <span class="ath-pricing-status" aria-live="polite"></span>
            </p>

            <h4><?php esc_html_e('Step 5: Sync Existing Woo Product', 'authentype-font-specimen'); ?></h4>
            <div class="ath-admin-grid">
                <label>
                    <span><?php esc_html_e('Linked Product', 'authentype-font-specimen'); ?></span>
                    <select name="ath_linked_product"><?php ath_specimen_product_options($product_id); ?></select>
                </label>
                <label>
                    <span><?php esc_html_e('Style Attribute Key', 'authentype-font-specimen'); ?></span>
                    <input type="text" name="ath_style_attribute" value="<?php echo esc_attr($style_attr); ?>" placeholder="pa_style">
                </label>
                <label>
                    <span><?php esc_html_e('License Attribute Key', 'authentype-font-specimen'); ?></span>
                    <input type="text" name="ath_license_attribute" value="<?php echo esc_attr($license_attr); ?>" placeholder="pa_license">
                </label>
                <label>
                    <span><?php esc_html_e('Default Specimen Style', 'authentype-font-specimen'); ?></span>
                    <select name="ath_default_specimen_style"><?php ath_specimen_default_style_options($styles, $default_specimen_style); ?></select>
                </label>
            </div>
            <p>
                <button type="button" class="button button-primary ath-build-woo" data-pricing-schema="<?php echo esc_attr($pricing_schema_signature); ?>" data-pricing-hash="<?php echo esc_attr($pricing_revision); ?>" <?php disabled(!$pricing_ready); ?>><?php esc_html_e('Sync Existing Woo Product', 'authentype-font-specimen'); ?></button>
                <button type="button" class="button button-secondary ath-generate-internal-codes"><?php esc_html_e('Generate Missing Internal Codes', 'authentype-font-specimen'); ?></button>
                <span class="ath-sync-status" aria-live="polite"></span>
            </p>
            <div class="ath-woo-sync-progress" hidden>
                <progress class="ath-woo-progress" max="100" value="0"></progress>
                <span class="ath-woo-progress-label">0%</span>
                <button type="button" class="button button-secondary ath-stop-woo-sync" hidden><?php esc_html_e('Pause Sync', 'authentype-font-specimen'); ?></button>
                <span class="description"><?php esc_html_e('Large families are processed in small resumable batches to avoid PHP/hosting timeouts.', 'authentype-font-specimen'); ?></span>
            </div>
        </section>

        <details class="ath-admin-advanced">
            <summary><?php esc_html_e('Advanced / Manual Edit', 'authentype-font-specimen'); ?></summary>

        <section class="ath-admin-section">
            <h3><?php esc_html_e('License Details URL', 'authentype-font-specimen'); ?></h3>
            <p class="description"><?php esc_html_e('Optional product-level override for the license links shown in the single-product Licensing tab and Choose Licenses popup. Leave blank to use the website-wide default.', 'authentype-font-specimen'); ?></p>
            <p>
                <label>
                    <span class="screen-reader-text"><?php esc_html_e('License URL Override', 'authentype-font-specimen'); ?></span>
                    <input type="text" class="regular-text code" name="ath_license_url_override" value="<?php echo esc_attr($license_url_override); ?>" placeholder="https://example.com/licenses/#{license}">
                </label>
            </p>
            <p class="description">
                <?php esc_html_e('Supports {license}. Without the token, the current license slug is appended as a fragment when no fragment is already present.', 'authentype-font-specimen'); ?>
                <?php if ($global_license_url) : ?>
                    <br><?php esc_html_e('Current global default:', 'authentype-font-specimen'); ?> <code><?php echo esc_html($global_license_url); ?></code>
                <?php else : ?>
                    <br><?php esc_html_e('Current global default: built-in /licenses/#license-slug fallback.', 'authentype-font-specimen'); ?>
                <?php endif; ?>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=ath_font&page=ath-license-url-settings')); ?>"><?php esc_html_e('Manage global default', 'authentype-font-specimen'); ?></a>
            </p>
        </section>

        <section class="ath-admin-section">
            <h3><?php esc_html_e('WooCommerce Advanced', 'authentype-font-specimen'); ?></h3>
            <p>
                <button type="button" class="button button-secondary ath-sync-woo"><?php esc_html_e('Sync from WooCommerce', 'authentype-font-specimen'); ?></button>
            </p>
            <p class="description"><?php esc_html_e('Optional manual sync from an existing WooCommerce variable product. Most new products should use the Product Builder flow above.', 'authentype-font-specimen'); ?></p>
        </section>

        <section class="ath-admin-section">
            <h3><?php esc_html_e('Font Styles', 'authentype-font-specimen'); ?></h3>
            <p class="description"><?php esc_html_e('Upload or paste a font file URL, then Detect can fill style name, weight, style, package flag, and Woo style value from common font filename patterns.', 'authentype-font-specimen'); ?></p>
            <div class="notice notice-warning inline"><p><strong><?php esc_html_e('Secure.7 note:', 'authentype-font-specimen'); ?></strong> <?php esc_html_e('A font uploaded manually to the normal Media Library can still have its own public source URL even though the specimen renderer never exposes that URL. For commercial previews, use Build Secure Assets so preview sources live in protected WooCommerce storage.', 'authentype-font-specimen'); ?></p></div>
            <table class="widefat striped ath-repeat-table" data-next-index="<?php echo esc_attr(max(1, count((array) $styles))); ?>">
                <thead>
                    <tr>
                        <th>Order</th><th>Style Name</th><th>Preview Font File</th><th>Weight</th><th>Style</th><th>Woo Style Value</th><th>Selected</th><th>Package</th><th></th>
                    </tr>
                </thead>
                <tbody id="ath-font-style-rows">
                    <?php
                    if (!empty($styles) && is_array($styles)) {
                        foreach ($styles as $index => $row) {
                            ath_specimen_style_row($index, $row);
                        }
                    } else {
                        ath_specimen_style_row(0);
                    }
                    ?>
                </tbody>
            </table>
            <button type="button" class="button button-secondary ath-add-style"><?php esc_html_e('Add Style', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button button-secondary ath-bulk-upload-styles"><?php esc_html_e('Bulk Upload Styles', 'authentype-font-specimen'); ?></button>
        </section>

        <section class="ath-admin-section">
            <h3><?php esc_html_e('License Options', 'authentype-font-specimen'); ?></h3>
            <div class="ath-smart-import">
                <p class="description"><?php esc_html_e('Smart paste one license per line. Format: Label | woo-value | description | optional group | optional recommended | optional checkout type | optional icon. Icons use the internal set: desktop, web, app, document, server, ads, social, broadcast, merchandise, corporate, enterprise, logo, custom.', 'authentype-font-specimen'); ?></p>
                <textarea class="ath-license-smart-paste" rows="4" placeholder="Desktop | desktop | For local desktop use.&#10;Webfont | webfont | For website embedding.&#10;App | app | For mobile or desktop apps."></textarea>
                <div class="ath-smart-actions">
                    <button type="button" class="button button-secondary ath-license-preset"><?php esc_html_e('Insert Common Set', 'authentype-font-specimen'); ?></button>
                    <button type="button" class="button button-primary ath-apply-license-paste"><?php esc_html_e('Build License Rows', 'authentype-font-specimen'); ?></button>
                    <label><input type="checkbox" class="ath-license-replace" checked> <?php esc_html_e('Replace current rows', 'authentype-font-specimen'); ?></label>
                    <span class="ath-license-smart-status" aria-live="polite"></span>
                </div>
            </div>
            <table class="widefat striped ath-repeat-table ath-license-options-table" data-next-index="<?php echo esc_attr(max(1, count((array) $licenses))); ?>">
                <thead>
                    <tr>
                        <th>Order</th><th>Label</th><th>Woo License Value</th><th>Icon</th><th>Picker Group</th><th>Featured</th><th>Checkout Type</th><th>Description</th><th></th>
                    </tr>
                </thead>
                <tbody id="ath-license-rows">
                    <?php
                    if (!empty($licenses) && is_array($licenses)) {
                        foreach ($licenses as $index => $row) {
                            ath_specimen_license_row($index, $row);
                        }
                    } else {
                        ath_specimen_license_row(0, array('license_label' => 'Desktop', 'license_variation_value' => 'desktop'));
                    }
                    ?>
                </tbody>
            </table>
            <button type="button" class="button button-secondary ath-add-license"><?php esc_html_e('Add License', 'authentype-font-specimen'); ?></button>
        </section>

        <section class="ath-admin-section">
            <h3><?php esc_html_e('Product Download Files', 'authentype-font-specimen'); ?></h3>
            <p class="description"><?php esc_html_e('Files buyers receive after purchase. Leave Style/License empty to apply a file to every variation, or fill Woo Style/License values to attach files only to matching variations during Sync Existing Woo Product.', 'authentype-font-specimen'); ?></p>
            <div class="ath-smart-import">
                <p class="description"><?php esc_html_e('Legacy importer for older package builds. Secure.7 normally writes protected download mappings directly and does not require re-uploading product ZIPs to Media Library.', 'authentype-font-specimen'); ?></p>
                <input type="url" class="ath-package-csv-url" placeholder="https://.../Family-WooCommerce-Variations.csv">
                <button type="button" class="button ath-upload-package-csv"><?php esc_html_e('Upload CSV', 'authentype-font-specimen'); ?></button>
                <button type="button" class="button button-primary ath-import-package-csv"><?php esc_html_e('Import Package CSV', 'authentype-font-specimen'); ?></button>
                <label><input type="checkbox" class="ath-package-csv-replace" checked> <?php esc_html_e('Replace current product files and imported prices', 'authentype-font-specimen'); ?></label>
            </div>
            <table class="widefat striped ath-repeat-table" data-next-index="<?php echo esc_attr(max(1, count((array) $product_downloads))); ?>">
                <thead>
                    <tr>
                        <th>Order</th><th>Download Name</th><th>Product File</th><th>Woo Style Value</th><th>Woo License Value</th><th></th>
                    </tr>
                </thead>
                <tbody id="ath-product-download-rows">
                    <?php
                    if (!empty($product_downloads) && is_array($product_downloads)) {
                        foreach ($product_downloads as $index => $row) {
                            ath_specimen_product_download_row($index, $row);
                        }
                    }
                    ?>
                </tbody>
            </table>
            <button type="button" class="button button-secondary ath-add-product-download"><?php esc_html_e('Add Product File', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button button-secondary ath-bulk-upload-downloads"><?php esc_html_e('Bulk Upload Product Files', 'authentype-font-specimen'); ?></button>
        </section>

        </details>

        <section class="ath-admin-section">
            <h3><?php esc_html_e('Font Preview Settings', 'authentype-font-specimen'); ?></h3>
            <div class="ath-admin-grid">
                <label>
                    <span><?php esc_html_e('Free Downloads Below Font Pairs', 'authentype-font-specimen'); ?></span>
                    <label><input type="checkbox" name="ath_free_downloads_below_pairs" value="1" <?php checked(!empty($free_downloads_enabled)); ?>> <?php esc_html_e('Show free download cards below Font Pairs on this specimen.', 'authentype-font-specimen'); ?></label>
                </label>
                <label>
                    <span><?php esc_html_e('Free Download Type', 'authentype-font-specimen'); ?></span>
                    <select name="ath_free_downloads_type">
                        <option value="" <?php selected($free_downloads_type, ''); ?>><?php esc_html_e('All types', 'authentype-font-specimen'); ?></option>
                        <?php if (function_exists('ath_free_download_types')) : ?>
                            <?php foreach (ath_free_download_types() as $value => $label) : ?>
                                <option value="<?php echo esc_attr($value); ?>" <?php selected($free_downloads_type, $value); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Free Download Limit', 'authentype-font-specimen'); ?></span>
                    <input type="number" name="ath_free_downloads_limit" value="<?php echo esc_attr($free_downloads_limit); ?>" min="1" max="48" step="1">
                </label>
            </div>
            <p class="description"><?php esc_html_e('This embeds the same secure email-gated Free Downloads section automatically below Font Pairs. You can still use [authentype_free_downloads] manually on other pages.', 'authentype-font-specimen'); ?></p>
        </section>

        <section class="ath-admin-section">
            <h3><?php esc_html_e('Pairing Fonts', 'authentype-font-specimen'); ?></h3>
            <p class="description"><?php esc_html_e('Optional custom fonts for Font Pairs. Choose whether each font appears in Title Font, Body Font, or both. Default Title and Default Body can preselect custom pairing fonts for each dropdown. These are for preview only and are not added to cart.', 'authentype-font-specimen'); ?></p>
            <table class="widefat striped ath-repeat-table ath-pairing-font-table" data-next-index="<?php echo esc_attr(max(1, count((array) $pairing_fonts))); ?>">
                <thead>
                    <tr>
                        <th>Order</th><th>Pair Font Name</th><th>Font File</th><th>Weight</th><th>Style</th><th>Product URL</th><th>Title</th><th>Body</th><th>Default Title</th><th>Default Body</th><th></th>
                    </tr>
                </thead>
                <tbody id="ath-pairing-font-rows">
                    <?php
                    if (!empty($pairing_fonts) && is_array($pairing_fonts)) {
                        foreach ($pairing_fonts as $index => $row) {
                            ath_specimen_pairing_font_row($index, $row);
                        }
                    }
                    ?>
                </tbody>
            </table>
            <button type="button" class="button button-secondary ath-add-pairing-font"><?php esc_html_e('Add Pairing Font', 'authentype-font-specimen'); ?></button>
        </section>

        <section class="ath-admin-section">
            <h3><?php esc_html_e('Font Pair Cards', 'authentype-font-specimen'); ?></h3>
            <p class="description"><?php esc_html_e('Curated pair cards shown on the frontend. Choose Title Font and Body Font from the Pairing Fonts library above. If no cards are added, the Font Pairs section is hidden.', 'authentype-font-specimen'); ?></p>
            <table class="widefat striped ath-repeat-table" data-next-index="<?php echo esc_attr(max(1, count((array) $pair_cards))); ?>">
                <thead>
                    <tr>
                        <th>Order</th><th>Title Font</th><th>Body Font</th><th>Heading Text</th><th>Body Text</th><th>Product URL</th><th></th>
                    </tr>
                </thead>
                <tbody id="ath-pair-card-rows">
                    <?php
                    if (!empty($pair_cards) && is_array($pair_cards)) {
                        foreach ($pair_cards as $index => $row) {
                            ath_specimen_pair_card_row($index, $row, $pairing_fonts);
                        }
                    }
                    ?>
                </tbody>
            </table>
            <button type="button" class="button button-secondary ath-add-pair-card"><?php esc_html_e('Add Pair Card', 'authentype-font-specimen'); ?></button>
        </section>

        <p class="description">Shortcode: <code>[authentype_font_specimen]</code></p>
    </div>

    <script type="text/html" id="tmpl-ath-style-row">
        <?php ath_specimen_style_row('__INDEX__'); ?>
    </script>
    <script type="text/html" id="tmpl-ath-license-row">
        <?php ath_specimen_license_row('__INDEX__'); ?>
    </script>
    <script type="text/html" id="tmpl-ath-product-download-row">
        <?php ath_specimen_product_download_row('__INDEX__'); ?>
    </script>
    <script type="text/html" id="tmpl-ath-package-license-row">
        <?php ath_specimen_package_license_row('__INDEX__'); ?>
    </script>
    <script type="text/html" id="tmpl-ath-pairing-font-row">
        <?php ath_specimen_pairing_font_row('__INDEX__'); ?>
    </script>
    <script type="text/html" id="tmpl-ath-pair-card-row">
        <?php ath_specimen_pair_card_row('__INDEX__', array(), $pairing_fonts); ?>
    </script>
    <?php
}

function ath_specimen_sanitize_styles($rows) {
    $clean = array();
    if (!is_array($rows)) return $clean;

    foreach ($rows as $row) {
        $style_name = isset($row['style_name']) ? sanitize_text_field($row['style_name']) : '';
        $font_file = isset($row['font_file']) ? esc_url_raw($row['font_file']) : '';
        if (!$style_name) continue;

        $clean[] = array(
            'style_name' => $style_name,
            'font_file' => $font_file,
            'font_weight' => isset($row['font_weight']) ? min(1000, max(1, absint($row['font_weight']))) : 400,
            'font_style' => isset($row['font_style']) && in_array($row['font_style'], array('normal', 'italic', 'oblique'), true) ? $row['font_style'] : 'normal',
            'style_variation_value' => isset($row['style_variation_value']) ? sanitize_text_field($row['style_variation_value']) : '',
            'default_selected' => !empty($row['default_selected']) ? 1 : 0,
            'is_package' => !empty($row['is_package']) ? 1 : 0,
        );
    }

    return $clean;
}

function ath_specimen_sanitize_licenses($rows) {
    $clean = array();
    if (!is_array($rows)) return $clean;

    foreach ($rows as $row) {
        $label = isset($row['license_label']) ? sanitize_text_field($row['license_label']) : '';
        $value = isset($row['license_variation_value']) ? sanitize_text_field($row['license_variation_value']) : '';
        if (!$label || !$value) continue;

        $group = isset($row['license_group']) ? sanitize_key($row['license_group']) : '';
        if (!in_array($group, ath_specimen_license_group_options(), true)) $group = '';
        $clean[] = array(
            'license_label' => $label,
            'license_variation_value' => $value,
            'license_icon' => isset($row['license_icon']) && isset(ath_specimen_license_icon_options()[sanitize_key($row['license_icon'])]) ? sanitize_key($row['license_icon']) : '',
            'license_description' => isset($row['license_description']) ? sanitize_textarea_field($row['license_description']) : '',
            'license_group' => $group,
            'license_featured' => !empty($row['license_featured']) ? 1 : 0,
            'license_checkout_type' => isset($row['license_checkout_type']) && in_array(sanitize_key($row['license_checkout_type']), ath_specimen_license_checkout_type_options(), true) ? sanitize_key($row['license_checkout_type']) : '',
        );
    }

    return $clean;
}

function ath_specimen_sanitize_price_value($value) {
    if ('' === $value || null === $value) return '';
    $value = is_string($value) ? str_replace(',', '.', trim($value)) : $value;
    $formatted = function_exists('wc_format_decimal') ? wc_format_decimal($value) : preg_replace('/[^0-9.\-]/', '', (string) $value);
    if ('' === (string) $formatted || !is_numeric($formatted) || !is_finite((float) $formatted) || (float) $formatted < 0) return '';
    return (string) $formatted;
}


function ath_specimen_sanitize_price_matrix($rows) {
    $clean = array();
    if (!is_array($rows)) return $clean;

    foreach ($rows as $style_key => $licenses) {
        $style_key = ath_specimen_price_matrix_key($style_key);
        if (!$style_key || !is_array($licenses)) continue;

        foreach ($licenses as $license_key => $prices) {
            $license_key = ath_specimen_price_matrix_key($license_key);
            if (!$license_key || !is_array($prices)) continue;

            $regular = isset($prices['regular']) ? ath_specimen_sanitize_price_value($prices['regular']) : '';
            $sale = isset($prices['sale']) ? ath_specimen_sanitize_price_value($prices['sale']) : '';
            // The plugin owns pricing: Sale is only valid when Regular exists and Sale < Regular.
            if ('' !== $sale && ('' === $regular || (float) $sale <= 0 || (float) $sale >= (float) $regular)) {
                $sale = '';
            }
            if ('' === $regular && '' === $sale) continue;

            if (!isset($clean[$style_key])) {
                $clean[$style_key] = array();
            }

            $clean[$style_key][$license_key] = array(
                'regular' => $regular,
                'sale' => $sale,
            );
        }
    }

    return $clean;
}

/**
 * Validate administrator-entered prices before sanitizing/saving them. This
 * avoids silently converting malformed or contradictory prices into a different
 * commerce state.
 */
function ath_specimen_validate_price_matrix_input($rows) {
    if (!is_array($rows)) {
        return new WP_Error('ath_pricing_invalid', __('Price Matrix payload is invalid. Reload the Athtyp edit page and try again.', 'authentype-font-specimen'));
    }

    foreach ($rows as $style_key => $licenses) {
        if (!is_array($licenses)) continue;
        foreach ($licenses as $license_key => $prices) {
            if (!is_array($prices)) continue;
            $style_label = ath_specimen_price_matrix_key($style_key) ?: sanitize_text_field((string) $style_key);
            $license_label = ath_specimen_price_matrix_key($license_key) ?: sanitize_text_field((string) $license_key);
            $regular_raw = isset($prices['regular']) ? trim(str_replace(',', '.', (string) $prices['regular'])) : '';
            $sale_raw = isset($prices['sale']) ? trim(str_replace(',', '.', (string) $prices['sale'])) : '';

            foreach (array('Regular' => $regular_raw, 'Sale' => $sale_raw) as $kind => $raw) {
                if ('' === $raw) continue;
                if (!is_numeric($raw) || !is_finite((float) $raw) || (float) $raw < 0) {
                    return new WP_Error(
                        'ath_pricing_invalid_value',
                        sprintf(__('%1$s price for %2$s × %3$s must be a non-negative number.', 'authentype-font-specimen'), $kind, $style_label, $license_label)
                    );
                }
            }

            if ('' !== $sale_raw) {
                if ('' === $regular_raw) {
                    return new WP_Error(
                        'ath_pricing_sale_without_regular',
                        sprintf(__('Sale price for %1$s × %2$s requires a Regular price.', 'authentype-font-specimen'), $style_label, $license_label)
                    );
                }
                if ((float) $sale_raw <= 0 || (float) $sale_raw >= (float) $regular_raw) {
                    return new WP_Error(
                        'ath_pricing_sale_range',
                        sprintf(__('Sale price for %1$s × %2$s must be greater than 0 and lower than Regular.', 'authentype-font-specimen'), $style_label, $license_label)
                    );
                }
            }
        }
    }
    return true;
}

function ath_specimen_price_matrix_has_configured_regular($matrix) {
    foreach ((array) $matrix as $licenses) {
        if (!is_array($licenses)) continue;
        foreach ($licenses as $prices) {
            if (!is_array($prices)) continue;
            if (array_key_exists('regular', $prices) && '' !== (string) $prices['regular']) return true;
        }
    }
    return false;
}

function ath_specimen_sanitize_product_downloads($rows) {
    $clean = array();
    if (!is_array($rows)) return $clean;

    foreach ($rows as $row) {
        $download_name = isset($row['download_name']) ? sanitize_text_field($row['download_name']) : '';
        $download_file = isset($row['download_file']) ? esc_url_raw($row['download_file']) : '';
        if (!$download_file) continue;
        $download_id = isset($row['download_id']) ? sanitize_text_field((string) $row['download_id']) : '';
        if (strlen($download_id) > 200) $download_id = substr($download_id, 0, 200);

        $clean[] = array(
            'download_id' => $download_id,
            'legacy_download' => !empty($row['legacy_download']) ? 1 : 0,
            'download_name' => $download_name,
            'download_file' => $download_file,
            'style_variation_value' => isset($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : '',
            'license_variation_value' => isset($row['license_variation_value']) ? ath_specimen_slug($row['license_variation_value']) : '',
        );
    }
    return $clean;
}

/**
 * Return only Style × License pairs backed by an actual style-specific font
 * delivery or Full Style package. License/document rows deliberately use an
 * empty style and must not create a purchasable Woo variation by themselves.
 */
function ath_specimen_product_download_delivery_pairs($rows) {
    $pairs = array();
    foreach ((array) $rows as $row) {
        if (!is_array($row) || empty($row['download_file'])) continue;
        $style = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : '';
        $license = !empty($row['license_variation_value']) ? ath_specimen_slug($row['license_variation_value']) : '';
        if ($style && $license) $pairs[$style . '|' . $license] = true;
    }
    return $pairs;
}


function ath_specimen_sanitize_package_builder($row) {
    $row = is_array($row) ? $row : array();
    $clean = array(
        'font_zip' => isset($row['font_zip']) ? esc_url_raw($row['font_zip']) : '',
        'family_name' => isset($row['family_name']) ? sanitize_text_field($row['family_name']) : '',
        'pricing_mode' => isset($row['pricing_mode']) && 'bundle' === $row['pricing_mode'] ? 'bundle' : 'per_style',
        'preview_format' => isset($row['preview_format']) && in_array($row['preview_format'], array('woff', 'otf', 'ttf', 'auto'), true) ? $row['preview_format'] : 'auto',
        'secure_token' => isset($row['secure_token']) ? ath_specimen_slug($row['secure_token']) : '',
        'licenses' => array(),
    );

    // Legacy template keys remain readable during migration, but all legacy
    // pricing fields are deliberately dropped from Package Builder storage.
    foreach (array('desktop', 'webfont', 'app', 'extended') as $license) {
        $template_key = $license . '_template';
        $clean[$template_key] = isset($row[$template_key]) ? esc_url_raw($row[$template_key]) : '';
    }

    if (!empty($row['licenses']) && is_array($row['licenses'])) {
        foreach ($row['licenses'] as $license) {
            if (!is_array($license)) continue;
            $label = isset($license['license_label']) ? sanitize_text_field($license['license_label']) : '';
            $value = isset($license['license_variation_value']) ? ath_specimen_slug($license['license_variation_value']) : '';
            if (!$label || !$value) continue;
            $clean['licenses'][] = array(
                'license_label' => $label,
                'license_variation_value' => $value,
                'template_zip' => isset($license['template_zip']) ? esc_url_raw($license['template_zip']) : '',
            );
        }
    }

    return $clean;
}

function ath_specimen_sanitize_pairing_fonts($rows) {
    $clean = array();
    if (!is_array($rows)) return $clean;

    foreach ($rows as $row) {
        $pair_name = isset($row['pair_name']) ? sanitize_text_field($row['pair_name']) : '';
        $font_file = isset($row['font_file']) ? esc_url_raw($row['font_file']) : '';
        if (!$pair_name || !$font_file) continue;
        $pair_key = isset($row['pair_key']) ? ath_specimen_slug($row['pair_key']) : '';
        if (!$pair_key) {
            $pair_key = ath_specimen_slug($pair_name);
        }

        $clean[] = array(
            'pair_key' => $pair_key,
            'pair_name' => $pair_name,
            'font_file' => $font_file,
            'font_weight' => isset($row['font_weight']) ? min(1000, max(1, absint($row['font_weight']))) : 400,
            'font_style' => isset($row['font_style']) && in_array($row['font_style'], array('normal', 'italic', 'oblique'), true) ? $row['font_style'] : 'normal',
            'product_url' => isset($row['product_url']) ? esc_url_raw($row['product_url']) : '',
            'default_selected' => !empty($row['default_selected']) ? 1 : 0,
            'use_title' => !empty($row['use_title']) ? 1 : 0,
            'use_body' => !empty($row['use_body']) ? 1 : 0,
            'default_title' => !empty($row['default_title']) ? 1 : 0,
            'default_body' => !empty($row['default_body']) ? 1 : 0,
        );
    }

    return $clean;
}

function ath_specimen_sanitize_pair_cards($rows) {
    $clean = array();
    if (!is_array($rows)) return $clean;

    foreach ($rows as $row) {
        $title_font = isset($row['title_font']) ? ath_specimen_slug($row['title_font']) : '';
        $body_font = isset($row['body_font']) ? ath_specimen_slug($row['body_font']) : '';
        if (!$title_font && !$body_font) continue;

        $clean[] = array(
            'title_font' => $title_font,
            'body_font' => $body_font,
            'heading_text' => isset($row['heading_text']) ? sanitize_text_field($row['heading_text']) : '',
            'body_text' => isset($row['body_text']) ? sanitize_textarea_field($row['body_text']) : '',
            'product_url' => isset($row['product_url']) ? esc_url_raw($row['product_url']) : '',
        );
    }

    return array_slice($clean, 0, 4);
}

add_action('save_post_ath_font', function ($post_id) {
    if (!isset($_POST['ath_specimen_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ath_specimen_meta_nonce'])), 'ath_specimen_save_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!function_exists('authentype_specimen_can_manage_internal') || !authentype_specimen_can_manage_internal()) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $client_workflow_signature = isset($_POST['ath_admin_workflow_signature']) ? sanitize_text_field(wp_unslash($_POST['ath_admin_workflow_signature'])) : '';
    $server_workflow_signature = ath_specimen_admin_workflow_signature($post_id);
    $workflow_safe = $client_workflow_signature && hash_equals((string) $server_workflow_signature, (string) $client_workflow_signature);
    $operation_guard = true;
    $stability_post_lock = '';
    if ($workflow_safe && function_exists('ath_specimen_stability_acquire_post_lock')) {
        $stability_post_lock = ath_specimen_stability_acquire_post_lock($post_id, 'admin_save', 2 * MINUTE_IN_SECONDS);
        if (is_wp_error($stability_post_lock)) {
            $operation_guard = $stability_post_lock;
            $stability_post_lock = '';
            $workflow_safe = false;
        } else {
            $release_post_id = $post_id;
            $release_post_token = $stability_post_lock;
            register_shutdown_function(function () use ($release_post_id, $release_post_token) {
                if (function_exists('ath_specimen_stability_release_post_lock')) ath_specimen_stability_release_post_lock($release_post_id, $release_post_token);
            });
        }
    }
    if ($workflow_safe && function_exists('ath_specimen_stability_cross_engine_guard')) {
        $current_product_id = absint(get_post_meta($post_id, '_ath_linked_product', true));
        $posted_product_id = isset($_POST['ath_linked_product']) ? absint($_POST['ath_linked_product']) : $current_product_id;
        $operation_guard = ath_specimen_stability_cross_engine_guard($post_id, $current_product_id, $stability_post_lock ? array('build') : array());
        if (!is_wp_error($operation_guard) && $posted_product_id && $posted_product_id !== $current_product_id) {
            $operation_guard = ath_specimen_stability_cross_engine_guard($post_id, $posted_product_id, $stability_post_lock ? array('build') : array());
        }
        if (is_wp_error($operation_guard)) $workflow_safe = false;
    }
    $pricing_validation = true;
    if ($workflow_safe && isset($_POST['ath_price_matrix'])) {
        $pricing_validation = ath_specimen_validate_price_matrix_input(wp_unslash($_POST['ath_price_matrix']));
        if (is_wp_error($pricing_validation)) $workflow_safe = false;
    }

    if ($workflow_safe) {
        update_post_meta($post_id, '_ath_linked_product', isset($_POST['ath_linked_product']) ? absint($_POST['ath_linked_product']) : 0);
        update_post_meta($post_id, '_ath_style_attribute', isset($_POST['ath_style_attribute']) ? sanitize_text_field(wp_unslash($_POST['ath_style_attribute'])) : 'pa_style');
        update_post_meta($post_id, '_ath_license_attribute', isset($_POST['ath_license_attribute']) ? sanitize_text_field(wp_unslash($_POST['ath_license_attribute'])) : 'pa_license');
        update_post_meta($post_id, '_ath_default_specimen_style', isset($_POST['ath_default_specimen_style']) ? ath_specimen_slug(wp_unslash($_POST['ath_default_specimen_style'])) : 'regular');
        update_post_meta($post_id, '_ath_font_styles', ath_specimen_sanitize_styles(isset($_POST['ath_font_styles']) ? wp_unslash($_POST['ath_font_styles']) : array()));
        update_post_meta($post_id, '_ath_license_options', ath_specimen_sanitize_licenses(isset($_POST['ath_license_options']) ? wp_unslash($_POST['ath_license_options']) : array()));
        if (isset($_POST['ath_price_matrix'])) ath_specimen_save_pricing_matrix($post_id, wp_unslash($_POST['ath_price_matrix']));
        update_post_meta($post_id, '_ath_package_builder', ath_specimen_sanitize_package_builder(isset($_POST['ath_package_builder']) ? wp_unslash($_POST['ath_package_builder']) : array()));
        update_post_meta($post_id, '_ath_product_downloads', ath_specimen_sanitize_product_downloads(isset($_POST['ath_product_downloads']) ? wp_unslash($_POST['ath_product_downloads']) : array()));
    } else {
        $message = is_wp_error($operation_guard)
            ? $operation_guard->get_error_message() . ' ' . __('Commerce/builder fields were not saved.', 'authentype-font-specimen')
            : (is_wp_error($pricing_validation)
                ? $pricing_validation->get_error_message() . ' ' . __('Commerce/builder fields were not saved.', 'authentype-font-specimen')
                : __('Commerce/builder fields were not saved because this Athtyp edit page became stale after a background asset, pricing, import, or sync change. Reload the page before editing or saving these fields again.', 'authentype-font-specimen'));
        ath_specimen_queue_admin_guard_notice($post_id, $message);
    }
    if ($stability_post_lock && function_exists('ath_specimen_stability_release_post_lock')) {
        ath_specimen_stability_release_post_lock($post_id, $stability_post_lock);
        $stability_post_lock = '';
    }
    if (isset($_POST['ath_license_url_override']) && function_exists('ath_specimen_sanitize_license_url_template')) {
        $license_url_override = ath_specimen_sanitize_license_url_template(wp_unslash($_POST['ath_license_url_override']));
        if ($license_url_override) {
            update_post_meta($post_id, '_ath_license_url_override', $license_url_override);
        } else {
            delete_post_meta($post_id, '_ath_license_url_override');
        }
    }
    update_post_meta($post_id, '_ath_pairing_fonts', ath_specimen_sanitize_pairing_fonts(isset($_POST['ath_pairing_fonts']) ? wp_unslash($_POST['ath_pairing_fonts']) : array()));
    update_post_meta($post_id, '_ath_pair_cards', ath_specimen_sanitize_pair_cards(isset($_POST['ath_pair_cards']) ? wp_unslash($_POST['ath_pair_cards']) : array()));
    update_post_meta($post_id, '_ath_free_downloads_below_pairs', !empty($_POST['ath_free_downloads_below_pairs']) ? '1' : '0');
    $free_downloads_type = isset($_POST['ath_free_downloads_type']) ? sanitize_key(wp_unslash($_POST['ath_free_downloads_type'])) : '';
    if ($free_downloads_type && function_exists('ath_free_download_types') && !array_key_exists($free_downloads_type, ath_free_download_types())) {
        $free_downloads_type = '';
    }
    update_post_meta($post_id, '_ath_free_downloads_type', $free_downloads_type);
    update_post_meta($post_id, '_ath_free_downloads_limit', isset($_POST['ath_free_downloads_limit']) ? min(48, max(1, absint($_POST['ath_free_downloads_limit']))) : 8);
});

function ath_specimen_label_from_slug($slug) {
    $label = str_replace(array('-', '_'), ' ', (string) $slug);
    return ucwords($label);
}

function ath_specimen_product_attribute_values($product, $attribute_key) {
    $values = array();
    if (!$product || !$product->is_type('variable')) return $values;

    $attribute_key = ath_specimen_normalize_attr_key($attribute_key);
    $raw_key = str_replace('attribute_', '', $attribute_key);
    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation) continue;
        $attributes = $variation->get_attributes();
        $raw_value = isset($attributes[$raw_key]) ? $attributes[$raw_key] : (isset($attributes[$attribute_key]) ? $attributes[$attribute_key] : '');
        if ('' === (string) $raw_value) continue;

        $slug = ath_specimen_slug($raw_value);
        if (!$slug) continue;
        $values[$slug] = ath_specimen_label_from_slug($slug);
    }

    ksort($values);
    return $values;
}

function ath_specimen_sort_style_attribute_values($values) {
    if (empty($values) || !is_array($values)) {
        return array();
    }

    uasort($values, function ($label_a, $label_b) {
        $weight = ath_specimen_package_style_sort_key($label_a) - ath_specimen_package_style_sort_key($label_b);
        return $weight ?: strcasecmp((string) $label_a, (string) $label_b);
    });

    return $values;
}

function ath_specimen_attribute_taxonomy($attribute_key) {
    $attribute_key = sanitize_title(str_replace('attribute_', '', (string) $attribute_key));
    return 0 === strpos($attribute_key, 'pa_') ? $attribute_key : 'pa_' . $attribute_key;
}

function ath_specimen_attribute_base_name($attribute_key) {
    $taxonomy = ath_specimen_attribute_taxonomy($attribute_key);
    return preg_replace('/^pa_/', '', $taxonomy);
}

function ath_specimen_ensure_wc_attribute_taxonomy($attribute_key, $label) {
    if (!function_exists('wc_create_attribute')) return 0;

    $name = ath_specimen_attribute_base_name($attribute_key);
    $taxonomy = 'pa_' . $name;
    $attribute_id = function_exists('wc_attribute_taxonomy_id_by_name') ? (int) wc_attribute_taxonomy_id_by_name($name) : 0;

    if (!$attribute_id) {
        $created = wc_create_attribute(array(
            'name' => $label,
            'slug' => $name,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => false,
        ));
        if (is_wp_error($created)) return $created;
        $attribute_id = absint($created);
        if (!$attribute_id) {
            return new WP_Error('ath_woo_attribute_create', sprintf(__('WooCommerce could not create the %s attribute.', 'authentype-font-specimen'), sanitize_text_field($label)));
        }
        delete_transient('wc_attribute_taxonomies');
    }

    if (!taxonomy_exists($taxonomy)) {
        register_taxonomy($taxonomy, array('product'), array(
            'labels' => array('name' => $label),
            'public' => false,
            'show_ui' => false,
            'hierarchical' => false,
            'show_in_nav_menus' => false,
            'query_var' => true,
            'rewrite' => false,
        ));
    }

    return $attribute_id;
}

function ath_specimen_ensure_term($taxonomy, $slug, $label) {
    $slug = ath_specimen_slug($slug);
    if (!$slug || !taxonomy_exists($taxonomy)) return 0;

    $term = get_term_by('slug', $slug, $taxonomy);
    if ($term && !is_wp_error($term)) {
        return (int) $term->term_id;
    }

    $inserted = wp_insert_term($label ?: $slug, $taxonomy, array('slug' => $slug));
    if (is_wp_error($inserted)) {
        $term = get_term_by('slug', $slug, $taxonomy);
        return $term && !is_wp_error($term) ? (int) $term->term_id : 0;
    }

    return !empty($inserted['term_id']) ? (int) $inserted['term_id'] : 0;
}

function ath_specimen_variation_lookup($product) {
    $lookup = array();
    if (!$product || !$product->is_type('variable')) return $lookup;

    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation) continue;

        $attrs = $variation->get_attributes();
        $style = '';
        $license = '';
        foreach ($attrs as $key => $value) {
            $attr_key = ath_specimen_normalize_attr_key($key);
            if (false !== strpos($attr_key, 'style')) {
                $style = ath_specimen_slug($value);
            } elseif (false !== strpos($attr_key, 'license')) {
                $license = ath_specimen_slug($value);
            }
        }
        if ($style && $license) {
            $lookup[$style . '|' . $license] = (int) $variation_id;
        }
    }

    return $lookup;
}

function ath_specimen_matrix_price_values($price_matrix, $style_value, $license_value) {
    $style_value = ath_specimen_slug($style_value);
    $license_value = ath_specimen_slug($license_value);
    $prices = !empty($price_matrix[$style_value][$license_value]) && is_array($price_matrix[$style_value][$license_value])
        ? $price_matrix[$style_value][$license_value]
        : array();

    $regular = isset($prices['regular']) && '' !== $prices['regular'] ? ath_specimen_sanitize_price_value($prices['regular']) : '';
    $sale = isset($prices['sale']) && '' !== $prices['sale'] ? ath_specimen_sanitize_price_value($prices['sale']) : '';

    // WooCommerce sale prices must be positive and lower than regular.
    if ('' !== $sale && ('' === $regular || (float) $sale <= 0 || (float) $sale >= (float) $regular)) {
        $sale = '';
    }

    return array(
        'regular' => (string) $regular,
        'sale' => (string) $sale,
        'active' => '' !== $sale ? (string) $sale : (string) $regular,
    );
}

function ath_specimen_apply_matrix_price_to_variation($variation, $price_matrix, $style_value, $license_value) {
    if (!$variation) return false;

    // Athtyp Price Matrix is the single pricing authority. Keep the exact same
    // normalization in sync and runtime mirror verification so Woo drift can be
    // detected without changing the storefront pricing model.
    $expected = ath_specimen_matrix_price_values($price_matrix, $style_value, $license_value);
    $regular = $expected['regular'];
    $sale = $expected['sale'];
    $active = $expected['active'];

    // WooCommerce stores the active price separately from regular/sale price.
    // Mirror all three props explicitly so a valid Athtyp matrix price never
    // remains non-purchasable because `_price` is empty/stale after a sync.
    $current_regular = (string) $variation->get_regular_price('edit');
    $current_sale = (string) $variation->get_sale_price('edit');
    $current_active = (string) $variation->get_price('edit');
    $changed = false;

    if ($current_regular !== (string) $regular) {
        $variation->set_regular_price($regular);
        $changed = true;
    }
    if ($current_sale !== (string) $sale) {
        $variation->set_sale_price($sale);
        $changed = true;
    }
    if ($current_active !== (string) $active) {
        $variation->set_price($active);
        $changed = true;
    }

    // Mark synced variations so an administrator can identify plugin-owned pricing.
    if ('athtyp' !== (string) $variation->get_meta('_ath_pricing_authority', true)) {
        $variation->update_meta_data('_ath_pricing_authority', 'athtyp');
        $changed = true;
    }

    return $changed;
}

function ath_specimen_download_name_from_file($url) {
    $path = wp_parse_url($url, PHP_URL_PATH);
    $file = $path ? basename($path) : basename((string) $url);
    $file = preg_replace('/\.[^.]+$/', '', $file);
    return $file ? ath_specimen_label_from_slug($file) : __('Font Files', 'authentype-font-specimen');
}

function ath_specimen_product_download_matches($row, $style_value, $license_value) {
    $row_style = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : '';
    $row_license = !empty($row['license_variation_value']) ? ath_specimen_slug($row['license_variation_value']) : '';

    if ($row_style && $row_style !== $style_value) return false;
    if ($row_license && $row_license !== $license_value) return false;
    return true;
}

function ath_specimen_build_wc_downloads($product_downloads, $style_value, $license_value) {
    $downloads = array();
    if (!class_exists('WC_Product_Download') || empty($product_downloads) || !is_array($product_downloads)) {
        return $downloads;
    }

    foreach ($product_downloads as $row) {
        if (empty($row['download_file']) || !ath_specimen_product_download_matches($row, $style_value, $license_value)) {
            continue;
        }

        $download = new WC_Product_Download();
        $download_id = !empty($row['download_id']) ? sanitize_text_field((string) $row['download_id']) : md5($style_value . '|' . $license_value . '|' . $row['download_file']);
        $download->set_id($download_id);
        $download->set_name(!empty($row['download_name']) ? $row['download_name'] : ath_specimen_download_name_from_file($row['download_file']));
        $download->set_file($row['download_file']);
        $downloads[] = $download;
    }

    return $downloads;
}

function ath_specimen_wc_download_signature($downloads) {
    $signature = array();
    foreach ((array) $downloads as $download) {
        if (!is_object($download)) continue;
        $id = method_exists($download, 'get_id') ? (string) $download->get_id() : '';
        $name = method_exists($download, 'get_name') ? (string) $download->get_name() : '';
        $file = method_exists($download, 'get_file') ? (string) $download->get_file() : '';
        $signature[] = $id . '|' . $name . '|' . $file;
    }
    sort($signature, SORT_STRING);
    return $signature;
}

function ath_specimen_apply_downloads_to_variation($variation, $product_downloads, $style_value, $license_value) {
    if (!$variation) return false;

    $product_downloads = is_array($product_downloads) ? $product_downloads : array();
    $downloads = ath_specimen_build_wc_downloads($product_downloads, $style_value, $license_value);
    $current_downloads = method_exists($variation, 'get_downloads') ? $variation->get_downloads('edit') : array();
    $changed = false;

    // secure.7.3.12: an empty mapping means "no current download", not
    // "leave the old Woo file untouched". This prevents stale font files from
    // surviving a license/style mapping change.
    if (empty($downloads)) {
        if (!empty($current_downloads)) {
            $variation->set_downloads(array());
            $changed = true;
        }
        if ($variation->get_downloadable('edit')) {
            $variation->set_downloadable(false);
            $changed = true;
        }
        if ('1' !== (string) $variation->get_meta('_ath_delivery_missing', true)) {
            $variation->update_meta_data('_ath_delivery_missing', '1');
            $changed = true;
        }
        return $changed;
    }

    if ('1' === (string) $variation->get_meta('_ath_delivery_missing', true)) {
        $variation->delete_meta_data('_ath_delivery_missing');
        $changed = true;
    }

    // Validate explicitly so new and existing variations fail consistently and
    // WooCommerce can auto-register an approved download directory for admins.
    foreach ($downloads as $download) {
        if (is_object($download) && method_exists($download, 'check_is_valid')) {
            $download->check_is_valid(true);
        }
    }

    if (ath_specimen_wc_download_signature($current_downloads) !== ath_specimen_wc_download_signature($downloads)) {
        $variation->set_downloads($downloads);
        $changed = true;
    }
    if (!$variation->get_virtual('edit')) {
        $variation->set_virtual(true);
        $changed = true;
    }
    if (!$variation->get_downloadable('edit')) {
        $variation->set_downloadable(true);
        $changed = true;
    }
    return $changed;
}

function ath_specimen_read_url_contents($url) {
    $url = esc_url_raw($url);
    if (!$url) return false;
    $path = ath_specimen_local_upload_path($url);
    return $path ? file_get_contents($path) : false;
}

function ath_specimen_csv_column($row, $name) {
    $wanted = strtolower(trim($name));
    foreach ($row as $key => $value) {
        if (strtolower(trim((string) $key)) === $wanted) {
            return trim((string) $value);
        }
    }
    return '';
}

function ath_specimen_find_attachment_url_by_filename($filename) {
    global $wpdb;

    $filename = basename((string) $filename);
    if (!$filename || !$wpdb) return '';

    $like = '%' . $wpdb->esc_like($filename);
    $attachment_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s ORDER BY post_id DESC LIMIT 1",
        $like
    ));

    if (!$attachment_id) {
        $attachment_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s ORDER BY ID DESC LIMIT 1",
            $like
        ));
    }

    return $attachment_id ? wp_get_attachment_url($attachment_id) : '';
}

function ath_specimen_import_package_csv($post_id, $csv_url, $replace = true) {
    $csv = ath_specimen_read_url_contents($csv_url);
    if (!$csv) {
        return new WP_Error('ath_csv_missing', __('Could not read the package CSV.', 'authentype-font-specimen'));
    }

    $handle = fopen('php://temp', 'r+');
    if (!$handle) {
        return new WP_Error('ath_csv_temp', __('Could not open a temporary CSV buffer.', 'authentype-font-specimen'));
    }
    fwrite($handle, $csv);
    rewind($handle);

    $headers = fgetcsv($handle);
    if (empty($headers) || !is_array($headers)) {
        fclose($handle);
        return new WP_Error('ath_csv_headers', __('CSV header row is missing.', 'authentype-font-specimen'));
    }

    $downloads = $replace ? array() : ath_specimen_get_meta($post_id, '_ath_product_downloads', array());
    $downloads = is_array($downloads) ? $downloads : array();
    $price_matrix = $replace ? array() : ath_specimen_get_meta($post_id, '_ath_price_matrix', array());
    $price_matrix = is_array($price_matrix) ? $price_matrix : array();
    $licenses = ath_specimen_get_meta($post_id, '_ath_license_options', array());
    $licenses = is_array($licenses) ? $licenses : array();
    $license_values = array();
    foreach ($licenses as $license) {
        if (!empty($license['license_variation_value'])) {
            $license_values[ath_specimen_slug($license['license_variation_value'])] = true;
        }
    }

    $imported = 0;
    $matched = 0;
    $unmatched = array();

    while (($values = fgetcsv($handle)) !== false) {
        if (!is_array($values) || count(array_filter($values, 'strlen')) < 1) continue;
        $row = array_combine($headers, array_pad($values, count($headers), ''));
        if (!$row) continue;

        $license_label = ath_specimen_csv_column($row, 'License');
        $style_label = ath_specimen_csv_column($row, 'Style');
        $price = ath_specimen_csv_column($row, 'Regular Price');
        $download_file = ath_specimen_csv_column($row, 'Download File');
        $download_path = ath_specimen_csv_column($row, 'Download Path');

        $license_value = ath_specimen_slug($license_label);
        $style_value = ath_specimen_slug($style_label);
        if (!$license_value || !$style_value) continue;

        if (empty($license_values[$license_value])) {
            $licenses[] = array(
                'license_label' => $license_label,
                'license_variation_value' => $license_value,
                'license_description' => '',
            );
            $license_values[$license_value] = true;
        }

        if ('' !== $price) {
            if (!isset($price_matrix[$style_value])) {
                $price_matrix[$style_value] = array();
            }
            $price_matrix[$style_value][$license_value] = array(
                'regular' => ath_specimen_sanitize_price_value($price),
                'sale' => '',
            );
        }

        $file_name = $download_file ? $download_file : basename((string) $download_path);
        $file_url = ath_specimen_find_attachment_url_by_filename($file_name);
        if ($file_url) {
            $matched++;
        } else {
            $unmatched[] = $file_name;
        }

        $downloads[] = array(
            'download_name' => $license_label . ' - ' . $style_label,
            'download_file' => $file_url,
            'style_variation_value' => $style_value,
            'license_variation_value' => $license_value,
        );
        $imported++;
    }
    fclose($handle);

    update_post_meta($post_id, '_ath_license_options', ath_specimen_sanitize_licenses($licenses));
    update_post_meta($post_id, '_ath_price_matrix', ath_specimen_sanitize_price_matrix($price_matrix));
    update_post_meta($post_id, '_ath_product_downloads', ath_specimen_sanitize_product_downloads($downloads));

    return array(
        'imported' => $imported,
        'matched' => $matched,
        'unmatched' => array_values(array_unique(array_filter($unmatched))),
    );
}

add_action('wp_ajax_ath_specimen_import_package_csv', function () {
    check_ajax_referer('ath_specimen_admin', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!authentype_specimen_can_manage_internal() || !$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    }

    $csv_url = isset($_POST['csv_url']) ? esc_url_raw(wp_unslash($_POST['csv_url'])) : '';
    $replace = !empty($_POST['replace']);
    $result = ath_specimen_import_package_csv($post_id, $csv_url, $replace);
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()), 400);
    }

    $message = sprintf(
        __('Package CSV imported. %1$d rows read, %2$d files matched in Media Library.', 'authentype-font-specimen'),
        (int) $result['imported'],
        (int) $result['matched']
    );
    if (!empty($result['unmatched'])) {
        $message .= ' ' . sprintf(__('%d files still need to be uploaded or matched by filename.', 'authentype-font-specimen'), count($result['unmatched']));
    }

    wp_send_json_success(array(
        'message' => $message,
        'unmatched' => $result['unmatched'],
    ));
});

function ath_specimen_uploaded_url_to_path($url) {
    $url = esc_url_raw($url);
    return $url ? ath_specimen_local_upload_path($url) : '';
}

function ath_specimen_delete_public_source_zip($url) {
    $url = esc_url_raw($url);
    $path = ath_specimen_uploaded_url_to_path($url);
    if (!$url || !$path || 'zip' !== strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        return false;
    }

    $attachment_id = attachment_url_to_postid($url);
    if ($attachment_id && wp_normalize_path((string) get_attached_file($attachment_id)) === wp_normalize_path($path)) {
        return (bool) wp_delete_attachment($attachment_id, true);
    }

    wp_delete_file($path);
    return !file_exists($path);
}

function ath_specimen_package_secure_token($existing = '') {
    $existing = ath_specimen_slug($existing);
    if ($existing) return $existing;

    if (function_exists('wp_generate_password')) {
        return strtolower(wp_generate_password(18, false, false));
    }

    return substr(md5(uniqid('', true)), 0, 18);
}

function ath_specimen_protect_download_dir($dir) {
    if (!$dir) return;

    wp_mkdir_p($dir);

    $index_path = trailingslashit($dir) . 'index.html';
    if (!file_exists($index_path)) {
        file_put_contents($index_path, '');
    }
    $index_php = trailingslashit($dir) . 'index.php';
    if (!file_exists($index_php)) {
        file_put_contents($index_php, "<?php http_response_code(403); exit;\n");
    }

    $htaccess_path = trailingslashit($dir) . '.htaccess';
    if (!file_exists($htaccess_path)) {
        file_put_contents(
            $htaccess_path,
            "Options -Indexes\n<FilesMatch \".*\">\n    Require all denied\n</FilesMatch>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n"
        );
    }

    // IIS equivalent. Nginx intentionally requires a server-level location
    // rule or WooCommerce Force Downloads/X-Accel-Redirect; PHP cannot safely
    // install an nginx configuration from a WordPress plugin.
    $web_config = trailingslashit($dir) . 'web.config';
    if (!file_exists($web_config)) {
        file_put_contents(
            $web_config,
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><remove users=\"*\" roles=\"\" verbs=\"\"/><add accessType=\"Deny\" users=\"*\"/></authorization></security><directoryBrowse enabled=\"false\"/></system.webServer></configuration>\n"
        );
    }
}

function ath_specimen_is_legacy_public_preview_url($post_id, $url) {
    $post_id = absint($post_id);
    $url = is_scalar($url) ? (string) $url : '';
    if (!$post_id || !$url) return false;

    $uploads = wp_get_upload_dir();
    if (empty($uploads['baseurl'])) return false;
    $candidate = wp_parse_url($url);
    $base = wp_parse_url($uploads['baseurl']);
    if (!is_array($candidate) || !is_array($base) || empty($candidate['host']) || empty($candidate['path']) || empty($base['host']) || empty($base['path'])) return false;
    if (strtolower($candidate['host']) !== strtolower($base['host'])) return false;

    $base_path = rtrim(rawurldecode($base['path']), '/');
    $path = rawurldecode($candidate['path']);
    if (0 !== strpos($path, $base_path . '/')) return false;
    $relative = ltrim(substr($path, strlen($base_path)), '/');
    return 0 === strpos($relative, 'authentype-packages/' . $post_id . '/') && false !== strpos($relative, '/preview/');
}

function ath_specimen_remove_legacy_public_preview_dir($post_id, $family_slug) {
    $post_id = absint($post_id);
    $family_slug = sanitize_file_name((string) $family_slug);
    if (!$post_id || !$family_slug) return;

    $uploads = wp_get_upload_dir();
    if (empty($uploads['basedir'])) return;
    $base_real = realpath($uploads['basedir']);
    if (false === $base_real) return;

    $preview_dir = trailingslashit($base_real) . 'authentype-packages/' . $post_id . '/' . $family_slug . '/preview';
    $parent_dir = dirname($preview_dir);
    if (!is_dir($preview_dir)) return;

    $normalized_base = rtrim(wp_normalize_path($base_real), '/') . '/';
    $normalized_preview = rtrim(wp_normalize_path($preview_dir), '/') . '/';
    if (0 !== strpos($normalized_preview, $normalized_base . 'authentype-packages/' . $post_id . '/')) return;

    foreach ((array) glob($preview_dir . '/*') as $file) {
        if (!is_file($file)) continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, array('woff', 'woff2', 'otf', 'ttf'), true)) wp_delete_file($file);
    }
    @rmdir($preview_dir);
    @rmdir($parent_dir);
}

function ath_specimen_package_font_ext($name) {
    $name = strtolower((string) $name);
    if (preg_match('/\.woff2$/', $name)) return 'woff2';
    if (preg_match('/\.woff$/', $name)) return 'woff';
    if (preg_match('/\.otf$/', $name)) return 'otf';
    if (preg_match('/\.ttf$/', $name)) return 'ttf';
    return '';
}

function ath_specimen_package_preview_exts($preview_format) {
    $preview_format = in_array($preview_format, array('woff', 'otf', 'ttf', 'auto'), true) ? $preview_format : 'auto';

    // Server previews are rasterized by Imagick or GD/FreeType. WOFF is a
    // delivery format and is not a dependable raster source; GD explicitly
    // supports TTF only. Prefer formats the active server can actually draw.
    $fallbacks = class_exists('Imagick') && class_exists('ImagickDraw')
        ? array('ttf', 'otf', 'woff')
        : (function_exists('imagettftext') ? array('ttf') : array());

    if ('auto' === $preview_format) {
        return $fallbacks;
    }

    if (empty($fallbacks)) {
        return array();
    }

    // A requested format that the active engine cannot reliably rasterize
    // stays readable in saved settings, but compatible candidates must win.
    if ('woff' === $preview_format || !in_array($preview_format, $fallbacks, true)) {
        return array_values(array_unique(array_merge($fallbacks, array($preview_format))));
    }

    return array_values(array_unique(array_merge(array($preview_format), $fallbacks)));
}

function ath_specimen_package_style_key($style) {
    $key = ath_specimen_slug(str_replace(array('_', '+'), ' ', (string) $style));
    $compact = str_replace('-', '', $key);
    if (in_array($compact, array('fullstyle', 'allstyles', 'familypack', 'completefamily', 'bundlefullstyle'), true)) {
        return 'full-style';
    }
    return $key;
}

function ath_specimen_package_is_junk($path) {
    $path = str_replace('\\', '/', (string) $path);
    $name = basename($path);
    return !$name || '/' === substr($path, -1) || false !== strpos($path, '__MACOSX/') || '.DS_Store' === $name || 0 === strpos($name, '._');
}

/**
 * Return a style hint from a one-level nested archive name.
 *
 * Examples:
 *   Family-Regular.zip -> Regular
 *   Bold.zip           -> Bold
 * Generic package names such as OTF.zip/Webfont.zip intentionally return an
 * empty hint so the actual font filename remains authoritative.
 */
function ath_specimen_package_nested_archive_style($archive_name, $family) {
    $stem = preg_replace('/\\.zip$/i', '', basename((string) $archive_name));
    $stem = trim(str_replace(array('_', '+'), ' ', (string) $stem));
    if (!$stem) return '';

    $key = ath_specimen_slug($stem);
    $family_key = ath_specimen_slug((string) $family);
    $generic = array(
        'otf', 'ttf', 'woff', 'woff2', 'webfont', 'webfonts', 'desktop',
        'font', 'fonts', 'file', 'files', 'family', 'package', 'packages',
        'source', 'sources', 'download', 'downloads', 'complete', 'all-formats',
    );
    if (!$key || $key === $family_key || in_array($key, $generic, true)) return '';

    return ath_specimen_package_style_name($stem . '.otf', $family);
}

/**
 * Materialize one ZIP entry into a private system-temp file without extracting
 * its internal paths. This is used only for one-level nested style archives.
 */
function ath_specimen_zip_index_to_private_temp($zip, $index, $expected_size = 0, $max_size = 0) {
    if (!($zip instanceof ZipArchive)) {
        return new WP_Error('ath_nested_zip_source', __('Nested ZIP source is unavailable.', 'authentype-font-specimen'));
    }

    $index = (int) $index;
    $stat = $zip->statIndex($index);
    $name = $zip->getNameIndex($index);
    if (!$stat || false === $name) {
        return new WP_Error('ath_nested_zip_stat', __('Could not inspect a nested style ZIP.', 'authentype-font-specimen'));
    }

    $size = isset($stat['size']) ? (int) $stat['size'] : 0;
    $max_size = $max_size > 0 ? (int) $max_size : max(1024, (int) apply_filters('authentype_specimen_nested_zip_max_archive_size', 64 * 1024 * 1024));
    if ($size <= 0 || $size > $max_size || ($expected_size > 0 && (int) $expected_size !== $size)) {
        return new WP_Error('ath_nested_zip_size', __('A nested style ZIP exceeds the configured size limit or changed during processing.', 'authentype-font-specimen'));
    }

    $temp_dir = sys_get_temp_dir();
    if (!$temp_dir || !is_dir($temp_dir) || !is_writable($temp_dir)) {
        return new WP_Error('ath_nested_zip_temp', __('Private system temporary storage is unavailable for nested ZIP scanning.', 'authentype-font-specimen'));
    }

    $temp = tempnam($temp_dir, 'ath-nested-');
    if (!$temp) {
        return new WP_Error('ath_nested_zip_temp', __('Could not create a private temporary file for nested ZIP scanning.', 'authentype-font-specimen'));
    }
    @chmod($temp, 0600);

    $stream = method_exists($zip, 'getStreamIndex') ? $zip->getStreamIndex($index) : $zip->getStream($name);
    $out = @fopen($temp, 'wb');
    if (!$stream || !$out) {
        if (is_resource($stream)) fclose($stream);
        if (is_resource($out)) fclose($out);
        @unlink($temp);
        return new WP_Error('ath_nested_zip_read', __('Could not read a nested style ZIP.', 'authentype-font-specimen'));
    }

    $written = 0;
    $ok = true;
    while (!feof($stream)) {
        $chunk = fread($stream, 1024 * 1024);
        if (false === $chunk) { $ok = false; break; }
        if ('' === $chunk) continue;
        $written += strlen($chunk);
        if ($written > $max_size) { $ok = false; break; }
        if (strlen($chunk) !== fwrite($out, $chunk)) { $ok = false; break; }
    }
    fclose($stream);
    fclose($out);

    if (!$ok || $written !== $size) {
        @unlink($temp);
        return new WP_Error('ath_nested_zip_read', __('A nested style ZIP could not be copied safely for scanning.', 'authentype-font-specimen'));
    }

    return $temp;
}

function ath_specimen_package_style_name($filename, $family) {
    $stem = preg_replace('/\.(woff2?|otf|ttf)$/i', '', basename((string) $filename));
    $family = trim((string) $family);
    if ($family && 0 === stripos($stem, $family . '-')) {
        $stem = substr($stem, strlen($family) + 1);
    } elseif (false !== strpos($stem, '-')) {
        $stem = substr($stem, strpos($stem, '-') + 1);
    }
    $stem = trim(str_replace(array('_', '+'), ' ', $stem));
    return $stem ? $stem : 'Regular';
}

function ath_specimen_package_style_sort_key($style) {
    $slug = ath_specimen_slug(str_replace(array(' ', '_'), '', (string) $style));
    $normalized = str_replace('-', '', $slug);
    if (in_array($normalized, array('fullstyle', 'allstyles', 'familypack', 'completefamily', 'bundlefullstyle'), true)) {
        return 1000;
    }

    $weight_order = array(
        100 => 10,
        200 => 20,
        300 => 30,
        400 => 40,
        500 => 50,
        600 => 60,
        700 => 70,
        800 => 80,
        900 => 90,
    );
    $weight = ath_specimen_package_weight($style);
    $sort = isset($weight_order[$weight]) ? $weight_order[$weight] : 990;
    if (false !== stripos((string) $style, 'italic') || false !== stripos((string) $style, 'oblique')) {
        $sort += 1;
    }

    return $sort;
}

function ath_specimen_package_weight($style) {
    $style = strtolower(str_replace(array(' ', '-'), '', (string) $style));
    if (false !== strpos($style, 'thin')) return 100;
    if (false !== strpos($style, 'extralight') || false !== strpos($style, 'ultralight')) return 200;
    if (false !== strpos($style, 'light')) return 300;
    if (false !== strpos($style, 'medium')) return 500;
    if (false !== strpos($style, 'semibold') || false !== strpos($style, 'demibold')) return 600;
    if (false !== strpos($style, 'extrabold') || false !== strpos($style, 'ultrabold')) return 800;
    if (false !== strpos($style, 'black') || false !== strpos($style, 'heavy')) return 900;
    if (false !== strpos($style, 'bold')) return 700;
    return 400;
}

function ath_specimen_package_format_group($ext, $family) {
    if (in_array($ext, array('otf', 'ttf'), true)) return $family . '-OTF-TTF';
    if (in_array($ext, array('woff', 'woff2'), true)) return $family . '-WOFF-WOFF2';
    return strtoupper($ext);
}

function ath_specimen_package_style_group($style, $ext, $family) {
    $style = str_replace(array(' ', '-'), '', (string) $style);
    if (in_array($ext, array('otf', 'ttf'), true)) return $family . $style . '-OTF-TTF';
    if (in_array($ext, array('woff', 'woff2'), true)) return $family . $style . '-WOFF-WOFF2';
    return $family . $style . '-' . strtoupper($ext);
}

function ath_specimen_package_license_exts($license_value) {
    $license_value = ath_specimen_slug($license_value);
    if ('desktop' === $license_value) return apply_filters('authentype_specimen_license_extensions', array('otf', 'ttf'), $license_value);
    if ('webfont' === $license_value) return apply_filters('authentype_specimen_license_extensions', array('woff', 'woff2'), $license_value);
    if (in_array($license_value, array('app', 'application', 'epub', 'e-pub', 'ebook'), true)) {
        return apply_filters('authentype_specimen_license_extensions', array('otf', 'ttf'), $license_value);
    }
    return apply_filters('authentype_specimen_license_extensions', array('otf', 'ttf', 'woff', 'woff2'), $license_value);
}

function ath_specimen_read_font_family_zip($zip_path, $family) {
    if (!class_exists('ZipArchive')) {
        return new WP_Error('ath_zip_missing', __('PHP ZipArchive is not available on this server.', 'authentype-font-specimen'));
    }

    $zip = new ZipArchive();
    if (true !== $zip->open($zip_path)) {
        return new WP_Error('ath_zip_open', __('Could not open the font family ZIP.', 'authentype-font-specimen'));
    }

    $entries = array();
    $max_entries = max(1, (int) apply_filters('authentype_specimen_zip_max_entries', 600));
    $max_font_entries = max($max_entries, (int) apply_filters('authentype_specimen_zip_max_font_entries', 2400));
    $max_entry_size = max(1024, (int) apply_filters('authentype_specimen_zip_max_entry_size', 32 * 1024 * 1024));
    $max_total_size = max($max_entry_size, (int) apply_filters('authentype_specimen_zip_max_total_size', 512 * 1024 * 1024));
    $max_ratio = max(1, (int) apply_filters('authentype_specimen_zip_max_compression_ratio', 100));
    $max_nested_archives = max(1, (int) apply_filters('authentype_specimen_nested_zip_max_archives', 500));
    $max_nested_entries = max(1, (int) apply_filters('authentype_specimen_nested_zip_max_entries', 64));
    $max_nested_archive_size = max(1024, (int) apply_filters('authentype_specimen_nested_zip_max_archive_size', 64 * 1024 * 1024));
    $max_nested_total = max($max_nested_archive_size, (int) apply_filters('authentype_specimen_nested_zip_max_total_size', 512 * 1024 * 1024));
    $total_size = 0;
    $nested_total = 0;
    $nested_count = 0;
    $font_count = 0;

    if ($zip->numFiles <= 0 || $zip->numFiles > $max_entries) {
        $zip->close();
        return new WP_Error('ath_zip_entries', __('The ZIP contains too many top-level entries.', 'authentype-font-specimen'));
    }

    $validate_font_stat = function ($stat) use (&$total_size, &$font_count, $max_entry_size, $max_total_size, $max_ratio, $max_font_entries) {
        if (!$stat) return new WP_Error('ath_zip_font_stat', __('A font file in the ZIP could not be inspected.', 'authentype-font-specimen'));
        $size = isset($stat['size']) ? (int) $stat['size'] : 0;
        $compressed_size = isset($stat['comp_size']) ? (int) $stat['comp_size'] : 0;
        if ($size <= 0 || $size > $max_entry_size) {
            return new WP_Error('ath_zip_entry_size', __('A font file in the ZIP exceeds the configured size limit.', 'authentype-font-specimen'));
        }
        if ($compressed_size > 0 && ($size / $compressed_size) > $max_ratio) {
            return new WP_Error('ath_zip_ratio', __('The ZIP contains a suspiciously compressed font entry.', 'authentype-font-specimen'));
        }
        $font_count++;
        if ($font_count > $max_font_entries) {
            return new WP_Error('ath_zip_font_entries', __('The combined family ZIP contains too many font files.', 'authentype-font-specimen'));
        }
        $total_size += $size;
        if ($total_size > $max_total_size) {
            return new WP_Error('ath_zip_total_size', __('The font files expand beyond the configured total size limit.', 'authentype-font-specimen'));
        }
        return $size;
    };

    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);
        if (ath_specimen_package_is_junk($name)) continue;

        // Existing behavior: direct font files can live at root or in folders.
        $ext = ath_specimen_package_font_ext($name);
        if ($ext) {
            $size = $validate_font_stat($zip->statIndex($index));
            if (is_wp_error($size)) { $zip->close(); return $size; }
            $entries[] = array(
                'name' => sanitize_file_name(basename($name)),
                'source_name' => sanitize_text_field((string) $name),
                'ext' => $ext,
                'style' => ath_specimen_package_style_name($name, $family),
                'zip_path' => $zip_path,
                'zip_index' => $index,
                'size' => (int) $size,
            );
            continue;
        }

        // New compatibility path: scan exactly one nested ZIP level. The child
        // archive is never extracted by path; only validated font entries are read.
        if (!preg_match('/\\.zip$/i', (string) $name)) continue;

        $nested_count++;
        if ($nested_count > $max_nested_archives) {
            $zip->close();
            return new WP_Error('ath_nested_zip_count', __('The family ZIP contains too many nested ZIP archives.', 'authentype-font-specimen'));
        }

        $outer_stat = $zip->statIndex($index);
        $outer_size = $outer_stat && isset($outer_stat['size']) ? (int) $outer_stat['size'] : 0;
        $outer_comp = $outer_stat && isset($outer_stat['comp_size']) ? (int) $outer_stat['comp_size'] : 0;
        if ($outer_size <= 0 || $outer_size > $max_nested_archive_size) {
            $zip->close();
            return new WP_Error('ath_nested_zip_size', sprintf(__('Nested ZIP "%s" exceeds the configured archive size limit.', 'authentype-font-specimen'), basename((string) $name)));
        }
        if ($outer_comp > 0 && ($outer_size / $outer_comp) > $max_ratio) {
            $zip->close();
            return new WP_Error('ath_nested_zip_ratio', sprintf(__('Nested ZIP "%s" is suspiciously compressed.', 'authentype-font-specimen'), basename((string) $name)));
        }
        $nested_total += $outer_size;
        if ($nested_total > $max_nested_total) {
            $zip->close();
            return new WP_Error('ath_nested_zip_total', __('Nested ZIP archives exceed the configured total size limit.', 'authentype-font-specimen'));
        }

        $temp = ath_specimen_zip_index_to_private_temp($zip, $index, $outer_size, $max_nested_archive_size);
        if (is_wp_error($temp)) { $zip->close(); return $temp; }

        $child = new ZipArchive();
        if (true !== $child->open($temp)) {
            @unlink($temp);
            $zip->close();
            return new WP_Error('ath_nested_zip_open', sprintf(__('Could not open nested ZIP "%s".', 'authentype-font-specimen'), basename((string) $name)));
        }
        if ($child->numFiles > $max_nested_entries) {
            $child->close(); @unlink($temp); $zip->close();
            return new WP_Error('ath_nested_zip_entries', sprintf(__('Nested ZIP "%s" contains too many entries.', 'authentype-font-specimen'), basename((string) $name)));
        }

        $archive_style = ath_specimen_package_nested_archive_style($name, $family);
        $archive_style_key = ath_specimen_package_style_key($archive_style);
        $family_key = ath_specimen_slug((string) $family);
        $child_fonts = 0;
        $deeper_zip = false;

        for ($inner_index = 0; $inner_index < $child->numFiles; $inner_index++) {
            $inner_name = $child->getNameIndex($inner_index);
            if (ath_specimen_package_is_junk($inner_name)) continue;
            if (preg_match('/\\.zip$/i', (string) $inner_name)) {
                $deeper_zip = true;
                continue; // one nested level only; never recurse further.
            }
            $inner_ext = ath_specimen_package_font_ext($inner_name);
            if (!$inner_ext) continue;

            $size = $validate_font_stat($child->statIndex($inner_index));
            if (is_wp_error($size)) {
                $child->close(); @unlink($temp); $zip->close();
                return $size;
            }

            $style = ath_specimen_package_style_name($inner_name, $family);
            $style_key = ath_specimen_package_style_key($style);
            if ($archive_style_key && (!$style_key || $style_key === $family_key || in_array($style_key, array('font', 'fonts', 'typeface'), true))) {
                $style = $archive_style;
            }

            $entries[] = array(
                'name' => sanitize_file_name(basename($inner_name)),
                'source_name' => sanitize_text_field(basename((string) $name) . ' -> ' . (string) $inner_name),
                'ext' => $inner_ext,
                'style' => $style,
                'zip_path' => $zip_path,
                'zip_index' => $index,
                'nested_zip_index' => $inner_index,
                'nested_zip_name' => sanitize_file_name(basename((string) $name)),
                'nested_zip_size' => $outer_size,
                'size' => (int) $size,
            );
            $child_fonts++;
        }

        $child->close();
        @unlink($temp);

        if (0 === $child_fonts && $deeper_zip) {
            $zip->close();
            return new WP_Error('ath_nested_zip_depth', sprintf(__('Nested ZIP "%s" contains another ZIP but no direct font files. Only one nested ZIP level is supported.', 'authentype-font-specimen'), basename((string) $name)));
        }
        // A valid nested ZIP with no fonts is treated as documentation/extras and ignored.
    }
    $zip->close();

    if (empty($entries)) {
        return new WP_Error('ath_zip_fonts', __('No font files found. The Family ZIP may contain OTF/TTF/WOFF/WOFF2 directly, inside folders, or inside one level of per-style nested ZIP archives.', 'authentype-font-specimen'));
    }

    return $entries;
}

function ath_specimen_zip_entry_data($entry) {
    if (empty($entry['zip_path']) || !isset($entry['zip_index']) || !class_exists('ZipArchive')) {
        return false;
    }

    $zip = new ZipArchive();
    if (true !== $zip->open($entry['zip_path'])) {
        return false;
    }

    $max_size = max(1024, (int) apply_filters('authentype_specimen_zip_max_entry_size', 32 * 1024 * 1024));

    // Direct font entry (legacy/current flat ZIP behavior).
    if (!isset($entry['nested_zip_index'])) {
        $stat = $zip->statIndex((int) $entry['zip_index']);
        if (!$stat || empty($stat['size']) || (int) $stat['size'] > $max_size || (!empty($entry['size']) && (int) $entry['size'] !== (int) $stat['size'])) {
            $zip->close();
            return false;
        }
        $data = $zip->getFromIndex((int) $entry['zip_index'], $max_size);
        $zip->close();
        return $data;
    }

    // One-level nested style ZIP. Materialize only the child archive itself to
    // private system temp, then read the validated font by index. No paths from
    // either archive are extracted to the filesystem.
    $max_nested = max(1024, (int) apply_filters('authentype_specimen_nested_zip_max_archive_size', 64 * 1024 * 1024));
    $expected_nested = !empty($entry['nested_zip_size']) ? (int) $entry['nested_zip_size'] : 0;
    $temp = ath_specimen_zip_index_to_private_temp($zip, (int) $entry['zip_index'], $expected_nested, $max_nested);
    $zip->close();
    if (is_wp_error($temp)) return false;

    $child = new ZipArchive();
    if (true !== $child->open($temp)) {
        @unlink($temp);
        return false;
    }
    $inner_index = (int) $entry['nested_zip_index'];
    $stat = $child->statIndex($inner_index);
    if (!$stat || empty($stat['size']) || (int) $stat['size'] > $max_size || (!empty($entry['size']) && (int) $entry['size'] !== (int) $stat['size'])) {
        $child->close(); @unlink($temp);
        return false;
    }
    $data = $child->getFromIndex($inner_index, $max_size);
    $child->close();
    @unlink($temp);
    return $data;
}

function ath_specimen_read_template_zip($zip_url, $license_label = '') {
    $template_path = ath_specimen_uploaded_url_to_path($zip_url);
    if (!$template_path) return new WP_Error('ath_template_path', sprintf(__('The template ZIP for %s is not a readable Media Library file.', 'authentype-font-specimen'), $license_label));
    if (!class_exists('ZipArchive')) return new WP_Error('ath_template_zip_missing', __('PHP ZipArchive is not available on this server.', 'authentype-font-specimen'));

    $zip = new ZipArchive();
    if (true !== $zip->open($template_path)) return new WP_Error('ath_template_open', sprintf(__('The template ZIP for %s could not be opened.', 'authentype-font-specimen'), $license_label));

    $max_entries = max(1, (int) apply_filters('authentype_specimen_template_zip_max_entries', 200));
    $max_entry_size = max(1024, (int) apply_filters('authentype_specimen_template_zip_max_entry_size', 16 * 1024 * 1024));
    $max_total_size = max($max_entry_size, (int) apply_filters('authentype_specimen_template_zip_max_total_size', 64 * 1024 * 1024));
    $max_ratio = max(1, (int) apply_filters('authentype_specimen_template_zip_max_compression_ratio', 100));
    if ($zip->numFiles <= 0 || $zip->numFiles > $max_entries) {
        $zip->close();
        return new WP_Error('ath_template_entries', sprintf(__('The template ZIP for %s contains an invalid number of entries.', 'authentype-font-specimen'), $license_label));
    }

    $docs = array();
    $total_size = 0;
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $name = $zip->getNameIndex($index);
        if (ath_specimen_package_is_junk($name)) continue;
        $file_name = sanitize_file_name(basename($name));
        if (!$file_name) continue;
        $stat = $zip->statIndex($index);
        if (!$stat) continue;
        $size = isset($stat['size']) ? (int) $stat['size'] : 0;
        $compressed_size = isset($stat['comp_size']) ? (int) $stat['comp_size'] : 0;
        if ($size <= 0 || $size > $max_entry_size) {
            $zip->close();
            return new WP_Error('ath_template_entry_size', sprintf(__('A document in the template ZIP for %s exceeds the configured size limit.', 'authentype-font-specimen'), $license_label));
        }
        $total_size += $size;
        if ($total_size > $max_total_size || ($compressed_size > 0 && ($size / $compressed_size) > $max_ratio)) {
            $zip->close();
            return new WP_Error('ath_template_expansion', sprintf(__('The template ZIP for %s exceeds the configured expansion limits.', 'authentype-font-specimen'), $license_label));
        }
        $data = $zip->getFromIndex($index, $max_entry_size);
        if (false === $data || strlen($data) !== $size) {
            $zip->close();
            return new WP_Error('ath_template_read', sprintf(__('A document in the template ZIP for %s could not be read completely.', 'authentype-font-specimen'), $license_label));
        }

        $is_license = false !== stripos($file_name, 'license') || false !== stripos($file_name, 'licence') || false !== stripos($file_name, 'eula') || preg_match('/(^|[-_])ofl([-_.]|$)/i', $file_name);

        $docs[] = array(
            'name' => $file_name,
            'target_dir' => $is_license ? 'License' : 'Documentation',
            'data' => $data,
        );
    }
    $zip->close();

    if (empty($docs)) return new WP_Error('ath_template_empty', sprintf(__('The template ZIP for %s does not contain readable documents.', 'authentype-font-specimen'), $license_label));
    if (!array_filter($docs, function ($doc) { return 'License' === $doc['target_dir']; })) {
        return new WP_Error('ath_template_license_missing', sprintf(__('The template ZIP for %s must contain a license, licence, EULA, or OFL document.', 'authentype-font-specimen'), $license_label));
    }

    return $docs;
}

function ath_specimen_write_package_zip($path, $url, $family, $license_label, $style_label, $entries, $required_exts, $template_docs = array()) {
    $selected = array_filter($entries, function ($entry) use ($style_label, $required_exts) {
        if (!in_array($entry['ext'], $required_exts, true)) return false;
        return 'Full Style' === $style_label || ath_specimen_package_style_key($entry['style']) === ath_specimen_package_style_key($style_label);
    });

    if (empty($selected)) return new WP_Error('ath_package_fonts_missing', sprintf(__('No matching font files are available for %1$s / %2$s.', 'authentype-font-specimen'), $license_label, $style_label));

    $zip = new ZipArchive();
    if (true !== $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        return new WP_Error('ath_package_create', sprintf(__('Could not create the package for %1$s / %2$s.', 'authentype-font-specimen'), $license_label, $style_label));
    }

    $root = sanitize_file_name($family . '-' . $license_label . '-' . $style_label);
    foreach ($selected as $entry) {
        $data = ath_specimen_zip_entry_data($entry);
        if (false === $data) {
            $zip->close();
            wp_delete_file($path);
            return new WP_Error('ath_package_font_read', sprintf(__('A font could not be read while building %1$s / %2$s.', 'authentype-font-specimen'), $license_label, $style_label));
        }
        $target = $root . '/' . ath_specimen_package_format_group($entry['ext'], $family) . '/' . ath_specimen_package_style_group($entry['style'], $entry['ext'], $family) . '/' . $entry['name'];
        if (!$zip->addFromString($target, $data)) {
            unset($data);
            $zip->close();
            wp_delete_file($path);
            return new WP_Error('ath_package_font_write', sprintf(__('A font could not be written while building %1$s / %2$s.', 'authentype-font-specimen'), $license_label, $style_label));
        }
        unset($data);
    }
    $font_list = implode("\n", wp_list_pluck($selected, 'name')) . "\n";
    if (!$zip->addFromString($root . '/Documentation/Font_List.txt', $font_list)) {
        $zip->close();
        wp_delete_file($path);
        return new WP_Error('ath_package_manifest', __('Could not write the package font manifest.', 'authentype-font-specimen'));
    }

    foreach ($template_docs as $doc) {
        if (empty($doc['name']) || !isset($doc['data'])) continue;
        $target_dir = !empty($doc['target_dir']) && 'License' === $doc['target_dir'] ? 'License' : 'Documentation';
        if (!$zip->addFromString($root . '/' . $target_dir . '/' . sanitize_file_name($doc['name']), $doc['data'])) {
            $zip->close();
            wp_delete_file($path);
            return new WP_Error('ath_package_document', sprintf(__('A document could not be written while building %1$s / %2$s.', 'authentype-font-specimen'), $license_label, $style_label));
        }
    }

    if (!$zip->close() || !is_file($path) || filesize($path) <= 0) {
        wp_delete_file($path);
        return new WP_Error('ath_package_finalize', sprintf(__('Could not finalize the package for %1$s / %2$s.', 'authentype-font-specimen'), $license_label, $style_label));
    }

    return $url;
}

function ath_specimen_cleanup_staged_files($files) {
    foreach ((array) $files as $file) {
        $path = is_array($file) && !empty($file['stage']) ? $file['stage'] : $file;
        if ($path && is_file($path)) wp_delete_file($path);
    }
}

function ath_specimen_rollback_committed_files($committed) {
    foreach (array_reverse((array) $committed) as $done) {
        if (is_file($done['final'])) wp_delete_file($done['final']);
        if (!empty($done['had_final']) && is_file($done['backup'])) @rename($done['backup'], $done['final']);
    }
}

function ath_specimen_finalize_committed_files($committed) {
    foreach ((array) $committed as $done) {
        if (!empty($done['had_final']) && is_file($done['backup'])) wp_delete_file($done['backup']);
    }
}

function ath_specimen_commit_staged_files($files, $build_id) {
    $committed = array();
    foreach ($files as $file) {
        if (empty($file['stage']) || empty($file['final']) || !is_file($file['stage'])) {
            ath_specimen_cleanup_staged_files($files);
            return new WP_Error('ath_package_stage_missing', __('A staged package file is missing. Existing packages were left unchanged.', 'authentype-font-specimen'));
        }
    }

    foreach ($files as $file) {
        $stage = !empty($file['stage']) ? $file['stage'] : '';
        $final = !empty($file['final']) ? $file['final'] : '';
        if (!$stage || !$final || !is_file($stage)) {
            ath_specimen_rollback_committed_files($committed);
            ath_specimen_cleanup_staged_files($files);
            return new WP_Error('ath_package_stage_missing', __('A staged package file disappeared during commit. Existing packages were restored.', 'authentype-font-specimen'));
        }

        $backup = $final . '.backup-' . $build_id;
        $had_final = is_file($final);
        if ($had_final && !@rename($final, $backup)) {
            ath_specimen_rollback_committed_files($committed);
            ath_specimen_cleanup_staged_files($files);
            return new WP_Error('ath_package_backup', __('Existing package files could not be prepared for replacement. They were left unchanged.', 'authentype-font-specimen'));
        }

        if (!@rename($stage, $final)) {
            if ($had_final && is_file($backup)) @rename($backup, $final);
            ath_specimen_rollback_committed_files($committed);
            ath_specimen_cleanup_staged_files($files);
            return new WP_Error('ath_package_commit', __('The package build could not be committed. Existing packages were restored.', 'authentype-font-specimen'));
        }

        $committed[] = array('final' => $final, 'backup' => $backup, 'had_final' => $had_final);
    }

    return $committed;
}

function ath_specimen_preview_styles_from_family_zip($post_id, $settings) {
    $font_zip_url = isset($settings['font_zip']) ? esc_url_raw($settings['font_zip']) : '';
    $zip_path = ath_specimen_uploaded_url_to_path($font_zip_url);
    if (!$zip_path) {
        return array();
    }

    $family = isset($settings['family_name']) ? sanitize_text_field($settings['family_name']) : '';
    if (!$family) {
        $family = get_the_title($post_id);
    }
    $family = trim($family) ? trim($family) : 'FontFamily';
    $family_slug = sanitize_file_name($family);
    $preview_exts = ath_specimen_package_preview_exts(isset($settings['preview_format']) ? $settings['preview_format'] : 'auto');
    if (empty($preview_exts)) {
        return array();
    }

    $entries = ath_specimen_read_font_family_zip($zip_path, $family);
    if (is_wp_error($entries)) {
        return array();
    }

    $upload_dir = wp_upload_dir();
    if (empty($upload_dir['basedir']) || empty($upload_dir['baseurl'])) {
        return array();
    }

    $preview_token = !empty($settings['secure_token'])
        ? ath_specimen_package_secure_token($settings['secure_token'])
        : substr(hash_hmac('sha256', 'ath-vector-preview|' . absint($post_id), wp_salt('auth')), 0, 18);
    $preview_base_dir = trailingslashit($upload_dir['basedir']) . 'woocommerce_uploads/authentype-previews/' . absint($post_id) . '/' . $preview_token . '/' . $family_slug;
    $preview_base_url = trailingslashit($upload_dir['baseurl']) . 'woocommerce_uploads/authentype-previews/' . absint($post_id) . '/' . rawurlencode($preview_token) . '/' . rawurlencode($family_slug);
    ath_specimen_protect_download_dir(trailingslashit($upload_dir['basedir']) . 'woocommerce_uploads');
    ath_specimen_protect_download_dir(trailingslashit($upload_dir['basedir']) . 'woocommerce_uploads/authentype-previews');
    ath_specimen_protect_download_dir($preview_base_dir);

    $style_groups = array();
    foreach ($entries as $entry) {
        $style_key = ath_specimen_package_style_key($entry['style']);
        if (!$style_key) continue;
        if (empty($style_groups[$style_key])) {
            $style_groups[$style_key] = array(
                'label' => $entry['style'],
                'entries' => array(),
            );
        }
        $style_groups[$style_key]['entries'][] = $entry;
    }

    $preview_styles = array();
    foreach ($style_groups as $style_key => $group) {
        $preview = null;
        foreach ($preview_exts as $preferred_ext) {
            foreach ($group['entries'] as $entry) {
                if ($entry['ext'] === $preferred_ext) {
                    $preview = $entry;
                    break 2;
                }
            }
        }
        if (!$preview) continue;

        $style = $group['label'];
        $preview_name = sanitize_file_name($family . '-' . $style . '.' . $preview['ext']);
        $preview_data = ath_specimen_zip_entry_data($preview);
        if (false === $preview_data) continue;
        $preview_path = $preview_base_dir . '/' . $preview_name;
        file_put_contents($preview_path, $preview_data);
        unset($preview_data);

        // Prove that this exact file can be rasterized on the current server
        // before publishing it into _ath_font_styles. A filename/extension
        // match alone is insufficient: FreeType delegates differ by host.
        if (function_exists('ath_specimen_server_render_image')) {
            $probe = ath_specimen_server_render_image(
                $preview_path,
                'Preview',
                360,
                32,
                1.18,
                '#111111',
                '#ffffff',
                'text'
            );
            if (is_wp_error($probe) || !is_string($probe) || strlen($probe) < 100) {
                wp_delete_file($preview_path);
                continue;
            }
            unset($probe);
        }
        $preview_styles[$style_key] = array(
            'style_name' => $style,
            'font_file' => $preview_base_url . '/' . rawurlencode($preview_name),
            'font_weight' => ath_specimen_package_weight($style),
            'font_style' => false !== stripos($style, 'italic') ? 'italic' : 'normal',
            'style_variation_value' => ath_specimen_slug($style),
            'default_selected' => 'regular' === ath_specimen_slug($style) ? 1 : 0,
            'is_package' => 0,
        );
    }

    return $preview_styles;
}

function ath_specimen_build_font_packages($post_id, $settings) {
    if (!function_exists('ath_specimen_build_font_packages_v7')) {
        return new WP_Error('ath_secure7_builder_missing', __('Secure.7 Package Builder is unavailable. Reinstall the plugin package.', 'authentype-font-specimen'));
    }
    return ath_specimen_build_font_packages_v7($post_id, $settings);
}

add_action('wp_ajax_ath_specimen_save_pricing', function () {
    check_ajax_referer('ath_specimen_admin', 'nonce');
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!authentype_specimen_can_manage_internal() || !$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    }
    if (!isset($_POST['ath_price_matrix'])) {
        wp_send_json_error(array('message' => __('Price Matrix was not present in this admin page. Reload the Athtyp edit page before saving pricing.', 'authentype-font-specimen')), 409);
    }
    $pricing_post_lock = '';
    if (function_exists('ath_specimen_stability_acquire_post_lock')) {
        $pricing_post_lock = ath_specimen_stability_acquire_post_lock($post_id, 'save_pricing', 2 * MINUTE_IN_SECONDS);
        if (is_wp_error($pricing_post_lock)) {
            if (function_exists('ath_specimen_stability_record_error')) ath_specimen_stability_record_error('save_pricing', $pricing_post_lock, array('post_id' => $post_id));
            wp_send_json_error(array('message' => $pricing_post_lock->get_error_message(), 'code' => $pricing_post_lock->get_error_code()), 409);
        }
        $release_post_id = $post_id;
        $release_post_token = $pricing_post_lock;
        register_shutdown_function(function () use ($release_post_id, $release_post_token) {
            if (function_exists('ath_specimen_stability_release_post_lock')) ath_specimen_stability_release_post_lock($release_post_id, $release_post_token);
        });
    }
    if (function_exists('ath_specimen_stability_cross_engine_guard')) {
        $linked_product_id = absint(ath_specimen_get_meta($post_id, '_ath_linked_product', 0));
        $operation_guard = ath_specimen_stability_cross_engine_guard($post_id, $linked_product_id, $pricing_post_lock ? array('build') : array());
        if (is_wp_error($operation_guard)) {
            if (function_exists('ath_specimen_stability_record_error')) ath_specimen_stability_record_error('save_pricing', $operation_guard, array('post_id' => $post_id, 'product_id' => $linked_product_id));
            wp_send_json_error(array('message' => $operation_guard->get_error_message(), 'code' => $operation_guard->get_error_code()), 409);
        }
    }

    $client_schema = isset($_POST['pricing_schema']) ? sanitize_text_field(wp_unslash($_POST['pricing_schema'])) : '';
    $current_styles = ath_specimen_get_meta($post_id, '_ath_font_styles', array());
    $current_licenses = ath_specimen_get_meta($post_id, '_ath_license_options', array());
    $current_schema = ath_specimen_pricing_schema_signature($current_styles, $current_licenses);
    if (!$client_schema || !hash_equals((string) $current_schema, (string) $client_schema)) {
        wp_send_json_error(array('message' => __('Price Matrix on this page is stale because Style or License inventory changed. Reload the Athtyp edit page before saving pricing.', 'authentype-font-specimen')), 409);
    }

    $client_pricing_hash = isset($_POST['pricing_hash']) ? sanitize_text_field(wp_unslash($_POST['pricing_hash'])) : '';
    $current_pricing_hash = ath_specimen_current_pricing_hash($post_id);
    if (!$client_pricing_hash || !hash_equals((string) $current_pricing_hash, (string) $client_pricing_hash)) {
        wp_send_json_error(array('message' => __('Pricing changed on the server after this page was loaded. Reload the Athtyp edit page before saving so a newer Price Matrix is not overwritten.', 'authentype-font-specimen')), 409);
    }

    $matrix = wp_unslash($_POST['ath_price_matrix']);
    $validation = ath_specimen_validate_price_matrix_input($matrix);
    if (is_wp_error($validation)) {
        wp_send_json_error(array('message' => $validation->get_error_message(), 'code' => $validation->get_error_code()), 400);
    }
    $saved = ath_specimen_save_pricing_matrix($post_id, $matrix);
    $style_attr = (string) ath_specimen_get_meta($post_id, '_ath_style_attribute', 'pa_style');
    $license_attr = (string) ath_specimen_get_meta($post_id, '_ath_license_attribute', 'pa_license');
    $current_signature = ath_specimen_woo_sync_signature($post_id, $style_attr, $license_attr);
    $synced_signature = (string) ath_specimen_get_meta($post_id, '_ath_woo_synced_signature', '');
    $woo_synced = $synced_signature && hash_equals($synced_signature, $current_signature);
    wp_send_json_success(array(
        'message' => __('Pricing saved. No secure assets or product files were rebuilt. Sync WooCommerce to copy the new prices to variations.', 'authentype-font-specimen'),
        'pricing_hash' => $saved['hash'],
        'workflow_signature' => ath_specimen_admin_workflow_signature($post_id),
        'pricing_configured' => !empty($saved['matrix']),
        'woo_synced' => $woo_synced,
    ));
});

add_action('wp_ajax_ath_specimen_build_packages', function () {
    check_ajax_referer('ath_specimen_admin', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!authentype_specimen_can_manage_internal() || !$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    }

    $settings = array(
        'font_zip' => isset($_POST['font_zip']) ? esc_url_raw(wp_unslash($_POST['font_zip'])) : '',
        'family_name' => isset($_POST['family_name']) ? sanitize_text_field(wp_unslash($_POST['family_name'])) : '',
        'preview_format' => isset($_POST['preview_format']) ? sanitize_text_field(wp_unslash($_POST['preview_format'])) : 'auto',
        'secure_token' => isset($_POST['secure_token']) ? ath_specimen_slug(wp_unslash($_POST['secure_token'])) : '',
        'licenses' => isset($_POST['package_licenses']) ? wp_unslash($_POST['package_licenses']) : array(),
    );

    $result = ath_specimen_build_font_packages($post_id, $settings);
    if (is_wp_error($result)) {
        if (function_exists('ath_specimen_stability_record_error')) ath_specimen_stability_record_error('build_secure_assets', $result, array('post_id' => $post_id, 'product_id' => absint(ath_specimen_get_meta($post_id, '_ath_linked_product', 0))));
        $status = in_array($result->get_error_code(), array('ath_package_build_locked', 'ath_stability_woo_busy', 'ath_stability_migration_busy'), true) ? 409 : 400;
        wp_send_json_error(array('message' => $result->get_error_message(), 'code' => $result->get_error_code()), $status);
    }

    $message = sprintf(__('Secure build complete. %1$d styles detected; %2$d protected assets/files created. Pricing was preserved and was not rebuilt.', 'authentype-font-specimen'), (int) $result['styles'], (int) $result['created']);
    if (!empty($result['nested_archives'])) {
        $message .= ' ' . sprintf(_n('%d nested Single Style ZIP was scanned safely.', '%d nested Single Style ZIPs were scanned safely.', (int) $result['nested_archives'], 'authentype-font-specimen'), (int) $result['nested_archives']);
    }
    if (!empty($result['family_zips'])) {
        $message .= ' ' . sprintf(_n('%d dynamic inventory package ZIP was generated.', '%d dynamic inventory package ZIPs were generated.', (int) $result['family_zips'], 'authentype-font-specimen'), (int) $result['family_zips']);
    }
    if (!empty($result['variations'])) {
        $message .= ' ' . sprintf(__('%d WooCommerce style/license variations can reuse these assets without duplicate ZIP compression.', 'authentype-font-specimen'), (int) $result['variations']);
    }
    if (!empty($result['source_removed'])) {
        $message .= ' ' . __('The public source ZIP was deleted from Media Library.', 'authentype-font-specimen');
    } else {
        $message .= ' ' . __('Security notice: the source ZIP could not be removed automatically; delete it from Media Library now.', 'authentype-font-specimen');
    }

    wp_send_json_success(array(
        'message' => $message,
        'csv_url' => $result['csv_url'],
    ));
});

add_action('wp_ajax_ath_specimen_sync_woo', function () {
    check_ajax_referer('ath_specimen_admin', 'nonce');

    if (!authentype_specimen_can_manage_internal()) {
        wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    }

    if (!function_exists('wc_get_product')) {
        wp_send_json_error(array('message' => __('WooCommerce is unavailable.', 'authentype-font-specimen')), 400);
    }

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $style_attr = isset($_POST['style_attribute']) ? sanitize_text_field(wp_unslash($_POST['style_attribute'])) : 'pa_style';
    $license_attr = isset($_POST['license_attribute']) ? sanitize_text_field(wp_unslash($_POST['license_attribute'])) : 'pa_license';

    if ($post_id && !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    }

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        wp_send_json_error(array('message' => __('Select a variable WooCommerce product.', 'authentype-font-specimen')), 400);
    }
    if (!current_user_can('edit_post', $product_id)) {
        wp_send_json_error(array('message' => __('You cannot edit the selected WooCommerce product.', 'authentype-font-specimen')), 403);
    }
    $sync_from_woo_post_lock = '';
    if ($post_id && function_exists('ath_specimen_stability_acquire_post_lock')) {
        $sync_from_woo_post_lock = ath_specimen_stability_acquire_post_lock($post_id, 'sync_from_woo', 2 * MINUTE_IN_SECONDS);
        if (is_wp_error($sync_from_woo_post_lock)) {
            if (function_exists('ath_specimen_stability_record_error')) ath_specimen_stability_record_error('sync_from_woo', $sync_from_woo_post_lock, array('post_id' => $post_id, 'product_id' => $product_id));
            wp_send_json_error(array('message' => $sync_from_woo_post_lock->get_error_message(), 'code' => $sync_from_woo_post_lock->get_error_code()), 409);
        }
        $release_post_id = $post_id;
        $release_post_token = $sync_from_woo_post_lock;
        register_shutdown_function(function () use ($release_post_id, $release_post_token) {
            if (function_exists('ath_specimen_stability_release_post_lock')) ath_specimen_stability_release_post_lock($release_post_id, $release_post_token);
        });
    }
    if ($post_id && function_exists('ath_specimen_stability_cross_engine_guard')) {
        $operation_guard = ath_specimen_stability_cross_engine_guard($post_id, $product_id, $sync_from_woo_post_lock ? array('build') : array());
        if (is_wp_error($operation_guard)) {
            if (function_exists('ath_specimen_stability_record_error')) ath_specimen_stability_record_error('sync_from_woo', $operation_guard, array('post_id' => $post_id, 'product_id' => $product_id));
            wp_send_json_error(array('message' => $operation_guard->get_error_message(), 'code' => $operation_guard->get_error_code()), 409);
        }
    }

    $style_values = ath_specimen_sort_style_attribute_values(ath_specimen_product_attribute_values($product, $style_attr));
    $license_values = ath_specimen_product_attribute_values($product, $license_attr);

    if ($post_id) {
        $existing_styles = ath_specimen_get_meta($post_id, '_ath_font_styles', array());
        $package_builder = ath_specimen_get_meta($post_id, '_ath_package_builder', array());
        $zip_preview_styles = ath_specimen_preview_styles_from_family_zip($post_id, is_array($package_builder) ? $package_builder : array());
        $existing_styles_by_value = array();
        if (is_array($existing_styles)) {
            foreach ($existing_styles as $existing_style) {
                $existing_value = !empty($existing_style['style_variation_value']) ? ath_specimen_slug($existing_style['style_variation_value']) : ath_specimen_slug(!empty($existing_style['style_name']) ? $existing_style['style_name'] : '');
                if ($existing_value) {
                    $existing_styles_by_value[$existing_value] = $existing_style;
                }
            }
        }

        $style_rows = array();
        $style_index = 0;
        foreach ($style_values as $slug => $label) {
            $existing_style = isset($existing_styles_by_value[$slug]) ? $existing_styles_by_value[$slug] : array();
            $zip_style = isset($zip_preview_styles[$slug]) ? $zip_preview_styles[$slug] : array();
            $existing_font_file = !empty($existing_style['font_file']) ? $existing_style['font_file'] : '';
            if ($existing_font_file && !empty($zip_style['font_file']) && ath_specimen_is_legacy_public_preview_url($post_id, $existing_font_file)) {
                $existing_font_file = '';
            }
            $style_rows[] = array(
                'style_name' => $label,
                'font_file' => $existing_font_file ? $existing_font_file : (!empty($zip_style['font_file']) ? $zip_style['font_file'] : ''),
                'font_weight' => !empty($existing_style['font_weight']) ? $existing_style['font_weight'] : (!empty($zip_style['font_weight']) ? $zip_style['font_weight'] : 400),
                'font_style' => !empty($existing_style['font_style']) ? $existing_style['font_style'] : (!empty($zip_style['font_style']) ? $zip_style['font_style'] : 'normal'),
                'style_variation_value' => $slug,
                'default_selected' => !empty($existing_style['default_selected']) || !empty($zip_style['default_selected']) || 0 === $style_index ? 1 : 0,
                'is_package' => !empty($existing_style['is_package']) || !empty($zip_style['is_package']) ? 1 : 0,
            );
            $style_index++;
        }

        $existing_licenses = ath_specimen_get_meta($post_id, '_ath_license_options', array());
        $existing_licenses_by_value = array();
        foreach ((array) $existing_licenses as $existing_license) {
            $existing_value = !empty($existing_license['license_variation_value']) ? ath_specimen_slug($existing_license['license_variation_value']) : '';
            if ($existing_value) $existing_licenses_by_value[$existing_value] = $existing_license;
        }
        $license_rows = array();
        foreach ($license_values as $slug => $label) {
            $existing_license = isset($existing_licenses_by_value[$slug]) ? $existing_licenses_by_value[$slug] : array();
            $license_rows[] = array(
                'license_label' => $label,
                'license_variation_value' => $slug,
                'license_description' => !empty($existing_license['license_description']) ? $existing_license['license_description'] : '',
                'license_group' => !empty($existing_license['license_group']) ? $existing_license['license_group'] : '',
                'license_featured' => !empty($existing_license['license_featured']) ? 1 : 0,
                'license_checkout_type' => !empty($existing_license['license_checkout_type']) ? $existing_license['license_checkout_type'] : '',
                'license_icon' => !empty($existing_license['license_icon']) ? $existing_license['license_icon'] : '',
            );
        }

        update_post_meta($post_id, '_ath_linked_product', $product_id);
        update_post_meta($post_id, '_ath_style_attribute', $style_attr);
        update_post_meta($post_id, '_ath_license_attribute', $license_attr);
        update_post_meta($post_id, '_ath_font_styles', ath_specimen_sanitize_styles($style_rows));
        update_post_meta($post_id, '_ath_license_options', ath_specimen_sanitize_licenses($license_rows));
        update_post_meta($post_id, '_ath_pricing_needs_review', '1');
    }

    wp_send_json_success(array(
        'message' => $post_id ? __('Synced from WooCommerce and saved to Athtyp.', 'authentype-font-specimen') : __('Synced from WooCommerce variations.', 'authentype-font-specimen'),
        'styles' => array_map(function ($slug, $label) use ($post_id) {
            $rows = ath_specimen_get_meta($post_id, '_ath_font_styles', array());
            $matched = array();
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $row_value = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : ath_specimen_slug(!empty($row['style_name']) ? $row['style_name'] : '');
                    if ($row_value === $slug) {
                        $matched = $row;
                        break;
                    }
                }
            }
            return array(
                'label' => $label,
                'value' => $slug,
                'font_file' => !empty($matched['font_file']) ? $matched['font_file'] : '',
                'font_weight' => !empty($matched['font_weight']) ? $matched['font_weight'] : 400,
                'font_style' => !empty($matched['font_style']) ? $matched['font_style'] : 'normal',
                'default_selected' => !empty($matched['default_selected']) ? 1 : 0,
                'is_package' => !empty($matched['is_package']) ? 1 : 0,
            );
        }, array_keys($style_values), $style_values),
        'licenses' => array_map(function ($slug, $label) use ($post_id) {
            $rows = ath_specimen_get_meta($post_id, '_ath_license_options', array());
            $matched = array();
            foreach ((array) $rows as $row) {
                if (!empty($row['license_variation_value']) && ath_specimen_slug($row['license_variation_value']) === $slug) {
                    $matched = $row;
                    break;
                }
            }
            return array(
                'label' => $label,
                'value' => $slug,
                'description' => !empty($matched['license_description']) ? $matched['license_description'] : '',
                'group' => !empty($matched['license_group']) ? $matched['license_group'] : '',
                'featured' => !empty($matched['license_featured']) ? 1 : 0,
                'checkout_type' => !empty($matched['license_checkout_type']) ? $matched['license_checkout_type'] : '',
                'icon' => !empty($matched['license_icon']) ? $matched['license_icon'] : '',
            );
        }, array_keys($license_values), $license_values),
    ));
});

function ath_specimen_woo_sync_state_key($token) {
    return 'ath_woo_sync_' . md5((string) $token);
}

function ath_specimen_woo_sync_lock_key($post_id, $product_id) {
    // The Woo product is the mutation target, so the mutex must be shared by
    // every Athtyp record that could point at that product. Keep the first
    // argument for backward call-site compatibility only.
    return 'ath_woo_lock_product_' . md5((string) absint($product_id));
}

function ath_specimen_woo_product_owner($product_id, $exclude_post_id = 0) {
    $product_id = absint($product_id);
    $exclude_post_id = absint($exclude_post_id);
    if (!$product_id) return 0;

    $owner = absint(get_post_meta($product_id, '_ath_athtyp_owner_post_id', true));
    if ($owner) {
        $owner_post = get_post($owner);
        $owner_link = absint(get_post_meta($owner, '_ath_linked_product', true));
        if (!$owner_post || 'ath_font' !== $owner_post->post_type || $owner_link !== $product_id) {
            delete_post_meta($product_id, '_ath_athtyp_owner_post_id');
            $owner = 0;
        }
    }
    if ($owner && $owner !== $exclude_post_id) return $owner;

    $matches = get_posts(array(
        'post_type' => 'ath_font',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'post__not_in' => $exclude_post_id ? array($exclude_post_id) : array(),
        'meta_key' => '_ath_linked_product',
        'meta_value' => $product_id,
        'meta_compare' => '=',
    ));
    $found = !empty($matches[0]) ? absint($matches[0]) : 0;
    if ($found) update_post_meta($product_id, '_ath_athtyp_owner_post_id', $found);
    return $found;
}

function ath_specimen_woo_product_ownership_guard($post_id, $product_id) {
    $owner = ath_specimen_woo_product_owner($product_id, $post_id);
    if (!$owner) return true;
    $title = get_the_title($owner);
    if (!$title) $title = sprintf(__('Athtyp #%d', 'authentype-font-specimen'), $owner);
    return new WP_Error(
        'ath_woo_owned',
        sprintf(__('Woo product #%1$d is already linked to %2$s (Athtyp #%3$d). Detach or restore that record before syncing this product from another Athtyp.', 'authentype-font-specimen'), absint($product_id), sanitize_text_field($title), $owner)
    );
}

function ath_specimen_woo_sync_lock_option_name($lock_key) {
    return 'ath_woo_mutex_' . md5((string) $lock_key);
}

function ath_specimen_woo_sync_read_lock($lock_key) {
    $lock_key = sanitize_key((string) $lock_key);
    if (!$lock_key) return '';
    $option_name = ath_specimen_woo_sync_lock_option_name($lock_key);
    $record = get_option($option_name, array());
    if (is_array($record) && !empty($record['token'])) {
        if (!empty($record['expires']) && (int) $record['expires'] <= time()) {
            delete_option($option_name);
        } else {
            return sanitize_text_field((string) $record['token']);
        }
    }

    // Seamless upgrade path for a sync session started on secure.8.0/7.3.12.
    $legacy = get_transient($lock_key);
    if ($legacy) {
        $legacy = sanitize_text_field((string) $legacy);
        if (ath_specimen_woo_sync_acquire_lock($lock_key, $legacy, 2 * HOUR_IN_SECONDS)) return $legacy;
        $current = get_option($option_name, array());
        if (is_array($current) && !empty($current['token']) && (empty($current['expires']) || (int) $current['expires'] > time())) {
            return sanitize_text_field((string) $current['token']);
        }
    }
    return '';
}

function ath_specimen_woo_sync_acquire_lock($lock_key, $token, $ttl = 0) {
    $lock_key = sanitize_key((string) $lock_key);
    $token = sanitize_text_field((string) $token);
    if (!$lock_key || !$token) return false;
    $ttl = max(5 * MINUTE_IN_SECONDS, (int) ($ttl ?: 2 * HOUR_IN_SECONDS));
    $option_name = ath_specimen_woo_sync_lock_option_name($lock_key);
    $record = array('token' => $token, 'expires' => time() + $ttl);
    if (add_option($option_name, $record, '', false)) {
        set_transient($lock_key, $token, $ttl); // compatibility + cheap visibility
        return true;
    }

    $current = get_option($option_name, array());
    if (is_array($current) && !empty($current['token']) && hash_equals((string) $current['token'], $token)) {
        update_option($option_name, $record, false);
        set_transient($lock_key, $token, $ttl);
        return true;
    }
    if (!is_array($current) || empty($current['expires']) || (int) $current['expires'] <= time()) {
        delete_option($option_name);
        if (add_option($option_name, $record, '', false)) {
            set_transient($lock_key, $token, $ttl);
            return true;
        }
    }
    return false;
}

function ath_specimen_woo_sync_release_lock($lock_key, $token = '') {
    $lock_key = sanitize_key((string) $lock_key);
    $token = sanitize_text_field((string) $token);
    if (!$lock_key) return;
    $option_name = ath_specimen_woo_sync_lock_option_name($lock_key);
    $current = get_option($option_name, array());
    if (!$token || !is_array($current) || empty($current['token']) || hash_equals((string) $current['token'], $token)) {
        delete_option($option_name);
    }
    $legacy = get_transient($lock_key);
    if (!$token || !$legacy || hash_equals((string) $legacy, $token)) delete_transient($lock_key);
}

function ath_specimen_woo_sync_batch_size() {
    $size = (int) apply_filters('authentype_specimen_woo_sync_batch_size', 12);
    return max(1, min(25, $size));
}

/**
 * Read-only structural guard for an existing Woo variable product.
 *
 * Athtyp intentionally manages a two-dimensional Style × License matrix. Before
 * the sync code creates terms, changes parent attributes, or touches a variation,
 * reject legacy structures that cannot be represented unambiguously.
 */
function ath_specimen_woo_sync_compatibility_preflight($product, $style_taxonomy, $license_taxonomy) {
    if (!$product || !method_exists($product, 'is_type') || !$product->is_type('variable')) {
        return new WP_Error('ath_woo_product', __('The selected WooCommerce product must be a variable product.', 'authentype-font-specimen'));
    }

    $style_taxonomy = sanitize_title(str_replace('attribute_', '', (string) $style_taxonomy));
    $license_taxonomy = sanitize_title(str_replace('attribute_', '', (string) $license_taxonomy));
    if (!$style_taxonomy || !$license_taxonomy || $style_taxonomy === $license_taxonomy) {
        return new WP_Error('ath_woo_mapping', __('Style and License must use two different WooCommerce attributes.', 'authentype-font-specimen'));
    }

    $extra_dimensions = array();
    foreach ((array) $product->get_attributes() as $key => $attribute) {
        if (!is_object($attribute) || !method_exists($attribute, 'get_variation') || !$attribute->get_variation()) continue;
        $name = method_exists($attribute, 'get_name') ? $attribute->get_name() : $key;
        $name = sanitize_title(str_replace('attribute_', '', (string) $name));
        if ($name && $name !== $style_taxonomy && $name !== $license_taxonomy) {
            $extra_dimensions[$name] = true;
        }
    }
    if (!empty($extra_dimensions)) {
        return new WP_Error(
            'ath_woo_extra_dimension',
            sprintf(
                __('Woo Sync blocked before changes: this product has an additional variation dimension (%s). Athtyp sync supports Style × License only.', 'authentype-font-specimen'),
                implode(', ', array_map('sanitize_text_field', array_keys($extra_dimensions)))
            )
        );
    }

    $seen = array();
    $wildcards = array();
    $duplicate = array();
    $child_extra = array();
    foreach ((array) $product->get_children() as $variation_id) {
        $variation_id = absint($variation_id);
        $variation = $variation_id ? wc_get_product($variation_id) : false;
        if (!$variation) continue;

        $attrs = array();
        foreach ((array) $variation->get_attributes() as $key => $value) {
            $name = sanitize_title(str_replace('attribute_', '', (string) $key));
            if (!$name) continue;
            $attrs[$name] = $value;
            if ($name !== $style_taxonomy && $name !== $license_taxonomy) {
                $child_extra[$name] = true;
            }
        }
        if (!empty($child_extra)) break;

        $style_value = isset($attrs[$style_taxonomy]) ? ath_specimen_slug($attrs[$style_taxonomy]) : '';
        $license_value = isset($attrs[$license_taxonomy]) ? ath_specimen_slug($attrs[$license_taxonomy]) : '';
        if (!$style_value || !$license_value) {
            $wildcards[] = $variation_id;
            continue;
        }

        $pair = $style_value . '|' . $license_value;
        if (isset($seen[$pair])) {
            $duplicate[$pair] = array($seen[$pair], $variation_id);
            break;
        }
        $seen[$pair] = $variation_id;
    }

    if (!empty($child_extra)) {
        return new WP_Error(
            'ath_woo_extra_dimension',
            sprintf(
                __('Woo Sync blocked before changes: existing variations contain an additional dimension (%s). Review the Woo product structure first.', 'authentype-font-specimen'),
                implode(', ', array_map('sanitize_text_field', array_keys($child_extra)))
            )
        );
    }
    if (!empty($wildcards)) {
        return new WP_Error(
            'ath_woo_wildcard_variation',
            sprintf(
                __('Woo Sync blocked before changes: variation #%d uses “Any” or is missing a Style/License value. Resolve wildcard variations first.', 'authentype-font-specimen'),
                (int) reset($wildcards)
            )
        );
    }
    if (!empty($duplicate)) {
        $pair = '';
        $ids = array();
        foreach ($duplicate as $duplicate_pair => $duplicate_ids) {
            $pair = (string) $duplicate_pair;
            $ids = (array) $duplicate_ids;
            break;
        }
        return new WP_Error(
            'ath_woo_duplicate_pair',
            sprintf(
                __('Woo Sync blocked before changes: variations #%1$d and #%2$d resolve to the same Style × License pair (%3$s). Resolve the duplicate first.', 'authentype-font-specimen'),
                (int) ($ids[0] ?? 0),
                (int) ($ids[1] ?? 0),
                $pair
            )
        );
    }

    return array('existing' => $seen);
}

function ath_specimen_woo_sync_signature($post_id, $style_attr_key, $license_attr_key) {
    $woo_licenses = array();
    foreach ((array) ath_specimen_get_meta($post_id, '_ath_license_options', array()) as $license) {
        $value = !empty($license['license_variation_value']) ? ath_specimen_slug($license['license_variation_value']) : '';
        if (!$value) continue;
        $woo_licenses[] = array(
            'label' => !empty($license['license_label']) ? sanitize_text_field($license['license_label']) : $value,
            'value' => $value,
            // Only purchase eligibility enters the commerce signature.
            // Pay once ↔ Annual remains presentation-only; switching to/from
            // Contact Sales requires Woo Sync because purchasability changes.
            'purchasable' => !(function_exists('ath_specimen_license_is_contact_sales') && ath_specimen_license_is_contact_sales($license)) ? 1 : 0,
        );
    }
    $payload = array(
        'styles' => ath_specimen_get_meta($post_id, '_ath_font_styles', array()),
        'licenses' => $woo_licenses,
        'prices' => ath_specimen_get_meta($post_id, '_ath_price_matrix', array()),
        'downloads' => ath_specimen_get_meta($post_id, '_ath_product_downloads', array()),
        'product_id' => (int) ath_specimen_get_meta($post_id, '_ath_linked_product', 0),
        'style_attribute' => sanitize_text_field((string) $style_attr_key),
        'license_attribute' => sanitize_text_field((string) $license_attr_key),
        // Bump whenever Woo mirror semantics change. This deliberately marks
        // products synced by older builds as Needs sync so `_price` is repaired
        // before storefront purchases are accepted.
        'woo_mirror_schema' => 2,
    );
    return hash('sha256', wp_json_encode($payload));
}

function ath_specimen_activate_managed_variation($variation) {
    if (!$variation) return false;
    $changed = false;
    if ('1' === (string) $variation->get_meta('_ath_disabled_by_sync', true)) {
        if ('publish' !== (string) $variation->get_status('edit')) {
            $variation->set_status('publish');
            $changed = true;
        }
        $variation->delete_meta_data('_ath_disabled_by_sync');
        $changed = true;
    }
    return $changed;
}

function ath_specimen_disable_stale_variation($variation) {
    if (!$variation || 'athtyp' !== (string) $variation->get_meta('_ath_pricing_authority', true)) return false;
    $changed = false;
    foreach (array('regular_price', 'sale_price', 'price') as $kind) {
        $getter = 'get_' . $kind;
        $setter = 'set_' . $kind;
        if (method_exists($variation, $getter) && method_exists($variation, $setter) && '' !== (string) $variation->{$getter}('edit')) {
            $variation->{$setter}('');
            $changed = true;
        }
    }
    // Keep historical download records on retired variations so existing
    // customer/order download permissions remain intact. The variation is made
    // private + unpriced + explicitly non-purchasable. If it becomes active
    // again, the normal sync path replaces/clears downloads from current mapping.
    if ('private' !== (string) $variation->get_status('edit')) {
        $variation->set_status('private');
        $changed = true;
    }
    if ('1' !== (string) $variation->get_meta('_ath_disabled_by_sync', true)) {
        $variation->update_meta_data('_ath_disabled_by_sync', '1');
        $changed = true;
    }
    if ($changed) $variation->save();
    return $changed;
}

function ath_specimen_retire_stale_variations($state) {
    $retired = 0;
    $desired = !empty($state['desired']) && is_array($state['desired']) ? $state['desired'] : array();
    foreach ((array) ($state['existing'] ?? array()) as $key => $variation_id) {
        if (isset($desired[$key])) continue;
        $variation = wc_get_product((int) $variation_id);
        if ($variation && ath_specimen_disable_stale_variation($variation)) $retired++;
    }
    return $retired;
}

function ath_specimen_woo_sync_public_state($state, $message = '') {
    $processed = isset($state['offset']) ? (int) $state['offset'] : 0;
    $total = isset($state['total']) ? max(0, (int) $state['total']) : 0;
    $percent = $total > 0 ? min(100, (int) floor(($processed / $total) * 100)) : 100;
    $stats = !empty($state['stats']) && is_array($state['stats']) ? $state['stats'] : array();

    return array(
        'message' => $message,
        'token' => !empty($state['token']) ? $state['token'] : '',
        'product_id' => !empty($state['product_id']) ? (int) $state['product_id'] : 0,
        'processed' => $processed,
        'total' => $total,
        'percent' => $percent,
        'batch_size' => !empty($state['batch_size']) ? (int) $state['batch_size'] : ath_specimen_woo_sync_batch_size(),
        'complete' => $total <= $processed,
        'workflow_signature' => !empty($state['post_id']) ? ath_specimen_admin_workflow_signature((int) $state['post_id']) : '',
        'stats' => array(
            'created' => !empty($stats['created']) ? (int) $stats['created'] : 0,
            'updated' => !empty($stats['updated']) ? (int) $stats['updated'] : 0,
            'skipped' => !empty($stats['skipped']) ? (int) $stats['skipped'] : 0,
            'priced' => !empty($stats['priced']) ? (int) $stats['priced'] : 0,
            'downloaded' => !empty($stats['downloaded']) ? (int) $stats['downloaded'] : 0,
            'retired' => !empty($stats['retired']) ? (int) $stats['retired'] : 0,
        ),
    );
}

function ath_specimen_woo_sync_store_state($state) {
    if (empty($state['token'])) return false;
    $ttl = 2 * HOUR_IN_SECONDS;
    if (!empty($state['lock_key']) && !ath_specimen_woo_sync_acquire_lock($state['lock_key'], $state['token'], $ttl)) {
        return false;
    }
    $state_key = ath_specimen_woo_sync_state_key($state['token']);
    $stored = set_transient($state_key, $state, $ttl);
    if (!$stored) {
        // update_option()/set_transient() may return false when the value is
        // unchanged, so verify persistence before declaring a storage failure.
        $verify = get_transient($state_key);
        if (!is_array($verify) || empty($verify['token']) || !hash_equals((string) $verify['token'], (string) $state['token']) || maybe_serialize($verify) !== maybe_serialize($state)) {
            if (!empty($state['lock_key'])) ath_specimen_woo_sync_release_lock($state['lock_key'], $state['token']);
            return false;
        }
    }
    return true;
}

function ath_specimen_woo_sync_delete_state($state) {
    if (!empty($state['token'])) {
        delete_transient(ath_specimen_woo_sync_state_key($state['token']));
    }
    if (!empty($state['lock_key'])) {
        ath_specimen_woo_sync_release_lock($state['lock_key'], !empty($state['token']) ? $state['token'] : '');
    }
}

function ath_specimen_prepare_woo_sync_state($post_id, $product_id, $style_attr_key, $license_attr_key, $reserved_token = '') {
    $styles = ath_specimen_get_meta($post_id, '_ath_font_styles', array());
    $licenses = ath_specimen_get_meta($post_id, '_ath_license_options', array());
    if (empty($styles) || !is_array($styles) || empty($licenses) || !is_array($licenses)) {
        return new WP_Error('ath_woo_rows', __('Add and save Font Styles and License Options first.', 'authentype-font-specimen'));
    }
    if ((bool) ath_specimen_get_meta($post_id, '_ath_pricing_needs_review', false)) {
        return new WP_Error('ath_woo_pricing_review', __('Woo Sync blocked: secure inventory changed and Pricing needs review. Reload the Athtyp edit page, review/save the Price Matrix, then sync WooCommerce.', 'authentype-font-specimen'));
    }

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) {
        return new WP_Error('ath_woo_product', __('The selected WooCommerce product must be a variable product.', 'authentype-font-specimen'));
    }
    if (!current_user_can('edit_post', $product_id)) {
        return new WP_Error('ath_woo_permission', __('You cannot edit the selected WooCommerce product.', 'authentype-font-specimen'));
    }
    $ownership = ath_specimen_woo_product_ownership_guard($post_id, $product_id);
    if (is_wp_error($ownership)) return $ownership;

    $style_taxonomy = ath_specimen_attribute_taxonomy($style_attr_key);
    $license_taxonomy = ath_specimen_attribute_taxonomy($license_attr_key);

    // Final-commerce preflight must happen before *any* Woo mutation: no
    // taxonomy creation, term assignment, parent-attribute save, price change,
    // variation creation, download rewrite, or pricing-authority takeover.
    $compatibility = ath_specimen_woo_sync_compatibility_preflight($product, $style_taxonomy, $license_taxonomy);
    if (is_wp_error($compatibility)) return $compatibility;
    $existing = !empty($compatibility['existing']) && is_array($compatibility['existing']) ? $compatibility['existing'] : array();

    $has_purchasable_license = false;
    foreach ($licenses as $license) {
        if (empty($license['license_label']) || empty($license['license_variation_value'])) continue;
        $is_contact = function_exists('ath_specimen_license_is_contact_sales') && ath_specimen_license_is_contact_sales($license);
        if (!$is_contact) { $has_purchasable_license = true; break; }
    }
    $price_matrix = ath_specimen_get_meta($post_id, '_ath_price_matrix', array());
    if ($has_purchasable_license && !ath_specimen_price_matrix_has_configured_regular(is_array($price_matrix) ? $price_matrix : array())) {
        return new WP_Error('ath_woo_pricing_empty', __('Woo Sync blocked: at least one purchasable license exists but the Price Matrix has no Regular price. Save pricing before syncing WooCommerce.', 'authentype-font-specimen'));
    }

    // secure.8.1.1: upgrade a legacy 8.1 adoption snapshot before the first
    // takeover mutation, while Woo still represents the original catalog state.
    if (function_exists('ath_specimen_adoption_maybe_upgrade_snapshot') &&
        'existing_woo_catalog' === (string) ath_specimen_get_meta($post_id, '_ath_adoption_source', '')) {
        ath_specimen_adoption_maybe_upgrade_snapshot($post_id);
    }

    $style_attribute_id = ath_specimen_ensure_wc_attribute_taxonomy($style_attr_key, __('Style', 'authentype-font-specimen'));
    if (is_wp_error($style_attribute_id)) return $style_attribute_id;
    $license_attribute_id = ath_specimen_ensure_wc_attribute_taxonomy($license_attr_key, __('License', 'authentype-font-specimen'));
    if (is_wp_error($license_attribute_id)) return $license_attribute_id;

    $style_terms = array();
    foreach ($styles as $style) {
        if (empty($style['style_name'])) continue;
        $value = !empty($style['style_variation_value']) ? ath_specimen_slug($style['style_variation_value']) : ath_specimen_slug($style['style_name']);
        if (!$value) continue;
        $term_id = ath_specimen_ensure_term($style_taxonomy, $value, $style['style_name']);
        if ($term_id) {
            $style_terms[$value] = array('term_id' => $term_id, 'label' => $style['style_name']);
        }
    }

    $license_terms = array();
    $purchasable_license_values = array();
    foreach ($licenses as $license) {
        if (empty($license['license_label']) || empty($license['license_variation_value'])) continue;
        $value = ath_specimen_slug($license['license_variation_value']);
        if (!$value) continue;
        $term_id = ath_specimen_ensure_term($license_taxonomy, $value, $license['license_label']);
        if ($term_id) {
            $license_terms[$value] = array('term_id' => $term_id, 'label' => $license['license_label']);
            $is_contact = function_exists('ath_specimen_license_is_contact_sales') && ath_specimen_license_is_contact_sales($license);
            if (!$is_contact) $purchasable_license_values[$value] = true;
        }
    }

    if (empty($style_terms) || empty($license_terms)) {
        return new WP_Error('ath_woo_terms', __('No valid style/license terms could be created.', 'authentype-font-specimen'));
    }

    $set_styles = wp_set_object_terms($product->get_id(), array_keys($style_terms), $style_taxonomy, false);
    if (is_wp_error($set_styles)) return $set_styles;
    $set_licenses = wp_set_object_terms($product->get_id(), array_keys($license_terms), $license_taxonomy, false);
    if (is_wp_error($set_licenses)) return $set_licenses;

    $style_attribute = new WC_Product_Attribute();
    $style_attribute->set_id($style_attribute_id);
    $style_attribute->set_name($style_taxonomy);
    $style_attribute->set_options(array_values(wp_list_pluck($style_terms, 'term_id')));
    $style_attribute->set_visible(true);
    $style_attribute->set_variation(true);

    $license_attribute = new WC_Product_Attribute();
    $license_attribute->set_id($license_attribute_id);
    $license_attribute->set_name($license_taxonomy);
    $license_attribute->set_options(array_values(wp_list_pluck($license_terms, 'term_id')));
    $license_attribute->set_visible(true);
    $license_attribute->set_variation(true);

    // Preserve unrelated WooCommerce attributes. Secure.7.1 previously replaced
    // the complete attribute array when setting Style + License.
    $product_attributes = $product->get_attributes();
    $product_attributes[$style_taxonomy] = $style_attribute;
    $product_attributes[$license_taxonomy] = $license_attribute;
    $product->set_attributes($product_attributes);
    $product_id = $product->save();

    update_post_meta($post_id, '_ath_linked_product', $product_id);
    update_post_meta($post_id, '_ath_style_attribute', sanitize_text_field($style_attr_key));
    update_post_meta($post_id, '_ath_license_attribute', sanitize_text_field($license_attr_key));
    update_post_meta($post_id, '_ath_pricing_authority', 'plugin');
    update_post_meta($product_id, '_ath_pricing_authority', 'athtyp');
    update_post_meta($product_id, '_ath_athtyp_owner_post_id', $post_id);

    // Reuse the read-only compatibility scan as the existing-variation lookup;
    // avoid hydrating every child variation a second time during sync init.
    // Only enqueue pairs backed by actual protected font delivery. This prevents
    // a license document or a globally declared license from creating an empty
    // Style × License Woo variation.
    $product_downloads = ath_specimen_get_meta($post_id, '_ath_product_downloads', array());
    $delivery_pairs = ath_specimen_product_download_delivery_pairs(is_array($product_downloads) ? $product_downloads : array());
    $queue = array();
    $desired = array();
    foreach (array_keys($style_terms) as $style_value) {
        foreach (array_keys($purchasable_license_values) as $license_value) {
            $lookup_key = $style_value . '|' . $license_value;
            if (empty($delivery_pairs[$lookup_key])) continue;
            $queue[] = array($style_value, $license_value);
            $desired[$lookup_key] = true;
        }
    }

    $token = preg_match('/^[A-Za-z0-9]{20,80}$/', (string) $reserved_token) ? (string) $reserved_token : wp_generate_password(40, false, false);
    $queue_total = count($queue);
    $batch_size = ath_specimen_woo_sync_batch_size();
    if ($queue_total > 300) {
        $batch_size = min($batch_size, 8);
    } elseif ($queue_total > 150) {
        $batch_size = min($batch_size, 10);
    }
    $state = array(
        'version' => 5,
        'token' => $token,
        'user_id' => get_current_user_id(),
        'post_id' => (int) $post_id,
        'product_id' => (int) $product_id,
        'style_attr_key' => sanitize_text_field($style_attr_key),
        'license_attr_key' => sanitize_text_field($license_attr_key),
        'style_taxonomy' => $style_taxonomy,
        'license_taxonomy' => $license_taxonomy,
        'signature' => ath_specimen_woo_sync_signature($post_id, $style_attr_key, $license_attr_key),
        'queue' => $queue,
        'existing' => $existing,
        'desired' => $desired,
        'offset' => 0,
        'total' => $queue_total,
        'batch_size' => $batch_size,
        'stats' => array('created' => 0, 'updated' => 0, 'skipped' => 0, 'priced' => 0, 'downloaded' => 0, 'retired' => 0),
        'lock_key' => ath_specimen_woo_sync_lock_key($post_id, $product_id),
        'started_at' => time(),
        'compatibility_preflight' => 1,
    );
    if (!ath_specimen_woo_sync_store_state($state)) {
        return new WP_Error('ath_woo_state', __('Woo sync could not persist its resumable state or retain the product lock safely. No variation batch was started; review the product and retry.', 'authentype-font-specimen'));
    }
    return $state;
}

function ath_specimen_load_woo_sync_state($token) {
    $token = sanitize_text_field((string) $token);
    if (!$token) return new WP_Error('ath_woo_token', __('Woo sync session token is missing.', 'authentype-font-specimen'));
    $state = get_transient(ath_specimen_woo_sync_state_key($token));
    if (empty($state) || !is_array($state) || empty($state['token']) || !hash_equals((string) $state['token'], $token)) {
        return new WP_Error('ath_woo_expired', __('Woo sync session expired. Click Sync Existing Woo Product to start again.', 'authentype-font-specimen'));
    }
    if ((int) $state['user_id'] !== get_current_user_id()) {
        return new WP_Error('ath_woo_user', __('This Woo sync session belongs to another administrator.', 'authentype-font-specimen'));
    }
    if (empty($state['post_id']) || !current_user_can('edit_post', (int) $state['post_id'])) {
        return new WP_Error('ath_woo_permission', __('Permission denied.', 'authentype-font-specimen'));
    }
    if (empty($state['product_id']) || !current_user_can('edit_post', (int) $state['product_id'])) {
        return new WP_Error('ath_woo_product_permission', __('You cannot edit the selected WooCommerce product.', 'authentype-font-specimen'));
    }
    $ownership = ath_specimen_woo_product_ownership_guard((int) $state['post_id'], (int) $state['product_id']);
    if (is_wp_error($ownership)) return $ownership;

    $current_lock_key = ath_specimen_woo_sync_lock_key((int) $state['post_id'], (int) $state['product_id']);
    if (empty($state['lock_key']) || !hash_equals((string) $current_lock_key, (string) $state['lock_key'])) {
        if (!ath_specimen_woo_sync_acquire_lock($current_lock_key, $token, 2 * HOUR_IN_SECONDS)) {
            return new WP_Error('ath_woo_lock', __('Another Woo sync session currently owns this WooCommerce product.', 'authentype-font-specimen'));
        }
        if (!empty($state['lock_key'])) ath_specimen_woo_sync_release_lock($state['lock_key'], $token);
        $state['lock_key'] = $current_lock_key;
        if (!ath_specimen_woo_sync_store_state($state)) {
            return new WP_Error('ath_woo_state', __('Woo sync state could not be persisted safely. Start the sync again.', 'authentype-font-specimen'));
        }
    }
    if (!empty($state['lock_key'])) {
        $locked_token = ath_specimen_woo_sync_read_lock($state['lock_key']);
        if ($locked_token && !hash_equals((string) $locked_token, (string) $token)) {
            return new WP_Error('ath_woo_lock', __('This Woo sync session no longer owns the product sync lock.', 'authentype-font-specimen'));
        }
        if (!$locked_token && !ath_specimen_woo_sync_acquire_lock($state['lock_key'], $token, 2 * HOUR_IN_SECONDS)) {
            return new WP_Error('ath_woo_lock', __('The Woo sync product lock could not be restored.', 'authentype-font-specimen'));
        }
    }
    return $state;
}

function ath_specimen_finalize_woo_sync($state) {
    $product_id = (int) $state['product_id'];
    $retired = ath_specimen_retire_stale_variations($state);
    if (!isset($state['stats']['retired'])) $state['stats']['retired'] = 0;
    $state['stats']['retired'] += $retired;
    if (class_exists('WC_Product_Variable') && method_exists('WC_Product_Variable', 'sync')) {
        WC_Product_Variable::sync($product_id);
    }
    if (function_exists('wc_delete_product_transients')) {
        wc_delete_product_transients($product_id);
    }
    clean_post_cache($product_id);
    if (!empty($state['signature']) && !empty($state['post_id'])) {
        update_post_meta((int) $state['post_id'], '_ath_woo_synced_signature', (string) $state['signature']);
        update_post_meta((int) $state['post_id'], '_ath_woo_synced_at', time());
    }

    $stats = $state['stats'];
    $message = sprintf(
        __('Woo product #%1$d synced in batches. %2$d created, %3$d updated, %4$d already current, %5$d price changes, %6$d download changes, %7$d retired stale variations.', 'authentype-font-specimen'),
        $product_id,
        (int) $stats['created'],
        (int) $stats['updated'],
        (int) $stats['skipped'],
        (int) $stats['priced'],
        (int) $stats['downloaded'],
        (int) $stats['retired']
    );
    $public = ath_specimen_woo_sync_public_state($state, $message);
    $public['complete'] = true;
    $public['percent'] = 100;
    ath_specimen_woo_sync_delete_state($state);
    return $public;
}

function ath_specimen_process_woo_sync_batch($state) {
    if ((bool) ath_specimen_get_meta($state['post_id'], '_ath_pricing_needs_review', false)) {
        ath_specimen_woo_sync_delete_state($state);
        return new WP_Error('ath_woo_pricing_review', __('Woo Sync stopped: secure inventory changed and Pricing now needs review. Review/save the Price Matrix before starting a new sync.', 'authentype-font-specimen'));
    }
    $current_signature = ath_specimen_woo_sync_signature($state['post_id'], $state['style_attr_key'], $state['license_attr_key']);
    if (!hash_equals((string) $state['signature'], (string) $current_signature)) {
        ath_specimen_woo_sync_delete_state($state);
        return new WP_Error('ath_woo_stale', __('Athtyp style/license/download data changed during sync. Click Sync Existing Woo Product again to start a fresh batch session.', 'authentype-font-specimen'));
    }

    $product = wc_get_product((int) $state['product_id']);
    if (!$product || !$product->is_type('variable')) {
        ath_specimen_woo_sync_delete_state($state);
        return new WP_Error('ath_woo_product', __('The linked WooCommerce product is no longer a variable product.', 'authentype-font-specimen'));
    }

    // Upgrade safety for a paused sync session created by an older plugin
    // version. New 8.2.4 sessions are already preflighted before preparation;
    // legacy sessions must pass the same read-only structure guard before the
    // next mutation is allowed to continue.
    if (empty($state['compatibility_preflight'])) {
        $compatibility = ath_specimen_woo_sync_compatibility_preflight($product, $state['style_taxonomy'], $state['license_taxonomy']);
        if (is_wp_error($compatibility)) {
            ath_specimen_woo_sync_delete_state($state);
            return $compatibility;
        }
        $state['compatibility_preflight'] = 1;
    }

    $price_matrix = ath_specimen_get_meta($state['post_id'], '_ath_price_matrix', array());
    $price_matrix = is_array($price_matrix) ? $price_matrix : array();
    $product_downloads = ath_specimen_get_meta($state['post_id'], '_ath_product_downloads', array());
    $product_downloads = is_array($product_downloads) ? $product_downloads : array();
    $limit = max(1, (int) $state['batch_size']);
    $handled = 0;

    while ((int) $state['offset'] < (int) $state['total'] && $handled < $limit) {
        $pair = $state['queue'][(int) $state['offset']];
        $style_value = isset($pair[0]) ? ath_specimen_slug($pair[0]) : '';
        $license_value = isset($pair[1]) ? ath_specimen_slug($pair[1]) : '';
        $lookup_key = $style_value . '|' . $license_value;

        try {
            $variation = null;
            $is_new = false;
            if (!empty($state['existing'][$lookup_key])) {
                $variation = wc_get_product((int) $state['existing'][$lookup_key]);
                if (!$variation) {
                    unset($state['existing'][$lookup_key]);
                }
            }

            if (!$variation) {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id((int) $state['product_id']);
                $variation->set_status('publish');
                $variation->set_attributes(array(
                    $state['style_taxonomy'] => $style_value,
                    $state['license_taxonomy'] => $license_value,
                ));
                $is_new = true;
            }

            $state_changed = ath_specimen_activate_managed_variation($variation);
            $price_changed = ath_specimen_apply_matrix_price_to_variation($variation, $price_matrix, $style_value, $license_value);
            $downloads_changed = ath_specimen_apply_downloads_to_variation($variation, $product_downloads, $style_value, $license_value);

            if ($is_new || $state_changed || $price_changed || $downloads_changed) {
                $variation_id = $variation->save();
                if (!$variation_id) {
                    throw new RuntimeException(__('WooCommerce did not return a variation ID after save.', 'authentype-font-specimen'));
                }
                $state['existing'][$lookup_key] = (int) $variation_id;
                if ($is_new) {
                    $state['stats']['created']++;
                } else {
                    $state['stats']['updated']++;
                }
            } else {
                $state['stats']['skipped']++;
            }

            if ($price_changed) $state['stats']['priced']++;
            if ($downloads_changed) $state['stats']['downloaded']++;
            $state['offset']++;
            $handled++;
        } catch (Throwable $error) {
            // Persist all successfully completed pairs in this batch so Retry/Resume
            // continues at the exact failing combination instead of duplicating work.
            if (!ath_specimen_woo_sync_store_state($state)) {
                return new WP_Error('ath_woo_state', __('Woo sync paused because progress could not be persisted safely. Start the sync again; completed Woo changes were not rolled back.', 'authentype-font-specimen'));
            }
            return new WP_Error(
                'ath_woo_variation',
                sprintf(
                    __('Woo sync paused at style "%1$s" / license "%2$s": %3$s', 'authentype-font-specimen'),
                    $style_value,
                    $license_value,
                    wp_strip_all_tags($error->getMessage())
                ),
                array(
                    'style' => $style_value,
                    'license' => $license_value,
                    'processed' => (int) $state['offset'],
                    'total' => (int) $state['total'],
                )
            );
        }
    }

    if ((int) $state['offset'] >= (int) $state['total']) {
        return ath_specimen_finalize_woo_sync($state);
    }

    if (!ath_specimen_woo_sync_store_state($state)) {
        return new WP_Error('ath_woo_state', __('Woo sync paused because progress could not be persisted safely. Start the sync again; completed Woo changes were not rolled back.', 'authentype-font-specimen'));
    }
    $message = sprintf(
        __('Syncing Woo product #%1$d: %2$d of %3$d variations processed.', 'authentype-font-specimen'),
        (int) $state['product_id'],
        (int) $state['offset'],
        (int) $state['total']
    );
    return ath_specimen_woo_sync_public_state($state, $message);
}

add_action('wp_ajax_ath_specimen_build_woo', function () {
    check_ajax_referer('ath_specimen_admin', 'nonce');

    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    if (!authentype_specimen_can_manage_internal() || !$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen'), 'restart' => true), 403);
    }
    if (!class_exists('WC_Product_Variable') || !class_exists('WC_Product_Variation')) {
        wp_send_json_error(array('message' => __('WooCommerce is unavailable.', 'authentype-font-specimen'), 'restart' => true), 400);
    }

    $phase = isset($_POST['phase']) ? sanitize_key(wp_unslash($_POST['phase'])) : 'init';

    if ('batch' === $phase) {
        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';
        $state = ath_specimen_load_woo_sync_state($token);
        if (is_wp_error($state)) {
            wp_send_json_error(array('message' => $state->get_error_message(), 'code' => $state->get_error_code(), 'restart' => true), 409);
        }
        if ((int) $state['post_id'] !== $post_id) {
            wp_send_json_error(array('message' => __('Woo sync session does not match this Athtyp post.', 'authentype-font-specimen'), 'restart' => true), 409);
        }

        $result = ath_specimen_process_woo_sync_batch($state);
        if (is_wp_error($result)) {
            if (function_exists('ath_specimen_stability_record_error')) ath_specimen_stability_record_error('woo_sync_batch', $result, array('post_id' => $post_id, 'product_id' => absint($state['product_id'] ?? 0), 'phase' => 'batch'));
            $code = $result->get_error_code();
            $data = $result->get_error_data();
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
                'code' => $code,
                'restart' => in_array($code, array('ath_woo_stale', 'ath_woo_pricing_review'), true),
                'progress' => is_array($data) ? $data : array(),
            ), 500);
        }
        wp_send_json_success($result);
    }

    $client_schema = isset($_POST['pricing_schema']) ? sanitize_text_field(wp_unslash($_POST['pricing_schema'])) : '';
    $current_styles = ath_specimen_get_meta($post_id, '_ath_font_styles', array());
    $current_licenses = ath_specimen_get_meta($post_id, '_ath_license_options', array());
    $current_schema = ath_specimen_pricing_schema_signature($current_styles, $current_licenses);
    if (!$client_schema || !hash_equals((string) $current_schema, (string) $client_schema)) {
        wp_send_json_error(array(
            'message' => __('This admin page is stale because Style or License inventory changed. Reload the Athtyp edit page before starting Woo Sync.', 'authentype-font-specimen'),
            'restart' => true,
        ), 409);
    }
    $client_pricing_hash = isset($_POST['pricing_hash']) ? sanitize_text_field(wp_unslash($_POST['pricing_hash'])) : '';
    $current_pricing_hash = ath_specimen_current_pricing_hash($post_id);
    if (!$client_pricing_hash || !hash_equals((string) $current_pricing_hash, (string) $client_pricing_hash)) {
        wp_send_json_error(array(
            'message' => __('This admin page is stale because Pricing changed on the server. Reload the Athtyp edit page before starting Woo Sync.', 'authentype-font-specimen'),
            'restart' => true,
        ), 409);
    }

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : (int) ath_specimen_get_meta($post_id, '_ath_linked_product', 0);
    if (!$product_id) {
        wp_send_json_error(array('message' => __('Select an existing WooCommerce variable product first. The plugin will not create a product automatically.', 'authentype-font-specimen'), 'restart' => true), 400);
    }
    $style_attr_key = isset($_POST['style_attribute']) ? sanitize_text_field(wp_unslash($_POST['style_attribute'])) : ath_specimen_get_meta($post_id, '_ath_style_attribute', 'pa_style');
    $license_attr_key = isset($_POST['license_attribute']) ? sanitize_text_field(wp_unslash($_POST['license_attribute'])) : ath_specimen_get_meta($post_id, '_ath_license_attribute', 'pa_license');

    if (function_exists('ath_specimen_stability_cross_engine_guard')) {
        $operation_guard = ath_specimen_stability_cross_engine_guard($post_id, $product_id, array('woo'));
        if (is_wp_error($operation_guard)) {
            if (function_exists('ath_specimen_stability_record_error')) ath_specimen_stability_record_error('woo_sync_init', $operation_guard, array('post_id' => $post_id, 'product_id' => $product_id));
            wp_send_json_error(array('message' => $operation_guard->get_error_message(), 'code' => $operation_guard->get_error_code(), 'restart' => true), 409);
        }
    }

    // Resume a still-valid server-side session after a page refresh or network interruption.
    $lock_key = ath_specimen_woo_sync_lock_key($post_id, $product_id);
    $locked_token = ath_specimen_woo_sync_read_lock($lock_key);
    if ($locked_token) {
        $raw_locked_state = get_transient(ath_specimen_woo_sync_state_key($locked_token));
        if (is_array($raw_locked_state) && !empty($raw_locked_state['token']) && hash_equals((string) $raw_locked_state['token'], (string) $locked_token)) {
            if ((int) ($raw_locked_state['user_id'] ?? 0) !== get_current_user_id()) {
                wp_send_json_error(array(
                    'message' => __('Another administrator is already syncing this WooCommerce product. Wait for that sync to finish or for the session to expire.', 'authentype-font-specimen'),
                    'restart' => false,
                ), 409);
            }
            $locked_state = ath_specimen_load_woo_sync_state($locked_token);
            if (!is_wp_error($locked_state)) {
                $current_signature = ath_specimen_woo_sync_signature($post_id, $style_attr_key, $license_attr_key);
                if (hash_equals((string) $locked_state['signature'], (string) $current_signature)) {
                    $public = ath_specimen_woo_sync_public_state($locked_state, __('Resuming the previous Woo sync session.', 'authentype-font-specimen'));
                    $public['resumed'] = true;
                    wp_send_json_success($public);
                }
                ath_specimen_woo_sync_delete_state($locked_state);
            }
        } else {
            ath_specimen_woo_sync_release_lock($lock_key, $locked_token);
        }
    }

    // Reserve the product atomically before preparing attributes/variations so
    // two simultaneous init requests cannot both enter the mutation phase.
    $reserved_token = wp_generate_password(40, false, false);
    if (!ath_specimen_woo_sync_acquire_lock($lock_key, $reserved_token, 15 * MINUTE_IN_SECONDS)) {
        wp_send_json_error(array(
            'message' => __('Another administrator is already syncing this WooCommerce product. Wait for that sync to finish or for the session to expire.', 'authentype-font-specimen'),
            'restart' => false,
        ), 409);
    }
    // Re-check long-running Athtyp mutations after owning the Woo lock. This
    // closes the small check/acquire race with a Secure Assets build that may
    // have started between the earlier read-only guard and this atomic lock.
    if (function_exists('ath_specimen_stability_cross_engine_guard')) {
        $operation_guard = ath_specimen_stability_cross_engine_guard($post_id, $product_id, array('woo'));
        if (is_wp_error($operation_guard)) {
            ath_specimen_woo_sync_release_lock($lock_key, $reserved_token);
            if (function_exists('ath_specimen_stability_record_error')) ath_specimen_stability_record_error('woo_sync_init', $operation_guard, array('post_id' => $post_id, 'product_id' => $product_id, 'phase' => 'post_lock'));
            wp_send_json_error(array('message' => $operation_guard->get_error_message(), 'code' => $operation_guard->get_error_code(), 'restart' => true), 409);
        }
    }

    $state = ath_specimen_prepare_woo_sync_state($post_id, $product_id, $style_attr_key, $license_attr_key, $reserved_token);
    if (is_wp_error($state)) {
        ath_specimen_woo_sync_release_lock($lock_key, $reserved_token);
        if (function_exists('ath_specimen_stability_record_error')) ath_specimen_stability_record_error('woo_sync_prepare', $state, array('post_id' => $post_id, 'product_id' => $product_id));
        wp_send_json_error(array('message' => $state->get_error_message(), 'code' => $state->get_error_code(), 'restart' => true), 400);
    }

    if (empty($state['total'])) {
        wp_send_json_success(ath_specimen_finalize_woo_sync($state));
    }

    $public = ath_specimen_woo_sync_public_state($state, sprintf(
        __('Woo batch sync ready: %1$d variations, %2$d per request.', 'authentype-font-specimen'),
        (int) $state['total'],
        (int) $state['batch_size']
    ));
    wp_send_json_success($public);
});

?>
