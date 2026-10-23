<?php
defined('ABSPATH') || exit;

function ath_specimen_get_meta($post_id, $key, $default = null) {
    $value = get_post_meta($post_id, $key, true);
    return null === $value || false === $value || '' === $value ? $default : $value;
}

/**
 * Resolve an existing local file while guaranteeing that it remains inside
 * the WordPress uploads directory. URL host/scheme are intentionally not an
 * authority: only the URL path is mapped into the CURRENT local uploads root.
 * This keeps staging/domain/HTTPS migrations working without ever fetching a
 * remote URL. Credentials, query strings, fragments, traversal and symlink
 * escapes remain rejected.
 */
function ath_specimen_local_upload_path($value) {
    $value = is_scalar($value) ? trim((string) $value) : '';
    if (!$value || !function_exists('wp_get_upload_dir')) return '';

    $uploads = wp_get_upload_dir();
    if (empty($uploads['baseurl']) || empty($uploads['basedir'])) return '';

    $base_real = realpath($uploads['basedir']);
    if (false === $base_real) return '';

    $candidate = '';
    if (filter_var($value, FILTER_VALIDATE_URL)) {
        $file = wp_parse_url($value);
        $base = wp_parse_url($uploads['baseurl']);
        if (!is_array($file) || !is_array($base) || empty($file['host']) || empty($file['path']) || empty($base['path'])) return '';
        if (!empty($file['user']) || !empty($file['pass']) || isset($file['query']) || isset($file['fragment'])) return '';
        if (empty($file['scheme']) || !in_array(strtolower($file['scheme']), array('http', 'https'), true)) return '';

        $file_path = rawurldecode($file['path']);
        $base_path = rtrim(rawurldecode($base['path']), '/');
        if ($file_path !== $base_path && 0 !== strpos($file_path, $base_path . '/')) return '';
        $relative = ltrim(substr($file_path, strlen($base_path)), '/');
        if ('' === $relative || false !== strpos(str_replace('\\', '/', $relative), '../')) return '';
        $candidate = trailingslashit($base_real) . $relative;
    } elseif (0 === strpos(wp_normalize_path($value), wp_normalize_path($base_real) . '/')) {
        $candidate = $value;
    } elseif (!preg_match('#(^|[\\/])\.\.([\\/]|$)#', $value) && 0 !== strpos($value, '/') && false === strpos($value, "\0")) {
        // New metadata may store a portable uploads-relative path such as
        // woocommerce_uploads/authentype-previews/... instead of an origin URL.
        $candidate = trailingslashit($base_real) . ltrim(wp_normalize_path($value), '/');
    } else {
        return '';
    }

    $real = realpath($candidate);
    if (false === $real || !is_file($real) || !is_readable($real)) return '';

    $base_normalized = rtrim(wp_normalize_path($base_real), '/') . '/';
    $real_normalized = wp_normalize_path($real);
    return 0 === strpos($real_normalized, $base_normalized) ? $real : '';
}

function ath_specimen_read_public_font($file) {
    static $cache = array();

    $url = is_array($file) && !empty($file['url']) ? $file['url'] : $file;
    $path = $url ? ath_specimen_local_upload_path($url) : '';
    if (!$path) return false;

    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($extension, array('otf', 'ttf', 'woff', 'woff2'), true)) return false;

    $max_size = max(1024, (int) apply_filters('authentype_specimen_public_font_max_size', 32 * 1024 * 1024));
    $size = filesize($path);
    if (false === $size || $size <= 0 || $size > $max_size) return false;

    $mtime = filemtime($path);
    $cache_key = $path . '|' . (string) $mtime . '|' . (string) $size;
    if (array_key_exists($cache_key, $cache)) return $cache[$cache_key];

    $data = file_get_contents($path, false, null, 0, $max_size + 1);
    if (false === $data || strlen($data) !== $size || strlen($data) > $max_size) {
        $cache[$cache_key] = false;
        return false;
    }

    $type = function_exists('mime_content_type') ? mime_content_type($path) : '';
    $cache[$cache_key] = array(
        'data' => $data,
        'type' => $type ?: 'font/' . $extension,
    );
    return $cache[$cache_key];
}

function ath_specimen_get_font_file_data($file) {
    $url = is_array($file) && !empty($file['url']) ? $file['url'] : $file;
    if (!$url) return false;

    return ath_specimen_read_public_font($url);
}

function ath_specimen_slug($value) {
    return sanitize_title((string) $value);
}

function ath_specimen_normalize_attr_key($key) {
    $key = sanitize_title($key);
    return 0 === strpos($key, 'attribute_') ? $key : 'attribute_' . $key;
}
/**
 * Adaptive license picker helpers. License grouping is presentation metadata
 * only; it never changes package delivery, pricing, or WooCommerce values.
 */
function ath_specimen_license_group_options() {
    return array('common', 'extended', 'business', 'custom');
}

function ath_specimen_license_auto_group($value, $label = '') {
    $slug = ath_specimen_slug($value ?: $label);
    if (preg_match('/(^|-)(custom|bespoke|contact|inquiry)(-|$)/', $slug)) return 'custom';
    if (preg_match('/(^|-)(corporate|enterprise|business|company|organisation|organization|agency)(-|$)/', $slug)) return 'business';
    if (preg_match('/(^|-)(desktop|webfont|web|app|application|epub|ebook|digital-publication)(-|$)/', $slug)) return 'common';
    return 'extended';
}

function ath_specimen_license_display_group($row) {
    $row = is_array($row) ? $row : array();
    $group = !empty($row['license_group']) ? sanitize_key($row['license_group']) : '';
    if (!in_array($group, ath_specimen_license_group_options(), true)) {
        $group = ath_specimen_license_auto_group(
            !empty($row['license_variation_value']) ? $row['license_variation_value'] : '',
            !empty($row['license_label']) ? $row['license_label'] : ''
        );
    }
    return $group;
}

function ath_specimen_license_is_featured($row) {
    return is_array($row) && !empty($row['license_featured']);
}

function ath_specimen_license_icon_options() {
    return array(
        'desktop' => __('Desktop / Monitor', 'authentype-font-specimen'),
        'web' => __('Web / Globe', 'authentype-font-specimen'),
        'app' => __('App / Mobile', 'authentype-font-specimen'),
        'document' => __('Document / eBook', 'authentype-font-specimen'),
        'server' => __('Server', 'authentype-font-specimen'),
        'ads' => __('Digital Ads / Email', 'authentype-font-specimen'),
        'social' => __('Social Media', 'authentype-font-specimen'),
        'broadcast' => __('Broadcast / Video', 'authentype-font-specimen'),
        'merchandise' => __('Merchandise / Products', 'authentype-font-specimen'),
        'corporate' => __('Corporate / Building', 'authentype-font-specimen'),
        'enterprise' => __('Enterprise / Organization', 'authentype-font-specimen'),
        'logo' => __('Logo / Trademark', 'authentype-font-specimen'),
        'custom' => __('Custom / Shield', 'authentype-font-specimen'),
    );
}

function ath_specimen_license_auto_icon($value, $label = '') {
    $slug = ath_specimen_slug($value ?: $label);
    if (preg_match('/(^|-)(desktop|print)(-|$)/', $slug)) return 'desktop';
    if (preg_match('/(^|-)(web|webfont|website)(-|$)/', $slug)) return 'web';
    if (preg_match('/(^|-)(app|application|mobile)(-|$)/', $slug)) return 'app';
    if (preg_match('/(^|-)(epub|ebook|document|pdf|electronic-doc)(-|$)/', $slug)) return 'document';
    if (preg_match('/(^|-)(server|cloud)(-|$)/', $slug)) return 'server';
    if (preg_match('/(^|-)(digital-ad|digital-ads|advertising|advert|email|banner)(-|$)/', $slug)) return 'ads';
    if (preg_match('/(^|-)(social|social-media)(-|$)/', $slug)) return 'social';
    if (preg_match('/(^|-)(broadcast|television|tv|film|video|stream|streaming)(-|$)/', $slug)) return 'broadcast';
    if (preg_match('/(^|-)(merchandise|merch|product|products|goods)(-|$)/', $slug)) return 'merchandise';
    if (preg_match('/(^|-)(enterprise|organization|organisation)(-|$)/', $slug)) return 'enterprise';
    if (preg_match('/(^|-)(corporate|business|agency|company)(-|$)/', $slug)) return 'corporate';
    if (preg_match('/(^|-)(logo|trademark|brand-mark|wordmark)(-|$)/', $slug)) return 'logo';
    return 'custom';
}

function ath_specimen_license_icon_key($row) {
    $row = is_array($row) ? $row : array();
    $icon = '';
    if (!empty($row['license_icon'])) $icon = sanitize_key($row['license_icon']);
    elseif (!empty($row['icon'])) $icon = sanitize_key($row['icon']);
    $options = ath_specimen_license_icon_options();
    if ($icon && isset($options[$icon])) return $icon;
    $value = !empty($row['license_variation_value']) ? $row['license_variation_value'] : (!empty($row['value']) ? $row['value'] : '');
    $label = !empty($row['license_label']) ? $row['license_label'] : (!empty($row['label']) ? $row['label'] : '');
    return ath_specimen_license_auto_icon($value, $label);
}

function ath_specimen_license_icon_svg($license) {
    $key = ath_specimen_license_icon_key($license);
    $common = 'viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"';
    switch ($key) {
        case 'desktop':
            return '<svg ' . $common . '><rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M8 20h8M12 16v4"/></svg>';
        case 'web':
            return '<svg ' . $common . '><circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"/></svg>';
        case 'app':
            return '<svg ' . $common . '><rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M10 5h4M11 18.5h2"/></svg>';
        case 'document':
            return '<svg ' . $common . '><path d="M6 2.5h8l4 4V21H6z"/><path d="M14 2.5V7h4M9 11h6M9 15h6"/></svg>';
        case 'server':
            return '<svg ' . $common . '><rect x="4" y="3" width="16" height="7" rx="1.5"/><rect x="4" y="14" width="16" height="7" rx="1.5"/><path d="M8 6.5h.01M8 17.5h.01M12 6.5h5M12 17.5h5"/></svg>';
        case 'ads':
            return '<svg ' . $common . '><path d="M4 13V9l12-4v12L4 13z"/><path d="M4 10H2.5v2H4M7 14l1.5 5h3L10 13"/></svg>';
        case 'social':
            return '<svg ' . $common . '><circle cx="6" cy="12" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><path d="M8 11l8-4M8 13l8 4"/></svg>';
        case 'broadcast':
            return '<svg ' . $common . '><rect x="3" y="5" width="18" height="13" rx="2"/><path d="M10 9l5 2.5-5 2.5zM8 21h8"/></svg>';
        case 'merchandise':
            return '<svg ' . $common . '><path d="M5 8h14l-1 13H6L5 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/></svg>';
        case 'corporate':
            return '<svg ' . $common . '><path d="M4 21V7l8-4 8 4v14M8 10h2M14 10h2M8 14h2M14 14h2M10 21v-3h4v3"/></svg>';
        case 'enterprise':
            return '<svg ' . $common . '><rect x="3" y="7" width="18" height="13" rx="1.5"/><path d="M8 7V4h8v3M3 12h18M10 12v2h4v-2"/></svg>';
        case 'logo':
            return '<svg ' . $common . '><path d="M4 4h9l7 7-9 9-7-7z"/><circle cx="9" cy="9" r="1.5"/><path d="M14 14l4 4"/></svg>';
        case 'custom':
        default:
            return '<svg ' . $common . '><path d="M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7z"/><path d="M9 12l2 2 4-4"/></svg>';
    }
}

function ath_specimen_license_checkout_type_options() {
    return array('pay_once', 'annual', 'contact');
}

function ath_specimen_license_auto_checkout_type($value, $label = '', $group = '') {
    $slug = ath_specimen_slug($value ?: $label);
    $group = sanitize_key((string) $group);
    if ('custom' === $group || preg_match('/(^|-)(custom|bespoke|contact|inquiry|quote)(-|$)/', $slug)) return 'contact';
    // Do not assume recurring/annual terms from a license name. Most font
    // foundries sell perpetual one-time grants unless the admin explicitly
    // marks a license Annual.
    return 'pay_once';
}

function ath_specimen_license_checkout_type($row) {
    $row = is_array($row) ? $row : array();
    $type = !empty($row['license_checkout_type']) ? sanitize_key($row['license_checkout_type']) : '';
    if (!in_array($type, ath_specimen_license_checkout_type_options(), true)) {
        $type = ath_specimen_license_auto_checkout_type(
            !empty($row['license_variation_value']) ? $row['license_variation_value'] : '',
            !empty($row['license_label']) ? $row['license_label'] : '',
            ath_specimen_license_display_group($row)
        );
    }
    return $type;
}

function ath_specimen_license_is_contact_sales($row) {
    return 'contact' === ath_specimen_license_checkout_type($row);
}


/**
 * Return the best-effort visitor IP without trusting spoofable forwarded
 * headers by default. Sites behind a reverse proxy may opt in with
 * authentype_specimen_trust_forwarded_ip_headers=true and must list the
 * proxy/origin peer IPs (exact IP or CIDR) via authentype_specimen_trusted_proxy_ips.
 * An explicit "*" entry is supported for legacy setups that intentionally
 * trust every direct peer, but is not recommended.
 */
function ath_specimen_ip_matches_cidr($ip, $cidr) {
    $ip = trim((string) $ip);
    $cidr = trim((string) $cidr);
    if (!$ip || !$cidr || !filter_var($ip, FILTER_VALIDATE_IP)) return false;
    if ('*' === $cidr) return true;
    if (false === strpos($cidr, '/')) return hash_equals(strtolower($cidr), strtolower($ip));

    list($network, $prefix) = array_pad(explode('/', $cidr, 2), 2, '');
    if (!filter_var($network, FILTER_VALIDATE_IP) || !ctype_digit((string) $prefix)) return false;
    $ip_bin = @inet_pton($ip);
    $network_bin = @inet_pton($network);
    if (false === $ip_bin || false === $network_bin || strlen($ip_bin) !== strlen($network_bin)) return false;

    $bits = strlen($ip_bin) * 8;
    $prefix = (int) $prefix;
    if ($prefix < 0 || $prefix > $bits) return false;
    $full_bytes = intdiv($prefix, 8);
    $remaining = $prefix % 8;
    if ($full_bytes && substr($ip_bin, 0, $full_bytes) !== substr($network_bin, 0, $full_bytes)) return false;
    if (!$remaining) return true;
    $mask = (0xff << (8 - $remaining)) & 0xff;
    return (ord($ip_bin[$full_bytes]) & $mask) === (ord($network_bin[$full_bytes]) & $mask);
}

function ath_specimen_client_ip() {
    $remote = isset($_SERVER['REMOTE_ADDR']) ? trim(sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']))) : '';
    if (!filter_var($remote, FILTER_VALIDATE_IP)) $remote = '';

    if (!$remote || !apply_filters('authentype_specimen_trust_forwarded_ip_headers', false)) {
        return $remote;
    }

    $trusted = apply_filters('authentype_specimen_trusted_proxy_ips', array());
    if (is_string($trusted)) $trusted = preg_split('/[\s,]+/', $trusted, -1, PREG_SPLIT_NO_EMPTY);
    $trusted = is_array($trusted) ? array_filter(array_map('trim', $trusted)) : array();
    $peer_is_trusted = false;
    foreach ($trusted as $rule) {
        if (ath_specimen_ip_matches_cidr($remote, $rule)) {
            $peer_is_trusted = true;
            break;
        }
    }
    if (!$peer_is_trusted) return $remote;

    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidate = trim(sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP'])));
        if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $raw = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
        $chain = array_values(array_filter(array_map('trim', explode(',', $raw)), static function ($candidate) {
            return (bool) filter_var($candidate, FILTER_VALIDATE_IP);
        }));
        // Walk from the origin-facing end and discard configured trusted proxy
        // hops. This resists a client prepending a forged left-most XFF value.
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $candidate = $chain[$i];
            $is_trusted = false;
            foreach ($trusted as $rule) {
                if (ath_specimen_ip_matches_cidr($candidate, $rule)) {
                    $is_trusted = true;
                    break;
                }
            }
            if (!$is_trusted) return $candidate;
        }
    }
    return $remote;
}

?>
