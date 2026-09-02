<?php
defined('ABSPATH') || exit;

/**
 * secure.7 package builder.
 *
 * Instead of creating License × (every style + family) ZIPs, font files are
 * copied once into the protected WooCommerce area and reused by variations.
 * Package bundles are ZIP-compressed from the detected inventory for every
 * product size (1 or many styles), while individual variations keep reusing
 * the protected shared font assets to preserve stable Woo download IDs.
 * License documentation is extracted once per license and attached separately.
 */

function ath_specimen_v7_extension_signature($exts) {
    $exts = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $exts))));
    sort($exts, SORT_STRING);
    return implode('-', $exts);
}

/**
 * Select the real protected assets that a delivery rule is allowed to use.
 * The rule is a filter, not a completeness requirement: if Desktop allows
 * OTF+TTF and the master contains only OTF for a style, the OTF asset remains
 * a valid member of the package inventory.
 */
function ath_specimen_v7_select_assets($assets, $allowed_exts) {
    $allowed = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $allowed_exts))));
    $selected = array();
    foreach ((array) $assets as $asset) {
        $ext = !empty($asset['ext']) ? sanitize_key($asset['ext']) : '';
        if ($ext && in_array($ext, $allowed, true)) $selected[] = $asset;
    }
    return $selected;
}

/** Return a signature from formats that actually exist in selected assets. */
function ath_specimen_v7_asset_extension_signature($assets) {
    $exts = array();
    foreach ((array) $assets as $asset) {
        if (!empty($asset['ext'])) $exts[] = sanitize_key($asset['ext']);
    }
    return ath_specimen_v7_extension_signature($exts);
}

/** Count how many detected styles contribute at least one asset to a package. */
function ath_specimen_v7_package_style_count($assets) {
    $styles = array();
    foreach ((array) $assets as $asset) {
        $key = !empty($asset['style_key']) ? sanitize_key($asset['style_key']) : '';
        if ($key) $styles[$key] = true;
    }
    return count($styles);
}

/**
 * Normalize scalar values to the representation WordPress returns from
 * get_post_meta(). Numeric and boolean scalars are stored in wp_postmeta as
 * strings, while serialized arrays preserve their internal PHP types.
 */
function ath_specimen_v7_normalize_meta_scalar($value) {
    if (null === $value || false === $value) return '';
    if (true === $value) return '1';
    if (is_int($value) || is_float($value)) return (string) $value;
    return $value;
}

/**
 * Verify a post-meta round trip without treating WordPress' normal scalar
 * string coercion as a write failure. Arrays/objects remain strictly checked
 * through the same serialized representation used by WordPress.
 */
function ath_specimen_v7_meta_values_match($stored, $expected) {
    $stored_is_scalar = is_scalar($stored) || null === $stored;
    $expected_is_scalar = is_scalar($expected) || null === $expected;

    if ($stored_is_scalar && $expected_is_scalar) {
        return ath_specimen_v7_normalize_meta_scalar($stored) === ath_specimen_v7_normalize_meta_scalar($expected);
    }

    return maybe_serialize($stored) === maybe_serialize($expected);
}


function ath_specimen_v7_build_lock_option_name($post_id) {
    return 'ath_pkg_mutex_' . md5((string) absint($post_id));
}

function ath_specimen_v7_acquire_build_lock($post_id, $token, $ttl = 0) {
    $post_id = absint($post_id);
    $token = sanitize_text_field((string) $token);
    if (!$post_id || !$token) return false;

    $ttl = max(5 * MINUTE_IN_SECONDS, (int) ($ttl ?: 30 * MINUTE_IN_SECONDS));
    $option_name = ath_specimen_v7_build_lock_option_name($post_id);
    $record = array('token' => $token, 'expires' => time() + $ttl);
    if (add_option($option_name, $record, '', false)) return true;

    $current = get_option($option_name, array());
    if (is_array($current) && !empty($current['token']) && hash_equals((string) $current['token'], $token)) {
        update_option($option_name, $record, false);
        return true;
    }
    if (!is_array($current) || empty($current['expires']) || (int) $current['expires'] <= time()) {
        delete_option($option_name);
        return add_option($option_name, $record, '', false);
    }
    return false;
}

function ath_specimen_v7_release_build_lock($post_id, $token) {
    $post_id = absint($post_id);
    $token = sanitize_text_field((string) $token);
    if (!$post_id) return;
    $option_name = ath_specimen_v7_build_lock_option_name($post_id);
    $current = get_option($option_name, array());
    if (!$token || !is_array($current) || empty($current['token']) || hash_equals((string) $current['token'], $token)) {
        delete_option($option_name);
    }
}

function ath_specimen_v7_validate_unique_style_formats($entries) {
    $seen = array();
    foreach ((array) $entries as $entry) {
        $style = !empty($entry['style']) ? sanitize_text_field($entry['style']) : '';
        $style_key = ath_specimen_package_style_key($style);
        $ext = !empty($entry['ext']) ? sanitize_key($entry['ext']) : '';
        if (!$style_key || !$ext) continue;

        $key = $style_key . '|' . $ext;
        $source = !empty($entry['source_name']) ? sanitize_text_field($entry['source_name']) : (!empty($entry['name']) ? sanitize_text_field($entry['name']) : __('unknown source', 'authentype-font-specimen'));
        if (isset($seen[$key])) {
            return new WP_Error(
                'ath_duplicate_style_format',
                sprintf(
                    __('Duplicate font asset detected for style "%1$s" / %2$s: %3$s and %4$s. Keep only one file for each Style × Format before building.', 'authentype-font-specimen'),
                    $style ?: $style_key,
                    strtoupper($ext),
                    $seen[$key],
                    $source
                ),
                array('style' => $style_key, 'format' => $ext, 'sources' => array($seen[$key], $source))
            );
        }
        $seen[$key] = $source;
    }
    return true;
}

function ath_specimen_v7_cleanup_build_residue($secure_base_dir, $max_age = 0) {
    $secure_base_dir = (string) $secure_base_dir;
    if (!$secure_base_dir || !is_dir($secure_base_dir)) return 0;
    $root_real = realpath($secure_base_dir);
    if (!$root_real) return 0;
    $max_age = max(HOUR_IN_SECONDS, (int) ($max_age ?: 6 * HOUR_IN_SECONDS));
    $cutoff = time() - $max_age;
    $removed = 0;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root_real, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if (!$item->isFile()) continue;
        $name = $item->getFilename();
        if (false === strpos($name, '.staging-') && false === strpos($name, '.backup-')) continue;
        if ($item->getMTime() > $cutoff) continue;
        $path = $item->getPathname();
        $normalized_root = rtrim(wp_normalize_path($root_real), '/') . '/';
        $normalized_path = wp_normalize_path($path);
        if (0 !== strpos($normalized_path, $normalized_root)) continue;
        wp_delete_file($path);
        if (!is_file($path)) $removed++;
    }
    return $removed;
}

function ath_specimen_v7_cleanup_legacy_preview_copies($post_id) {
    $post_id = absint($post_id);
    if (!$post_id) return 0;
    $uploads = wp_get_upload_dir();
    if (empty($uploads['basedir'])) return 0;
    $preview_root = trailingslashit($uploads['basedir']) . 'woocommerce_uploads/authentype-previews';
    $product_dir = trailingslashit($preview_root) . $post_id;
    if (!is_dir($product_dir)) return 0;
    ath_specimen_v7_remove_tree($product_dir, $preview_root);
    return is_dir($product_dir) ? 0 : 1;
}

function ath_specimen_v7_preview_exts($settings) {
    $wanted = !empty($settings['preview_format']) ? sanitize_key($settings['preview_format']) : 'auto';
    // Imagick generally handles OTF/TTF. GD FreeType requires TTF, so prefer it
    // automatically when Imagick is not present.
    if (!class_exists('Imagick') && function_exists('imagettftext')) return array('ttf');
    if ('ttf' === $wanted) return array('ttf', 'otf', 'woff');
    if ('otf' === $wanted) return array('otf', 'ttf', 'woff');
    return array('otf', 'ttf', 'woff');
}



function ath_specimen_v7_remove_tree($path, $root) {
    $root_real = realpath($root);
    $path_real = realpath($path);
    if (!$root_real || !$path_real) return;
    $root_norm = rtrim(wp_normalize_path($root_real), '/') . '/';
    $path_norm = wp_normalize_path($path_real);
    if (0 !== strpos($path_norm . '/', $root_norm) || $path_norm === rtrim($root_norm, '/')) return;
    if (is_dir($path_real)) {
        foreach ((array) scandir($path_real) as $item) {
            if ('.' === $item || '..' === $item) continue;
            ath_specimen_v7_remove_tree($path_real . DIRECTORY_SEPARATOR . $item, $root_real);
        }
        @rmdir($path_real);
    } elseif (is_file($path_real)) {
        wp_delete_file($path_real);
    }
}

function ath_specimen_v7_cleanup_legacy_assets($secure_base_dir) {
    $allowed_dirs = array('files', 'licenses', 'family-packages', 'manifests');
    foreach ((array) scandir($secure_base_dir) as $item) {
        if ('.' === $item || '..' === $item || '.htaccess' === $item || 'index.html' === $item || 'index.php' === $item) continue;
        $path = trailingslashit($secure_base_dir) . $item;
        if (is_dir($path) && in_array($item, $allowed_dirs, true)) continue;
        if (is_file($path) && preg_match('/^\.(htaccess|user\.ini)$/i', $item)) continue;
        ath_specimen_v7_remove_tree($path, $secure_base_dir);
    }
}

function ath_specimen_v7_stage_write($stage, $data) {
    $dir = dirname($stage);
    if (!is_dir($dir) && !wp_mkdir_p($dir)) return false;
    // The secure family root is protected once before staging begins. Avoid
    // repeating .htaccess/index writes for hundreds of child style folders.
    $written = @file_put_contents($stage, $data, LOCK_EX);
    return false !== $written && $written === strlen($data);
}

function ath_specimen_v7_build_family_zip($stage_path, $family, $label, $assets, $required_exts) {
    if (!class_exists('ZipArchive')) return new WP_Error('ath_zip_missing', __('PHP ZipArchive is not available on this server.', 'authentype-font-specimen'));
    $selected = ath_specimen_v7_select_assets($assets, $required_exts);
    if (empty($selected)) return new WP_Error('ath_family_zip_empty', __('No matching font files were found for the family package.', 'authentype-font-specimen'));

    $zip = new ZipArchive();
    if (true !== $zip->open($stage_path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        return new WP_Error('ath_family_zip_create', __('Could not create the shared family package.', 'authentype-font-specimen'));
    }
    $root = sanitize_file_name($family . '-' . $label);
    $manifest = array();
    foreach ($selected as $asset) {
        if (empty($asset['stage']) || !is_file($asset['stage'])) continue;
        $target = $root . '/' . ath_specimen_package_format_group($asset['ext'], $family) . '/' . ath_specimen_package_style_group($asset['style'], $asset['ext'], $family) . '/' . $asset['name'];
        if (!$zip->addFile($asset['stage'], $target)) {
            $zip->close();
            @wp_delete_file($stage_path);
            return new WP_Error('ath_family_zip_add', __('A font file could not be added to the shared family package.', 'authentype-font-specimen'));
        }
        $manifest[] = $asset['name'];
    }
    $zip->addFromString($root . '/Documentation/Font_List.txt', implode("\n", $manifest) . "\n");
    if (!$zip->close() || !is_file($stage_path) || filesize($stage_path) <= 0) {
        @wp_delete_file($stage_path);
        return new WP_Error('ath_family_zip_finalize', __('Could not finalize the shared family package.', 'authentype-font-specimen'));
    }
    return true;
}

function ath_specimen_v7_stage_license_docs($secure_base_dir, $secure_base_url, $build_id, $license_key, $license_label, $template_docs, &$staged_files, &$downloads) {
    if (empty($template_docs)) return 0;
    $count = 0;
    foreach ($template_docs as $index => $doc) {
        if (empty($doc['name']) || !isset($doc['data'])) continue;
        $section = !empty($doc['target_dir']) && 'License' === $doc['target_dir'] ? 'License' : 'Documentation';
        $name = sanitize_file_name($doc['name']);
        if (!$name) continue;
        $dir = $secure_base_dir . '/licenses/' . sanitize_file_name($license_key) . '/' . $section;
        $url_dir = $secure_base_url . '/licenses/' . rawurlencode($license_key) . '/' . rawurlencode($section);
        if (!is_dir($dir)) wp_mkdir_p($dir);
        $final = $dir . '/' . $name;
        $stage = $final . '.staging-' . $build_id;
        if (!ath_specimen_v7_stage_write($stage, $doc['data'])) {
            return new WP_Error('ath_license_doc_write', sprintf(__('Could not stage a license document for %s.', 'authentype-font-specimen'), $license_label));
        }
        $staged_files[] = array('stage' => $stage, 'final' => $final);
        $downloads[] = array(
            'download_name' => $license_label . ' — ' . preg_replace('/\.[^.]+$/', '', $name),
            'download_file' => $url_dir . '/' . rawurlencode($name),
            'style_variation_value' => '',
            'license_variation_value' => $license_key,
        );
        $count++;
    }
    return $count;
}


function ath_specimen_v7_carry_legacy_download_ids($post_id, $downloads) {
    $old = ath_specimen_get_meta($post_id, '_ath_product_downloads', array());
    if (empty($old) || !is_array($old) || empty($downloads) || !is_array($downloads)) return $downloads;
    $pools = array();
    foreach ($old as $row) {
        if (empty($row['download_id'])) continue;
        $style = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : '';
        $license = !empty($row['license_variation_value']) ? ath_specimen_slug($row['license_variation_value']) : '';
        if (!$license) continue;
        $key = $style . '|' . $license;
        if (!isset($pools[$key])) $pools[$key] = array();
        $pools[$key][] = sanitize_text_field((string) $row['download_id']);
    }
    foreach ($downloads as &$row) {
        $style = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : '';
        $license = !empty($row['license_variation_value']) ? ath_specimen_slug($row['license_variation_value']) : '';
        $key = $style . '|' . $license;
        if (!empty($pools[$key])) {
            $row['download_id'] = array_shift($pools[$key]);
            $row['legacy_download'] = 1;
        }
    }
    unset($row);
    return $downloads;
}

function ath_specimen_build_font_packages_v7($post_id, $settings) {
    $post_id = absint($post_id);
    $lock_token = wp_generate_password(40, false, false);
    if (!ath_specimen_v7_acquire_build_lock($post_id, $lock_token, 30 * MINUTE_IN_SECONDS)) {
        return new WP_Error('ath_package_build_locked', __('A secure asset build is already running for this product. Wait for it to finish before starting another build.', 'authentype-font-specimen'));
    }

    try {
        $product_id = absint(get_post_meta($post_id, '_ath_linked_product', true));
        if (function_exists('ath_specimen_stability_cross_engine_guard')) {
            $guard = ath_specimen_stability_cross_engine_guard($post_id, $product_id, array('build'));
            if (is_wp_error($guard)) return $guard;
        }
        return ath_specimen_build_font_packages_v7_unlocked($post_id, $settings);
    } finally {
        ath_specimen_v7_release_build_lock($post_id, $lock_token);
    }
}

function ath_specimen_build_font_packages_v7_unlocked($post_id, $settings) {
    $post_id = absint($post_id);
    $font_zip_url = isset($settings['font_zip']) ? esc_url_raw($settings['font_zip']) : '';
    $zip_path = ath_specimen_uploaded_url_to_path($font_zip_url);
    if (!$zip_path) return new WP_Error('ath_package_zip_path', __('Upload the font family ZIP to Media Library first.', 'authentype-font-specimen'));
    if (!class_exists('ZipArchive')) return new WP_Error('ath_zip_missing', __('PHP ZipArchive is not available on this server.', 'authentype-font-specimen'));
    if (!class_exists('Imagick') && !function_exists('imagettftext')) {
        return new WP_Error('ath_renderer_missing', __('Secure.7 requires PHP Imagick or GD with FreeType before protected preview assets can be built.', 'authentype-font-specimen'));
    }

    $family = !empty($settings['family_name']) ? sanitize_text_field($settings['family_name']) : get_the_title($post_id);
    $family = trim($family) ?: 'FontFamily';
    $family_slug = sanitize_file_name($family);
    $entries = ath_specimen_read_font_family_zip($zip_path, $family);
    if (is_wp_error($entries)) return $entries;
    $unique_inventory = ath_specimen_v7_validate_unique_style_formats($entries);
    if (is_wp_error($unique_inventory)) return $unique_inventory;
    $nested_archive_indexes = array();
    foreach ($entries as $entry) {
        if (isset($entry['nested_zip_index']) && isset($entry['zip_index'])) {
            $nested_archive_indexes[(int) $entry['zip_index']] = true;
        }
    }
    $nested_archive_count = count($nested_archive_indexes);

    $upload_dir = wp_upload_dir();
    if (empty($upload_dir['basedir']) || empty($upload_dir['baseurl'])) return new WP_Error('ath_upload_dir', __('WordPress upload directory is unavailable.', 'authentype-font-specimen'));

    $secure_token = ath_specimen_package_secure_token(isset($settings['secure_token']) ? $settings['secure_token'] : '');
    $settings['secure_token'] = $secure_token;
    $short_token = substr($secure_token, 0, 8);
    $secure_base_dir = trailingslashit($upload_dir['basedir']) . 'woocommerce_uploads/authentype-packages/' . $post_id . '/' . $secure_token . '/' . $family_slug;
    $secure_base_url = trailingslashit($upload_dir['baseurl']) . 'woocommerce_uploads/authentype-packages/' . $post_id . '/' . rawurlencode($secure_token) . '/' . rawurlencode($family_slug);
    wp_mkdir_p($secure_base_dir);
    ath_specimen_protect_download_dir(trailingslashit($upload_dir['basedir']) . 'woocommerce_uploads');
    ath_specimen_protect_download_dir(trailingslashit($upload_dir['basedir']) . 'woocommerce_uploads/authentype-packages');
    ath_specimen_protect_download_dir($secure_base_dir);

    $build_id = sanitize_key(str_replace('-', '', wp_generate_uuid4()));
    $staged_files = array();
    $assets = array();
    $assets_by_style = array();
    $seen_style_formats = array();

    // Open the source archive once; do not reopen it for every style/format.
    $source = new ZipArchive();
    if (true !== $source->open($zip_path)) return new WP_Error('ath_zip_open', __('Could not reopen the font family ZIP.', 'authentype-font-specimen'));
    $nested_sources = array();
    $close_nested_sources = function () use (&$nested_sources) {
        foreach ($nested_sources as $nested_source) {
            if (!empty($nested_source['zip']) && $nested_source['zip'] instanceof ZipArchive) {
                $nested_source['zip']->close();
            }
            if (!empty($nested_source['temp']) && is_file($nested_source['temp'])) {
                @unlink($nested_source['temp']);
            }
        }
        $nested_sources = array();
    };
    foreach ($entries as $entry) {
        $style_key = ath_specimen_package_style_key($entry['style']);
        if (!$style_key || empty($entry['ext']) || !isset($entry['zip_index'])) continue;
        $style_format_key = $style_key . '|' . $entry['ext'];
        if (isset($seen_style_formats[$style_format_key])) {
            $close_nested_sources();
            $source->close();
            ath_specimen_cleanup_staged_files($staged_files);
            return new WP_Error('ath_duplicate_style_format', sprintf(__('Duplicate Style × Format reached secure staging for %1$s / %2$s.', 'authentype-font-specimen'), $entry['style'], strtoupper($entry['ext'])));
        }
        $seen_style_formats[$style_format_key] = true;

        if (isset($entry['nested_zip_index'])) {
            $outer_index = (int) $entry['zip_index'];
            if (!isset($nested_sources[$outer_index])) {
                $max_nested = max(1024, (int) apply_filters('authentype_specimen_nested_zip_max_archive_size', 64 * 1024 * 1024));
                $expected_nested = !empty($entry['nested_zip_size']) ? (int) $entry['nested_zip_size'] : 0;
                $temp = ath_specimen_zip_index_to_private_temp($source, $outer_index, $expected_nested, $max_nested);
                if (is_wp_error($temp)) {
                    $close_nested_sources();
                    $source->close();
                    ath_specimen_cleanup_staged_files($staged_files);
                    return $temp;
                }
                $child = new ZipArchive();
                if (true !== $child->open($temp)) {
                    @unlink($temp);
                    $close_nested_sources();
                    $source->close();
                    ath_specimen_cleanup_staged_files($staged_files);
                    return new WP_Error('ath_nested_zip_reopen', sprintf(__('Could not reopen nested ZIP %s during secure build.', 'authentype-font-specimen'), !empty($entry['nested_zip_name']) ? $entry['nested_zip_name'] : ''));
                }
                $nested_sources[$outer_index] = array('zip' => $child, 'temp' => $temp);
            }

            $child = $nested_sources[$outer_index]['zip'];
            $inner_index = (int) $entry['nested_zip_index'];
            $inner_stat = $child->statIndex($inner_index);
            $max_font_size = max(1024, (int) apply_filters('authentype_specimen_zip_max_entry_size', 32 * 1024 * 1024));
            if (!$inner_stat || empty($inner_stat['size']) || (int) $inner_stat['size'] > $max_font_size || (!empty($entry['size']) && (int) $entry['size'] !== (int) $inner_stat['size'])) {
                $data = false;
            } else {
                $data = $child->getFromIndex($inner_index, $max_font_size);
            }
        } else {
            $data = $source->getFromIndex((int) $entry['zip_index']);
        }

        if (false === $data || '' === $data) {
            $close_nested_sources();
            $source->close();
            ath_specimen_cleanup_staged_files($staged_files);
            return new WP_Error('ath_asset_read', sprintf(__('Could not read %s from the source ZIP.', 'authentype-font-specimen'), $entry['name']));
        }
        $name = sanitize_file_name($entry['name']);
        $asset_dir = $secure_base_dir . '/files/' . sanitize_file_name($style_key);
        $asset_url_dir = $secure_base_url . '/files/' . rawurlencode($style_key);
        if (!is_dir($asset_dir)) wp_mkdir_p($asset_dir);
        $final = $asset_dir . '/' . $name;
        $stage = $final . '.staging-' . $build_id;
        if (!ath_specimen_v7_stage_write($stage, $data)) {
            unset($data);
            $close_nested_sources();
            $source->close();
            ath_specimen_cleanup_staged_files(array_merge($staged_files, array(array('stage' => $stage))));
            return new WP_Error('ath_asset_write', sprintf(__('Could not stage %s.', 'authentype-font-specimen'), $name));
        }
        unset($data);
        $staged_files[] = array('stage' => $stage, 'final' => $final);
        $asset = array(
            'style_key' => $style_key,
            'style' => $entry['style'],
            'ext' => $entry['ext'],
            'name' => $name,
            'stage' => $stage,
            'final' => $final,
            'url' => $asset_url_dir . '/' . rawurlencode($name),
        );
        $assets[] = $asset;
        if (empty($assets_by_style[$style_key])) $assets_by_style[$style_key] = array();
        $assets_by_style[$style_key][] = $asset;
    }
    $close_nested_sources();
    $source->close();
    if (empty($assets_by_style)) {
        ath_specimen_cleanup_staged_files($staged_files);
        return new WP_Error('ath_assets_empty', __('No usable font assets were staged.', 'authentype-font-specimen'));
    }

    $style_keys = array_keys($assets_by_style);
    usort($style_keys, function ($a, $b) use ($assets_by_style) {
        $la = !empty($assets_by_style[$a][0]['style']) ? $assets_by_style[$a][0]['style'] : $a;
        $lb = !empty($assets_by_style[$b][0]['style']) ? $assets_by_style[$b][0]['style'] : $b;
        $delta = ath_specimen_package_style_sort_key($la) - ath_specimen_package_style_sort_key($lb);
        return $delta ?: strcasecmp($la, $lb);
    });
    $styles = array_map(function ($key) use ($assets_by_style) { return $assets_by_style[$key][0]['style']; }, $style_keys);

    $package_licenses = ath_specimen_package_builder_licenses($settings);
    $existing_license_ui = array();
    foreach ((array) ath_specimen_get_meta($post_id, '_ath_license_options', array()) as $existing_license) {
        $existing_value = !empty($existing_license['license_variation_value']) ? ath_specimen_slug($existing_license['license_variation_value']) : '';
        if (!$existing_value) continue;
        $existing_license_ui[$existing_value] = array(
            'description' => !empty($existing_license['license_description']) ? sanitize_textarea_field($existing_license['license_description']) : '',
            'group' => !empty($existing_license['license_group']) && in_array($existing_license['license_group'], ath_specimen_license_group_options(), true) ? $existing_license['license_group'] : '',
            'featured' => !empty($existing_license['license_featured']) ? 1 : 0,
            'checkout_type' => !empty($existing_license['license_checkout_type']) ? $existing_license['license_checkout_type'] : '',
            'icon' => !empty($existing_license['license_icon']) && isset(ath_specimen_license_icon_options()[$existing_license['license_icon']]) ? $existing_license['license_icon'] : '',
        );
    }
    $rules = array();
    foreach ($package_licenses as $license) {
        $label = !empty($license['license_label']) ? sanitize_text_field($license['license_label']) : '';
        $value = !empty($license['license_variation_value']) ? ath_specimen_slug($license['license_variation_value']) : ath_specimen_slug($label);
        if (!$label || !$value) continue;
        $rules[$value] = array(
            'label' => $label,
            'exts' => ath_specimen_package_license_exts($value),
            'template_zip' => !empty($license['template_zip']) ? esc_url_raw($license['template_zip']) : '',
            'description' => !empty($license['license_description']) ? sanitize_textarea_field($license['license_description']) : (!empty($existing_license_ui[$value]['description']) ? $existing_license_ui[$value]['description'] : ''),
            'group' => !empty($existing_license_ui[$value]['group']) ? $existing_license_ui[$value]['group'] : '',
            'featured' => !empty($existing_license_ui[$value]['featured']) ? 1 : 0,
            'checkout_type' => !empty($existing_license_ui[$value]['checkout_type']) ? $existing_license_ui[$value]['checkout_type'] : '',
            'icon' => !empty($existing_license_ui[$value]['icon']) ? $existing_license_ui[$value]['icon'] : '',
        );
    }
    if (empty($rules)) {
        ath_specimen_cleanup_staged_files($staged_files);
        return new WP_Error('ath_package_licenses', __('Add at least one license row before building packages.', 'authentype-font-specimen'));
    }

    $style_count = count($styles);
    if ($style_count < 1) {
        ath_specimen_cleanup_staged_files($staged_files);
        return new WP_Error('ath_style_inventory_empty', __('No font styles were detected in the master ZIP.', 'authentype-font-specimen'));
    }

    // Full Style is a real multi-style family package and is available per
    // license only when that license's allowed delivery formats contribute at
    // least one real font asset for every detected style. A license with a
    // partial family must never receive a misleading Full Style ZIP/variation.
    $master_has_multiple_styles = $style_count > 1;
    $package_inventory = array();
    $full_style_license_inventory = array();
    if ($master_has_multiple_styles) {
        foreach ($rules as $license_key => $rule) {
            $selected_assets = ath_specimen_v7_select_assets($assets, $rule['exts']);
            $signature = ath_specimen_v7_asset_extension_signature($selected_assets);
            $selected_style_count = ath_specimen_v7_package_style_count($selected_assets);
            $is_complete_family = !empty($selected_assets) && $signature && $selected_style_count === $style_count;

            $package_inventory[$license_key] = array(
                'formats' => $signature ? explode('-', $signature) : array(),
                'styles' => $selected_style_count,
                'assets' => count($selected_assets),
                'full_style' => $is_complete_family ? 1 : 0,
            );

            if ($is_complete_family) {
                $full_style_license_inventory[$license_key] = array(
                    'assets' => $selected_assets,
                    'signature' => $signature,
                );
            }
        }
    }
    $has_package_bundle = !empty($full_style_license_inventory);

    // Preview records point to protected local font assets; URLs never reach the frontend.
    $preview_exts = ath_specimen_v7_preview_exts($settings);
    $font_styles = array();
    foreach ($style_keys as $style_index => $style_key) {
        $style_assets = $assets_by_style[$style_key];
        $preview = null;
        $preview_errors = array();
        foreach ($preview_exts as $ext) {
            foreach ($style_assets as $asset) {
                if ($asset['ext'] !== $ext) continue;
                if (!function_exists('ath_specimen_server_render_image')) {
                    $preview_errors[] = __('The secure preview renderer is not loaded.', 'authentype-font-specimen');
                    continue;
                }

                // Do not persist a preview source merely because its extension
                // looks compatible. Exercise the exact staged file with the
                // active server renderer first: some ImageMagick builds reject
                // otherwise valid OTF/WOFF files, while GD only supports TTF.
                $probe = ath_specimen_server_render_image(
                    $asset['stage'],
                    'Preview Aa 123',
                    360,
                    32,
                    1.18,
                    '#111111',
                    '#ffffff',
                    'text'
                );
                if (!is_wp_error($probe) && is_string($probe) && strlen($probe) >= 100) {
                    $preview = $asset;
                    unset($probe);
                    break 2;
                }
                $preview_errors[] = is_wp_error($probe)
                    ? $probe->get_error_message()
                    : sprintf(__('The %s renderer probe returned no usable image.', 'authentype-font-specimen'), strtoupper($ext));
                unset($probe);
            }
        }
        if (!$preview) {
            ath_specimen_cleanup_staged_files($staged_files);
            $detail = $preview_errors ? ' ' . implode(' ', array_unique($preview_errors)) : '';
            return new WP_Error(
                'ath_preview_renderer_incompatible',
                sprintf(__('No preview source for %s could be rendered on this server.', 'authentype-font-specimen'), $style_assets[0]['style']) . $detail
            );
        }
        $label = $style_assets[0]['style'];
        $font_styles[] = array(
            'style_name' => $label,
            'font_file' => $preview['url'],
            'font_weight' => ath_specimen_package_weight($label),
            'font_style' => false !== stripos($label, 'italic') || false !== stripos($label, 'oblique') ? 'italic' : 'normal',
            'style_variation_value' => ath_specimen_slug($label),
            'default_selected' => 0,
            'is_package' => 0,
        );
    }

    // Keep the previous default only when it still points to a real detected
    // style. Otherwise prefer Regular when present, then fall back to the first
    // actual style. Full Style is never used as an automatic specimen default.
    $actual_style_values = array();
    foreach ($font_styles as $row) {
        $value = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : '';
        if ($value) $actual_style_values[] = $value;
    }
    $existing_default_style = ath_specimen_slug((string) ath_specimen_get_meta($post_id, '_ath_default_specimen_style', ''));
    if ($existing_default_style && in_array($existing_default_style, $actual_style_values, true)) {
        $default_specimen_style = $existing_default_style;
    } elseif (in_array('regular', $actual_style_values, true)) {
        $default_specimen_style = 'regular';
    } else {
        $default_specimen_style = !empty($actual_style_values[0]) ? $actual_style_values[0] : '';
    }
    foreach ($font_styles as &$font_style_row) {
        $font_style_row['default_selected'] = !empty($font_style_row['style_variation_value']) && ath_specimen_slug($font_style_row['style_variation_value']) === $default_specimen_style ? 1 : 0;
    }
    unset($font_style_row);

    if ($has_package_bundle) {
        $font_styles[] = array(
            'style_name' => 'Full Style',
            'font_file' => !empty($font_styles[0]['font_file']) ? $font_styles[0]['font_file'] : '',
            'font_weight' => 1,
            'font_style' => 'normal',
            'style_variation_value' => 'full-style',
            'default_selected' => 0,
            'is_package' => 1,
        );
    }

    $licenses = array();
    $downloads = array();
    $csv_rows = array();
    $license_doc_count = 0;

    foreach ($rules as $license_key => $rule) {
        $licenses[] = array(
            'license_label' => $rule['label'],
            'license_variation_value' => $license_key,
            'license_description' => !empty($rule['description']) ? $rule['description'] : '',
            'license_group' => !empty($rule['group']) ? $rule['group'] : '',
            'license_featured' => !empty($rule['featured']) ? 1 : 0,
            'license_checkout_type' => !empty($rule['checkout_type']) ? $rule['checkout_type'] : '',
            'license_icon' => !empty($rule['icon']) ? $rule['icon'] : '',
        );
        $docs = array();
        if (!empty($rule['template_zip'])) {
            $docs = ath_specimen_read_template_zip($rule['template_zip'], $rule['label']);
            if (is_wp_error($docs)) {
                ath_specimen_cleanup_staged_files($staged_files);
                return $docs;
            }
        }
        $doc_result = ath_specimen_v7_stage_license_docs($secure_base_dir, $secure_base_url, $build_id, $license_key, $rule['label'], $docs, $staged_files, $downloads);
        if (is_wp_error($doc_result)) {
            ath_specimen_cleanup_staged_files($staged_files);
            return $doc_result;
        }
        $license_doc_count += $doc_result;

        foreach ($style_keys as $style_key) {
            $style_label = $assets_by_style[$style_key][0]['style'];
            $style_value = ath_specimen_slug($style_label);
            foreach ($assets_by_style[$style_key] as $asset) {
                if (!in_array($asset['ext'], $rule['exts'], true)) continue;
                $downloads[] = array(
                    'download_name' => $style_label . ' — ' . strtoupper($asset['ext']),
                    'download_file' => $asset['url'],
                    'style_variation_value' => $style_value,
                    'license_variation_value' => $license_key,
                );
            }
        }
    }

    // Build Full Style packages only for licenses that cover every detected
    // style. Packages remain deduplicated by the formats that actually exist.
    $family_package_map = array();
    $family_zip_count = 0;
    foreach ($full_style_license_inventory as $license_key => $delivery) {
        if (empty($rules[$license_key])) continue;
        $rule = $rules[$license_key];
        $selected_assets = !empty($delivery['assets']) && is_array($delivery['assets']) ? $delivery['assets'] : array();
        $signature = !empty($delivery['signature']) ? sanitize_text_field($delivery['signature']) : '';
        if (!$signature || empty($selected_assets)) continue;

        if (!isset($family_package_map[$signature])) {
            $label = ucwords(str_replace('-', ' ', $signature)) . ' Package';
            $dir = $secure_base_dir . '/family-packages';
            $url_dir = $secure_base_url . '/family-packages';
            if (!is_dir($dir)) wp_mkdir_p($dir);
            $name = sanitize_file_name($family . '-Full-Style-' . $signature . '-' . $short_token . '.zip');
            $final = $dir . '/' . $name;
            $stage = $final . '.staging-' . $build_id;
            $actual_exts = explode('-', $signature);
            $built = ath_specimen_v7_build_family_zip($stage, $family, $label, $assets, $actual_exts);
            if (is_wp_error($built)) {
                ath_specimen_cleanup_staged_files(array_merge($staged_files, array(array('stage' => $stage))));
                return $built;
            }
            $staged_files[] = array('stage' => $stage, 'final' => $final);
            $family_package_map[$signature] = $url_dir . '/' . rawurlencode($name);
            $family_zip_count++;
        }
        $downloads[] = array(
            'download_name' => $rule['label'] . ' — Full Style Package',
            'download_file' => $family_package_map[$signature],
            'style_variation_value' => 'full-style',
            'license_variation_value' => $license_key,
        );
    }

    // Inventory authority is the actual protected delivery mapping. License
    // documentation has an empty style and therefore cannot make an otherwise
    // missing Style × License pair purchasable.
    $delivery_pairs = array();
    foreach ($downloads as $download_row) {
        $style_value = !empty($download_row['style_variation_value']) ? ath_specimen_slug($download_row['style_variation_value']) : '';
        $license_value = !empty($download_row['license_variation_value']) ? ath_specimen_slug($download_row['license_variation_value']) : '';
        if ($style_value && $license_value && !empty($download_row['download_file'])) {
            $delivery_pairs[$style_value . '|' . $license_value] = true;
        }
    }

    foreach ($rules as $license_key => $rule) {
        foreach ($styles as $style_label) {
            $style_value = ath_specimen_slug($style_label);
            if (empty($delivery_pairs[$style_value . '|' . $license_key])) continue;
            $csv_rows[] = array($family, $rule['label'], $style_label, strtoupper($family_slug . '-' . $license_key . '-' . $style_value), '', 'Pricing managed in Athtyp Price Matrix');
        }
        if (!empty($delivery_pairs['full-style|' . $license_key])) {
            $csv_rows[] = array($family, $rule['label'], 'Full Style', strtoupper($family_slug . '-' . $license_key . '-full-style'), '', 'Pricing managed in Athtyp Price Matrix');
        }
    }

    $csv_dir = $secure_base_dir . '/manifests';
    if (!is_dir($csv_dir)) wp_mkdir_p($csv_dir);
    $csv_name = sanitize_file_name($family . '-WooCommerce-Variations-' . $short_token . '.csv');
    $csv_path = $csv_dir . '/' . $csv_name;
    $csv_url = $secure_base_url . '/manifests/' . rawurlencode($csv_name);
    $csv_stage = $csv_path . '.staging-' . $build_id;
    $handle = @fopen($csv_stage, 'x');
    if (!$handle) {
        ath_specimen_cleanup_staged_files($staged_files);
        return new WP_Error('ath_csv_write', __('Could not create the variation manifest.', 'authentype-font-specimen'));
    }
    fputcsv($handle, array('Family', 'License', 'Style', 'SKU', 'Price', 'Download Architecture'));
    foreach ($csv_rows as $row) fputcsv($handle, $row);
    fclose($handle);
    $staged_files[] = array('stage' => $csv_stage, 'final' => $csv_path);

    $committed = ath_specimen_commit_staged_files($staged_files, $build_id);
    if (is_wp_error($committed)) return $committed;

    $dimension_keys = function ($style_rows, $license_rows) {
        $style_keys = array();
        foreach ((array) $style_rows as $row) {
            $key = !empty($row['style_variation_value']) ? ath_specimen_slug($row['style_variation_value']) : ath_specimen_slug($row['style_name'] ?? '');
            if ($key) $style_keys[] = $key;
        }
        $license_keys = array();
        foreach ((array) $license_rows as $row) {
            $key = !empty($row['license_variation_value']) ? ath_specimen_slug($row['license_variation_value']) : ath_specimen_slug($row['license_label'] ?? '');
            if ($key) $license_keys[] = $key;
        }
        sort($style_keys, SORT_STRING);
        sort($license_keys, SORT_STRING);
        return array($style_keys, $license_keys);
    };
    $old_dimensions = $dimension_keys(
        ath_specimen_get_meta($post_id, '_ath_font_styles', array()),
        ath_specimen_get_meta($post_id, '_ath_license_options', array())
    );
    $new_dimensions = $dimension_keys($font_styles, $licenses);
    $pricing_structure_changed = $old_dimensions !== $new_dimensions;

    $old_product_downloads = ath_specimen_get_meta($post_id, '_ath_product_downloads', array());
    $old_delivery_pairs = function_exists('ath_specimen_product_download_delivery_pairs')
        ? ath_specimen_product_download_delivery_pairs(is_array($old_product_downloads) ? $old_product_downloads : array())
        : array();
    $old_delivery_keys = array_keys($old_delivery_pairs);
    $new_delivery_keys = array_keys($delivery_pairs);
    sort($old_delivery_keys, SORT_STRING);
    sort($new_delivery_keys, SORT_STRING);
    $delivery_availability_changed = $old_delivery_keys !== $new_delivery_keys;
    $pricing_inventory_changed = $pricing_structure_changed || $delivery_availability_changed;

    $reconciled_price_matrix = null;
    if ($pricing_inventory_changed) {
        $existing_price_matrix = ath_specimen_get_meta($post_id, '_ath_price_matrix', array());
        $reconciled_price_matrix = function_exists('ath_specimen_reconcile_price_matrix_dimensions')
            ? ath_specimen_reconcile_price_matrix_dimensions($existing_price_matrix, $font_styles, $licenses, $delivery_pairs)
            : ath_specimen_sanitize_price_matrix(is_array($existing_price_matrix) ? $existing_price_matrix : array());
    }

    // Preserve legacy Woo download identifiers where a matching Style × License
    // mapping still exists, protecting existing customer download permissions.
    $downloads = ath_specimen_v7_carry_legacy_download_ids($post_id, $downloads);

    $new_meta = array(
        '_ath_package_builder' => ath_specimen_sanitize_package_builder($settings),
        '_ath_font_styles' => ath_specimen_sanitize_styles($font_styles),
        '_ath_license_options' => ath_specimen_sanitize_licenses($licenses),
        '_ath_product_downloads' => ath_specimen_sanitize_product_downloads($downloads),
        '_ath_default_specimen_style' => $default_specimen_style,
        '_ath_package_architecture' => 'shared-assets-v7',
        '_ath_asset_config_hash' => ath_specimen_asset_config_hash($settings),
        '_ath_asset_built_at' => time(),
    );
    if ($pricing_inventory_changed && is_array($reconciled_price_matrix)) {
        $new_meta['_ath_price_matrix'] = $reconciled_price_matrix;
        $new_meta['_ath_pricing_hash'] = ath_specimen_pricing_hash($reconciled_price_matrix);
    }
    $old_meta = array();
    foreach ($new_meta as $key => $value) {
        $old_meta[$key] = array('exists' => metadata_exists('post', $post_id, $key), 'value' => get_post_meta($post_id, $key, true));
    }
    $meta_ok = true;
    $failed_meta_key = '';
    foreach ($new_meta as $key => $value) {
        update_post_meta($post_id, $key, $value);
        $stored_value = get_post_meta($post_id, $key, true);
        if (!ath_specimen_v7_meta_values_match($stored_value, $value)) {
            $meta_ok = false;
            $failed_meta_key = $key;
            break;
        }
    }
    if (!$meta_ok) {
        foreach ($old_meta as $key => $old) $old['exists'] ? update_post_meta($post_id, $key, $old['value']) : delete_post_meta($post_id, $key);
        ath_specimen_rollback_committed_files($committed);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf('[Authentype] Package metadata commit verification failed for post %d at key %s.', (int) $post_id, $failed_meta_key));
        }
        return new WP_Error(
            'ath_package_metadata',
            __('Package metadata could not be committed. Existing files and metadata were restored.', 'authentype-font-specimen'),
            array('meta_key' => $failed_meta_key, 'post_id' => (int) $post_id)
        );
    }
    ath_specimen_finalize_committed_files($committed);
    if ($pricing_inventory_changed) update_post_meta($post_id, '_ath_pricing_needs_review', '1');
    ath_specimen_v7_cleanup_legacy_assets($secure_base_dir);
    ath_specimen_v7_cleanup_build_residue($secure_base_dir);
    ath_specimen_v7_cleanup_legacy_preview_copies($post_id);
    if (function_exists('ath_specimen_clear_font_metadata_cache')) ath_specimen_clear_font_metadata_cache($post_id);
    $prewarm = max(0, (int) apply_filters('authentype_specimen_metadata_prewarm_limit', 1, $post_id));
    if ($prewarm && function_exists('ath_specimen_refresh_font_metadata_cache')) ath_specimen_refresh_font_metadata_cache($post_id, $font_styles, $prewarm);
    ath_specimen_remove_legacy_public_preview_dir($post_id, $family_slug);

    $source_removed = false;
    if (apply_filters('authentype_specimen_delete_public_source_after_build', true, $post_id, $font_zip_url)) {
        $source_removed = ath_specimen_delete_public_source_zip($font_zip_url);
    }
    if ($source_removed) {
        $settings['font_zip'] = '';
        update_post_meta($post_id, '_ath_package_builder', ath_specimen_sanitize_package_builder($settings));
    }

    return array(
        'created' => count($assets) + $license_doc_count + $family_zip_count + 1,
        'styles' => count($styles),
        'csv_url' => $csv_url,
        'source_removed' => $source_removed,
        'font_assets' => count($assets),
        'family_zips' => $family_zip_count,
        'license_docs' => $license_doc_count,
        'variations' => count($delivery_pairs),
        'package_inventory' => $package_inventory,
        'nested_archives' => $nested_archive_count,
    );
}
