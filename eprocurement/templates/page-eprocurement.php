<?php
/**
 * Custom page template for the eProcurement portal.
 *
 * Bypasses the active WordPress theme entirely — outputs a clean HTML
 * document with only eProcurement styles and scripts. This ensures the
 * site looks like a standalone eProcurement application, not a WordPress blog.
 *
 * @package Eprocurement
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Ensure we have the post content available.
if ( have_posts() ) {
    the_post();
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'eproc-standalone' ); ?>>
<?php wp_body_open(); ?>

<?php the_content(); ?>

<?php wp_footer(); ?>
</body>
</html>
