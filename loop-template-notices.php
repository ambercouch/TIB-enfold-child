<?php
/**
 * Created by PhpStorm.
 * User: Richard
 * Date: 07/10/2018
 * Time: 13:46
 */


?>


  <div class="resource__document">


        <div id="postID<?php echo $post->ID ?>" class="c-document">
          <h4 class="c-document__title"><?php echo $post->post_title; ?></h4>
          <div class="c-document__body">
            <p><?php the_field('short_description', $post->ID); ?></p>
          </div>
          <div class="c-document__footer">

              <?php

              $post = get_post($post->ID);
              $content = $post ? apply_filters('the_content', $post->post_content) : '';
              if (trim(wp_strip_all_tags($content))) : ?>
                <a href="<?php echo get_permalink($post->ID) ?>">More info</a>
              <?php endif; ?>
              <?php if (get_field('document_link', $post->ID)) : ?>
                <a href="<?php the_field('document_link', $post->ID); ?>">View Document</a>
              <?php endif ?>
              <?php if (get_field('document_upload', $post->ID)) : ?>
                <a href="<?php the_field('document_upload', $post->ID); ?>">Download File</a>
              <?php endif ?>
          </div>
        </div>

  
  </div>
