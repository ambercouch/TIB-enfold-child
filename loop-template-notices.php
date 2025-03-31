<article id="post-<?php the_ID(); ?>" <?php post_class('l-post-list__item--staff-notice'); ?>>
  <div class="c-staff-notice">
      <?php if ( '' !== get_the_post_thumbnail() ) : ?>
        <div class="post-thumbnail c-staff-notice__feature-image">
          <a href="<?php the_permalink(); ?>" class="c-staff-notice__feature-image-link" >
              <?php the_post_thumbnail('post-thumbnail'); ?>
          </a>
        </div><!-- .post-thumbnail -->
      <?php endif; ?>
    <header  class="entry-header c-staff-notice__header">
      <h3 class="entry-title c-staff-notice__heading">
        <a href="<?php esc_url( get_permalink() ) ?>" class="c-staff-notice__link" rel="bookmark">
          <span class="c-staff-notice__link-title"><?php the_title() ?></span>
        </a>
      </h3>
    </header>
      <?php if (has_excerpt()) : ?>
        <div class="c-accl-post-thumb__excerpt"  >
            <?php the_excerpt() ?>
        </div>
      <?php else : ?>
        <div class="c-accl-post-thumb__content"  >
            <?php the_content() ?>
        </div>
      <?php endif; ?>
  </div>
</article>
