<?php if (!empty($grouped_posts)) : ?>
    <?php foreach ($grouped_posts as $subtax_term => $term_posts) : ?>
<h1 class="l-provision-documents__title"><?php echo esc_html($subtax_term); ?></h1>
<div class="l-resources">
    <?php foreach ($term_posts as $subtax2_term => $posts) : ?>
  <div class="c-resource">
    <h3 class="resource__title"><?php echo esc_html($subtax2_term); ?> Resources</h3>
    <div class="resource__document">
        <?php foreach ($posts as $post) : ?>

      <div id="postID<?php echo $post->ID ?>" class="c-document">
        <h4 class="c-document__title"><?php echo $post->post_title; ?></h4>
        <div class="c-document__body">
          <p><?php the_field('short_description', $post->ID); ?></p>
        </div>
        <div class="c-document__footer">
            <?php if (get_the_content()) : ?>
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

        <?php endforeach; ?>
    </div>
  </div>
    <?php endforeach; ?>
  </div>
    <?php endforeach; ?>
<?php endif; ?>

