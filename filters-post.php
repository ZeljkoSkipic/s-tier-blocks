<?php

if (!isset($args['taxonomies']) && $args['taxonomies']) return;
$taxonomies = $args['taxonomies'];

$get_params = [];

$params = $_GET ? $_GET : "";

if ($params) {
    foreach ($params as $param => $terms) {
        $param = str_replace('filter-', "",  $param);
        $terms = wp_strip_all_tags($terms);
        if ($param !== 'search' && $param !== 'pageNumber') {

            if ($terms) {
                $get_params[$param] = explode('-', $terms);
            }
        }
    }
}

?>

<div id="filters" class="post-filters container space_0_3">
	<div class="post-filters__top">
		<div class="post-filters__taxonomies">

			<?php foreach ($taxonomies as $taxonomy_name => $terms) :

					$selected_terms_counter = isset($get_params[$taxonomy_name]) ? count($get_params[$taxonomy_name]) : 0;
			?>

				<div taxonomy-name="<?php echo $taxonomy_name;  ?>" class="post-filters__taxonomy">

					<?php if (isset($terms[array_key_first($terms)]['title']) && $terms[array_key_first($terms)]['title']) :
					?>

						<h2 data-taxonomy-name="<?php echo $taxonomy_name; ?>" class="search-tax-title">Filter <?php echo $terms[array_key_first($terms)]['title']; ?> <?php if($selected_terms_counter): ?> <span class="terms-counter"><?php echo $selected_terms_counter ?></span> <?php endif; ?></h2>

					<?php endif; ?>

					<?php if ($terms) : ?>

						<div class="post-filters__terms">

							<?php foreach ($terms as $term_ID => $term) :
							?>

								<div class="post-filters__term">
									<input <?php if ($get_params) foreach ($get_params as $taxonomy => $get_param) if ($taxonomy == $taxonomy_name && in_array($term_ID, $get_param)) echo 'checked=checked';  ?> <?php if (isset($children_ID) && $children_ID) echo "data-children=" . implode('-', $children_ID); ?> data-parent="true" data-taxonomy-name="<?php echo $taxonomy_name; ?>" value="<?php echo $term_ID; ?>" type="checkbox" id="<?php echo $term_ID; ?>">
									<label for="<?php echo $term_ID; ?>"><?php echo $term['name'] ?></label>
								</div>

							<?php endforeach; ?>

							<span class="clear-filters"><?php esc_html_e('Clear Filters', 's-tier'); ?></span>

						</div>

					<?php endif; ?>
				</div>

			<?php endforeach; ?>

		</div>

		<?php Filter::search() ?>

	</div>

</div>
