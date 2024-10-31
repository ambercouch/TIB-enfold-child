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
        'name'                  => _x('Services', 'Post type general name', 'actib'),
        'singular_name'         => _x('Service', 'Post type singular name', 'actib'),
        'menu_name'             => _x('Services', 'Admin Menu text', 'actib'),
        'name_admin_bar'        => _x('Service', 'Add New on Toolbar', 'actib'),
        'add_new'               => __('Add New', 'actib'),
        'add_new_item'          => __('Add New Service', 'actib'),
        'new_item'              => __('New Service', 'actib'),
        'edit_item'             => __('Edit Service', 'actib'),
        'view_item'             => __('View Service', 'actib'),
        'all_items'             => __('All Services', 'actib'),
        'search_items'          => __('Search Services', 'actib'),
        'parent_item_colon'     => __('Parent Services:', 'actib'),
        'not_found'             => __('No services found.', 'actib'),
        'not_found_in_trash'    => __('No services found in Trash.', 'actib'),
        'featured_image'        => _x('Service Cover Image', 'Overrides the “Featured Image” phrase.', 'actib'),
        'set_featured_image'    => _x('Set cover image', 'Overrides the “Set featured image” phrase.', 'actib'),
        'remove_featured_image' => _x('Remove cover image', 'Overrides the “Remove featured image” phrase.', 'actib'),
        'use_featured_image'    => _x('Use as cover image', 'Overrides the “Use as featured image” phrase.', 'actib'),
        'archives'              => _x('Service archives', 'The post type archive label used in nav menus.', 'actib'),
        'insert_into_item'      => _x('Insert into service', 'Overrides the “Insert into post”/”Insert into page” phrase.', 'actib'),
        'uploaded_to_this_item' => _x('Uploaded to this service', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase.', 'actib'),
        'filter_items_list'     => _x('Filter services list', 'Screen reader text for the filter links.', 'actib'),
        'items_list_navigation' => _x('Services list navigation', 'Screen reader text for the pagination.', 'actib'),
        'items_list'            => _x('Services list', 'Screen reader text for the items list.', 'actib'),
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

// Register Custom Post Type Documents
function register_documents_cpt() {
    $labels = array(
        'name'                  => _x( 'Documents', 'Post Type General Name', 'actib' ),
        'singular_name'         => _x( 'Document', 'Post Type Singular Name', 'actib' ),
        'menu_name'             => __( 'Documents', 'actib' ),
        'name_admin_bar'        => __( 'Document', 'actib' ),
        'archives'              => __( 'Document Archives', 'actib' ),
        'attributes'            => __( 'Document Attributes', 'actib' ),
        'parent_item_colon'     => __( 'Parent Document:', 'actib' ),
        'all_items'             => __( 'All Documents', 'actib' ),
        'add_new_item'          => __( 'Add New Document', 'actib' ),
        'add_new'               => __( 'Add New', 'actib' ),
        'new_item'              => __( 'New Document', 'actib' ),
        'edit_item'             => __( 'Edit Document', 'actib' ),
        'update_item'           => __( 'Update Document', 'actib' ),
        'view_item'             => __( 'View Document', 'actib' ),
        'view_items'            => __( 'View Documents', 'actib' ),
        'search_items'          => __( 'Search Document', 'actib' ),
        'not_found'             => __( 'Not found', 'actib' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'actib' ),
        'featured_image'        => __( 'Featured Image', 'actib' ),
        'set_featured_image'    => __( 'Set featured image', 'actib' ),
        'remove_featured_image' => __( 'Remove featured image', 'actib' ),
        'use_featured_image'    => __( 'Use as featured image', 'actib' ),
        'insert_into_item'      => __( 'Insert into document', 'actib' ),
        'uploaded_to_this_item' => __( 'Uploaded to this document', 'actib' ),
        'items_list'            => __( 'Documents list', 'actib' ),
        'items_list_navigation' => __( 'Documents list navigation', 'actib' ),
        'filter_items_list'     => __( 'Filter documents list', 'actib' ),
    );
    $args = array(
        'label'                 => __( 'Document', 'actib' ),
        'description'           => __( 'Post Type for documents', 'actib' ),
        'labels'                => $labels,
        'menu_icon'             => 'dashicons-media-document',
        'supports'              => array( 'title', 'editor', 'thumbnail', 'revisions' ),
        'taxonomies'            => array( 'document_type', 'resource' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
    );
    register_post_type( 'documents', $args );
}
add_action( 'init', 'register_documents_cpt', 0 );

// Register Custom Taxonomy Document Type (Single Selection)
function register_document_type_taxonomy() {
    $labels = array(
        'name'              => _x( 'Document Types', 'taxonomy general name', 'actib' ),
        'singular_name'     => _x( 'Document Type', 'taxonomy singular name', 'actib' ),
        'search_items'      => __( 'Search Document Types', 'actib' ),
        'all_items'         => __( 'All Document Types', 'actib' ),
        'parent_item'       => __( 'Parent Document Type', 'actib' ),
        'parent_item_colon' => __( 'Parent Document Type:', 'actib' ),
        'edit_item'         => __( 'Edit Document Type', 'actib' ),
        'update_item'       => __( 'Update Document Type', 'actib' ),
        'add_new_item'      => __( 'Add New Document Type', 'actib' ),
        'new_item_name'     => __( 'New Document Type Name', 'actib' ),
        'menu_name'         => __( 'Document Type', 'actib' ),
    );
    $args = array(
        'labels'            => $labels,
        'hierarchical'      => true, // Set to true for category-like behavior
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => false,
        'single_value'      => true, // Custom parameter to enforce single category selection
    );
    register_taxonomy( 'document_type', array( 'documents' ), $args );
}
add_action( 'init', 'register_document_type_taxonomy', 0 );

// Register Custom Tag Taxonomy Resource (Multiple Selection)
function register_resource_taxonomy() {
    $labels = array(
        'name'              => _x( 'Resources', 'taxonomy general name', 'actib' ),
        'singular_name'     => _x( 'Resource', 'taxonomy singular name', 'actib' ),
        'search_items'      => __( 'Search Resources', 'actib' ),
        'all_items'         => __( 'All Resources', 'actib' ),
        'edit_item'         => __( 'Edit Resource', 'actib' ),
        'update_item'       => __( 'Update Resource', 'actib' ),
        'add_new_item'      => __( 'Add New Resource', 'actib' ),
        'new_item_name'     => __( 'New Resource Name', 'actib' ),
        'menu_name'         => __( 'Resources', 'actib' ),
    );
    $args = array(
        'labels'            => $labels,
        'hierarchical'      => false, // Set to false for tag-like behavior
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => true,
    );
    register_taxonomy( 'resource', array( 'documents' ), $args );
}
add_action( 'init', 'register_resource_taxonomy', 0 );

// Register Custom Taxonomy Audience
function register_audience_taxonomy() {
    $labels = array(
        'name'                       => _x( 'Audiences', 'taxonomy general name', 'textdomain' ),
        'singular_name'              => _x( 'Audience', 'taxonomy singular name', 'textdomain' ),
        'search_items'               => __( 'Search Audiences', 'textdomain' ),
        'all_items'                  => __( 'All Audiences', 'textdomain' ),
        'parent_item'                => __( 'Parent Audience', 'textdomain' ),
        'parent_item_colon'          => __( 'Parent Audience:', 'textdomain' ),
        'edit_item'                  => __( 'Edit Audience', 'textdomain' ),
        'update_item'                => __( 'Update Audience', 'textdomain' ),
        'add_new_item'               => __( 'Add New Audience', 'textdomain' ),
        'new_item_name'              => __( 'New Audience Name', 'textdomain' ),
        'menu_name'                  => __( 'Audience', 'textdomain' ),
    );
    $args = array(
        'labels'                     => $labels,
        'hierarchical'               => false, // Set to false for tag-like behavior
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => true,
    );
    register_taxonomy( 'audience', array( 'documents' ), $args );
}
add_action( 'init', 'register_audience_taxonomy', 0 );

if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_document_fields',
        'title' => 'Document Fields',
        'fields' => array(
            array(
                'key' => 'field_document_upload',
                'label' => 'Document Upload',
                'name' => 'document_upload',
                'type' => 'file',
                'instructions' => 'Upload a document if applicable.',
                'required' => 0,
                'return_format' => 'url', // You can use 'array' to get more details.
                'mime_types' => 'pdf,doc,docx', // Restrict to document types
            ),
            array(
                'key' => 'field_document_link',
                'label' => 'Document Link',
                'name' => 'document_link',
                'type' => 'url',
                'instructions' => 'Provide a URL to an online document if applicable (e.g. Google Docs).',
                'required' => 0,
            ),
            array(
                'key' => 'field_short_description',
                'label' => 'Short Description',
                'name' => 'short_description',
                'type' => 'textarea',
                'instructions' => 'Provide a brief description of the document.',
                'required' => 0,
                'maxlength' => 255, // Limit the description length
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'documents', // Custom post type slug
                ),
            ),
        ),
        'style' => 'seamless',
        'position' => 'acf_after_title',
        'active' => true,
        'description' => '',
    ));

endif;

// Register Custom Post Type Notices
function register_notices_cpt() {
    $labels = array(
        'name'                  => _x( 'Notices', 'Post Type General Name', 'actib' ),
        'singular_name'         => _x( 'Notice', 'Post Type Singular Name', 'actib' ),
        'menu_name'             => __( 'Notices', 'actib' ),
        'name_admin_bar'        => __( 'Notice', 'actib' ),
        'archives'              => __( 'Notice Archives', 'actib' ),
        'attributes'            => __( 'Notice Attributes', 'actib' ),
        'parent_item_colon'     => __( 'Parent notice:', 'actib' ),
        'all_items'             => __( 'All notices', 'actib' ),
        'add_new_item'          => __( 'Add New notice', 'actib' ),
        'add_new'               => __( 'Add New', 'actib' ),
        'new_item'              => __( 'New notice', 'actib' ),
        'edit_item'             => __( 'Edit notice', 'actib' ),
        'update_item'           => __( 'Update notice', 'actib' ),
        'view_item'             => __( 'View notice', 'actib' ),
        'view_items'            => __( 'View notices', 'actib' ),
        'search_items'          => __( 'Search notice', 'actib' ),
        'not_found'             => __( 'Not found', 'actib' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'actib' ),
        'featured_image'        => __( 'Featured Image', 'actib' ),
        'set_featured_image'    => __( 'Set featured image', 'actib' ),
        'remove_featured_image' => __( 'Remove featured image', 'actib' ),
        'use_featured_image'    => __( 'Use as featured image', 'actib' ),
        'insert_into_item'      => __( 'Insert into notice', 'actib' ),
        'uploaded_to_this_item' => __( 'Uploaded to this notice', 'actib' ),
        'items_list'            => __( 'notices list', 'actib' ),
        'items_list_navigation' => __( 'notices list navigation', 'actib' ),
        'filter_items_list'     => __( 'Filter notices list', 'actib' ),
    );
    $args = array(
        'label'                 => __( 'Notice', 'actib' ),
        'description'           => __( 'Post Type for notices', 'actib' ),
        'labels'                => $labels,
        'menu_icon'             => 'dashicons-format-status',
        'supports'              => array( 'title', 'editor', 'thumbnail', 'revisions' ),
        'taxonomies'            => array( 'notice_type', 'notice_audience' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
    );
    register_post_type( 'notices', $args );
}
add_action( 'init', 'register_notices_cpt', 0 );

// Register Custom Taxonomy notice Type (Single Selection)
function register_notice_type_taxonomy() {
    $labels = array(
        'name'              => _x( 'Notice Types', 'taxonomy general name', 'actib' ),
        'singular_name'     => _x( 'Hotice Type', 'taxonomy singular name', 'actib' ),
        'search_items'      => __( 'Search notice Types', 'actib' ),
        'all_items'         => __( 'All Notice Types', 'actib' ),
        'parent_item'       => __( 'Parent Notice Type', 'actib' ),
        'parent_item_colon' => __( 'Parent Notice Type:', 'actib' ),
        'edit_item'         => __( 'Edit Notice Type', 'actib' ),
        'update_item'       => __( 'Update Notice Type', 'actib' ),
        'add_new_item'      => __( 'Add New Notice Type', 'actib' ),
        'new_item_name'     => __( 'New Notice Type Name', 'actib' ),
        'menu_name'         => __( 'Notice Type', 'actib' ),
    );
    $args = array(
        'labels'            => $labels,
        'hierarchical'      => true, // Set to true for category-like behavior
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => false,
        'single_value'      => true, // Custom parameter to enforce single category selection
    );
    register_taxonomy( 'notice_type', array( 'notices' ), $args );
}
add_action( 'init', 'register_notice_type_taxonomy', 0 );

// Register Custom Taxonomy notice_audience
function register_notice_audience_taxonomy() {
    $labels = array(
        'name'                       => _x( 'Notice Audiences', 'taxonomy general name', 'textdomain' ),
        'singular_name'              => _x( 'Notice Audiences', 'taxonomy singular name', 'textdomain' ),
        'search_items'               => __( 'Search Notice Audiences', 'textdomain' ),
        'all_items'                  => __( 'All Notice Audiences', 'textdomain' ),
        'parent_item'                => __( 'Parent Notice Audiences', 'textdomain' ),
        'parent_item_colon'          => __( 'Parent Notice Audiences:', 'textdomain' ),
        'edit_item'                  => __( 'Edit Notice Audiences', 'textdomain' ),
        'update_item'                => __( 'Update Notice Audiences', 'textdomain' ),
        'add_new_item'               => __( 'Add New Notice Audiences', 'textdomain' ),
        'new_item_name'              => __( 'New Notice Audiences Name', 'textdomain' ),
        'menu_name'                  => __( 'Notice Audiences', 'textdomain' ),
    );
    $args = array(
        'labels'                     => $labels,
        'hierarchical'               => false, // Set to false for tag-like behavior
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => true,
    );
    register_taxonomy( 'notice_audience', array( 'notices' ), $args );
}
add_action( 'init', 'register_notice_audience_taxonomy', 0 );




