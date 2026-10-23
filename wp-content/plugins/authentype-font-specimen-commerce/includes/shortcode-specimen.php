<?php
defined('ABSPATH') || exit;

function ath_specimen_get_styles($post_id) {
    $rows = ath_specimen_get_meta($post_id, '_ath_font_styles', array());
    $styles = array();

    if (!is_array($rows)) return $styles;

    foreach ($rows as $index => $row) {
        if (empty($row['style_name']) || empty($row['font_file'])) continue;

        $style_name = (string) $row['style_name'];
        $weight = !empty($row['font_weight']) ? (int) $row['font_weight'] : 400;
        $font_style = !empty($row['font_style']) ? sanitize_key($row['font_style']) : 'normal';
        $variation_value = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : ath_specimen_slug($style_name);
        $is_package = !empty($row['is_package']) || in_array($variation_value, array('full-style', 'fullstyle', 'bundle-full-style', 'all-styles', 'family-pack', 'complete-family'), true);
        $family = 'ath-specimen-' . $post_id . '-' . ($index + 1);
        // Do not hydrate font metadata during the initial page request. secure.7
        // fetches one protected metadata record only when Glyphs/Tech Specs is
        // opened for this style.
        $font_info = array('features' => array(), 'codepoints' => array(), 'ligatures' => array(), 'format' => '', 'tech' => array());

        $styles[] = array(
            'id' => 'style-' . ($index + 1),
            'token' => function_exists('ath_specimen_preview_token') ? ath_specimen_preview_token($post_id, 'style', $index + 1) : 'style-' . ($index + 1),
            'name' => $style_name,
            'family' => $family,
            'weight' => $weight,
            'font_style' => $font_style,
            'variation_value' => $variation_value,
            'default_selected' => !empty($row['default_selected']),
            'is_package' => $is_package,
            'features' => !empty($font_info['features']) ? $font_info['features'] : array(),
            'codepoints' => !empty($font_info['codepoints']) ? $font_info['codepoints'] : array(),
            'ligatures' => !empty($font_info['ligatures']) ? $font_info['ligatures'] : array(),
            'format' => !empty($font_info['format']) ? $font_info['format'] : '',
            'tech' => !empty($font_info['tech']) && is_array($font_info['tech']) ? $font_info['tech'] : array(),
        );
    }

    return $styles;
}

function ath_specimen_get_pairing_fonts($post_id) {
    $rows = ath_specimen_get_meta($post_id, '_ath_pairing_fonts', array());
    $fonts = array();

    if (!is_array($rows)) return $fonts;

    foreach ($rows as $index => $row) {
        if (empty($row['pair_name']) || empty($row['font_file'])) continue;

        $weight = !empty($row['font_weight']) ? (int) $row['font_weight'] : 400;
        $font_style = !empty($row['font_style']) ? sanitize_key($row['font_style']) : 'normal';
        $pair_key = !empty($row['pair_key']) ? ath_specimen_slug($row['pair_key']) : ath_specimen_slug($row['pair_name']);
        $family = 'ath-specimen-' . $post_id . '-pair-' . ($index + 1);

        $fonts[] = array(
            'id' => 'pair-' . ($index + 1),
            'token' => function_exists('ath_specimen_preview_token') ? ath_specimen_preview_token($post_id, 'pair', $index + 1) : 'pair-' . ($index + 1),
            'key' => $pair_key,
            'name' => (string) $row['pair_name'],
            'family' => $family,
            'weight' => $weight,
            'font_style' => $font_style,
            'product_url' => !empty($row['product_url']) ? esc_url_raw($row['product_url']) : '',
            'default_selected' => !empty($row['default_selected']),
            'use_title' => array_key_exists('use_title', $row) ? !empty($row['use_title']) : true,
            'use_body' => array_key_exists('use_body', $row) ? !empty($row['use_body']) : true,
            'default_title' => !empty($row['default_title']),
            'default_body' => array_key_exists('default_body', $row) ? !empty($row['default_body']) : !empty($row['default_selected']),
        );
    }

    return $fonts;
}

function ath_specimen_get_pair_cards($post_id) {
    $rows = ath_specimen_get_meta($post_id, '_ath_pair_cards', array());
    $cards = array();

    if (!is_array($rows)) return $cards;

    foreach ($rows as $row) {
        $title_font = isset($row['title_font']) ? ath_specimen_slug($row['title_font']) : '';
        $body_font = isset($row['body_font']) ? ath_specimen_slug($row['body_font']) : '';
        if (!$title_font && !$body_font) continue;

        $cards[] = array(
            'title_font' => $title_font,
            'body_font' => $body_font,
            'heading_text' => !empty($row['heading_text']) ? (string) $row['heading_text'] : '',
            'body_text' => !empty($row['body_text']) ? (string) $row['body_text'] : '',
            'product_url' => !empty($row['product_url']) ? esc_url_raw($row['product_url']) : '',
        );
    }

    return array_slice($cards, 0, 4);
}

function ath_specimen_default_pairing_font_id($pairing_fonts, $target = 'body') {
    if (empty($pairing_fonts)) return '';

    $use_key = 'title' === $target ? 'use_title' : 'use_body';
    $default_key = 'title' === $target ? 'default_title' : 'default_body';

    foreach ($pairing_fonts as $font) {
        if (!empty($font[$default_key]) && !empty($font[$use_key])) {
            return $font['id'];
        }
    }

    return '';
}

function ath_specimen_character_sets() {
    return array(
        'Basic Latin' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ abcdefghijklmnopqrstuvwxyz 0123456789',
        'Basic Cyrillic' => 'АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ абвгдежзийклмнопрстуфхцчшщъыьэюя',
        'Extended Latin' => 'ÀÁÂÃÄÅĀĂĄ ÇĆĈĊČ ÐĎ ÈÉÊËĒĔĖĘĚ ĜĞĠĢ ÌÍÎÏĨĪĬĮİ ŁŃÑŅŇ ÒÓÔÕÖØŌŎŐ ŔŖŘ ŚŜŞŠ ŢŤ ÙÚÛÜŨŪŬŮŰŲ ÝŶŸ ŹŻŽ',
        'Extended Cyrillic' => 'ЀЁЂЃЄЅІЇЈЉЊЋЌЍЎЏ ҐҒҔҖҚҠҢҪҮҰҲҶӀӁӐӒӔӖӘӜӞӢӤӦӨӮӰӲ',
        'Greek' => 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩ αβγδεζηθικλμνξοπρστυφχψω άέήίόύώϊϋ',
        'Vietnam' => 'Ă Â Đ Ê Ô Ơ Ư ă â đ ê ô ơ ư ẮẰẲẴẶ ẤẦẨẪẬ ẾỀỂỄỆ ỐỒỔỖỘ ỚỜỞỠỢ ỨỪỬỮỰ',
        'Arabic' => 'ابتثجحخدذرزسشصضطظعغفقكلمنهوي ءآأؤإئ',
        'Fractions' => '¼ ½ ¾ ⅓ ⅔ ⅛ ⅜ ⅝ ⅞ 1/2 3/4 12/16',
        'Figures and Currencies' => '0123456789 $ € £ ¥ ₩ ₹ ₿ ¢ ₫ ₱ ₽ ₺ ₪',
        'Indo-Arabic Figures' => '٠١٢٣٤٥٦٧٨٩ ۰۱۲۳۴۵۶۷۸۹',
        'Punctuation and Symbols' => '.,:;… !? ¡¿ · • * # / \\ | @ & ¶ § © ® ™ ° † ‡',
        'Arabic Punctuation' => '، ؛ ؟ ٪ ٫ ٬ ؍ ؎ ؏',
        'Math Symbols' => '+ − × ÷ = ≠ ≈ < > ≤ ≥ ± ∞ ∑ ∏ √ ∫ ∂ ∆ ∇ % ‰',
        'Arrows' => '← ↑ → ↓ ↔',
        'Icons' => '★ ☆ ◆ ◇ ● ○ ■ □ ▲ △ ▼ ▽ ✓ ✕ ✦ ✧ ♡ ♢ ♧ ♤',
    );
}

function ath_specimen_feature_labels() {
    return array(
        'aalt' => 'Access All Alternates',
        'calt' => 'Contextual Alternates',
        'case' => 'Case Sensitive Forms',
        'ccmp' => 'Glyph Composition',
        'dlig' => 'Discretionary Ligatures',
        'frac' => 'Fractions',
        'kern' => 'Kerning',
        'liga' => 'Ligatures',
        'lnum' => 'Lining Figures',
        'onum' => 'Oldstyle Figures',
        'pnum' => 'Proportional Figures',
        'salt' => 'Stylistic Alternates',
        'smcp' => 'Small Caps',
        'ss01' => 'Stylistic Set 01',
        'ss02' => 'Stylistic Set 02',
        'ss03' => 'Stylistic Set 03',
        'ss04' => 'Stylistic Set 04',
        'ss05' => 'Stylistic Set 05',
        'ss06' => 'Stylistic Set 06',
        'ss07' => 'Stylistic Set 07',
        'ss08' => 'Stylistic Set 08',
        'ss09' => 'Stylistic Set 09',
        'ss10' => 'Stylistic Set 10',
        'swsh' => 'Swashes',
        'tnum' => 'Tabular Figures',
    );
}

function ath_specimen_available_features($styles) {
    $labels = ath_specimen_feature_labels();
    $features = array();

    foreach ($styles as $style) {
        foreach ($style['features'] as $tag) {
            $features[$tag] = isset($labels[$tag]) ? $labels[$tag] : strtoupper($tag);
        }
    }

    ksort($features);
    return $features;
}

function ath_specimen_default_style_id($styles, $preferred = 'regular') {
    if (empty($styles)) return '';

    $preferred = ath_specimen_slug($preferred ?: 'regular');
    foreach ($styles as $style) {
        if ($style['variation_value'] === $preferred || ath_specimen_slug($style['name']) === $preferred) {
            return $style['id'];
        }
    }

    foreach ($styles as $style) {
        if ('regular' === $style['variation_value'] || 'regular' === ath_specimen_slug($style['name'])) {
            return $style['id'];
        }
    }

    return $styles[0]['id'];
}

function ath_specimen_regular_style_id($styles) {
    return ath_specimen_default_style_id($styles, 'regular');
}

function ath_specimen_get_licenses($post_id) {
    $rows = ath_specimen_get_meta($post_id, '_ath_license_options', array());
    $licenses = array();

    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (empty($row['license_label']) || empty($row['license_variation_value'])) continue;

            $licenses[] = array(
                'label' => (string) $row['license_label'],
                'value' => ath_specimen_slug($row['license_variation_value']),
                'description' => !empty($row['license_description']) ? (string) $row['license_description'] : '',
                'group' => ath_specimen_license_display_group($row),
                'featured' => ath_specimen_license_is_featured($row) ? 1 : 0,
                'checkout_type' => ath_specimen_license_checkout_type($row),
                'icon' => ath_specimen_license_icon_key($row),
            );
        }
    }

    if (empty($licenses)) {
        $licenses = array(
            array('label' => 'Desktop', 'value' => 'desktop', 'description' => 'For local desktop use.', 'group' => 'common', 'featured' => 1, 'checkout_type' => 'pay_once', 'icon' => 'desktop'),
            array('label' => 'Webfont', 'value' => 'webfont', 'description' => 'For website embedding.', 'group' => 'common', 'featured' => 0, 'checkout_type' => 'pay_once', 'icon' => 'web'),
            array('label' => 'App', 'value' => 'app', 'description' => 'For app or software use.', 'group' => 'common', 'featured' => 0, 'checkout_type' => 'pay_once', 'icon' => 'app'),
        );
    }

    return $licenses;
}

function ath_specimen_font_post_is_resolvable($font_id) {
    $font_id = absint($font_id);
    if (!$font_id || 'ath_font' !== get_post_type($font_id)) return false;
    if ('publish' === get_post_status($font_id)) return true;

    // Draft/Private specimen records are preparation data, not storefront data.
    // Editors can still preview them while normal visitors cannot resolve them.
    return current_user_can('edit_post', $font_id);
}

function ath_specimen_resolve_font_post_id($requested_id = 0) {
    if ($requested_id) {
        return ath_specimen_font_post_is_resolvable($requested_id) ? (int) $requested_id : 0;
    }

    $current_id = get_the_ID();
    if (!$current_id) return 0;

    if ('ath_font' === get_post_type($current_id)) {
        return ath_specimen_font_post_is_resolvable($current_id) ? (int) $current_id : 0;
    }

    if ('product' === get_post_type($current_id)) {
        $can_preview_nonpublic = current_user_can('edit_post', $current_id);
        $linked_fonts = get_posts(array(
            'post_type' => 'ath_font',
            'post_status' => $can_preview_nonpublic ? array('publish', 'draft', 'private') : array('publish'),
            'posts_per_page' => 5,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => array(
                array(
                    'key' => '_ath_linked_product',
                    'value' => (int) $current_id,
                    'compare' => '=',
                    'type' => 'NUMERIC',
                ),
            ),
        ));

        // Prefer a Published record whenever one exists. Editors may fall back
        // to Draft/Private only for explicit preparation/preview work.
        foreach ((array) $linked_fonts as $font_id) {
            if ('publish' === get_post_status((int) $font_id)) return (int) $font_id;
        }
        if ($can_preview_nonpublic) {
            foreach ((array) $linked_fonts as $font_id) {
                if (ath_specimen_font_post_is_resolvable((int) $font_id)) return (int) $font_id;
            }
        }
        return 0;
    }

    return (int) $current_id;
}

function ath_specimen_discount_badge($regular, $sale) {
    $regular = (float) $regular;
    $sale = (float) $sale;
    if ($regular <= 0 || $sale <= 0 || $regular <= $sale) return '';

    $discount = round((($regular - $sale) / $regular) * 100);
    if ($discount <= 0) return '';

    return '<span class="ath-discount-badge">' . esc_html(sprintf(__('%s%% off', 'authentype-font-specimen'), $discount)) . '</span>';
}

function ath_specimen_style_min_price_html($product_id, $style_attr, $style_value) {
    if (!function_exists('wc_get_product')) return '';

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) return '';

    $style_attr = ath_specimen_normalize_attr_key($style_attr);
    $style_value = ath_specimen_slug($style_value);
    $best = null;

    $raw_style_attr = str_replace('attribute_', '', $style_attr);
    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation) continue;
        $attributes = $variation->get_attributes();
        $variation_style = !empty($attributes[$raw_style_attr]) ? ath_specimen_slug($attributes[$raw_style_attr]) : '';
        if ($variation_style !== $style_value || '' === (string) $variation->get_price()) continue;

        $price = function_exists('wc_get_price_to_display') ? (float) wc_get_price_to_display($variation) : (float) $variation->get_price();
        $regular_raw = $variation->get_regular_price();
        $regular = '' !== (string) $regular_raw && function_exists('wc_get_price_to_display')
            ? (float) wc_get_price_to_display($variation, array('price' => (float) $regular_raw))
            : ('' !== (string) $regular_raw ? (float) $regular_raw : $price);
        if (null === $best || $price < $best['price']) {
            $best = array('price' => $price, 'regular' => $regular);
        }
    }

    if (!$best) return '';

    $price_html = function_exists('wc_price') ? wc_price($best['price']) : esc_html($best['price']);
    if ($best['regular'] > $best['price']) {
        $regular_html = function_exists('wc_price') ? wc_price($best['regular']) : esc_html($best['regular']);
        return '<span class="ath-price-current">' . wp_kses_post($price_html) . '</span><del class="ath-price-regular">' . wp_kses_post($regular_html) . '</del>' . ath_specimen_discount_badge($best['regular'], $best['price']);
    }

    return '<span class="ath-price-current">' . wp_kses_post($price_html) . '</span>';
}

function ath_specimen_price_map($product_id, $style_attr, $license_attr) {
    if (!function_exists('wc_get_product')) return array();

    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) return array();

    $style_attr = ath_specimen_normalize_attr_key($style_attr);
    $license_attr = ath_specimen_normalize_attr_key($license_attr);
    $price_map = array();

    $raw_style_attr = str_replace('attribute_', '', $style_attr);
    $raw_license_attr = str_replace('attribute_', '', $license_attr);
    foreach ($product->get_children() as $variation_id) {
        $variation = wc_get_product($variation_id);
        if (!$variation) continue;
        $attributes = $variation->get_attributes();
        $variation_style = !empty($attributes[$raw_style_attr]) ? ath_specimen_slug($attributes[$raw_style_attr]) : '';
        $variation_license = !empty($attributes[$raw_license_attr]) ? ath_specimen_slug($attributes[$raw_license_attr]) : '';

        if (!$variation_style || !$variation_license || '' === (string) $variation->get_price()) continue;
        if (!isset($price_map[$variation_style])) {
            $price_map[$variation_style] = array();
        }

        $price = function_exists('wc_get_price_to_display') ? (float) wc_get_price_to_display($variation) : (float) $variation->get_price();
        $regular_raw = $variation->get_regular_price();
        $regular = '' !== (string) $regular_raw && function_exists('wc_get_price_to_display')
            ? (float) wc_get_price_to_display($variation, array('price' => (float) $regular_raw))
            : ('' !== (string) $regular_raw ? (float) $regular_raw : $price);

        $price_map[$variation_style][$variation_license] = array(
            'price' => $price,
            'regular' => $regular,
            'discount' => $regular > $price && $regular > 0 ? (int) round((($regular - $price) / $regular) * 100) : 0,
        );
    }

    return $price_map;
}


function ath_specimen_price_map_from_matrix($matrix) {
    $map = array();
    if (!is_array($matrix)) return $map;
    foreach ($matrix as $style => $licenses) {
        $style = ath_specimen_slug($style);
        if (!$style || !is_array($licenses)) continue;
        foreach ($licenses as $license => $prices) {
            $license = ath_specimen_slug($license);
            if (!$license || !is_array($prices)) continue;
            $regular = isset($prices['regular']) && '' !== $prices['regular'] ? (float) $prices['regular'] : 0;
            $sale = isset($prices['sale']) && '' !== $prices['sale'] ? (float) $prices['sale'] : 0;
            $sale = ($regular > 0 && $sale > 0 && $sale < $regular) ? $sale : 0;
            $price = $sale > 0 ? $sale : $regular;
            if ($price <= 0) continue;
            if (!isset($map[$style])) $map[$style] = array();
            $map[$style][$license] = array(
                'price' => $price,
                'regular' => $regular > 0 ? $regular : $price,
                'discount' => $regular > $price ? (int) round((($regular - $price) / $regular) * 100) : 0,
            );
        }
    }
    return $map;
}

function ath_specimen_price_map_style_min_html($price_map, $style_value) {
    $style_value = ath_specimen_slug($style_value);
    if (empty($price_map[$style_value]) || !is_array($price_map[$style_value])) return '';
    $best = null;
    foreach ($price_map[$style_value] as $price) {
        if (!isset($price['price'])) continue;
        if (null === $best || (float) $price['price'] < (float) $best['price']) $best = $price;
    }
    if (!$best) return '';
    $current = function_exists('wc_price') ? wc_price((float) $best['price']) : esc_html((string) $best['price']);
    $regular = isset($best['regular']) ? (float) $best['regular'] : (float) $best['price'];
    if ($regular > (float) $best['price']) {
        $regular_html = function_exists('wc_price') ? wc_price($regular) : esc_html((string) $regular);
        return '<span class="ath-price-current">' . wp_kses_post($current) . '</span><del class="ath-price-regular">' . wp_kses_post($regular_html) . '</del>' . ath_specimen_discount_badge($regular, (float) $best['price']);
    }
    return '<span class="ath-price-current">' . wp_kses_post($current) . '</span>';
}

function ath_specimen_bundle_style_value($styles) {
    foreach ($styles as $style) {
        if (!empty($style['is_package'])) return $style['variation_value'];
    }

    foreach ($styles as $style) {
        if (in_array($style['variation_value'], array('full-style', 'fullstyle', 'bundle-full-style', 'family-pack', 'complete-family'), true)) {
            return $style['variation_value'];
        }
    }

    return '';
}


/**
 * Keep Family Packages proof-sheet labels compact. Some adopted catalogs store
 * a full font name (for example "Suspiria ExtraLight") while native Athtyp
 * inventories usually store only the style name ("ExtraLight"). This helper
 * is presentation-only and never changes the underlying variation value.
 */
function ath_specimen_family_style_label($font_title, $style_name) {
    $font_title = trim((string) $font_title);
    $style_name = trim((string) $style_name);
    if ('' === $font_title || '' === $style_name) return $style_name;

    $prefix = $font_title . ' ';
    if (0 === stripos($style_name, $prefix)) {
        $short = trim(substr($style_name, strlen($prefix)));
        if ('' !== $short) return $short;
    }

    return $style_name;
}



function ath_specimen_license_detail_url($post_id, $license) {
    $value = !empty($license['value']) ? ath_specimen_slug($license['value']) : '';
    $default = function_exists('ath_specimen_resolve_license_detail_url')
        ? ath_specimen_resolve_license_detail_url($post_id, $value)
        : home_url('/licenses/' . ($value ? '#' . rawurlencode($value) : ''));
    return apply_filters('authentype_specimen_license_detail_url', $default, $value, $post_id, $license);
}

function ath_specimen_codepoints_sample($codepoints, $limit = 420) {
    if (empty($codepoints) || !is_array($codepoints)) return '';
    $chars = '';
    $count = 0;
    foreach ($codepoints as $cp => $supported) {
        $cp = is_int($cp) ? $cp : (int) $cp;
        if ($cp < 33 || $cp > 0x10ffff || ($cp >= 0xd800 && $cp <= 0xdfff)) continue;
        $chars .= ath_specimen_codepoint_to_utf8($cp);
        if (++$count >= $limit) break;
    }
    return $chars;
}

function ath_specimen_shortcode($atts) {
    $atts = shortcode_atts(array('id' => 0), $atts, 'authentype_font_specimen');
    $post_id = ath_specimen_resolve_font_post_id((int) $atts['id']);
    if (!$post_id) return '';

    $product_id = (int) ath_specimen_get_meta($post_id, '_ath_linked_product', 0);
    $style_attr = ath_specimen_normalize_attr_key(ath_specimen_get_meta($post_id, '_ath_style_attribute', 'pa_style'));
    $license_attr = ath_specimen_normalize_attr_key(ath_specimen_get_meta($post_id, '_ath_license_attribute', 'pa_license'));
    $styles = ath_specimen_get_styles($post_id);
    if (empty($styles)) return '<p class="ath-specimen-note">' . esc_html__('No font styles have been configured yet.', 'authentype-font-specimen') . '</p>';

    // Preserve the optional pairing/free-download features from secure.6. Pairing
    // previews use the same signed server-render tokens, so no font bytes are
    // exposed when these legacy-compatible sections are enabled.
    $pairing_fonts = ath_specimen_get_pairing_fonts($post_id);
    $pair_cards = ath_specimen_get_pair_cards($post_id);
    $pairing_fonts_by_key = array();
    foreach ($pairing_fonts as $pairing_font) {
        if (!empty($pairing_font['key'])) $pairing_fonts_by_key[$pairing_font['key']] = $pairing_font;
    }
    $free_downloads_below_pairs = (bool) ath_specimen_get_meta($post_id, '_ath_free_downloads_below_pairs', '');
    $free_downloads_type = sanitize_key(ath_specimen_get_meta($post_id, '_ath_free_downloads_type', ''));
    $free_downloads_limit = min(48, max(1, absint(ath_specimen_get_meta($post_id, '_ath_free_downloads_limit', '8'))));

    $licenses = ath_specimen_get_licenses($post_id);
    $license_count = count($licenses);
    $license_picker_mode = $license_count <= 6 ? 'simple' : ($license_count <= 10 ? 'compact' : 'expanded');
    $license_groups_present = array_values(array_unique(array_filter(array_map(function ($license) {
        return !empty($license['group']) ? sanitize_key($license['group']) : '';
    }, $licenses))));
    $contact_license = null;
    foreach ($licenses as $license) {
        if (!empty($license['checkout_type']) && 'contact' === $license['checkout_type']) { $contact_license = $license; break; }
    }
    // secure.7.3.7 adaptive license picker: display grouping is UI-only.
    // Pricing/delivery values remain unchanged and still use the license slug.
    // secure.7.3.6 pricing authority: frontend always reads Athtyp Price Matrix.
    // WooCommerce receives a synchronized copy for cart/checkout, but it is not
    // used as a competing preview-price source.
    $price_matrix = ath_specimen_get_meta($post_id, '_ath_price_matrix', array());
    $price_map = ath_specimen_price_map_from_matrix(is_array($price_matrix) ? $price_matrix : array());
    $bundle_style_value = ath_specimen_bundle_style_value($styles);
    $individual_styles = array_values(array_filter($styles, function ($style) { return empty($style['is_package']); }));
    $bundle_style = null;
    foreach ($styles as $style) if (!empty($style['is_package'])) { $bundle_style = $style; break; }
    $default_style_id = ath_specimen_default_style_id($individual_styles, ath_specimen_get_meta($post_id, '_ath_default_specimen_style', 'regular'));
    $default_style = !empty($individual_styles[0]) ? $individual_styles[0] : $styles[0];
    foreach ($individual_styles as $style) if ($style['id'] === $default_style_id) { $default_style = $style; break; }
    $font_title = get_the_title($post_id);
    $style_count = count($individual_styles);

    wp_enqueue_style('authentype-font-specimen');
    wp_enqueue_script('authentype-font-specimen');
    wp_localize_script('authentype-font-specimen', 'AthSpecimen', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ath_specimen_cart'),
        'renderNonce' => wp_create_nonce('ath_specimen_render_preview'),
        'cartUrl' => function_exists('wc_get_cart_url') ? wc_get_cart_url() : '',
        'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
        'currencySymbol' => function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD') : '$',
        'currencyPosition' => function_exists('get_option') ? get_option('woocommerce_currency_pos', 'left') : 'left',
        'priceDecimals' => function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2,
        'decimalSeparator' => function_exists('wc_get_price_decimal_separator') ? wc_get_price_decimal_separator() : '.',
        'thousandSeparator' => function_exists('wc_get_price_thousand_separator') ? wc_get_price_thousand_separator() : ',',
        'multiStyleMaxStyles' => max(1, (int) apply_filters('ath_specimen_cart_max_styles', 50, $product_id)),
        'multiStyleMaxLicenses' => max(1, (int) apply_filters('ath_specimen_cart_max_licenses', 10, $product_id)),
        'multiStyleMaxCombinations' => max(1, (int) apply_filters('ath_specimen_cart_max_combinations', 100, $product_id)),
        'i18n' => array(
            'chooseLicense' => __('Choose a license.', 'authentype-font-specimen'),
            'added' => __('Added to cart.', 'authentype-font-specimen'),
            'failed' => __('Could not add to cart.', 'authentype-font-specimen'),
            'renderFailed' => __('Preview renderer is unavailable on this server.', 'authentype-font-specimen'),
            'loading' => __('Loading preview…', 'authentype-font-specimen'),
            'stylesSelected' => __('styles selected', 'authentype-font-specimen'),
            'styleSelected' => __('style selected', 'authentype-font-specimen'),
            'availableAllStyles' => __('Available for all selected styles', 'authentype-font-specimen'),
            'notAvailableFor' => __('Not available for', 'authentype-font-specimen'),
            'fullFamily' => __('Full Style Bundle', 'authentype-font-specimen'),
            'saveWithFamily' => __('You may save more with the full family.', 'authentype-font-specimen'),
            'switchToFamily' => __('Choose Full Family instead →', 'authentype-font-specimen'),
        ),
    ));

    $has_bundle = $bundle_style && $bundle_style_value;
    $is_single_style = (1 === $style_count && !$has_bundle);
    $product_mode = $has_bundle ? 'family-bundle' : ($is_single_style ? 'single' : 'family');
    $tab_count = $has_bundle ? 5 : 4;
    ob_start();
    ?>
    <div class="ath-specimen ath-specimen-v7 ath-mode-<?php echo esc_attr($product_mode); ?>"
        data-product-mode="<?php echo esc_attr($product_mode); ?>"
        data-font-post-id="<?php echo esc_attr($post_id); ?>"
        data-product-id="<?php echo esc_attr($product_id); ?>"
        data-style-attribute="<?php echo esc_attr($style_attr); ?>"
        data-license-attribute="<?php echo esc_attr($license_attr); ?>"
        data-price-map="<?php echo esc_attr(wp_json_encode($price_map)); ?>"
        data-bundle-style="<?php echo esc_attr($bundle_style_value); ?>">

        <div class="ath-family-intro">
            <strong><?php echo esc_html($font_title); ?></strong>
            <?php if ($has_bundle) : ?>
                <span><?php echo esc_html(sprintf(_n('contains %d style and family package options.', 'contains %d styles and family package options.', $style_count, 'authentype-font-specimen'), $style_count)); ?></span>
            <?php else : ?>
                <span><?php echo esc_html(sprintf(_n('contains %d style.', 'contains %d styles.', $style_count, 'authentype-font-specimen'), $style_count)); ?></span>
            <?php endif; ?>
        </div>

        <nav class="ath-tabs ath-tabs-<?php echo esc_attr($tab_count); ?>" role="tablist" aria-label="<?php esc_attr_e('Font information', 'authentype-font-specimen'); ?>">
            <button type="button" class="ath-tab" role="tab" aria-selected="false" data-tab="glyphs"><span class="ath-tab-glyph">Aa</span><small><?php esc_html_e('Glyphs', 'authentype-font-specimen'); ?></small></button>
            <?php if ($has_bundle) : ?>
                <button type="button" class="ath-tab is-active" role="tab" aria-selected="true" data-tab="family-packages">
                    <span class="ath-best-value">★ <?php esc_html_e('Best Value', 'authentype-font-specimen'); ?></span>
                    <?php esc_html_e('Family Packages', 'authentype-font-specimen'); ?>
                </button>
            <?php endif; ?>
            <button type="button" class="ath-tab<?php echo !$has_bundle ? ' is-active' : ''; ?>" role="tab" aria-selected="<?php echo $has_bundle ? 'false' : 'true'; ?>" data-tab="individual-styles"><?php echo esc_html($is_single_style ? __('Preview', 'authentype-font-specimen') : __('Individual Styles', 'authentype-font-specimen')); ?></button>
            <button type="button" class="ath-tab" role="tab" aria-selected="false" data-tab="tech-specs"><?php esc_html_e('Tech Specs', 'authentype-font-specimen'); ?></button>
            <button type="button" class="ath-tab" role="tab" aria-selected="false" data-tab="licensing"><?php esc_html_e('Licensing', 'authentype-font-specimen'); ?></button>
        </nav>

        <section class="ath-tab-panel" data-panel="glyphs" hidden>
            <div class="ath-panel-toolbar ath-glyph-toolbar<?php echo $is_single_style ? ' ath-single-style-control' : ''; ?>">
                <strong><?php echo esc_html($is_single_style ? __('Full glyph set:', 'authentype-font-specimen') : __('Select style to display all glyphs:', 'authentype-font-specimen')); ?></strong>
                <?php if ($is_single_style) : ?><span class="ath-single-style-name"><?php echo esc_html($default_style['name']); ?></span><?php endif; ?>
                <select class="ath-glyph-style-select<?php echo $is_single_style ? ' ath-single-hidden-select' : ''; ?>">
                    <?php foreach ($individual_styles as $style) : ?>
                        <option value="<?php echo esc_attr($style['id']); ?>" data-token="<?php echo esc_attr($style['token']); ?>" <?php selected($style['id'], $default_style['id']); ?>><?php echo esc_html($style['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <h3 class="ath-glyph-title"><?php echo esc_html($default_style['name']); ?></h3>
            <p class="ath-glyph-description"><?php esc_html_e('Every Glyph ID in the selected style is rasterized on the server, including glyphs with no Unicode value such as alternates, ligatures, ornaments, and .notdef. The browser receives PNG pixels and safe labels only—never the font file or reusable outline paths.', 'authentype-font-specimen'); ?></p>
            <div class="ath-glyph-meta">
                <span data-glyph-count><?php echo !empty($default_style['tech']['glyph_count']) ? esc_html(number_format_i18n((int) $default_style['tech']['glyph_count'])) . ' ' . esc_html__('glyphs total', 'authentype-font-specimen') : ''; ?></span>
                <span data-glyph-coverage></span>
                <span data-glyph-range></span>
            </div>
            <div class="ath-glyph-filters" role="group" aria-label="<?php esc_attr_e('Glyph filters', 'authentype-font-specimen'); ?>">
                <button type="button" class="ath-glyph-filter is-active" data-glyph-filter="all"><?php esc_html_e('All', 'authentype-font-specimen'); ?></button>
                <button type="button" class="ath-glyph-filter" data-glyph-filter="unicode"><?php esc_html_e('Unicode', 'authentype-font-specimen'); ?></button>
                <button type="button" class="ath-glyph-filter" data-glyph-filter="unencoded"><?php esc_html_e('Unencoded', 'authentype-font-specimen'); ?></button>
            </div>
            <div class="ath-glyph-pagination">
                <button type="button" class="ath-page-btn" data-glyph-prev><?php esc_html_e('← Previous', 'authentype-font-specimen'); ?></button>
                <span class="ath-page-status" data-glyph-page><?php esc_html_e('Page 1', 'authentype-font-specimen'); ?></span>
                <button type="button" class="ath-page-btn" data-glyph-next><?php esc_html_e('Next →', 'authentype-font-specimen'); ?></button>
            </div>
            <canvas class="ath-server-canvas ath-glyph-canvas" data-font-token="<?php echo esc_attr($default_style['token']); ?>" data-mode="glyph-grid" data-font-size="38" aria-label="<?php esc_attr_e('Glyph overview', 'authentype-font-specimen'); ?>"></canvas>
        </section>

        <?php if ($has_bundle) : ?>
        <section class="ath-tab-panel is-active ath-family-packages-panel" data-panel="family-packages">
            <div class="ath-section-heading ath-family-package-heading">
                <div>
                    <span class="ath-eyebrow">★ <?php esc_html_e('Best Value', 'authentype-font-specimen'); ?></span>
                    <h3><?php echo esc_html(sprintf(__('%s Complete Family', 'authentype-font-specimen'), $font_title)); ?></h3>
                    <span class="ath-family-style-count"><?php echo esc_html(sprintf(_n('%d style', '%d styles', $style_count, 'authentype-font-specimen'), $style_count)); ?></span>
                </div>
                <p><?php esc_html_e('Compare every included style at once, then choose the license that matches your use.', 'authentype-font-specimen'); ?></p>
            </div>

            <div class="ath-preview-toolbar ath-family-preview-toolbar" data-preview-toolbar="family-packages">
                <textarea class="ath-master-text" rows="1" maxlength="1200" aria-label="<?php esc_attr_e('Family package preview text', 'authentype-font-specimen'); ?>">The quick brown fox jumps over the lazy dog</textarea>
                <label class="ath-size-control"><span>A</span><input type="range" class="ath-size" min="22" max="110" value="38"><strong>A</strong></label>
                <label class="ath-color-control"><input type="color" class="ath-text-color" value="#111111"><span><?php esc_html_e('Color', 'authentype-font-specimen'); ?></span></label>
                <button type="button" class="ath-feature-menu-btn" aria-expanded="false">ff <span>▾</span></button>
                <button type="button" class="ath-reset">↻ <?php esc_html_e('Reset', 'authentype-font-specimen'); ?></button>
                <div class="ath-feature-popover" hidden><strong><?php esc_html_e('Available OpenType features', 'authentype-font-specimen'); ?></strong><div data-feature-list><?php esc_html_e('Choose a style in Tech Specs to inspect its features.', 'authentype-font-specimen'); ?></div></div>
            </div>

            <article class="ath-family-package-proof">
                <header class="ath-family-package-proof-head">
                    <div>
                        <span class="ath-package-kicker"><?php esc_html_e('Complete Family', 'authentype-font-specimen'); ?></span>
                        <h4><?php echo esc_html($font_title); ?></h4>
                    </div>
                    <span class="ath-family-best-value-badge">★ <?php esc_html_e('Best Value', 'authentype-font-specimen'); ?></span>
                </header>

                <div class="ath-family-style-list" role="list" aria-label="<?php esc_attr_e('Styles included in complete family package', 'authentype-font-specimen'); ?>">
                    <?php foreach ($individual_styles as $style) : $family_style_label = ath_specimen_family_style_label($font_title, $style['name']); ?>
                        <div class="ath-family-style-row" role="listitem">
                            <div class="ath-family-style-preview-cell">
                                <canvas class="ath-server-canvas ath-family-style-preview"
                                    data-font-token="<?php echo esc_attr($style['token']); ?>"
                                    data-sync-master="1"
                                    data-mode="style-text"
                                    data-text="The quick brown fox jumps over the lazy dog"
                                    data-font-size="38"
                                    data-fit-single-line="1"
                                    aria-label="<?php echo esc_attr(sprintf(__('%s family package preview', 'authentype-font-specimen'), $family_style_label)); ?>"></canvas>
                            </div>
                            <div class="ath-family-style-name"><?php echo esc_html($family_style_label); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <footer class="ath-family-package-proof-foot">
                    <div class="ath-family-package-summary">
                        <strong><?php echo esc_html(sprintf(_n('Complete %d-style family', 'Complete %d-style family', $style_count, 'authentype-font-specimen'), $style_count)); ?></strong>
                        <span><?php esc_html_e('Includes every available style. File formats are supplied according to the license selected at checkout.', 'authentype-font-specimen'); ?></span>
                    </div>
                    <div class="ath-family-package-commerce">
                        <div class="ath-family-package-price">
                            <span class="ath-from-label"><?php esc_html_e('From', 'authentype-font-specimen'); ?></span>
                            <div class="ath-from-price"><?php echo wp_kses_post(ath_specimen_price_map_style_min_html($price_map, $bundle_style_value)); ?></div>
                        </div>
                        <button type="button" class="ath-buy-choice ath-primary-button ath-purchase-cta" data-style-value="<?php echo esc_attr($bundle_style_value); ?>" data-package-label="<?php esc_attr_e('Complete Family', 'authentype-font-specimen'); ?>" aria-label="<?php esc_attr_e('Choose a license for the complete family package', 'authentype-font-specimen'); ?>"><?php esc_html_e('Choose License', 'authentype-font-specimen'); ?></button>
                    </div>
                </footer>
            </article>
        </section>
        <?php endif; ?>

        <section class="ath-tab-panel<?php echo !$has_bundle ? ' is-active' : ''; ?>" data-panel="individual-styles" <?php echo !$has_bundle ? '' : 'hidden'; ?>>
            <div class="ath-preview-toolbar">
                <textarea class="ath-master-text" rows="1" maxlength="1200" aria-label="<?php esc_attr_e('Preview text', 'authentype-font-specimen'); ?>">The quick brown fox jumps over the lazy dog</textarea>
                <label class="ath-size-control"><span>A</span><input type="range" class="ath-size" min="22" max="110" value="38"><strong>A</strong></label>
                <label class="ath-color-control"><input type="color" class="ath-text-color" value="#111111"><span><?php esc_html_e('Color', 'authentype-font-specimen'); ?></span></label>
                <button type="button" class="ath-feature-menu-btn" aria-expanded="false">ff <span>▾</span></button>
                <button type="button" class="ath-reset">↻ <?php esc_html_e('Reset', 'authentype-font-specimen'); ?></button>
                <div class="ath-feature-popover" hidden><strong><?php esc_html_e('Available OpenType features', 'authentype-font-specimen'); ?></strong><div data-feature-list><?php esc_html_e('Choose a style in Tech Specs to inspect its features.', 'authentype-font-specimen'); ?></div></div>
            </div>

            <?php if (!$is_single_style) : ?>
                <div class="ath-multi-style-bar" data-multi-style-bar hidden aria-live="polite">
                    <div class="ath-multi-style-summary">
                        <strong data-style-selection-count><?php esc_html_e('0 styles selected', 'authentype-font-specimen'); ?></strong>
                        <span data-style-selection-names></span>
                    </div>
                    <div class="ath-multi-style-actions">
                        <button type="button" class="ath-multi-style-clear" data-clear-style-selection><?php esc_html_e('Clear', 'authentype-font-specimen'); ?></button>
                        <button type="button" class="ath-multi-style-buy ath-purchase-cta" data-choose-selected-styles><?php esc_html_e('Buy Selected Styles', 'authentype-font-specimen'); ?></button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ath-individual-list">
                <?php foreach ($individual_styles as $style) : $from_price = ath_specimen_price_map_style_min_html($price_map, $style['variation_value']); ?>
                    <article class="ath-individual-row<?php echo !$is_single_style ? ' ath-has-style-select' : ''; ?>" data-style-value="<?php echo esc_attr($style['variation_value']); ?>" data-style-name="<?php echo esc_attr($style['name']); ?>">
                        <?php if (!$is_single_style) : ?>
                            <label class="ath-style-select-control" title="<?php echo esc_attr(sprintf(__('Select %s', 'authentype-font-specimen'), $style['name'])); ?>">
                                <input type="checkbox" class="ath-style-select" value="<?php echo esc_attr($style['variation_value']); ?>" aria-label="<?php echo esc_attr(sprintf(__('Select %s for multi-style purchase', 'authentype-font-specimen'), $style['name'])); ?>">
                                <span class="ath-style-select-ui" aria-hidden="true"></span>
                            </label>
                        <?php endif; ?>
                        <div class="ath-individual-head">
                            <h4><?php echo esc_html($style['name']); ?></h4>
                            <div class="ath-row-meta">
                                <div class="ath-row-price"><?php if ($from_price) echo '<span class="ath-from-label">' . esc_html__('from', 'authentype-font-specimen') . '</span> ' . wp_kses_post($from_price); ?></div>
                                <button type="button" class="ath-buy-choice ath-buy-row ath-purchase-cta" data-style-value="<?php echo esc_attr($style['variation_value']); ?>" data-package-label="<?php echo esc_attr($style['name']); ?>" aria-label="<?php echo esc_attr(sprintf(__('Buy %s — choose license and pricing', 'authentype-font-specimen'), $style['name'])); ?>"><?php echo esc_html(sprintf(__('Buy %s', 'authentype-font-specimen'), $style['name'])); ?></button>
                            </div>
                        </div>
                        <canvas class="ath-server-canvas ath-style-preview" data-font-token="<?php echo esc_attr($style['token']); ?>" data-sync-master="1" data-mode="style-text" data-text="The quick brown fox jumps over the lazy dog" data-font-size="38" data-fit-single-line="1" aria-label="<?php echo esc_attr(sprintf(__('%s preview', 'authentype-font-specimen'), $style['name'])); ?>"></canvas>
                    </article>
                <?php endforeach; ?>
            </div>


        </section>

        <section class="ath-tab-panel" data-panel="tech-specs" hidden>
            <div class="ath-panel-toolbar ath-tech-toolbar<?php echo $is_single_style ? ' ath-single-style-control' : ''; ?>">
                <strong><?php echo esc_html($is_single_style ? __('Font style:', 'authentype-font-specimen') : __('Select style to display tech specs:', 'authentype-font-specimen')); ?></strong>
                <?php if ($is_single_style) : ?><span class="ath-single-style-name"><?php echo esc_html($default_style['name']); ?></span><?php endif; ?>
                <select class="ath-tech-style-select<?php echo $is_single_style ? ' ath-single-hidden-select' : ''; ?>">
                    <?php foreach ($individual_styles as $style) : ?>
                        <option value="<?php echo esc_attr($style['id']); ?>" data-token="<?php echo esc_attr($style['token']); ?>" <?php selected($style['id'], $default_style['id']); ?>><?php echo esc_html($style['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <canvas class="ath-server-canvas ath-tech-preview" data-font-token="<?php echo esc_attr($default_style['token']); ?>" data-text="The quick brown fox jumps over the lazy dog" data-font-size="38" data-fit-single-line="1" aria-label="<?php esc_attr_e('Technical font preview', 'authentype-font-specimen'); ?>"></canvas>
            <div class="ath-tech-detail-grid">
                <section class="ath-tech-card">
                    <h4><?php esc_html_e('Font Identity', 'authentype-font-specimen'); ?></h4>
                    <dl class="ath-tech-table">
                        <div><dt><?php esc_html_e('Full Font Name:', 'authentype-font-specimen'); ?></dt><dd data-tech="full_name">—</dd></div>
                        <div><dt><?php esc_html_e('Family Name:', 'authentype-font-specimen'); ?></dt><dd data-tech="family_name">—</dd></div>
                        <div><dt><?php esc_html_e('Typographic Family:', 'authentype-font-specimen'); ?></dt><dd data-tech="typographic_family">—</dd></div>
                        <div><dt><?php esc_html_e('Sub-Family Name:', 'authentype-font-specimen'); ?></dt><dd data-tech="subfamily_name">—</dd></div>
                        <div><dt><?php esc_html_e('Typographic Subfamily:', 'authentype-font-specimen'); ?></dt><dd data-tech="typographic_subfamily">—</dd></div>
                        <div><dt><?php esc_html_e('PostScript Name:', 'authentype-font-specimen'); ?></dt><dd data-tech="postscript_name">—</dd></div>
                        <div><dt><?php esc_html_e('Version:', 'authentype-font-specimen'); ?></dt><dd data-tech="version">—</dd></div>
                        <div><dt><?php esc_html_e('Format:', 'authentype-font-specimen'); ?></dt><dd data-tech="format">—</dd></div>
                        <div><dt><?php esc_html_e('Outline:', 'authentype-font-specimen'); ?></dt><dd data-tech="outline">—</dd></div>
                        <div><dt><?php esc_html_e('Variable Font:', 'authentype-font-specimen'); ?></dt><dd data-tech="variable">—</dd></div>
                        <div><dt><?php esc_html_e('File Size:', 'authentype-font-specimen'); ?></dt><dd data-tech="file_size">—</dd></div>
                    </dl>
                </section>

                <section class="ath-tech-card">
                    <h4><?php esc_html_e('Metrics & Coverage', 'authentype-font-specimen'); ?></h4>
                    <dl class="ath-tech-table">
                        <div><dt><?php esc_html_e('Glyphs:', 'authentype-font-specimen'); ?></dt><dd data-tech="glyph_count">—</dd></div>
                        <div><dt><?php esc_html_e('Unicode Characters:', 'authentype-font-specimen'); ?></dt><dd data-tech="unicode_characters">—</dd></div>
                        <div><dt><?php esc_html_e('Encoded Glyphs:', 'authentype-font-specimen'); ?></dt><dd data-tech="encoded_glyphs">—</dd></div>
                        <div><dt><?php esc_html_e('Unencoded Glyphs:', 'authentype-font-specimen'); ?></dt><dd data-tech="unencoded_glyphs">—</dd></div>
                        <div><dt><?php esc_html_e('Units per em:', 'authentype-font-specimen'); ?></dt><dd data-tech="units_per_em">—</dd></div>
                        <div><dt><?php esc_html_e('Weight Class:', 'authentype-font-specimen'); ?></dt><dd data-tech="weight_class">—</dd></div>
                        <div><dt><?php esc_html_e('Width Class:', 'authentype-font-specimen'); ?></dt><dd data-tech="width_class">—</dd></div>
                        <div><dt><?php esc_html_e('Italic Angle:', 'authentype-font-specimen'); ?></dt><dd data-tech="italic_angle">—</dd></div>
                        <div><dt><?php esc_html_e('Fixed Pitch:', 'authentype-font-specimen'); ?></dt><dd data-tech="fixed_pitch">—</dd></div>
                        <div><dt><?php esc_html_e('Typo Ascender:', 'authentype-font-specimen'); ?></dt><dd data-tech="typo_ascender">—</dd></div>
                        <div><dt><?php esc_html_e('Typo Descender:', 'authentype-font-specimen'); ?></dt><dd data-tech="typo_descender">—</dd></div>
                        <div><dt><?php esc_html_e('Typo Line Gap:', 'authentype-font-specimen'); ?></dt><dd data-tech="typo_line_gap">—</dd></div>
                        <div><dt><?php esc_html_e('hhea Ascender:', 'authentype-font-specimen'); ?></dt><dd data-tech="hhea_ascender">—</dd></div>
                        <div><dt><?php esc_html_e('hhea Descender:', 'authentype-font-specimen'); ?></dt><dd data-tech="hhea_descender">—</dd></div>
                        <div><dt><?php esc_html_e('Windows Ascent:', 'authentype-font-specimen'); ?></dt><dd data-tech="win_ascent">—</dd></div>
                        <div><dt><?php esc_html_e('Windows Descent:', 'authentype-font-specimen'); ?></dt><dd data-tech="win_descent">—</dd></div>
                        <div><dt><?php esc_html_e('Cap Height:', 'authentype-font-specimen'); ?></dt><dd data-tech="cap_height">—</dd></div>
                        <div><dt><?php esc_html_e('x-Height:', 'authentype-font-specimen'); ?></dt><dd data-tech="x_height">—</dd></div>
                        <div><dt><?php esc_html_e('Underline Position:', 'authentype-font-specimen'); ?></dt><dd data-tech="underline_position">—</dd></div>
                        <div><dt><?php esc_html_e('Underline Thickness:', 'authentype-font-specimen'); ?></dt><dd data-tech="underline_thickness">—</dd></div>
                        <div><dt><?php esc_html_e('Bounding Box:', 'authentype-font-specimen'); ?></dt><dd data-tech="bbox">—</dd></div>
                    </dl>
                </section>

                <section class="ath-tech-card">
                    <h4><?php esc_html_e('Embedded Font Metadata', 'authentype-font-specimen'); ?></h4>
                    <dl class="ath-tech-table">
                        <div><dt><?php esc_html_e('Designer:', 'authentype-font-specimen'); ?></dt><dd data-tech="designer">—</dd></div>
                        <div><dt><?php esc_html_e('Manufacturer:', 'authentype-font-specimen'); ?></dt><dd data-tech="manufacturer">—</dd></div>
                        <div><dt><?php esc_html_e('Vendor ID:', 'authentype-font-specimen'); ?></dt><dd data-tech="vendor_id">—</dd></div>
                        <div><dt><?php esc_html_e('Unique Font ID:', 'authentype-font-specimen'); ?></dt><dd data-tech="unique_id">—</dd></div>
                        <div><dt><?php esc_html_e('Embedding:', 'authentype-font-specimen'); ?></dt><dd data-tech="embedding">—</dd></div>
                        <div><dt><?php esc_html_e('PANOSE:', 'authentype-font-specimen'); ?></dt><dd data-tech="panose">—</dd></div>
                        <div><dt><?php esc_html_e('Copyright:', 'authentype-font-specimen'); ?></dt><dd data-tech="copyright">—</dd></div>
                        <div><dt><?php esc_html_e('Trademark:', 'authentype-font-specimen'); ?></dt><dd data-tech="trademark">—</dd></div>
                    </dl>
                </section>
            </div>

            <section class="ath-tech-wide-card ath-tech-language-card">
                <div class="ath-tech-card-head">
                    <div><h4><?php esc_html_e('Detected Language Support', 'authentype-font-specimen'); ?></h4><p><?php esc_html_e('Detected from the Unicode cmap actually present in this font style. A language is shown only when its required character repertoire is covered.', 'authentype-font-specimen'); ?></p></div>
                    <strong data-tech-language-count>—</strong>
                </div>
                <div class="ath-tech-script-row"><span><?php esc_html_e('Script coverage:', 'authentype-font-specimen'); ?></span><div data-tech-scripts>—</div></div>
                <div class="ath-language-groups" data-tech-languages>—</div>
            </section>

            <section class="ath-tech-wide-card" data-tech-axes-card hidden>
                <h4><?php esc_html_e('Variable Axes', 'authentype-font-specimen'); ?></h4>
                <div class="ath-axis-list" data-tech-axes>—</div>
            </section>

            <section class="ath-tech-wide-card ath-tech-font-notes" data-tech-notes-card hidden>
                <h4><?php esc_html_e('Font Description & Embedded License Metadata', 'authentype-font-specimen'); ?></h4>
                <div data-tech-description hidden><strong><?php esc_html_e('Description', 'authentype-font-specimen'); ?></strong><p></p></div>
                <div data-tech-license-description hidden><strong><?php esc_html_e('Embedded license metadata', 'authentype-font-specimen'); ?></strong><p></p></div>
            </section>

            <section class="ath-tech-wide-card ath-tech-tables-card">
                <h4><?php esc_html_e('Font Tables', 'authentype-font-specimen'); ?></h4>
                <div data-tech-tables>—</div>
            </section>

            <div class="ath-tech-features"><strong><?php esc_html_e('OpenType Features', 'authentype-font-specimen'); ?></strong><div data-tech-features>—</div></div>
        </section>

        <section class="ath-tab-panel" data-panel="licensing" hidden>
            <div class="ath-license-page-copy">
                <h3><?php esc_html_e('Licensing Options', 'authentype-font-specimen'); ?></h3>
                <p><?php esc_html_e('Choose the license that matches the way the font will be used. For company-wide, agency, high-volume, or unlisted usage, review the full license terms or contact the foundry before purchasing.', 'authentype-font-specimen'); ?></p>
            </div>
            <div class="ath-license-detail-list">
                <?php foreach ($licenses as $license) : ?>
                    <article class="ath-license-detail-card" id="ath-license-<?php echo esc_attr($license['value']); ?>">
                        <div class="ath-license-icon"><?php echo ath_specimen_license_icon_svg($license); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal SVG. ?></div>
                        <div><h4><?php echo esc_html($license['label']); ?>: <span><?php esc_html_e('usage license', 'authentype-font-specimen'); ?></span></h4>
                            <?php if (!empty($license['description'])) : ?><p><?php echo esc_html($license['description']); ?></p><?php endif; ?>
                            <a href="<?php echo esc_url(ath_specimen_license_detail_url($post_id, $license)); ?>"><?php esc_html_e('Read full license details', 'authentype-font-specimen'); ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <?php if (!empty($pair_cards) && !empty($pairing_fonts_by_key)) : ?>
            <section class="ath-pairing-block">
                <div class="ath-subsection-heading"><span><?php esc_html_e('Font Pairs', 'authentype-font-specimen'); ?></span><small><?php esc_html_e('Optional pairing previews', 'authentype-font-specimen'); ?></small></div>
                <div class="ath-pair-grid">
                    <?php foreach ($pair_cards as $pair_card) : ?>
                        <?php
                        $title_font = !empty($pair_card['title_font']) && isset($pairing_fonts_by_key[$pair_card['title_font']]) ? $pairing_fonts_by_key[$pair_card['title_font']] : null;
                        $body_font = !empty($pair_card['body_font']) && isset($pairing_fonts_by_key[$pair_card['body_font']]) ? $pairing_fonts_by_key[$pair_card['body_font']] : null;
                        if (!$title_font && !$body_font) continue;
                        if (!$title_font) $title_font = $body_font;
                        if (!$body_font) $body_font = $title_font;
                        $heading_text = $pair_card['heading_text'] ?: __('A modern voice for expressive brands', 'authentype-font-specimen');
                        $body_text = $pair_card['body_text'] ?: __('Pair a strong display style with a quieter supporting style to test hierarchy, rhythm, and contrast.', 'authentype-font-specimen');
                        $pair_product_url = $pair_card['product_url'] ?: $body_font['product_url'];
                        ?>
                        <article class="ath-pair-card">
                            <div class="ath-pair-meta"><?php echo esc_html($title_font['name']); ?> <span>/</span> <?php echo esc_html($body_font['name']); ?></div>
                            <canvas class="ath-server-canvas ath-pair-heading" data-font-token="<?php echo esc_attr($title_font['token']); ?>" data-text="<?php echo esc_attr($heading_text); ?>" data-font-size="38" data-line-height="1.04" aria-label="<?php esc_attr_e('Font pairing heading preview', 'authentype-font-specimen'); ?>"></canvas>
                            <canvas class="ath-server-canvas ath-pair-body" data-font-token="<?php echo esc_attr($body_font['token']); ?>" data-text="<?php echo esc_attr($body_text); ?>" data-font-size="18" data-line-height="1.36" aria-label="<?php esc_attr_e('Font pairing body preview', 'authentype-font-specimen'); ?>"></canvas>
                            <?php if (!empty($pair_product_url)) : ?><a class="ath-pair-product-link" href="<?php echo esc_url($pair_product_url); ?>"><?php esc_html_e('View paired font', 'authentype-font-specimen'); ?></a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($free_downloads_below_pairs && function_exists('ath_free_downloads_shortcode')) : ?>
            <?php $free_downloads_html = ath_free_downloads_shortcode(array('type' => $free_downloads_type, 'limit' => $free_downloads_limit, 'font_id' => $post_id)); ?>
            <?php if ($free_downloads_html) : ?>
                <section class="ath-v7-free-downloads">
                    <div class="ath-subsection-heading"><span><?php esc_html_e('Free Downloads', 'authentype-font-specimen'); ?></span></div>
                    <?php echo $free_downloads_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode is responsible for escaping. ?>
                </section>
            <?php endif; ?>
        <?php endif; ?>

        <div class="ath-license-modal" data-license-mode="<?php echo esc_attr($license_picker_mode); ?>" data-license-count="<?php echo esc_attr($license_count); ?>" hidden>
            <div class="ath-license-backdrop" data-close-modal></div>
            <div class="ath-license-dialog" role="dialog" aria-modal="true" aria-labelledby="ath-license-title-<?php echo esc_attr($post_id); ?>">
                <header class="ath-license-modal-head">
                    <div class="ath-license-modal-title-wrap"><h3 id="ath-license-title-<?php echo esc_attr($post_id); ?>"><?php esc_html_e('Choose Your Licenses', 'authentype-font-specimen'); ?></h3><p><span><?php esc_html_e('Selected package:', 'authentype-font-specimen'); ?> <strong data-selected-package>—</strong></span><span class="ath-license-modal-instruction"><?php esc_html_e('Select the license coverage you need, then add it to your cart.', 'authentype-font-specimen'); ?></span></p><div class="ath-selected-styles-total" data-selected-styles-total-box><span class="ath-selected-styles-total-meta" data-selected-styles-total-meta>—</span><span class="ath-selected-styles-total-label"><?php esc_html_e('Selected total', 'authentype-font-specimen'); ?></span><strong data-selected-styles-total>—</strong><del data-selected-styles-regular hidden></del><span class="ath-selected-styles-discount" data-selected-styles-discount hidden></span></div></div>
                    <button type="button" class="ath-modal-close" data-close-modal aria-label="<?php esc_attr_e('Close', 'authentype-font-specimen'); ?>">×</button>
                </header>
                <?php if ($license_count > 6) : ?>
                    <div class="ath-license-picker-tools">
                        <?php if ($license_count > 10) : ?>
                            <label class="ath-license-search-wrap"><span class="screen-reader-text"><?php esc_html_e('Search licenses', 'authentype-font-specimen'); ?></span><input type="search" class="ath-license-search" placeholder="<?php esc_attr_e('Search licenses…', 'authentype-font-specimen'); ?>" autocomplete="off"></label>
                            <div class="ath-license-group-filters" role="group" aria-label="<?php esc_attr_e('Filter license groups', 'authentype-font-specimen'); ?>">
                                <button type="button" class="is-active" data-license-group-filter="all"><?php esc_html_e('All', 'authentype-font-specimen'); ?></button>
                                <?php if (in_array('common', $license_groups_present, true)) : ?><button type="button" data-license-group-filter="common"><?php esc_html_e('Common', 'authentype-font-specimen'); ?></button><?php endif; ?>
                                <?php if (in_array('extended', $license_groups_present, true)) : ?><button type="button" data-license-group-filter="extended"><?php esc_html_e('Extended', 'authentype-font-specimen'); ?></button><?php endif; ?>
                                <?php if (in_array('business', $license_groups_present, true)) : ?><button type="button" data-license-group-filter="business"><?php esc_html_e('Business', 'authentype-font-specimen'); ?></button><?php endif; ?>
                                <?php if (in_array('custom', $license_groups_present, true)) : ?><button type="button" data-license-group-filter="custom"><?php esc_html_e('Custom', 'authentype-font-specimen'); ?></button><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="ath-license-picker-heading"><strong data-license-picker-heading><?php esc_html_e('Most common licenses', 'authentype-font-specimen'); ?></strong><span data-license-visible-count></span></div>
                    </div>
                <?php endif; ?>
                <div class="ath-license-list-head" aria-hidden="true"><span><?php esc_html_e('No.', 'authentype-font-specimen'); ?></span><span></span><span></span><span><?php esc_html_e('License type', 'authentype-font-specimen'); ?></span><span><?php esc_html_e('Term', 'authentype-font-specimen'); ?></span><span><?php esc_html_e('Price', 'authentype-font-specimen'); ?></span></div>
                <div class="ath-license-options" data-license-list>
                    <?php foreach ($licenses as $license) : ?>
                        <?php $license_search_source = trim($license['label'] . ' ' . $license['description'] . ' ' . $license['group']); $license_search = function_exists('mb_strtolower') ? mb_strtolower($license_search_source, 'UTF-8') : strtolower($license_search_source); ?>
                        <?php $license_display_label = preg_match('/license$/i', trim($license['label'])) ? $license['label'] : $license['label'] . ' ' . __('License', 'authentype-font-specimen'); ?>
                        <?php $checkout_type = !empty($license['checkout_type']) ? $license['checkout_type'] : 'pay_once'; $is_contact = 'contact' === $checkout_type; ?>
                        <label class="ath-license-option<?php echo $is_contact ? ' is-contact-sales' : ''; ?>" data-license-value="<?php echo esc_attr($license['value']); ?>" data-license-label="<?php echo esc_attr($license_display_label); ?>" data-license-group="<?php echo esc_attr($license['group']); ?>" data-license-featured="<?php echo !empty($license['featured']) ? '1' : '0'; ?>" data-license-checkout-type="<?php echo esc_attr($checkout_type); ?>" data-license-search="<?php echo esc_attr($license_search); ?>">
                            <input type="checkbox" name="ath_licenses_<?php echo esc_attr($post_id); ?>[]" value="<?php echo esc_attr($license['value']); ?>" <?php echo $is_contact ? 'disabled' : ''; ?>>
                            <span class="ath-check-ui" aria-hidden="true"></span>
                            <span class="ath-license-icon"><?php echo ath_specimen_license_icon_svg($license); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed internal SVG. ?></span>
                            <span class="ath-license-option-main">
                                <span class="ath-license-name-line"><strong><?php echo esc_html($license_display_label); ?></strong><?php if (!empty($license['featured'])) : ?><span class="ath-license-recommended"><?php esc_html_e('Recommended', 'authentype-font-specimen'); ?></span><?php endif; ?></span>
                                <?php if (!empty($license['description'])) : ?><em><?php echo esc_html($license['description']); ?></em><?php endif; ?>
                                <span class="ath-license-scope" data-license-scope></span>
                                <a href="<?php echo esc_url(ath_specimen_license_detail_url($post_id, $license)); ?>" target="_blank" rel="noopener"><?php esc_html_e('Read license details', 'authentype-font-specimen'); ?></a>
                            </span>
                            <span class="ath-license-term"><?php echo esc_html('annual' === $checkout_type ? __('Annual', 'authentype-font-specimen') : ('contact' === $checkout_type ? __('Custom quote', 'authentype-font-specimen') : __('Pay once', 'authentype-font-specimen'))); ?></span>
                            <span class="ath-license-pricing-line">
                                <?php if ($is_contact) : ?>
                                    <a class="ath-contact-sales-link" href="<?php echo esc_url(ath_specimen_license_detail_url($post_id, $license)); ?>"><?php esc_html_e('Contact sales', 'authentype-font-specimen'); ?> →</a>
                                <?php else : ?>
                                    <span class="ath-license-price" data-license-price>—</span><del data-license-regular hidden></del><span class="ath-license-discount" data-license-discount hidden></span>
                                <?php endif; ?>
                            </span>
                        </label>
                    <?php endforeach; ?>
                    <p class="ath-license-no-results" data-license-no-results hidden><?php esc_html_e('No licenses match your search or filter.', 'authentype-font-specimen'); ?></p>
                </div>
                <?php if ($license_count > 6) : ?>
                    <div class="ath-more-licenses-wrap"><button type="button" class="ath-more-licenses-toggle" data-more-licenses aria-expanded="false"><span data-more-label><?php esc_html_e('Show more licenses', 'authentype-font-specimen'); ?></span> <span aria-hidden="true">↓</span></button></div>
                <?php endif; ?>
                <div class="ath-license-modal-foot">
                    <?php if ($has_bundle) : ?>
                        <div class="ath-family-saving-recommendation" data-family-saving-recommendation hidden>
                            <div>
                                <span class="ath-family-saving-kicker"><?php esc_html_e('Full Family', 'authentype-font-specimen'); ?></span>
                                <strong data-family-saving-title><?php esc_html_e('You may save more with the full family.', 'authentype-font-specimen'); ?></strong>
                                <span data-family-saving-detail></span>
                            </div>
                            <button type="button" data-switch-to-family><?php esc_html_e('Choose Full Family instead →', 'authentype-font-specimen'); ?></button>
                        </div>
                    <?php endif; ?>
                    <div class="ath-license-foot-feedback">
                        <?php if ($contact_license) : ?><div class="ath-sales-cta"><span><?php esc_html_e('Need a higher-volume or different license?', 'authentype-font-specimen'); ?></span> <a href="<?php echo esc_url(ath_specimen_license_detail_url($post_id, $contact_license)); ?>"><?php esc_html_e('Contact sales', 'authentype-font-specimen'); ?> →</a></div><?php endif; ?>
                        <p class="ath-cart-message" hidden></p>
                        <div class="ath-cart-actions" hidden><a class="ath-view-cart" href="<?php echo esc_url(function_exists('wc_get_cart_url') ? wc_get_cart_url() : '#'); ?>"><?php esc_html_e('View cart', 'authentype-font-specimen'); ?></a></div>
                    </div>
                    <div class="ath-license-checkout-row">
                        <div class="ath-license-checkout-summary" aria-live="polite">
                            <span class="ath-summary-caption"><?php esc_html_e('Selected licenses', 'authentype-font-specimen'); ?></span>
                            <strong data-summary-license>—</strong>
                            <span class="ath-summary-pricing"><span class="ath-summary-subtotal-label"><?php esc_html_e('Subtotal', 'authentype-font-specimen'); ?></span><span data-summary-price>—</span><del data-summary-regular hidden></del><span data-summary-discount hidden></span></span>
                        </div>
                        <button type="button" class="ath-add-to-cart"><?php esc_html_e('Add to cart', 'authentype-font-specimen'); ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('authentype_font_specimen', 'ath_specimen_shortcode');
?>
