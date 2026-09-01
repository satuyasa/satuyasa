<?php
defined('ABSPATH') || exit;

/**
 * Global + per-product license detail URL routing.
 *
 * Routing precedence:
 * 1. Product override (_ath_license_url_override)
 * 2. Site-wide option (authentype_specimen_license_url_template)
 * 3. Historical fallback: /licenses/#license-slug
 *
 * Templates may contain {license}. Without the token, the license slug is
 * appended as a fragment when the configured URL does not already contain one.
 */

function ath_specimen_license_url_option_name() {
    return 'authentype_specimen_license_url_template';
}

function ath_specimen_sanitize_license_url_template($value) {
    $value = is_scalar($value) ? trim((string) $value) : '';
    if ('' === $value) return '';

    // Preserve the documented token through WordPress URL sanitization.
    $placeholder = '__ATH_LICENSE_TOKEN__';
    $value = str_replace('{license}', $placeholder, $value);

    // Allow a site-relative path or a normal HTTP(S) URL only.
    if (0 === strpos($value, '/')) {
        $value = preg_replace('/[\x00-\x20<>"\']+/', '', $value);
        $clean = is_string($value) ? $value : '';
    } else {
        $clean = esc_url_raw($value, array('http', 'https'));
    }

    if ('' === $clean) return '';
    return str_replace($placeholder, '{license}', $clean);
}

function ath_specimen_global_license_url_template() {
    return ath_specimen_sanitize_license_url_template(
        get_option(ath_specimen_license_url_option_name(), '')
    );
}

function ath_specimen_product_license_url_override($post_id) {
    if (!$post_id) return '';
    return ath_specimen_sanitize_license_url_template(
        get_post_meta((int) $post_id, '_ath_license_url_override', true)
    );
}

function ath_specimen_license_url_template_for_product($post_id) {
    $override = ath_specimen_product_license_url_override($post_id);
    if ($override) return $override;

    $global = ath_specimen_global_license_url_template();
    if ($global) return $global;

    // Keep the old behavior exactly when no routing setting exists.
    return '/licenses/#{license}';
}

function ath_specimen_resolve_license_url_template($template, $license_value = '') {
    $template = ath_specimen_sanitize_license_url_template($template);
    $license_value = ath_specimen_slug($license_value);

    if (!$template) {
        $template = '/licenses/#{license}';
    }

    $encoded = $license_value ? rawurlencode($license_value) : '';
    if (false !== strpos($template, '{license}')) {
        $resolved = str_replace('{license}', $encoded, $template);
    } else {
        $resolved = $template;
        if ($encoded && false === strpos($resolved, '#')) {
            $resolved = rtrim($resolved, '#') . '#' . $encoded;
        }
    }

    if (0 === strpos($resolved, '/')) {
        return home_url($resolved);
    }

    return esc_url_raw($resolved, array('http', 'https'));
}

function ath_specimen_resolve_license_detail_url($post_id, $license_value = '') {
    return ath_specimen_resolve_license_url_template(
        ath_specimen_license_url_template_for_product($post_id),
        $license_value
    );
}

add_action('admin_init', function () {
    register_setting(
        'ath_specimen_license_url_settings',
        ath_specimen_license_url_option_name(),
        array(
            'type' => 'string',
            'sanitize_callback' => 'ath_specimen_sanitize_license_url_template',
            'default' => '',
        )
    );
});

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=ath_font',
        __('License URL Settings', 'authentype-font-specimen'),
        __('License URLs', 'authentype-font-specimen'),
        'manage_options',
        'ath-license-url-settings',
        'ath_specimen_render_license_url_settings_page'
    );
});

function ath_specimen_render_license_url_settings_page() {
    if (!current_user_can('manage_options')) return;

    $template = ath_specimen_global_license_url_template();
    $desktop_example = ath_specimen_resolve_license_url_template($template ?: '/licenses/#{license}', 'desktop');
    ?>
    <div class="wrap">
        <?php settings_errors(); ?>
        <h1><?php esc_html_e('License URL Settings', 'authentype-font-specimen'); ?></h1>
        <p><?php esc_html_e('Set the default license-details destination for every Athtyp product on this website. Individual products can override it from their edit screen.', 'authentype-font-specimen'); ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('ath_specimen_license_url_settings'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ath-license-url-template"><?php esc_html_e('Global License URL', 'authentype-font-specimen'); ?></label></th>
                    <td>
                        <input
                            id="ath-license-url-template"
                            name="<?php echo esc_attr(ath_specimen_license_url_option_name()); ?>"
                            type="text"
                            class="regular-text code"
                            value="<?php echo esc_attr($template); ?>"
                            placeholder="https://example.com/licenses/#{license}"
                        >
                        <p class="description">
                            <?php esc_html_e('Use {license} where the license slug should appear. Examples: https://example.com/licenses/#{license} or https://example.com/licenses/{license}/. If {license} is omitted, #desktop, #webfont, etc. are appended automatically unless the URL already has a fragment. Leave blank to use /licenses/#license-slug.', 'authentype-font-specimen'); ?>
                        </p>
                        <p class="description"><strong><?php esc_html_e('Desktop example:', 'authentype-font-specimen'); ?></strong> <code><?php echo esc_html($desktop_example); ?></code></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Save License URL', 'authentype-font-specimen')); ?>
        </form>
    </div>
    <?php
}
