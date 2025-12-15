<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	$theme_id = '';
	if ( function_exists( 'wp_get_theme' ) ) {
		$theme = wp_get_theme();
		$theme_id = strtolower( $theme->get_stylesheet() . ' ' . $theme->get_template() );
	}
	$is_neve = ( false !== strpos( $theme_id, 'neve' ) );
	?>
	<main id="primary" class="site-main">
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<?php if ( $is_neve ) : ?>
				<div class="nv-content-wrap entry-content">
					<div class="container">
						<?php the_content(); ?>
					</div>
				</div>
			<?php else : ?>
				<div class="entry-content">
					<div class="wrap">
						<?php the_content(); ?>
					</div>
				</div>
			<?php endif; ?>
		</article>
	</main>
	<?php
endwhile;

get_footer();
