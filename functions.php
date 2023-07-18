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
