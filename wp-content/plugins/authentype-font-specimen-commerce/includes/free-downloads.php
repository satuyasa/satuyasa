<?php
defined('ABSPATH') || exit;

function ath_free_download_types() {
    return array(
        'font' => __('Free Font', 'authentype-font-specimen'),
        'vector' => __('Vector', 'authentype-font-specimen'),
        'template' => __('Template', 'authentype-font-specimen'),
        'mockup' => __('Mockup', 'authentype-font-specimen'),
        'other' => __('Other', 'authentype-font-specimen'),
    );
}

function ath_free_download_license_default_presets() {
    return array(
        'tester' => array(
            'label' => __('Tester', 'authentype-font-specimen'),
            'version' => '1.0',
            'summary' => '',
            'document_url' => '',
        ),
        'demo' => array(
            'label' => __('Demo', 'authentype-font-specimen'),
            'version' => '1.0',
            'summary' => '',
            'document_url' => '',
        ),
        'free-personal' => array(
            'label' => __('Free Personal', 'authentype-font-specimen'),
            'version' => '1.0',
            'summary' => '',
            'document_url' => '',
        ),
        'free-commercial-limited' => array(
            'label' => __('Free Commercial Limited', 'authentype-font-specimen'),
            'version' => '1.0',
            'summary' => __('Commercial print and social media use, plus personal non-commercial use. Web embedding, apps, games, software, commercial video, font redistribution, and redistribution of modified fonts are not included.', 'authentype-font-specimen'),
            'document_url' => AUTHENTYPE_SPECIMEN_URL . 'assets/free-licenses/Free_Commercial_Limited_License.pdf',
        ),
        'free-commercial' => array(
            'label' => __('Free Commercial', 'authentype-font-specimen'),
            'version' => '1.0',
            'summary' => __('Personal and commercial design use including merchandise, packaging, advertisements, marketing materials, and print or digital client work. Font redistribution and app, game, or software embedding without prior written permission are not included.', 'authentype-font-specimen'),
            'document_url' => AUTHENTYPE_SPECIMEN_URL . 'assets/free-licenses/Free_License_Authentype_Detailed_License.pdf',
        ),
    );
}

function ath_free_download_license_presets() {
    $defaults = ath_free_download_license_default_presets();
    $saved = get_option('ath_free_download_license_presets', array());
    $saved = is_array($saved) ? $saved : array();
    $presets = array();

    foreach ($defaults as $key => $fallback) {
        $row = isset($saved[$key]) && is_array($saved[$key]) ? $saved[$key] : array();
        $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
        $version = isset($row['version']) ? sanitize_text_field($row['version']) : '';
        $summary = isset($row['summary']) ? sanitize_textarea_field($row['summary']) : '';
        $document_raw = isset($row['document_url']) ? (string) $row['document_url'] : '';
        $document_url = '__bundled__' === $document_raw ? $fallback['document_url'] : ath_free_download_sanitize_url($document_raw);

        $presets[$key] = array(
            'key' => $key,
            'label' => $label !== '' ? $label : $fallback['label'],
            'version' => $version !== '' ? $version : $fallback['version'],
            'summary' => array_key_exists('summary', $row) ? $summary : $fallback['summary'],
            'document_url' => array_key_exists('document_url', $row) ? $document_url : $fallback['document_url'],
            'source' => 'global',
        );
    }

    return $presets;
}

function ath_free_download_license_types() {
    $types = array();
    foreach (ath_free_download_license_presets() as $key => $preset) {
        $types[$key] = $preset['label'];
    }
    $types['custom'] = __('Custom License', 'authentype-font-specimen');
    return $types;
}

function ath_free_download_default_license_key() {
    $presets = ath_free_download_license_presets();
    $key = sanitize_key((string) get_option('ath_free_download_default_license', 'demo'));
    return isset($presets[$key]) ? $key : 'demo';
}

function ath_free_download_selected_license_key($download_id) {
    $download_id = absint($download_id);
    if ($download_id && metadata_exists('post', $download_id, '_ath_free_download_license_type')) {
        $key = sanitize_key((string) get_post_meta($download_id, '_ath_free_download_license_type', true));
        if (array_key_exists($key, ath_free_download_license_types())) return $key;
    }
    return ath_free_download_default_license_key();
}

function ath_free_download_resolve_license($download_id) {
    $download_id = absint($download_id);
    $key = ath_free_download_selected_license_key($download_id);
    $presets = ath_free_download_license_presets();

    if ('custom' === $key) {
        $label = sanitize_text_field((string) ath_specimen_get_meta($download_id, '_ath_free_download_license_label', ''));
        $version = sanitize_text_field((string) ath_specimen_get_meta($download_id, '_ath_free_download_license_version', '1.0'));
        $summary = sanitize_textarea_field((string) ath_specimen_get_meta($download_id, '_ath_free_download_license_summary', ''));
        $document_url = ath_free_download_sanitize_url(ath_specimen_get_meta($download_id, '_ath_free_download_license_document_url', ''));
        return array(
            'key' => 'custom',
            'label' => $label !== '' ? $label : __('Custom License', 'authentype-font-specimen'),
            'version' => $version !== '' ? $version : '1.0',
            'summary' => $summary,
            'document_url' => $document_url,
            'source' => 'custom',
        );
    }

    if (isset($presets[$key])) return $presets[$key];
    return $presets['demo'];
}

function ath_free_download_license_fingerprint($license) {
    $license = is_array($license) ? $license : array();
    $canonical = array(
        'key' => sanitize_key((string) ($license['key'] ?? '')),
        'label' => sanitize_text_field((string) ($license['label'] ?? '')),
        'version' => sanitize_text_field((string) ($license['version'] ?? '')),
        'summary' => sanitize_textarea_field((string) ($license['summary'] ?? '')),
        'document_url' => ath_free_download_sanitize_url((string) ($license['document_url'] ?? '')),
    );
    return hash_hmac('sha256', wp_json_encode($canonical), wp_salt('auth'));
}

function ath_free_download_license_settings_url() {
    return admin_url('edit.php?post_type=ath_font&page=ath-free-license-settings');
}

add_action('admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=ath_font',
        __('Free License Settings', 'authentype-font-specimen'),
        __('Free Licenses', 'authentype-font-specimen'),
        'manage_options',
        'ath-free-license-settings',
        'ath_free_download_render_license_settings_page'
    );
}, 25);

add_action('admin_enqueue_scripts', function ($hook) {
    if (!authentype_specimen_can_manage_internal()) return;
    if (false === strpos((string) $hook, 'ath-free-license-settings')) return;
    wp_enqueue_media();
});

function ath_free_download_render_license_settings_page() {
    if (!authentype_specimen_can_manage_internal()) return;

    $presets = ath_free_download_license_presets();
    $default_key = ath_free_download_default_license_key();
    $saved = isset($_GET['ath_free_license_saved']);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Free License Settings', 'authentype-font-specimen'); ?></h1>
        <p><?php esc_html_e('Create one site-wide license authority for Free Downloads. Each free item reuses a preset, while the accepted license label, version, summary, and document are snapshotted on the lead record at download time.', 'authentype-font-specimen'); ?></p>
        <?php if ($saved) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('Free license settings saved.', 'authentype-font-specimen'); ?></p></div><?php endif; ?>
        <div class="notice notice-info inline"><p><?php esc_html_e('The bundled Free Commercial Limited and Free Commercial summaries follow the two Authentype license documents supplied for this release. Tester, Demo, and Free Personal terms are intentionally left without inferred summaries until you configure them.', 'authentype-font-specimen'); ?></p></div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="ath_free_license_settings_save">
            <?php wp_nonce_field('ath_free_license_settings_save', 'ath_free_license_settings_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="ath-free-default-license"><?php esc_html_e('Default Free Download License', 'authentype-font-specimen'); ?></label></th>
                    <td>
                        <select id="ath-free-default-license" name="default_license">
                            <?php foreach ($presets as $key => $preset) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($default_key, $key); ?>><?php echo esc_html($preset['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Used only when a Free Download item has never had a license preset saved. Existing items keep their current license selection.', 'authentype-font-specimen'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Reusable License Presets', 'authentype-font-specimen'); ?></h2>
            <table class="widefat striped" style="max-width:1200px;">
                <thead><tr>
                    <th style="width:16%;"><?php esc_html_e('Preset', 'authentype-font-specimen'); ?></th>
                    <th style="width:18%;"><?php esc_html_e('Frontend Label', 'authentype-font-specimen'); ?></th>
                    <th style="width:9%;"><?php esc_html_e('Version', 'authentype-font-specimen'); ?></th>
                    <th><?php esc_html_e('Short Scope / Summary', 'authentype-font-specimen'); ?></th>
                    <th style="width:27%;"><?php esc_html_e('License Document', 'authentype-font-specimen'); ?></th>
                </tr></thead>
                <tbody>
                    <?php foreach ($presets as $key => $preset) : ?>
                        <tr>
                            <td><code><?php echo esc_html($key); ?></code></td>
                            <td><input type="text" class="regular-text" name="presets[<?php echo esc_attr($key); ?>][label]" value="<?php echo esc_attr($preset['label']); ?>"></td>
                            <td><input type="text" style="width:100%;" name="presets[<?php echo esc_attr($key); ?>][version]" value="<?php echo esc_attr($preset['version']); ?>" placeholder="1.0"></td>
                            <td><textarea rows="4" style="width:100%;" name="presets[<?php echo esc_attr($key); ?>][summary]" placeholder="<?php esc_attr_e('Short explanation shown before download.', 'authentype-font-specimen'); ?>"><?php echo esc_textarea($preset['summary']); ?></textarea></td>
                            <td>
                                <div style="display:flex;gap:6px;align-items:center;">
                                    <input type="url" class="regular-text ath-free-license-document" style="min-width:0;flex:1;" name="presets[<?php echo esc_attr($key); ?>][document_url]" value="<?php echo esc_attr($preset['document_url']); ?>" placeholder="https://.../license.pdf">
                                    <button type="button" class="button ath-free-license-upload"><?php esc_html_e('Choose PDF', 'authentype-font-specimen'); ?></button>
                                </div>
                                <?php if ($preset['document_url']) : ?><p style="margin:6px 0 0;"><a href="<?php echo esc_url($preset['document_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View current document', 'authentype-font-specimen'); ?></a></p><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php submit_button(__('Save Free License Settings', 'authentype-font-specimen')); ?>
        </form>
    </div>
    <script>
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.ath-free-license-upload');
        if (!button || !window.wp || !wp.media) return;
        var input = button.parentElement ? button.parentElement.querySelector('.ath-free-license-document') : null;
        var frame = wp.media({title: 'Select license PDF', button: {text: 'Use this document'}, multiple: false, library: {type: 'application/pdf'}});
        frame.on('select', function () {
            var file = frame.state().get('selection').first().toJSON();
            if (input) input.value = file.url || '';
        });
        frame.open();
    });
    </script>
    <?php
}

add_action('admin_post_ath_free_license_settings_save', function () {
    if (!authentype_specimen_can_manage_internal()) wp_die(esc_html__('You are not allowed to manage free licenses.', 'authentype-font-specimen'));
    check_admin_referer('ath_free_license_settings_save', 'ath_free_license_settings_nonce');

    $defaults = ath_free_download_license_default_presets();
    $posted = isset($_POST['presets']) && is_array($_POST['presets']) ? wp_unslash($_POST['presets']) : array();
    $clean = array();
    foreach ($defaults as $key => $fallback) {
        $row = isset($posted[$key]) && is_array($posted[$key]) ? $posted[$key] : array();
        $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
        $version = isset($row['version']) ? sanitize_text_field($row['version']) : '';
        $clean[$key] = array(
            'label' => $label !== '' ? $label : $fallback['label'],
            'version' => $version !== '' ? $version : $fallback['version'],
            'summary' => isset($row['summary']) ? sanitize_textarea_field($row['summary']) : '',
            'document_url' => (isset($row['document_url']) && $fallback['document_url'] && hash_equals((string) $fallback['document_url'], (string) ath_free_download_sanitize_url($row['document_url']))) ? '__bundled__' : (isset($row['document_url']) ? ath_free_download_sanitize_url($row['document_url']) : ''),
        );
    }

    $default_key = isset($_POST['default_license']) ? sanitize_key(wp_unslash($_POST['default_license'])) : 'demo';
    if (!isset($defaults[$default_key])) $default_key = 'demo';
    update_option('ath_free_download_license_presets', $clean, false);
    update_option('ath_free_download_default_license', $default_key, false);

    wp_safe_redirect(add_query_arg('ath_free_license_saved', '1', ath_free_download_license_settings_url()));
    exit;
});

function ath_free_download_related_font_options($selected = 0) {
    $fonts = get_posts(array(
        'post_type' => 'ath_font',
        'post_status' => array('publish', 'draft', 'private'),
        'posts_per_page' => 300,
        'orderby' => 'title',
        'order' => 'ASC',
    ));

    echo '<option value="0">' . esc_html__('Global / not tied to a font', 'authentype-font-specimen') . '</option>';
    foreach ($fonts as $font) {
        echo '<option value="' . esc_attr($font->ID) . '" ' . selected((int) $selected, (int) $font->ID, false) . '>' . esc_html($font->post_title) . '</option>';
    }
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'ath_free_download_settings',
        __('Free Download Settings', 'authentype-font-specimen'),
        'ath_render_free_download_metabox',
        'ath_free_download',
        'normal',
        'high'
    );
});

function ath_render_free_download_metabox($post) {
    wp_nonce_field('ath_free_download_save_meta', 'ath_free_download_meta_nonce');

    $type = ath_specimen_get_meta($post->ID, '_ath_free_download_type', 'font');
    $file_url = ath_specimen_get_meta($post->ID, '_ath_free_download_file', '');
    $external_url = ath_specimen_get_meta($post->ID, '_ath_free_download_external_url', '');
    $button_label = ath_specimen_get_meta($post->ID, '_ath_free_download_button_label', __('Download Free', 'authentype-font-specimen'));
    $license_type = ath_free_download_selected_license_key($post->ID);
    $license = ath_free_download_resolve_license($post->ID);
    $custom_license_label = ath_specimen_get_meta($post->ID, '_ath_free_download_license_label', '');
    $custom_license_version = ath_specimen_get_meta($post->ID, '_ath_free_download_license_version', '1.0');
    $custom_license_summary = ath_specimen_get_meta($post->ID, '_ath_free_download_license_summary', '');
    $custom_license_document_url = ath_specimen_get_meta($post->ID, '_ath_free_download_license_document_url', '');
    $related_font = (int) ath_specimen_get_meta($post->ID, '_ath_free_download_related_font', 0);
    $require_email = ath_specimen_get_meta($post->ID, '_ath_free_download_require_email', '1');
    $note = ath_specimen_get_meta($post->ID, '_ath_free_download_note', '');
    $display_order = (int) ath_specimen_get_meta($post->ID, '_ath_free_download_display_order', 10);
    ?>
    <div class="ath-admin-metabox">
        <section class="ath-admin-section ath-builder-flow">
            <h3><?php esc_html_e('Frontend Shortcodes', 'authentype-font-specimen'); ?></h3>
            <p class="description"><?php esc_html_e('Use these on any page to display free download cards.', 'authentype-font-specimen'); ?></p>
            <p><code>[authentype_free_downloads]</code></p>
            <p><code>[authentype_free_downloads type="font" limit="8"]</code></p>
        </section>
        <section class="ath-admin-section ath-builder-flow">
            <div class="ath-admin-grid">
                <label>
                    <span><?php esc_html_e('Download Type', 'authentype-font-specimen'); ?></span>
                    <select name="ath_free_download_type">
                        <?php foreach (ath_free_download_types() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($type, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Related Athtyp Font', 'authentype-font-specimen'); ?></span>
                    <select name="ath_free_download_related_font">
                        <?php ath_free_download_related_font_options($related_font); ?>
                    </select>
                </label>
                <label>
                    <span><?php esc_html_e('Download File', 'authentype-font-specimen'); ?></span>
                    <input type="url" class="ath-free-download-file" name="ath_free_download_file" value="<?php echo esc_attr($file_url); ?>" placeholder="https://.../free-pack.zip">
                    <button type="button" class="button ath-upload-free-download"><?php esc_html_e('Upload File', 'authentype-font-specimen'); ?></button>
                </label>
                <label>
                    <span><?php esc_html_e('External URL', 'authentype-font-specimen'); ?></span>
                    <input type="url" name="ath_free_download_external_url" value="<?php echo esc_attr($external_url); ?>" placeholder="https://...">
                </label>
                <label>
                    <span><?php esc_html_e('Button Label', 'authentype-font-specimen'); ?></span>
                    <input type="text" name="ath_free_download_button_label" value="<?php echo esc_attr($button_label); ?>" placeholder="Download Free">
                </label>
                <label>
                    <span><?php esc_html_e('License Preset', 'authentype-font-specimen'); ?></span>
                    <select name="ath_free_download_license_type" id="ath-free-download-license-type">
                        <?php foreach (ath_free_download_license_types() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($license_type, $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="description"><a href="<?php echo esc_url(ath_free_download_license_settings_url()); ?>"><?php esc_html_e('Manage global Free License presets', 'authentype-font-specimen'); ?></a></small>
                </label>
                <label>
                    <span><?php esc_html_e('Display Order', 'authentype-font-specimen'); ?></span>
                    <input type="number" name="ath_free_download_display_order" min="0" max="9999" step="1" value="<?php echo esc_attr($display_order); ?>">
                    <small class="description"><?php esc_html_e('Lower numbers appear first.', 'authentype-font-specimen'); ?></small>
                </label>
                <label>
                    <span><?php esc_html_e('Email Gate', 'authentype-font-specimen'); ?></span>
                    <label><input type="checkbox" name="ath_free_download_require_email" value="1" <?php checked(!empty($require_email)); ?>> <?php esc_html_e('Require email before download', 'authentype-font-specimen'); ?></label>
                </label>
            </div>
            <div class="ath-free-license-authority" style="margin:18px 0;padding:16px;border:1px solid #dcdcde;background:#fff;">
                <div style="display:flex;justify-content:space-between;gap:18px;align-items:flex-start;flex-wrap:wrap;">
                    <div>
                        <strong><?php esc_html_e('Current Free License', 'authentype-font-specimen'); ?></strong>
                        <p id="ath-free-license-live-summary" style="margin:6px 0 0;max-width:900px;">
                            <strong><?php echo esc_html($license['label']); ?></strong>
                            <?php if (!empty($license['version'])) : ?> <span><?php echo esc_html(sprintf(__('v%s', 'authentype-font-specimen'), $license['version'])); ?></span><?php endif; ?>
                            <?php if (!empty($license['summary'])) : ?><br><span><?php echo esc_html($license['summary']); ?></span><?php endif; ?>
                            <?php if (!empty($license['document_url'])) : ?><br><a href="<?php echo esc_url($license['document_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('View license document', 'authentype-font-specimen'); ?></a><?php endif; ?>
                        </p>
                    </div>
                    <small class="description"><?php esc_html_e('This exact label, version, summary, and document are recorded on gated-download leads when the visitor accepts the license.', 'authentype-font-specimen'); ?></small>
                </div>
                <div class="ath-free-license-custom-fields" <?php echo 'custom' === $license_type ? '' : 'hidden'; ?> style="margin-top:16px;padding-top:16px;border-top:1px solid #dcdcde;">
                    <h4 style="margin:0 0 12px;"><?php esc_html_e('Custom License Override', 'authentype-font-specimen'); ?></h4>
                    <div class="ath-admin-grid">
                        <label>
                            <span><?php esc_html_e('Custom Label', 'authentype-font-specimen'); ?></span>
                            <input type="text" name="ath_free_download_license_label" value="<?php echo esc_attr($custom_license_label); ?>" placeholder="Custom License">
                        </label>
                        <label>
                            <span><?php esc_html_e('Version', 'authentype-font-specimen'); ?></span>
                            <input type="text" name="ath_free_download_license_version" value="<?php echo esc_attr($custom_license_version); ?>" placeholder="1.0">
                        </label>
                        <label style="grid-column:1/-1;">
                            <span><?php esc_html_e('Short Scope / Summary', 'authentype-font-specimen'); ?></span>
                            <textarea name="ath_free_download_license_summary" rows="3"><?php echo esc_textarea($custom_license_summary); ?></textarea>
                        </label>
                        <label style="grid-column:1/-1;">
                            <span><?php esc_html_e('License Document URL', 'authentype-font-specimen'); ?></span>
                            <input type="url" name="ath_free_download_license_document_url" value="<?php echo esc_attr($custom_license_document_url); ?>" placeholder="https://.../license.pdf">
                            <small class="description"><?php esc_html_e('Use a public HTTPS URL for the custom license document. Global presets can upload/select PDFs from Athtyp → Free Licenses.', 'authentype-font-specimen'); ?></small>
                        </label>
                    </div>
                </div>
            </div>
            <p class="description"><?php esc_html_e('Use Download File for files stored in Media Library. Use External URL only when the free item is hosted elsewhere. For vector/template/font packs, ZIP is safer than raw SVG files.', 'authentype-font-specimen'); ?></p>
            <p class="description"><strong><?php esc_html_e('Card description:', 'authentype-font-specimen'); ?></strong> <?php esc_html_e('the WordPress Excerpt is the short description shown on the product specimen card. The main editor remains available for the standalone Free Download page.', 'authentype-font-specimen'); ?></p>
            <label>
                <span><?php esc_html_e('Small Note', 'authentype-font-specimen'); ?></span>
                <textarea name="ath_free_download_note" rows="3" style="width:100%;"><?php echo esc_textarea($note); ?></textarea>
            </label>
        </section>
    </div>
    <script>
    (function () {
        var select = document.getElementById('ath-free-download-license-type');
        var custom = document.querySelector('.ath-free-license-custom-fields');
        if (!select || !custom) return;
        select.addEventListener('change', function () {
            custom.hidden = select.value !== 'custom';
        });
    }());
    </script>
    <?php
}

add_action('save_post_ath_free_download', function ($post_id) {
    if (!isset($_POST['ath_free_download_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ath_free_download_meta_nonce'])), 'ath_free_download_save_meta')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!function_exists('authentype_specimen_can_manage_internal') || !authentype_specimen_can_manage_internal()) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $type = isset($_POST['ath_free_download_type']) ? sanitize_key(wp_unslash($_POST['ath_free_download_type'])) : 'font';
    if (!array_key_exists($type, ath_free_download_types())) {
        $type = 'other';
    }

    update_post_meta($post_id, '_ath_free_download_type', $type);
    update_post_meta($post_id, '_ath_free_download_file', isset($_POST['ath_free_download_file']) ? ath_free_download_sanitize_url(wp_unslash($_POST['ath_free_download_file'])) : '');
    update_post_meta($post_id, '_ath_free_download_external_url', isset($_POST['ath_free_download_external_url']) ? ath_free_download_sanitize_url(wp_unslash($_POST['ath_free_download_external_url'])) : '');
    update_post_meta($post_id, '_ath_free_download_button_label', isset($_POST['ath_free_download_button_label']) ? sanitize_text_field(wp_unslash($_POST['ath_free_download_button_label'])) : '');
    update_post_meta($post_id, '_ath_free_download_related_font', isset($_POST['ath_free_download_related_font']) ? absint($_POST['ath_free_download_related_font']) : 0);
    $license_type = isset($_POST['ath_free_download_license_type']) ? sanitize_key(wp_unslash($_POST['ath_free_download_license_type'])) : ath_free_download_default_license_key();
    if (!array_key_exists($license_type, ath_free_download_license_types())) {
        $license_type = ath_free_download_default_license_key();
    }
    update_post_meta($post_id, '_ath_free_download_license_type', $license_type);
    update_post_meta($post_id, '_ath_free_download_license_label', isset($_POST['ath_free_download_license_label']) ? sanitize_text_field(wp_unslash($_POST['ath_free_download_license_label'])) : '');
    update_post_meta($post_id, '_ath_free_download_license_version', isset($_POST['ath_free_download_license_version']) ? sanitize_text_field(wp_unslash($_POST['ath_free_download_license_version'])) : '1.0');
    update_post_meta($post_id, '_ath_free_download_license_summary', isset($_POST['ath_free_download_license_summary']) ? sanitize_textarea_field(wp_unslash($_POST['ath_free_download_license_summary'])) : '');
    update_post_meta($post_id, '_ath_free_download_license_document_url', isset($_POST['ath_free_download_license_document_url']) ? ath_free_download_sanitize_url(wp_unslash($_POST['ath_free_download_license_document_url'])) : '');
    update_post_meta($post_id, '_ath_free_download_require_email', !empty($_POST['ath_free_download_require_email']) ? '1' : '0');
    update_post_meta($post_id, '_ath_free_download_note', isset($_POST['ath_free_download_note']) ? sanitize_textarea_field(wp_unslash($_POST['ath_free_download_note'])) : '');
    $display_order = isset($_POST['ath_free_download_display_order']) ? absint($_POST['ath_free_download_display_order']) : 10;
    update_post_meta($post_id, '_ath_free_download_display_order', min(9999, $display_order));
});

function ath_free_download_client_ip_hash() {
    $ip = function_exists('ath_specimen_client_ip') ? ath_specimen_client_ip() : '';
    return $ip ? wp_hash($ip) : '';
}

function ath_free_download_sanitize_url($url) {
    return esc_url_raw((string) $url, array('http', 'https'));
}

function ath_free_download_rate_key($download_id, $email) {
    return 'ath_free_dl_' . md5(absint($download_id) . '|' . strtolower((string) $email) . '|' . ath_free_download_client_ip_hash());
}

function ath_free_download_ip_rate_limited($increment = false) {
    $ip_hash = ath_free_download_client_ip_hash();
    if (!$ip_hash) return false;
    $key = 'ath_free_ip_' . md5($ip_hash);
    $data = get_transient($key);
    $data = is_array($data) ? $data : array('count' => 0);
    $max = max(3, (int) apply_filters('authentype_specimen_free_download_requests_per_hour', 12));
    if ((int) ($data['count'] ?? 0) >= $max) return true;
    if ($increment) {
        $data['count'] = (int) ($data['count'] ?? 0) + 1;
        set_transient($key, $data, HOUR_IN_SECONDS);
    }
    return false;
}

function ath_free_download_url_for_item($download_id) {
    $download_id = absint($download_id);
    if (!$download_id || 'ath_free_download' !== get_post_type($download_id) || 'publish' !== get_post_status($download_id)) {
        return '';
    }

    $file_url = ath_free_download_sanitize_url(ath_specimen_get_meta($download_id, '_ath_free_download_file', ''));
    $external_url = ath_free_download_sanitize_url(ath_specimen_get_meta($download_id, '_ath_free_download_external_url', ''));
    return $file_url ?: $external_url;
}

function ath_free_download_local_path_from_url($url) {
    $url = ath_free_download_sanitize_url($url);
    return $url ? ath_specimen_local_upload_path($url) : '';
}

function ath_free_download_create_token($download_id, $lead_id) {
    $download_url = ath_free_download_url_for_item($download_id);
    if (!$download_url) {
        return '';
    }

    $token = wp_generate_password(40, false, false);
    set_transient('ath_free_download_token_' . $token, array(
        'download_id' => absint($download_id),
        'lead_id' => absint($lead_id),
        'url' => $download_url,
        'ip_hash' => ath_free_download_client_ip_hash(),
        'created' => time(),
    ), 15 * MINUTE_IN_SECONDS);
    if ($lead_id) {
        update_post_meta(absint($lead_id), '_ath_lead_token_created_at', current_time('mysql'));
    }

    return $token;
}

function ath_free_download_token_url($token) {
    return add_query_arg(array(
        'action' => 'ath_free_download_token',
        'token' => rawurlencode((string) $token),
    ), admin_url('admin-post.php'));
}

function ath_free_download_stream_file($path) {
    if (!is_file($path) || !is_readable($path)) {
        status_header(404);
        exit;
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = basename($path);
    $mime = wp_check_filetype($filename);
    $content_type = !empty($mime['type']) ? $mime['type'] : 'application/octet-stream';

    nocache_headers();
    header('Content-Type: ' . $content_type);
    header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function ath_free_download_store_lead($download_id, $name, $email, $license = null) {
    $license = is_array($license) ? $license : ath_free_download_resolve_license($download_id);
    $download_title = get_the_title($download_id);
    $title = trim($name) ? $name . ' - ' . $email : $email;
    $accepted_at = current_time('mysql');
    $fingerprint = ath_free_download_license_fingerprint($license);

    $lead_id = wp_insert_post(array(
        'post_type' => 'ath_free_lead',
        'post_status' => 'private',
        'post_title' => sanitize_text_field($title),
    ), true);

    if (is_wp_error($lead_id) || !$lead_id) {
        return 0;
    }

    $snapshot = array(
        'key' => sanitize_key((string) ($license['key'] ?? '')),
        'label' => sanitize_text_field((string) ($license['label'] ?? '')),
        'version' => sanitize_text_field((string) ($license['version'] ?? '')),
        'summary' => sanitize_textarea_field((string) ($license['summary'] ?? '')),
        'document_url' => ath_free_download_sanitize_url((string) ($license['document_url'] ?? '')),
        'source' => sanitize_key((string) ($license['source'] ?? 'global')),
        'accepted_at' => $accepted_at,
        'fingerprint' => $fingerprint,
    );

    update_post_meta($lead_id, '_ath_lead_name', sanitize_text_field($name));
    update_post_meta($lead_id, '_ath_lead_email', sanitize_email($email));
    update_post_meta($lead_id, '_ath_lead_download_id', absint($download_id));
    update_post_meta($lead_id, '_ath_lead_download_title', sanitize_text_field($download_title));
    update_post_meta($lead_id, '_ath_lead_license_type', $snapshot['key']);
    update_post_meta($lead_id, '_ath_lead_license_label', $snapshot['label']);
    update_post_meta($lead_id, '_ath_lead_license_version', $snapshot['version']);
    update_post_meta($lead_id, '_ath_lead_license_summary', $snapshot['summary']);
    update_post_meta($lead_id, '_ath_lead_license_document_url', $snapshot['document_url']);
    update_post_meta($lead_id, '_ath_lead_license_source', $snapshot['source']);
    update_post_meta($lead_id, '_ath_lead_license_accepted_at', $accepted_at);
    update_post_meta($lead_id, '_ath_lead_license_fingerprint', $fingerprint);
    update_post_meta($lead_id, '_ath_lead_license_snapshot', $snapshot);
    update_post_meta($lead_id, '_ath_lead_ip_hash', ath_free_download_client_ip_hash());
    update_post_meta($lead_id, '_ath_lead_user_agent', isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 255) : '');
    update_post_meta($lead_id, '_ath_lead_requested_at', $accepted_at);

    return $lead_id;
}

add_action('wp_ajax_ath_free_download_request', 'ath_free_download_ajax_request');
add_action('wp_ajax_nopriv_ath_free_download_request', 'ath_free_download_ajax_request');
add_action('admin_post_ath_free_download_token', 'ath_free_download_token_handler');
add_action('admin_post_nopriv_ath_free_download_token', 'ath_free_download_token_handler');

function ath_free_download_token_handler() {
    $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
    if (!$token || !preg_match('/^[A-Za-z0-9]{20,80}$/', $token)) {
        status_header(403);
        exit;
    }

    // add_option is backed by a unique option key, giving us an atomic claim on
    // this one-time token even when two browser requests arrive concurrently.
    $claim_key = 'ath_fd_claim_' . md5($token);
    if (!add_option($claim_key, time(), '', false)) {
        $claimed_at = (int) get_option($claim_key, 0);
        if ($claimed_at > 0 && (time() - $claimed_at) > 2 * MINUTE_IN_SECONDS) {
            delete_option($claim_key);
        }
        if (!add_option($claim_key, time(), '', false)) {
            status_header(409);
            exit;
        }
    }

    $release_claim = static function () use ($claim_key) {
        delete_option($claim_key);
    };

    $key = 'ath_free_download_token_' . $token;
    $data = get_transient($key);
    if (!is_array($data) || empty($data['download_id']) || empty($data['url'])) {
        delete_transient($key);
        $release_claim();
        status_header(403);
        exit;
    }

    // Validate before consuming the token. A temporary network/proxy mismatch
    // no longer burns an otherwise valid 15-minute link.
    if (!empty($data['ip_hash']) && !hash_equals((string) $data['ip_hash'], (string) ath_free_download_client_ip_hash())) {
        $release_claim();
        status_header(403);
        exit;
    }

    $download_url = ath_free_download_url_for_item($data['download_id']);
    if (!$download_url || !hash_equals((string) ath_free_download_sanitize_url($data['url']), (string) $download_url)) {
        $release_claim();
        status_header(404);
        exit;
    }

    $path = ath_free_download_local_path_from_url($download_url);
    // Consume only after every authorization/path check has succeeded.
    delete_transient($key);
    if (!empty($data['lead_id'])) {
        $lead_id = absint($data['lead_id']);
        update_post_meta($lead_id, '_ath_lead_token_used_at', current_time('mysql'));
        update_post_meta($lead_id, '_ath_lead_downloaded_at', current_time('mysql'));
    }
    $release_claim();

    if ($path) {
        ath_free_download_stream_file($path);
    }

    wp_redirect($download_url, 302);
    exit;
}

function ath_free_download_ajax_request() {
    check_ajax_referer('ath_free_download', 'nonce');

    $download_id = isset($_POST['download_id']) ? absint($_POST['download_id']) : 0;
    $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $agree = !empty($_POST['agree']);
    $license_fingerprint = isset($_POST['license_fingerprint']) ? sanitize_text_field(wp_unslash($_POST['license_fingerprint'])) : '';
    $honeypot = isset($_POST['website']) ? trim((string) wp_unslash($_POST['website'])) : '';

    if ($honeypot) {
        wp_send_json_error(array('message' => __('Download request rejected.', 'authentype-font-specimen')), 400);
    }

    $download_url = ath_free_download_url_for_item($download_id);
    if (!$download_url) {
        wp_send_json_error(array('message' => __('Download file is unavailable.', 'authentype-font-specimen')), 404);
    }

    if (!$email || !is_email($email)) {
        wp_send_json_error(array('message' => __('Enter a valid email address.', 'authentype-font-specimen')), 400);
    }

    if (!$agree) {
        wp_send_json_error(array('message' => __('Please agree to the free license terms.', 'authentype-font-specimen')), 400);
    }

    $license = ath_free_download_resolve_license($download_id);
    $current_fingerprint = ath_free_download_license_fingerprint($license);
    if (!$license_fingerprint || !hash_equals($current_fingerprint, $license_fingerprint)) {
        wp_send_json_error(array('message' => __('The license terms changed after this page was loaded. Refresh the page and review the current license before downloading.', 'authentype-font-specimen')), 409);
    }

    if (ath_free_download_ip_rate_limited()) {
        wp_send_json_error(array('message' => __('Too many download requests were made from this network. Please try again later.', 'authentype-font-specimen')), 429);
    }

    $rate_key = ath_free_download_rate_key($download_id, $email);
    if (get_transient($rate_key)) {
        wp_send_json_error(array('message' => __('Please wait before requesting this download again.', 'authentype-font-specimen')), 429);
    }
    set_transient($rate_key, 1, 5 * MINUTE_IN_SECONDS);
    ath_free_download_ip_rate_limited(true);

    $lead_id = ath_free_download_store_lead($download_id, $name, $email, $license);
    if (!$lead_id) {
        wp_send_json_error(array('message' => __('Could not save your download request.', 'authentype-font-specimen')), 500);
    }

    $token = ath_free_download_create_token($download_id, $lead_id);
    if (!$token) {
        wp_send_json_error(array('message' => __('Could not prepare your secure download link.', 'authentype-font-specimen')), 500);
    }

    wp_send_json_success(array(
        'message' => __('Thanks. Your download is ready.', 'authentype-font-specimen'),
        'download_url' => esc_url_raw(ath_free_download_token_url($token)),
    ));
}

function ath_free_downloads_shortcode($atts = array()) {
    $atts = shortcode_atts(array(
        'type' => '',
        'limit' => 12,
        'font_id' => 0,
        'include_global' => 0,
    ), $atts, 'authentype_free_downloads');

    $meta_query = array();
    $type = sanitize_key($atts['type']);
    if ($type && array_key_exists($type, ath_free_download_types())) {
        $meta_query[] = array(
            'key' => '_ath_free_download_type',
            'value' => $type,
        );
    }

    $font_id = absint($atts['font_id']);
    if ($font_id) {
        $related_query = array(
            'relation' => 'OR',
            array(
                'key' => '_ath_free_download_related_font',
                'value' => $font_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ),
        );

        if (!empty($atts['include_global'])) {
            $related_query[] = array(
                'key' => '_ath_free_download_related_font',
                'compare' => 'NOT EXISTS',
            );
            $related_query[] = array(
                'key' => '_ath_free_download_related_font',
                'value' => '0',
                'compare' => '=',
                'type' => 'NUMERIC',
            );
        }

        $meta_query[] = $related_query;
    }

    $limit = max(1, min(48, (int) $atts['limit']));
    // Fetch a bounded candidate set first, then sort by the dedicated UI order.
    // This keeps older items (which do not yet have the meta key) visible.
    $items = get_posts(array(
        'post_type' => 'ath_free_download',
        'post_status' => 'publish',
        'posts_per_page' => 96,
        'meta_query' => $meta_query,
        'orderby' => array('date' => 'DESC', 'ID' => 'DESC'),
    ));

    if (!empty($items)) {
        usort($items, static function ($a, $b) {
            $a_order = (int) ath_specimen_get_meta($a->ID, '_ath_free_download_display_order', 10);
            $b_order = (int) ath_specimen_get_meta($b->ID, '_ath_free_download_display_order', 10);
            if ($a_order === $b_order) {
                return (int) $b->ID <=> (int) $a->ID;
            }
            return $a_order <=> $b_order;
        });
        $items = array_slice($items, 0, $limit);
    }

    if (empty($items)) {
        return '';
    }

    wp_enqueue_style('authentype-font-specimen');
    wp_enqueue_script('authentype-font-specimen');
    wp_localize_script('authentype-font-specimen', 'AthFreeDownloads', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ath_free_download'),
        'i18n' => array(
            'ready' => __('Thanks. Your download is ready.', 'authentype-font-specimen'),
            'failed' => __('Could not prepare the download.', 'authentype-font-specimen'),
            'preparing' => __('Preparing…', 'authentype-font-specimen'),
        ),
    ));
    $types = ath_free_download_types();
    ob_start();
    ?>
    <div class="ath-free-downloads">
        <?php foreach ($items as $item) : ?>
            <?php
            $item_type = ath_specimen_get_meta($item->ID, '_ath_free_download_type', 'other');
            $download_url = ath_free_download_url_for_item($item->ID);
            $button_label = ath_specimen_get_meta($item->ID, '_ath_free_download_button_label', __('Download Free', 'authentype-font-specimen'));
            $license = ath_free_download_resolve_license($item->ID);
            $license_fingerprint = ath_free_download_license_fingerprint($license);
            $require_email = ath_specimen_get_meta($item->ID, '_ath_free_download_require_email', '1');
            $note = ath_specimen_get_meta($item->ID, '_ath_free_download_note', '');
            ?>
            <article class="ath-free-download-card<?php echo has_post_thumbnail($item) ? ' has-thumb' : ' no-thumb'; ?>" data-free-download-id="<?php echo esc_attr($item->ID); ?>">
                <div class="ath-free-download-card-inner">
                    <?php if (has_post_thumbnail($item)) : ?>
                        <figure class="ath-free-download-thumb">
                            <?php echo get_the_post_thumbnail($item, 'medium_large', array('loading' => 'lazy')); ?>
                        </figure>
                    <?php endif; ?>
                    <div class="ath-free-download-content">
                        <div class="ath-free-download-kicker"><?php esc_html_e('Free Download', 'authentype-font-specimen'); ?></div>
                        <div class="ath-free-download-topline">
                            <div class="ath-free-download-badges">
                                <span class="ath-free-download-type"><?php echo esc_html(isset($types[$item_type]) ? $types[$item_type] : $types['other']); ?></span>
                                <span class="ath-free-download-license"><?php echo esc_html($license['label']); ?><?php if (!empty($license['version'])) : ?> · <?php echo esc_html(sprintf(__('v%s', 'authentype-font-specimen'), $license['version'])); ?><?php endif; ?></span>
                            </div>
                        </div>
                        <h3><?php echo esc_html(get_the_title($item)); ?></h3>
                        <?php if ($item->post_excerpt) : ?>
                            <p class="ath-free-download-excerpt"><?php echo esc_html($item->post_excerpt); ?></p>
                        <?php endif; ?>
                        <?php if ($note) : ?>
                            <div class="ath-free-download-note"><span aria-hidden="true">✓</span><small><?php echo esc_html($note); ?></small></div>
                        <?php endif; ?>
                        <div class="ath-free-download-license-info">
                            <span class="ath-free-download-license-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" focusable="false"><path d="M7.75 13.25a5.25 5.25 0 1 1 4.95-7h8.55v3h-2.5v2.5h-3v2.5h-3.05a5.24 5.24 0 0 1-4.95 3.5 5.25 5.25 0 0 1 0-10.5Zm0 3a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5Z" fill="currentColor"/></svg>
                            </span>
                            <div class="ath-free-download-license-info-body">
                                <div class="ath-free-download-license-eyebrow"><?php esc_html_e('Usage rights', 'authentype-font-specimen'); ?></div>
                                <div class="ath-free-download-license-info-head">
                                    <strong><?php echo esc_html($license['label']); ?></strong>
                                    <?php if (!empty($license['version'])) : ?><span><?php echo esc_html(sprintf(__('Version %s', 'authentype-font-specimen'), $license['version'])); ?></span><?php endif; ?>
                                </div>
                                <?php if (!empty($license['summary'])) : ?><p><?php echo esc_html($license['summary']); ?></p><?php endif; ?>
                                <?php if (!empty($license['document_url'])) : ?><a class="ath-free-download-license-link" href="<?php echo esc_url($license['document_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Read full license terms', 'authentype-font-specimen'); ?> <span aria-hidden="true">↗</span></a><?php endif; ?>
                            </div>
                        </div>
                        <?php if ($download_url) : ?>
                            <div class="ath-free-download-actions">
                                <p class="ath-free-download-action-note"><?php esc_html_e('License terms are shown above before download.', 'authentype-font-specimen'); ?></p>
                                <?php if (!empty($require_email)) : ?>
                                    <?php $form_id = 'ath-free-download-form-' . $item->ID; ?>
                                    <button type="button" class="ath-free-download-button ath-free-download-open" aria-expanded="false" aria-controls="<?php echo esc_attr($form_id); ?>"><?php echo esc_html($button_label ?: __('Download Free', 'authentype-font-specimen')); ?> <span aria-hidden="true">↓</span></button>
                                    <form class="ath-free-download-form" id="<?php echo esc_attr($form_id); ?>" hidden>
                                        <input type="hidden" name="download_id" value="<?php echo esc_attr($item->ID); ?>">
                                        <input type="hidden" name="license_fingerprint" value="<?php echo esc_attr($license_fingerprint); ?>">
                                        <input type="text" name="website" value="" autocomplete="off" tabindex="-1" class="ath-free-download-hp" aria-hidden="true">
                                        <div class="ath-free-download-form-head">
                                            <div><strong><?php esc_html_e('Get your free download', 'authentype-font-specimen'); ?></strong><small><?php esc_html_e('Enter your email to generate a secure download link.', 'authentype-font-specimen'); ?></small></div>
                                            <button type="button" class="ath-free-download-cancel" aria-label="<?php esc_attr_e('Close download form', 'authentype-font-specimen'); ?>">×</button>
                                        </div>
                                        <div class="ath-free-download-fields">
                                            <label>
                                                <span><?php esc_html_e('Name', 'authentype-font-specimen'); ?></span>
                                                <input type="text" name="name" autocomplete="name">
                                            </label>
                                            <label>
                                                <span><?php esc_html_e('Email', 'authentype-font-specimen'); ?> <em>*</em></span>
                                                <input type="email" name="email" required autocomplete="email">
                                            </label>
                                        </div>
                                        <label class="ath-free-download-agree">
                                            <input type="checkbox" name="agree" value="1" required>
                                            <span>
                                                <?php echo esc_html(sprintf(__('I agree to the %s', 'authentype-font-specimen'), $license['label'])); ?><?php if (!empty($license['version'])) : ?> <?php echo esc_html(sprintf(__('(v%s)', 'authentype-font-specimen'), $license['version'])); ?><?php endif; ?>.
                                                <?php if (!empty($license['document_url'])) : ?> <a href="<?php echo esc_url($license['document_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Read license', 'authentype-font-specimen'); ?></a><?php endif; ?>
                                            </span>
                                        </label>
                                        <div class="ath-free-download-form-actions">
                                            <button type="submit" class="ath-free-download-submit"><?php esc_html_e('Get Free Download', 'authentype-font-specimen'); ?></button>
                                            <small><?php esc_html_e('Your secure download link expires shortly.', 'authentype-font-specimen'); ?></small>
                                        </div>
                                        <p class="ath-free-download-message" role="status" aria-live="polite" hidden></p>
                                        <div class="ath-free-download-ready" hidden>
                                            <span class="ath-free-download-ready-icon" aria-hidden="true">✓</span>
                                            <div><strong><?php esc_html_e('Your download is ready', 'authentype-font-specimen'); ?></strong><small><?php esc_html_e('This secure link expires in 15 minutes.', 'authentype-font-specimen'); ?></small></div>
                                            <a href="#" data-free-download-ready-link><?php esc_html_e('Download now', 'authentype-font-specimen'); ?> <span aria-hidden="true">↓</span></a>
                                        </div>
                                    </form>
                                <?php else : ?>
                                    <a class="ath-free-download-button" href="<?php echo esc_url($download_url); ?>" download><?php echo esc_html($button_label ?: __('Download Free', 'authentype-font-specimen')); ?> <span aria-hidden="true">↓</span></a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('authentype_free_downloads', 'ath_free_downloads_shortcode');

add_action('add_meta_boxes_ath_free_lead', function () {
    add_meta_box(
        'ath_free_lead_license_snapshot',
        __('Accepted Free License', 'authentype-font-specimen'),
        'ath_free_download_render_lead_license_snapshot',
        'ath_free_lead',
        'normal',
        'high'
    );
});

function ath_free_download_render_lead_license_snapshot($post) {
    $label = ath_specimen_get_meta($post->ID, '_ath_lead_license_label', '');
    $version = ath_specimen_get_meta($post->ID, '_ath_lead_license_version', '');
    $summary = ath_specimen_get_meta($post->ID, '_ath_lead_license_summary', '');
    $url = ath_free_download_sanitize_url(ath_specimen_get_meta($post->ID, '_ath_lead_license_document_url', ''));
    $accepted_at = ath_specimen_get_meta($post->ID, '_ath_lead_license_accepted_at', '');
    $license_key = ath_specimen_get_meta($post->ID, '_ath_lead_license_type', '');
    $download_title = ath_specimen_get_meta($post->ID, '_ath_lead_download_title', '');
    if (!$label) $label = ath_specimen_label_from_slug($license_key);
    ?>
    <table class="widefat striped" style="max-width:900px;">
        <tbody>
            <tr><th style="width:190px;"><?php esc_html_e('Download', 'authentype-font-specimen'); ?></th><td><?php echo esc_html($download_title); ?></td></tr>
            <tr><th><?php esc_html_e('License', 'authentype-font-specimen'); ?></th><td><strong><?php echo esc_html($label); ?></strong><?php if ($license_key) : ?> <code><?php echo esc_html($license_key); ?></code><?php endif; ?></td></tr>
            <tr><th><?php esc_html_e('Accepted Version', 'authentype-font-specimen'); ?></th><td><?php echo esc_html($version ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Accepted At', 'authentype-font-specimen'); ?></th><td><?php echo esc_html($accepted_at ?: '—'); ?></td></tr>
            <tr><th><?php esc_html_e('Scope Snapshot', 'authentype-font-specimen'); ?></th><td><?php echo $summary ? esc_html($summary) : '—'; ?></td></tr>
            <tr><th><?php esc_html_e('License Document', 'authentype-font-specimen'); ?></th><td><?php if ($url) : ?><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Open accepted license document', 'authentype-font-specimen'); ?></a><?php else : ?>—<?php endif; ?></td></tr>
        </tbody>
    </table>
    <?php if (!$accepted_at) : ?><p class="description"><?php esc_html_e('This is a legacy lead created before versioned license snapshots were introduced; only fields that existed at the time can be shown.', 'authentype-font-specimen'); ?></p><?php endif; ?>
    <?php
}

add_filter('manage_ath_free_lead_posts_columns', function ($columns) {
    return array(
        'cb' => isset($columns['cb']) ? $columns['cb'] : '',
        'title' => __('Lead', 'authentype-font-specimen'),
        'email' => __('Email', 'authentype-font-specimen'),
        'download' => __('Download', 'authentype-font-specimen'),
        'license' => __('License', 'authentype-font-specimen'),
        'date' => __('Date', 'authentype-font-specimen'),
    );
});

add_action('manage_ath_free_lead_posts_custom_column', function ($column, $post_id) {
    if ('email' === $column) {
        echo esc_html(ath_specimen_get_meta($post_id, '_ath_lead_email', ''));
    }

    if ('download' === $column) {
        echo esc_html(ath_specimen_get_meta($post_id, '_ath_lead_download_title', ''));
    }

    if ('license' === $column) {
        $label = ath_specimen_get_meta($post_id, '_ath_lead_license_label', '');
        $version = ath_specimen_get_meta($post_id, '_ath_lead_license_version', '');
        $url = ath_free_download_sanitize_url(ath_specimen_get_meta($post_id, '_ath_lead_license_document_url', ''));
        if (!$label) $label = ath_specimen_label_from_slug(ath_specimen_get_meta($post_id, '_ath_lead_license_type', ''));
        echo esc_html($label);
        if ($version) echo '<br><small>' . esc_html(sprintf(__('Version %s', 'authentype-font-specimen'), $version)) . '</small>';
        if ($url) echo '<br><small><a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('License document', 'authentype-font-specimen') . '</a></small>';
    }
}, 10, 2);
?>
