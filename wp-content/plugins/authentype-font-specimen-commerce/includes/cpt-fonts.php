<?php
defined('ABSPATH') || exit;

function authentype_specimen_shop_slug() {
    $slug = sanitize_title((string) apply_filters('authentype_specimen_shop_slug', 'font-shop'));
    return $slug ?: 'font-shop';
}

function authentype_specimen_register_post_types() {
    $menu_icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M3 6.7h3.7C8.1 6.7 9 5.8 9.8 4.3L11.7 1h2.8v5.7H18v2.6h-3.5V19h-2.8V9.3H9.9L6.1 12v7H3.3v-5.1L1.6 15V12l4.3-2.7H3V6.7Zm7 0h1.7V3.8l-.7 1.3c-.3.6-.7 1.1-1 1.6ZM18.7 16.4c0 1.2-1 2.1-2.1 2.1s-2.1-.9-2.1-2.1 1-2.1 2.1-2.1 2.1.9 2.1 2.1Z"/></svg>');

    register_post_type('ath_font', array(
        'labels' => array(
            'name' => __('Athtyp', 'authentype-font-specimen'),
            'singular_name' => __('Athtyp', 'authentype-font-specimen'),
            'menu_name' => __('Athtyp', 'authentype-font-specimen'),
            'add_new' => __('Add New', 'authentype-font-specimen'),
            'add_new_item' => __('Add New Athtyp', 'authentype-font-specimen'),
            'edit_item' => __('Edit Athtyp', 'authentype-font-specimen'),
            'new_item' => __('New Athtyp', 'authentype-font-specimen'),
            'view_item' => __('View Athtyp', 'authentype-font-specimen'),
            'search_items' => __('Search Athtyp', 'authentype-font-specimen'),
            'not_found' => __('No Athtyp found', 'authentype-font-specimen'),
            'not_found_in_trash' => __('No Athtyp found in Trash', 'authentype-font-specimen'),
        ),
        'public' => true,
        'has_archive' => authentype_specimen_shop_slug(),
        'rewrite' => array(
            'slug' => authentype_specimen_shop_slug(),
            'with_front' => false,
        ),
        'show_in_rest' => true,
        'menu_position' => 5,
        'menu_icon' => $menu_icon,
        'supports' => array('title', 'thumbnail', 'editor'),
        'capabilities' => array(
            'edit_post' => 'manage_options',
            'read_post' => 'read',
            'delete_post' => 'manage_options',
            'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options',
            'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options',
            'delete_posts' => 'manage_options',
            'delete_private_posts' => 'manage_options',
            'delete_published_posts' => 'manage_options',
            'delete_others_posts' => 'manage_options',
            'edit_private_posts' => 'manage_options',
            'edit_published_posts' => 'manage_options',
            'create_posts' => 'manage_options',
        ),
        'map_meta_cap' => false,
    ));

    register_post_type('ath_free_download', array(
        'labels' => array(
            'name' => __('Free Downloads', 'authentype-font-specimen'),
            'singular_name' => __('Free Download', 'authentype-font-specimen'),
            'menu_name' => __('Free Downloads', 'authentype-font-specimen'),
            'add_new' => __('Add New', 'authentype-font-specimen'),
            'add_new_item' => __('Add New Free Download', 'authentype-font-specimen'),
            'edit_item' => __('Edit Free Download', 'authentype-font-specimen'),
            'new_item' => __('New Free Download', 'authentype-font-specimen'),
            'view_item' => __('View Free Download', 'authentype-font-specimen'),
            'search_items' => __('Search Free Downloads', 'authentype-font-specimen'),
            'not_found' => __('No free downloads found', 'authentype-font-specimen'),
            'not_found_in_trash' => __('No free downloads found in Trash', 'authentype-font-specimen'),
        ),
        'public' => true,
        'has_archive' => true,
        'rewrite' => array('slug' => 'free-downloads'),
        'show_in_rest' => true,
        'show_in_menu' => 'edit.php?post_type=ath_font',
        'supports' => array('title', 'thumbnail', 'editor', 'excerpt'),
        'capabilities' => array(
            'edit_post' => 'manage_options',
            'read_post' => 'read',
            'delete_post' => 'manage_options',
            'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options',
            'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options',
            'delete_posts' => 'manage_options',
            'delete_private_posts' => 'manage_options',
            'delete_published_posts' => 'manage_options',
            'delete_others_posts' => 'manage_options',
            'edit_private_posts' => 'manage_options',
            'edit_published_posts' => 'manage_options',
            'create_posts' => 'manage_options',
        ),
        'map_meta_cap' => false,
    ));

    register_post_type('ath_free_lead', array(
        'labels' => array(
            'name' => __('Free Download Leads', 'authentype-font-specimen'),
            'singular_name' => __('Free Download Lead', 'authentype-font-specimen'),
            'menu_name' => __('Free Leads', 'authentype-font-specimen'),
            'edit_item' => __('View Free Download Lead', 'authentype-font-specimen'),
            'search_items' => __('Search Free Download Leads', 'authentype-font-specimen'),
            'not_found' => __('No free download leads found', 'authentype-font-specimen'),
        ),
        'public' => false,
        'publicly_queryable' => false,
        'exclude_from_search' => true,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=ath_font',
        'show_in_rest' => false,
        'capability_type' => 'post',
        'capabilities' => array(
            'create_posts' => 'do_not_allow',
            'edit_post' => 'manage_options',
            'read_post' => 'manage_options',
            'delete_post' => 'manage_options',
            'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options',
            'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options',
            'delete_posts' => 'manage_options',
        ),
        'map_meta_cap' => false,
        'supports' => array('title'),
    ));
}
add_action('init', 'authentype_specimen_register_post_types');
?>
