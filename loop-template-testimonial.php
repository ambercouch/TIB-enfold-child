<?php
$testimonial_title = get_field('testimonial_title');
$testimonial_title = ($testimonial_title !== '') ? $testimonial_title : get_the_title();

$testimonial_intro = get_field('testimonial_intro');
$testimonial_body  = get_field('testimonial_body'); // WYSIWYG returns HTML
$testimonial_cite  = get_field('testimonial_citation');

$testimonial_image = get_field('testimonial_image'); // array|id|url depending on return_format
$testimonial_id    = get_the_ID();

// Normalise image to an <img> tag if present
$testimonial_img_html = '';
if ( ! empty($testimonial_image) ) {
    // If return_format is "id"
    if ( is_numeric($testimonial_image) ) {
        $testimonial_img_html = wp_get_attachment_image((int) $testimonial_image, 'medium', false, array(
            'class' => 'c-ac-testimonial__img',
            'loading' => 'lazy',
        ));
    }
    // If return_format is "array"
    elseif ( is_array($testimonial_image) && ! empty($testimonial_image['ID']) ) {
        $testimonial_img_html = wp_get_attachment_image((int) $testimonial_image['ID'], 'medium', false, array(
            'class' => 'c-ac-testimonial__img',
            'loading' => 'lazy',
        ));
    }
    // If return_format is "url"
    elseif ( is_string($testimonial_image) ) {
        $testimonial_img_html = sprintf(
            '<img class="c-ac-testimonial__img" src="%s" alt="%s" loading="lazy" />',
            esc_url($testimonial_image),
            esc_attr($testimonial_title)
        );
    }
}

// Determine if we should collapse body (only when BOTH intro + body exist)
$has_intro = ! empty(trim((string) $testimonial_intro));
$has_body  = ! empty(trim((string) wp_strip_all_tags((string) $testimonial_body))); // avoid empty <p></p> weirdness
$should_collapse_body = ($has_intro && $has_body);

// Unique ids for aria
$body_id   = 'testimonial-body-' . $testimonial_id;
$toggle_id = 'testimonial-toggle-' . $testimonial_id;
?>

<li class="l-ac-testimonial-list__item ac-theme" style="list-style:none">
  <article id="post-<?php the_ID(); ?>" <?php post_class('c-ac-testimonial'); ?>>
    <div class="c-ac-testimonial__thumb">

        <?php if ( $testimonial_img_html ) : ?>
          <div class="c-ac-testimonial__image">
              <?php echo $testimonial_img_html; ?>
          </div>
        <?php endif; ?>

      <div class="c-ac-testimonial__content">

          <?php if ( ! empty($testimonial_title) ) : ?>
            <h3 class="c-ac-testimonial__title"><?php echo esc_html($testimonial_title); ?></h3>
          <?php endif; ?>

          <?php if ( $has_intro ) : ?>
            <div class="c-ac-testimonial__intro">
                <?php echo wpautop($testimonial_intro); ?>
            </div>
          <?php endif; ?>

          <?php if ( $has_body ) : ?>
            <div
                id="<?php echo esc_attr($body_id); ?>"
                class="c-ac-testimonial__body<?php echo $should_collapse_body ? ' is-collapsible is-collapsed' : ''; ?>"
                <?php if ( $should_collapse_body ) : ?>
                  aria-hidden="true"
                <?php endif; ?>
            >
              <div class="c-ac-testimonial__body-inner">
                  <?php echo wp_kses_post($testimonial_body); ?>
              </div>

            </div>

              <?php if ( ! empty(trim((string) $testimonial_cite)) ) : ?>
              <cite class="c-ac-testimonial__cite">
                  <?php echo esc_html($testimonial_cite); ?>
              </cite>
              <?php endif; ?>



          <?php endif; ?>
          <?php if ( $should_collapse_body ) : ?>
        <div class="c-ac-testimonial__footer">
            <?php if ( $should_collapse_body ) : ?>
              <button
                  id="<?php echo esc_attr($toggle_id); ?>"
                  class="c-ac-testimonial__toggle"
                  type="button"
                  data-target="<?php echo esc_attr($body_id); ?>"
                  aria-controls="<?php echo esc_attr($body_id); ?>"
                  aria-expanded="false"
              >
                Show more
              </button>
            <?php endif; ?>
        </div>
          <?php endif; ?>






      </div>
    </div>
  </article>
</li>
