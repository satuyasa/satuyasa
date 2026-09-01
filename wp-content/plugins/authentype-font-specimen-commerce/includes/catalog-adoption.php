<?php
defined('ABSPATH') || exit;

/**
 * Existing Woo Catalog Adoption Center.
 *
 * Important: adoption is intentionally one-way/read-only against WooCommerce.
 * It creates a draft Athtyp record and imports the current Woo structure, prices,
 * and downloads. It does NOT mutate the Woo product. The normal Athtyp workflow
 * remains Build Secure Assets -> Save Pricing -> Sync Existing Woo Product.
 */

function ath_specimen_adoption_capable() {
    return authentype_specimen_can_manage_internal() && current_user_can('edit_products');
}


function ath_specimen_adoption_partial_font_id($product_id) {
    $product_id = absint($product_id);
    if (!$product_id) return 0;
    $ids = get_posts(array(
        'post_type' => 'ath_font',
        'post_status' => array('draft', 'private', 'pending', 'future', 'publish'),
        'posts_per_page' => 10,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => array(
            array('key' => '_ath_adoption_source_product', 'value' => $product_id, 'compare' => '=', 'type' => 'NUMERIC'),
        ),
    ));
    foreach ($ids as $font_id) {
        $font_id = (int) $font_id;
        $state = (string) get_post_meta($font_id, '_ath_adoption_state', true);
        if (in_array($state, array('importing', 'failed'), true)) return $font_id;
        if ('' === $state && 'existing_woo_catalog' === (string) get_post_meta($font_id, '_ath_adoption_source', true)) {
            $styles = get_post_meta($font_id, '_ath_font_styles', true);
            $licenses = get_post_meta($font_id, '_ath_license_options', true);
            $snapshot = get_post_meta($font_id, '_ath_adoption_snapshot', true);
            if (empty($styles) || empty($licenses) || empty($snapshot)) return $font_id;
        }
    }
    return 0;
}


function ath_specimen_adoption_download_record($download) {
    if (!is_object($download)) return null;
    $id = method_exists($download, 'get_id') ? sanitize_text_field((string) $download->get_id()) : '';
    $name = method_exists($download, 'get_name') ? sanitize_text_field((string) $download->get_name()) : '';
    $file = method_exists($download, 'get_file') ? (string) $download->get_file() : '';
    return array('id' => $id, 'name' => $name, 'file' => $file);
}

function ath_specimen_adoption_date_value($value) {
    if (!$value) return '';
    if (is_object($value) && method_exists($value, 'getTimestamp')) return (string) $value->getTimestamp();
    if (is_numeric($value)) return (string) (int) $value;
    $ts = strtotime((string) $value);
    return $ts ? (string) $ts : '';
}

/**
 * Load one immutable Woo dataset per adoption request. All analysis, import and
 * snapshot steps reuse it so large families are not hydrated several times.
 */
function ath_specimen_adoption_load_dataset($product_id) {
    $product_id = absint($product_id);
    $product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
    if (!$product) return new WP_Error('ath_adopt_product', __('WooCommerce product not found.', 'authentype-font-specimen'));

    $attributes = array();
    foreach ((array) $product->get_attributes() as $key => $attribute) {
        if (!is_object($attribute) || !method_exists($attribute, 'get_variation') || !$attribute->get_variation()) continue;
        $key = sanitize_title((string) $key);
        if (!$key && method_exists($attribute, 'get_name')) $key = sanitize_title((string) $attribute->get_name());
        if (!$key) continue;
        $attributes[$key] = array(
            'key' => $key,
            'label' => ath_specimen_adoption_attribute_label($key, $product),
            'global' => 0 === strpos($key, 'pa_'),
            'values' => array(),
        );
    }

    $variations = array();
    $variation_keys = array_keys($attributes);
    foreach ((array) $product->get_children() as $variation_id) {
        $variation = wc_get_product((int) $variation_id);
        if (!$variation) continue;
        $attrs = array();
        foreach ((array) $variation->get_attributes() as $key => $value) {
            $key = sanitize_title((string) $key);
            if (!$key) continue;
            $slug = ath_specimen_slug($value);
            $attrs[$key] = $slug;
            if (!isset($attributes[$key])) {
                $attributes[$key] = array(
                    'key' => $key,
                    'label' => ath_specimen_adoption_attribute_label($key, $product),
                    'global' => 0 === strpos($key, 'pa_'),
                    'values' => array(),
                );
            }
            if ($slug) $attributes[$key]['values'][$slug] = true;
            if (!in_array($key, $variation_keys, true)) $variation_keys[] = $key;
        }
        $download_rows = array();
        foreach ((array) $variation->get_downloads('edit') as $download) {
            $record = ath_specimen_adoption_download_record($download);
            if ($record) $download_rows[] = $record;
        }
        $variations[] = array(
            'id' => (int) $variation_id,
            'product' => $variation,
            'attributes' => $attrs,
            'status' => (string) $variation->get_status('edit'),
            'regular_price' => (string) $variation->get_regular_price('edit'),
            'sale_price' => (string) $variation->get_sale_price('edit'),
            'price' => (string) $variation->get_price('edit'),
            'sale_from' => ath_specimen_adoption_date_value($variation->get_date_on_sale_from('edit')),
            'sale_to' => ath_specimen_adoption_date_value($variation->get_date_on_sale_to('edit')),
            'virtual' => (bool) $variation->get_virtual('edit'),
            'downloadable' => (bool) $variation->get_downloadable('edit'),
            'downloads' => $download_rows,
        );
    }

    foreach ($attributes as &$row) {
        $row['values'] = array_keys((array) $row['values']);
        $row['value_count'] = count($row['values']);
    }
    unset($row);

    return array(
        'product_id' => $product_id,
        'product' => $product,
        'attributes' => $attributes,
        'variation_attribute_keys' => array_values(array_unique(array_filter($variation_keys))),
        'variations' => $variations,
    );
}


function ath_specimen_adoption_existing_font_id($product_id) {
    $ids = get_posts(array(
        'post_type' => 'ath_font',
        'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
        'posts_per_page' => 5,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => array(
            array('key' => '_ath_linked_product', 'value' => absint($product_id), 'compare' => '=', 'type' => 'NUMERIC'),
        ),
    ));
    foreach ($ids as $font_id) {
        $font_id = (int) $font_id;
        $state = (string) get_post_meta($font_id, '_ath_adoption_state', true);
        if (in_array($state, array('complete', 'restored'), true)) return $font_id;
        if ('' !== $state) continue;
        if ('existing_woo_catalog' !== (string) get_post_meta($font_id, '_ath_adoption_source', true)) return $font_id;
        $styles = get_post_meta($font_id, '_ath_font_styles', true);
        $licenses = get_post_meta($font_id, '_ath_license_options', true);
        $snapshot = get_post_meta($font_id, '_ath_adoption_snapshot', true);
        if (is_array($styles) && !empty($styles) && is_array($licenses) && !empty($licenses) && is_array($snapshot) && !empty($snapshot)) return $font_id;
        // Incomplete secure.8.1 adoption: do not mutate during Scan/Dry Run.
        // ath_specimen_adopt_woo_product() will reuse/repair it after acquiring
        // the per-product adoption mutex.
    }
    return 0;
}




function ath_specimen_adoption_attribute_label($key, $product = null) {
    $key = sanitize_title((string) $key);
    if (!$key) return '';
    if (function_exists('wc_attribute_label')) {
        $label = wc_attribute_label($key, $product);
        if ($label && $label !== $key) return $label;
    }
    return ath_specimen_label_from_slug(preg_replace('/^pa_/', '', $key));
}

function ath_specimen_adoption_variation_attributes($product) {
    if (!$product) return array();
    $dataset = ath_specimen_adoption_load_dataset($product->get_id());
    return is_wp_error($dataset) ? array() : $dataset['attributes'];
}


function ath_specimen_adoption_attr_score($row, $kind) {
    $haystack = strtolower(trim((string) ($row['key'] ?? '') . ' ' . (string) ($row['label'] ?? '')));
    if ('style' === $kind) {
        if (preg_match('/(^|[^a-z])(font[\s_-]*)?(style|weight)([^a-z]|$)/', $haystack)) return 100;
        if (preg_match('/(^|[^a-z])(face|font-weight|font-style)([^a-z]|$)/', $haystack)) return 80;
    } else {
        if (preg_match('/(^|[^a-z])(license|licence)([^a-z]|$)/', $haystack)) return 100;
        if (preg_match('/(^|[^a-z])(usage|use-type|usage-type)([^a-z]|$)/', $haystack)) return 80;
    }
    return 0;
}

function ath_specimen_adoption_detect_mapping($attributes) {
    $mapping = array('style' => '', 'license' => '', 'confidence' => 'none');
    if (empty($attributes) || !is_array($attributes)) return $mapping;

    $style_scores = array();
    $license_scores = array();
    foreach ($attributes as $key => $row) {
        $style_scores[$key] = ath_specimen_adoption_attr_score($row, 'style');
        $license_scores[$key] = ath_specimen_adoption_attr_score($row, 'license');
    }
    arsort($style_scores);
    arsort($license_scores);

    $style_score = $style_scores ? max($style_scores) : 0;
    $license_score = $license_scores ? max($license_scores) : 0;
    $style_candidates = array_keys(array_filter($style_scores, function ($score) use ($style_score) {
        return $style_score > 0 && (int) $score === (int) $style_score;
    }));
    $license_candidates = array_keys(array_filter($license_scores, function ($score) use ($license_score) {
        return $license_score > 0 && (int) $score === (int) $license_score;
    }));

    // Never auto-pick between equal-scoring legacy attributes. A saved profile
    // or one manual Dry Run confirmation is required to disambiguate them.
    if (1 === count($style_candidates) && 1 === count($license_candidates)) {
        $style_key = reset($style_candidates);
        $license_key = reset($license_candidates);
        if ($style_key !== $license_key) {
            $mapping['style'] = $style_key;
            $mapping['license'] = $license_key;
            $mapping['confidence'] = ($style_score >= 100 && $license_score >= 100) ? 'high' : 'medium';
        }
    }
    return $mapping;
}


/**
 * Saved Style + License mapping profiles are site-local hints for legacy stores.
 * They are learned only from successful adoptions or existing linked Athtyp
 * records. Explicit/linked mapping wins; a unique learned profile outranks a
 * fresh label heuristic, while multiple matching profiles are never guessed.
 */
function ath_specimen_adoption_mapping_profiles() {
    $profiles = get_option('authentype_specimen_adoption_mapping_profiles', array());
    if (!is_array($profiles)) return array();

    $clean = array();
    foreach ($profiles as $profile) {
        if (!is_array($profile)) continue;
        $style = sanitize_title((string) ($profile['style'] ?? ''));
        $license = sanitize_title((string) ($profile['license'] ?? ''));
        if (!$style || !$license || $style === $license) continue;
        $key = $style . '|' . $license;
        $clean[$key] = array(
            'style' => $style,
            'license' => $license,
            'count' => max(1, (int) ($profile['count'] ?? 1)),
            'last_used' => max(0, (int) ($profile['last_used'] ?? 0)),
        );
    }
    uasort($clean, function ($a, $b) {
        if ($a['count'] === $b['count']) return $b['last_used'] <=> $a['last_used'];
        return $b['count'] <=> $a['count'];
    });
    return array_values($clean);
}

function ath_specimen_adoption_remember_mapping_profile($style_attr, $license_attr) {
    $style_attr = sanitize_title((string) $style_attr);
    $license_attr = sanitize_title((string) $license_attr);
    if (!$style_attr || !$license_attr || $style_attr === $license_attr) return false;

    // Bulk reuse is intentionally limited to global Woo attributes because the
    // normal Athtyp sync model is built around global variation taxonomies.
    if (0 !== strpos($style_attr, 'pa_') || 0 !== strpos($license_attr, 'pa_')) return false;

    $profiles = ath_specimen_adoption_mapping_profiles();
    $key = $style_attr . '|' . $license_attr;
    $indexed = array();
    foreach ($profiles as $profile) {
        $pkey = $profile['style'] . '|' . $profile['license'];
        $indexed[$pkey] = $profile;
    }
    if (!isset($indexed[$key])) {
        $indexed[$key] = array('style' => $style_attr, 'license' => $license_attr, 'count' => 0, 'last_used' => 0);
    }
    $indexed[$key]['count'] = max(1, (int) $indexed[$key]['count'] + 1);
    $indexed[$key]['last_used'] = time();

    uasort($indexed, function ($a, $b) {
        if ($a['count'] === $b['count']) return $b['last_used'] <=> $a['last_used'];
        return $b['count'] <=> $a['count'];
    });
    $indexed = array_slice($indexed, 0, 50, true);

    if (false === get_option('authentype_specimen_adoption_mapping_profiles', false)) {
        return add_option('authentype_specimen_adoption_mapping_profiles', array_values($indexed), '', false);
    }
    return update_option('authentype_specimen_adoption_mapping_profiles', array_values($indexed), false);
}

function ath_specimen_adoption_seed_mapping_profiles() {
    if (get_option('authentype_specimen_adoption_mapping_profiles_seeded_v1')) return;

    $indexed = array();
    foreach (ath_specimen_adoption_mapping_profiles() as $profile) {
        $key = $profile['style'] . '|' . $profile['license'];
        $indexed[$key] = $profile;
    }

    $ids = get_posts(array(
        'post_type' => 'ath_font',
        'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
        'posts_per_page' => 500,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => array(
            'relation' => 'AND',
            array('key' => '_ath_linked_product', 'compare' => 'EXISTS'),
            array('key' => '_ath_style_attribute', 'compare' => 'EXISTS'),
            array('key' => '_ath_license_attribute', 'compare' => 'EXISTS'),
        ),
    ));
    $now = time();
    foreach ((array) $ids as $font_id) {
        $style = sanitize_title((string) get_post_meta((int) $font_id, '_ath_style_attribute', true));
        $license = sanitize_title((string) get_post_meta((int) $font_id, '_ath_license_attribute', true));
        if (!$style || !$license || $style === $license || 0 !== strpos($style, 'pa_') || 0 !== strpos($license, 'pa_')) continue;
        $key = $style . '|' . $license;
        if (!isset($indexed[$key])) {
            $indexed[$key] = array('style' => $style, 'license' => $license, 'count' => 0, 'last_used' => 0);
        }
        $indexed[$key]['count'] = max(1, (int) $indexed[$key]['count'] + 1);
        $indexed[$key]['last_used'] = max((int) $indexed[$key]['last_used'], $now);
    }
    uasort($indexed, function ($a, $b) {
        if ($a['count'] === $b['count']) return $b['last_used'] <=> $a['last_used'];
        return $b['count'] <=> $a['count'];
    });
    $profiles = array_values(array_slice($indexed, 0, 50, true));
    if (false === get_option('authentype_specimen_adoption_mapping_profiles', false)) {
        add_option('authentype_specimen_adoption_mapping_profiles', $profiles, '', false);
    } else {
        update_option('authentype_specimen_adoption_mapping_profiles', $profiles, false);
    }
    update_option('authentype_specimen_adoption_mapping_profiles_seeded_v1', time(), false);
}

/**
 * Return exactly one saved mapping profile that exists on this product.
 * Multiple matching profiles are deliberately treated as ambiguous.
 */
function ath_specimen_adoption_match_mapping_profile($attributes) {
    if (empty($attributes) || !is_array($attributes)) return array('style' => '', 'license' => '', 'confidence' => 'none');

    $matches = array();
    foreach (ath_specimen_adoption_mapping_profiles() as $profile) {
        $style = $profile['style'];
        $license = $profile['license'];
        if ($style === $license || !isset($attributes[$style]) || !isset($attributes[$license])) continue;
        $matches[$style . '|' . $license] = array(
            'style' => $style,
            'license' => $license,
            'confidence' => 'profile',
        );
    }
    if (1 !== count($matches)) return array('style' => '', 'license' => '', 'confidence' => count($matches) > 1 ? 'ambiguous_profile' : 'none');
    return reset($matches);
}


function ath_specimen_adoption_owner_font_candidate($product_id) {
    $product_id = absint($product_id);
    if (!$product_id) return 0;
    $font_id = absint(get_post_meta($product_id, '_ath_athtyp_owner_post_id', true));
    if (!$font_id || 'ath_font' !== get_post_type($font_id)) return 0;

    // A consistent reverse link is handled by existing_font_id(). Anything else
    // is ownership drift and must be reviewed before a new Athtyp record exists.
    if ((int) get_post_meta($font_id, '_ath_linked_product', true) === $product_id) return 0;
    return $font_id;
}

/**
 * Exact-slug Athtyp candidates are never auto-adopted. This prevents the bulk
 * matcher from creating a duplicate Athtyp draft when a legacy Woo product may
 * already have been recreated manually but not linked yet.
 */
function ath_specimen_adoption_unlinked_font_candidate($product) {
    if (!$product || !method_exists($product, 'get_slug')) return 0;
    $slug = sanitize_title((string) $product->get_slug());
    if (!$slug) return 0;

    $candidate = get_page_by_path($slug, OBJECT, 'ath_font');
    if (!$candidate || 'ath_font' !== $candidate->post_type) return 0;
    $font_id = (int) $candidate->ID;
    if (!$font_id) return 0;
    if ((int) get_post_meta($font_id, '_ath_linked_product', true) > 0) return 0;
    return $font_id;
}

function ath_specimen_adoption_term_label($attribute_key, $value) {
    $slug = ath_specimen_slug($value);
    $attribute_key = sanitize_title((string) $attribute_key);
    if ($slug && 0 === strpos($attribute_key, 'pa_') && taxonomy_exists($attribute_key)) {
        $term = get_term_by('slug', $slug, $attribute_key);
        if ($term && !is_wp_error($term) && !empty($term->name)) return $term->name;
    }
    return ath_specimen_label_from_slug($slug ?: $value);
}

function ath_specimen_adoption_analyze_product($product_id, $style_attr = '', $license_attr = '', $dataset = null) {
    $product_id = absint($product_id);
    if (!$dataset) $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) return $dataset;
    $product = $dataset['product'];

    $existing_font_id = ath_specimen_adoption_existing_font_id($product_id);
    $partial_font_id = ath_specimen_adoption_partial_font_id($product_id);
    $is_adopted = $existing_font_id > 0;
    $analysis = array(
        'product_id' => $product_id,
        'product_name' => $product->get_name(),
        'product_type' => $product->get_type(),
        'existing_font_id' => $existing_font_id,
        'partial_font_id' => $partial_font_id,
        'attributes' => $dataset['attributes'],
        'style_attr' => '',
        'license_attr' => '',
        'mapping_confidence' => 'none',
        'styles' => array(),
        'licenses' => array(),
        'variation_count' => count($dataset['variations']),
        'complete_pair_count' => 0,
        'expected_pair_count' => 0,
        'price_count' => 0,
        'sale_count' => 0,
        'scheduled_sale_count' => 0,
        'download_count' => 0,
        'download_count_total' => 0,
        'delivery_variation_count' => 0,
        'missing_delivery_variations' => array(),
        'missing_price_variations' => array(),
        'broken_download_count' => 0,
        'duplicate_pairs' => array(),
        'wildcard_variations' => array(),
        'nonpublished_variations' => array(),
        'extra_dimensions' => array(),
        'sparse_matrix' => false,
        'candidate_font_id' => 0,
        'bulk_ready' => false,
        'bulk_reason' => '',
        'status' => 'incompatible',
        'status_label' => __('Incompatible', 'authentype-font-specimen'),
        'message' => '',
    );

    // secure.8.2.21: an already-adopted product remains a live, read-only Woo
    // catalog audit target. Do not return before mapping/variation/download
    // analysis: Catalog Adoption must show the current Woo mirror rather than
    // zero-filled placeholder data. The adoption status is restored at the end.
    if (!$product->is_type('variable')) {
        if ($is_adopted) {
            $analysis['status'] = 'adopted';
            $analysis['status_label'] = __('Already Adopted', 'authentype-font-specimen');
            $analysis['message'] = __('This Woo product is already linked to an Athtyp font. Live WooCommerce catalog data is shown read-only.', 'authentype-font-specimen');
            return $analysis;
        }
        $analysis['status'] = 'simple';
        $analysis['status_label'] = __('Needs Conversion', 'authentype-font-specimen');
        $analysis['message'] = __('Automatic adoption requires a variable WooCommerce product. Simple products are left untouched.', 'authentype-font-specimen');
        return $analysis;
    }

    $attributes = $dataset['attributes'];
    $manual_mapping = '' !== trim((string) $style_attr) || '' !== trim((string) $license_attr);
    $linked_mapping = false;
    $linked_style_attr = '';
    $linked_license_attr = '';

    // Prefer the mapping that was captured when this Woo product was adopted.
    // This keeps the live audit stable even when heuristic labels later change.
    if ($is_adopted && !$manual_mapping) {
        $linked_style_attr = sanitize_title((string) get_post_meta($existing_font_id, '_ath_style_attribute', true));
        $linked_license_attr = sanitize_title((string) get_post_meta($existing_font_id, '_ath_license_attribute', true));
        if ($linked_style_attr && $linked_license_attr && $linked_style_attr !== $linked_license_attr && isset($attributes[$linked_style_attr]) && isset($attributes[$linked_license_attr])) {
            $linked_mapping = true;
        }
    }

    $detected = ath_specimen_adoption_detect_mapping($attributes);
    if (!$manual_mapping && !$linked_mapping) {
        $profile_match = ath_specimen_adoption_match_mapping_profile($attributes);
        if (in_array(($profile_match['confidence'] ?? ''), array('profile', 'ambiguous_profile'), true)) {
            // A mapping learned from completed adoptions is stronger than a
            // fresh label heuristic. Multiple matching profiles are blocked
            // rather than guessed.
            $detected = $profile_match;
        }
    }
    if (!$manual_mapping && !$linked_mapping && !in_array($detected['confidence'], array('high', 'profile'), true) && !$is_adopted) {
        $analysis['style_attr'] = sanitize_title($detected['style']);
        $analysis['license_attr'] = sanitize_title($detected['license']);
        $analysis['mapping_confidence'] = $detected['confidence'];
        $analysis['status'] = 'needs_mapping';
        $analysis['status_label'] = __('Needs Mapping', 'authentype-font-specimen');
        $analysis['message'] = __('Automatic adoption only accepts high-confidence Style + License detection. Confirm the two attributes in Dry Run.', 'authentype-font-specimen');
        return $analysis;
    }

    if ($linked_mapping) {
        $style_attr = $linked_style_attr;
        $license_attr = $linked_license_attr;
        $analysis['mapping_confidence'] = 'linked';
    } else {
        $style_attr = sanitize_title($manual_mapping ? $style_attr : $detected['style']);
        $license_attr = sanitize_title($manual_mapping ? $license_attr : $detected['license']);
        $analysis['mapping_confidence'] = $manual_mapping ? 'manual' : $detected['confidence'];
    }
    $analysis['style_attr'] = $style_attr;
    $analysis['license_attr'] = $license_attr;

    if (!$style_attr || !$license_attr || $style_attr === $license_attr || !isset($attributes[$style_attr]) || !isset($attributes[$license_attr])) {
        if ($is_adopted) {
            // Mapping is unavailable, but still report variation-level commerce
            // totals from the one dataset already loaded for this request.
            foreach ($dataset['variations'] as $row) {
                if ('' !== $row['regular_price'] || '' !== $row['sale_price'] || '' !== $row['price']) $analysis['price_count']++;
                if ('' !== $row['sale_price']) $analysis['sale_count']++;
                if ('' !== $row['sale_from'] || '' !== $row['sale_to']) $analysis['scheduled_sale_count']++;
                $analysis['download_count_total'] += count((array) $row['downloads']);
                foreach ((array) $row['downloads'] as $download) {
                    if (!empty($download['file']) && ath_specimen_adoption_download_url($download['file'])) $analysis['download_count']++;
                }
            }
            $analysis['status'] = 'adopted';
            $analysis['status_label'] = __('Already Adopted', 'authentype-font-specimen');
            $analysis['message'] = __('This Woo product is already linked to an Athtyp font. Current Style/License mapping could not be resolved, so only variation-level live Woo data is available.', 'authentype-font-specimen');
            return $analysis;
        }
        $analysis['status'] = 'needs_mapping';
        $analysis['status_label'] = __('Needs Mapping', 'authentype-font-specimen');
        $analysis['message'] = __('Choose which existing Woo variation attribute represents Style and which represents License.', 'authentype-font-specimen');
        return $analysis;
    }
    if ((0 !== strpos($style_attr, 'pa_') || 0 !== strpos($license_attr, 'pa_')) && !$is_adopted) {
        $analysis['status'] = 'needs_global_attributes';
        $analysis['status_label'] = __('Needs Global Attributes', 'authentype-font-specimen');
        $analysis['message'] = __('The selected Style/License attributes are custom product attributes. Convert them to global Woo attributes before automatic adoption.', 'authentype-font-specimen');
        return $analysis;
    }
    if (!$is_adopted && (!taxonomy_exists($style_attr) || !taxonomy_exists($license_attr))) {
        $analysis['status'] = 'needs_global_attributes';
        $analysis['status_label'] = __('Missing Global Attribute Taxonomy', 'authentype-font-specimen');
        $analysis['message'] = __('The variation keys look global, but one of the Style/License taxonomies is not registered in WooCommerce. Automatic adoption is blocked until the attribute taxonomy is restored.', 'authentype-font-specimen');
        return $analysis;
    }

    // Structurally, legacy products must have exactly Style + License as their
    // variation dimensions. A third dimension is blocked even when its value is
    // constant, because later Athtyp sync is intentionally two-dimensional.
    // Already-adopted products are audited read-only even if drift is present.
    $dimension_keys = array_values(array_unique((array) $dataset['variation_attribute_keys']));
    foreach ($dimension_keys as $key) {
        if ($key !== $style_attr && $key !== $license_attr) $analysis['extra_dimensions'][$key] = 1;
    }
    if (!empty($analysis['extra_dimensions']) && !$is_adopted) {
        $analysis['status'] = 'incompatible';
        $analysis['status_label'] = __('Extra Variation Dimension', 'authentype-font-specimen');
        $analysis['message'] = __('This product contains a variation dimension besides Style and License, even if that extra dimension currently has one value. Automatic adoption is blocked.', 'authentype-font-specimen');
        return $analysis;
    }

    $pair_seen = array();
    foreach ($dataset['variations'] as $row) {
        $variation_id = (int) $row['id'];
        if ('publish' !== (string) $row['status']) $analysis['nonpublished_variations'][] = $variation_id;
        $attrs = (array) $row['attributes'];
        $style_value = isset($attrs[$style_attr]) ? ath_specimen_slug($attrs[$style_attr]) : '';
        $license_value = isset($attrs[$license_attr]) ? ath_specimen_slug($attrs[$license_attr]) : '';
        if (!$style_value || !$license_value) {
            $analysis['wildcard_variations'][] = $variation_id;
            continue;
        }

        $pair_key = $style_value . '|' . $license_value;
        if (isset($pair_seen[$pair_key])) {
            if (!isset($analysis['duplicate_pairs'][$pair_key])) $analysis['duplicate_pairs'][$pair_key] = array($pair_seen[$pair_key]);
            $analysis['duplicate_pairs'][$pair_key][] = $variation_id;
        } else {
            $pair_seen[$pair_key] = $variation_id;
        }

        $analysis['styles'][$style_value] = ath_specimen_adoption_term_label($style_attr, $style_value);
        $analysis['licenses'][$license_value] = ath_specimen_adoption_term_label($license_attr, $license_value);
        $analysis['complete_pair_count']++;
        $has_price = '' !== $row['regular_price'] || '' !== $row['sale_price'] || '' !== $row['price'];
        if ($has_price) {
            $analysis['price_count']++;
        } else {
            $analysis['missing_price_variations'][] = $variation_id;
        }
        if ('' !== $row['sale_price']) $analysis['sale_count']++;
        if ('' !== $row['sale_from'] || '' !== $row['sale_to']) $analysis['scheduled_sale_count']++;

        $variation_importable = 0;
        $analysis['download_count_total'] += count((array) $row['downloads']);
        foreach ((array) $row['downloads'] as $download) {
            $raw_file = !empty($download['file']) ? (string) $download['file'] : '';
            if ($raw_file && ath_specimen_adoption_download_url($raw_file)) {
                $analysis['download_count']++;
                $variation_importable++;
            } elseif ($raw_file) {
                $analysis['broken_download_count']++;
            }
        }
        if ($variation_importable > 0) {
            $analysis['delivery_variation_count']++;
        } else {
            $analysis['missing_delivery_variations'][] = $variation_id;
        }
    }

    // For products that are already linked, the catalog page is an audit view,
    // not an adoption gate. Keep all diagnostics above, but do not reclassify the
    // product as incompatible because Woo has drifted since adoption.
    if ($is_adopted) {
        $analysis['expected_pair_count'] = count($analysis['styles']) * count($analysis['licenses']);
        $analysis['styles'] = ath_specimen_sort_style_attribute_values($analysis['styles']);
        $analysis['status'] = 'adopted';
        $analysis['status_label'] = __('Already Adopted', 'authentype-font-specimen');
        $analysis['message'] = __('This Woo product is already linked to an Athtyp font. Catalog data above is the current live WooCommerce state and is read-only here.', 'authentype-font-specimen');
        return $analysis;
    }

    if (!empty($analysis['wildcard_variations'])) {
        $analysis['status'] = 'incompatible';
        $analysis['status_label'] = __('Wildcard Variation', 'authentype-font-specimen');
        $analysis['message'] = __('At least one variation uses “Any” or is missing Style/License. Automatic adoption is blocked so unmanaged legacy variations cannot remain purchasable.', 'authentype-font-specimen');
        return $analysis;
    }
    if (!empty($analysis['nonpublished_variations'])) {
        $analysis['status'] = 'incompatible';
        $analysis['status_label'] = __('Non-Published Variation', 'authentype-font-specimen');
        $analysis['message'] = __('At least one legacy variation is private/non-published. Automatic adoption is blocked because Athtyp must not guess whether that Style × License combination should become sellable.', 'authentype-font-specimen');
        return $analysis;
    }
    if (!empty($analysis['duplicate_pairs'])) {
        $analysis['status'] = 'incompatible';
        $analysis['status_label'] = __('Ambiguous Variations', 'authentype-font-specimen');
        $analysis['message'] = __('More than one Woo variation collapses to the same Style × License pair. Adoption is blocked so no pricing or file choice is guessed.', 'authentype-font-specimen');
        return $analysis;
    }
    if (empty($analysis['styles']) || empty($analysis['licenses'])) {
        $analysis['status'] = 'incompatible';
        $analysis['message'] = __('No complete Style × License variations were found.', 'authentype-font-specimen');
        return $analysis;
    }

    $analysis['expected_pair_count'] = count($analysis['styles']) * count($analysis['licenses']);
    if ($analysis['complete_pair_count'] !== $analysis['expected_pair_count']) {
        // secure.8.2.23: sparse legacy catalogs are safe to adopt read-only.
        // Import only the exact existing variation pairs. The availability-aware
        // Woo Sync introduced in secure.8.2.20 later queues only pairs backed by
        // actual delivery, so adoption no longer needs to invent a cartesian matrix.
        $analysis['sparse_matrix'] = true;
    }
    if ($analysis['scheduled_sale_count'] > 0) {
        $analysis['status'] = 'incompatible';
        $analysis['status_label'] = __('Scheduled Sale Detected', 'authentype-font-specimen');
        $analysis['message'] = __('One or more Woo variations use sale start/end dates. The current Athtyp Price Matrix has no sale scheduler, so automatic adoption is blocked to prevent an expiring sale becoming permanent.', 'authentype-font-specimen');
        return $analysis;
    }

    $analysis['styles'] = ath_specimen_sort_style_attribute_values($analysis['styles']);
    $analysis['status'] = 'compatible';

    // Exact-slug pre-existing Athtyp records are review-only. Never create a
    // second record merely because the old one is not linked. Interrupted
    // adoption drafts are excluded because the retry flow intentionally reuses them.
    $analysis['candidate_font_id'] = ath_specimen_adoption_owner_font_candidate($product_id);
    if (!$analysis['candidate_font_id']) {
        $analysis['candidate_font_id'] = ath_specimen_adoption_unlinked_font_candidate($product);
    }
    if ($partial_font_id && (int) $analysis['candidate_font_id'] === (int) $partial_font_id) {
        $analysis['candidate_font_id'] = 0;
    }
    if (!empty($analysis['candidate_font_id'])) {
        $analysis['status'] = 'needs_existing_match';
        $analysis['status_label'] = __('Existing Athtyp Candidate', 'authentype-font-specimen');
        $analysis['message'] = sprintf(__('Athtyp #%d is referenced by Woo ownership metadata or has the same product slug, but is not consistently linked. Automatic adoption is blocked to prevent a duplicate; review that record first.', 'authentype-font-specimen'), (int) $analysis['candidate_font_id']);
        $analysis['bulk_reason'] = $analysis['message'];
        return $analysis;
    }

    $bulk_reasons = array();
    if (!in_array($analysis['mapping_confidence'], array('high', 'profile'), true)) {
        $bulk_reasons[] = __('mapping is not reusable yet', 'authentype-font-specimen');
    }
    if (!empty($analysis['missing_delivery_variations'])) {
        $bulk_reasons[] = sprintf(__('%d variation(s) have no importable download file', 'authentype-font-specimen'), count($analysis['missing_delivery_variations']));
    }
    if ($analysis['broken_download_count'] > 0) {
        $bulk_reasons[] = sprintf(__('%d download path(s) cannot be represented safely', 'authentype-font-specimen'), $analysis['broken_download_count']);
    }
    if (!empty($analysis['missing_price_variations'])) {
        $bulk_reasons[] = sprintf(__('%d variation(s) have no current price', 'authentype-font-specimen'), count($analysis['missing_price_variations']));
    }

    $analysis['bulk_ready'] = empty($bulk_reasons);
    $analysis['bulk_reason'] = implode('; ', $bulk_reasons);

    if ($partial_font_id) {
        $analysis['status_label'] = __('Ready to Retry', 'authentype-font-specimen');
        $analysis['message'] = __('A previous adoption did not complete. The existing draft can be safely retried; WooCommerce has not been modified.', 'authentype-font-specimen');
    } elseif ($analysis['bulk_ready']) {
        $analysis['status_label'] = $analysis['sparse_matrix'] ? __('Bulk Ready — Sparse', 'authentype-font-specimen') : __('Bulk Ready', 'authentype-font-specimen');
        $analysis['message'] = $analysis['sparse_matrix']
            ? sprintf(__('Safe for bulk read-only adoption. The exact %1$d existing Style × License pairs will be preserved; missing combinations are not invented.', 'authentype-font-specimen'), $analysis['complete_pair_count'])
            : __('Safe for bulk read-only adoption. WooCommerce will not be modified during this step.', 'authentype-font-specimen');
    } else {
        $analysis['status_label'] = __('Compatible — Review', 'authentype-font-specimen');
        $analysis['message'] = sprintf(__('Structurally adoptable, but excluded from automatic bulk adoption because %s. Review this product in Dry Run.', 'authentype-font-specimen'), $analysis['bulk_reason']);
    }
    return $analysis;
}

function ath_specimen_adoption_snapshot($product, $style_attr, $license_attr, $dataset = null) {
    if (!$product) return array();
    if (!$dataset) $dataset = ath_specimen_adoption_load_dataset($product->get_id());
    if (is_wp_error($dataset)) return array();

    $parent_attributes = array();
    $taxonomy_terms = array();
    foreach ((array) $product->get_attributes() as $key => $attribute) {
        if (!is_object($attribute)) continue;
        $name = method_exists($attribute, 'get_name') ? (string) $attribute->get_name() : (string) $key;
        $parent_attributes[] = array(
            'id' => method_exists($attribute, 'get_id') ? (int) $attribute->get_id() : 0,
            'name' => $name,
            'options' => method_exists($attribute, 'get_options') ? array_values((array) $attribute->get_options()) : array(),
            'position' => method_exists($attribute, 'get_position') ? (int) $attribute->get_position() : 0,
            'visible' => method_exists($attribute, 'get_visible') ? (bool) $attribute->get_visible() : false,
            'variation' => method_exists($attribute, 'get_variation') ? (bool) $attribute->get_variation() : false,
        );
        $tax = sanitize_title($name);
        if (0 === strpos($tax, 'pa_') && taxonomy_exists($tax)) {
            $terms = wp_get_object_terms($product->get_id(), $tax, array('fields' => 'slugs'));
            if (!is_wp_error($terms)) $taxonomy_terms[$tax] = array_values($terms);
        }
    }

    $parent_auth_exists = metadata_exists('post', $product->get_id(), '_ath_pricing_authority');
    $snapshot = array(
        'version' => 2,
        'captured_at' => time(),
        'product_id' => $product->get_id(),
        'product_type' => $product->get_type(),
        'product_status' => (string) $product->get_status('edit'),
        'catalog_visibility' => method_exists($product, 'get_catalog_visibility') ? (string) $product->get_catalog_visibility('edit') : '',
        'style_attribute' => sanitize_title($style_attr),
        'license_attribute' => sanitize_title($license_attr),
        'parent_attributes' => $parent_attributes,
        'default_attributes' => method_exists($product, 'get_default_attributes') ? (array) $product->get_default_attributes('edit') : array(),
        'taxonomy_terms' => $taxonomy_terms,
        'parent_pricing_authority' => array('exists' => $parent_auth_exists ? 1 : 0, 'value' => $parent_auth_exists ? (string) get_post_meta($product->get_id(), '_ath_pricing_authority', true) : ''),
        'variations' => array(),
    );

    foreach ($dataset['variations'] as $row) {
        $variation = $row['product'];
        $meta = array();
        foreach (array('_ath_pricing_authority', '_ath_delivery_missing', '_ath_disabled_by_sync') as $key) {
            $exists = metadata_exists('post', (int) $row['id'], $key);
            $meta[$key] = array('exists' => $exists ? 1 : 0, 'value' => $exists ? get_post_meta((int) $row['id'], $key, true) : '');
        }
        $snapshot['variations'][] = array(
            'id' => (int) $row['id'],
            'status' => $row['status'],
            'attributes' => (array) $row['attributes'],
            'regular_price' => $row['regular_price'],
            'sale_price' => $row['sale_price'],
            'price' => $row['price'],
            'sale_from' => $row['sale_from'],
            'sale_to' => $row['sale_to'],
            'virtual' => !empty($row['virtual']) ? 1 : 0,
            'downloadable' => !empty($row['downloadable']) ? 1 : 0,
            'downloads' => array_values((array) $row['downloads']),
            'managed_meta' => $meta,
        );
    }
    return $snapshot;
}


function ath_specimen_adoption_download_url($file) {
    $file = trim((string) $file);
    if (!$file) return '';
    if (filter_var($file, FILTER_VALIDATE_URL)) return esc_url_raw($file);

    // Woo can hold an absolute local path. Preserve it only when it resolves
    // inside WordPress uploads, converting it to the equivalent uploads URL so
    // the existing Athtyp delivery schema can represent it safely.
    $uploads = wp_get_upload_dir();
    $basedir = !empty($uploads['basedir']) ? wp_normalize_path($uploads['basedir']) : '';
    $baseurl = !empty($uploads['baseurl']) ? rtrim($uploads['baseurl'], '/') : '';
    $normalized = wp_normalize_path($file);
    if ($basedir && $baseurl && 0 === strpos($normalized, trailingslashit($basedir))) {
        $relative = ltrim(substr($normalized, strlen($basedir)), '/');
        return esc_url_raw($baseurl . '/' . str_replace('%2F', '/', rawurlencode($relative)));
    }
    return '';
}

function ath_specimen_adoption_import_rows($product, $analysis, $dataset = null) {
    if (!$dataset) $dataset = ath_specimen_adoption_load_dataset($product->get_id());
    if (is_wp_error($dataset)) return $dataset;
    $style_attr = $analysis['style_attr'];
    $license_attr = $analysis['license_attr'];
    $styles = array();
    $licenses = array();
    $matrix = array();
    $downloads = array();
    $style_index = 0;

    foreach ($analysis['styles'] as $slug => $label) {
        $styles[] = array(
            'style_name' => $label,
            'font_file' => '',
            'font_weight' => function_exists('ath_specimen_package_style_weight') ? ath_specimen_package_style_weight($label) : 400,
            'font_style' => false !== stripos($label, 'italic') ? 'italic' : 'normal',
            'style_variation_value' => $slug,
            'default_selected' => 0 === $style_index ? 1 : 0,
            'is_package' => preg_match('/(full|family|all[\s_-]*styles?)/i', $label) ? 1 : 0,
        );
        $style_index++;
    }
    foreach ($analysis['licenses'] as $slug => $label) {
        $licenses[] = array(
            'license_label' => $label,
            'license_variation_value' => $slug,
            'license_description' => '',
            'license_group' => '',
            'license_featured' => 0,
            'license_checkout_type' => '',
            'license_icon' => '',
        );
    }

    foreach ($dataset['variations'] as $row) {
        $attrs = (array) $row['attributes'];
        $style = isset($attrs[$style_attr]) ? ath_specimen_slug($attrs[$style_attr]) : '';
        $license = isset($attrs[$license_attr]) ? ath_specimen_slug($attrs[$license_attr]) : '';
        if (!$style || !$license) continue;

        $regular = (string) $row['regular_price'];
        $sale = (string) $row['sale_price'];
        if ('' === $regular && '' !== (string) $row['price']) $regular = (string) $row['price'];
        if ('' !== $regular || '' !== $sale) {
            if (!isset($matrix[$style])) $matrix[$style] = array();
            $matrix[$style][$license] = array('regular' => $regular, 'sale' => $sale);
        }

        foreach ((array) $row['downloads'] as $download) {
            $file = !empty($download['file']) ? ath_specimen_adoption_download_url($download['file']) : '';
            if (!$file) continue;
            $downloads[] = array(
                'download_id' => !empty($download['id']) ? sanitize_text_field((string) $download['id']) : '',
                'download_name' => !empty($download['name']) ? sanitize_text_field((string) $download['name']) : ath_specimen_download_name_from_file($file),
                'download_file' => $file,
                'style_variation_value' => $style,
                'license_variation_value' => $license,
                'legacy_download' => 1,
            );
        }
    }

    return array(
        'styles' => ath_specimen_sanitize_styles($styles),
        'licenses' => ath_specimen_sanitize_licenses($licenses),
        'prices' => ath_specimen_sanitize_price_matrix($matrix),
        'downloads' => ath_specimen_sanitize_product_downloads($downloads),
    );
}




function ath_specimen_adoption_backfill_download_ids($font_id, $dataset, $style_attr, $license_attr) {
    $font_id = absint($font_id);
    if (!$font_id || is_wp_error($dataset) || empty($dataset['variations'])) return 0;
    $rows = get_post_meta($font_id, '_ath_product_downloads', true);
    if (empty($rows) || !is_array($rows)) return 0;
    $exact = array();
    $pair = array();
    foreach ($dataset['variations'] as $variation) {
        $attrs = (array) ($variation['attributes'] ?? array());
        $style = isset($attrs[$style_attr]) ? ath_specimen_slug($attrs[$style_attr]) : '';
        $license = isset($attrs[$license_attr]) ? ath_specimen_slug($attrs[$license_attr]) : '';
        if (!$style || !$license) continue;
        $pair_key = $style . '|' . $license;
        foreach ((array) ($variation['downloads'] ?? array()) as $download) {
            if (empty($download['id']) || empty($download['file'])) continue;
            $file = ath_specimen_adoption_download_url($download['file']);
            if (!$file) continue;
            $id = sanitize_text_field((string) $download['id']);
            $key = $pair_key . '|' . $file;
            if (!isset($exact[$key])) $exact[$key] = array();
            $exact[$key][] = $id;
            if (!isset($pair[$pair_key])) $pair[$pair_key] = array();
            $pair[$pair_key][] = $id;
        }
    }
    $changed = 0;
    $used = array();
    foreach ($rows as &$row) {
        if (!empty($row['download_id'])) continue;
        $style = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : '';
        $license = !empty($row['license_variation_value']) ? ath_specimen_slug($row['license_variation_value']) : '';
        $file = !empty($row['download_file']) ? esc_url_raw($row['download_file']) : '';
        $pair_key = $style . '|' . $license;
        $candidate = '';
        $exact_key = $pair_key . '|' . $file;
        foreach ((array) ($exact[$exact_key] ?? array()) as $id) {
            if (empty($used[$id])) { $candidate = $id; break; }
        }
        if (!$candidate) {
            foreach ((array) ($pair[$pair_key] ?? array()) as $id) {
                if (empty($used[$id])) { $candidate = $id; break; }
            }
        }
        if ($candidate) {
            $row['download_id'] = $candidate;
            $row['legacy_download'] = 1;
            $used[$candidate] = true;
            $changed++;
        }
    }
    unset($row);
    if ($changed) update_post_meta($font_id, '_ath_product_downloads', ath_specimen_sanitize_product_downloads($rows));
    return $changed;
}

function ath_specimen_adoption_maybe_upgrade_snapshot($font_id) {
    $font_id = absint($font_id);
    if (!$font_id || 'existing_woo_catalog' !== (string) get_post_meta($font_id, '_ath_adoption_source', true)) return false;
    // Only capture/backfill from Woo before Athtyp has ever completed a sync.
    if (get_post_meta($font_id, '_ath_woo_synced_at', true)) return false;
    $product_id = (int) get_post_meta($font_id, '_ath_linked_product', true);
    $style_attr = (string) get_post_meta($font_id, '_ath_style_attribute', true);
    $license_attr = (string) get_post_meta($font_id, '_ath_license_attribute', true);
    $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) return false;

    // secure.8.1 imported file/name but not Woo download identifiers. Backfill
    // them from untouched Woo variations before the first Athtyp sync.
    ath_specimen_adoption_backfill_download_ids($font_id, $dataset, $style_attr, $license_attr);

    $snapshot = get_post_meta($font_id, '_ath_adoption_snapshot', true);
    if (!is_array($snapshot) || empty($snapshot['version']) || (int) $snapshot['version'] < 2) {
        update_post_meta($font_id, '_ath_adoption_snapshot', ath_specimen_adoption_snapshot($dataset['product'], $style_attr, $license_attr, $dataset));
    }
    update_post_meta($font_id, '_ath_adoption_state', 'complete');
    return true;
}


function ath_specimen_adoption_restore_downloads($rows) {
    $downloads = array();
    if (!class_exists('WC_Product_Download')) return $downloads;
    foreach ((array) $rows as $row) {
        if (empty($row['file'])) continue;
        $download = new WC_Product_Download();
        if (!empty($row['id'])) $download->set_id((string) $row['id']);
        $download->set_name(!empty($row['name']) ? (string) $row['name'] : ath_specimen_download_name_from_file($row['file']));
        $download->set_file((string) $row['file']);
        $downloads[] = $download;
    }
    return $downloads;
}

function ath_specimen_adoption_restore_snapshot($font_id) {
    $font_id = absint($font_id);
    if (!$font_id || !ath_specimen_adoption_capable() || !current_user_can('edit_post', $font_id)) {
        return new WP_Error('ath_restore_permission', __('Permission denied.', 'authentype-font-specimen'));
    }
    ath_specimen_adoption_maybe_upgrade_snapshot($font_id);
    $snapshot = get_post_meta($font_id, '_ath_adoption_snapshot', true);
    if (!is_array($snapshot) || (int) ($snapshot['version'] ?? 0) < 2) {
        return new WP_Error('ath_restore_snapshot', __('A complete version-2 pre-adoption snapshot is not available for this font.', 'authentype-font-specimen'));
    }
    $product_id = absint($snapshot['product_id'] ?? 0);
    if (!$product_id || !current_user_can('edit_post', $product_id)) return new WP_Error('ath_restore_product_permission', __('You cannot edit the linked Woo product.', 'authentype-font-specimen'));
    $product = wc_get_product($product_id);
    if (!$product || !$product->is_type('variable')) return new WP_Error('ath_restore_product', __('The snapshot product is no longer a variable WooCommerce product.', 'authentype-font-specimen'));

    $mutex = 'ath_adopt_mutex_' . $product_id;
    $record = array('user' => get_current_user_id(), 'time' => time(), 'purpose' => 'restore');
    if (!add_option($mutex, $record, '', false)) {
        $current = get_option($mutex, array());
        if (is_array($current) && !empty($current['time']) && (int) $current['time'] < time() - 10 * MINUTE_IN_SECONDS) delete_option($mutex);
        if (!add_option($mutex, $record, '', false)) return new WP_Error('ath_restore_busy', __('This Woo product is already being processed by another adoption/restore request.', 'authentype-font-specimen'));
    }
    if (function_exists('ath_specimen_stability_cross_engine_guard')) {
        $operation_guard = ath_specimen_stability_cross_engine_guard($font_id, $product_id, array('adoption'));
        if (is_wp_error($operation_guard)) {
            delete_option($mutex);
            return $operation_guard;
        }
    }

    $woo_lock_key = function_exists('ath_specimen_woo_sync_lock_key') ? ath_specimen_woo_sync_lock_key($font_id, $product_id) : '';
    $woo_lock_token = 'restore-' . wp_generate_password(32, false, false);
    if ($woo_lock_key && function_exists('ath_specimen_woo_sync_acquire_lock') && !ath_specimen_woo_sync_acquire_lock($woo_lock_key, $woo_lock_token, 30 * MINUTE_IN_SECONDS)) {
        delete_option($mutex);
        return new WP_Error('ath_restore_sync_busy', __('A Woo sync session currently owns this product. Finish or let that session expire before restoring the snapshot.', 'authentype-font-specimen'));
    }

    try {
        $attributes = array();
        foreach ((array) ($snapshot['parent_attributes'] ?? array()) as $row) {
            if (!class_exists('WC_Product_Attribute') || empty($row['name'])) continue;
            $attribute = new WC_Product_Attribute();
            $attribute->set_id(absint($row['id'] ?? 0));
            $attribute->set_name((string) $row['name']);
            $attribute->set_options(array_values((array) ($row['options'] ?? array())));
            $attribute->set_position((int) ($row['position'] ?? 0));
            $attribute->set_visible(!empty($row['visible']));
            $attribute->set_variation(!empty($row['variation']));
            $attributes[sanitize_title((string) $row['name'])] = $attribute;
        }
        foreach ((array) ($snapshot['taxonomy_terms'] ?? array()) as $taxonomy => $slugs) {
            $taxonomy = sanitize_title($taxonomy);
            if ($taxonomy && taxonomy_exists($taxonomy)) wp_set_object_terms($product_id, array_map('sanitize_title', (array) $slugs), $taxonomy, false);
        }
        $product->set_attributes($attributes);
        if (method_exists($product, 'set_default_attributes')) $product->set_default_attributes((array) ($snapshot['default_attributes'] ?? array()));
        if (!empty($snapshot['catalog_visibility']) && method_exists($product, 'set_catalog_visibility')) $product->set_catalog_visibility((string) $snapshot['catalog_visibility']);
        if (!empty($snapshot['product_status'])) $product->set_status((string) $snapshot['product_status']);
        $product->save();

        $parent_auth = (array) ($snapshot['parent_pricing_authority'] ?? array());
        if (!empty($parent_auth['exists'])) update_post_meta($product_id, '_ath_pricing_authority', $parent_auth['value'] ?? '');
        else delete_post_meta($product_id, '_ath_pricing_authority');

        $snapshot_ids = array();
        $restored = 0;
        foreach ((array) ($snapshot['variations'] ?? array()) as $row) {
            $variation_id = absint($row['id'] ?? 0);
            if (!$variation_id) continue;
            $variation = wc_get_product($variation_id);
            if (!$variation || (int) $variation->get_parent_id() !== $product_id) continue;
            $snapshot_ids[$variation_id] = true;
            $variation->set_attributes((array) ($row['attributes'] ?? array()));
            $variation->set_status((string) ($row['status'] ?? 'publish'));
            $variation->set_regular_price((string) ($row['regular_price'] ?? ''));
            $variation->set_sale_price((string) ($row['sale_price'] ?? ''));
            $variation->set_date_on_sale_from(!empty($row['sale_from']) ? (int) $row['sale_from'] : null);
            $variation->set_date_on_sale_to(!empty($row['sale_to']) ? (int) $row['sale_to'] : null);
            $variation->set_virtual(!empty($row['virtual']));
            $variation->set_downloadable(!empty($row['downloadable']));
            $variation->set_downloads(ath_specimen_adoption_restore_downloads($row['downloads'] ?? array()));
            foreach ((array) ($row['managed_meta'] ?? array()) as $key => $meta) {
                if (!empty($meta['exists'])) $variation->update_meta_data($key, $meta['value'] ?? '');
                else $variation->delete_meta_data($key);
            }
            $variation->delete_meta_data('_ath_restored_orphan');
            $variation->save();
            $restored++;
        }

        // Never hard-delete post-adoption variations: they may already appear in
        // an order. Disable only Athtyp-managed additions that are absent from the
        // pre-adoption snapshot.
        $disabled_new = 0;
        $fresh = wc_get_product($product_id);
        foreach ((array) $fresh->get_children() as $variation_id) {
            $variation_id = (int) $variation_id;
            if (isset($snapshot_ids[$variation_id])) continue;
            $variation = wc_get_product($variation_id);
            if (!$variation || 'athtyp' !== (string) $variation->get_meta('_ath_pricing_authority', true)) continue;
            $variation->set_regular_price('');
            $variation->set_sale_price('');
            $variation->set_status('private');
            $variation->update_meta_data('_ath_restored_orphan', '1');
            $variation->save();
            $disabled_new++;
        }
        if (class_exists('WC_Product_Variable') && method_exists('WC_Product_Variable', 'sync')) WC_Product_Variable::sync($product_id);
        if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($product_id);
        clean_post_cache($product_id);

        update_post_meta($font_id, '_ath_adoption_state', 'restored');
        update_post_meta($font_id, '_ath_adoption_restored_at', time());
        update_post_meta($font_id, '_ath_adoption_detached_product', $product_id);
        delete_post_meta($font_id, '_ath_woo_synced_signature');
        delete_post_meta($font_id, '_ath_woo_synced_at');

        // Restore means WooCommerce is authoritative again. Detach only after
        // the product + variation snapshot has been restored successfully so a
        // Published Athtyp record cannot continue blocking the restored product
        // through the commerce sync guard.
        delete_post_meta($font_id, '_ath_linked_product');
        if ((int) get_post_meta($product_id, '_ath_athtyp_owner_post_id', true) === $font_id) {
            delete_post_meta($product_id, '_ath_athtyp_owner_post_id');
        }
        clean_post_cache($font_id);
        return array('product_id' => $product_id, 'restored' => $restored, 'disabled_new' => $disabled_new);
    } finally {
        if (!empty($woo_lock_key) && function_exists('ath_specimen_woo_sync_release_lock')) ath_specimen_woo_sync_release_lock($woo_lock_key, $woo_lock_token);
        delete_option($mutex);
    }
}

function ath_specimen_adopt_woo_product($product_id, $style_attr = '', $license_attr = '', $require_bulk_ready = false) {
    $product_id = absint($product_id);
    if (!$product_id || !ath_specimen_adoption_capable() || !current_user_can('edit_post', $product_id)) {
        return new WP_Error('ath_adopt_permission', __('Permission denied.', 'authentype-font-specimen'));
    }
    $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) return $dataset;
    $analysis = ath_specimen_adoption_analyze_product($product_id, $style_attr, $license_attr, $dataset);
    if (is_wp_error($analysis)) return $analysis;
    if ('adopted' === $analysis['status'] && !empty($analysis['existing_font_id'])) {
        return array('font_id' => (int) $analysis['existing_font_id'], 'already_adopted' => true, 'analysis' => $analysis);
    }
    if ('compatible' !== $analysis['status']) {
        return new WP_Error('ath_adopt_incompatible', $analysis['message'] ?: __('This product is not safe for automatic adoption.', 'authentype-font-specimen'));
    }
    if ($require_bulk_ready && empty($analysis['bulk_ready'])) {
        return new WP_Error('ath_adopt_not_bulk_ready', $analysis['bulk_reason'] ?: __('This product changed after scan and is no longer safe for automatic bulk adoption.', 'authentype-font-specimen'));
    }

    $mutex = 'ath_adopt_mutex_' . $product_id;
    $mutex_record = array('user' => get_current_user_id(), 'time' => time(), 'purpose' => 'adopt');
    if (!add_option($mutex, $mutex_record, '', false)) {
        $existing_mutex = get_option($mutex, array());
        if (is_array($existing_mutex) && !empty($existing_mutex['time']) && (int) $existing_mutex['time'] < time() - 10 * MINUTE_IN_SECONDS) delete_option($mutex);
        if (!add_option($mutex, $mutex_record, '', false)) return new WP_Error('ath_adopt_busy', __('This product is already being adopted by another request.', 'authentype-font-specimen'));
    }

    $post_id = 0;
    try {
        $existing = ath_specimen_adoption_existing_font_id($product_id);
        if ($existing) return array('font_id' => $existing, 'already_adopted' => true, 'analysis' => $analysis);
        $product = $dataset['product'];
        $rows = ath_specimen_adoption_import_rows($product, $analysis, $dataset);
        if (is_wp_error($rows)) return $rows;

        $post_id = ath_specimen_adoption_partial_font_id($product_id);
        if (!$post_id) {
            $post_id = wp_insert_post(array(
                'post_type' => 'ath_font',
                'post_status' => 'draft',
                'post_title' => $product->get_name(),
                'post_content' => '',
            ), true);
            if (is_wp_error($post_id)) return $post_id;
        }

        // Logical transaction marker. The linked-product key is deliberately the
        // final commit so a fatal/timeout cannot make a half-import look complete.
        update_post_meta($post_id, '_ath_adoption_source', 'existing_woo_catalog');
        update_post_meta($post_id, '_ath_adoption_source_product', $product_id);
        update_post_meta($post_id, '_ath_adoption_state', 'importing');
        update_post_meta($post_id, '_ath_adoption_started_at', time());
        delete_post_meta($post_id, '_ath_adoption_last_error');
        delete_post_meta($post_id, '_ath_linked_product');

        update_post_meta($post_id, '_ath_adoption_snapshot', ath_specimen_adoption_snapshot($product, $analysis['style_attr'], $analysis['license_attr'], $dataset));
        update_post_meta($post_id, '_ath_style_attribute', $analysis['style_attr']);
        update_post_meta($post_id, '_ath_license_attribute', $analysis['license_attr']);
        update_post_meta($post_id, '_ath_font_styles', $rows['styles']);
        update_post_meta($post_id, '_ath_license_options', $rows['licenses']);
        ath_specimen_save_pricing_matrix($post_id, $rows['prices']);
        update_post_meta($post_id, '_ath_product_downloads', $rows['downloads']);
        update_post_meta($post_id, '_ath_package_builder', array(
            'font_zip' => '',
            'family_name' => $product->get_name(),
            'pricing_mode' => 'per_style',
            'preview_format' => 'auto',
            'secure_token' => '',
            'licenses' => array_map(function ($license) {
                return array(
                    'license_label' => $license['license_label'],
                    'license_variation_value' => $license['license_variation_value'],
                    'template_zip' => '',
                );
            }, $rows['licenses']),
        ));
        update_post_meta($post_id, '_ath_adoption_created_at', time());
        update_post_meta($post_id, '_ath_adoption_legacy_delivery', !empty($rows['downloads']) ? '1' : '0');
        delete_post_meta($post_id, '_ath_woo_synced_signature');
        delete_post_meta($post_id, '_ath_woo_synced_at');

        $thumbnail_id = get_post_thumbnail_id($product_id);
        if ($thumbnail_id) set_post_thumbnail($post_id, $thumbnail_id);

        // Critical verification before commit.
        if (count((array) get_post_meta($post_id, '_ath_font_styles', true)) !== count($rows['styles']) ||
            count((array) get_post_meta($post_id, '_ath_license_options', true)) !== count($rows['licenses'])) {
            throw new RuntimeException(__('Adoption metadata verification failed before commit.', 'authentype-font-specimen'));
        }

        update_post_meta($post_id, '_ath_linked_product', $product_id); // COMMIT LAST.
        if ((int) get_post_meta($post_id, '_ath_linked_product', true) !== $product_id) {
            throw new RuntimeException(__('Could not commit the linked Woo product after adoption.', 'authentype-font-specimen'));
        }
        update_post_meta($post_id, '_ath_adoption_state', 'complete');
        update_post_meta($post_id, '_ath_adoption_completed_at', time());
        update_post_meta($product_id, '_ath_athtyp_owner_post_id', $post_id);

        // A mapping becomes reusable only after a complete, verified adoption.
        // This lets one manually confirmed legacy schema safely unlock matching
        // products without teaching the matcher from a failed or dry-run guess.
        ath_specimen_adoption_remember_mapping_profile($analysis['style_attr'], $analysis['license_attr']);

        return array(
            'font_id' => (int) $post_id,
            'already_adopted' => false,
            'analysis' => $analysis,
            'counts' => array(
                'styles' => count($rows['styles']),
                'licenses' => count($rows['licenses']),
                'prices' => array_sum(array_map('count', $rows['prices'])),
                'downloads' => count($rows['downloads']),
            ),
        );
    } catch (Throwable $e) {
        if ($post_id && !is_wp_error($post_id)) {
            delete_post_meta((int) $post_id, '_ath_linked_product');
            update_post_meta((int) $post_id, '_ath_adoption_state', 'failed');
            update_post_meta((int) $post_id, '_ath_adoption_last_error', sanitize_text_field($e->getMessage()));
            update_post_meta((int) $post_id, '_ath_adoption_failed_at', time());
        }
        return new WP_Error('ath_adopt_failed', sprintf(__('Adoption stopped safely before Woo takeover: %s', 'authentype-font-specimen'), $e->getMessage()));
    } finally {
        delete_option($mutex);
    }
}



/**
 * Read-only storefront readiness audit for a linked Athtyp/Woo product.
 *
 * secure.8.2.24: this function deliberately performs no writes. It reuses one
 * Woo dataset, the linked Athtyp pricing/delivery authority, and the exact
 * runtime mirror validator used by checkout. A stale broad sync receipt alone
 * does not fail a product when every live variation still mirrors Athtyp.
 */
function ath_specimen_adoption_commerce_readiness($product_id, $dataset = null, $analysis = null) {
    $product_id = absint($product_id);
    $result = array(
        'product_id' => $product_id,
        'font_id' => 0,
        'status' => 'review',
        'status_label' => __('Review', 'authentype-font-specimen'),
        'message' => '',
        'flags' => array(),
        'counts' => array(
            'expected_pairs' => 0,
            'matched_pairs' => 0,
            'woo_variations' => 0,
        ),
    );
    if (!$product_id) {
        $result['message'] = __('Woo product ID is missing.', 'authentype-font-specimen');
        return $result;
    }

    if (!$dataset) $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) {
        $result['message'] = $dataset->get_error_message();
        return $result;
    }
    $product = $dataset['product'];
    $result['counts']['woo_variations'] = count((array) $dataset['variations']);

    $font_id = ath_specimen_adoption_existing_font_id($product_id);
    $result['font_id'] = $font_id;
    if (!$font_id) {
        $result['message'] = __('This Woo product is not linked to a completed Athtyp record.', 'authentype-font-specimen');
        return $result;
    }

    $review = array();
    $missing_delivery = array();

    $linked_ids = get_posts(array(
        'post_type' => 'ath_font',
        'post_status' => 'any',
        'posts_per_page' => 3,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_key' => '_ath_linked_product',
        'meta_value' => $product_id,
        'meta_compare' => '=',
    ));
    $linked_ids = array_values(array_unique(array_map('intval', (array) $linked_ids)));
    if (count($linked_ids) > 1) {
        $review[] = __('more than one Athtyp record links to this Woo product', 'authentype-font-specimen');
    }
    $owner_meta = absint(get_post_meta($product_id, '_ath_athtyp_owner_post_id', true));
    if ($owner_meta && $owner_meta !== $font_id) {
        $review[] = __('Woo ownership metadata points to a different Athtyp record', 'authentype-font-specimen');
    }
    $pricing = array();
    $sync = array();

    // Storefront checkout resolves linked products through published Athtyp
    // posts only. A completed-but-draft adoption therefore cannot be Shop Ready.
    if ('publish' !== get_post_status($font_id)) {
        $review[] = __('linked Athtyp is not published', 'authentype-font-specimen');
    }
    if (!$product->is_type('variable')) {
        $review[] = __('Woo product is not variable', 'authentype-font-specimen');
    }
    if ('publish' !== (string) $product->get_status('edit')) {
        $review[] = __('Woo product is not published', 'authentype-font-specimen');
    }

    if (!$analysis) $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
    if (is_wp_error($analysis)) {
        $review[] = $analysis->get_error_message();
        $analysis = array();
    }

    $style_attr = sanitize_title((string) get_post_meta($font_id, '_ath_style_attribute', true));
    $license_attr = sanitize_title((string) get_post_meta($font_id, '_ath_license_attribute', true));
    if (!$style_attr || !$license_attr || $style_attr === $license_attr ||
        empty($dataset['attributes'][$style_attr]) || empty($dataset['attributes'][$license_attr])) {
        $review[] = __('Style/License mapping no longer matches live Woo attributes', 'authentype-font-specimen');
    }
    if (!empty($analysis['extra_dimensions'])) $review[] = __('Woo has an extra variation dimension', 'authentype-font-specimen');
    if (!empty($analysis['wildcard_variations'])) $review[] = __('Woo contains wildcard Style/License variations', 'authentype-font-specimen');
    if (!empty($analysis['duplicate_pairs'])) $review[] = __('Woo contains duplicate Style × License variations', 'authentype-font-specimen');

    $styles = get_post_meta($font_id, '_ath_font_styles', true);
    $licenses = get_post_meta($font_id, '_ath_license_options', true);
    $styles = is_array($styles) ? $styles : array();
    $licenses = is_array($licenses) ? $licenses : array();
    $allowed_styles = array();
    foreach ($styles as $row) {
        if (!is_array($row)) continue;
        $value = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : ath_specimen_slug($row['style_name'] ?? '');
        if ($value) $allowed_styles[$value] = true;
    }
    $allowed_licenses = array();
    $contact_licenses = array();
    foreach ($licenses as $row) {
        if (!is_array($row) || empty($row['license_variation_value'])) continue;
        $value = ath_specimen_slug($row['license_variation_value']);
        if (!$value) continue;
        $allowed_licenses[$value] = true;
        if (function_exists('ath_specimen_license_is_contact_sales') && ath_specimen_license_is_contact_sales($row)) {
            $contact_licenses[$value] = true;
        }
    }
    if (empty($allowed_styles)) $review[] = __('Athtyp has no usable Style inventory', 'authentype-font-specimen');
    if (empty($allowed_licenses)) $review[] = __('Athtyp has no usable License inventory', 'authentype-font-specimen');

    if ((bool) get_post_meta($font_id, '_ath_pricing_needs_review', true)) {
        $pricing[] = __('Athtyp pricing is marked Needs Review', 'authentype-font-specimen');
    }

    $matrix = get_post_meta($font_id, '_ath_price_matrix', true);
    $matrix = is_array($matrix) ? $matrix : array();
    $download_rows = get_post_meta($font_id, '_ath_product_downloads', true);
    $download_rows = is_array($download_rows) ? $download_rows : array();
    $delivery_pairs = function_exists('ath_specimen_product_download_delivery_pairs')
        ? ath_specimen_product_download_delivery_pairs($download_rows)
        : array();

    $expected = array();
    foreach ((array) $delivery_pairs as $pair_key => $enabled) {
        if (!$enabled || false === strpos($pair_key, '|')) continue;
        list($style, $license) = array_map('ath_specimen_slug', explode('|', $pair_key, 2));
        if (!$style || !$license) continue;
        if (empty($allowed_styles[$style]) || empty($allowed_licenses[$license])) {
            $review[] = sprintf(__('delivery pair %s is outside the current Athtyp Style/License inventory', 'authentype-font-specimen'), $pair_key);
            continue;
        }
        // Contact-sales licenses are intentionally never cart-purchasable.
        if (!empty($contact_licenses[$license])) continue;
        $expected[$pair_key] = true;
        $price = function_exists('ath_specimen_matrix_price_values')
            ? ath_specimen_matrix_price_values($matrix, $style, $license)
            : array('active' => '');
        if ('' === (string) ($price['active'] ?? '')) {
            $pricing[] = sprintf(__('no active Athtyp price for %s', 'authentype-font-specimen'), $pair_key);
        }
    }
    $result['counts']['expected_pairs'] = count($expected);

    $has_purchasable_license = false;
    foreach ($allowed_licenses as $license => $yes) {
        if (empty($contact_licenses[$license])) { $has_purchasable_license = true; break; }
    }
    if (!$has_purchasable_license) {
        $review[] = __('Athtyp has no purchasable license configured for storefront checkout', 'authentype-font-specimen');
    } elseif (empty($expected)) {
        $missing_delivery[] = __('no purchasable Style × License pair has an Athtyp delivery mapping', 'authentype-font-specimen');
    }

    $actual = array();
    if ($style_attr && $license_attr) {
        foreach ((array) $dataset['variations'] as $row) {
            $attrs = (array) ($row['attributes'] ?? array());
            $style = isset($attrs[$style_attr]) ? ath_specimen_slug($attrs[$style_attr]) : '';
            $license = isset($attrs[$license_attr]) ? ath_specimen_slug($attrs[$license_attr]) : '';
            if (!$style || !$license) continue;
            $actual[$style . '|' . $license] = $row;
        }
    }

    foreach ($expected as $pair_key => $yes) {
        if (empty($actual[$pair_key])) {
            $sync[] = sprintf(__('Woo variation is missing for %s', 'authentype-font-specimen'), $pair_key);
            continue;
        }
        $row = $actual[$pair_key];
        $variation = $row['product'] ?? null;
        if ('publish' !== (string) ($row['status'] ?? '')) {
            $sync[] = sprintf(__('Woo variation is not published for %s', 'authentype-font-specimen'), $pair_key);
        }
        if (!$variation || !is_object($variation)) {
            $review[] = sprintf(__('Woo variation object is unavailable for %s', 'authentype-font-specimen'), $pair_key);
            continue;
        }
        if ('1' === (string) $variation->get_meta('_ath_disabled_by_sync', true) ||
            '1' === (string) $variation->get_meta('_ath_delivery_missing', true)) {
            $sync[] = sprintf(__('Woo variation is disabled/stale for %s', 'authentype-font-specimen'), $pair_key);
        }

        list($style, $license) = explode('|', $pair_key, 2);
        if (function_exists('ath_specimen_variation_mirror_status')) {
            $mirror = ath_specimen_variation_mirror_status($font_id, $variation, $style, $license);
            if (is_wp_error($mirror)) {
                $code = (string) $mirror->get_error_code();
                if ('ath_mirror_unpriced' === $code) {
                    $pricing[] = sprintf(__('pricing mirror is incomplete for %s', 'authentype-font-specimen'), $pair_key);
                } elseif ('ath_mirror_delivery_missing' === $code) {
                    $missing_delivery[] = sprintf(__('Athtyp delivery is missing for %s', 'authentype-font-specimen'), $pair_key);
                } elseif (in_array($code, array('ath_mirror_price', 'ath_mirror_delivery'), true)) {
                    $sync[] = sprintf(__('Woo mirror differs from Athtyp for %s', 'authentype-font-specimen'), $pair_key);
                } else {
                    $review[] = sprintf(__('variation validation failed for %s', 'authentype-font-specimen'), $pair_key);
                }
            } else {
                $result['counts']['matched_pairs']++;
            }
        }
    }

    // Existing Woo pairs that Athtyp no longer considers purchasable should be
    // reconciled by normal Woo Sync. If Athtyp has no delivery for a still-valid
    // pair, classify it as Missing Delivery rather than suggesting a blind sync.
    foreach ($actual as $pair_key => $row) {
        if (isset($expected[$pair_key])) continue;
        if (false === strpos($pair_key, '|')) continue;
        list($style, $license) = explode('|', $pair_key, 2);
        if (!empty($contact_licenses[$license]) || empty($allowed_styles[$style]) || empty($allowed_licenses[$license])) {
            $sync[] = sprintf(__('Woo contains a stale/non-purchasable pair %s', 'authentype-font-specimen'), $pair_key);
        } elseif (empty($delivery_pairs[$pair_key])) {
            $missing_delivery[] = sprintf(__('Athtyp has no delivery mapping for live Woo pair %s', 'authentype-font-specimen'), $pair_key);
        }
    }

    // Read the current mutex option directly. Do not call the normal lock
    // reader here because it may clean/upgrade stale legacy locks; this audit is
    // intentionally zero-write.
    if (function_exists('ath_specimen_woo_sync_lock_key') && function_exists('ath_specimen_woo_sync_lock_option_name')) {
        $lock_key = ath_specimen_woo_sync_lock_key($font_id, $product_id);
        $lock_record = $lock_key ? get_option(ath_specimen_woo_sync_lock_option_name($lock_key), array()) : array();
        if (is_array($lock_record) && !empty($lock_record['token']) &&
            (empty($lock_record['expires']) || (int) $lock_record['expires'] > time())) {
            $sync[] = __('Woo Sync is currently in progress', 'authentype-font-specimen');
        }
    }

    $review = array_values(array_unique(array_filter($review)));
    $missing_delivery = array_values(array_unique(array_filter($missing_delivery)));
    $pricing = array_values(array_unique(array_filter($pricing)));
    $sync = array_values(array_unique(array_filter($sync)));
    $result['flags'] = array(
        'review' => $review,
        'missing_delivery' => $missing_delivery,
        'needs_pricing' => $pricing,
        'needs_sync' => $sync,
    );

    $format_message = function ($items) {
        $items = array_values(array_filter((array) $items));
        if (empty($items)) return '';
        $shown = array_slice($items, 0, 3);
        $message = implode('; ', $shown);
        if (count($items) > count($shown)) $message .= sprintf(__('; +%d more', 'authentype-font-specimen'), count($items) - count($shown));
        return $message;
    };

    if (!empty($review)) {
        $result['status'] = 'review';
        $result['status_label'] = __('Review', 'authentype-font-specimen');
        $result['message'] = $format_message($review);
    } elseif (!empty($missing_delivery)) {
        $result['status'] = 'missing_delivery';
        $result['status_label'] = __('Missing Delivery', 'authentype-font-specimen');
        $result['message'] = $format_message($missing_delivery);
    } elseif (!empty($pricing)) {
        $result['status'] = 'needs_pricing';
        $result['status_label'] = __('Needs Pricing', 'authentype-font-specimen');
        $result['message'] = $format_message($pricing);
    } elseif (!empty($sync)) {
        $result['status'] = 'needs_sync';
        $result['status_label'] = __('Needs Woo Sync', 'authentype-font-specimen');
        $result['message'] = $format_message($sync);
    } else {
        $result['status'] = 'shop_ready';
        $result['status_label'] = __('Shop Ready', 'authentype-font-specimen');
        $result['message'] = sprintf(__('Exact live mirror verified for %d purchasable Style × License pair(s).', 'authentype-font-specimen'), (int) $result['counts']['matched_pairs']);
    }
    return $result;
}

/**
 * Build a zero-write hydration plan for one already-linked Woo product.
 *
 * secure.8.2.25: legacy delivery hydration is intentionally narrower than
 * adoption or Woo Sync. It may only repair an Athtyp record currently
 * classified Missing Delivery by copying the exact existing Woo download
 * identity (ID/name/file) for Style × License pairs that have no Athtyp
 * delivery mapping. It never changes WooCommerce, pricing, styles, licenses,
 * orders, customer permissions, or already-mapped delivery pairs.
 */
function ath_specimen_adoption_legacy_delivery_plan($product_id, $dataset = null, $analysis = null) {
    $product_id = absint($product_id);
    $result = array(
        'product_id' => $product_id,
        'font_id' => 0,
        'eligible' => false,
        'status' => 'skipped',
        'status_label' => __('Skipped', 'authentype-font-specimen'),
        'message' => '',
        'product_name' => '',
        'blocked_reasons' => array(),
        'rows' => array(),
        'pairs' => array(),
        'counts' => array('pairs' => 0, 'downloads' => 0, 'existing_downloads' => 0),
    );
    if (!$product_id) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Woo product ID is missing.', 'authentype-font-specimen');
        return $result;
    }

    if (!$dataset) $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (!is_wp_error($dataset) && !empty($dataset['product']) && is_object($dataset['product']) && method_exists($dataset['product'], 'get_name')) {
        $result['product_name'] = sanitize_text_field((string) $dataset['product']->get_name());
    }
    if (is_wp_error($dataset)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = $dataset->get_error_message();
        return $result;
    }
    if (!$analysis) $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
    if (is_wp_error($analysis)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = $analysis->get_error_message();
        return $result;
    }

    $readiness = ath_specimen_adoption_commerce_readiness($product_id, $dataset, $analysis);
    $result['readiness_status'] = sanitize_key((string) ($readiness['status'] ?? 'review'));
    if ('missing_delivery' !== $result['readiness_status']) {
        $result['message'] = sprintf(
            __('Current commerce status is %s, so Legacy Delivery Hydration will not touch this product.', 'authentype-font-specimen'),
            (string) ($readiness['status_label'] ?? __('Review', 'authentype-font-specimen'))
        );
        return $result;
    }

    $font_id = absint($readiness['font_id'] ?? 0);
    $result['font_id'] = $font_id;
    if (!$font_id || !current_user_can('edit_post', $font_id)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('The linked Athtyp record cannot be edited by the current user.', 'authentype-font-specimen');
        return $result;
    }

    // Require exactly one Athtyp link. The readiness audit already treats
    // duplicates as Review, but repeat the guard here because hydration writes.
    $linked_ids = get_posts(array(
        'post_type' => 'ath_font',
        'post_status' => 'any',
        'posts_per_page' => 3,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_key' => '_ath_linked_product',
        'meta_value' => $product_id,
        'meta_compare' => '=',
    ));
    $linked_ids = array_values(array_unique(array_map('intval', (array) $linked_ids)));
    if (1 !== count($linked_ids) || (int) $linked_ids[0] !== $font_id) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Legacy delivery hydration requires exactly one Athtyp record linked to this Woo product.', 'authentype-font-specimen');
        return $result;
    }
    $owner_meta = absint(get_post_meta($product_id, '_ath_athtyp_owner_post_id', true));
    if ($owner_meta && $owner_meta !== $font_id) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Woo ownership metadata points to a different Athtyp record.', 'authentype-font-specimen');
        return $result;
    }

    $style_attr = sanitize_title((string) get_post_meta($font_id, '_ath_style_attribute', true));
    $license_attr = sanitize_title((string) get_post_meta($font_id, '_ath_license_attribute', true));
    if (!$style_attr || !$license_attr || $style_attr === $license_attr ||
        empty($dataset['attributes'][$style_attr]) || empty($dataset['attributes'][$license_attr])) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('The saved Style/License mapping does not exactly match the current Woo product.', 'authentype-font-specimen');
        return $result;
    }
    if (!empty($analysis['extra_dimensions']) || !empty($analysis['wildcard_variations']) || !empty($analysis['duplicate_pairs'])) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Woo variation structure is ambiguous. Resolve extra dimensions, wildcard values, or duplicate Style × License pairs before hydration.', 'authentype-font-specimen');
        return $result;
    }

    $styles = get_post_meta($font_id, '_ath_font_styles', true);
    $licenses = get_post_meta($font_id, '_ath_license_options', true);
    $styles = is_array($styles) ? $styles : array();
    $licenses = is_array($licenses) ? $licenses : array();
    $allowed_styles = array();
    foreach ($styles as $row) {
        if (!is_array($row)) continue;
        $value = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : ath_specimen_slug($row['style_name'] ?? '');
        if ($value) $allowed_styles[$value] = true;
    }
    $allowed_licenses = array();
    $contact_licenses = array();
    foreach ($licenses as $row) {
        if (!is_array($row) || empty($row['license_variation_value'])) continue;
        $value = ath_specimen_slug($row['license_variation_value']);
        if (!$value) continue;
        $allowed_licenses[$value] = true;
        if (function_exists('ath_specimen_license_is_contact_sales') && ath_specimen_license_is_contact_sales($row)) {
            $contact_licenses[$value] = true;
        }
    }
    if (empty($allowed_styles) || empty($allowed_licenses)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Athtyp Style/License inventory is incomplete.', 'authentype-font-specimen');
        return $result;
    }

    $existing_rows = get_post_meta($font_id, '_ath_product_downloads', true);
    $existing_rows = is_array($existing_rows) ? $existing_rows : array();
    $existing_rows = ath_specimen_sanitize_product_downloads($existing_rows);
    $result['counts']['existing_downloads'] = count($existing_rows);
    $delivery_pairs = function_exists('ath_specimen_product_download_delivery_pairs')
        ? ath_specimen_product_download_delivery_pairs($existing_rows)
        : array();

    $used_ids = array();
    foreach ($existing_rows as $row) {
        $id = !empty($row['download_id']) ? sanitize_text_field((string) $row['download_id']) : '';
        if ($id) $used_ids[$id] = true;
    }

    $new_rows = array();
    $planned_pairs = array();
    $new_ids = array();
    $blocked = array();
    foreach ((array) $dataset['variations'] as $variation) {
        $attrs = (array) ($variation['attributes'] ?? array());
        $style = isset($attrs[$style_attr]) ? ath_specimen_slug($attrs[$style_attr]) : '';
        $license = isset($attrs[$license_attr]) ? ath_specimen_slug($attrs[$license_attr]) : '';
        if (!$style || !$license) continue;
        if (empty($allowed_styles[$style]) || empty($allowed_licenses[$license]) || !empty($contact_licenses[$license])) continue;
        $pair_key = $style . '|' . $license;
        if (!empty($delivery_pairs[$pair_key])) continue; // Never append to an already-mapped pair.

        if ('publish' !== (string) ($variation['status'] ?? '')) {
            $blocked[] = sprintf(__('Woo variation #%d is not published.', 'authentype-font-specimen'), (int) ($variation['id'] ?? 0));
            continue;
        }
        if (empty($variation['downloadable'])) {
            $blocked[] = sprintf(__('Woo variation #%d is not marked downloadable.', 'authentype-font-specimen'), (int) ($variation['id'] ?? 0));
            continue;
        }

        $pair_rows = array();
        $raw_downloads = (array) ($variation['downloads'] ?? array());
        if (empty($raw_downloads)) {
            $blocked[] = sprintf(__('Woo variation #%d has no download files to hydrate.', 'authentype-font-specimen'), (int) ($variation['id'] ?? 0));
            continue;
        }
        foreach ($raw_downloads as $download) {
            $raw_file = !empty($download['file']) ? (string) $download['file'] : '';
            $file = $raw_file ? ath_specimen_adoption_download_url($raw_file) : '';
            $id = !empty($download['id']) ? sanitize_text_field((string) $download['id']) : '';
            if (!$raw_file || !$file) {
                $blocked[] = sprintf(__('Woo variation #%d contains a download path that cannot be represented safely.', 'authentype-font-specimen'), (int) ($variation['id'] ?? 0));
                $pair_rows = array();
                break;
            }
            if (!$id) {
                $blocked[] = sprintf(__('Woo variation #%d contains a download without a stable Woo download ID.', 'authentype-font-specimen'), (int) ($variation['id'] ?? 0));
                $pair_rows = array();
                break;
            }
            if (!empty($used_ids[$id]) || !empty($new_ids[$id])) {
                $blocked[] = sprintf(__('Woo download ID %s is already assigned elsewhere in this Athtyp delivery map.', 'authentype-font-specimen'), $id);
                $pair_rows = array();
                break;
            }
            $pair_rows[] = array(
                'download_id' => $id,
                'download_name' => !empty($download['name']) ? sanitize_text_field((string) $download['name']) : ath_specimen_download_name_from_file($file),
                'download_file' => $file,
                'style_variation_value' => $style,
                'license_variation_value' => $license,
                'legacy_download' => 1,
            );
            $new_ids[$id] = true;
        }
        if (empty($pair_rows)) continue;
        foreach ($pair_rows as $row) $new_rows[] = $row;
        $planned_pairs[$pair_key] = true;
    }

    if (!empty($blocked)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['blocked_reasons'] = array_values(array_unique(array_filter(array_map('sanitize_text_field', $blocked))));
        $shown = array_slice($result['blocked_reasons'], 0, 3);
        $result['message'] = implode('; ', $shown);
        if (count($blocked) > count($shown)) $result['message'] .= sprintf(__('; +%d more', 'authentype-font-specimen'), count($blocked) - count($shown));
        return $result;
    }
    if (empty($new_rows) || empty($planned_pairs)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('The product is Missing Delivery, but no safe unmapped Woo download pair could be reconstructed.', 'authentype-font-specimen');
        return $result;
    }

    $result['rows'] = ath_specimen_sanitize_product_downloads($new_rows);
    $result['pairs'] = array_values(array_keys($planned_pairs));
    $result['counts']['pairs'] = count($planned_pairs);
    $result['counts']['downloads'] = count($result['rows']);
    if (!$result['counts']['downloads']) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Download rows were removed by safety sanitization; hydration was blocked.', 'authentype-font-specimen');
        return $result;
    }

    $result['eligible'] = true;
    $result['status'] = 'eligible';
    $result['status_label'] = __('Hydration Ready', 'authentype-font-specimen');
    $result['message'] = sprintf(
        __('Safe to copy %1$d existing Woo download file(s) across %2$d missing Style × License pair(s) into Athtyp only.', 'authentype-font-specimen'),
        (int) $result['counts']['downloads'],
        (int) $result['counts']['pairs']
    );
    return $result;
}

/**
 * Persist an immutable pre-hydration snapshot of Athtyp delivery metadata.
 *
 * secure.8.2.26: snapshots are append-only multi-value post meta. A new
 * version is added for every successful write attempt; previous snapshots are
 * never overwritten. The lightweight "latest" pointer contains no delivery
 * rows and exists only for diagnostics/recovery tooling.
 */
function ath_specimen_adoption_legacy_delivery_create_snapshot($font_id, $product_id, $existing_raw) {
    $font_id = absint($font_id);
    $product_id = absint($product_id);
    if (!$font_id || !$product_id) {
        return new WP_Error('ath_hydrate_snapshot_target', __('Cannot create a hydration safety snapshot without both Athtyp and Woo product IDs.', 'authentype-font-specimen'));
    }

    $meta_key = '_ath_legacy_delivery_hydration_snapshot';
    $existing_snapshots = get_post_meta($font_id, $meta_key, false);
    $version = 1;
    foreach ((array) $existing_snapshots as $snapshot) {
        if (!is_array($snapshot)) continue;
        $version = max($version, (int) ($snapshot['version'] ?? 0) + 1);
    }

    $had_meta = metadata_exists('post', $font_id, '_ath_product_downloads');
    $snapshot_id = 'hyd-v' . $version . '-' . gmdate('YmdHis') . '-' . strtolower(wp_generate_password(8, false, false));
    $snapshot = array(
        'schema' => 1,
        'snapshot_id' => sanitize_key($snapshot_id),
        'version' => $version,
        'created_at' => time(),
        'created_at_gmt' => gmdate('c'),
        'user_id' => get_current_user_id(),
        'font_id' => $font_id,
        'woo_product_id' => $product_id,
        'product_downloads_meta_existed' => $had_meta ? 1 : 0,
        // Preserve the exact pre-hydration value, not merely the sanitized
        // representation, so a later recovery tool can restore byte-equivalent
        // WordPress meta semantics when practical.
        'product_downloads' => $existing_raw,
        'product_downloads_hash' => hash('sha256', maybe_serialize(array('exists' => $had_meta ? 1 : 0, 'value' => $existing_raw))),
    );

    $meta_id = add_post_meta($font_id, $meta_key, $snapshot, false);
    if (!$meta_id) {
        return new WP_Error('ath_hydrate_snapshot_write', __('Hydration was stopped because the pre-hydration safety snapshot could not be persisted.', 'authentype-font-specimen'));
    }

    // This pointer is intentionally summary-only. Updating it never removes or
    // overwrites the append-only snapshot records above.
    update_post_meta($font_id, '_ath_legacy_delivery_hydration_snapshot_latest', array(
        'snapshot_id' => $snapshot['snapshot_id'],
        'version' => $version,
        'created_at' => $snapshot['created_at'],
        'woo_product_id' => $product_id,
        'meta_id' => (int) $meta_id,
        'product_downloads_hash' => $snapshot['product_downloads_hash'],
    ));

    return array(
        'snapshot_id' => $snapshot['snapshot_id'],
        'version' => $version,
        'meta_id' => (int) $meta_id,
    );
}

function ath_specimen_adoption_legacy_delivery_hydrate($product_id) {
    $product_id = absint($product_id);
    if (!$product_id || !ath_specimen_adoption_capable() || !current_user_can('edit_post', $product_id)) {
        return new WP_Error('ath_hydrate_permission', __('Permission denied.', 'authentype-font-specimen'));
    }

    $mutex = 'ath_adopt_mutex_' . $product_id;
    $mutex_record = array('user' => get_current_user_id(), 'time' => time(), 'purpose' => 'legacy_delivery_hydration');
    if (!add_option($mutex, $mutex_record, '', false)) {
        $existing_mutex = get_option($mutex, array());
        if (is_array($existing_mutex) && !empty($existing_mutex['time']) && (int) $existing_mutex['time'] < time() - 10 * MINUTE_IN_SECONDS) delete_option($mutex);
        if (!add_option($mutex, $mutex_record, '', false)) {
            return new WP_Error('ath_hydrate_busy', __('This Woo product is already being processed by another adoption/restore/hydration request.', 'authentype-font-specimen'));
        }
    }

    try {
        // Re-read everything after acquiring the mutex. Preview results are never
        // trusted as write authority because Woo/Athtyp may have changed meanwhile.
        $dataset = ath_specimen_adoption_load_dataset($product_id);
        if (is_wp_error($dataset)) return $dataset;
        $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
        if (is_wp_error($analysis)) return $analysis;
        $plan = ath_specimen_adoption_legacy_delivery_plan($product_id, $dataset, $analysis);
        if (empty($plan['eligible'])) {
            return new WP_Error('ath_hydrate_not_eligible', $plan['message'] ?: __('This product is no longer safe for legacy delivery hydration.', 'authentype-font-specimen'));
        }

        $font_id = absint($plan['font_id'] ?? 0);
        if (!$font_id || !current_user_can('edit_post', $font_id)) {
            return new WP_Error('ath_hydrate_font_permission', __('You cannot edit the linked Athtyp record.', 'authentype-font-specimen'));
        }
        if (function_exists('ath_specimen_stability_cross_engine_guard')) {
            $operation_guard = ath_specimen_stability_cross_engine_guard($font_id, $product_id, array('adoption'));
            if (is_wp_error($operation_guard)) return $operation_guard;
        }

        // Serialize against Woo Sync as well as adoption/restore. Acquiring this
        // short-lived product mutex changes no Woo product/variation data; it only
        // prevents a sync session from starting while Athtyp delivery metadata is
        // being reconstructed from the current live Woo downloads.
        $woo_lock_key = '';
        $woo_lock_token = '';
        if (function_exists('ath_specimen_woo_sync_lock_key') && function_exists('ath_specimen_woo_sync_acquire_lock')) {
            $woo_lock_key = ath_specimen_woo_sync_lock_key($font_id, $product_id);
            $woo_lock_token = 'hydrate-' . wp_generate_password(32, false, false);
            if ($woo_lock_key && !ath_specimen_woo_sync_acquire_lock($woo_lock_key, $woo_lock_token, 10 * MINUTE_IN_SECONDS)) {
                return new WP_Error('ath_hydrate_sync_busy', __('Woo Sync is currently in progress for this product. Finish the sync before hydrating legacy delivery.', 'authentype-font-specimen'));
            }
        }

        try {
            $existing_raw = get_post_meta($font_id, '_ath_product_downloads', true);
            $existing_rows = is_array($existing_raw) ? $existing_raw : array();
            $existing_rows = ath_specimen_sanitize_product_downloads($existing_rows);
            $merged = ath_specimen_sanitize_product_downloads(array_merge($existing_rows, (array) $plan['rows']));
            $expected_count = count($existing_rows) + count((array) $plan['rows']);
            if (count($merged) !== $expected_count) {
                return new WP_Error('ath_hydrate_collision', __('Hydration stopped because a duplicate or invalid delivery row was detected during final merge.', 'authentype-font-specimen'));
            }

            // Persist a versioned, append-only recovery snapshot immediately
            // before the only authoritative delivery-map write. If the snapshot
            // cannot be stored, hydration is blocked rather than proceeding
            // without a recovery point.
            $snapshot = ath_specimen_adoption_legacy_delivery_create_snapshot($font_id, $product_id, $existing_raw);
            if (is_wp_error($snapshot)) return $snapshot;

            update_post_meta($font_id, '_ath_product_downloads', $merged);
            $stored = get_post_meta($font_id, '_ath_product_downloads', true);
            $stored = is_array($stored) ? ath_specimen_sanitize_product_downloads($stored) : array();
            if (count($stored) !== count($merged)) {
                // Roll back the only commerce-authority write if verification
                // fails. Never leave a partially hydrated delivery map behind.
                if (is_array($existing_raw)) update_post_meta($font_id, '_ath_product_downloads', $existing_raw);
                else delete_post_meta($font_id, '_ath_product_downloads');
                return new WP_Error('ath_hydrate_verify', __('Athtyp delivery metadata verification failed after hydration and the previous delivery map was restored.', 'authentype-font-specimen'));
            }

            update_post_meta($font_id, '_ath_adoption_legacy_delivery', '1');
            update_post_meta($font_id, '_ath_legacy_delivery_hydrated_at', time());
            update_post_meta($font_id, '_ath_legacy_delivery_hydrated_from_product', $product_id);
            update_post_meta($font_id, '_ath_legacy_delivery_hydrated_download_count', count((array) $plan['rows']));
            clean_post_cache($font_id);

            return array(
                'product_id' => $product_id,
                'font_id' => $font_id,
                'pairs' => (int) ($plan['counts']['pairs'] ?? 0),
                'downloads' => (int) ($plan['counts']['downloads'] ?? 0),
                'total_downloads' => count($stored),
                'snapshot_id' => sanitize_key((string) ($snapshot['snapshot_id'] ?? '')),
                'snapshot_version' => (int) ($snapshot['version'] ?? 0),
                'message' => sprintf(
                    __('Hydrated %1$d legacy Woo download file(s) across %2$d missing Style × License pair(s). WooCommerce, pricing, orders, and customer permissions were not changed.', 'authentype-font-specimen'),
                    (int) ($plan['counts']['downloads'] ?? 0),
                    (int) ($plan['counts']['pairs'] ?? 0)
                ),
            );
        } finally {
            if ($woo_lock_key && $woo_lock_token && function_exists('ath_specimen_woo_sync_release_lock')) {
                ath_specimen_woo_sync_release_lock($woo_lock_key, $woo_lock_token);
            }
        }
    } finally {
        delete_option($mutex);
    }
}

function ath_specimen_adoption_legacy_delivery_plan_payload($plan) {
    return array(
        'product_id' => (int) ($plan['product_id'] ?? 0),
        'font_id' => (int) ($plan['font_id'] ?? 0),
        'eligible' => !empty($plan['eligible']),
        'status' => sanitize_key((string) ($plan['status'] ?? 'blocked')),
        'status_label' => sanitize_text_field((string) ($plan['status_label'] ?? '')),
        'readiness_status' => sanitize_key((string) ($plan['readiness_status'] ?? '')),
        'message' => sanitize_text_field((string) ($plan['message'] ?? '')),
        'product_name' => sanitize_text_field((string) (($plan['product_name'] ?? '') ?: get_the_title((int) ($plan['product_id'] ?? 0)))),
        'blocked_reasons' => array_values(array_slice(array_filter(array_map('sanitize_text_field', (array) (!empty($plan['blocked_reasons']) ? $plan['blocked_reasons'] : (!empty($plan['message']) ? array($plan['message']) : array())))), 0, 12)),
        'counts' => array(
            'pairs' => (int) ($plan['counts']['pairs'] ?? 0),
            'downloads' => (int) ($plan['counts']['downloads'] ?? 0),
            'existing_downloads' => (int) ($plan['counts']['existing_downloads'] ?? 0),
        ),
    );
}


/**
 * Build a zero-write legacy pricing hydration plan for one linked Woo product.
 *
 * secure.8.2.27: this migration path is intentionally one-way and narrow:
 * WooCommerce is read only, existing Athtyp prices always win, and only an
 * entirely empty Athtyp Style × License price cell may be populated from the
 * exact live Woo variation that owns the same pair. Scheduled sales, ambiguous
 * variation structures, stale/disabled variations, malformed existing price
 * rows, and non-Needs-Pricing products are blocked.
 */
function ath_specimen_adoption_legacy_pricing_plan($product_id, $dataset = null, $analysis = null) {
    $product_id = absint($product_id);
    $result = array(
        'product_id' => $product_id,
        'font_id' => 0,
        'eligible' => false,
        'status' => 'skipped',
        'status_label' => __('Skipped', 'authentype-font-specimen'),
        'readiness_status' => '',
        'message' => '',
        'product_name' => '',
        'blocked_reasons' => array(),
        'prices' => array(),
        'pairs' => array(),
        'counts' => array(
            'pairs' => 0,
            'sales' => 0,
            'expected_pairs' => 0,
            'existing_priced' => 0,
        ),
    );
    if (!$product_id) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Woo product ID is missing.', 'authentype-font-specimen');
        return $result;
    }

    if (!$dataset) $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (!is_wp_error($dataset) && !empty($dataset['product']) && is_object($dataset['product']) && method_exists($dataset['product'], 'get_name')) {
        $result['product_name'] = sanitize_text_field((string) $dataset['product']->get_name());
    }
    if (is_wp_error($dataset)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = $dataset->get_error_message();
        return $result;
    }
    if (!$analysis) $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
    if (is_wp_error($analysis)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = $analysis->get_error_message();
        return $result;
    }

    $readiness = ath_specimen_adoption_commerce_readiness($product_id, $dataset, $analysis);
    $result['readiness_status'] = sanitize_key((string) ($readiness['status'] ?? 'review'));
    if ('needs_pricing' !== $result['readiness_status']) {
        $result['message'] = sprintf(
            __('Current commerce status is %s, so Legacy Pricing Hydration will not touch this product.', 'authentype-font-specimen'),
            (string) ($readiness['status_label'] ?? __('Review', 'authentype-font-specimen'))
        );
        return $result;
    }

    $font_id = absint($readiness['font_id'] ?? 0);
    $result['font_id'] = $font_id;
    if (!$font_id || !current_user_can('edit_post', $font_id)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('The linked Athtyp record cannot be edited by the current user.', 'authentype-font-specimen');
        return $result;
    }

    $linked_ids = get_posts(array(
        'post_type' => 'ath_font',
        'post_status' => 'any',
        'posts_per_page' => 3,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_key' => '_ath_linked_product',
        'meta_value' => $product_id,
        'meta_compare' => '=',
    ));
    $linked_ids = array_values(array_unique(array_map('intval', (array) $linked_ids)));
    if (1 !== count($linked_ids) || (int) $linked_ids[0] !== $font_id) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Legacy pricing hydration requires exactly one Athtyp record linked to this Woo product.', 'authentype-font-specimen');
        return $result;
    }
    $owner_meta = absint(get_post_meta($product_id, '_ath_athtyp_owner_post_id', true));
    if ($owner_meta && $owner_meta !== $font_id) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Woo ownership metadata points to a different Athtyp record.', 'authentype-font-specimen');
        return $result;
    }

    $style_attr = sanitize_title((string) get_post_meta($font_id, '_ath_style_attribute', true));
    $license_attr = sanitize_title((string) get_post_meta($font_id, '_ath_license_attribute', true));
    if (!$style_attr || !$license_attr || $style_attr === $license_attr ||
        empty($dataset['attributes'][$style_attr]) || empty($dataset['attributes'][$license_attr])) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('The saved Style/License mapping does not exactly match the current Woo product.', 'authentype-font-specimen');
        return $result;
    }
    if (!empty($analysis['extra_dimensions']) || !empty($analysis['wildcard_variations']) || !empty($analysis['duplicate_pairs'])) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Woo variation structure is ambiguous. Resolve extra dimensions, wildcard values, or duplicate Style × License pairs before pricing hydration.', 'authentype-font-specimen');
        return $result;
    }

    $styles = get_post_meta($font_id, '_ath_font_styles', true);
    $licenses = get_post_meta($font_id, '_ath_license_options', true);
    $styles = is_array($styles) ? $styles : array();
    $licenses = is_array($licenses) ? $licenses : array();
    $allowed_styles = array();
    foreach ($styles as $row) {
        if (!is_array($row)) continue;
        $value = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : ath_specimen_slug($row['style_name'] ?? '');
        if ($value) $allowed_styles[$value] = true;
    }
    $allowed_licenses = array();
    $contact_licenses = array();
    foreach ($licenses as $row) {
        if (!is_array($row) || empty($row['license_variation_value'])) continue;
        $value = ath_specimen_slug($row['license_variation_value']);
        if (!$value) continue;
        $allowed_licenses[$value] = true;
        if (function_exists('ath_specimen_license_is_contact_sales') && ath_specimen_license_is_contact_sales($row)) {
            $contact_licenses[$value] = true;
        }
    }
    if (empty($allowed_styles) || empty($allowed_licenses)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Athtyp Style/License inventory is incomplete.', 'authentype-font-specimen');
        return $result;
    }

    $download_rows = get_post_meta($font_id, '_ath_product_downloads', true);
    $download_rows = is_array($download_rows) ? ath_specimen_sanitize_product_downloads($download_rows) : array();
    $delivery_pairs = function_exists('ath_specimen_product_download_delivery_pairs')
        ? ath_specimen_product_download_delivery_pairs($download_rows)
        : array();
    if (empty($delivery_pairs)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Athtyp has no delivery-backed purchasable pairs to price.', 'authentype-font-specimen');
        return $result;
    }

    $actual = array();
    foreach ((array) $dataset['variations'] as $variation) {
        $attrs = (array) ($variation['attributes'] ?? array());
        $style = isset($attrs[$style_attr]) ? ath_specimen_slug($attrs[$style_attr]) : '';
        $license = isset($attrs[$license_attr]) ? ath_specimen_slug($attrs[$license_attr]) : '';
        if (!$style || !$license) continue;
        $actual[$style . '|' . $license] = $variation;
    }

    $matrix_raw = get_post_meta($font_id, '_ath_price_matrix', true);
    $matrix_raw = is_array($matrix_raw) ? $matrix_raw : array();
    $blocked = array();
    $planned = array();

    foreach ((array) $delivery_pairs as $pair_key => $enabled) {
        if (!$enabled || false === strpos($pair_key, '|')) continue;
        list($style, $license) = array_map('ath_specimen_slug', explode('|', $pair_key, 2));
        if (!$style || !$license || empty($allowed_styles[$style]) || empty($allowed_licenses[$license]) || !empty($contact_licenses[$license])) continue;
        $pair_key = $style . '|' . $license;
        $result['counts']['expected_pairs']++;

        $existing = function_exists('ath_specimen_matrix_price_values')
            ? ath_specimen_matrix_price_values($matrix_raw, $style, $license)
            : array('regular' => '', 'sale' => '', 'active' => '');
        if ('' !== (string) ($existing['active'] ?? '')) {
            $result['counts']['existing_priced']++;
            continue; // Existing Athtyp price always wins; never overwrite it.
        }

        // If a cell contains non-empty raw values but sanitizes to no active
        // price, do not overwrite potentially intentional/malformed legacy data.
        $raw_cell = isset($matrix_raw[$style][$license]) && is_array($matrix_raw[$style][$license])
            ? $matrix_raw[$style][$license]
            : array();
        $raw_regular = isset($raw_cell['regular']) ? trim((string) $raw_cell['regular']) : '';
        $raw_sale = isset($raw_cell['sale']) ? trim((string) $raw_cell['sale']) : '';
        if ('' !== $raw_regular || '' !== $raw_sale) {
            $blocked[] = sprintf(__('Athtyp already contains a non-empty but invalid price row for %s; it must be reviewed manually.', 'authentype-font-specimen'), $pair_key);
            continue;
        }

        if (empty($actual[$pair_key])) {
            $blocked[] = sprintf(__('Woo variation is missing for %s.', 'authentype-font-specimen'), $pair_key);
            continue;
        }
        $variation = $actual[$pair_key];
        $variation_id = (int) ($variation['id'] ?? 0);
        $variation_product = $variation['product'] ?? null;
        if ('publish' !== (string) ($variation['status'] ?? '')) {
            $blocked[] = sprintf(__('Woo variation #%d is not published.', 'authentype-font-specimen'), $variation_id);
            continue;
        }
        if (!$variation_product || !is_object($variation_product)) {
            $blocked[] = sprintf(__('Woo variation #%d is unavailable.', 'authentype-font-specimen'), $variation_id);
            continue;
        }
        if ('1' === (string) $variation_product->get_meta('_ath_disabled_by_sync', true) ||
            '1' === (string) $variation_product->get_meta('_ath_delivery_missing', true)) {
            $blocked[] = sprintf(__('Woo variation #%d is marked disabled/stale by Athtyp sync metadata.', 'authentype-font-specimen'), $variation_id);
            continue;
        }
        if ('' !== (string) ($variation['sale_from'] ?? '') || '' !== (string) ($variation['sale_to'] ?? '')) {
            $blocked[] = sprintf(__('Woo variation #%d uses a scheduled sale; scheduled pricing is not imported automatically.', 'authentype-font-specimen'), $variation_id);
            continue;
        }

        $regular_raw = trim((string) ($variation['regular_price'] ?? ''));
        $sale_raw = trim((string) ($variation['sale_price'] ?? ''));
        $active_raw = trim((string) ($variation['price'] ?? ''));
        if ('' === $regular_raw && '' === $sale_raw && '' !== $active_raw) {
            // Legacy Woo data occasionally stores only _price. Treat that exact
            // unscheduled active value as Regular rather than inventing a sale.
            $regular_raw = $active_raw;
        }

        $regular = '' !== $regular_raw && function_exists('ath_specimen_sanitize_price_value')
            ? ath_specimen_sanitize_price_value($regular_raw)
            : ('' !== $regular_raw ? $regular_raw : '');
        $sale = '' !== $sale_raw && function_exists('ath_specimen_sanitize_price_value')
            ? ath_specimen_sanitize_price_value($sale_raw)
            : ('' !== $sale_raw ? $sale_raw : '');
        $active = '' !== $active_raw && function_exists('ath_specimen_sanitize_price_value')
            ? ath_specimen_sanitize_price_value($active_raw)
            : ('' !== $active_raw ? $active_raw : '');

        if ('' === $regular) {
            $blocked[] = sprintf(__('Woo variation #%d has no valid Regular price to import.', 'authentype-font-specimen'), $variation_id);
            continue;
        }
        if ('' !== $sale_raw && ('' === $sale || (float) $sale <= 0 || (float) $sale >= (float) $regular)) {
            $blocked[] = sprintf(__('Woo variation #%d has an invalid Sale price relationship.', 'authentype-font-specimen'), $variation_id);
            continue;
        }
        $expected_active = '' !== $sale ? $sale : $regular;
        if ('' !== $active && abs((float) $active - (float) $expected_active) > 0.000001) {
            $blocked[] = sprintf(__('Woo variation #%d active price does not match its Regular/Sale values.', 'authentype-font-specimen'), $variation_id);
            continue;
        }

        if (!isset($planned[$style])) $planned[$style] = array();
        $planned[$style][$license] = array('regular' => (string) $regular, 'sale' => (string) $sale);
        $result['pairs'][$pair_key] = array(
            'variation_id' => $variation_id,
            'regular' => (string) $regular,
            'sale' => (string) $sale,
        );
        $result['counts']['pairs']++;
        if ('' !== $sale) $result['counts']['sales']++;
    }

    $result['blocked_reasons'] = array_values(array_unique(array_filter($blocked)));
    if (!empty($blocked)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $shown = array_slice($result['blocked_reasons'], 0, 3);
        $result['message'] = implode('; ', $shown);
        if (count($result['blocked_reasons']) > count($shown)) {
            $result['message'] .= sprintf(__('; +%d more', 'authentype-font-specimen'), count($result['blocked_reasons']) - count($shown));
        }
        return $result;
    }
    if (empty($planned)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['blocked_reasons'] = array(__('The product is marked Needs Pricing, but no completely empty delivery-backed price cell can be hydrated safely. Review its existing Price Matrix manually.', 'authentype-font-specimen'));
        $result['message'] = $result['blocked_reasons'][0];
        return $result;
    }

    $result['prices'] = $planned;
    $result['eligible'] = true;
    $result['status'] = 'eligible';
    $result['status_label'] = __('Eligible', 'authentype-font-specimen');
    $result['message'] = sprintf(
        __('Safe to copy exact live Woo pricing into %1$d empty Athtyp Style × License price cell(s). Existing Athtyp prices will not be overwritten.', 'authentype-font-specimen'),
        (int) $result['counts']['pairs']
    );
    return $result;
}

/**
 * Persist an append-only pre-pricing-hydration recovery snapshot.
 */
function ath_specimen_adoption_legacy_pricing_create_snapshot($font_id, $product_id, $matrix_raw) {
    $font_id = absint($font_id);
    $product_id = absint($product_id);
    if (!$font_id || !$product_id) {
        return new WP_Error('ath_pricing_hydrate_snapshot_target', __('Cannot create a pricing hydration snapshot without both Athtyp and Woo product IDs.', 'authentype-font-specimen'));
    }

    $meta_key = '_ath_legacy_pricing_hydration_snapshot';
    $existing_snapshots = get_post_meta($font_id, $meta_key, false);
    $version = 1;
    foreach ((array) $existing_snapshots as $snapshot) {
        if (!is_array($snapshot)) continue;
        $version = max($version, (int) ($snapshot['version'] ?? 0) + 1);
    }

    $capture_meta = function ($key) use ($font_id) {
        $exists = metadata_exists('post', $font_id, $key);
        return array('exists' => $exists ? 1 : 0, 'value' => $exists ? get_post_meta($font_id, $key, true) : null);
    };
    $snapshot_id = 'price-v' . $version . '-' . gmdate('YmdHis') . '-' . strtolower(wp_generate_password(8, false, false));
    $snapshot = array(
        'schema' => 1,
        'snapshot_id' => sanitize_key($snapshot_id),
        'version' => $version,
        'created_at' => time(),
        'created_at_gmt' => gmdate('c'),
        'user_id' => get_current_user_id(),
        'font_id' => $font_id,
        'woo_product_id' => $product_id,
        'price_matrix_meta_existed' => metadata_exists('post', $font_id, '_ath_price_matrix') ? 1 : 0,
        'price_matrix' => $matrix_raw,
        'pricing_hash_meta' => $capture_meta('_ath_pricing_hash'),
        'pricing_saved_at_meta' => $capture_meta('_ath_pricing_saved_at'),
        'pricing_needs_review_meta' => $capture_meta('_ath_pricing_needs_review'),
    );
    $snapshot['integrity_hash'] = hash('sha256', maybe_serialize(array(
        'price_matrix_exists' => $snapshot['price_matrix_meta_existed'],
        'price_matrix' => $snapshot['price_matrix'],
        'pricing_hash_meta' => $snapshot['pricing_hash_meta'],
        'pricing_saved_at_meta' => $snapshot['pricing_saved_at_meta'],
        'pricing_needs_review_meta' => $snapshot['pricing_needs_review_meta'],
    )));

    $meta_id = add_post_meta($font_id, $meta_key, $snapshot, false);
    if (!$meta_id) {
        return new WP_Error('ath_pricing_hydrate_snapshot_write', __('Pricing hydration was stopped because the pre-hydration safety snapshot could not be persisted.', 'authentype-font-specimen'));
    }

    update_post_meta($font_id, '_ath_legacy_pricing_hydration_snapshot_latest', array(
        'snapshot_id' => $snapshot['snapshot_id'],
        'version' => $version,
        'created_at' => $snapshot['created_at'],
        'woo_product_id' => $product_id,
        'meta_id' => (int) $meta_id,
        'integrity_hash' => $snapshot['integrity_hash'],
    ));

    return array(
        'snapshot_id' => $snapshot['snapshot_id'],
        'version' => $version,
        'meta_id' => (int) $meta_id,
    );
}

function ath_specimen_adoption_restore_pricing_meta_value($font_id, $key, $state) {
    $font_id = absint($font_id);
    if (!$font_id || !is_array($state)) return;
    if (!empty($state['exists'])) update_post_meta($font_id, $key, $state['value']);
    else delete_post_meta($font_id, $key);
}

function ath_specimen_adoption_legacy_pricing_hydrate($product_id) {
    $product_id = absint($product_id);
    if (!$product_id || !ath_specimen_adoption_capable() || !current_user_can('edit_post', $product_id)) {
        return new WP_Error('ath_pricing_hydrate_permission', __('Permission denied.', 'authentype-font-specimen'));
    }

    $mutex = 'ath_adopt_mutex_' . $product_id;
    $mutex_record = array('user' => get_current_user_id(), 'time' => time(), 'purpose' => 'legacy_pricing_hydration');
    if (!add_option($mutex, $mutex_record, '', false)) {
        $existing_mutex = get_option($mutex, array());
        if (is_array($existing_mutex) && !empty($existing_mutex['time']) && (int) $existing_mutex['time'] < time() - 10 * MINUTE_IN_SECONDS) delete_option($mutex);
        if (!add_option($mutex, $mutex_record, '', false)) {
            return new WP_Error('ath_pricing_hydrate_busy', __('This Woo product is already being processed by another adoption/restore/hydration request.', 'authentype-font-specimen'));
        }
    }

    try {
        $dataset = ath_specimen_adoption_load_dataset($product_id);
        if (is_wp_error($dataset)) return $dataset;
        $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
        if (is_wp_error($analysis)) return $analysis;
        $plan = ath_specimen_adoption_legacy_pricing_plan($product_id, $dataset, $analysis);
        if (empty($plan['eligible'])) {
            return new WP_Error('ath_pricing_hydrate_not_eligible', $plan['message'] ?: __('This product is no longer safe for legacy pricing hydration.', 'authentype-font-specimen'));
        }

        $font_id = absint($plan['font_id'] ?? 0);
        if (!$font_id || !current_user_can('edit_post', $font_id)) {
            return new WP_Error('ath_pricing_hydrate_font_permission', __('You cannot edit the linked Athtyp record.', 'authentype-font-specimen'));
        }
        if (function_exists('ath_specimen_stability_cross_engine_guard')) {
            $operation_guard = ath_specimen_stability_cross_engine_guard($font_id, $product_id, array('adoption'));
            if (is_wp_error($operation_guard)) return $operation_guard;
        }

        $woo_lock_key = '';
        $woo_lock_token = '';
        if (function_exists('ath_specimen_woo_sync_lock_key') && function_exists('ath_specimen_woo_sync_acquire_lock')) {
            $woo_lock_key = ath_specimen_woo_sync_lock_key($font_id, $product_id);
            $woo_lock_token = 'price-hydrate-' . wp_generate_password(32, false, false);
            if ($woo_lock_key && !ath_specimen_woo_sync_acquire_lock($woo_lock_key, $woo_lock_token, 10 * MINUTE_IN_SECONDS)) {
                return new WP_Error('ath_pricing_hydrate_sync_busy', __('Woo Sync is currently in progress for this product. Finish the sync before hydrating legacy pricing.', 'authentype-font-specimen'));
            }
        }

        try {
            $matrix_exists = metadata_exists('post', $font_id, '_ath_price_matrix');
            $matrix_raw = get_post_meta($font_id, '_ath_price_matrix', true);
            $matrix_raw = is_array($matrix_raw) ? $matrix_raw : array();
            $before_hash_state = array('exists' => metadata_exists('post', $font_id, '_ath_pricing_hash') ? 1 : 0, 'value' => get_post_meta($font_id, '_ath_pricing_hash', true));
            $before_saved_state = array('exists' => metadata_exists('post', $font_id, '_ath_pricing_saved_at') ? 1 : 0, 'value' => get_post_meta($font_id, '_ath_pricing_saved_at', true));
            $before_review_state = array('exists' => metadata_exists('post', $font_id, '_ath_pricing_needs_review') ? 1 : 0, 'value' => get_post_meta($font_id, '_ath_pricing_needs_review', true));

            $merged = $matrix_raw;
            foreach ((array) $plan['prices'] as $style => $licenses) {
                $style = ath_specimen_slug($style);
                if (!$style || !is_array($licenses)) continue;
                if (!isset($merged[$style]) || !is_array($merged[$style])) $merged[$style] = array();
                foreach ($licenses as $license => $price) {
                    $license = ath_specimen_slug($license);
                    if (!$license || !is_array($price)) continue;
                    $existing = function_exists('ath_specimen_matrix_price_values')
                        ? ath_specimen_matrix_price_values($merged, $style, $license)
                        : array('active' => '');
                    if ('' !== (string) ($existing['active'] ?? '')) {
                        return new WP_Error('ath_pricing_hydrate_collision', sprintf(__('Pricing hydration stopped because %s gained an Athtyp price after preview. Re-run Preview Missing Pricing.', 'authentype-font-specimen'), $style . '|' . $license));
                    }
                    $raw_cell = isset($merged[$style][$license]) && is_array($merged[$style][$license]) ? $merged[$style][$license] : array();
                    if ('' !== trim((string) ($raw_cell['regular'] ?? '')) || '' !== trim((string) ($raw_cell['sale'] ?? ''))) {
                        return new WP_Error('ath_pricing_hydrate_invalid_existing', sprintf(__('Pricing hydration stopped because %s now contains a non-empty existing price row.', 'authentype-font-specimen'), $style . '|' . $license));
                    }
                    $regular = function_exists('ath_specimen_sanitize_price_value') ? ath_specimen_sanitize_price_value($price['regular'] ?? '') : (string) ($price['regular'] ?? '');
                    $sale = function_exists('ath_specimen_sanitize_price_value') ? ath_specimen_sanitize_price_value($price['sale'] ?? '') : (string) ($price['sale'] ?? '');
                    if ('' === $regular || ('' !== $sale && ((float) $sale <= 0 || (float) $sale >= (float) $regular))) {
                        return new WP_Error('ath_pricing_hydrate_invalid_plan', sprintf(__('Pricing hydration stopped because the planned Woo price for %s is no longer valid.', 'authentype-font-specimen'), $style . '|' . $license));
                    }
                    $merged[$style][$license] = array('regular' => (string) $regular, 'sale' => (string) $sale);
                }
            }

            $snapshot = ath_specimen_adoption_legacy_pricing_create_snapshot($font_id, $product_id, $matrix_raw);
            if (is_wp_error($snapshot)) return $snapshot;

            // This is the only authoritative Price Matrix write in the migration.
            // Do not call the normal UI save path because it would conflate a
            // bulk legacy migration with an administrator review action.
            update_post_meta($font_id, '_ath_price_matrix', $merged);
            $pricing_hash = function_exists('ath_specimen_pricing_hash') ? ath_specimen_pricing_hash($merged) : hash('sha256', wp_json_encode($merged));
            update_post_meta($font_id, '_ath_pricing_hash', $pricing_hash);
            update_post_meta($font_id, '_ath_pricing_saved_at', time());

            // Clear Needs Review only after every delivery-backed purchasable
            // pair has an active price in the merged matrix. This does not mark
            // Woo as synced; the next readiness audit still verifies the exact
            // live mirror and can classify genuine drift as Needs Woo Sync.
            $styles = get_post_meta($font_id, '_ath_font_styles', true);
            $licenses = get_post_meta($font_id, '_ath_license_options', true);
            $downloads = get_post_meta($font_id, '_ath_product_downloads', true);
            $delivery_pairs = function_exists('ath_specimen_product_download_delivery_pairs')
                ? ath_specimen_product_download_delivery_pairs(is_array($downloads) ? $downloads : array())
                : array();
            $allowed_styles = array();
            foreach ((array) $styles as $row) {
                if (!is_array($row)) continue;
                $value = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : ath_specimen_slug($row['style_name'] ?? '');
                if ($value) $allowed_styles[$value] = true;
            }
            $allowed_licenses = array();
            $contact_licenses = array();
            foreach ((array) $licenses as $row) {
                if (!is_array($row) || empty($row['license_variation_value'])) continue;
                $value = ath_specimen_slug($row['license_variation_value']);
                if (!$value) continue;
                $allowed_licenses[$value] = true;
                if (function_exists('ath_specimen_license_is_contact_sales') && ath_specimen_license_is_contact_sales($row)) $contact_licenses[$value] = true;
            }
            $all_priced = true;
            foreach ((array) $delivery_pairs as $pair_key => $enabled) {
                if (!$enabled || false === strpos($pair_key, '|')) continue;
                list($style, $license) = array_map('ath_specimen_slug', explode('|', $pair_key, 2));
                if (!$style || !$license || empty($allowed_styles[$style]) || empty($allowed_licenses[$license]) || !empty($contact_licenses[$license])) continue;
                $price = function_exists('ath_specimen_matrix_price_values') ? ath_specimen_matrix_price_values($merged, $style, $license) : array('active' => '');
                if ('' === (string) ($price['active'] ?? '')) { $all_priced = false; break; }
            }
            if ($all_priced) delete_post_meta($font_id, '_ath_pricing_needs_review');

            $stored = get_post_meta($font_id, '_ath_price_matrix', true);
            $stored = is_array($stored) ? $stored : array();
            $verify_ok = true;
            foreach ((array) $plan['prices'] as $style => $licenses) {
                foreach ((array) $licenses as $license => $price) {
                    $expected = array(
                        'regular' => (string) (function_exists('ath_specimen_sanitize_price_value') ? ath_specimen_sanitize_price_value($price['regular'] ?? '') : ($price['regular'] ?? '')),
                        'sale' => (string) (function_exists('ath_specimen_sanitize_price_value') ? ath_specimen_sanitize_price_value($price['sale'] ?? '') : ($price['sale'] ?? '')),
                    );
                    $actual_price = function_exists('ath_specimen_matrix_price_values') ? ath_specimen_matrix_price_values($stored, $style, $license) : array('regular' => '', 'sale' => '');
                    if ((string) ($actual_price['regular'] ?? '') !== $expected['regular'] || (string) ($actual_price['sale'] ?? '') !== $expected['sale']) {
                        $verify_ok = false;
                        break 2;
                    }
                }
            }
            if (!$verify_ok) {
                if ($matrix_exists) update_post_meta($font_id, '_ath_price_matrix', $matrix_raw);
                else delete_post_meta($font_id, '_ath_price_matrix');
                ath_specimen_adoption_restore_pricing_meta_value($font_id, '_ath_pricing_hash', $before_hash_state);
                ath_specimen_adoption_restore_pricing_meta_value($font_id, '_ath_pricing_saved_at', $before_saved_state);
                ath_specimen_adoption_restore_pricing_meta_value($font_id, '_ath_pricing_needs_review', $before_review_state);
                return new WP_Error('ath_pricing_hydrate_verify', __('Athtyp pricing verification failed after hydration and the previous pricing metadata was restored.', 'authentype-font-specimen'));
            }

            update_post_meta($font_id, '_ath_adoption_legacy_pricing', '1');
            update_post_meta($font_id, '_ath_legacy_pricing_hydrated_at', time());
            update_post_meta($font_id, '_ath_legacy_pricing_hydrated_from_product', $product_id);
            update_post_meta($font_id, '_ath_legacy_pricing_hydrated_pair_count', (int) ($plan['counts']['pairs'] ?? 0));
            clean_post_cache($font_id);

            return array(
                'product_id' => $product_id,
                'font_id' => $font_id,
                'pairs' => (int) ($plan['counts']['pairs'] ?? 0),
                'sales' => (int) ($plan['counts']['sales'] ?? 0),
                'snapshot_id' => sanitize_key((string) ($snapshot['snapshot_id'] ?? '')),
                'snapshot_version' => (int) ($snapshot['version'] ?? 0),
                'message' => sprintf(
                    __('Hydrated %1$d empty Athtyp price cell(s) from exact live Woo pricing. Existing Athtyp prices and WooCommerce were not changed.', 'authentype-font-specimen'),
                    (int) ($plan['counts']['pairs'] ?? 0)
                ),
            );
        } finally {
            if ($woo_lock_key && $woo_lock_token && function_exists('ath_specimen_woo_sync_release_lock')) {
                ath_specimen_woo_sync_release_lock($woo_lock_key, $woo_lock_token);
            }
        }
    } finally {
        delete_option($mutex);
    }
}

function ath_specimen_adoption_legacy_pricing_plan_payload($plan) {
    return array(
        'product_id' => (int) ($plan['product_id'] ?? 0),
        'font_id' => (int) ($plan['font_id'] ?? 0),
        'eligible' => !empty($plan['eligible']),
        'status' => sanitize_key((string) ($plan['status'] ?? 'blocked')),
        'status_label' => sanitize_text_field((string) ($plan['status_label'] ?? '')),
        'readiness_status' => sanitize_key((string) ($plan['readiness_status'] ?? '')),
        'message' => sanitize_text_field((string) ($plan['message'] ?? '')),
        'product_name' => sanitize_text_field((string) (($plan['product_name'] ?? '') ?: get_the_title((int) ($plan['product_id'] ?? 0)))),
        'blocked_reasons' => array_values(array_slice(array_filter(array_map('sanitize_text_field', (array) (!empty($plan['blocked_reasons']) ? $plan['blocked_reasons'] : (!empty($plan['message']) ? array($plan['message']) : array())))), 0, 12)),
        'counts' => array(
            'pairs' => (int) ($plan['counts']['pairs'] ?? 0),
            'sales' => (int) ($plan['counts']['sales'] ?? 0),
            'expected_pairs' => (int) ($plan['counts']['expected_pairs'] ?? 0),
            'existing_priced' => (int) ($plan['counts']['existing_priced'] ?? 0),
        ),
    );
}


/**
 * Canonicalize only conservative legacy aliases used by old Woo catalogs.
 * This is deliberately not fuzzy matching: a stale value must still resolve
 * uniquely and its download signature must exactly equal the target Athtyp
 * delivery before any Woo mutation is allowed.
 */
function ath_specimen_adoption_reconcile_style_alias($value) {
    $value = ath_specimen_slug($value);
    if (!$value) return '';
    do {
        $before = $value;
        $value = preg_replace('/-(normal|roman|upright)$/', '', $value);
        $value = trim((string) $value, '-');
    } while ($value && $value !== $before);
    return $value;
}

function ath_specimen_adoption_reconcile_license_alias($value) {
    $value = ath_specimen_slug($value);
    if (!$value) return '';
    do {
        $before = $value;
        $value = preg_replace('/-(license|licence)$/', '', $value);
        $value = trim((string) $value, '-');
    } while ($value && $value !== $before);
    return $value;
}

function ath_specimen_adoption_reconcile_download_signature($downloads) {
    if (function_exists('ath_specimen_wc_download_signature')) {
        return ath_specimen_wc_download_signature((array) $downloads);
    }
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

function ath_specimen_adoption_reconcile_attribute_snapshot($attribute) {
    if (!$attribute || !is_object($attribute)) return array();
    return array(
        'id' => method_exists($attribute, 'get_id') ? (int) $attribute->get_id() : 0,
        'name' => method_exists($attribute, 'get_name') ? (string) $attribute->get_name() : '',
        'options' => method_exists($attribute, 'get_options') ? array_values((array) $attribute->get_options()) : array(),
        'position' => method_exists($attribute, 'get_position') ? (int) $attribute->get_position() : 0,
        'visible' => method_exists($attribute, 'get_visible') ? (bool) $attribute->get_visible() : true,
        'variation' => method_exists($attribute, 'get_variation') ? (bool) $attribute->get_variation() : true,
    );
}

/**
 * secure.8.2.28: Build a zero-write reconciliation plan for an already-linked
 * product whose only storefront drift can be repaired by reusing EXISTING Woo
 * variation IDs and EXISTING downloads. No variation is created/deleted, and a
 * candidate is rejected unless the current Woo download signature already
 * exactly matches the intended Athtyp delivery for the target pair.
 */
function ath_specimen_adoption_legacy_woo_reconcile_plan($product_id, $dataset = null, $analysis = null) {
    $product_id = absint($product_id);
    $result = array(
        'product_id' => $product_id,
        'font_id' => 0,
        'eligible' => false,
        'status' => 'skipped',
        'status_label' => __('Skipped', 'authentype-font-specimen'),
        'readiness_status' => '',
        'message' => '',
        'product_name' => '',
        'blocked_reasons' => array(),
        'actions' => array(),
        'final_styles' => array(),
        'final_licenses' => array(),
        'style_term_ids' => array(),
        'license_term_ids' => array(),
        'style_attr' => '',
        'license_attr' => '',
        'counts' => array('pairs' => 0, 'remaps' => 0, 'price_updates' => 0, 'unchanged' => 0),
    );
    if (!$product_id) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Woo product ID is missing.', 'authentype-font-specimen');
        return $result;
    }

    if (!$dataset) $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (!is_wp_error($dataset) && !empty($dataset['product']) && is_object($dataset['product']) && method_exists($dataset['product'], 'get_name')) {
        $result['product_name'] = sanitize_text_field((string) $dataset['product']->get_name());
    }
    if (is_wp_error($dataset)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = $dataset->get_error_message();
        return $result;
    }
    $product = $dataset['product'] ?? null;
    if (!$product || !is_object($product) || !method_exists($product, 'is_type') || !$product->is_type('variable')) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Legacy Woo reconciliation requires a variable product.', 'authentype-font-specimen');
        return $result;
    }
    if (!$analysis) $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
    if (is_wp_error($analysis)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = $analysis->get_error_message();
        return $result;
    }

    $readiness = ath_specimen_adoption_commerce_readiness($product_id, $dataset, $analysis);
    $result['readiness_status'] = sanitize_key((string) ($readiness['status'] ?? 'review'));
    if ('needs_sync' !== $result['readiness_status']) {
        $result['message'] = sprintf(
            __('Current commerce status is %s, so Legacy Woo Variation Reconciliation will not touch this product.', 'authentype-font-specimen'),
            (string) ($readiness['status_label'] ?? __('Review', 'authentype-font-specimen'))
        );
        return $result;
    }

    $font_id = absint($readiness['font_id'] ?? 0);
    $result['font_id'] = $font_id;
    if (!$font_id || !current_user_can('edit_post', $font_id) || !current_user_can('edit_post', $product_id)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('The linked Athtyp/Woo records cannot be edited by the current user.', 'authentype-font-specimen');
        return $result;
    }

    $linked_ids = get_posts(array(
        'post_type' => 'ath_font', 'post_status' => 'any', 'posts_per_page' => 3,
        'fields' => 'ids', 'no_found_rows' => true,
        'meta_key' => '_ath_linked_product', 'meta_value' => $product_id, 'meta_compare' => '=',
    ));
    $linked_ids = array_values(array_unique(array_map('intval', (array) $linked_ids)));
    if (1 !== count($linked_ids) || (int) $linked_ids[0] !== $font_id) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Reconciliation requires exactly one Athtyp record linked to this Woo product.', 'authentype-font-specimen');
        return $result;
    }
    $owner_meta = absint(get_post_meta($product_id, '_ath_athtyp_owner_post_id', true));
    if ($owner_meta && $owner_meta !== $font_id) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Woo ownership metadata points to a different Athtyp record.', 'authentype-font-specimen');
        return $result;
    }

    $style_attr = sanitize_title((string) get_post_meta($font_id, '_ath_style_attribute', true));
    $license_attr = sanitize_title((string) get_post_meta($font_id, '_ath_license_attribute', true));
    $result['style_attr'] = $style_attr;
    $result['license_attr'] = $license_attr;
    if (!$style_attr || !$license_attr || $style_attr === $license_attr ||
        0 !== strpos($style_attr, 'pa_') || 0 !== strpos($license_attr, 'pa_') ||
        empty($dataset['attributes'][$style_attr]) || empty($dataset['attributes'][$license_attr])) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Reconciliation requires the saved global Style/License attributes to match the live Woo product exactly.', 'authentype-font-specimen');
        return $result;
    }
    if (!empty($analysis['extra_dimensions']) || !empty($analysis['wildcard_variations']) || !empty($analysis['duplicate_pairs'])) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Woo variation structure is ambiguous. Extra dimensions, wildcard values, or duplicate Style × License pairs must be resolved first.', 'authentype-font-specimen');
        return $result;
    }

    $styles = get_post_meta($font_id, '_ath_font_styles', true);
    $licenses = get_post_meta($font_id, '_ath_license_options', true);
    $styles = is_array($styles) ? $styles : array();
    $licenses = is_array($licenses) ? $licenses : array();
    $allowed_styles = array();
    $style_order = array();
    foreach ($styles as $row) {
        if (!is_array($row)) continue;
        $value = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : ath_specimen_slug($row['style_name'] ?? '');
        if (!$value) continue;
        $allowed_styles[$value] = true;
        $style_order[] = $value;
    }
    $allowed_licenses = array();
    $contact_licenses = array();
    $license_order = array();
    foreach ($licenses as $row) {
        if (!is_array($row) || empty($row['license_variation_value'])) continue;
        $value = ath_specimen_slug($row['license_variation_value']);
        if (!$value) continue;
        $allowed_licenses[$value] = true;
        $license_order[] = $value;
        if (function_exists('ath_specimen_license_is_contact_sales') && ath_specimen_license_is_contact_sales($row)) $contact_licenses[$value] = true;
    }
    if (empty($allowed_styles) || empty($allowed_licenses)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Athtyp Style/License inventory is incomplete.', 'authentype-font-specimen');
        return $result;
    }

    $matrix = get_post_meta($font_id, '_ath_price_matrix', true);
    $matrix = is_array($matrix) ? $matrix : array();
    $download_rows = get_post_meta($font_id, '_ath_product_downloads', true);
    $download_rows = is_array($download_rows) ? $download_rows : array();
    $delivery_pairs = function_exists('ath_specimen_product_download_delivery_pairs')
        ? ath_specimen_product_download_delivery_pairs($download_rows) : array();

    $expected = array();
    foreach ((array) $delivery_pairs as $pair_key => $enabled) {
        if (!$enabled || false === strpos($pair_key, '|')) continue;
        list($style, $license) = array_map('ath_specimen_slug', explode('|', $pair_key, 2));
        if (!$style || !$license || empty($allowed_styles[$style]) || empty($allowed_licenses[$license]) || !empty($contact_licenses[$license])) continue;
        $price = function_exists('ath_specimen_matrix_price_values')
            ? ath_specimen_matrix_price_values($matrix, $style, $license)
            : array('regular' => '', 'sale' => '', 'active' => '');
        if ('' === (string) ($price['active'] ?? '')) continue;
        $downloads = function_exists('ath_specimen_build_wc_downloads')
            ? ath_specimen_build_wc_downloads($download_rows, $style, $license) : array();
        $signature = ath_specimen_adoption_reconcile_download_signature($downloads);
        if (empty($signature)) continue;
        $expected[$style . '|' . $license] = array(
            'style' => $style, 'license' => $license,
            'price' => $price, 'download_signature' => $signature,
        );
    }
    if (empty($expected)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('Athtyp has no fully priced, delivery-backed purchasable pairs to reconcile.', 'authentype-font-specimen');
        return $result;
    }

    $actual = array();
    foreach ((array) $dataset['variations'] as $row) {
        $attrs = (array) ($row['attributes'] ?? array());
        $style = isset($attrs[$style_attr]) ? ath_specimen_slug($attrs[$style_attr]) : '';
        $license = isset($attrs[$license_attr]) ? ath_specimen_slug($attrs[$license_attr]) : '';
        if (!$style || !$license) continue;
        $actual[$style . '|' . $license] = $row;
    }

    $blocked = array();
    $occupied_exact = array();
    foreach ($actual as $pair_key => $row) if (isset($expected[$pair_key])) $occupied_exact[$pair_key] = true;
    $assigned_targets = array();
    $final_pairs = array();
    $actions = array();

    foreach ($actual as $pair_key => $row) {
        $variation = $row['product'] ?? null;
        if (!$variation || !is_object($variation)) {
            $blocked[] = sprintf(__('Woo variation object is unavailable for %s.', 'authentype-font-specimen'), $pair_key);
            continue;
        }
        if ('publish' !== (string) ($row['status'] ?? '')) {
            $blocked[] = sprintf(__('Woo variation #%d is not published.', 'authentype-font-specimen'), (int) ($row['id'] ?? 0));
            continue;
        }
        if (!empty($row['sale_from']) || !empty($row['sale_to'])) {
            $blocked[] = sprintf(__('Woo variation #%d has scheduled sale dates; automatic price reconciliation is blocked.', 'authentype-font-specimen'), (int) ($row['id'] ?? 0));
            continue;
        }
        if (empty($row['downloadable']) || empty($row['downloads'])) {
            $blocked[] = sprintf(__('Woo variation #%d has no preserved downloadable file set.', 'authentype-font-specimen'), (int) ($row['id'] ?? 0));
            continue;
        }
        if ('1' === (string) $variation->get_meta('_ath_disabled_by_sync', true) || '1' === (string) $variation->get_meta('_ath_delivery_missing', true)) {
            $blocked[] = sprintf(__('Woo variation #%d is explicitly disabled/stale and will not be revived by this narrow reconciliation.', 'authentype-font-specimen'), (int) ($row['id'] ?? 0));
            continue;
        }
        $actual_signature = ath_specimen_adoption_reconcile_download_signature($variation->get_downloads('edit'));
        if (empty($actual_signature)) {
            $blocked[] = sprintf(__('Woo variation #%d has no stable download signature.', 'authentype-font-specimen'), (int) ($row['id'] ?? 0));
            continue;
        }

        $target_key = '';
        if (isset($expected[$pair_key])) {
            $target_key = $pair_key;
        } else {
            list($old_style, $old_license) = explode('|', $pair_key, 2);
            $style_alias = ath_specimen_adoption_reconcile_style_alias($old_style);
            $license_alias = ath_specimen_adoption_reconcile_license_alias($old_license);
            $candidates = array();
            foreach ($expected as $candidate_key => $candidate) {
                if (!empty($occupied_exact[$candidate_key]) || !empty($assigned_targets[$candidate_key])) continue;
                if (ath_specimen_adoption_reconcile_style_alias($candidate['style']) !== $style_alias) continue;
                if (ath_specimen_adoption_reconcile_license_alias($candidate['license']) !== $license_alias) continue;
                if ($candidate['download_signature'] !== $actual_signature) continue;
                $candidates[] = $candidate_key;
            }
            if (1 !== count($candidates)) {
                $blocked[] = sprintf(
                    0 === count($candidates)
                        ? __('Stale Woo pair %s has no unique Athtyp target with the same existing download IDs/files.', 'authentype-font-specimen')
                        : __('Stale Woo pair %s matches more than one Athtyp target; automatic reconciliation is ambiguous.', 'authentype-font-specimen'),
                    $pair_key
                );
                continue;
            }
            $target_key = reset($candidates);
        }

        if (empty($expected[$target_key])) {
            $blocked[] = sprintf(__('Target pair %s is not a current Athtyp purchasable pair.', 'authentype-font-specimen'), $target_key);
            continue;
        }
        if ($expected[$target_key]['download_signature'] !== $actual_signature) {
            $blocked[] = sprintf(__('Woo downloads for %s differ from Athtyp target %s; no-file reconciliation refuses to modify this variation.', 'authentype-font-specimen'), $pair_key, $target_key);
            continue;
        }
        if (!empty($assigned_targets[$target_key]) && (int) $assigned_targets[$target_key] !== (int) ($row['id'] ?? 0)) {
            $blocked[] = sprintf(__('More than one Woo variation would resolve to target pair %s.', 'authentype-font-specimen'), $target_key);
            continue;
        }
        $assigned_targets[$target_key] = (int) ($row['id'] ?? 0);
        $final_pairs[$target_key] = true;

        $target = $expected[$target_key];
        $remap = $pair_key !== $target_key;
        $price = $target['price'];
        $price_change = (string) ($row['regular_price'] ?? '') !== (string) ($price['regular'] ?? '') ||
            (string) ($row['sale_price'] ?? '') !== (string) ($price['sale'] ?? '') ||
            (string) ($row['price'] ?? '') !== (string) ($price['active'] ?? '');
        if ($remap || $price_change) {
            $actions[] = array(
                'variation_id' => (int) ($row['id'] ?? 0),
                'from_pair' => $pair_key,
                'to_pair' => $target_key,
                'from_style' => strtok($pair_key, '|'),
                'from_license' => substr($pair_key, strpos($pair_key, '|') + 1),
                'to_style' => $target['style'],
                'to_license' => $target['license'],
                'regular' => (string) ($price['regular'] ?? ''),
                'sale' => (string) ($price['sale'] ?? ''),
                'active' => (string) ($price['active'] ?? ''),
                'remap' => $remap ? 1 : 0,
                'price_change' => $price_change ? 1 : 0,
                'download_signature' => $actual_signature,
            );
            if ($remap) $result['counts']['remaps']++;
            if ($price_change) $result['counts']['price_updates']++;
        } else {
            $result['counts']['unchanged']++;
        }
    }

    $expected_keys = array_keys($expected);
    sort($expected_keys, SORT_STRING);
    $final_keys = array_keys($final_pairs);
    sort($final_keys, SORT_STRING);
    if ($expected_keys !== $final_keys) {
        $missing = array_values(array_diff($expected_keys, $final_keys));
        $extra = array_values(array_diff($final_keys, $expected_keys));
        if ($missing) $blocked[] = sprintf(__('Reconciliation would still leave missing Woo pair(s): %s', 'authentype-font-specimen'), implode(', ', array_slice($missing, 0, 6)));
        if ($extra) $blocked[] = sprintf(__('Reconciliation would leave unexpected Woo pair(s): %s', 'authentype-font-specimen'), implode(', ', array_slice($extra, 0, 6)));
    }

    $parent_attributes = $product->get_attributes();
    $style_attribute = $parent_attributes[$style_attr] ?? null;
    $license_attribute = $parent_attributes[$license_attr] ?? null;
    if (!$style_attribute || !$license_attribute || !is_object($style_attribute) || !is_object($license_attribute) ||
        !method_exists($style_attribute, 'get_variation') || !method_exists($license_attribute, 'get_variation') ||
        !$style_attribute->get_variation() || !$license_attribute->get_variation()) {
        $blocked[] = __('Parent Woo Style/License variation attributes are unavailable.', 'authentype-font-specimen');
    }

    $final_styles_lookup = array();
    $final_licenses_lookup = array();
    foreach ($final_keys as $pair_key) {
        list($style, $license) = explode('|', $pair_key, 2);
        $final_styles_lookup[$style] = true;
        $final_licenses_lookup[$license] = true;
    }
    foreach ($style_order as $value) if (!empty($final_styles_lookup[$value])) $result['final_styles'][] = $value;
    foreach ($license_order as $value) if (!empty($final_licenses_lookup[$value])) $result['final_licenses'][] = $value;

    foreach ($result['final_styles'] as $value) {
        $term = get_term_by('slug', $value, $style_attr);
        if (!$term || is_wp_error($term)) $blocked[] = sprintf(__('Target Style term %s does not exist in WooCommerce.', 'authentype-font-specimen'), $value);
        else $result['style_term_ids'][] = (int) $term->term_id;
    }
    foreach ($result['final_licenses'] as $value) {
        $term = get_term_by('slug', $value, $license_attr);
        if (!$term || is_wp_error($term)) $blocked[] = sprintf(__('Target License term %s does not exist in WooCommerce.', 'authentype-font-specimen'), $value);
        else $result['license_term_ids'][] = (int) $term->term_id;
    }

    $blocked = array_values(array_unique(array_filter(array_map('sanitize_text_field', $blocked))));
    if ($blocked) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['blocked_reasons'] = $blocked;
        $shown = array_slice($blocked, 0, 3);
        $result['message'] = implode('; ', $shown);
        if (count($blocked) > count($shown)) $result['message'] .= sprintf(__('; +%d more', 'authentype-font-specimen'), count($blocked) - count($shown));
        return $result;
    }
    if (empty($actions)) {
        $result['status'] = 'blocked';
        $result['status_label'] = __('Blocked', 'authentype-font-specimen');
        $result['message'] = __('The product is marked Needs Woo Sync, but no safe attribute/price-only reconciliation action was found.', 'authentype-font-specimen');
        return $result;
    }

    $result['counts']['pairs'] = count($actions);
    $result['actions'] = $actions;
    $result['eligible'] = true;
    $result['status'] = 'eligible';
    $result['status_label'] = __('Reconciliation Ready', 'authentype-font-specimen');
    $result['message'] = sprintf(
        __('Safe to reuse %1$d existing Woo variation(s): %2$d stale Style/License pair(s) will be remapped and %3$d variation price(s) will be mirrored. Downloads remain byte-for-byte untouched.', 'authentype-font-specimen'),
        count($actions), (int) $result['counts']['remaps'], (int) $result['counts']['price_updates']
    );
    return $result;
}

function ath_specimen_adoption_legacy_woo_reconcile_plan_payload($plan) {
    return array(
        'product_id' => (int) ($plan['product_id'] ?? 0),
        'font_id' => (int) ($plan['font_id'] ?? 0),
        'eligible' => !empty($plan['eligible']),
        'status' => sanitize_key((string) ($plan['status'] ?? 'blocked')),
        'status_label' => sanitize_text_field((string) ($plan['status_label'] ?? '')),
        'readiness_status' => sanitize_key((string) ($plan['readiness_status'] ?? '')),
        'message' => sanitize_text_field((string) ($plan['message'] ?? '')),
        'product_name' => sanitize_text_field((string) (($plan['product_name'] ?? '') ?: get_the_title((int) ($plan['product_id'] ?? 0)))),
        'blocked_reasons' => array_values(array_slice(array_filter(array_map('sanitize_text_field', (array) (!empty($plan['blocked_reasons']) ? $plan['blocked_reasons'] : (!empty($plan['message']) ? array($plan['message']) : array())))), 0, 12)),
        'counts' => array(
            'pairs' => (int) ($plan['counts']['pairs'] ?? 0),
            'remaps' => (int) ($plan['counts']['remaps'] ?? 0),
            'price_updates' => (int) ($plan['counts']['price_updates'] ?? 0),
            'unchanged' => (int) ($plan['counts']['unchanged'] ?? 0),
        ),
    );
}

function ath_specimen_adoption_legacy_woo_reconcile_create_snapshot($font_id, $product_id, $plan, $dataset) {
    $font_id = absint($font_id);
    $product_id = absint($product_id);
    if (!$font_id || !$product_id) return new WP_Error('ath_reconcile_snapshot_target', __('Cannot create a reconciliation snapshot without Athtyp and Woo product IDs.', 'authentype-font-specimen'));

    $meta_key = '_ath_legacy_woo_reconcile_snapshot';
    $existing = get_post_meta($font_id, $meta_key, false);
    $version = 1;
    foreach ((array) $existing as $row) if (is_array($row)) $version = max($version, (int) ($row['version'] ?? 0) + 1);

    $product = $dataset['product'] ?? null;
    $attributes = $product && method_exists($product, 'get_attributes') ? $product->get_attributes() : array();
    $style_attr = (string) ($plan['style_attr'] ?? '');
    $license_attr = (string) ($plan['license_attr'] ?? '');
    $style_terms = wp_get_object_terms($product_id, $style_attr, array('fields' => 'slugs'));
    $license_terms = wp_get_object_terms($product_id, $license_attr, array('fields' => 'slugs'));
    if (is_wp_error($style_terms) || is_wp_error($license_terms)) return new WP_Error('ath_reconcile_snapshot_terms', __('Could not capture the current Woo Style/License terms before reconciliation.', 'authentype-font-specimen'));

    $variations = array();
    foreach ((array) ($plan['actions'] ?? array()) as $action) {
        $variation = wc_get_product((int) ($action['variation_id'] ?? 0));
        if (!$variation) return new WP_Error('ath_reconcile_snapshot_variation', __('A target Woo variation disappeared before the safety snapshot could be created.', 'authentype-font-specimen'));
        $variations[] = array(
            'variation_id' => (int) $variation->get_id(),
            'attributes' => (array) $variation->get_attributes('edit'),
            'regular_price' => (string) $variation->get_regular_price('edit'),
            'sale_price' => (string) $variation->get_sale_price('edit'),
            'price' => (string) $variation->get_price('edit'),
            'status' => (string) $variation->get_status('edit'),
            'downloadable' => (bool) $variation->get_downloadable('edit'),
            'download_signature' => ath_specimen_adoption_reconcile_download_signature($variation->get_downloads('edit')),
        );
    }

    $snapshot_id = 'woo-rec-v' . $version . '-' . gmdate('YmdHis') . '-' . strtolower(wp_generate_password(8, false, false));
    $snapshot = array(
        'schema' => 1, 'snapshot_id' => sanitize_key($snapshot_id), 'version' => $version,
        'created_at' => time(), 'created_at_gmt' => gmdate('c'), 'user_id' => get_current_user_id(),
        'font_id' => $font_id, 'woo_product_id' => $product_id,
        'style_attr' => $style_attr, 'license_attr' => $license_attr,
        'style_terms' => array_values((array) $style_terms), 'license_terms' => array_values((array) $license_terms),
        'style_attribute' => ath_specimen_adoption_reconcile_attribute_snapshot($attributes[$style_attr] ?? null),
        'license_attribute' => ath_specimen_adoption_reconcile_attribute_snapshot($attributes[$license_attr] ?? null),
        'variations' => $variations,
    );
    $snapshot['integrity_hash'] = hash('sha256', maybe_serialize($snapshot));
    $meta_id = add_post_meta($font_id, $meta_key, $snapshot, false);
    if (!$meta_id) return new WP_Error('ath_reconcile_snapshot_write', __('Reconciliation was stopped because the pre-change Woo safety snapshot could not be persisted.', 'authentype-font-specimen'));
    update_post_meta($font_id, '_ath_legacy_woo_reconcile_snapshot_latest', array(
        'snapshot_id' => $snapshot['snapshot_id'], 'version' => $version, 'created_at' => $snapshot['created_at'],
        'woo_product_id' => $product_id, 'meta_id' => (int) $meta_id, 'integrity_hash' => $snapshot['integrity_hash'],
    ));
    return array('snapshot_id' => $snapshot['snapshot_id'], 'version' => $version, 'meta_id' => (int) $meta_id, 'snapshot' => $snapshot);
}

function ath_specimen_adoption_reconcile_restore_attribute($product_id, $taxonomy, $attribute_snapshot, $term_slugs) {
    $product = wc_get_product($product_id);
    if (!$product) return false;
    $set = wp_set_object_terms($product_id, array_values((array) $term_slugs), $taxonomy, false);
    if (is_wp_error($set)) return false;
    $attrs = $product->get_attributes();
    $attribute = new WC_Product_Attribute();
    $attribute->set_id((int) ($attribute_snapshot['id'] ?? 0));
    $attribute->set_name((string) ($attribute_snapshot['name'] ?? $taxonomy));
    $attribute->set_options(array_values((array) ($attribute_snapshot['options'] ?? array())));
    $attribute->set_position((int) ($attribute_snapshot['position'] ?? 0));
    $attribute->set_visible(!empty($attribute_snapshot['visible']));
    $attribute->set_variation(!empty($attribute_snapshot['variation']));
    $attrs[$taxonomy] = $attribute;
    $product->set_attributes($attrs);
    return (bool) $product->save();
}

function ath_specimen_adoption_legacy_woo_reconcile_rollback($product_id, $snapshot) {
    $ok = true;
    foreach ((array) ($snapshot['variations'] ?? array()) as $row) {
        $variation = wc_get_product((int) ($row['variation_id'] ?? 0));
        if (!$variation) { $ok = false; continue; }
        $before_downloads = ath_specimen_adoption_reconcile_download_signature($variation->get_downloads('edit'));
        $variation->set_attributes((array) ($row['attributes'] ?? array()));
        $variation->set_regular_price((string) ($row['regular_price'] ?? ''));
        $variation->set_sale_price((string) ($row['sale_price'] ?? ''));
        $variation->set_price((string) ($row['price'] ?? ''));
        if (!$variation->save()) $ok = false;
        $restored = wc_get_product((int) ($row['variation_id'] ?? 0));
        if (!$restored || ath_specimen_adoption_reconcile_download_signature($restored->get_downloads('edit')) !== (array) ($row['download_signature'] ?? array())) $ok = false;
        // Downloads are never intentionally written. This additional check flags
        // an unexpected third-party mutation during rollback rather than hiding it.
        if ($before_downloads !== (array) ($row['download_signature'] ?? array())) $ok = false;
    }
    if (!ath_specimen_adoption_reconcile_restore_attribute($product_id, (string) ($snapshot['style_attr'] ?? ''), (array) ($snapshot['style_attribute'] ?? array()), (array) ($snapshot['style_terms'] ?? array()))) $ok = false;
    if (!ath_specimen_adoption_reconcile_restore_attribute($product_id, (string) ($snapshot['license_attr'] ?? ''), (array) ($snapshot['license_attribute'] ?? array()), (array) ($snapshot['license_terms'] ?? array()))) $ok = false;
    if (class_exists('WC_Product_Variable') && method_exists('WC_Product_Variable', 'sync')) WC_Product_Variable::sync($product_id);
    if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($product_id);
    return $ok;
}

function ath_specimen_adoption_legacy_woo_reconcile($product_id) {
    $product_id = absint($product_id);
    if (!$product_id || !ath_specimen_adoption_capable() || !current_user_can('edit_post', $product_id)) return new WP_Error('ath_reconcile_permission', __('Permission denied.', 'authentype-font-specimen'));

    $mutex = 'ath_adopt_mutex_' . $product_id;
    $mutex_record = array('user' => get_current_user_id(), 'time' => time(), 'purpose' => 'legacy_woo_reconciliation');
    if (!add_option($mutex, $mutex_record, '', false)) {
        $existing_mutex = get_option($mutex, array());
        if (is_array($existing_mutex) && !empty($existing_mutex['time']) && (int) $existing_mutex['time'] < time() - 10 * MINUTE_IN_SECONDS) delete_option($mutex);
        if (!add_option($mutex, $mutex_record, '', false)) return new WP_Error('ath_reconcile_busy', __('This Woo product is already being processed by another migration request.', 'authentype-font-specimen'));
    }

    try {
        $dataset = ath_specimen_adoption_load_dataset($product_id);
        if (is_wp_error($dataset)) return $dataset;
        $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
        if (is_wp_error($analysis)) return $analysis;
        $plan = ath_specimen_adoption_legacy_woo_reconcile_plan($product_id, $dataset, $analysis);
        if (empty($plan['eligible'])) return new WP_Error('ath_reconcile_not_eligible', $plan['message'] ?: __('This product is no longer safe for reconciliation.', 'authentype-font-specimen'));

        $font_id = absint($plan['font_id'] ?? 0);
        if (!$font_id || !current_user_can('edit_post', $font_id)) return new WP_Error('ath_reconcile_font_permission', __('You cannot edit the linked Athtyp record.', 'authentype-font-specimen'));
        if (function_exists('ath_specimen_stability_cross_engine_guard')) {
            $operation_guard = ath_specimen_stability_cross_engine_guard($font_id, $product_id, array('adoption'));
            if (is_wp_error($operation_guard)) return $operation_guard;
        }

        $woo_lock_key = '';
        $woo_lock_token = '';
        if (function_exists('ath_specimen_woo_sync_lock_key') && function_exists('ath_specimen_woo_sync_acquire_lock')) {
            $woo_lock_key = ath_specimen_woo_sync_lock_key($font_id, $product_id);
            $woo_lock_token = 'reconcile-' . wp_generate_password(32, false, false);
            if ($woo_lock_key && !ath_specimen_woo_sync_acquire_lock($woo_lock_key, $woo_lock_token, 10 * MINUTE_IN_SECONDS)) {
                return new WP_Error('ath_reconcile_sync_busy', __('Woo Sync is currently in progress for this product. Finish it before reconciliation.', 'authentype-font-specimen'));
            }
        }

        try {
            // Re-read and re-plan after both locks. Preview is never write authority.
            $dataset = ath_specimen_adoption_load_dataset($product_id);
            if (is_wp_error($dataset)) return $dataset;
            $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
            if (is_wp_error($analysis)) return $analysis;
            $plan = ath_specimen_adoption_legacy_woo_reconcile_plan($product_id, $dataset, $analysis);
            if (empty($plan['eligible'])) return new WP_Error('ath_reconcile_changed', $plan['message'] ?: __('The product changed after preview and is no longer safe to reconcile.', 'authentype-font-specimen'));

            $snapshot_info = ath_specimen_adoption_legacy_woo_reconcile_create_snapshot($font_id, $product_id, $plan, $dataset);
            if (is_wp_error($snapshot_info)) return $snapshot_info;
            $snapshot = (array) ($snapshot_info['snapshot'] ?? array());
            $failure = null;

            foreach ((array) $plan['actions'] as $action) {
                $variation_id = absint($action['variation_id'] ?? 0);
                $variation = wc_get_product($variation_id);
                if (!$variation || (int) $variation->get_parent_id() !== $product_id) { $failure = __('A target variation disappeared or changed parent.', 'authentype-font-specimen'); break; }
                $downloads_before = ath_specimen_adoption_reconcile_download_signature($variation->get_downloads('edit'));
                if ($downloads_before !== (array) ($action['download_signature'] ?? array())) { $failure = __('A Woo download changed after preview; reconciliation stopped before touching that variation.', 'authentype-font-specimen'); break; }

                $attrs = (array) $variation->get_attributes('edit');
                $attrs[$plan['style_attr']] = (string) $action['to_style'];
                $attrs[$plan['license_attr']] = (string) $action['to_license'];
                $variation->set_attributes($attrs);
                $variation->set_regular_price((string) $action['regular']);
                $variation->set_sale_price((string) $action['sale']);
                $variation->set_price((string) $action['active']);
                $saved_id = $variation->save();
                if ((int) $saved_id !== $variation_id) { $failure = __('WooCommerce did not preserve the existing variation ID.', 'authentype-font-specimen'); break; }

                $verify = wc_get_product($variation_id);
                if (!$verify) { $failure = __('Woo variation could not be reloaded after reconciliation.', 'authentype-font-specimen'); break; }
                $verify_attrs = (array) $verify->get_attributes('edit');
                $verify_style = isset($verify_attrs[$plan['style_attr']]) ? ath_specimen_slug($verify_attrs[$plan['style_attr']]) : '';
                $verify_license = isset($verify_attrs[$plan['license_attr']]) ? ath_specimen_slug($verify_attrs[$plan['license_attr']]) : '';
                if ($verify_style !== (string) $action['to_style'] || $verify_license !== (string) $action['to_license'] ||
                    (string) $verify->get_regular_price('edit') !== (string) $action['regular'] ||
                    (string) $verify->get_sale_price('edit') !== (string) $action['sale'] ||
                    (string) $verify->get_price('edit') !== (string) $action['active']) {
                    $failure = __('Variation attribute/price verification failed.', 'authentype-font-specimen'); break;
                }
                if (ath_specimen_adoption_reconcile_download_signature($verify->get_downloads('edit')) !== $downloads_before || !$verify->get_downloadable('edit')) {
                    $failure = __('Download preservation verification failed; reconciliation was rolled back.', 'authentype-font-specimen'); break;
                }
            }

            if (!$failure && (int) ($plan['counts']['remaps'] ?? 0) > 0) {
                $set_styles = wp_set_object_terms($product_id, array_values((array) $plan['final_styles']), (string) $plan['style_attr'], false);
                $set_licenses = wp_set_object_terms($product_id, array_values((array) $plan['final_licenses']), (string) $plan['license_attr'], false);
                if (is_wp_error($set_styles) || is_wp_error($set_licenses)) {
                    $failure = __('Parent Woo Style/License term reconciliation failed.', 'authentype-font-specimen');
                } else {
                    $parent = wc_get_product($product_id);
                    $attrs = $parent ? $parent->get_attributes() : array();
                    $style_existing = $attrs[$plan['style_attr']] ?? null;
                    $license_existing = $attrs[$plan['license_attr']] ?? null;
                    if (!$parent || !$style_existing || !$license_existing) {
                        $failure = __('Parent Woo variation attributes disappeared during reconciliation.', 'authentype-font-specimen');
                    } else {
                        $style_existing->set_options(array_values((array) $plan['style_term_ids']));
                        $license_existing->set_options(array_values((array) $plan['license_term_ids']));
                        $attrs[$plan['style_attr']] = $style_existing;
                        $attrs[$plan['license_attr']] = $license_existing;
                        $parent->set_attributes($attrs);
                        if (!$parent->save()) $failure = __('Parent Woo Style/License attributes could not be saved.', 'authentype-font-specimen');
                    }
                }
            }

            if (!$failure) {
                if (class_exists('WC_Product_Variable') && method_exists('WC_Product_Variable', 'sync')) WC_Product_Variable::sync($product_id);
                if (function_exists('wc_delete_product_transients')) wc_delete_product_transients($product_id);

                $verify_dataset = ath_specimen_adoption_load_dataset($product_id);
                if (is_wp_error($verify_dataset)) {
                    $failure = __('Final Woo dataset verification failed.', 'authentype-font-specimen');
                } else {
                    $verify_actual = array();
                    foreach ((array) $verify_dataset['variations'] as $row) {
                        $a = (array) ($row['attributes'] ?? array());
                        $s = isset($a[$plan['style_attr']]) ? ath_specimen_slug($a[$plan['style_attr']]) : '';
                        $l = isset($a[$plan['license_attr']]) ? ath_specimen_slug($a[$plan['license_attr']]) : '';
                        if ($s && $l) $verify_actual[$s . '|' . $l] = $row;
                    }
                    foreach ((array) $plan['actions'] as $action) {
                        $target_key = (string) $action['to_pair'];
                        $row = $verify_actual[$target_key] ?? null;
                        if (!$row || (int) ($row['id'] ?? 0) !== (int) ($action['variation_id'] ?? 0)) { $failure = __('Final variation pair verification failed.', 'authentype-font-specimen'); break; }
                        $v = $row['product'] ?? null;
                        if (!$v || ath_specimen_adoption_reconcile_download_signature($v->get_downloads('edit')) !== (array) ($action['download_signature'] ?? array())) { $failure = __('Final download-preservation verification failed.', 'authentype-font-specimen'); break; }
                    }
                }
            }

            if ($failure) {
                $rolled_back = ath_specimen_adoption_legacy_woo_reconcile_rollback($product_id, $snapshot);
                return new WP_Error('ath_reconcile_verify', $failure . ($rolled_back ? ' ' . __('The pre-change Woo snapshot was restored.', 'authentype-font-specimen') : ' ' . __('Automatic rollback could not be fully verified; use the saved snapshot for recovery.', 'authentype-font-specimen')));
            }

            update_post_meta($font_id, '_ath_legacy_woo_reconciled_at', time());
            update_post_meta($font_id, '_ath_legacy_woo_reconciled_from_product', $product_id);
            clean_post_cache($font_id);
            return array(
                'product_id' => $product_id, 'font_id' => $font_id,
                'pairs' => (int) ($plan['counts']['pairs'] ?? 0),
                'remaps' => (int) ($plan['counts']['remaps'] ?? 0),
                'price_updates' => (int) ($plan['counts']['price_updates'] ?? 0),
                'snapshot_id' => sanitize_key((string) ($snapshot_info['snapshot_id'] ?? '')),
                'snapshot_version' => (int) ($snapshot_info['version'] ?? 0),
                'message' => sprintf(
                    __('Reconciled %1$d existing Woo variation(s): %2$d Style/License remap(s), %3$d price update(s). Variation IDs and all download IDs/names/files were preserved.', 'authentype-font-specimen'),
                    (int) ($plan['counts']['pairs'] ?? 0), (int) ($plan['counts']['remaps'] ?? 0), (int) ($plan['counts']['price_updates'] ?? 0)
                ),
            );
        } finally {
            if ($woo_lock_key && $woo_lock_token && function_exists('ath_specimen_woo_sync_release_lock')) ath_specimen_woo_sync_release_lock($woo_lock_key, $woo_lock_token);
        }
    } finally {
        delete_option($mutex);
    }
}


function ath_specimen_adoption_readiness_ajax_payload($result) {
    return array(
        'product_id' => (int) ($result['product_id'] ?? 0),
        'font_id' => (int) ($result['font_id'] ?? 0),
        'status' => sanitize_key((string) ($result['status'] ?? 'review')),
        'status_label' => sanitize_text_field((string) ($result['status_label'] ?? '')),
        'message' => sanitize_text_field((string) ($result['message'] ?? '')),
        'counts' => array(
            'expected_pairs' => (int) ($result['counts']['expected_pairs'] ?? 0),
            'matched_pairs' => (int) ($result['counts']['matched_pairs'] ?? 0),
            'woo_variations' => (int) ($result['counts']['woo_variations'] ?? 0),
        ),
    );
}

function ath_specimen_adoption_status_class($status, $bulk_ready = null) {
    if ('adopted' === $status) return 'is-good';
    if ('compatible' === $status) return false === $bulk_ready ? 'is-warning' : 'is-good';
    if (in_array($status, array('needs_mapping', 'needs_global_attributes', 'needs_existing_match', 'simple'), true)) return 'is-warning';
    return 'is-bad';
}


add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=ath_font',
        __('Woo Catalog', 'authentype-font-specimen'),
        __('Woo Catalog', 'authentype-font-specimen'),
        'manage_options',
        'ath-catalog-adoption',
        'ath_specimen_render_catalog_adoption_page'
    );
});


/**
 * Read-only inspector helpers for existing WooCommerce source downloads.
 * These functions never alter Woo products, Athtyp records, downloads, orders,
 * or customer permissions. Local files are resolved only inside wp-content/uploads.
 */
function ath_specimen_adoption_source_file_ref($variation_id, $download) {
    $variation_id = absint($variation_id);
    $id = !empty($download['id']) ? sanitize_text_field((string) $download['id']) : '';
    if ($id) return $id;
    return 'file-' . substr(hash('sha256', $variation_id . '|' . (string) ($download['file'] ?? '') . '|' . (string) ($download['name'] ?? '')), 0, 24);
}

function ath_specimen_adoption_source_is_upload_url($file) {
    $file = trim((string) $file);
    if (!$file || !filter_var($file, FILTER_VALIDATE_URL)) return false;
    $uploads = wp_get_upload_dir();
    if (empty($uploads['baseurl'])) return false;
    $a = wp_parse_url($file);
    $b = wp_parse_url($uploads['baseurl']);
    if (!is_array($a) || !is_array($b) || empty($a['host']) || empty($b['host'])) return false;
    if (strtolower($a['host']) !== strtolower($b['host'])) return false;
    if (isset($a['port']) !== isset($b['port']) || (isset($a['port']) && (int) $a['port'] !== (int) $b['port'])) return false;
    $ap = isset($a['path']) ? rawurldecode((string) $a['path']) : '';
    $bp = isset($b['path']) ? rtrim(rawurldecode((string) $b['path']), '/') : '';
    return $bp && ($ap === $bp || 0 === strpos($ap, $bp . '/'));
}

function ath_specimen_adoption_source_zip_summary($path) {
    $result = array('entries' => 0, 'fonts' => 0, 'font_entries' => array(), 'truncated' => false);
    if (!$path || !class_exists('ZipArchive') || 'zip' !== strtolower(pathinfo($path, PATHINFO_EXTENSION))) return $result;
    $zip = new ZipArchive();
    if (true !== $zip->open($path, ZipArchive::RDONLY)) return $result;
    $result['entries'] = (int) $zip->numFiles;
    $max_scan = min((int) $zip->numFiles, 5000);
    $max_names = 30;
    for ($i = 0; $i < $max_scan; $i++) {
        $stat = $zip->statIndex($i);
        if (!$stat || empty($stat['name'])) continue;
        $name = str_replace('\\', '/', (string) $stat['name']);
        if ('/' === substr($name, -1)) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, array('otf', 'ttf', 'woff', 'woff2'), true)) continue;
        $result['fonts']++;
        if (count($result['font_entries']) < $max_names) $result['font_entries'][] = $name;
    }
    if ((int) $zip->numFiles > $max_scan || $result['fonts'] > count($result['font_entries'])) $result['truncated'] = true;
    $zip->close();
    return $result;
}

function ath_specimen_adoption_source_file_info($file) {
    static $cache = array();
    $file = is_scalar($file) ? trim((string) $file) : '';
    if ($file && isset($cache[$file])) return $cache[$file];
    $info = array(
        'file' => $file,
        'local_path' => '',
        'status' => 'unavailable',
        'status_label' => __('Unavailable', 'authentype-font-specimen'),
        'size' => 0,
        'extension' => '',
        'filename' => '',
        'zip' => array('entries' => 0, 'fonts' => 0, 'font_entries' => array(), 'truncated' => false),
        'can_download' => false,
        'remote' => false,
    );
    if (!$file) return $info;

    $parsed_path = filter_var($file, FILTER_VALIDATE_URL) ? (string) (wp_parse_url($file, PHP_URL_PATH) ?: '') : $file;
    $info['filename'] = sanitize_file_name(wp_basename(rawurldecode($parsed_path)));
    if (!$info['filename']) $info['filename'] = __('Woo download file', 'authentype-font-specimen');
    $info['extension'] = strtolower(pathinfo($info['filename'], PATHINFO_EXTENSION));

    $local = function_exists('ath_specimen_local_upload_path') ? ath_specimen_local_upload_path($file) : '';
    if ($local) {
        $info['local_path'] = $local;
        $info['status'] = 'found';
        $info['status_label'] = __('Found', 'authentype-font-specimen');
        $size = @filesize($local);
        $info['size'] = false === $size ? 0 : max(0, (int) $size);
        $info['can_download'] = true;
        $info['zip'] = ath_specimen_adoption_source_zip_summary($local);
        $cache[$file] = $info;
        return $info;
    }

    if (ath_specimen_adoption_source_is_upload_url($file)) {
        $info['status'] = 'missing';
        $info['status_label'] = __('Missing on disk', 'authentype-font-specimen');
        $cache[$file] = $info;
        return $info;
    }

    if (filter_var($file, FILTER_VALIDATE_URL)) {
        $parts = wp_parse_url($file);
        if (is_array($parts) && !empty($parts['scheme']) && in_array(strtolower($parts['scheme']), array('http', 'https'), true) && empty($parts['user']) && empty($parts['pass'])) {
            $info['status'] = 'remote';
            $info['status_label'] = __('Remote source', 'authentype-font-specimen');
            $info['remote'] = true;
            $info['can_download'] = true;
        }
    }
    if ($file) $cache[$file] = $info;
    return $info;
}

function ath_specimen_adoption_source_rows($product_id, $dataset = null, $analysis = null) {
    $product_id = absint($product_id);
    if (!$dataset) $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) return $dataset;
    if (!$analysis) $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
    if (is_wp_error($analysis)) return $analysis;

    $rows = array();
    $style_attr = sanitize_title((string) ($analysis['style_attr'] ?? ''));
    $license_attr = sanitize_title((string) ($analysis['license_attr'] ?? ''));
    $product = $dataset['product'];

    // Parent-level Woo downloads are also valid source references and can be
    // especially useful for old/simple products.
    foreach ((array) $product->get_downloads('edit') as $download) {
        $record = ath_specimen_adoption_download_record($download);
        if (!$record || empty($record['file'])) continue;
        $rows[] = array(
            'variation_id' => 0,
            'style' => '',
            'style_label' => __('Parent product', 'authentype-font-specimen'),
            'license' => '',
            'license_label' => '—',
            'download' => $record,
            'ref' => ath_specimen_adoption_source_file_ref(0, $record),
            'info' => ath_specimen_adoption_source_file_info($record['file']),
        );
    }

    foreach ((array) $dataset['variations'] as $variation) {
        $attrs = (array) ($variation['attributes'] ?? array());
        $style = $style_attr && isset($attrs[$style_attr]) ? ath_specimen_slug($attrs[$style_attr]) : '';
        $license = $license_attr && isset($attrs[$license_attr]) ? ath_specimen_slug($attrs[$license_attr]) : '';
        $style_label = $style ? ath_specimen_adoption_term_label($style_attr, $style) : '—';
        $license_label = $license ? ath_specimen_adoption_term_label($license_attr, $license) : '—';
        foreach ((array) ($variation['downloads'] ?? array()) as $record) {
            if (!is_array($record) || empty($record['file'])) continue;
            $rows[] = array(
                'variation_id' => (int) ($variation['id'] ?? 0),
                'style' => $style,
                'style_label' => $style_label,
                'license' => $license,
                'license_label' => $license_label,
                'download' => $record,
                'ref' => ath_specimen_adoption_source_file_ref((int) ($variation['id'] ?? 0), $record),
                'info' => ath_specimen_adoption_source_file_info($record['file']),
            );
        }
    }
    return $rows;
}

/**
 * Group the exact Woo download rows by the product/variation that grants them.
 * This is the closest admin-side representation of "what the buyer receives":
 * the same Woo download IDs/names/files attached to the purchased variation.
 * No product data is changed.
 */
function ath_specimen_adoption_buyer_delivery_groups($rows) {
    $groups = array();
    foreach ((array) $rows as $row) {
        $variation_id = absint($row['variation_id'] ?? 0);
        $key = $variation_id ? 'variation-' . $variation_id : 'parent';
        if (!isset($groups[$key])) {
            $groups[$key] = array(
                'variation_id' => $variation_id,
                'style_label' => (string) ($row['style_label'] ?? '—'),
                'license_label' => (string) ($row['license_label'] ?? '—'),
                'rows' => array(),
                'local_count' => 0,
                'remote_count' => 0,
                'missing_count' => 0,
            );
        }
        $groups[$key]['rows'][] = $row;
        $status = (string) ($row['info']['status'] ?? 'unavailable');
        if ('found' === $status) $groups[$key]['local_count']++;
        elseif ('remote' === $status) $groups[$key]['remote_count']++;
        else $groups[$key]['missing_count']++;
    }
    return array_values($groups);
}

function ath_specimen_adoption_buyer_delivery_stream_local($path, $filename, $content_type = 'application/octet-stream') {
    $path = (string) $path;
    if (!$path || !is_file($path) || !is_readable($path)) {
        wp_die(esc_html__('The buyer delivery file could not be read.', 'authentype-font-specimen'), '', array('response' => 404));
    }
    $filename = sanitize_file_name((string) $filename);
    if (!$filename) $filename = sanitize_file_name(wp_basename($path));
    if (!$filename) $filename = 'woo-buyer-delivery';
    $size = @filesize($path);
    while (ob_get_level()) @ob_end_clean();
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    if (false !== $size) header('Content-Length: ' . (int) $size);
    $handle = @fopen($path, 'rb');
    if (!$handle) wp_die(esc_html__('The buyer delivery file could not be opened.', 'authentype-font-specimen'), '', array('response' => 500));
    fpassthru($handle);
    fclose($handle);
    exit;
}

function ath_specimen_adoption_buyer_delivery_filename($record, $local_path, &$used) {
    $actual_ext = strtolower(pathinfo((string) $local_path, PATHINFO_EXTENSION));
    $base = sanitize_file_name((string) ($record['name'] ?? ''));
    if (!$base) $base = sanitize_file_name(wp_basename((string) $local_path));
    if ($actual_ext && strtolower(pathinfo($base, PATHINFO_EXTENSION)) !== $actual_ext) $base .= '.' . $actual_ext;
    if (!$base) $base = 'buyer-file' . ($actual_ext ? '.' . $actual_ext : '');
    $stem = pathinfo($base, PATHINFO_FILENAME);
    $ext = pathinfo($base, PATHINFO_EXTENSION);
    $candidate = $base;
    $i = 2;
    while (isset($used[strtolower($candidate)])) {
        $candidate = $stem . '-' . $i . ($ext ? '.' . $ext : '');
        $i++;
    }
    $used[strtolower($candidate)] = true;
    return $candidate;
}

function ath_specimen_render_woo_source_file_inspector($product_id) {
    $product_id = absint($product_id);
    $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) {
        echo '<div class="notice notice-error"><p>' . esc_html($dataset->get_error_message()) . '</p></div>';
        return;
    }
    $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
    if (is_wp_error($analysis)) {
        echo '<div class="notice notice-error"><p>' . esc_html($analysis->get_error_message()) . '</p></div>';
        return;
    }
    $rows = ath_specimen_adoption_source_rows($product_id, $dataset, $analysis);
    if (is_wp_error($rows)) {
        echo '<div class="notice notice-error"><p>' . esc_html($rows->get_error_message()) . '</p></div>';
        return;
    }
    $base_url = admin_url('edit.php?post_type=ath_font&page=ath-catalog-adoption');
    $found = $missing = $remote = $fonts = 0;
    foreach ($rows as $row) {
        $status = (string) ($row['info']['status'] ?? '');
        if ('found' === $status) $found++;
        elseif ('missing' === $status) $missing++;
        elseif ('remote' === $status) $remote++;
        $fonts += (int) ($row['info']['zip']['fonts'] ?? 0);
        if (in_array(strtolower((string) ($row['info']['extension'] ?? '')), array('otf','ttf','woff','woff2'), true)) $fonts++;
    }
    ?>
    <p><a href="<?php echo esc_url($base_url); ?>">← <?php esc_html_e('Back to Woo Catalog', 'authentype-font-specimen'); ?></a></p>
    <div class="ath-source-inspector">
        <div class="ath-source-inspector-head">
            <div>
                <h2><?php echo esc_html($dataset['product']->get_name()); ?></h2>
                <p><?php echo esc_html(sprintf(__('Woo #%1$d · %2$s', 'authentype-font-specimen'), $product_id, ucfirst((string) $dataset['product']->get_type()))); ?></p>
            </div>
            <?php if (!empty($analysis['existing_font_id'])) : ?>
                <a class="button" href="<?php echo esc_url(get_edit_post_link((int) $analysis['existing_font_id'], '')); ?>"><?php esc_html_e('Open Athtyp', 'authentype-font-specimen'); ?></a>
            <?php endif; ?>
        </div>
        <p class="description"><?php esc_html_e('Read-only inspector. It shows the files already attached to this Woo product and can securely stream local source files to an authorized administrator. Nothing is copied, rebuilt, renamed, or changed.', 'authentype-font-specimen'); ?></p>
        <div class="ath-source-inspector-stats">
            <span><strong><?php echo esc_html(count($rows)); ?></strong> <?php esc_html_e('download references', 'authentype-font-specimen'); ?></span>
            <span><strong><?php echo esc_html($found); ?></strong> <?php esc_html_e('local files found', 'authentype-font-specimen'); ?></span>
            <span><strong><?php echo esc_html($remote); ?></strong> <?php esc_html_e('remote sources', 'authentype-font-specimen'); ?></span>
            <span><strong><?php echo esc_html($missing); ?></strong> <?php esc_html_e('missing', 'authentype-font-specimen'); ?></span>
            <span><strong><?php echo esc_html($fonts); ?></strong> <?php esc_html_e('font candidates', 'authentype-font-specimen'); ?></span>
        </div>
        <?php
        // For variable products the customer delivery belongs to the purchased
        // variation. Parent-level downloads remain visible in the detailed
        // source inspector below, but are not presented as variation delivery.
        $buyer_rows = $rows;
        if (method_exists($dataset['product'], 'is_type') && $dataset['product']->is_type('variable')) {
            $buyer_rows = array_values(array_filter($rows, function ($row) {
                return !empty($row['variation_id']);
            }));
        }
        $buyer_groups = ath_specimen_adoption_buyer_delivery_groups($buyer_rows);
        ?>
        <div class="ath-buyer-delivery-panel">
            <h3><?php esc_html_e('What the Buyer Receives', 'authentype-font-specimen'); ?></h3>
            <p class="description"><?php esc_html_e('Each row is the exact Woo download set attached to that purchasable product/variation. One-file deliveries stream that exact file. Multi-file local deliveries can be downloaded as a temporary admin ZIP containing the same files unchanged; WooCommerce itself is not modified.', 'authentype-font-specimen'); ?></p>
            <table class="widefat striped ath-buyer-delivery-table">
                <thead><tr>
                    <th><?php esc_html_e('Style / License', 'authentype-font-specimen'); ?></th>
                    <th><?php esc_html_e('Woo Delivery', 'authentype-font-specimen'); ?></th>
                    <th><?php esc_html_e('Buyer Receives', 'authentype-font-specimen'); ?></th>
                    <th><?php esc_html_e('Action', 'authentype-font-specimen'); ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($buyer_groups as $group) :
                    $variation_id = (int) ($group['variation_id'] ?? 0);
                    $delivery_nonce_action = 'ath_buyer_delivery_download_' . $product_id . '_' . $variation_id;
                    $delivery_url = add_query_arg(array(
                        'action' => 'ath_specimen_download_woo_buyer_delivery',
                        'product_id' => $product_id,
                        'variation_id' => $variation_id,
                        '_wpnonce' => wp_create_nonce($delivery_nonce_action),
                    ), admin_url('admin-post.php'));
                    $file_count = count((array) ($group['rows'] ?? array()));
                    $bundle_available = 1 === $file_count || ($file_count > 1 && (int) ($group['local_count'] ?? 0) === $file_count);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html((string) ($group['style_label'] ?? '—')); ?></strong><br><small><?php echo esc_html((string) ($group['license_label'] ?? '—')); ?></small></td>
                        <td><small><?php echo $variation_id ? esc_html('Variation #' . $variation_id) : esc_html__('Parent product', 'authentype-font-specimen'); ?></small></td>
                        <td>
                            <strong><?php echo esc_html(sprintf(_n('%d file', '%d files', $file_count, 'authentype-font-specimen'), $file_count)); ?></strong>
                            <ul class="ath-buyer-delivery-files">
                            <?php foreach ((array) ($group['rows'] ?? array()) as $delivery_row) :
                                $delivery_info = (array) ($delivery_row['info'] ?? array());
                                $delivery_download = (array) ($delivery_row['download'] ?? array());
                            ?>
                                <li><code><?php echo esc_html((string) ($delivery_info['filename'] ?? $delivery_download['name'] ?? 'Woo file')); ?></code> <span class="ath-source-status is-<?php echo esc_attr((string) ($delivery_info['status'] ?? 'unavailable')); ?>"><?php echo esc_html((string) ($delivery_info['status_label'] ?? '')); ?></span></li>
                            <?php endforeach; ?>
                            </ul>
                        </td>
                        <td>
                            <?php if ($bundle_available && $file_count > 0) : ?>
                                <a class="button button-primary" href="<?php echo esc_url($delivery_url); ?>"><?php echo 1 === $file_count ? esc_html__('Download Buyer File', 'authentype-font-specimen') : esc_html__('Download Buyer Delivery', 'authentype-font-specimen'); ?></a>
                            <?php elseif ($file_count > 1) : ?>
                                <span class="description"><?php esc_html_e('Mixed remote/missing delivery: use the exact file buttons below.', 'authentype-font-specimen'); ?></span>
                            <?php else : ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$buyer_groups) : ?><tr><td colspan="4"><?php esc_html_e('No buyer delivery files are currently attached to this Woo product.', 'authentype-font-specimen'); ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <h3 class="ath-source-detail-heading"><?php esc_html_e('Exact Woo File References', 'authentype-font-specimen'); ?></h3>
        <table class="widefat striped ath-source-inspector-table">
            <thead><tr>
                <th><?php esc_html_e('Style / License', 'authentype-font-specimen'); ?></th>
                <th><?php esc_html_e('Woo Reference', 'authentype-font-specimen'); ?></th>
                <th><?php esc_html_e('Source File', 'authentype-font-specimen'); ?></th>
                <th><?php esc_html_e('Status', 'authentype-font-specimen'); ?></th>
                <th><?php esc_html_e('Contents', 'authentype-font-specimen'); ?></th>
                <th><?php esc_html_e('Action', 'authentype-font-specimen'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $row) :
                $download = (array) $row['download'];
                $info = (array) $row['info'];
                $nonce_action = 'ath_source_file_download_' . $product_id . '_' . (int) $row['variation_id'] . '_' . (string) $row['ref'];
                $download_url = add_query_arg(array(
                    'action' => 'ath_specimen_download_woo_source',
                    'product_id' => $product_id,
                    'variation_id' => (int) $row['variation_id'],
                    'file_ref' => (string) $row['ref'],
                    '_wpnonce' => wp_create_nonce($nonce_action),
                ), admin_url('admin-post.php'));
                $zip = (array) ($info['zip'] ?? array());
                $contents = '';
                if (!empty($zip['fonts'])) {
                    $contents = sprintf(_n('%d font file in ZIP', '%d font files in ZIP', (int) $zip['fonts'], 'authentype-font-specimen'), (int) $zip['fonts']);
                } elseif (in_array(strtolower((string) ($info['extension'] ?? '')), array('otf','ttf','woff','woff2'), true)) {
                    $contents = __('Direct font file', 'authentype-font-specimen');
                } elseif (!empty($zip['entries'])) {
                    $contents = sprintf(_n('%d ZIP entry', '%d ZIP entries', (int) $zip['entries'], 'authentype-font-specimen'), (int) $zip['entries']);
                } else {
                    $contents = '—';
                }
            ?>
                <tr>
                    <td><strong><?php echo esc_html((string) $row['style_label']); ?></strong><br><small><?php echo esc_html((string) $row['license_label']); ?></small></td>
                    <td><small><?php echo (int) $row['variation_id'] ? esc_html('Variation #' . (int) $row['variation_id']) : esc_html__('Parent product', 'authentype-font-specimen'); ?><br><?php echo esc_html__('Download ID:', 'authentype-font-specimen') . ' ' . esc_html((string) ($download['id'] ?: '—')); ?></small></td>
                    <td><strong><?php echo esc_html((string) ($info['filename'] ?? '')); ?></strong><br><small><?php echo !empty($info['size']) ? esc_html(size_format((int) $info['size'], 2)) : esc_html(strtoupper((string) ($info['extension'] ?? ''))); ?></small></td>
                    <td><span class="ath-source-status is-<?php echo esc_attr((string) ($info['status'] ?? 'unavailable')); ?>"><?php echo esc_html((string) ($info['status_label'] ?? '')); ?></span></td>
                    <td>
                        <?php echo esc_html($contents); ?>
                        <?php if (!empty($zip['font_entries'])) : ?>
                            <details class="ath-source-zip-entries"><summary><?php esc_html_e('Show font names', 'authentype-font-specimen'); ?></summary><ul><?php foreach ((array) $zip['font_entries'] as $entry) : ?><li><code><?php echo esc_html($entry); ?></code></li><?php endforeach; ?></ul></details>
                        <?php endif; ?>
                    </td>
                    <td><?php if (!empty($info['can_download'])) : ?><a class="button" href="<?php echo esc_url($download_url); ?>"><?php echo !empty($info['remote']) ? esc_html__('Open Exact File', 'authentype-font-specimen') : esc_html__('Download Exact File', 'authentype-font-specimen'); ?></a><?php else : ?>—<?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows) : ?><tr><td colspan="6"><?php esc_html_e('No Woo download files are attached to this product or its variations.', 'authentype-font-specimen'); ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_action('admin_post_ath_specimen_download_woo_buyer_delivery', function () {
    if (!ath_specimen_adoption_capable()) wp_die(esc_html__('Permission denied.', 'authentype-font-specimen'), '', array('response' => 403));
    $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
    $variation_id = isset($_GET['variation_id']) ? absint($_GET['variation_id']) : 0;
    if (!$product_id) wp_die(esc_html__('Invalid buyer delivery request.', 'authentype-font-specimen'), '', array('response' => 400));
    check_admin_referer('ath_buyer_delivery_download_' . $product_id . '_' . $variation_id);

    $delivery_product = wc_get_product($variation_id ?: $product_id);
    if (!$delivery_product) wp_die(esc_html__('Woo buyer delivery product was not found.', 'authentype-font-specimen'), '', array('response' => 404));
    if ($variation_id && (!method_exists($delivery_product, 'get_parent_id') || (int) $delivery_product->get_parent_id() !== $product_id)) {
        wp_die(esc_html__('The variation does not belong to this Woo product.', 'authentype-font-specimen'), '', array('response' => 400));
    }

    $records = array();
    foreach ((array) $delivery_product->get_downloads('edit') as $download) {
        $record = ath_specimen_adoption_download_record($download);
        if ($record && !empty($record['file'])) $records[] = $record;
    }
    if (!$records) wp_die(esc_html__('This Woo delivery currently has no downloadable files.', 'authentype-font-specimen'), '', array('response' => 404));

    // One Woo download: stream/redirect the exact file reference currently
    // granted by this product/variation. No temporary repackaging is needed.
    if (1 === count($records)) {
        $record = $records[0];
        $file = (string) $record['file'];
        $local = function_exists('ath_specimen_local_upload_path') ? ath_specimen_local_upload_path($file) : '';
        if ($local) {
            $filename = sanitize_file_name((string) ($record['name'] ?? ''));
            $actual_ext = strtolower(pathinfo($local, PATHINFO_EXTENSION));
            if (!$filename) $filename = sanitize_file_name(wp_basename($local));
            if ($actual_ext && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== $actual_ext) $filename .= '.' . $actual_ext;
            ath_specimen_adoption_buyer_delivery_stream_local($local, $filename, 'application/octet-stream');
        }
        if (ath_specimen_adoption_source_is_upload_url($file)) {
            wp_die(esc_html__('The Woo buyer download points to WordPress uploads, but the file is missing on disk.', 'authentype-font-specimen'), '', array('response' => 404));
        }
        if (filter_var($file, FILTER_VALIDATE_URL)) {
            $parts = wp_parse_url($file);
            if (is_array($parts) && !empty($parts['scheme']) && in_array(strtolower($parts['scheme']), array('http', 'https'), true) && empty($parts['user']) && empty($parts['pass'])) {
                wp_redirect(esc_url_raw($file), 302, 'Athtyp Woo Buyer Delivery');
                exit;
            }
        }
        wp_die(esc_html__('The Woo buyer delivery file is unavailable.', 'authentype-font-specimen'), '', array('response' => 404));
    }

    // Multiple Woo downloads are normally shown to the buyer as separate links.
    // For admin recovery convenience only, package those exact local files into
    // one temporary ZIP. The source files themselves are never changed.
    if (!class_exists('ZipArchive')) {
        wp_die(esc_html__('ZIP support is required to download a multi-file buyer delivery in one click. Use the exact file buttons instead.', 'authentype-font-specimen'), '', array('response' => 500));
    }
    $local_rows = array();
    foreach ($records as $record) {
        $file = (string) ($record['file'] ?? '');
        $local = function_exists('ath_specimen_local_upload_path') ? ath_specimen_local_upload_path($file) : '';
        if (!$local || !is_file($local) || !is_readable($local)) {
            wp_die(esc_html__('This buyer delivery contains a remote or missing file. Use the exact file buttons so no remote content is fetched or altered.', 'authentype-font-specimen'), '', array('response' => 409));
        }
        $local_rows[] = array('record' => $record, 'path' => $local);
    }

    $tmp = wp_tempnam('ath-buyer-delivery-' . $product_id . '-' . $variation_id . '.zip');
    if (!$tmp) wp_die(esc_html__('A temporary buyer-delivery archive could not be created.', 'authentype-font-specimen'), '', array('response' => 500));
    register_shutdown_function(function () use ($tmp) {
        if (is_file($tmp)) @unlink($tmp);
    });
    $zip = new ZipArchive();
    if (true !== $zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        @unlink($tmp);
        wp_die(esc_html__('The temporary buyer-delivery archive could not be opened.', 'authentype-font-specimen'), '', array('response' => 500));
    }
    $used = array();
    $ok = true;
    foreach ($local_rows as $item) {
        $entry = ath_specimen_adoption_buyer_delivery_filename((array) $item['record'], (string) $item['path'], $used);
        if (!$zip->addFile((string) $item['path'], $entry)) { $ok = false; break; }
    }
    if (!$zip->close()) $ok = false;
    if (!$ok || !is_file($tmp) || !filesize($tmp)) {
        @unlink($tmp);
        wp_die(esc_html__('The temporary buyer-delivery archive could not be completed.', 'authentype-font-specimen'), '', array('response' => 500));
    }

    $product_name = sanitize_title((string) $delivery_product->get_name());
    if (!$product_name) $product_name = 'woo-product-' . ($variation_id ?: $product_id);
    $download_name = $product_name . '-buyer-delivery.zip';
    $size = @filesize($tmp);
    while (ob_get_level()) @ob_end_clean();
    nocache_headers();
    header('X-Content-Type-Options: nosniff');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $download_name) . '"; filename*=UTF-8\'\'' . rawurlencode($download_name));
    if (false !== $size) header('Content-Length: ' . (int) $size);
    $handle = @fopen($tmp, 'rb');
    if (!$handle) {
        @unlink($tmp);
        wp_die(esc_html__('The temporary buyer-delivery archive could not be read.', 'authentype-font-specimen'), '', array('response' => 500));
    }
    fpassthru($handle);
    fclose($handle);
    @unlink($tmp);
    exit;
});

add_action('admin_post_ath_specimen_download_woo_source', function () {
    if (!ath_specimen_adoption_capable()) wp_die(esc_html__('Permission denied.', 'authentype-font-specimen'), '', array('response' => 403));
    $product_id = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
    $variation_id = isset($_GET['variation_id']) ? absint($_GET['variation_id']) : 0;
    $file_ref = isset($_GET['file_ref']) ? sanitize_text_field(wp_unslash($_GET['file_ref'])) : '';
    if (!$product_id || !$file_ref) wp_die(esc_html__('Invalid source file request.', 'authentype-font-specimen'), '', array('response' => 400));
    check_admin_referer('ath_source_file_download_' . $product_id . '_' . $variation_id . '_' . $file_ref);

    $product = wc_get_product($variation_id ?: $product_id);
    if (!$product) wp_die(esc_html__('Woo source product was not found.', 'authentype-font-specimen'), '', array('response' => 404));
    if ($variation_id && (!method_exists($product, 'get_parent_id') || (int) $product->get_parent_id() !== $product_id)) {
        wp_die(esc_html__('The variation does not belong to this Woo product.', 'authentype-font-specimen'), '', array('response' => 400));
    }

    $matched = null;
    foreach ((array) $product->get_downloads('edit') as $download) {
        $record = ath_specimen_adoption_download_record($download);
        if (!$record || empty($record['file'])) continue;
        if (hash_equals((string) ath_specimen_adoption_source_file_ref($variation_id, $record), (string) $file_ref)) { $matched = $record; break; }
    }
    if (!$matched) wp_die(esc_html__('The Woo download reference no longer exists.', 'authentype-font-specimen'), '', array('response' => 404));

    $file = (string) $matched['file'];
    $local = function_exists('ath_specimen_local_upload_path') ? ath_specimen_local_upload_path($file) : '';
    if ($local) {
        $size = @filesize($local);
        $filename = sanitize_file_name(!empty($matched['name']) ? (string) $matched['name'] : wp_basename($local));
        $actual_ext = strtolower(pathinfo($local, PATHINFO_EXTENSION));
        if ($actual_ext && strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== $actual_ext) $filename .= '.' . $actual_ext;
        if (!$filename) $filename = 'woo-source-file' . ($actual_ext ? '.' . $actual_ext : '');
        while (ob_get_level()) @ob_end_clean();
        nocache_headers();
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        if (false !== $size) header('Content-Length: ' . (int) $size);
        $handle = @fopen($local, 'rb');
        if (!$handle) wp_die(esc_html__('The source file could not be opened.', 'authentype-font-specimen'), '', array('response' => 500));
        fpassthru($handle);
        fclose($handle);
        exit;
    }

    if (ath_specimen_adoption_source_is_upload_url($file)) {
        wp_die(esc_html__('The Woo download URL points to WordPress uploads, but the file is missing on disk.', 'authentype-font-specimen'), '', array('response' => 404));
    }
    if (filter_var($file, FILTER_VALIDATE_URL)) {
        $parts = wp_parse_url($file);
        if (is_array($parts) && !empty($parts['scheme']) && in_array(strtolower($parts['scheme']), array('http','https'), true) && empty($parts['user']) && empty($parts['pass'])) {
            wp_redirect(esc_url_raw($file), 302, 'Athtyp Woo Source Inspector');
            exit;
        }
    }
    wp_die(esc_html__('The Woo source file is unavailable or outside the permitted uploads area.', 'authentype-font-specimen'), '', array('response' => 404));
});

function ath_specimen_adoption_filter_status($analysis, $wanted) {
    if (!$wanted || 'all' === $wanted) return true;
    if ('ready' === $wanted) return !empty($analysis['bulk_ready']);
    if ('review' === $wanted) return 'adopted' !== $analysis['status'] && empty($analysis['bulk_ready']);
    return $analysis['status'] === $wanted;
}


function ath_specimen_render_catalog_adoption_page() {
    if (!ath_specimen_adoption_capable()) wp_die(esc_html__('Permission denied.', 'authentype-font-specimen'));
    if (!function_exists('wc_get_product')) {
        echo '<div class="wrap"><h1>' . esc_html__('Catalog Adoption', 'authentype-font-specimen') . '</h1><div class="notice notice-error"><p>' . esc_html__('WooCommerce must be active.', 'authentype-font-specimen') . '</p></div></div>';
        return;
    }

    // One-time seed from products that were already adopted before mapping
    // profiles existed. This only stores attribute-key hints; Woo is untouched.
    ath_specimen_adoption_seed_mapping_profiles();

    $dry_run_id = isset($_GET['ath_dry_run']) ? absint($_GET['ath_dry_run']) : 0;
    $source_files_id = isset($_GET['ath_source_files']) ? absint($_GET['ath_source_files']) : 0;
    $style_attr = isset($_GET['style_attr']) ? sanitize_title(wp_unslash($_GET['style_attr'])) : '';
    $license_attr = isset($_GET['license_attr']) ? sanitize_title(wp_unslash($_GET['license_attr'])) : '';
    ?>
    <div class="wrap ath-adoption-wrap">
        <h1><?php esc_html_e('Woo Catalog', 'authentype-font-specimen'); ?></h1>
        <p class="description ath-adoption-intro"><?php esc_html_e('Use this page to connect old Woo font products, check storefront readiness, and find the original files already attached to WooCommerce. Normal scans and file inspection are read-only.', 'authentype-font-specimen'); ?></p>
        <div class="notice notice-info inline"><p><strong><?php esc_html_e('Priority:', 'authentype-font-specimen'); ?></strong> <?php esc_html_e('Preserve existing Woo product IDs, variation IDs, download IDs, files, orders, and customer permissions whenever possible.', 'authentype-font-specimen'); ?></p></div>
        <?php
        if ($source_files_id) {
            ath_specimen_render_woo_source_file_inspector($source_files_id);
        } elseif ($dry_run_id) {
            ath_specimen_render_adoption_dry_run($dry_run_id, $style_attr, $license_attr);
        } else {
            ath_specimen_render_adoption_catalog_list();
        }
        ?>
    </div>
    <?php
}

function ath_specimen_render_adoption_dry_run($product_id, $style_attr = '', $license_attr = '') {
    $product = wc_get_product($product_id);
    if (!$product) {
        echo '<div class="notice notice-error"><p>' . esc_html__('Product not found.', 'authentype-font-specimen') . '</p></div>';
        return;
    }
    $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) { echo '<div class="notice notice-error"><p>' . esc_html($dataset->get_error_message()) . '</p></div>'; return; }
    $attributes = $dataset['attributes'];
    $analysis = ath_specimen_adoption_analyze_product($product_id, $style_attr, $license_attr, $dataset);
    if (is_wp_error($analysis)) {
        echo '<div class="notice notice-error"><p>' . esc_html($analysis->get_error_message()) . '</p></div>';
        return;
    }
    $base_url = admin_url('edit.php?post_type=ath_font&page=ath-catalog-adoption');
    ?>
    <p><a href="<?php echo esc_url($base_url); ?>">← <?php esc_html_e('Back to catalog', 'authentype-font-specimen'); ?></a></p>
    <div class="ath-adoption-dry-run">
        <div class="ath-adoption-heading-row">
            <div><h2><?php echo esc_html($product->get_name()); ?></h2><p>#<?php echo esc_html($product_id); ?> · <?php echo esc_html(ucfirst($product->get_type())); ?></p></div>
            <span class="ath-adoption-status <?php echo esc_attr(ath_specimen_adoption_status_class($analysis['status'], isset($analysis['bulk_ready']) ? (bool) $analysis['bulk_ready'] : null)); ?>"><?php echo esc_html($analysis['status_label']); ?></span>
        </div>

        <?php if (!$analysis['existing_font_id'] && $product->is_type('variable')) : ?>
            <form method="get" class="ath-adoption-mapping-form">
                <input type="hidden" name="post_type" value="ath_font">
                <input type="hidden" name="page" value="ath-catalog-adoption">
                <input type="hidden" name="ath_dry_run" value="<?php echo esc_attr($product_id); ?>">
                <label><strong><?php esc_html_e('Style attribute', 'authentype-font-specimen'); ?></strong>
                    <select name="style_attr">
                        <option value=""><?php esc_html_e('Choose…', 'authentype-font-specimen'); ?></option>
                        <?php foreach ($attributes as $key => $row) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($analysis['style_attr'], $key); ?>><?php echo esc_html($row['label'] . ' [' . $key . ']' . (!$row['global'] ? ' — custom' : '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><strong><?php esc_html_e('License attribute', 'authentype-font-specimen'); ?></strong>
                    <select name="license_attr">
                        <option value=""><?php esc_html_e('Choose…', 'authentype-font-specimen'); ?></option>
                        <?php foreach ($attributes as $key => $row) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($analysis['license_attr'], $key); ?>><?php echo esc_html($row['label'] . ' [' . $key . ']' . (!$row['global'] ? ' — custom' : '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="button"><?php esc_html_e('Refresh Dry Run', 'authentype-font-specimen'); ?></button>
            </form>
        <?php endif; ?>

        <div class="ath-adoption-summary-grid">
            <div><strong><?php echo esc_html(count($analysis['styles'])); ?></strong><span><?php esc_html_e('Styles', 'authentype-font-specimen'); ?></span></div>
            <div><strong><?php echo esc_html(count($analysis['licenses'])); ?></strong><span><?php esc_html_e('Licenses', 'authentype-font-specimen'); ?></span></div>
            <div><strong><?php echo esc_html($analysis['variation_count']); ?></strong><span><?php esc_html_e('Variations', 'authentype-font-specimen'); ?></span></div>
            <div><strong><?php echo esc_html($analysis['price_count']); ?></strong><span><?php esc_html_e('Prices', 'authentype-font-specimen'); ?></span></div>
            <div><strong><?php echo esc_html($analysis['download_count']); ?></strong><span><?php esc_html_e('Importable files', 'authentype-font-specimen'); ?></span></div>
        </div>
        <p class="ath-adoption-message"><?php echo esc_html($analysis['message']); ?></p>
        <?php if (!empty($analysis['candidate_font_id'])) : ?>
            <p><a class="button" href="<?php echo esc_url(get_edit_post_link((int) $analysis['candidate_font_id'], '')); ?>"><?php esc_html_e('Open Existing Athtyp Candidate', 'authentype-font-specimen'); ?></a></p>
        <?php endif; ?>

        <?php if ('compatible' === $analysis['status']) : ?>
            <h3><?php esc_html_e('Dry Run — changes to Athtyp only', 'authentype-font-specimen'); ?></h3>
            <table class="widefat striped ath-adoption-plan"><tbody>
                <tr><th><?php esc_html_e('Create', 'authentype-font-specimen'); ?></th><td><?php esc_html_e('1 draft Athtyp font linked to this existing Woo product.', 'authentype-font-specimen'); ?></td></tr>
                <tr><th><?php esc_html_e('Import', 'authentype-font-specimen'); ?></th><td><?php echo esc_html(sprintf(__('%1$d styles, %2$d licenses, %3$d variation price records, %4$d existing download files.', 'authentype-font-specimen'), count($analysis['styles']), count($analysis['licenses']), $analysis['price_count'], $analysis['download_count'])); ?></td></tr>
                <tr><th><?php esc_html_e('Snapshot', 'authentype-font-specimen'); ?></th><td><?php esc_html_e('Capture current Woo variation IDs, attributes, prices, statuses, and downloads before Athtyp takeover.', 'authentype-font-specimen'); ?></td></tr>
                <tr><th><?php esc_html_e('Woo changes now', 'authentype-font-specimen'); ?></th><td><strong><?php esc_html_e('None.', 'authentype-font-specimen'); ?></strong> <?php esc_html_e('No variation, price, file, status, or order data is written during adoption.', 'authentype-font-specimen'); ?></td></tr>
            </tbody></table>
            <p class="submit">
                <button type="button" class="button button-primary ath-adopt-one" data-product-id="<?php echo esc_attr($product_id); ?>" data-style-attr="<?php echo esc_attr($analysis['style_attr']); ?>" data-license-attr="<?php echo esc_attr($analysis['license_attr']); ?>"><?php esc_html_e('Adopt Product', 'authentype-font-specimen'); ?></button>
                <span class="spinner"></span><span class="ath-adopt-result" aria-live="polite"></span>
            </p>
        <?php elseif ('adopted' === $analysis['status']) : ?>
            <?php ath_specimen_adoption_maybe_upgrade_snapshot($analysis['existing_font_id']); $snap = get_post_meta($analysis['existing_font_id'], '_ath_adoption_snapshot', true); ?>
            <p><a class="button button-primary" href="<?php echo esc_url(get_edit_post_link($analysis['existing_font_id'], '')); ?>"><?php esc_html_e('Open Athtyp Font', 'authentype-font-specimen'); ?></a>
            <?php if (is_array($snap) && (int) ($snap['version'] ?? 0) >= 2) : ?>
                <button type="button" class="button ath-restore-snapshot" data-font-id="<?php echo esc_attr($analysis['existing_font_id']); ?>"><?php esc_html_e('Restore Pre-Adoption Woo State', 'authentype-font-specimen'); ?></button>
                <span class="spinner"></span><span class="ath-restore-result" aria-live="polite"></span>
            <?php endif; ?></p>
        <?php endif; ?>
    </div>
    <?php
}

function ath_specimen_render_adoption_catalog_list() {
    $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
    $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
    $status_filter = isset($_GET['ath_status']) ? sanitize_key(wp_unslash($_GET['ath_status'])) : 'all';
    $per_page = 20;

    $base_args = array(
        'post_type' => 'product',
        'post_status' => array('publish', 'draft', 'private'),
        'orderby' => 'title',
        'order' => 'ASC',
        's' => $search,
    );
    $rows = array();
    $total_pages = 1;

    if ('all' === $status_filter) {
        $query = new WP_Query(array_merge($base_args, array('posts_per_page' => $per_page, 'paged' => $paged)));
        foreach ($query->posts as $post) {
            $analysis = ath_specimen_adoption_analyze_product($post->ID);
            if (!is_wp_error($analysis)) $rows[] = array($post, $analysis);
        }
        $total_pages = max(1, (int) $query->max_num_pages);
    } else {
        // Status filtering must happen before pagination. Fetch IDs only (cheap),
        // analyze them one dataset at a time, then paginate the matching set.
        $id_query = new WP_Query(array_merge($base_args, array('posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true)));
        $matched = array();
        foreach ((array) $id_query->posts as $product_id) {
            $analysis = ath_specimen_adoption_analyze_product((int) $product_id);
            if (!is_wp_error($analysis) && ath_specimen_adoption_filter_status($analysis, $status_filter)) $matched[] = array((int) $product_id, $analysis);
        }
        $total_pages = max(1, (int) ceil(count($matched) / $per_page));
        foreach (array_slice($matched, ($paged - 1) * $per_page, $per_page) as $item) {
            $post = get_post($item[0]);
            if ($post) $rows[] = array($post, $item[1]);
        }
    }
    $page_has_ready = false;
    foreach ($rows as $item) {
        if (!empty($item[1]['bulk_ready']) && 'compatible' === (string) ($item[1]['status'] ?? '')) { $page_has_ready = true; break; }
    }
    ?>
    <form method="get" class="ath-adoption-filters">
        <input type="hidden" name="post_type" value="ath_font">
        <input type="hidden" name="page" value="ath-catalog-adoption">
        <p class="search-box">
            <label class="screen-reader-text" for="ath-product-search"><?php esc_html_e('Search products', 'authentype-font-specimen'); ?></label>
            <input type="search" id="ath-product-search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search Woo products…', 'authentype-font-specimen'); ?>">
            <select name="ath_status">
                <option value="all" <?php selected($status_filter, 'all'); ?>><?php esc_html_e('All statuses', 'authentype-font-specimen'); ?></option>
                <option value="ready" <?php selected($status_filter, 'ready'); ?>><?php esc_html_e('Bulk ready', 'authentype-font-specimen'); ?></option>
                <option value="compatible" <?php selected($status_filter, 'compatible'); ?>><?php esc_html_e('Structurally compatible', 'authentype-font-specimen'); ?></option>
                <option value="adopted" <?php selected($status_filter, 'adopted'); ?>><?php esc_html_e('Already adopted', 'authentype-font-specimen'); ?></option>
                <option value="review" <?php selected($status_filter, 'review'); ?>><?php esc_html_e('Needs review', 'authentype-font-specimen'); ?></option>
            </select>
            <button class="button"><?php esc_html_e('Filter', 'authentype-font-specimen'); ?></button>
        </p>
    </form>
    <div class="ath-catalog-workflow-note">
        <strong><?php esc_html_e('Simple workflow', 'authentype-font-specimen'); ?></strong>
        <span><?php esc_html_e('1. Scan only when you still have Woo products that are not linked. 2. Audit linked products to see what actually needs attention. 3. Use Source Files on any row when you need to find or download the original Woo file.', 'authentype-font-specimen'); ?></span>
    </div>
    <div class="ath-legacy-matcher ath-catalog-primary-tool" data-search="<?php echo esc_attr($search); ?>">
        <div class="ath-legacy-matcher-copy">
            <strong><?php esc_html_e('1. Connect Old Woo Products', 'authentype-font-specimen'); ?></strong>
            <span><?php esc_html_e('Use this only for Woo products that do not have an Athtyp record yet. Scan is read-only; adoption creates the link without rewriting the existing Woo product.', 'authentype-font-specimen'); ?></span>
        </div>
        <div class="ath-legacy-matcher-actions">
            <button type="button" class="button ath-scan-legacy-catalog"><?php esc_html_e('Scan Woo Catalog', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button button-primary ath-adopt-all-ready" disabled><?php esc_html_e('Adopt All Ready', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button ath-pause-legacy-matcher" hidden><?php esc_html_e('Pause', 'authentype-font-specimen'); ?></button>
        </div>
        <div class="ath-legacy-matcher-counts" aria-live="polite">
            <span><strong data-ath-count="scanned">0</strong> <?php esc_html_e('scanned', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-count="ready">0</strong> <?php esc_html_e('ready', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-count="review">0</strong> <?php esc_html_e('review', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-count="adopted">0</strong> <?php esc_html_e('adopted', 'authentype-font-specimen'); ?></span>
        </div>
        <p class="ath-legacy-matcher-status" aria-live="polite"></p>
    </div>
    <div class="ath-commerce-readiness">
        <div class="ath-commerce-readiness-copy">
            <strong><?php esc_html_e('2. Check Storefront', 'authentype-font-specimen'); ?></strong>
            <span><?php esc_html_e('Main health check for products that are already linked. It tells you which products are Shop Ready and which ones need a specific repair. This audit is read-only.', 'authentype-font-specimen'); ?></span>
        </div>
        <div class="ath-commerce-readiness-actions">
            <button type="button" class="button ath-audit-commerce-readiness"><?php esc_html_e('Audit Adopted Products', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button ath-pause-commerce-readiness" hidden><?php esc_html_e('Pause', 'authentype-font-specimen'); ?></button>
        </div>
        <div class="ath-commerce-readiness-counts" aria-live="polite">
            <span><strong data-ath-readiness-count="audited">0</strong> <?php esc_html_e('audited', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-readiness-count="shop_ready">0</strong> <?php esc_html_e('shop ready', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-readiness-count="needs_sync">0</strong> <?php esc_html_e('need sync', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-readiness-count="needs_pricing">0</strong> <?php esc_html_e('need pricing', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-readiness-count="missing_delivery">0</strong> <?php esc_html_e('missing delivery', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-readiness-count="review">0</strong> <?php esc_html_e('review', 'authentype-font-specimen'); ?></span>
        </div>
        <p class="ath-commerce-readiness-status" aria-live="polite"></p>
    </div>
    <details class="ath-adoption-advanced-tools">
        <summary><strong><?php esc_html_e('Advanced repair tools', 'authentype-font-specimen'); ?></strong><span><?php esc_html_e('Open only when Check Storefront reports Missing Delivery, Needs Pricing, or Needs Woo Sync.', 'authentype-font-specimen'); ?></span></summary>
        <div class="ath-adoption-advanced-tools-body">
    <div class="ath-legacy-delivery-hydration">
        <div class="ath-legacy-delivery-hydration-copy">
            <strong><?php esc_html_e('Legacy Delivery Hydration', 'authentype-font-specimen'); ?></strong>
            <span><?php esc_html_e('Two-step repair for adopted products classified Missing Delivery. Preview is read-only; Hydrate copies the existing Woo download ID, name, and file into missing Athtyp Style × License delivery pairs only. WooCommerce, pricing, orders, and customer permissions are never changed.', 'authentype-font-specimen'); ?></span>
        </div>
        <div class="ath-legacy-delivery-hydration-actions">
            <button type="button" class="button ath-preview-legacy-delivery"><?php esc_html_e('Preview Missing Delivery', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button button-primary ath-hydrate-legacy-delivery" disabled><?php esc_html_e('Hydrate Legacy Delivery', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button ath-pause-legacy-delivery" hidden><?php esc_html_e('Pause', 'authentype-font-specimen'); ?></button>
        </div>
        <div class="ath-legacy-delivery-hydration-counts" aria-live="polite">
            <span><strong data-ath-hydration-count="checked">0</strong> <?php esc_html_e('checked', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-hydration-count="eligible">0</strong> <?php esc_html_e('eligible products', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-hydration-count="pairs">0</strong> <?php esc_html_e('pairs', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-hydration-count="files">0</strong> <?php esc_html_e('files', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-hydration-count="blocked">0</strong> <?php esc_html_e('blocked', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-hydration-count="skipped">0</strong> <?php esc_html_e('non-target', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-hydration-count="hydrated">0</strong> <?php esc_html_e('hydrated', 'authentype-font-specimen'); ?></span>
        </div>
        <p class="ath-legacy-delivery-hydration-status" aria-live="polite"></p>
        <div class="ath-legacy-delivery-blocked" hidden>
            <strong><?php esc_html_e('Blocked products requiring review', 'authentype-font-specimen'); ?></strong>
            <ol class="ath-legacy-delivery-blocked-list"></ol>
        </div>
    </div>
    <div class="ath-legacy-pricing-hydration">
        <div class="ath-legacy-pricing-hydration-copy">
            <strong><?php esc_html_e('Legacy Pricing Hydration', 'authentype-font-specimen'); ?></strong>
            <span><?php esc_html_e('Two-step migration for adopted products classified Needs Pricing. Preview is read-only; Hydrate copies exact live Woo Regular/Sale prices into completely empty Athtyp Style × License price cells only. Existing Athtyp prices, WooCommerce, delivery, orders, and customer permissions are never changed.', 'authentype-font-specimen'); ?></span>
        </div>
        <div class="ath-legacy-pricing-hydration-actions">
            <button type="button" class="button ath-preview-legacy-pricing"><?php esc_html_e('Preview Missing Pricing', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button button-primary ath-hydrate-legacy-pricing" disabled><?php esc_html_e('Hydrate Legacy Pricing', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button ath-pause-legacy-pricing" hidden><?php esc_html_e('Pause', 'authentype-font-specimen'); ?></button>
        </div>
        <div class="ath-legacy-pricing-hydration-counts" aria-live="polite">
            <span><strong data-ath-pricing-hydration-count="checked">0</strong> <?php esc_html_e('checked', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-pricing-hydration-count="eligible">0</strong> <?php esc_html_e('eligible products', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-pricing-hydration-count="pairs">0</strong> <?php esc_html_e('price cells', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-pricing-hydration-count="sales">0</strong> <?php esc_html_e('sale cells', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-pricing-hydration-count="blocked">0</strong> <?php esc_html_e('blocked', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-pricing-hydration-count="skipped">0</strong> <?php esc_html_e('non-target', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-pricing-hydration-count="hydrated">0</strong> <?php esc_html_e('hydrated', 'authentype-font-specimen'); ?></span>
        </div>
        <p class="ath-legacy-pricing-hydration-status" aria-live="polite"></p>
        <div class="ath-legacy-pricing-blocked" hidden>
            <strong><?php esc_html_e('Pricing hydration products requiring review', 'authentype-font-specimen'); ?></strong>
            <ol class="ath-legacy-pricing-blocked-list"></ol>
        </div>
    </div>
    <div class="ath-legacy-woo-reconciliation">
        <div class="ath-legacy-woo-reconciliation-copy">
            <strong><?php esc_html_e('Legacy Woo Variation Reconciliation', 'authentype-font-specimen'); ?></strong>
            <span><?php esc_html_e('Two-step repair for adopted products classified Needs Woo Sync. Preview is read-only. Reconcile reuses the existing Woo variation IDs and changes only stale Style/License attributes plus Athtyp-authoritative Regular/Sale/active prices. Existing Woo download IDs, names, files, variation status, orders, and customer permissions are never changed.', 'authentype-font-specimen'); ?></span>
        </div>
        <div class="ath-legacy-woo-reconciliation-actions">
            <button type="button" class="button ath-preview-legacy-woo-reconciliation"><?php esc_html_e('Preview Stale Woo Pairs', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button button-primary ath-run-legacy-woo-reconciliation" disabled><?php esc_html_e('Reconcile Safe Woo Pairs', 'authentype-font-specimen'); ?></button>
            <button type="button" class="button ath-pause-legacy-woo-reconciliation" hidden><?php esc_html_e('Pause', 'authentype-font-specimen'); ?></button>
        </div>
        <div class="ath-legacy-woo-reconciliation-counts" aria-live="polite">
            <span><strong data-ath-woo-reconcile-count="checked">0</strong> <?php esc_html_e('checked', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-woo-reconcile-count="eligible">0</strong> <?php esc_html_e('eligible products', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-woo-reconcile-count="pairs">0</strong> <?php esc_html_e('variation actions', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-woo-reconcile-count="remaps">0</strong> <?php esc_html_e('pair remaps', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-woo-reconcile-count="prices">0</strong> <?php esc_html_e('price updates', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-woo-reconcile-count="blocked">0</strong> <?php esc_html_e('blocked', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-woo-reconcile-count="skipped">0</strong> <?php esc_html_e('non-target', 'authentype-font-specimen'); ?></span>
            <span><strong data-ath-woo-reconcile-count="reconciled">0</strong> <?php esc_html_e('reconciled', 'authentype-font-specimen'); ?></span>
        </div>
        <p class="ath-legacy-woo-reconciliation-status" aria-live="polite"></p>
        <div class="ath-legacy-woo-reconciliation-blocked" hidden>
            <strong><?php esc_html_e('Woo reconciliation products requiring review', 'authentype-font-specimen'); ?></strong>
            <ol class="ath-legacy-woo-reconciliation-blocked-list"></ol>
        </div>
    </div>
        </div>
    </details>
    <?php if ($page_has_ready) : ?>
    <div class="ath-adoption-bulkbar">
        <button type="button" class="button button-primary ath-adopt-selected" disabled><?php esc_html_e('Adopt Selected Ready', 'authentype-font-specimen'); ?></button>
        <button type="button" class="button ath-pause-adoption" hidden><?php esc_html_e('Pause', 'authentype-font-specimen'); ?></button>
        <span class="ath-adoption-bulk-status" aria-live="polite"></span>
    </div>
    <?php endif; ?>
    <table class="wp-list-table widefat fixed striped table-view-list ath-adoption-table">
        <thead><tr>
            <td class="manage-column check-column"><?php if ($page_has_ready) : ?><input type="checkbox" class="ath-select-all-compatible" aria-label="<?php esc_attr_e('Select bulk-ready products on this page', 'authentype-font-specimen'); ?>"><?php endif; ?></td>
            <th><?php esc_html_e('Woo Product', 'authentype-font-specimen'); ?></th><th><?php esc_html_e('Type', 'authentype-font-specimen'); ?></th><th><?php esc_html_e('Detected Mapping', 'authentype-font-specimen'); ?></th><th><?php esc_html_e('Catalog Data', 'authentype-font-specimen'); ?></th><th><?php esc_html_e('Status', 'authentype-font-specimen'); ?></th><th><?php esc_html_e('Action', 'authentype-font-specimen'); ?></th>
        </tr></thead><tbody>
        <?php
        foreach ($rows as $item) {
            list($post, $analysis) = $item;
            $compatible = 'compatible' === $analysis['status'] && !empty($analysis['bulk_ready']);
            $dry_url = add_query_arg(array('ath_dry_run' => $post->ID), admin_url('edit.php?post_type=ath_font&page=ath-catalog-adoption'));
            $source_url = add_query_arg(array('ath_source_files' => $post->ID), admin_url('edit.php?post_type=ath_font&page=ath-catalog-adoption'));
            ?>
            <tr data-product-id="<?php echo esc_attr($post->ID); ?>">
                <th class="check-column"><?php if ($compatible) : ?><input type="checkbox" class="ath-adopt-checkbox" value="<?php echo esc_attr($post->ID); ?>" data-style-attr="<?php echo esc_attr($analysis['style_attr']); ?>" data-license-attr="<?php echo esc_attr($analysis['license_attr']); ?>"><?php endif; ?></th>
                <td><strong><?php echo esc_html($analysis['product_name']); ?></strong><div class="row-actions"><span>#<?php echo esc_html($post->ID); ?></span> | <a href="<?php echo esc_url(get_edit_post_link($post->ID, '')); ?>"><?php esc_html_e('Edit Woo', 'authentype-font-specimen'); ?></a></div></td>
                <td><?php echo esc_html(ucfirst($analysis['product_type'])); ?></td>
                <td><?php if ($analysis['style_attr'] && $analysis['license_attr']) : ?><code><?php echo esc_html($analysis['style_attr']); ?></code> × <code><?php echo esc_html($analysis['license_attr']); ?></code><?php else : ?>—<?php endif; ?></td>
                <td><?php echo esc_html(sprintf(__('%1$d styles · %2$d licenses · %3$d vars · %4$d importable files', 'authentype-font-specimen'), count($analysis['styles']), count($analysis['licenses']), $analysis['variation_count'], $analysis['download_count'])); ?><small class="ath-commerce-readiness-result" hidden></small></td>
                <td><span class="ath-adoption-status <?php echo esc_attr(ath_specimen_adoption_status_class($analysis['status'], isset($analysis['bulk_ready']) ? (bool) $analysis['bulk_ready'] : null)); ?>"><?php echo esc_html($analysis['status_label']); ?></span><small><?php echo esc_html($analysis['message']); ?></small></td>
                <td><a class="button button-primary" href="<?php echo esc_url($source_url); ?>"><?php esc_html_e('Source Files', 'authentype-font-specimen'); ?></a> <?php if ('adopted' === $analysis['status']) : ?><a class="button" href="<?php echo esc_url(get_edit_post_link($analysis['existing_font_id'], '')); ?>"><?php esc_html_e('Open Athtyp', 'authentype-font-specimen'); ?></a> <a class="button ath-secondary-action" href="<?php echo esc_url($dry_url); ?>"><?php esc_html_e('Recovery', 'authentype-font-specimen'); ?></a><?php else : ?><a class="button ath-secondary-action" href="<?php echo esc_url($dry_url); ?>"><?php esc_html_e('Review', 'authentype-font-specimen'); ?></a><?php endif; ?></td>
            </tr>
            <?php
        }
        if (!$rows) echo '<tr><td colspan="7">' . esc_html__('No products match this view.', 'authentype-font-specimen') . '</td></tr>';
        ?>
        </tbody></table>
        <?php if ($total_pages > 1) : ?>
            <div class="tablenav"><div class="tablenav-pages"><?php echo wp_kses_post(paginate_links(array('base' => add_query_arg('paged', '%#%'), 'format' => '', 'current' => $paged, 'total' => $total_pages, 'add_args' => array('s' => $search, 'ath_status' => $status_filter)))); ?></div></div>
        <?php endif; ?>
    <?php
}



function ath_specimen_adoption_ajax_analysis_payload($analysis) {
    return array(
        'product_id' => (int) ($analysis['product_id'] ?? 0),
        'product_name' => sanitize_text_field((string) ($analysis['product_name'] ?? '')),
        'status' => sanitize_key((string) ($analysis['status'] ?? '')),
        'status_label' => sanitize_text_field((string) ($analysis['status_label'] ?? '')),
        'message' => sanitize_text_field((string) ($analysis['message'] ?? '')),
        'style_attr' => sanitize_title((string) ($analysis['style_attr'] ?? '')),
        'license_attr' => sanitize_title((string) ($analysis['license_attr'] ?? '')),
        'mapping_confidence' => sanitize_key((string) ($analysis['mapping_confidence'] ?? 'none')),
        'bulk_ready' => !empty($analysis['bulk_ready']),
        'bulk_reason' => sanitize_text_field((string) ($analysis['bulk_reason'] ?? '')),
        'sparse_matrix' => !empty($analysis['sparse_matrix']),
        'existing_font_id' => (int) ($analysis['existing_font_id'] ?? 0),
        'candidate_font_id' => (int) ($analysis['candidate_font_id'] ?? 0),
        'counts' => array(
            'styles' => count((array) ($analysis['styles'] ?? array())),
            'licenses' => count((array) ($analysis['licenses'] ?? array())),
            'variations' => (int) ($analysis['variation_count'] ?? 0),
            'pairs' => (int) ($analysis['complete_pair_count'] ?? 0),
            'expected_pairs' => (int) ($analysis['expected_pair_count'] ?? 0),
            'prices' => (int) ($analysis['price_count'] ?? 0),
            'downloads' => (int) ($analysis['download_count'] ?? 0),
        ),
    );
}

add_action('wp_ajax_ath_specimen_adoption_catalog_ids', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);

    $paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
    $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
    $per_page = 100;

    $query = new WP_Query(array(
        'post_type' => 'product',
        'post_status' => array('publish', 'draft', 'private'),
        'orderby' => 'title',
        'order' => 'ASC',
        's' => $search,
        'posts_per_page' => $per_page,
        'paged' => $paged,
        'fields' => 'ids',
    ));
    wp_send_json_success(array(
        'ids' => array_values(array_map('intval', (array) $query->posts)),
        'page' => $paged,
        'total_pages' => max(1, (int) $query->max_num_pages),
        'total' => (int) $query->found_posts,
    ));
});

add_action('wp_ajax_ath_specimen_adoption_scan_product', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);

    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!$product_id || !current_user_can('edit_post', $product_id)) {
        wp_send_json_error(array('message' => __('Permission denied for this product.', 'authentype-font-specimen')), 403);
    }
    $analysis = ath_specimen_adoption_analyze_product($product_id);
    if (is_wp_error($analysis)) wp_send_json_error(array('message' => $analysis->get_error_message()), 400);
    wp_send_json_success(ath_specimen_adoption_ajax_analysis_payload($analysis));
});



add_action('wp_ajax_ath_specimen_adoption_readiness_ids', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);

    $paged = isset($_POST['paged']) ? max(1, absint($_POST['paged'])) : 1;
    $per_page = 100;
    $query = new WP_Query(array(
        'post_type' => 'ath_font',
        'post_status' => array('publish', 'draft', 'private', 'pending', 'future'),
        'posts_per_page' => $per_page,
        'paged' => $paged,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'meta_query' => array(
            array('key' => '_ath_linked_product', 'compare' => 'EXISTS'),
        ),
    ));
    $ids = array();
    foreach ((array) $query->posts as $font_id) {
        $product_id = absint(get_post_meta((int) $font_id, '_ath_linked_product', true));
        if (!$product_id || isset($ids[$product_id]) || !current_user_can('edit_post', $product_id)) continue;
        $ids[$product_id] = $product_id;
    }
    wp_send_json_success(array(
        'ids' => array_values(array_map('intval', $ids)),
        'page' => $paged,
        'total_pages' => max(1, (int) $query->max_num_pages),
        'total' => (int) $query->found_posts,
    ));
});

add_action('wp_ajax_ath_specimen_adoption_readiness_product', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!$product_id || !current_user_can('edit_post', $product_id)) {
        wp_send_json_error(array('message' => __('Permission denied for this product.', 'authentype-font-specimen')), 403);
    }
    $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) wp_send_json_error(array('message' => $dataset->get_error_message()), 400);
    $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
    if (is_wp_error($analysis)) wp_send_json_error(array('message' => $analysis->get_error_message()), 400);
    $result = ath_specimen_adoption_commerce_readiness($product_id, $dataset, $analysis);
    wp_send_json_success(ath_specimen_adoption_readiness_ajax_payload($result));
});


add_action('wp_ajax_ath_specimen_adoption_legacy_delivery_plan', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!$product_id || !current_user_can('edit_post', $product_id)) {
        wp_send_json_error(array('message' => __('Permission denied for this product.', 'authentype-font-specimen')), 403);
    }
    $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) wp_send_json_error(array('message' => $dataset->get_error_message()), 400);
    $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
    if (is_wp_error($analysis)) wp_send_json_error(array('message' => $analysis->get_error_message()), 400);
    $plan = ath_specimen_adoption_legacy_delivery_plan($product_id, $dataset, $analysis);
    wp_send_json_success(ath_specimen_adoption_legacy_delivery_plan_payload($plan));
});

add_action('wp_ajax_ath_specimen_adoption_legacy_delivery_hydrate', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!$product_id || !current_user_can('edit_post', $product_id)) {
        wp_send_json_error(array('message' => __('Permission denied for this product.', 'authentype-font-specimen')), 403);
    }
    $result = ath_specimen_adoption_legacy_delivery_hydrate($product_id);
    if (is_wp_error($result)) wp_send_json_error(array('message' => $result->get_error_message()), 400);
    wp_send_json_success(array(
        'product_id' => (int) ($result['product_id'] ?? 0),
        'font_id' => (int) ($result['font_id'] ?? 0),
        'pairs' => (int) ($result['pairs'] ?? 0),
        'downloads' => (int) ($result['downloads'] ?? 0),
        'total_downloads' => (int) ($result['total_downloads'] ?? 0),
        'snapshot_id' => sanitize_key((string) ($result['snapshot_id'] ?? '')),
        'snapshot_version' => (int) ($result['snapshot_version'] ?? 0),
        'message' => sanitize_text_field((string) ($result['message'] ?? '')),
    ));
});


add_action('wp_ajax_ath_specimen_adoption_legacy_pricing_plan', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!$product_id || !current_user_can('edit_post', $product_id)) {
        wp_send_json_error(array('message' => __('Permission denied for this product.', 'authentype-font-specimen')), 403);
    }
    $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) wp_send_json_error(array('message' => $dataset->get_error_message()), 400);
    $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
    if (is_wp_error($analysis)) wp_send_json_error(array('message' => $analysis->get_error_message()), 400);
    $plan = ath_specimen_adoption_legacy_pricing_plan($product_id, $dataset, $analysis);
    wp_send_json_success(ath_specimen_adoption_legacy_pricing_plan_payload($plan));
});

add_action('wp_ajax_ath_specimen_adoption_legacy_pricing_hydrate', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!$product_id || !current_user_can('edit_post', $product_id)) {
        wp_send_json_error(array('message' => __('Permission denied for this product.', 'authentype-font-specimen')), 403);
    }
    $result = ath_specimen_adoption_legacy_pricing_hydrate($product_id);
    if (is_wp_error($result)) wp_send_json_error(array('message' => $result->get_error_message()), 400);
    wp_send_json_success(array(
        'product_id' => (int) ($result['product_id'] ?? 0),
        'font_id' => (int) ($result['font_id'] ?? 0),
        'pairs' => (int) ($result['pairs'] ?? 0),
        'sales' => (int) ($result['sales'] ?? 0),
        'snapshot_id' => sanitize_key((string) ($result['snapshot_id'] ?? '')),
        'snapshot_version' => (int) ($result['snapshot_version'] ?? 0),
        'message' => sanitize_text_field((string) ($result['message'] ?? '')),
    ));
});


add_action('wp_ajax_ath_specimen_adoption_legacy_woo_reconcile_plan', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!$product_id || !current_user_can('edit_post', $product_id)) wp_send_json_error(array('message' => __('Permission denied for this product.', 'authentype-font-specimen')), 403);
    $dataset = ath_specimen_adoption_load_dataset($product_id);
    if (is_wp_error($dataset)) wp_send_json_error(array('message' => $dataset->get_error_message()), 400);
    $analysis = ath_specimen_adoption_analyze_product($product_id, '', '', $dataset);
    if (is_wp_error($analysis)) wp_send_json_error(array('message' => $analysis->get_error_message()), 400);
    $plan = ath_specimen_adoption_legacy_woo_reconcile_plan($product_id, $dataset, $analysis);
    wp_send_json_success(ath_specimen_adoption_legacy_woo_reconcile_plan_payload($plan));
});

add_action('wp_ajax_ath_specimen_adoption_legacy_woo_reconcile', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    if (!$product_id || !current_user_can('edit_post', $product_id)) wp_send_json_error(array('message' => __('Permission denied for this product.', 'authentype-font-specimen')), 403);
    $result = ath_specimen_adoption_legacy_woo_reconcile($product_id);
    if (is_wp_error($result)) wp_send_json_error(array('message' => $result->get_error_message()), 400);
    wp_send_json_success(array(
        'product_id' => (int) ($result['product_id'] ?? 0),
        'font_id' => (int) ($result['font_id'] ?? 0),
        'pairs' => (int) ($result['pairs'] ?? 0),
        'remaps' => (int) ($result['remaps'] ?? 0),
        'price_updates' => (int) ($result['price_updates'] ?? 0),
        'snapshot_id' => sanitize_key((string) ($result['snapshot_id'] ?? '')),
        'snapshot_version' => (int) ($result['snapshot_version'] ?? 0),
        'message' => sanitize_text_field((string) ($result['message'] ?? '')),
    ));
});


add_action('wp_ajax_ath_specimen_adopt_product', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $style_attr = isset($_POST['style_attr']) ? sanitize_title(wp_unslash($_POST['style_attr'])) : '';
    $license_attr = isset($_POST['license_attr']) ? sanitize_title(wp_unslash($_POST['license_attr'])) : '';
    $require_bulk_ready = !empty($_POST['require_bulk_ready']);
    $result = ath_specimen_adopt_woo_product($product_id, $style_attr, $license_attr, $require_bulk_ready);
    if (is_wp_error($result)) wp_send_json_error(array('message' => $result->get_error_message()), 400);
    $font_id = !empty($result['font_id']) ? (int) $result['font_id'] : 0;
    wp_send_json_success(array(
        'message' => !empty($result['already_adopted']) ? __('Already adopted.', 'authentype-font-specimen') : __('Adopted safely. WooCommerce was not modified.', 'authentype-font-specimen'),
        'font_id' => $font_id,
        'edit_url' => $font_id ? get_edit_post_link($font_id, '') : '',
        'counts' => !empty($result['counts']) ? $result['counts'] : array(),
    ));
});


add_action('wp_ajax_ath_specimen_restore_adoption_snapshot', function () {
    check_ajax_referer('ath_specimen_adoption', 'nonce');
    if (!ath_specimen_adoption_capable()) wp_send_json_error(array('message' => __('Permission denied.', 'authentype-font-specimen')), 403);
    $font_id = isset($_POST['font_id']) ? absint($_POST['font_id']) : 0;
    $result = ath_specimen_adoption_restore_snapshot($font_id);
    if (is_wp_error($result)) wp_send_json_error(array('message' => $result->get_error_message()), 400);
    wp_send_json_success(array(
        'message' => sprintf(__('Pre-adoption Woo state restored. %1$d legacy variations restored; %2$d post-adoption Athtyp variations were disabled, not deleted.', 'authentype-font-specimen'), (int) $result['restored'], (int) $result['disabled_new']),
        'product_id' => (int) $result['product_id'],
    ));
});
