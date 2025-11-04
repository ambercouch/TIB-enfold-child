<?php
if (!defined('ABSPATH')) {
	die();
}

global $avia_config;
global $post;



// 1) ACF value can be an old URL or a numeric ID
$raw_staff = get_field('staff_link', get_the_ID());

// 2) Your service list (URIs)
$services = tib_10to8_get_service_uris();

// 3) Force a live fetch on singles so the transient gets set
//    Use 28–60 days as you prefer. While testing, add ?tib10to8_flush=1 to the URL.
$next_html = tib_render_next_slot_multi($services, $raw_staff, 28, 'No current availability');

// OPTIONAL: quick inline debug to confirm what we’re passing
if (defined('TIB_10TO8_DEBUG') && TIB_10TO8_DEBUG) {
    echo "\n<!-- single-therapist: staff_raw=" . esc_html(var_export($raw_staff, true)) .
        " | services=" . count((array)$services) .
        " | days=28 -->\n";
}

// 4) Booking URL
$staff_id    = tib_10to8_extract_id($raw_staff);
$booking_url = $staff_id ? tib_10to8_staff_booking_url($staff_id) : '';
  /*
	 * get_header is a basic wordpress function, used to retrieve the header.php file in your theme directory.
	 */
get_header();


if (get_post_meta(get_the_ID(), 'header', true) != 'no') echo avia_title(array('heading' => 'strong', 'title' => $post->post_title, 'link' => isset($t_link) ? $t_link : '', 'subtitle' => isset($t_sub) ? $t_sub : ''));

do_action('ava_after_main_title');

?>

<div class='container_wrap container_wrap_first main_color <?php avia_layout_class('main'); ?>'>

	<div class='ac-test container template-blog template-single-blog '>

		<main class='content units <?php avia_layout_class('content'); ?> <?php echo avia_blog_class_string(); ?>' <?php avia_markup_helper(array('context' => 'content', 'post_type' => 'post')); ?>>

			<?php
			/* Run the loop to output the posts.
                    * If you want to overload this in a child theme then include a file
                    * called loop-index.php and that will be used instead.
                    *
                    */

			if (!defined('ABSPATH')) {
				exit;
			}    // Exit if accessed directly


			global $avia_config, $post_loop_count;


			if (empty($post_loop_count)) {
				$post_loop_count = 1;
			}

			$blog_style = !empty($avia_config['blog_style']) ? $avia_config['blog_style'] : avia_get_option('blog_style', 'multi-big');
			if (is_single()) {
				$blog_style = avia_get_option('single_post_style', 'single-big');
			}

			$blog_global_style = avia_get_option('blog_global_style', ''); //alt: elegant-blog

			$initial_id = avia_get_the_ID();

			// check if we got posts to display:
			if (have_posts()) :

				while (have_posts()) : the_post();

					/*
     * get the current post id, the current post class and current post format
 	 */
					$url = '';
					$current_post = array();
					$current_post['post_loop_count'] = $post_loop_count;
					$current_post['the_id'] = get_the_ID();
					$current_post['parity'] = $post_loop_count % 2 ? 'odd' : 'even';
					$current_post['last'] = count($wp_query->posts) == $post_loop_count ? ' post-entry-last ' : '';
					$current_post['post_type'] = get_post_type($current_post['the_id']);
					$current_post['post_class'] = 'post-entry-' . $current_post['the_id'] . ' post-loop-' . $post_loop_count . ' post-parity-' . $current_post['parity'] . $current_post['last'] . ' ' . $blog_style;
					$current_post['post_class'] .= ($current_post['post_type'] == 'post') ? '' : ' post';
					$current_post['post_format'] = get_post_format() ? get_post_format() : 'standard';
					$current_post['post_layout'] = avia_layout_class('main', false);
					$blog_content = !empty($avia_config['blog_content']) ? $avia_config['blog_content'] : 'content';

					/*If post uses builder change content to exerpt on overview pages*/
					if (Avia_Builder()->get_alb_builder_status($current_post['the_id']) && !is_singular($current_post['the_id']) && $current_post['post_type'] == 'post') {
						$current_post['post_format'] = 'standard';
						$blog_content = 'excerpt_read_more';
					}

					/**
					 * Allows especially for ALB posts to change output to 'content'
					 * Supported since 4.5.5
					 *
					 * @since 4.5.5
					 * @return string
					 */
					$blog_content = apply_filters('avf_blog_content_in_loop', $blog_content, $current_post, $blog_style, $blog_global_style);


					/*
     * retrieve slider, title and content for this post,...
     */
					$size = strpos($blog_style, 'big') ? ((strpos($current_post['post_layout'], 'sidebar') !== false) ? 'entry_with_sidebar' : 'entry_without_sidebar') : 'square';

					if (!empty($avia_config['preview_mode']) && !empty($avia_config['image_size']) && $avia_config['preview_mode'] == 'custom') {
						$size = $avia_config['image_size'];
					}


					$current_post['title'] = get_the_title();

					/**
					 * Allow 3rd party to hook and return a plugin specific content.
					 * This returned content replaces Enfold's standard content building procedure.
					 *
					 * @since 4.5.7.2
					 * @param string
					 * @param string $context
					 * @return string
					 */
					$current_post['content'] = apply_filters('avf_the_content', '', 'loop_index');
					if ('' == $current_post['content']) {
						$current_post['content'] 	= $blog_content == 'content' ? get_the_content(__('Read more', 'avia_framework') . '<span class="more-link-arrow"></span>') : get_the_excerpt();
						$current_post['content'] 	= $blog_content == 'excerpt_read_more' ? $current_post['content'] . '<div class="read-more-link"><a href="' . get_permalink() . '" class="more-link">' . __('Read more', 'avia_framework') . '<span class="more-link-arrow"></span></a></div>' : $current_post['content'];
						$current_post['before_content'] = '';

						/*
		 * ...now apply a filter, based on the post type... (filter function is located in includes/helper-post-format.php)
		 */
						$current_post	= apply_filters('post-format-' . $current_post['post_format'], $current_post);
						$with_slider    = empty($current_post['slider']) ? '' : 'with-slider';

						/*
		 * ... last apply the default wordpress filters to the content
		 */
						$current_post['content'] = str_replace(']]>', ']]&gt;', apply_filters('the_content', $current_post['content']));
					}

					/*
	 * Now extract the variables so that $current_post['slider'] becomes $slider, $current_post['title'] becomes $title, etc
	 */
					extract($current_post);








					/*
	 * render the html:
	 */

					echo "<article class='" . implode(' ', get_post_class('post-entry post-entry-type-' . $post_format . ' ' . $post_class . ' ' . $with_slider)) . "' " . avia_markup_helper(array('context' => 'entry', 'echo' => false)) . '>';



					//default link for preview images
					$link = !empty($url) ? $url : get_permalink();

					//preview image description
					$desc = '';
					$thumb_post = get_post(get_post_thumbnail_id());
					if ($thumb_post instanceof WP_Post) {
						if ('' != trim($thumb_post->post_excerpt)) {
							//	return 'Caption' from media gallery
							$desc = $thumb_post->post_excerpt;
						} else if ('' != trim($thumb_post->post_title)) {
							//	return 'Title' from media gallery
							$desc = $thumb_post->post_title;
						} else if ('' != trim($thumb_post->post_content)) {
							//	return 'Description' from media gallery
							$desc = $thumb_post->post_content;
						}
					}

					$desc = trim($desc);
					if ('' == $desc) {
						$desc = trim(the_title_attribute('echo=0'));
					}

					/**
					 * Allows to change the title attribute text for the featured image.
					 * If '' is returned, then no title attribute is added.
					 *
					 * @since 4.6.4
					 * @param string $desc
					 * @param string $context				'loop_index'
					 * @param WP_Post $thumb_post
					 */
					$featured_img_title = apply_filters('avf_featured_image_title_attr', $desc, 'loop_index', $thumb_post);

					$featured_img_title = '' != trim($featured_img_title) ? ' title="' . esc_attr($featured_img_title) . '" ' : '';

					//on single page replace the link with a fullscreen image
					if (is_singular()) {
						$link = avia_image_by_id(get_post_thumbnail_id(), 'medium', 'url');
					}

					if (!in_array($blog_style, array('bloglist-simple', 'bloglist-compact', 'bloglist-excerpt'))) {
						//echo preview image
						if (strpos($blog_global_style, 'elegant-blog') === false) {
							if (strpos($blog_style, 'big') !== false) {
								if (isset($slider)) {
									$slider = '<a href="' . $link . '" ' . $featured_img_title . '>' . $slider . '</a>';
								}

								if (isset($slider)) {
									echo '<div class="big-preview ' . $blog_style . '" ' . avia_markup_helper(array('context' => 'image', 'echo' => false)) . '>' . $slider . '</div>';
								}
							}

							if (!empty($before_content)) {
								echo '<div class="big-preview ' . $blog_style . '">' . $before_content . '</div>';
							}
						}
					}



					echo "<div class='entry-content-wrapper clearfix {$post_format}-content c-single-therapist'>";
					if ($link != '') {

						echo '<div style="float:left; margin-right:20px;"><img src="' . $link . '" class="therapistmainimage"></div>';
					}

					echo '<header class="entry-header c-single-therapist__header">';



					$close_header 	= '</header>';


// Ensure this is inside The Loop, or use a specific post ID
            $post_id = get_the_ID(); // Get the current post ID

// Retrieve the custom meta values
            $full_title = get_the_title();
            $words = explode(' ', trim($full_title));
            $first_name = $words[0] ?? '';





            $staff_link_label = get_field('staff_link_label');
            $description = get_post_meta($post_id, 'description', true);
            $active_status = get_post_meta($post_id, 'active_post', true);


// Display the values
            if (!empty($booking_url))
            {

                $staff_link_label_output = ($staff_link_label == '' ) ? 'Book a session with '.$first_name : $staff_link_label;
                $staff_link_output = '<p class="u-ta-c"><a class="btn c-btn c-btn--staff-link" href="' . esc_url($booking_url ?: $raw_staff) . '" >'.esc_html($staff_link_label_output).'</a></p>';
            }else{
                $staff_link_output = '';
            }

            if (!empty($description))
            {
                //$description_output = '<p><strong>Description:</strong> ' . esc_html($description) . '</p>';
            }else{
                $description_output = '';
            }


            $service_fees = get_field('service_fees');
            $service_fees_output = '';



            if (!empty($service_fees)) {
                $service_fees_output = '<h3 class="u-mt">Services and Fees</h3>';
                $service_fees_output .= '<table class="u-mt c-service-fees">';
                $service_fees_output .= '<thead class="c-service-fees__header">';
                $service_fees_output .= '<tr class="c-service-fees__row">';
                $service_fees_output .= '<th class="c-service-fees__cell c-service-fees__cell--header">Service</th>';
                $service_fees_output .= '<th class="c-service-fees__cell c-service-fees__cell--header">Fee</th>';
                $service_fees_output .= '</tr>';
                $service_fees_output .= '</thead>';
                $service_fees_output .= '<tbody class="c-service-fees__body">';

                $notes = []; // Collect all notes

                foreach ($service_fees as $i => $service) {
                    $has_note = !empty($service['service_notes']);
                    $service_fees_output .= '<tr class="c-service-fees__row">';
                    $service_fees_output .= '<td class="c-service-fees__cell c-service-fees__cell--title">'
                        . esc_html($service['service_name'])
                        . ($has_note ? ' *' : '') // Add asterisk if there's a note
                        . '</td>';
                    $service_fees_output .= '<td class="c-service-fees__cell c-service-fees__cell--fee"><b>'
                        . ( !empty($service['service_fee']) ? '£' . esc_html($service['service_fee']) : 'TBC' )
                        . '</b></td>';
                    $service_fees_output .= '</tr>';

                    if ($has_note) {
                        $notes[] = $service['service_notes']; // Add note to the notes array
                    }
                }

                $service_fees_output .= '</tbody>';
                $service_fees_output .= '</table>';

                // Add notes below the table if any
                if (!empty($notes)) {
                    $service_fees_output .= '<ul class="c-service-fees__notes">';
                    foreach ($notes as $index => $note) {
                        $asterisks = str_repeat('*', $index + 1); // Generate the appropriate number of asterisks
                        $service_fees_output .= '<li class="c-service-fees__note">' . $asterisks . ' ' . esc_html($note) . '</li>';
                    }
                    $service_fees_output .= '</ul>';
                }
            }

            $content_next_appointment = '';

            if ($staff_id) {

              $content_next_appointment .= '<div class="c-next-appointment">';
              $content_next_appointment .= '<div class="c-next-appointment__container">';
              $content_next_appointment .=' <small>Next available appointment: </small> ' . $next_html;
              $content_next_appointment .= '</div>';
              $content_next_appointment .= '</div>';


                //echo $content_output;
            }


// Echo the output if needed
            $content_output  = '<div class="entry-content" ' . avia_markup_helper(array('context' => 'entry_content', 'echo' => false)) . '>';
            $content_output .= $content_next_appointment;
            $content_output .=		$content;
          $content_output .= $staff_link_output;
					$content_output .= '</div>';
					$content_output .= '<div class="content-extras">';
					$content_output .= '';


					$content_output .= $service_fees_output;

					$content_output .= $staff_link_output;

					$content_output .= '</div>';


					$taxonomies  = get_object_taxonomies(get_post_type($the_id));
					$cats = '';
					$excluded_taxonomies = array_merge(get_taxonomies(array('public' => false)), array('post_tag', 'post_format'));
					$excluded_taxonomies = apply_filters('avf_exclude_taxonomies', $excluded_taxonomies, get_post_type($the_id), $the_id);

					//var_dump($taxonomies);

					// if( ! empty( $taxonomies ) )
					// {
					//     foreach( $taxonomies as $taxonomy )
					//     {
					//         if( ! in_array( $taxonomy, $excluded_taxonomies ) )
					//         {
					$cats .= 'Available to assist: ';
					$cats .= strip_tags(get_the_term_list($the_id, 'service', '', ', ', '')) . ' ';
					//         }
					//     }
					// }



					//elegant blog
					//prev: if( $blog_global_style == 'elegant-blog' )
					if (strpos($blog_global_style, 'elegant-blog') !== false) {
						$cat_output = '';

						if (!empty($cats)) {
							$cat_output .= '<span class="blog-categories minor-meta">';
							$cat_output .= $cats;
							$cat_output .= '</span>';
							$cats = '';
						}

						if (in_array($blog_style, array('bloglist-compact', 'bloglist-excerpt'))) {
							echo $title;
						} else {

							// The wrapper div prevents the Safari reader from displaying the content twice  ¯\_(ツ)_/¯
							echo '<div class="av-heading-wrapper">';

							if (strpos($blog_global_style, 'modern-blog') === false) {
								echo $cat_output . $title;
							} else {
								echo $title . $cat_output;
							}

							echo '</div><br><br>';
						}

						echo $close_header;
						$close_header = '';



						$cats = '';
						$title = '';
						$content_output = '';
					}


					echo $title;



					if ($blog_style !== 'bloglist-compact') {
						echo "<span class='post-meta-infos'>";

						//echo "<time class='date-container minor-meta updated' >" . get_the_time( get_option( 'date_format' ) ) . '</time>';
						//echo "<span class='text-sep text-sep-date'>/</span>";

						// if ( get_comments_number() != '0' || comments_open() )
						// {

						//                    echo "<span class='comment-container minor-meta'>";
						//                    comments_popup_link(  '0 ' . __( 'Comments', 'avia_framework' ),
						//                                          '1 ' . __( 'Comment', 'avia_framework' ),
						//                                          '% ' . __( 'Comments', 'avia_framework' ),
						// 						  'comments-link',
						//                                          '' . __( 'Comments Disabled','avia_framework' )
						// 			);
						//                    echo '</span>';
						//                    echo "<span class='text-sep text-sep-comment'>/</span>";
						// }


						if (!empty($cats)) {
							echo '<span class="blog-categories minor-meta">';
							echo	$cats;
							echo '</span>';
						}


						// echo '<span class="blog-author minor-meta">' . __( 'by','avia_framework' ) . ' ';
						// echo	'<span class="entry-author-link" ' . avia_markup_helper( array( 'context' => 'author_name', 'echo' => false ) ) . '>';
						// echo		'<span class="author"><span class="fn">';
						// 				the_author_posts_link();
						// echo		'</span></span>';
						// echo	'</span>';
						// echo '</span>';

						// if( $blog_style == 'bloglist-simple' )
						// {
						// 	echo '<div class="read-more-link"><a href="' . get_permalink() . '" class="more-link">' . __( 'Read more', 'avia_framework' ) . '<span class="more-link-arrow"></span></a></div>';
						// }

						echo '</span>';
					} // display meta-infos on all layouts except bloglist-compact

					echo $close_header;


					// echo the post content
					//          if ( $blog_style == 'bloglist-excerpt')
					// {
					//              the_excerpt();
					//              echo '<div class="read-more-link"><a href="' . get_permalink() . '" class="more-link">' . __( 'Read more', 'avia_framework' ) . '<span class="more-link-arrow"></span></a></div>';
					//          }




            echo $content_output;



					echo '<footer class="entry-footer">';

					$avia_wp_link_pages_args = apply_filters('avf_wp_link_pages_args', array(
						'before'	=> '<nav class="pagination_split_post">' . __('Pages:', 'avia_framework'),
						'after'		=> '</nav>',
						'pagelink'	=> '<span>%</span>',
						'separator'	=> ' ',
					));

					wp_link_pages($avia_wp_link_pages_args);

					if (is_single() && !post_password_required()) {
						//tags on single post
						if (has_tag()) {
							echo '<span class="blog-tags minor-meta">';
							the_tags('<strong>' . __('Tags:', 'avia_framework') . '</strong><span> ');
							echo '</span></span>';
						}

						//share links on single post
						//avia_social_share_links();

					}

					do_action('ava_after_content', $the_id, 'post');

					echo '</footer>';

					echo "<div class='post_delimiter'></div>";
					echo '</div>';
					echo "<div class='post_author_timeline'></div>";
					echo av_blog_entry_markup_helper($current_post['the_id']);

					echo '</article>';

					$post_loop_count++;
				endwhile;
			else :

				$default_heading = 'h1';
				$args = array(
					'heading'		=> $default_heading,
					'extra_class'	=> ''
				);

				/**
				 * @since 4.5.5
				 * @return array
				 */
				$args = apply_filters('avf_customize_heading_settings', $args, 'loop_index::nothing_found', array());

				$heading = !empty($args['heading']) ? $args['heading'] : $default_heading;
				$css = !empty($args['extra_class']) ? $args['extra_class'] : '';
			?>

				<article class="entry">
					<header class="entry-content-header">
						<?php echo "<{$heading} class='post-title entry-title {$css}'>" . __('Nothing Found', 'avia_framework') . "</{$heading}>"; ?>
					</header>

					<p class="entry-content" <?php avia_markup_helper(array('context' => 'entry_content')); ?>><?php _e('Sorry, no posts matched your criteria', 'avia_framework'); ?></p>

					<footer class="entry-footer"></footer>
				</article>

			<?php

			endif;

			// if( empty( $avia_config['remove_pagination'] ) )
			// {
			// 	echo "<div class='{$blog_style}'>" . avia_pagination( '', 'nav' ) . '</div>';
			// }




			?>

			<!--end content-->
		</main>

		<?php
		//$avia_config['currently_viewing'] = "blog";
		//get the sidebar
		//get_sidebar();
		global $avia_config;

		##############################################################################
		# Display the sidebar
		##############################################################################

		$default_sidebar = true;
		$sidebar_pos = avia_layout_class('main', false);

		$sidebar_smartphone = avia_get_option('smartphones_sidebar') == 'smartphones_sidebar' ? 'smartphones_sidebar_active' : "";
		$sidebar = "";

		if (strpos($sidebar_pos, 'sidebar_left')  !== false) $sidebar = 'left';
		if (strpos($sidebar_pos, 'sidebar_right') !== false) $sidebar = 'right';

		//filter the sidebar position (eg woocommerce single product pages always want the same sidebar pos)
		$sidebar = apply_filters('avf_sidebar_position', $sidebar);

		$sidebar = 'right';
		//if the layout hasnt the sidebar keyword defined we dont need to display one
		if (empty($sidebar)) return;
		if (!empty($avia_config['overload_sidebar'])) $avia_config['currently_viewing'] = $avia_config['overload_sidebar'];

		//get text alignment for left sidebar
		$sidebar_text_alignment = '';

		if ($sidebar == 'left') {
			$sidebar_left_textalign = avia_get_option('sidebar_left_textalign');
			$sidebar_text_alignment = $sidebar_left_textalign !== '' ? 'sidebar_' . $sidebar_left_textalign : '';
		}

		echo "<aside class='sidebar sidebar_" . $sidebar . " " . $sidebar_text_alignment . " " . $sidebar_smartphone . " " . avia_layout_class('sidebar', false) . " units' " . avia_markup_helper(array('context' => 'sidebar', 'echo' => false)) . ">";
		echo "<div class='inner_sidebar extralight-border'>";

		echo do_shortcode('[HomeBookingIndividual therapist="' . $the_id . '"]');


		echo "</div>";
		echo "</aside>";





		?>


	</div>
	<!--end container-->

</div><!-- close default .container_wrap element -->


<?php
get_footer();
