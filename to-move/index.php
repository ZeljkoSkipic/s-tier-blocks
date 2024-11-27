<?php

/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package s-tier
 */

get_header();
?>

<main id="primary" class="site-main space_1">

	<?php Filter::sidebar(); ?>

	<div class="archive_posts posts_grid container">
		<?php
		if (have_posts()) : ?>
			<?php while (have_posts()) :
				the_post();
				get_template_part('template-parts/content', get_post_type());
			endwhile; ?>

		<?php else : ?>
			<p class="filter-no-items"><?php esc_html_e('There are no results for the set filters.', 's-tier') ?></p>
	</div>
<?php endif; ?>

<?php Filter::pagination(); ?>
<div class="loader-wrapper">
	<div class="loader"></div>
</div>

</main><!-- #main -->

<?php
get_footer();
