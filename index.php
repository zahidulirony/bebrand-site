<?php
/**
 * Every ordinary page, post and list. The website itself is the page shown with the "BeBrand landing page" template.
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( is_404() || ! have_posts() ) :
	?>
	<article class="bb-post">
		<h1>Page not found</h1>
		<p>That page does not exist. <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Go to the home page</a>.</p>
	</article>
	<?php
elseif ( is_singular() ) :
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'bb-post' ); ?>>
			<h1><?php the_title(); ?></h1>
			<div class="bb-content"><?php the_content(); ?></div>
		</article>
		<?php
	endwhile;
else :
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class( 'bb-post bb-item' ); ?>>
			<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
			<p class="bb-date"><?php echo esc_html( get_the_date() ); ?></p>
			<div class="bb-content"><?php the_excerpt(); ?></div>
		</article>
		<?php
	endwhile;
	the_posts_pagination();
endif;

get_footer();
