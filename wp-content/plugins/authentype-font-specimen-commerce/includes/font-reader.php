<?php
defined('ABSPATH') || exit;

function ath_specimen_u16($data, $offset) {
    if ($offset < 0 || $offset + 2 > strlen($data)) return 0;
    $value = unpack('n', substr($data, $offset, 2));
    return $value ? $value[1] : 0;
}

function ath_specimen_u32($data, $offset) {
    if ($offset < 0 || $offset + 4 > strlen($data)) return 0;
    $value = unpack('N', substr($data, $offset, 4));
    return $value ? $value[1] : 0;
}

function ath_specimen_s16($data, $offset) {
    $value = ath_specimen_u16($data, $offset);
    return $value >= 0x8000 ? $value - 0x10000 : $value;
}

function ath_specimen_s32($data, $offset) {
    $value = ath_specimen_u32($data, $offset);
    return $value >= 0x80000000 ? $value - 0x100000000 : $value;
}

function ath_specimen_fixed_16_16($data, $offset) {
    return ath_specimen_s32($data, $offset) / 65536;
}

function ath_specimen_get_table_map($font_data) {
    if (!is_string($font_data) || strlen($font_data) < 12) return array();

    $signature = substr($font_data, 0, 4);
    $max_tables = (int) apply_filters('authentype_specimen_font_max_tables', 256);
    $max_table_size = (int) apply_filters('authentype_specimen_font_max_table_size', 32 * 1024 * 1024);

    if ('wOF2' === $signature) {
        return array();
    }

    if ('wOFF' === $signature) {
        $num_tables = ath_specimen_u16($font_data, 12);
        $tables = array();
        if ($num_tables <= 0 || $num_tables > $max_tables || 44 + ($num_tables * 20) > strlen($font_data)) return array();

        for ($i = 0; $i < $num_tables; $i++) {
            $entry = 44 + ($i * 20);
            if ($entry + 20 > strlen($font_data)) break;

            $tag = substr($font_data, $entry, 4);
            $offset = ath_specimen_u32($font_data, $entry + 4);
            $comp_length = ath_specimen_u32($font_data, $entry + 8);
            $orig_length = ath_specimen_u32($font_data, $entry + 12);
            if ($comp_length <= 0 || $orig_length <= 0 || $orig_length > $max_table_size || $offset > strlen($font_data) || $comp_length > strlen($font_data) - $offset) continue;
            $table_data = substr($font_data, $offset, $comp_length);

            if ($comp_length !== $orig_length && function_exists('gzuncompress')) {
                $decoded = @gzuncompress($table_data, $max_table_size);
                if (false !== $decoded && strlen($decoded) === $orig_length) {
                    $table_data = $decoded;
                } else {
                    continue;
                }
            }

            if ($table_data) {
                $tables[$tag] = $table_data;
            }
        }

        return $tables;
    }

    $num_tables = ath_specimen_u16($font_data, 4);
    $tables = array();
    if ($num_tables <= 0 || $num_tables > $max_tables || 12 + ($num_tables * 16) > strlen($font_data)) return array();

    for ($i = 0; $i < $num_tables; $i++) {
        $entry = 12 + ($i * 16);
        if ($entry + 16 > strlen($font_data)) break;

        $tag = substr($font_data, $entry, 4);
        $offset = ath_specimen_u32($font_data, $entry + 8);
        $length = ath_specimen_u32($font_data, $entry + 12);

        if ($offset > 0 && $length > 0 && $length <= $max_table_size && $offset <= strlen($font_data) && $length <= strlen($font_data) - $offset) {
            $tables[$tag] = substr($font_data, $offset, $length);
        }
    }

    return $tables;
}

function ath_specimen_parse_layout_features($table_data) {
    if (!$table_data || strlen($table_data) < 8) return array();

    $feature_list_offset = ath_specimen_u16($table_data, 6);
    if (!$feature_list_offset || $feature_list_offset + 2 > strlen($table_data)) return array();

    $feature_count = ath_specimen_u16($table_data, $feature_list_offset);
    $features = array();

    for ($i = 0; $i < $feature_count; $i++) {
        $record = $feature_list_offset + 2 + ($i * 6);
        if ($record + 6 > strlen($table_data)) break;

        $tag = substr($table_data, $record, 4);
        if (preg_match('/^[A-Za-z0-9 ]{4}$/', $tag)) {
            $features[] = trim($tag);
        }
    }

    return array_values(array_unique($features));
}

function ath_specimen_parse_layout_feature_lookups($table_data) {
    if (!$table_data || strlen($table_data) < 8) return array();

    $feature_list_offset = ath_specimen_u16($table_data, 6);
    if (!$feature_list_offset || $feature_list_offset + 2 > strlen($table_data)) return array();

    $feature_count = ath_specimen_u16($table_data, $feature_list_offset);
    $features = array();

    for ($i = 0; $i < $feature_count; $i++) {
        $record = $feature_list_offset + 2 + ($i * 6);
        if ($record + 6 > strlen($table_data)) break;

        $tag = trim(substr($table_data, $record, 4));
        $feature_offset = $feature_list_offset + ath_specimen_u16($table_data, $record + 4);
        if (!$tag || $feature_offset + 4 > strlen($table_data)) continue;

        $lookup_count = ath_specimen_u16($table_data, $feature_offset + 2);
        $features[$tag] = array();

        for ($j = 0; $j < $lookup_count; $j++) {
            $features[$tag][] = ath_specimen_u16($table_data, $feature_offset + 4 + ($j * 2));
        }
    }

    return $features;
}

function ath_specimen_parse_coverage($table_data, $offset) {
    if (!$offset || $offset + 4 > strlen($table_data)) return array();

    $format = ath_specimen_u16($table_data, $offset);
    $glyphs = array();

    if (1 === $format) {
        $count = ath_specimen_u16($table_data, $offset + 2);
        for ($i = 0; $i < $count; $i++) {
            $glyphs[] = ath_specimen_u16($table_data, $offset + 4 + ($i * 2));
        }
    }

    if (2 === $format) {
        $count = ath_specimen_u16($table_data, $offset + 2);
        for ($i = 0; $i < $count; $i++) {
            $record = $offset + 4 + ($i * 6);
            if ($record + 6 > strlen($table_data)) break;

            $start = ath_specimen_u16($table_data, $record);
            $end = ath_specimen_u16($table_data, $record + 2);
            if ($end < $start || $end - $start > 4096) continue;

            for ($glyph = $start; $glyph <= $end; $glyph++) {
                $glyphs[] = $glyph;
            }
        }
    }

    return $glyphs;
}

function ath_specimen_parse_lookup_offsets($table_data) {
    if (!$table_data || strlen($table_data) < 10) return array();

    $lookup_list_offset = ath_specimen_u16($table_data, 8);
    if (!$lookup_list_offset || $lookup_list_offset + 2 > strlen($table_data)) return array();

    $lookup_count = ath_specimen_u16($table_data, $lookup_list_offset);
    $offsets = array();

    for ($i = 0; $i < $lookup_count; $i++) {
        $offsets[$i] = $lookup_list_offset + ath_specimen_u16($table_data, $lookup_list_offset + 2 + ($i * 2));
    }

    return $offsets;
}

function ath_specimen_codepoint_to_utf8($codepoint) {
    if ($codepoint <= 0x7f) return chr($codepoint);
    if ($codepoint <= 0x7ff) return chr(0xc0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3f));
    if ($codepoint <= 0xffff) return chr(0xe0 | ($codepoint >> 12)) . chr(0x80 | (($codepoint >> 6) & 0x3f)) . chr(0x80 | ($codepoint & 0x3f));
    return chr(0xf0 | ($codepoint >> 18)) . chr(0x80 | (($codepoint >> 12) & 0x3f)) . chr(0x80 | (($codepoint >> 6) & 0x3f)) . chr(0x80 | ($codepoint & 0x3f));
}

function ath_specimen_has_legacy_kern_table($kern_data) {
    if (!$kern_data || strlen($kern_data) < 4) return false;
    return 0 === ath_specimen_u16($kern_data, 0) && ath_specimen_u16($kern_data, 2) > 0;
}

function ath_specimen_add_cmap_mapping(&$codepoints, &$glyph_map, &$glyph_codepoints, $codepoint, $glyph_id) {
    if ($codepoint < 0 || !$glyph_id) return;

    $codepoints[$codepoint] = true;
    if (!isset($glyph_map[$glyph_id])) {
        $glyph_map[$glyph_id] = ath_specimen_codepoint_to_utf8($codepoint);
    }
    if (!isset($glyph_codepoints[$glyph_id])) {
        $glyph_codepoints[$glyph_id] = (int) $codepoint;
    }
}

function ath_specimen_add_cmap_range(&$codepoints, $start, $end) {
    if ($start < 0 || $end < $start) return;
    if ($end - $start > 4096) return;

    for ($codepoint = $start; $codepoint <= $end; $codepoint++) {
        $codepoints[$codepoint] = true;
    }
}

function ath_specimen_parse_cmap_data($cmap_data) {
    if (!$cmap_data || strlen($cmap_data) < 4) return array('codepoints' => array(), 'glyph_map' => array(), 'glyph_codepoints' => array());

    $num_tables = ath_specimen_u16($cmap_data, 2);
    $subtables = array();
    $codepoints = array();
    $glyph_map = array();
    $glyph_codepoints = array();

    for ($i = 0; $i < $num_tables; $i++) {
        $record = 4 + ($i * 8);
        if ($record + 8 > strlen($cmap_data)) break;

        $platform_id = ath_specimen_u16($cmap_data, $record);
        $encoding_id = ath_specimen_u16($cmap_data, $record + 2);
        $offset = ath_specimen_u32($cmap_data, $record + 4);

        if ($offset && $offset + 2 <= strlen($cmap_data)) {
            $format = ath_specimen_u16($cmap_data, $offset);
            $score = ($platform_id === 3 ? 10 : 0) + ($encoding_id === 10 ? 5 : 0) + ($format === 12 ? 3 : 0);
            $subtables[] = array('offset' => $offset, 'format' => $format, 'score' => $score);
        }
    }

    usort($subtables, function ($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    foreach ($subtables as $subtable) {
        $offset = $subtable['offset'];
        $format = $subtable['format'];

        if (4 === $format) {
            $seg_count = ath_specimen_u16($cmap_data, $offset + 6) / 2;
            $end_codes = $offset + 14;
            $start_codes = $end_codes + ($seg_count * 2) + 2;
            $id_deltas = $start_codes + ($seg_count * 2);
            $id_range_offsets = $id_deltas + ($seg_count * 2);

            for ($i = 0; $i < $seg_count; $i++) {
                $start = ath_specimen_u16($cmap_data, $start_codes + ($i * 2));
                $end = ath_specimen_u16($cmap_data, $end_codes + ($i * 2));
                if (0xffff === $start && 0xffff === $end) continue;
                if ($end < $start || $end - $start > 4096) continue;

                $id_delta = ath_specimen_u16($cmap_data, $id_deltas + ($i * 2));
                $id_range_offset_pos = $id_range_offsets + ($i * 2);
                $id_range_offset = ath_specimen_u16($cmap_data, $id_range_offset_pos);

                for ($codepoint = $start; $codepoint <= $end; $codepoint++) {
                    if (0 === $id_range_offset) {
                        $glyph_id = ($codepoint + $id_delta) & 0xffff;
                    } else {
                        $glyph_index_offset = $id_range_offset_pos + $id_range_offset + (($codepoint - $start) * 2);
                        $glyph_id = ath_specimen_u16($cmap_data, $glyph_index_offset);
                        if (0 !== $glyph_id) {
                            $glyph_id = ($glyph_id + $id_delta) & 0xffff;
                        }
                    }

                    if (0 !== $glyph_id) {
                        ath_specimen_add_cmap_mapping($codepoints, $glyph_map, $glyph_codepoints, $codepoint, $glyph_id);
                    }
                }
            }
        }

        if (12 === $format) {
            $group_count = ath_specimen_u32($cmap_data, $offset + 12);
            for ($i = 0; $i < $group_count; $i++) {
                $group = $offset + 16 + ($i * 12);
                if ($group + 12 > strlen($cmap_data)) break;

                $start = ath_specimen_u32($cmap_data, $group);
                $end = ath_specimen_u32($cmap_data, $group + 4);
                $start_glyph_id = ath_specimen_u32($cmap_data, $group + 8);
                if ($end >= $start && $end - $start <= 4096) {
                    for ($codepoint = $start; $codepoint <= $end; $codepoint++) {
                        ath_specimen_add_cmap_mapping($codepoints, $glyph_map, $glyph_codepoints, $codepoint, $start_glyph_id + ($codepoint - $start));
                    }
                }
            }
        }
    }

    return array('codepoints' => $codepoints, 'glyph_map' => $glyph_map, 'glyph_codepoints' => $glyph_codepoints);
}

function ath_specimen_parse_cmap($cmap_data) {
    $parsed = ath_specimen_parse_cmap_data($cmap_data);
    return $parsed['codepoints'];
}

function ath_specimen_parse_gsub_ligatures($gsub_data, $glyph_map) {
    if (!$gsub_data || empty($glyph_map)) return array();

    $feature_lookups = ath_specimen_parse_layout_feature_lookups($gsub_data);
    $lookup_offsets = ath_specimen_parse_lookup_offsets($gsub_data);
    $ligature_features = array('liga', 'dlig', 'rlig', 'clig');
    $lookup_indices = array();

    foreach ($ligature_features as $feature) {
        if (empty($feature_lookups[$feature])) continue;
        foreach ($feature_lookups[$feature] as $lookup_index) {
            $lookup_indices[$lookup_index] = true;
        }
    }

    if (empty($lookup_indices)) return array();

    $ligatures = array();

    foreach (array_keys($lookup_indices) as $lookup_index) {
        if (!isset($lookup_offsets[$lookup_index])) continue;

        $lookup_offset = $lookup_offsets[$lookup_index];
        if ($lookup_offset + 6 > strlen($gsub_data)) continue;

        $lookup_type = ath_specimen_u16($gsub_data, $lookup_offset);
        if (4 !== $lookup_type) continue;

        $subtable_count = ath_specimen_u16($gsub_data, $lookup_offset + 4);
        for ($i = 0; $i < $subtable_count; $i++) {
            $subtable_offset = $lookup_offset + ath_specimen_u16($gsub_data, $lookup_offset + 6 + ($i * 2));
            if ($subtable_offset + 6 > strlen($gsub_data)) continue;

            $format = ath_specimen_u16($gsub_data, $subtable_offset);
            if (1 !== $format) continue;

            $coverage = ath_specimen_parse_coverage($gsub_data, $subtable_offset + ath_specimen_u16($gsub_data, $subtable_offset + 2));
            $lig_set_count = ath_specimen_u16($gsub_data, $subtable_offset + 4);

            for ($set = 0; $set < $lig_set_count; $set++) {
                if (empty($coverage[$set]) || empty($glyph_map[$coverage[$set]])) continue;

                $lig_set_offset = $subtable_offset + ath_specimen_u16($gsub_data, $subtable_offset + 6 + ($set * 2));
                if ($lig_set_offset + 2 > strlen($gsub_data)) continue;

                $ligature_count = ath_specimen_u16($gsub_data, $lig_set_offset);
                for ($j = 0; $j < $ligature_count; $j++) {
                    $ligature_offset = $lig_set_offset + ath_specimen_u16($gsub_data, $lig_set_offset + 2 + ($j * 2));
                    if ($ligature_offset + 4 > strlen($gsub_data)) continue;

                    $component_count = ath_specimen_u16($gsub_data, $ligature_offset + 2);
                    if ($component_count < 2 || $component_count > 12) continue;

                    $sequence = $glyph_map[$coverage[$set]];
                    $complete = true;

                    for ($component = 1; $component < $component_count; $component++) {
                        $glyph_id = ath_specimen_u16($gsub_data, $ligature_offset + 4 + (($component - 1) * 2));
                        if (empty($glyph_map[$glyph_id])) {
                            $complete = false;
                            break;
                        }
                        $sequence .= $glyph_map[$glyph_id];
                    }

                    if ($complete) {
                        $ligatures[$sequence] = true;
                    }
                }
            }
        }
    }

    return array_keys($ligatures);
}

function ath_specimen_utf8_codepoint($char) {
    $bytes = array_values(unpack('C*', $char));
    $count = count($bytes);

    if (1 === $count) return $bytes[0];
    if (2 === $count) return (($bytes[0] & 0x1f) << 6) | ($bytes[1] & 0x3f);
    if (3 === $count) return (($bytes[0] & 0x0f) << 12) | (($bytes[1] & 0x3f) << 6) | ($bytes[2] & 0x3f);
    if (4 === $count) return (($bytes[0] & 0x07) << 18) | (($bytes[1] & 0x3f) << 12) | (($bytes[2] & 0x3f) << 6) | ($bytes[3] & 0x3f);

    return 0;
}

function ath_specimen_supported_sample($sample, $codepoints) {
    if (empty($codepoints)) return '';

    $chars = preg_split('//u', $sample, -1, PREG_SPLIT_NO_EMPTY);
    if (!$chars) return '';

    $supported = '';
    foreach ($chars as $char) {
        if (preg_match('/^\s$/u', $char)) {
            $supported .= $char;
            continue;
        }

        if (isset($codepoints[ath_specimen_utf8_codepoint($char)])) {
            $supported .= $char;
        }
    }

    return trim(preg_replace('/\s+/u', ' ', $supported));
}


function ath_specimen_unicode_label($codepoint) {
    $codepoint = (int) $codepoint;
    if ($codepoint < 0) return '';
    $hex = strtoupper(dechex($codepoint));
    $hex = str_pad($hex, $codepoint > 0xFFFF ? 6 : 4, '0', STR_PAD_LEFT);
    return 'U+' . $hex;
}

function ath_specimen_build_glyph_items($glyph_count, $glyph_map, $glyph_codepoints = array()) {
    $glyph_count = max(0, (int) $glyph_count);
    $items = array();
    if ($glyph_count < 1) return $items;

    $glyph_map = is_array($glyph_map) ? $glyph_map : array();
    $glyph_codepoints = is_array($glyph_codepoints) ? $glyph_codepoints : array();
    ksort($glyph_map, SORT_NUMERIC);

    for ($gid = 0; $gid < $glyph_count; $gid++) {
        $text = isset($glyph_map[$gid]) ? (string) $glyph_map[$gid] : '';
        $type = '' !== $text ? 'unicode' : 'unencoded';
        $label = 'GID ' . $gid;
        if (0 === $gid) {
            $label .= ' · .notdef';
        } elseif ('unicode' === $type && isset($glyph_codepoints[$gid])) {
            $label .= ' · ' . ath_specimen_unicode_label($glyph_codepoints[$gid]);
        }
        $items[] = array(
            'gid' => $gid,
            'text' => $text,
            'label' => $label,
            'type' => $type,
        );
    }

    return $items;
}

function ath_specimen_get_font_info($url) {
    $font_file = ath_specimen_get_font_file_data($url);
    if (!$font_file) {
        return array('features' => array(), 'codepoints' => array(), 'ligatures' => array(), 'format' => '', 'tech' => array(), 'languages' => array(), 'scripts' => array(), 'glyph_codepoints' => array());
    }

    $tables = ath_specimen_get_table_map($font_file['data']);
    $features = array();
    $cmap = !empty($tables['cmap']) ? ath_specimen_parse_cmap_data($tables['cmap']) : array('codepoints' => array(), 'glyph_map' => array(), 'glyph_codepoints' => array());

    foreach (array('GSUB', 'GPOS') as $table_name) {
        if (!empty($tables[$table_name])) {
            $features = array_merge($features, ath_specimen_parse_layout_features($tables[$table_name]));
        }
    }

    if (!empty($tables['kern']) && ath_specimen_has_legacy_kern_table($tables['kern'])) {
        $features[] = 'kern';
    }

    $tech = ath_specimen_font_tech_info($tables, $font_file['data'], is_scalar($url) ? (string) $url : '');
    $unicode_characters = !empty($cmap['codepoints']) ? count($cmap['codepoints']) : 0;
    $encoded_glyphs = !empty($cmap['glyph_map']) ? count($cmap['glyph_map']) : 0;
    $glyph_count = !empty($tech['glyph_count']) ? (int) $tech['glyph_count'] : 0;
    $tech['unicode_characters'] = $unicode_characters;
    $tech['encoded_glyphs'] = $encoded_glyphs;
    $tech['unencoded_glyphs'] = max(0, $glyph_count - $encoded_glyphs);
    $languages = ath_specimen_detect_languages($cmap['codepoints']);
    $scripts = ath_specimen_font_scripts($cmap['codepoints']);
    $tech['language_count'] = count($languages);
    $tech['script_count'] = count($scripts);
    return array(
        'features' => array_values(array_unique($features)),
        'codepoints' => $cmap['codepoints'],
        'ligatures' => !empty($tables['GSUB']) ? ath_specimen_parse_gsub_ligatures($tables['GSUB'], $cmap['glyph_map']) : array(),
        'format' => substr($font_file['data'], 0, 4),
        'tech' => $tech,
        'languages' => $languages,
        'scripts' => $scripts,
        // secure.7.3.12 stores only encoded GID→Unicode mappings. Full Glyph
        // pages synthesize GID rows on demand, avoiding huge per-glyph JSON
        // objects for fonts with tens of thousands of glyphs.
        'glyph_codepoints' => !empty($cmap['glyph_codepoints']) ? array_map('intval', $cmap['glyph_codepoints']) : array(),
    );
}

function ath_specimen_font_utf16be_to_utf8($value) {
    if (!is_string($value) || '' === $value) return '';
    if (function_exists('mb_convert_encoding')) {
        $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-16BE');
        if (is_string($converted)) return trim($converted);
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-16BE', 'UTF-8//IGNORE', $value);
        if (is_string($converted)) return trim($converted);
    }
    return '';
}

function ath_specimen_parse_name_table($name_data) {
    if (!is_string($name_data) || strlen($name_data) < 6) return array();
    $count = ath_specimen_u16($name_data, 2);
    $storage = ath_specimen_u16($name_data, 4);
    if ($count < 1 || $count > 4096 || $storage >= strlen($name_data)) return array();

    $wanted = array(
        0 => 'copyright',
        1 => 'family',
        2 => 'subfamily',
        3 => 'unique_id',
        4 => 'full_name',
        5 => 'version',
        6 => 'postscript',
        7 => 'trademark',
        8 => 'manufacturer',
        9 => 'designer',
        10 => 'description',
        11 => 'vendor_url',
        12 => 'designer_url',
        13 => 'license_description',
        14 => 'license_url',
        16 => 'typographic_family',
        17 => 'typographic_subfamily',
        25 => 'variations_postscript_prefix',
    );
    $names = array();
    $score = array();
    for ($i = 0; $i < $count; $i++) {
        $offset = 6 + ($i * 12);
        if ($offset + 12 > strlen($name_data)) break;
        $platform = ath_specimen_u16($name_data, $offset);
        $encoding = ath_specimen_u16($name_data, $offset + 2);
        $language = ath_specimen_u16($name_data, $offset + 4);
        $name_id = ath_specimen_u16($name_data, $offset + 6);
        $length = ath_specimen_u16($name_data, $offset + 8);
        $string_offset = ath_specimen_u16($name_data, $offset + 10);
        if (!isset($wanted[$name_id]) || $length < 1) continue;
        $start = $storage + $string_offset;
        if ($start < 0 || $start + $length > strlen($name_data)) continue;
        $raw = substr($name_data, $start, $length);
        $value = '';
        $candidate_score = 1;
        if (0 === $platform || 3 === $platform) {
            $value = ath_specimen_font_utf16be_to_utf8($raw);
            $candidate_score = (0x0409 === $language || 0 === $language) ? 10 : 7;
        } elseif (1 === $platform) {
            $value = preg_replace('/[^\x20-\x7E]/', '', $raw);
            $candidate_score = 3;
        }
        $value = trim((string) $value);
        $key = $wanted[$name_id];
        if ($value && (!isset($score[$key]) || $candidate_score > $score[$key])) {
            $names[$key] = $value;
            $score[$key] = $candidate_score;
        }
    }
    return $names;
}

function ath_specimen_font_format_label($font_data, $tables = array(), $url = '') {
    $signature = is_string($font_data) && strlen($font_data) >= 4 ? substr($font_data, 0, 4) : '';
    $ext = strtolower(pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
    if ('wOFF' === $signature) {
        $flavor = strlen($font_data) >= 8 ? substr($font_data, 4, 4) : '';
        return 'OTTO' === $flavor ? 'WOFF (OpenType CFF)' : 'WOFF (TrueType)';
    }
    if ('wOF2' === $signature) return 'WOFF2';
    if ('OTTO' === $signature || isset($tables['CFF ']) || isset($tables['CFF2'])) return 'OpenType CFF';
    if ("\x00\x01\x00\x00" === $signature || 'true' === strtolower($signature) || isset($tables['glyf'])) return 'OpenType TrueType';
    if ('otf' === $ext) return 'OpenType';
    if ('ttf' === $ext) return 'TrueType';
    return $ext ? strtoupper($ext) : 'OpenType';
}


function ath_specimen_weight_class_label($value) {
    $value = (int) $value;
    $labels = array(100 => 'Thin', 200 => 'Extra Light', 300 => 'Light', 400 => 'Regular', 500 => 'Medium', 600 => 'Semi Bold', 700 => 'Bold', 800 => 'Extra Bold', 900 => 'Black');
    if (!$value) return '';
    return $value . (isset($labels[$value]) ? ' · ' . $labels[$value] : '');
}

function ath_specimen_width_class_label($value) {
    $value = (int) $value;
    $labels = array(1 => 'Ultra Condensed', 2 => 'Extra Condensed', 3 => 'Condensed', 4 => 'Semi Condensed', 5 => 'Normal', 6 => 'Semi Expanded', 7 => 'Expanded', 8 => 'Extra Expanded', 9 => 'Ultra Expanded');
    if (!$value) return '';
    return $value . (isset($labels[$value]) ? ' · ' . $labels[$value] : '');
}

function ath_specimen_embedding_label($fs_type) {
    $fs_type = (int) $fs_type;
    if (0 === $fs_type) return 'Installable embedding';
    $labels = array();
    if ($fs_type & 0x0002) $labels[] = 'Restricted license embedding';
    if ($fs_type & 0x0004) $labels[] = 'Preview & Print embedding';
    if ($fs_type & 0x0008) $labels[] = 'Editable embedding';
    if ($fs_type & 0x0100) $labels[] = 'No subsetting';
    if ($fs_type & 0x0200) $labels[] = 'Bitmap embedding only';
    return $labels ? implode(' · ', $labels) : 'Embedding flags: 0x' . strtoupper(str_pad(dechex($fs_type), 4, '0', STR_PAD_LEFT));
}

function ath_specimen_parse_fvar_axes($fvar_data) {
    if (!is_string($fvar_data) || strlen($fvar_data) < 16) return array();
    $axes_offset = ath_specimen_u16($fvar_data, 4);
    $axis_count = ath_specimen_u16($fvar_data, 8);
    $axis_size = ath_specimen_u16($fvar_data, 10);
    if ($axis_count < 1 || $axis_count > 64 || $axis_size < 20 || $axes_offset < 16) return array();
    $labels = array('wght' => 'Weight', 'wdth' => 'Width', 'opsz' => 'Optical Size', 'slnt' => 'Slant', 'ital' => 'Italic', 'GRAD' => 'Grade');
    $axes = array();
    for ($i = 0; $i < $axis_count; $i++) {
        $offset = $axes_offset + ($i * $axis_size);
        if ($offset + 20 > strlen($fvar_data)) break;
        $tag = substr($fvar_data, $offset, 4);
        if (!preg_match('/^[\x20-\x7E]{4}$/', $tag)) continue;
        $axes[] = array(
            'tag' => $tag,
            'name' => isset($labels[$tag]) ? $labels[$tag] : strtoupper($tag),
            'min' => round(ath_specimen_fixed_16_16($fvar_data, $offset + 4), 4),
            'default' => round(ath_specimen_fixed_16_16($fvar_data, $offset + 8), 4),
            'max' => round(ath_specimen_fixed_16_16($fvar_data, $offset + 12), 4),
        );
    }
    return $axes;
}

function ath_specimen_font_scripts($codepoints) {
    if (empty($codepoints) || !is_array($codepoints)) return array();
    $ranges = array(
        'Latin' => array(array(0x0041, 0x024F), array(0x1E00, 0x1EFF)),
        'Greek' => array(array(0x0370, 0x03FF), array(0x1F00, 0x1FFF)),
        'Cyrillic' => array(array(0x0400, 0x052F), array(0x2DE0, 0x2DFF), array(0xA640, 0xA69F)),
        'Armenian' => array(array(0x0530, 0x058F)),
        'Hebrew' => array(array(0x0590, 0x05FF)),
        'Arabic' => array(array(0x0600, 0x06FF), array(0x0750, 0x077F), array(0x08A0, 0x08FF)),
        'Devanagari' => array(array(0x0900, 0x097F)),
        'Bengali' => array(array(0x0980, 0x09FF)),
        'Gurmukhi' => array(array(0x0A00, 0x0A7F)),
        'Gujarati' => array(array(0x0A80, 0x0AFF)),
        'Odia' => array(array(0x0B00, 0x0B7F)),
        'Tamil' => array(array(0x0B80, 0x0BFF)),
        'Telugu' => array(array(0x0C00, 0x0C7F)),
        'Kannada' => array(array(0x0C80, 0x0CFF)),
        'Malayalam' => array(array(0x0D00, 0x0D7F)),
        'Sinhala' => array(array(0x0D80, 0x0DFF)),
        'Thai' => array(array(0x0E00, 0x0E7F)),
        'Lao' => array(array(0x0E80, 0x0EFF)),
        'Tibetan' => array(array(0x0F00, 0x0FFF)),
        'Myanmar' => array(array(0x1000, 0x109F)),
        'Georgian' => array(array(0x10A0, 0x10FF), array(0x2D00, 0x2D2F)),
        'Ethiopic' => array(array(0x1200, 0x137F)),
        'Khmer' => array(array(0x1780, 0x17FF)),
        'Hiragana' => array(array(0x3040, 0x309F)),
        'Katakana' => array(array(0x30A0, 0x30FF), array(0x31F0, 0x31FF)),
        'CJK Ideographs' => array(array(0x3400, 0x4DBF), array(0x4E00, 0x9FFF)),
        'Hangul' => array(array(0x1100, 0x11FF), array(0x3130, 0x318F), array(0xAC00, 0xD7AF)),
    );
    $counts = array_fill_keys(array_keys($ranges), 0);
    foreach (array_keys($codepoints) as $cp) {
        $cp = (int) $cp;
        foreach ($ranges as $script => $script_ranges) {
            foreach ($script_ranges as $range) {
                if ($cp >= $range[0] && $cp <= $range[1]) { $counts[$script]++; break; }
            }
        }
    }
    $scripts = array();
    foreach ($counts as $script => $count) {
        if ($count >= 3) $scripts[] = array('name' => $script, 'characters' => $count);
    }
    usort($scripts, function ($a, $b) { return $b['characters'] <=> $a['characters']; });
    return $scripts;
}

function ath_specimen_language_catalog() {
    $latin = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $catalog = array(
        'af' => array('Afrikaans', 'Latin', $latin . 'ÁáÉéÈèÊêËëÎîÏïÔôÖöÛûÜü'),
        'sq' => array('Albanian', 'Latin', $latin . 'ÇçËë'),
        'az' => array('Azerbaijani', 'Latin', $latin . 'ÇçƏəĞğİıÖöŞşÜü'),
        'eu' => array('Basque', 'Latin', $latin . 'Ññ'),
        'bs' => array('Bosnian', 'Latin', $latin . 'ČčĆćĐđŠšŽž'),
        'br' => array('Breton', 'Latin', $latin . 'ÂâÊêÎîÔôÛûÙùÑñ'),
        'ca' => array('Catalan', 'Latin', $latin . 'ÀàÇçÈèÉéÍíÏïÒòÓóÚúÜü·'),
        'co' => array('Corsican', 'Latin', $latin . 'ÀàÈèÉéÌìÒòÙù'),
        'hr' => array('Croatian', 'Latin', $latin . 'ČčĆćĐđŠšŽž'),
        'cs' => array('Czech', 'Latin', $latin . 'ÁáČčĎďÉéĚěÍíŇňÓóŘřŠšŤťÚúŮůÝýŽž'),
        'da' => array('Danish', 'Latin', $latin . 'ÆæØøÅå'),
        'nl' => array('Dutch', 'Latin', $latin . 'ÁáÉéÈèËëÏïÓóÖöÜü'),
        'en' => array('English', 'Latin', $latin),
        'eo' => array('Esperanto', 'Latin', $latin . 'ĈĉĜĝĤĥĴĵŜŝŬŭ'),
        'et' => array('Estonian', 'Latin', $latin . 'ÄäÖöÕõÜüŠšŽž'),
        'fo' => array('Faroese', 'Latin', $latin . 'ÁáÐðÍíÓóÚúÝýÆæØø'),
        'fi' => array('Finnish', 'Latin', $latin . 'ÅåÄäÖöŠšŽž'),
        'fr' => array('French', 'Latin', $latin . 'ÀàÂâÆæÇçÉéÈèÊêËëÎîÏïÔôŒœÙùÛûÜüŸÿ'),
        'fy' => array('Frisian', 'Latin', $latin . 'ÂâÊêÉéÛûÚú'),
        'gl' => array('Galician', 'Latin', $latin . 'ÁáÉéÍíÑñÓóÚúÜü'),
        'de' => array('German', 'Latin', $latin . 'ÄäÖöÜüẞß'),
        'kl' => array('Greenlandic', 'Latin', $latin . 'ÆæØøÅå'),
        'ht' => array('Haitian Creole', 'Latin', $latin . 'ÀàÈèÒò'),
        'ha' => array('Hausa', 'Latin', $latin . 'ƁɓƊɗƘƙƳƴ'),
        'haw' => array('Hawaiian', 'Latin', $latin . 'ĀāĒēĪīŌōŪūʻ'),
        'hu' => array('Hungarian', 'Latin', $latin . 'ÁáÉéÍíÓóÖöŐőÚúÜüŰű'),
        'is' => array('Icelandic', 'Latin', $latin . 'ÁáÐðÉéÍíÓóÖöÞþÚúÝýÆæ'),
        'ig' => array('Igbo', 'Latin', $latin . 'ỊịỌọỤụṄṅ'),
        'id' => array('Indonesian', 'Latin', $latin),
        'ga' => array('Irish', 'Latin', $latin . 'ÁáÉéÍíÓóÚú'),
        'it' => array('Italian', 'Latin', $latin . 'ÀàÈèÉéÌìÍíÎîÒòÓóÙùÚú'),
        'ku' => array('Kurdish (Latin)', 'Latin', $latin . 'ÇçÊêÎîŞşÛû'),
        'lv' => array('Latvian', 'Latin', $latin . 'ĀāČčĒēĢģĪīĶķĻļŅņŠšŪūŽž'),
        'lt' => array('Lithuanian', 'Latin', $latin . 'ĄąČčĘęĖėĮįŠšŲųŪūŽž'),
        'lb' => array('Luxembourgish', 'Latin', $latin . 'ÄäËëÉé'),
        'ms' => array('Malay', 'Latin', $latin),
        'mt' => array('Maltese', 'Latin', $latin . 'ĊċĠġĦħŻż'),
        'mi' => array('Māori', 'Latin', $latin . 'ĀāĒēĪīŌōŪū'),
        'sr-Latn' => array('Serbian (Latin)', 'Latin', $latin . 'ČčĆćĐđŠšŽž'),
        'cnr-Latn' => array('Montenegrin (Latin)', 'Latin', $latin . 'ČčĆćĐđŠšŽžŚśŹź'),
        'nv' => array('Navajo', 'Latin', $latin . 'ÁáĄąÉéĘęÍíŁłÓóǪǫ'),
        'se' => array('Northern Sámi', 'Latin', $latin . 'ÁáČčĐđŊŋŠšŦŧŽž'),
        'nb' => array('Norwegian Bokmål', 'Latin', $latin . 'ÆæØøÅå'),
        'nn' => array('Norwegian Nynorsk', 'Latin', $latin . 'ÆæØøÅå'),
        'pl' => array('Polish', 'Latin', $latin . 'ĄąĆćĘęŁłŃńÓóŚśŹźŻż'),
        'pt' => array('Portuguese', 'Latin', $latin . 'ÁáÀàÂâÃãÇçÉéÊêÍíÓóÔôÕõÚúÜü'),
        'ro' => array('Romanian', 'Latin', $latin . 'ĂăÂâÎîȘșȚț'),
        'gd' => array('Scottish Gaelic', 'Latin', $latin . 'ÀàÈèÌìÒòÙù'),
        'sk' => array('Slovak', 'Latin', $latin . 'ÁáÄäČčĎďÉéÍíĹĺĽľŇňÓóÔôŔŕŠšŤťÚúÝýŽž'),
        'sl' => array('Slovenian', 'Latin', $latin . 'ČčŠšŽž'),
        'so' => array('Somali', 'Latin', $latin),
        'es' => array('Spanish', 'Latin', $latin . 'ÁáÉéÍíÑñÓóÚúÜü'),
        'sw' => array('Swahili', 'Latin', $latin),
        'sv' => array('Swedish', 'Latin', $latin . 'ÅåÄäÖö'),
        'tl' => array('Tagalog / Filipino', 'Latin', $latin . 'Ññ'),
        'tk' => array('Turkmen', 'Latin', $latin . 'ÄäÇçŽžŇňÖöŞşÜüÝý'),
        'tr' => array('Turkish', 'Latin', $latin . 'ÇçĞğİıÖöŞşÜü'),
        'uz-Latn' => array('Uzbek (Latin)', 'Latin', $latin . 'ʻʼ'),
        'vi' => array('Vietnamese', 'Latin', $latin . 'ĂăÂâĐđÊêÔôƠơƯưÀàÁáẢảÃãẠạẰằẮắẲẳẴẵẶặẦầẤấẨẩẪẫẬậÈèÉéẺẻẼẽẸẹỀềẾếỂểỄễỆệÌìÍíỈỉĨĩỊịÒòÓóỎỏÕõỌọỒồỐốỔổỖỗỘộỜờỚớỞởỠỡỢợÙùÚúỦủŨũỤụỪừỨứỬửỮữỰựỲỳÝýỶỷỸỹỴỵ'),
        'cy' => array('Welsh', 'Latin', $latin . 'ÂâÊêÎîÔôÛûŴŵŶŷ'),
        'xh' => array('Xhosa', 'Latin', $latin),
        'yo' => array('Yoruba', 'Latin', $latin . 'ẸẹỌọṢṣ'),
        'zu' => array('Zulu', 'Latin', $latin),
        'ak' => array('Akan', 'Latin', $latin . 'ƐɛƆɔ'),
        'ay' => array('Aymara', 'Latin', $latin . 'Ññ'),
        'bm' => array('Bambara', 'Latin', $latin . 'ƐɛƆɔƝɲŊŋ'),
        'bem' => array('Bemba', 'Latin', $latin),
        'ceb' => array('Cebuano', 'Latin', $latin),
        'ch' => array('Chamorro', 'Latin', $latin . 'ÅåÑñ'),
        'ny' => array('Chichewa', 'Latin', $latin),
        'crh-Latn' => array('Crimean Tatar (Latin)', 'Latin', $latin . 'ÇçĞğİıÑñÖöŞşÜü'),
        'ee' => array('Ewe', 'Latin', $latin . 'ƉɖƐɛƑƒƔɣŊŋƆɔ'),
        'fj' => array('Fijian', 'Latin', $latin),
        'ff-Latn' => array('Fulah (Latin)', 'Latin', $latin . 'ƁɓƊɗŊŋƳƴ'),
        'gn' => array('Guarani', 'Latin', $latin . 'ÃãẼẽĨĩÑñÕõŨũỸỹ'),
        'ia' => array('Interlingua', 'Latin', $latin),
        'jv' => array('Javanese (Latin)', 'Latin', $latin),
        'ki' => array('Kikuyu', 'Latin', $latin . 'ĨĩŨũ'),
        'rw' => array('Kinyarwanda', 'Latin', $latin),
        'rn' => array('Kirundi', 'Latin', $latin),
        'la' => array('Latin', 'Latin', $latin),
        'lg' => array('Luganda', 'Latin', $latin),
        'ln' => array('Lingala', 'Latin', $latin . 'ƐɛƆɔ'),
        'mg' => array('Malagasy', 'Latin', $latin),
        'nah' => array('Nahuatl', 'Latin', $latin),
        'oc' => array('Occitan', 'Latin', $latin . 'ÀàÇçÈèÉéÍíÒòÓóÚú'),
        'om' => array('Oromo', 'Latin', $latin),
        'pap' => array('Papiamento', 'Latin', $latin . 'ÁáÉéÍíÑñÓóÚúÜü'),
        'qu' => array('Quechua', 'Latin', $latin . 'Ññ'),
        'rm' => array('Romansh', 'Latin', $latin . 'ÀàÈèÉéÌìÒòÙù'),
        'sm' => array('Samoan', 'Latin', $latin . 'ĀāĒēĪīŌōŪūʻ'),
        'sco' => array('Scots', 'Latin', $latin),
        'sn' => array('Shona', 'Latin', $latin),
        'st' => array('Sesotho', 'Latin', $latin),
        'su' => array('Sundanese (Latin)', 'Latin', $latin),
        'ty' => array('Tahitian', 'Latin', $latin . 'ĀāĒēĪīŌōŪū'),
        'tet' => array('Tetum', 'Latin', $latin),
        'to' => array('Tongan', 'Latin', $latin . 'ĀāĒēĪīŌōŪūʻ'),
        'tpi' => array('Tok Pisin', 'Latin', $latin),
        'tn' => array('Tswana', 'Latin', $latin),
        'ts' => array('Tsonga', 'Latin', $latin),
        'tw' => array('Twi', 'Latin', $latin . 'ƐɛƆɔ'),
        've' => array('Venda', 'Latin', $latin),
        'wa' => array('Walloon', 'Latin', $latin . 'ÅåÂâÇçÈèÉéÊêÎîÔôÛû'),
        'wo' => array('Wolof', 'Latin', $latin . 'ÀàÉéËëÑñŊŋÓó'),

        'ru' => array('Russian', 'Cyrillic', 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя'),
        'uk' => array('Ukrainian', 'Cyrillic', 'АБВГҐДЕЄЖЗИІЇЙКЛМНОПРСТУФХЦЧШЩЬЮЯабвгґдеєжзиіїйклмнопрстуфхцчшщьюя'),
        'be' => array('Belarusian', 'Cyrillic', 'АБВГДЕЁЖЗІЙКЛМНОПРСТУЎФХЦЧШЫЬЭЮЯабвгдеёжзійклмнопрстуўфхцчшыьэюя'),
        'bg' => array('Bulgarian', 'Cyrillic', 'АБВГДЕЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЬЮЯабвгдежзийклмнопрстуфхцчшщъьюя'),
        'sr' => array('Serbian (Cyrillic)', 'Cyrillic', 'АБВГДЂЕЖЗИЈКЛЉМНЊОПРСТЋУФХЦЧЏШабвгдђежзијклљмнњопрстћуфхцчџш'),
        'mk' => array('Macedonian', 'Cyrillic', 'АБВГДЃЕЖЗЅИЈКЛЉМНЊОПРСТЌУФХЦЧЏШабвгдѓежзѕијклљмнњопрстќуфхцчџш'),
        'kk' => array('Kazakh', 'Cyrillic', 'АӘБВГҒДЕЁЖЗИЙКҚЛМНҢОӨПРСТУҰҮФХҺЦЧШЩЪЫІЬЭЮЯаәбвгғдеёжзийкқлмнңоөпрстуұүфхһцчшщъыіьэюя'),
        'ky' => array('Kyrgyz', 'Cyrillic', 'АБВГДЕЁЖЗИЙКЛМНҢОӨПРСТУҮФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнңоөпрстуүфхцчшщъыьэюя'),
        'tg' => array('Tajik', 'Cyrillic', 'АБВГҒДЕЁЖЗИӢЙКҚЛМНОПРСТУӮФХҲЧҶШЪЭЮЯабвгғдеёжзиӣйкқлмнопрстуӯфхҳчҷшъэюя'),
        'mn' => array('Mongolian (Cyrillic)', 'Cyrillic', 'АБВГДЕЁЖЗИЙКЛМНОӨПРСТУҮФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмноөпрстуүфхцчшщъыьэюя'),
        'tt' => array('Tatar', 'Cyrillic', 'АӘБВГДЕЁЖҖЗИЙКЛМНҢОӨПРСТУҮФХҺЦЧШЩЪЫЬЭЮЯаәбвгдеёжҗзийклмнңоөпрстуүфхһцчшщъыьэюя'),
        'ba' => array('Bashkir', 'Cyrillic', 'АӘБВГҒДҘЕЁЖЗИЙКҠЛМНҢОӨПРСҪТУҮФХҺЦЧШЩЪЫЬЭЮЯаәбвгғҙеёжзийкҡлмнңоөпрсҫтуүфхһцчшщъыьэюя'),
        'cv' => array('Chuvash', 'Cyrillic', 'АӐБВГДЕЁӖЖЗЙЙКЛМНПРСҪТУӲФХЦЧШЫЬЭЮЯаӑбвгдеёӗжзййклмнпрсҫтуӳфхцчшыьэюя'),
        'os' => array('Ossetian', 'Cyrillic', 'АӔБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯаӕбвгдеёжзийклмнопрстуфхцчшщъыьэюя'),
        'sah' => array('Sakha / Yakut', 'Cyrillic', 'АБВГҔДЕЁЖЗИЙКЛМНҤОӨПРСТУҮФХҺЦЧШЩЪЫЬЭЮЯабвгҕдеёжзийклмнҥоөпрстуүфхһцчшщъыьэюя'),
        'udm' => array('Udmurt', 'Cyrillic', 'АБВГДЕЁЖӜЗӞИӤЙКЛМНОӦПРСТУФХЦЧӴШЩЪЫЬЭЮЯабвгдеёжӝзӟиӥйклмноӧпрстуфхцчӵшщъыьэюя'),

        'el' => array('Greek', 'Greek', 'ΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩαβγδεζηθικλμνξοπρσςτυφχψωΆάΈέΉήΊίΌόΎύΏώΪϊΫϋΐΰ'),
        'hy' => array('Armenian', 'Armenian', 'ԱԲԳԴԵԶԷԸԹԺԻԼԽԾԿՀՁՂՃՄՅՆՇՈՉՊՋՌՍՎՏՐՑՒՓՔՕՖաբգդեզէըթժիլխծկհձղճմյնշոչպջռսվտրցւփքօֆև'),
        'ka' => array('Georgian', 'Georgian', 'აბგდევზთიკლმნოპჟრსტუფქღყშჩცძწჭხჯჰ'),
        'he' => array('Hebrew', 'Hebrew', 'אבגדהוזחטיךכלםמןנסעףפץצקרשת'),
        'yi' => array('Yiddish', 'Hebrew', 'אבגדהוזחטיךכלםמןנסעףפץצקרשתװױײ'),
        'ar' => array('Arabic', 'Arabic', 'ابتثجحخدذرزسشصضطظعغفقكلمنهويءآأؤإئأةى'),
        'fa' => array('Persian', 'Arabic', 'ابتثجحخدذرزسشصضطظعغفقكلمنهویپچژگکءآأؤئۀ'),
        'ur' => array('Urdu', 'Arabic', 'ابتثجحخدذرزسشصضطظعغفقکلمنہویپچژگٹڈڑںھہےءآ'),

        'hi' => array('Hindi', 'Devanagari', 'अआइईउऊऋएऐओऔकखगघङचछजझञटठडढणतथदधनपफबभमयरलवशषसहािीुूृेैोौंःँ'),
        'mr' => array('Marathi', 'Devanagari', 'अआइईउऊऋएऐओऔकखगघङचछजझञटठडढणतथदधनपफबभमयरलवशषसहळािीुूृेैोौंःँ'),
        'ne' => array('Nepali', 'Devanagari', 'अआइईउऊएऐओऔकखगघङचछजझञटठडढणतथदधनपफबभमयरलवशषसहािीुूेैोौंःँ'),
        'bn' => array('Bengali', 'Bengali', 'অআইঈউঊঋএঐওঔকখগঘঙচছজঝঞটঠডঢণতথদধনপফবভমযরলশষসহািীুূৃেৈোৌংঃঁ'),
        'pa' => array('Punjabi (Gurmukhi)', 'Gurmukhi', 'ਅਆਇਈਉਊਏਐਓਔਕਖਗਘਙਚਛਜਝਞਟਠਡਢਣਤਥਦਧਨਪਫਬਭਮਯਰਲਵਸਹਾਿੀੁੂੇੈੋੌਂੰ'),
        'gu' => array('Gujarati', 'Gujarati', 'અઆઇઈઉઊઋએઐઓઔકખગઘઙચછજઝઞટઠડઢણતથદધનપફબભમયરલવશષસહાિીુૂૃેૈોૌંઃ'),
        'or' => array('Odia', 'Odia', 'ଅଆଇଈଉଊଋଏଐଓଔକଖଗଘଙଚଛଜଝଞଟଠଡଢଣତଥଦଧନପଫବଭମଯରଲଳଶଷସହାିୀୁୂୃେୈୋୌଂଃ'),
        'ta' => array('Tamil', 'Tamil', 'அஆஇஈஉஊஎஏஐஒஓஔகஙசஞடணதநபமயரலவழளறனாிீுூெேைொோௌஂ'),
        'te' => array('Telugu', 'Telugu', 'అఆఇఈఉఊఋఎఏఐఒఓఔకఖగఘఙచఛజఝఞటఠడఢణతథదధనపఫబభమయరలవశషసహాిీుూృెేైొోౌం'),
        'kn' => array('Kannada', 'Kannada', 'ಅಆಇಈಉಊಋಎಏಐಒಓಔಕಖಗಘಙಚಛಜಝಞಟಠಡಢಣತಥದಧನಪಫಬಭಮಯರಲವಶಷಸಹಾಿೀುೂೃೆೇೈೊೋೌಂ'),
        'ml' => array('Malayalam', 'Malayalam', 'അആഇഈഉഊഋഎഏഐഒഓഔകഖഗഘങചഛജഝഞടഠഡഢണതഥദധനപഫബഭമയരലവശഷസഹാിീുൂൃെേൈൊോൗം'),
        'th' => array('Thai', 'Thai', 'กขฃคฅฆงจฉชซฌญฎฏฐฑฒณดตถทธนบปผฝพฟภมยรลวศษสหฬอฮะาิีึืุูเแโใไ่้๊๋์'),
    );
    return apply_filters('authentype_specimen_language_catalog', $catalog);
}

function ath_specimen_detect_languages($codepoints) {
    if (empty($codepoints) || !is_array($codepoints)) return array();
    $supported = array();
    foreach (ath_specimen_language_catalog() as $code => $entry) {
        if (!is_array($entry) || count($entry) < 3) continue;
        list($name, $script, $required) = $entry;
        $chars = preg_split('//u', (string) $required, -1, PREG_SPLIT_NO_EMPTY);
        if (!$chars) continue;
        $ok = true;
        foreach (array_unique($chars) as $char) {
            $cp = ath_specimen_utf8_codepoint($char);
            if (!$cp || !isset($codepoints[$cp])) { $ok = false; break; }
        }
        if ($ok) $supported[] = array('code' => (string) $code, 'name' => (string) $name, 'script' => (string) $script);
    }
    usort($supported, function ($a, $b) { return strcasecmp($a['name'], $b['name']); });
    return $supported;
}

function ath_specimen_font_tech_info($tables, $font_data, $url = '') {
    $names = !empty($tables['name']) ? ath_specimen_parse_name_table($tables['name']) : array();
    $glyphs = !empty($tables['maxp']) && strlen($tables['maxp']) >= 6 ? ath_specimen_u16($tables['maxp'], 4) : 0;
    $head = !empty($tables['head']) ? $tables['head'] : '';
    $os2 = !empty($tables['OS/2']) ? $tables['OS/2'] : '';
    $hhea = !empty($tables['hhea']) ? $tables['hhea'] : '';
    $post = !empty($tables['post']) ? $tables['post'] : '';
    $upm = $head && strlen($head) >= 20 ? ath_specimen_u16($head, 18) : 0;
    $os2_version = $os2 && strlen($os2) >= 2 ? ath_specimen_u16($os2, 0) : 0;
    $weight = $os2 && strlen($os2) >= 6 ? ath_specimen_u16($os2, 4) : 0;
    $width = $os2 && strlen($os2) >= 8 ? ath_specimen_u16($os2, 6) : 0;
    $fs_type = $os2 && strlen($os2) >= 10 ? ath_specimen_u16($os2, 8) : 0;
    $vendor_id = $os2 && strlen($os2) >= 62 ? trim(substr($os2, 58, 4)) : '';
    $panose = '';
    if ($os2 && strlen($os2) >= 42) {
        $bytes = array_values(unpack('C*', substr($os2, 32, 10)));
        $panose = implode(' ', array_map('intval', $bytes));
    }
    $outline = isset($tables['CFF2']) ? 'CFF2' : (isset($tables['CFF ']) ? 'CFF' : (isset($tables['glyf']) ? 'TrueType glyf' : ''));
    $bbox = '';
    if ($head && strlen($head) >= 44) {
        $bbox = ath_specimen_s16($head, 36) . ', ' . ath_specimen_s16($head, 38) . ' → ' . ath_specimen_s16($head, 40) . ', ' . ath_specimen_s16($head, 42);
    }
    $axes = !empty($tables['fvar']) ? ath_specimen_parse_fvar_axes($tables['fvar']) : array();
    return array(
        'full_name' => !empty($names['full_name']) ? $names['full_name'] : '',
        'family_name' => !empty($names['family']) ? $names['family'] : '',
        'typographic_family' => !empty($names['typographic_family']) ? $names['typographic_family'] : '',
        'subfamily_name' => !empty($names['subfamily']) ? $names['subfamily'] : '',
        'typographic_subfamily' => !empty($names['typographic_subfamily']) ? $names['typographic_subfamily'] : '',
        'postscript_name' => !empty($names['postscript']) ? $names['postscript'] : '',
        'unique_id' => !empty($names['unique_id']) ? $names['unique_id'] : '',
        'format' => ath_specimen_font_format_label($font_data, $tables, $url),
        'outline' => $outline,
        'version' => !empty($names['version']) ? $names['version'] : '',
        'glyph_count' => $glyphs,
        'units_per_em' => $upm,
        'file_size' => is_string($font_data) ? strlen($font_data) : 0,
        'weight_class' => ath_specimen_weight_class_label($weight),
        'width_class' => ath_specimen_width_class_label($width),
        'vendor_id' => $vendor_id,
        'panose' => $panose,
        'embedding' => ath_specimen_embedding_label($fs_type),
        'italic_angle' => $post && strlen($post) >= 8 ? round(ath_specimen_fixed_16_16($post, 4), 4) : 0,
        'fixed_pitch' => $post && strlen($post) >= 16 ? (ath_specimen_u32($post, 12) ? 'Yes' : 'No') : '',
        'underline_position' => $post && strlen($post) >= 10 ? ath_specimen_s16($post, 8) : '',
        'underline_thickness' => $post && strlen($post) >= 12 ? ath_specimen_s16($post, 10) : '',
        'hhea_ascender' => $hhea && strlen($hhea) >= 10 ? ath_specimen_s16($hhea, 4) : '',
        'hhea_descender' => $hhea && strlen($hhea) >= 10 ? ath_specimen_s16($hhea, 6) : '',
        'hhea_line_gap' => $hhea && strlen($hhea) >= 10 ? ath_specimen_s16($hhea, 8) : '',
        'typo_ascender' => $os2 && strlen($os2) >= 74 ? ath_specimen_s16($os2, 68) : '',
        'typo_descender' => $os2 && strlen($os2) >= 74 ? ath_specimen_s16($os2, 70) : '',
        'typo_line_gap' => $os2 && strlen($os2) >= 74 ? ath_specimen_s16($os2, 72) : '',
        'win_ascent' => $os2 && strlen($os2) >= 78 ? ath_specimen_u16($os2, 74) : '',
        'win_descent' => $os2 && strlen($os2) >= 78 ? ath_specimen_u16($os2, 76) : '',
        'x_height' => $os2_version >= 2 && strlen($os2) >= 90 ? ath_specimen_s16($os2, 86) : '',
        'cap_height' => $os2_version >= 2 && strlen($os2) >= 90 ? ath_specimen_s16($os2, 88) : '',
        'bbox' => $bbox,
        'designer' => !empty($names['designer']) ? $names['designer'] : '',
        'manufacturer' => !empty($names['manufacturer']) ? $names['manufacturer'] : '',
        'description' => !empty($names['description']) ? $names['description'] : '',
        'copyright' => !empty($names['copyright']) ? $names['copyright'] : '',
        'trademark' => !empty($names['trademark']) ? $names['trademark'] : '',
        'license_description' => !empty($names['license_description']) ? $names['license_description'] : '',
        'license_url' => !empty($names['license_url']) ? $names['license_url'] : '',
        'vendor_url' => !empty($names['vendor_url']) ? $names['vendor_url'] : '',
        'designer_url' => !empty($names['designer_url']) ? $names['designer_url'] : '',
        'variable_axes' => $axes,
        'variable' => !empty($axes) ? 'Yes' : 'No',
        'table_tags' => array_values(array_keys($tables)),
    );
}

function ath_specimen_font_file_fingerprint($url) {
    $path = ath_specimen_local_upload_path($url);
    if (!$path) return '';
    $size = @filesize($path);
    $mtime = @filemtime($path);
    return hash('sha256', wp_normalize_path($path) . '|' . (int) $size . '|' . (int) $mtime);
}

function ath_specimen_empty_font_info() {
    return array('features' => array(), 'codepoints' => array(), 'ligatures' => array(), 'format' => '', 'tech' => array(), 'languages' => array(), 'scripts' => array(), 'glyph_codepoints' => array());
}

/**
 * Persistent metadata lives as one protected JSON record per style/pair.
 * This intentionally avoids a single giant serialized post-meta value: a
 * 100+ style family can load one metadata record without hydrating every
 * other style into PHP/WordPress object cache.
 */
function ath_specimen_metadata_cache_dir($post_id) {
    $post_id = absint($post_id);
    if (!$post_id) return '';
    $uploads = wp_get_upload_dir();
    if (empty($uploads['basedir'])) return '';
    $base = trailingslashit($uploads['basedir']) . 'woocommerce_uploads/authentype-metadata-cache';
    $dir = trailingslashit($base) . $post_id;
    if (!is_dir($dir) && !wp_mkdir_p($dir)) return '';
    if (function_exists('ath_specimen_protect_download_dir')) {
        ath_specimen_protect_download_dir(trailingslashit($uploads['basedir']) . 'woocommerce_uploads');
        ath_specimen_protect_download_dir($base);
        ath_specimen_protect_download_dir($dir);
    } else {
        // Safe fallback for calls made before the admin helpers are loaded.
        if (!is_file(trailingslashit($dir) . 'index.html')) @file_put_contents(trailingslashit($dir) . 'index.html', '');
        if (!is_file(trailingslashit($dir) . '.htaccess')) @file_put_contents(trailingslashit($dir) . '.htaccess', "Options -Indexes\n<FilesMatch \".*\">\nRequire all denied\n</FilesMatch>\n");
    }
    return is_dir($dir) ? $dir : '';
}

function ath_specimen_metadata_cache_file($post_id, $cache_key) {
    $cache_key = sanitize_key($cache_key);
    if (!$cache_key) return '';
    $dir = ath_specimen_metadata_cache_dir($post_id);
    return $dir ? trailingslashit($dir) . $cache_key . '-' . substr(hash('sha256', $cache_key), 0, 12) . '.json' : '';
}

function ath_specimen_read_font_metadata_record($post_id, $cache_key, $fingerprint) {
    $file = ath_specimen_metadata_cache_file($post_id, $cache_key);
    if (!$file || !is_file($file)) return array();
    $size = @filesize($file);
    if (!$size || $size > 8 * 1024 * 1024) return array();
    $raw = @file_get_contents($file);
    if (!$raw) return array();
    $record = json_decode($raw, true);
    if (!is_array($record) || (int) ($record['schema'] ?? 0) < 4 || empty($record['fingerprint']) || !hash_equals((string) $record['fingerprint'], (string) $fingerprint) || empty($record['info']) || !is_array($record['info'])) return array();
    return $record['info'];
}

function ath_specimen_write_font_metadata_record($post_id, $cache_key, $fingerprint, $info) {
    $file = ath_specimen_metadata_cache_file($post_id, $cache_key);
    if (!$file) return false;
    $record = array(
        'schema' => 4,
        'fingerprint' => (string) $fingerprint,
        'cached_at' => time(),
        'info' => is_array($info) ? $info : ath_specimen_empty_font_info(),
    );
    $json = wp_json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) return false;
    $tmp = $file . '.tmp-' . substr(wp_generate_uuid4(), 0, 8);
    $written = @file_put_contents($tmp, $json, LOCK_EX);
    if (false === $written || $written !== strlen($json)) {
        @wp_delete_file($tmp);
        return false;
    }
    if (is_file($file)) @wp_delete_file($file);
    if (!@rename($tmp, $file)) {
        @wp_delete_file($tmp);
        return false;
    }
    return true;
}

function ath_specimen_get_font_info_cached($post_id, $cache_key, $url, $allow_build = true) {
    $post_id = absint($post_id);
    $cache_key = sanitize_key($cache_key);
    if (!$post_id || !$cache_key || !$url) return ath_specimen_empty_font_info();

    $fingerprint = ath_specimen_font_file_fingerprint($url);
    if (!$fingerprint) return ath_specimen_empty_font_info();
    $cached = ath_specimen_read_font_metadata_record($post_id, $cache_key, $fingerprint);
    if (!empty($cached)) {
        if (isset($cached['glyph_codepoints']) && is_array($cached['glyph_codepoints'])) return $cached;
        if (!$allow_build) return $cached;
    }
    if (!$allow_build) return ath_specimen_empty_font_info();

    $info = ath_specimen_get_font_info($url);
    if (!is_array($info)) $info = ath_specimen_empty_font_info();
    ath_specimen_write_font_metadata_record($post_id, $cache_key, $fingerprint, $info);
    return $info;
}

function ath_specimen_clear_font_metadata_cache($post_id) {
    $dir = ath_specimen_metadata_cache_dir($post_id);
    if ($dir && is_dir($dir)) {
        foreach ((array) glob(trailingslashit($dir) . '*.json') as $file) @wp_delete_file($file);
    }
    delete_post_meta(absint($post_id), '_ath_font_metadata_cache_v7');
}

function ath_specimen_refresh_font_metadata_cache($post_id, $styles, $limit = 0) {
    $post_id = absint($post_id);
    if (!$post_id || !is_array($styles)) return 0;
    $limit = max(0, absint($limit));
    $count = 0;
    foreach ($styles as $index => $style) {
        if (empty($style['font_file']) || !empty($style['is_package'])) continue;
        if ($limit && $count >= $limit) break;
        $cache_key = 'style-' . ($index + 1);
        $fingerprint = ath_specimen_font_file_fingerprint($style['font_file']);
        if (!$fingerprint) continue;
        $info = ath_specimen_get_font_info($style['font_file']);
        if (!is_array($info)) $info = ath_specimen_empty_font_info();
        if (ath_specimen_write_font_metadata_record($post_id, $cache_key, $fingerprint, $info)) $count++;
    }
    delete_post_meta($post_id, '_ath_font_metadata_cache_v7');
    return $count;
}

?>
