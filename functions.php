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
