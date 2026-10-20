<?php
/**
 * Created by PhpStorm.
 * User: Richard
 * Date: 07/10/2018
 * Time: 13:46
 */


$document_id = get_the_ID();
?>


  <div class="resource__document">


        <div id="postID<?php echo $document_id ?>" class="c-document">
          <h4 class="c-document__title"><?php the_title(); ?></h4>
          <div class="c-document__body">
            <p><?php the_field('short_description', $document_id); ?></p>
          </div>
          <div class="c-document__footer">

              <?php

              $content = apply_filters('the_content', get_post_field('post_content', $document_id, 'raw'));
              if (trim(wp_strip_all_tags($content))) : ?>
                <a href="<?php echo get_permalink($document_id) ?>">More info</a>
              <?php endif; ?>
              <?php if (get_field('document_link', $document_id)) : ?>
                <a href="<?php the_field('document_link', $document_id); ?>">View Document</a>
              <?php endif ?>
              <?php if (get_field('document_upload', $document_id)) : ?>
                <a href="<?php the_field('document_upload', $document_id); ?>">Download File</a>
              <?php endif ?>
          </div>
        </div>

  
  </div>
