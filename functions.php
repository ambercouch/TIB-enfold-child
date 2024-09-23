<?php

/*
* Add your own functions here. You can also copy some of the theme functions into this file.
* Wordpress will use those functions instead of the original functions then.
*/


add_filter('avia_blog_post_query', 'avia_modify_post_grid_query_asc');

function avia_modify_post_grid_query_asc( $query ) {
    $query['orderby'] = 'title';
    $query['order'] = 'ASC';
    return $query;
}


 add_filter('the_content', 'ac_blog_back');
 function ac_blog_back($content){
    global $post;
    $is_enfold_builder_active = get_post_meta($post->ID, '_aviaLayoutBuilder_active', true);

     // Check if the post type is 'service', and if so, return the content unmodified
     if ('service' === get_post_type($post)) {
         return $content;
     }

     $output = "";
    if (is_single() && 'active' === $is_enfold_builder_active){
        $output .= "<div class='container'>";
        $output .= "<div class='content u-mb-0 u-pb-0'>";
        $output .= "<a class='c-btn--back' data-av_iconfont='entypo-fontello' data-av_icon='' href='/blog-page'> All Blogs</a>";
        $output .= "</div>";
        $output .= "</div>";
    }
    $output .= $content;

    return $output;
}

function register_services_post_type() {
    $labels = array(
        'name'                  => _x('Services', 'Post type general name', 'textdomain'),
        'singular_name'         => _x('Service', 'Post type singular name', 'textdomain'),
        'menu_name'             => _x('Services', 'Admin Menu text', 'textdomain'),
        'name_admin_bar'        => _x('Service', 'Add New on Toolbar', 'textdomain'),
        'add_new'               => __('Add New', 'textdomain'),
        'add_new_item'          => __('Add New Service', 'textdomain'),
        'new_item'              => __('New Service', 'textdomain'),
        'edit_item'             => __('Edit Service', 'textdomain'),
        'view_item'             => __('View Service', 'textdomain'),
        'all_items'             => __('All Services', 'textdomain'),
        'search_items'          => __('Search Services', 'textdomain'),
        'parent_item_colon'     => __('Parent Services:', 'textdomain'),
        'not_found'             => __('No services found.', 'textdomain'),
        'not_found_in_trash'    => __('No services found in Trash.', 'textdomain'),
        'featured_image'        => _x('Service Cover Image', 'Overrides the “Featured Image” phrase.', 'textdomain'),
        'set_featured_image'    => _x('Set cover image', 'Overrides the “Set featured image” phrase.', 'textdomain'),
        'remove_featured_image' => _x('Remove cover image', 'Overrides the “Remove featured image” phrase.', 'textdomain'),
        'use_featured_image'    => _x('Use as cover image', 'Overrides the “Use as featured image” phrase.', 'textdomain'),
        'archives'              => _x('Service archives', 'The post type archive label used in nav menus.', 'textdomain'),
        'insert_into_item'      => _x('Insert into service', 'Overrides the “Insert into post”/”Insert into page” phrase.', 'textdomain'),
        'uploaded_to_this_item' => _x('Uploaded to this service', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase.', 'textdomain'),
        'filter_items_list'     => _x('Filter services list', 'Screen reader text for the filter links.', 'textdomain'),
        'items_list_navigation' => _x('Services list navigation', 'Screen reader text for the pagination.', 'textdomain'),
        'items_list'            => _x('Services list', 'Screen reader text for the items list.', 'textdomain'),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite' => array('slug' => 'therapy'), // Change the slug here
        'capability_type'    => 'page',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => null,
        'supports'           => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions'),
    );

    register_post_type('service', $args);
}

add_action('init', 'register_services_post_type');
 
function enable_avia_builder_for_custom_post_type() {
    add_post_type_support('service', 'avia_builder');
    add_filter('avf_builder_boxes', 'add_builder_to_custom_post_type');
}

function add_builder_to_custom_post_type($metabox) {
    $metabox[] = array(
        'title' => __('Avia Layout Builder', 'avia_framework'),
        'id' => 'avia_builder',
        'page' => array('service', 'page'), // ensure your custom post type name is correct
        'context' => 'normal',
        'priority' => 'high',
        'expandable' => true
    );

    return $metabox;
}

add_action('after_setup_theme', 'enable_avia_builder_for_custom_post_type');


