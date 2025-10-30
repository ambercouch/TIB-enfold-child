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

// Register Custom Tag Taxonomy Provision (Multiple Selection)
function register_provision_taxonomy() {
    $labels = array(
        'name'              => _x( 'Provisions', 'taxonomy general name', 'actib' ),
        'singular_name'     => _x( 'Provision', 'taxonomy singular name', 'actib' ),
        'search_items'      => __( 'Search Provisions', 'actib' ),
        'all_items'         => __( 'All Provisions', 'actib' ),
        'edit_item'         => __( 'Edit Provision', 'actib' ),
        'update_item'       => __( 'Update Provision', 'actib' ),
        'add_new_item'      => __( 'Add New Provision', 'actib' ),
        'new_item_name'     => __( 'New Provision Name', 'actib' ),
        'menu_name'         => __( 'Provisions', 'actib' ),
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
    register_taxonomy( 'provision', array( 'documents' ), $args );
}
add_action( 'init', 'register_provision_taxonomy', 0 );

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

add_action( 'acf/include_fields', function() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( array(
        'key' => 'group_therapist_details',
        'title' => 'Therapist Details',
        'fields' => array(
            array(
                'key' => 'field_name',
                'label' => 'Name',
                'name' => 'name',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => 'Add the name of the Therapist or specialist. This will fall back to the title if left blank',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'maxlength' => '',
                'allow_in_bindings' => 0,
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
            ),
            array(
                'key' => 'field_short_description',
                'label' => 'Short description',
                'name' => 'short_description',
                'aria-label' => '',
                'type' => 'textarea',
                'instructions' => 'Add a short description to show in the list staff members',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'maxlength' => 500,
                'allow_in_bindings' => 0,
                'rows' => 3,
                'placeholder' => '',
                'new_lines' => 'br',
            ),
            array(
                'key' => 'field_years_experience',
                'label' => 'Years Experiance',
                'name' => 'years_experiance',
                'aria-label' => '',
                'type' => 'number',
                'instructions' => 'Optionally add the years that this therapist has been practicing to help users make informed choices.',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'min' => '',
                'max' => '',
                'allow_in_bindings' => 0,
                'placeholder' => '',
                'step' => '',
                'prepend' => '',
                'append' => '',
            ),
            array(
                'key' => 'field_availability',
                'label' => 'Availability',
                'name' => 'availability',
                'aria-label' => '',
                'type' => 'checkbox',
                'instructions' => 'Check the days of the week that this therapist is available',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'choices' => array(
                    'Mondays' => 'Mondays',
                    'Tuesdays' => 'Tuesdays',
                    'Wednesdays' => 'Wednesdays',
                    'Thursdays' => 'Thursdays',
                    'Fridays' => 'Fridays',
                    'Saturdays' => 'Saturdays',
                    'Sundays' => 'Sundays'
                ),
                'default_value' => array(
                ),
                'return_format' => 'array',
                'allow_custom' => 0,
                'allow_in_bindings' => 0,
                'layout' => 'horizontal',
                'toggle' => 0,
                'save_custom' => 0,
                'custom_choice_button_text' => 'Add new choice',
            ),

            array(
                'key' => 'field_availability_time',
                'label' => 'Availability time',
                'name' => 'availability_time',
                'aria-label' => '',
                'type' => 'checkbox',
                'instructions' => 'Check the time of days you are available',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'choices' => array(
                    'Mornings' => 'Mornings',
                    'Afternoons' => 'Afternoons',
                    'Evenings' => 'Evenings'
                ),
                'default_value' => array(
                ),
                'return_format' => 'array',
                'allow_custom' => 0,
                'allow_in_bindings' => 0,
                'layout' => 'horizontal',
                'toggle' => 0,
                'save_custom' => 0,
                'custom_choice_button_text' => 'Add new choice',
            ),
            array(
                'key' => 'field_service_fees',
                'label' => 'Service Fees',
                'name' => 'service_fees',
                'aria-label' => '',
                'type' => 'repeater',
                'instructions' => '',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'layout' => 'table',
                'pagination' => 0,
                'min' => 0,
                'max' => 0,
                'collapsed' => '',
                'button_label' => 'Add Row',
                'rows_per_page' => 20,
                'sub_fields' => array(
                    array(
                        'key' => 'field_service_name',
                        'label' => 'Service Name',
                        'name' => 'service_name',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => 'The name of a service that this therapist offers e.g. Inividuals, Couples, Relationships, Young people 14+ etc',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_service_fees',
                    ),
                    array(
                        'key' => 'field_service_fee',
                        'label' => 'Service Fee',
                        'name' => 'service_fee',
                        'aria-label' => '',
                        'type' => 'number',
                        'instructions' => 'Add the fee that is charged for this service',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'min' => '',
                        'max' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'step' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_service_fees',
                    ),
                    array(
                        'key' => 'field_service_note',
                        'label' => 'Service notes',
                        'name' => 'service_notes',
                        'aria-label' => '',
                        'type' => 'text',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'default_value' => '',
                        'maxlength' => '',
                        'allow_in_bindings' => 0,
                        'placeholder' => '',
                        'prepend' => '',
                        'append' => '',
                        'parent_repeater' => 'field_service_fees',
                    ),
                ),
            ),
            array(
                'key' => 'field_staff_link',
                'label' => 'Staff Member',
                'name' => 'staff_link',               // keep same name so templates don’t break
                'type' => 'select',                   // was: 'url'
                'instructions' => 'Choose the staff member from 10to8.',
                'required' => 0,
                'choices' => [],                      // leave empty; we’ll fill via filter
                'ui' => 1,                            // nice select UI
                'ajax' => 0,                          // we’ll cache; no need for AJAX
                'allow_null' => 1,
                'return_format' => 'value',           // save just the numeric ID
                'placeholder' => 'Select a staff member…',
            ),
            array(
                'key' => 'field_staff_link_label',
                'label' => 'Staff Link  Label',
                'name' => 'staff_link_label',
                'aria-label' => '',
                'type' => 'text',
                'instructions' => 'The button text for the staff link (defaults to Book a session with...)',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'allow_in_bindings' => 0,
                'placeholder' => 'Book Now',
            ),
            array(
                'key' => 'field_currently_unavailable',
                'label' => 'Currently Unavailable',
                'name' => 'currently_unavailable',
                'aria-label' => '',
                'type' => 'true_false',
                'instructions' => 'Select <b>Yes</b> if this Therapist is currently <b>not</b> able to take bookings eg Holiday, sabbatical etc',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'message' => 'Unavailable for bookings',
                'default_value' => 0,
                'allow_in_bindings' => 0,
                'ui_on_text' => '',
                'ui_off_text' => '',
                'ui' => 1,
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'therapist',
                ),
            ),
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'additional_service',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
        'hide_on_screen' => '',
        'active' => true,
        'description' => '',
        'show_in_rest' => 0,
    ) );
} );

add_filter('wpcf7_autop_or_not', '__return_false');


function enqueue_limit_primary_issues_script() {
    // Only enqueue on pages where the form appears
    wp_add_inline_script(
        'contact-form-7', // Load after CF7 core script
        <<<JS
        document.addEventListener('DOMContentLoaded', function () {
          const select = document.getElementById('primary_issues');
          if (select && select.multiple) {
            select.addEventListener('change', function () {
              const selectedOptions = Array.from(this.selectedOptions);
              if (selectedOptions.length > 5) {
                selectedOptions[selectedOptions.length - 1].selected = false;
                alert('Please select no more than 5 issues.');
              }
            });
          }
        });
        JS
    );
}
add_action('wp_enqueue_scripts', 'enqueue_limit_primary_issues_script');


/**
 * Talk in the Bay — 10to8 (Sign In Scheduling) helpers
 * - Normalises IDs/URLs for staff & services
 * - Finds the earliest slot across many services & all their locations
 * - ACF select for staff (stores numeric staff ID)
 * - Simple render/echo helpers for templates
 *
 * Assumptions:
 * - Auth uses "Token <API_KEY>" (swap to Bearer if your tenant requires)
 * - Slot endpoint accepts start_date / end_date
 * - Responses may be an array or {results:[...]}
 *
 * Optional constants (set in wp-config.php):
 *   define('TIB_10TO8_API_KEY', 'xxx');
 *   define('TIB_10TO8_BOOK_SLUG', 'kyvakywdvslaodmqbc'); // your org slug
 *   define('TIB_10TO8_DEBUG', true); // emit HTML comments in page source while testing
 */

/* ---------------------------
 * Normalisers & small helpers
 * --------------------------- */

/**
 * Extract a numeric ID from:
 *  - "251651"
 *  - "https://app.10to8.com/api/booking/v2/staff/251651/"
 *  - "https://app.10to8.com/book/SLUG/staff/251651/"
 *  - or any .../<digits>/ tail.
 */
function tib_10to8_extract_id($value): ?string
{
    if (!$value) return null;
    $s = trim((string)$value);
    if ($s === '') return null;
    if (ctype_digit($s)) return $s;
    if (preg_match('~/(\d+)/?$~', $s, $m)) return $m[1];
    return null;
}

/** Staff: ID/URL -> full API URI */
function tib_10to8_staff_to_uri($value): ?string
{
    $id = tib_10to8_extract_id($value);
    return $id ? "https://app.10to8.com/api/booking/v2/staff/{$id}/" : null;
}

/** Service: ID/URL -> full API URI */
function tib_10to8_service_to_uri($value): ?string
{
    $id = tib_10to8_extract_id($value);
    return $id ? "https://app.10to8.com/api/booking/v2/service/{$id}/" : null;
}

/** Build booking URL for a staff ID (needs TIB_10TO8_BOOK_SLUG) */
function tib_10to8_staff_booking_url(string $staff_id): ?string
{
    if (!defined('TIB_10TO8_BOOK_SLUG') || !TIB_10TO8_BOOK_SLUG) return null;
    return sprintf('https://app.10to8.com/book/%s/staff/%s/', TIB_10TO8_BOOK_SLUG, $staff_id);
}

/** Build full API URI for a staff ID */
function tib_10to8_staff_api_uri(string $staff_id): string
{
    return sprintf('https://app.10to8.com/api/booking/v2/staff/%s/', $staff_id);
}

/* ------------------------------------
 * Services list (override friendly)
 * ------------------------------------ */

/**
 * Default service URIs used for next-slot lookups.
 * You can override via the 'tib_10to8_service_uris' filter, or later move to wp_options.
 */
function tib_10to8_get_service_uris(): array
{
    $services = [
        'https://app.10to8.com/api/booking/v2/service/1886311/',
        // Individual Session (Cardiff)
        'https://app.10to8.com/api/booking/v2/service/1767089/',
        // Individual Session (Swansea)
        'https://app.10to8.com/api/booking/v2/service/1767110/',
        // Individual Session (Online)
        'https://app.10to8.com/api/booking/v2/service/1956384/',
        // Young Person Session (Swansea)
        'https://app.10to8.com/api/booking/v2/service/1050705/',
        // Young Person Session (Cardiff)
        'https://app.10to8.com/api/booking/v2/service/1889844/',
        // Young Person Session (Online)
        'https://app.10to8.com/api/booking/v2/service/1943583/',
        // Relationships/Couples Session (Swansea)
        'https://app.10to8.com/api/booking/v2/service/1889847/',
        // Relationships/Couples Session (Cardiff)
        'https://app.10to8.com/api/booking/v2/service/1889848/',
        // Relationships/Couples Session (Online)
    ];
    return apply_filters('tib_10to8_service_uris', $services);
}

/* ---------------------------------------------------------
 * Core: earliest slot across services & all their locations
 * --------------------------------------------------------- */

/**
 * Get the earliest slot for (one or many services, one staff) across all service locations.
 * @param string|array $service_ids IDs, API URLs, booking URLs, or mix
 * @param string       $staff_id    ID, API URL, or booking URL
 * @param int          $days_ahead  Window in days (default 60)
 * @return array|null|WP_Error      ['slot_id','start_iso','end_iso','start_local','date','time','raw'] or null if none
 */
if (!function_exists('tib_get_next_10to8_slot_multi')) {
    function tib_get_next_10to8_slot_multi($service_ids, $staff_id, $days_ahead = 60) {
        // Per-request API call cap (raise so one therapist can complete)
        static $tib10to8_api_calls = 0;
        $made_api_calls = 0; // track actual network calls
        $CALL_CAP = defined('TIB_10TO8_CALL_CAP') ? (int)TIB_10TO8_CALL_CAP : 64;

        $api_key = defined('TIB_10TO8_API_KEY') ? TIB_10TO8_API_KEY : '';
        if (!$api_key) return new WP_Error('tib_10to8_config', 'Missing API key');

        $debug = defined('TIB_10TO8_DEBUG') && TIB_10TO8_DEBUG;

        // --- NORMALISE STAFF to API URI ---
        $staff_uri = function_exists('tib_10to8_staff_to_uri') ? tib_10to8_staff_to_uri($staff_id) : null;
        if (!$staff_uri) {
            // minimal inline fallback
            $sid = preg_match('~/(\d+)/?$~', (string)$staff_id, $m) ? $m[1] : (ctype_digit((string)$staff_id) ? (string)$staff_id : null);
            if ($sid) $staff_uri = "https://app.10to8.com/api/booking/v2/staff/{$sid}/";
        }
        if (!$staff_uri) return new WP_Error('tib_10to8_bad_staff', 'Invalid staff value (expected ID or staff URL)');

        // --- NORMALISE SERVICES to API URIs (accept array / CSV / IDs / URLs) ---
        $services_in = is_array($service_ids)
            ? array_values(array_filter(array_map('trim', $service_ids)))
            : array_values(array_filter(array_map('trim', explode(',', (string)$service_ids))));

        $service_uris = [];
        foreach ($services_in as $sv) {
            if (stripos($sv, '/api/booking/v2/service/') !== false) {
                $service_uris[] = rtrim($sv, '/') . '/';
            } else {
                if (function_exists('tib_10to8_service_to_uri')) {
                    $uri = tib_10to8_service_to_uri($sv);
                } else {
                    $sid = preg_match('~/(\d+)/?$~', (string)$sv, $m) ? $m[1] : (ctype_digit((string)$sv) ? (string)$sv : null);
                    $uri = $sid ? "https://app.10to8.com/api/booking/v2/service/{$sid}/" : null;
                }
                if ($uri) $service_uris[] = $uri;
            }
        }
        $service_uris = array_values(array_unique($service_uris));
        sort($service_uris, SORT_STRING); // stable order for cache keys
        if (empty($service_uris)) return new WP_Error('tib_10to8_bad_service', 'No valid services');

        // --- CACHE KEY (built from normalised values) ---
        $cache_key = 'tib_10to8_nextslot_multi_' . md5(implode('|', $service_uris) . '|' . $staff_uri . '|' . (int)$days_ahead);
        $disable_cache = isset($_GET['tib10to8_flush']);

        if (!$disable_cache) {
            $cached = get_transient($cache_key);
            if ($cached !== false) {
                if ($debug) echo "\n<!-- 10to8[MULTI] cache HIT key=$cache_key -->\n";
                return $cached;
            } elseif ($debug) {
                echo "\n<!-- 10to8[MULTI] cache MISS key=$cache_key -->\n";
            }
        } elseif ($debug) {
            echo "\n<!-- 10to8[MULTI] cache BYPASSED -->\n";
        }

        // --- Request bits ---
        $headers = [
            'Authorization' => 'Token ' . $api_key,
            'Accept'        => 'application/json',
        ];
        $from = gmdate('Y-m-d');
        $to   = gmdate('Y-m-d', strtotime('+' . (int)$days_ahead . ' days'));
        $slot_base = 'https://app.10to8.com/api/booking/v2/slot/';

        $parse_rows = function ($raw) {
            $body = json_decode($raw, true);
            if (!is_array($body)) return [];
            if (isset($body['results']) && is_array($body['results'])) return $body['results'];
            if (array_values($body) === $body) return $body; // list
            return [];
        };

        // Fetch service->locations (10 min cache)
        $fetch_service_locations = function (string $service_uri) use ($headers, $debug) {
            $svc_key = 'tib_10to8_service_locations_' . md5($service_uri);
            $locs = get_transient($svc_key);
            if ($locs !== false) {
                if ($debug) echo "\n<!-- 10to8[MULTI] service locations (cached) for $service_uri: " . count((array)$locs) . " -->\n";
                return is_array($locs) ? $locs : [];
            }
            $resp = wp_remote_get($service_uri, ['headers'=>$headers, 'timeout'=>10, 'decompress'=>false]);
            if (is_wp_error($resp)) {
                if ($debug) echo "\n<!-- 10to8[MULTI] service fetch WP_Error: $service_uri | " . esc_html($resp->get_error_message()) . " -->\n";
                return [];
            }
            $code = wp_remote_retrieve_response_code($resp);
            $raw  = wp_remote_retrieve_body($resp);
            if ($code !== 200) {
                if ($debug) echo "\n<!-- 10to8[MULTI] service fetch HTTP $code: $service_uri | body: " . esc_html(substr($raw ?? '',0,200)) . " -->\n";
                return [];
            }
            $svc = json_decode($raw, true);
            $locs = isset($svc['locations']) && is_array($svc['locations']) ? array_values(array_filter($svc['locations'])) : [];
            set_transient($svc_key, $locs, 10 * MINUTE_IN_SECONDS);
            if ($debug) echo "\n<!-- 10to8[MULTI] service locations NEW for $service_uri: " . count($locs) . " -->\n";
            return $locs;
        };

        // --- Collect across (service × location) ---
        $all_slots = [];
        $svc_idx = 0;

        foreach ($service_uris as $service_uri) {
            $svc_idx++;
            $locations = $fetch_service_locations($service_uri);
            if (empty($locations)) {
                if ($debug) echo "\n<!-- 10to8[MULTI] no locations for service#$svc_idx: $service_uri -->\n";
                continue;
            }

            $loc_idx = 0;
            foreach ((array)$locations as $loc_uri) {
                $loc_idx++;

                // cap before calling
                if ($tib10to8_api_calls >= $CALL_CAP) {
                    if ($debug) echo "\n<!-- 10to8[MULTI] call cap reached ({$CALL_CAP}) — stopping -->\n";
                    break 2;
                }

                $url = add_query_arg([
                    'service'    => $service_uri,
                    'staff'      => $staff_uri,
                    'location'   => $loc_uri,
                    'start_date' => $from,
                    'end_date'   => $to,
                    'page_size'  => 50,
                ], $slot_base);

                $tib10to8_api_calls++;
                $made_api_calls++;

                $resp = wp_remote_get($url, [
                    'headers'    => $headers,
                    'timeout'    => 5,
                    'decompress' => false,
                ]);
                if (is_wp_error($resp)) {
                    if ($debug) echo "\n<!-- 10to8[MULTI] svc#$svc_idx loc#$loc_idx: WP_Error | URL: $url | msg: " . esc_html($resp->get_error_message()) . " -->\n";
                    continue;
                }
                $code = wp_remote_retrieve_response_code($resp);
                $raw  = wp_remote_retrieve_body($resp);

                if ($code !== 200) {
                    if ($debug) echo "\n<!-- 10to8[MULTI] svc#$svc_idx loc#$loc_idx: HTTP $code | URL: $url | body: " . esc_html(substr($raw ?? '',0,200)) . " -->\n";
                    continue;
                }

                $rows = $parse_rows($raw);
                $count = is_array($rows) ? count($rows) : 0;
                if ($debug) echo "\n<!-- 10to8[MULTI] svc#$svc_idx loc#$loc_idx: 200 OK | results: $count | URL: $url -->\n";

                if ($count > 0) {
                    foreach ($rows as &$r) {
                        if (!isset($r['_tib_location'])) $r['_tib_location'] = $loc_uri;
                        if (!isset($r['_tib_service']))  $r['_tib_service']  = $service_uri;
                    }
                    unset($r);
                    $all_slots = array_merge($all_slots, $rows);
                }
            }
        }

        if (empty($all_slots)) {
            // Only cache "no availability" if we actually queried the API
            if (!$disable_cache && $made_api_calls > 0) {
                set_transient($cache_key, null, 10 * MINUTE_IN_SECONDS);
            }
            return null;
        }

        // --- Detect time keys (tenant uses start_datetime / end_datetime) ---
        $first = $all_slots[0];
        $detect_key = function(array $row, array $cands) {
            $lower = array_change_key_case($row, CASE_LOWER);
            foreach ($cands as $c) {
                $c2 = strtolower($c);
                if (array_key_exists($c2, $lower)) {
                    foreach ($row as $k => $v) if (strtolower($k) === $c2) return $k;
                }
            }
            return null;
        };
        $start_key = $detect_key($first, ['start_datetime','start','start_dt','start_at','datetime','begin']);
        $end_key   = $detect_key($first,   ['end_datetime','end','end_dt','end_at','datetime_end','finish']);
        if (!$start_key) {
            if ($debug) echo "\n<!-- 10to8[MULTI] could not detect start key; sample: " . esc_html(substr(json_encode($first),0,400)) . " -->\n";
            if (!$disable_cache && $made_api_calls > 0) set_transient($cache_key, null, 10 * MINUTE_IN_SECONDS);
            return null;
        }

        // --- Earliest across all services/locations ---
        usort($all_slots, function($a,$b) use($start_key){
            $as = isset($a[$start_key]) ? strtotime($a[$start_key]) : PHP_INT_MAX;
            $bs = isset($b[$start_key]) ? strtotime($b[$start_key]) : PHP_INT_MAX;
            return $as <=> $bs;
        });

        $next = $all_slots[0];
        $start_iso = $next[$start_key];
        $end_iso   = ($end_key && isset($next[$end_key])) ? $next[$end_key] : null;

        // Display timezone (use site tz if set; otherwise London)
        $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('Europe/London');
        try { $when = (new DateTimeImmutable($start_iso))->setTimezone($tz); }
        catch (Exception $e) { $when = (new DateTimeImmutable($start_iso.'Z'))->setTimezone($tz); }

        $out = [
            'slot_id'     => $next['id'] ?? null,
            'start_iso'   => $start_iso,
            'end_iso'     => $end_iso,
            'start_local' => $when->format('Y-m-d H:i'),
            'date'        => wp_date('D j M Y', $when->getTimestamp(), $tz),
            'time'        => wp_date('H:i',        $when->getTimestamp(), $tz),
            'raw'         => $next,
        ];

        if ($debug) {
            $chosen_svc = $next['_tib_service']  ?? 'unknown-service';
            $chosen_loc = $next['_tib_location'] ?? 'unknown-location';
            echo "\n<!-- 10to8[MULTI] chosen: service=$chosen_svc | location=$chosen_loc | start_iso={$out['start_iso']} | local={$out['date']} {$out['time']} -->\n";
        }

        if (!$disable_cache) set_transient($cache_key, $out, 10 * MINUTE_IN_SECONDS);
        return $out;
    }
}

/**
 * Get cached next-slot only (no network). Returns array|null.
 * Must compute the SAME cache key as the online version.
 */
function tib_get_next_10to8_slot_multi_cached($service_ids, $staff_id, $days_ahead = 60) {
    // Normalise staff
    $staff_uri = function_exists('tib_10to8_staff_to_uri') ? tib_10to8_staff_to_uri($staff_id) : null;
    if (!$staff_uri) {
        $sid = preg_match('~/(\d+)/?$~', (string)$staff_id, $m) ? $m[1] : (ctype_digit((string)$staff_id) ? (string)$staff_id : null);
        if ($sid) $staff_uri = "https://app.10to8.com/api/booking/v2/staff/{$sid}/";
    }
    if (!$staff_uri) return null;

    // Normalise & sort services
    $services_in = is_array($service_ids)
        ? array_values(array_filter(array_map('trim', $service_ids)))
        : array_values(array_filter(array_map('trim', explode(',', (string)$service_ids))));
    $service_uris = [];
    foreach ($services_in as $sv) {
        if (stripos($sv, '/api/booking/v2/service/') !== false) $service_uris[] = rtrim($sv, '/') . '/';
        else {
            $uri = function_exists('tib_10to8_service_to_uri') ? tib_10to8_service_to_uri($sv) : null;
            if (!$uri) {
                $sid = preg_match('~/(\d+)/?$~', (string)$sv, $m) ? $m[1] : (ctype_digit((string)$sv) ? (string)$sv : null);
                $uri = $sid ? "https://app.10to8.com/api/booking/v2/service/{$sid}/" : null;
            }
            if ($uri) $service_uris[] = $uri;
        }
    }
    $service_uris = array_values(array_unique($service_uris));
    if (empty($service_uris)) return null;
    sort($service_uris, SORT_STRING);

    $cache_key = 'tib_10to8_nextslot_multi_' . md5(implode('|', $service_uris) . '|' . $staff_uri . '|' . (int)$days_ahead);
    $cached = get_transient($cache_key);
    return ($cached !== false) ? $cached : null;
}

/**
 * Render cached; if missing, optionally do a tiny online fetch to fill it.
 */
function tib_render_next_slot_multi_cached($service_ids, $staff_id, $days_ahead = 60, $empty_text = 'Check availability', $soft_fetch = false, $soft_cap = 8) {
    $slot = tib_get_next_10to8_slot_multi_cached($service_ids, $staff_id, $days_ahead);

    if (!$slot && $soft_fetch && defined('TIB_10TO8_API_KEY') && TIB_10TO8_API_KEY) {
        // Perform a small online fetch to fill just this cache (short window & tight cap)
        if (!defined('TIB_10TO8_CALL_CAP')) {
            define('TIB_10TO8_CALL_CAP', (int)$soft_cap);
        }
        tib_get_next_10to8_slot_multi($service_ids, $staff_id, min(28, (int)$days_ahead));
        $slot = tib_get_next_10to8_slot_multi_cached($service_ids, $staff_id, $days_ahead);
    }

    if (!$slot || is_wp_error($slot)) {
        return '<span class="tib-slot tib-slot--none">' . esc_html($empty_text) . '</span>';
    }
    return sprintf(
        '<span class="c-next-appointment__date-time"><time datetime="%s">%s at %s</time></span>',
        esc_attr($slot['start_iso']),
        esc_html($slot['date']),
        esc_html($slot['time'])
    );
}