
    <div class="c-document">
      <h4 class="c-document__title"><?php the_title()?></h4>
      <div class="c-document__body">
        <p><?php the_field('short_description'); ?></p>
      </div>
      <div class="c-document__footer">
        <?php if (get_the_content()) : ?>
        <a href="<?php the_permalink(); ?>">More info</a>
        <?php endif; ?>
          <?php if (get_field('document_link')) : ?>
        <a href="<?php the_field('document_link'); ?>">View Document</a>
        <?php endif ?>
          <?php if (get_field('document_upload')) : ?>
            <a href="<?php the_field('document_upload'); ?>">Download File</a>
          <?php endif ?>
      </div>
    </div>


