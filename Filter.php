<?php
class Filter
{
	private static $filterTerms = [];
	private static $getParams = ['search', 'pageNumber'];
	private static $postPerPage = 12;

	public static function init(): void
	{
		add_action('wp_ajax_post_filter', [static::class, 'ajaxCall']);
		add_action('wp_ajax_nopriv_post_filter', [static::class, 'ajaxCall']);
		add_action('pre_get_posts', [static::class, 'filterInit']);
		add_action('init', [static::class, 'initTaxonomies']);
		add_action('template_redirect', [static::class, 'taxonomyRedirect']);
		add_filter('redirect_canonical', [static::class, 'disableCanonical'], 10, 2);
	}

	public static function disableCanonical($redirect_url, $requested_url)
	{

		global $wp_query;

		if (is_home() && isset($wp_query->query_vars['paged']) && $wp_query->query_vars['paged'] > 1) {
			return false;
		}

		return $redirect_url;
	}

	public static function initTaxonomies(): void
	{
		self::setFilterTaxonomies();
	}

	private static function mapInitArgs(): array
	{

		$query_args = [
			'post_type'         => 'post',
			'post_status'       => array('publish'),
			'paged'             => 1,
			'posts_per_page'    => self::$postPerPage,
		];

		if ($_GET) {
			$params = array_filter($_GET, function ($param) {
				$param = str_replace('filter-', "",  $param);
				return in_array($param, self::$getParams);
			}, ARRAY_FILTER_USE_KEY);
		}

		if (isset($params) && $params) {
			foreach ($params as $param => $terms) {
				$terms = wp_strip_all_tags($terms);
				$param = str_replace('filter-', "",  $param);

				if ($param !== 'search' && $param !== 'pageNumber') {

					if ($terms) {
						$terms_objects = [];

						$terms_parsed = explode('-', $terms);
						if ($terms_parsed) {

							foreach ($terms_parsed as $term_ID) {
								$term_info = get_term($term_ID, $param);
								if ($term_info) {
									$terms_objects[] = $term_ID;
								}
							}
						}
					}

					if (isset($terms_objects) && $terms_objects) {
						$tax_query[] = [
							'taxonomy'  => $param,
							'field'     => 'term_id',
							'terms'     => $terms_objects
						];
					}

					if (count($tax_query) > 1) $tax_query['relation'] = 'AND';

					$query_args['tax_query'] = $tax_query;
				} else {
					if ($param == 'pageNumber') {
						$query_args['paged'] = $terms;
					}

					if ($param == 'search') {
						$search_term = $terms;
						$query_args['s'] = $search_term;
					}
				}
			}
		}


		return $query_args;
	}

	public static function taxonomyRedirect()
	{
		if (is_tax() || is_category()) {
			$term = get_queried_object();
			if ($term && !is_wp_error($term)) {
				$taxonomy = $term->taxonomy;
				$post_types = get_taxonomy($taxonomy)->object_type;
				if (in_array('post', $post_types)) {
					$term_id = $term->term_id;
					$redirect_url = add_query_arg('filter-' . $taxonomy, $term_id, get_post_type_archive_link('post') . "#filter");
					wp_redirect($redirect_url);
					exit;
				}
			}
		}
	}

	public static function filterInit($query): void
	{
		if ($query->is_main_query() && !is_admin() && is_home()) {
			$init_args = self::mapInitArgs();

			$query->set('posts_per_page', self::$postPerPage);
			$query->set('post_status', 'publish');
			$query->set('has_password', false);

			if (isset($init_args['paged']) && $init_args['paged']) {
				$query->set('paged', $init_args['paged']);
			}

			if (isset($init_args['s']) && $init_args['s']) {
				$query->set('s', $init_args['s']);
			}

			if (isset($init_args['tax_query']) && $init_args['tax_query']) {
				$query->set('tax_query', $init_args['tax_query']);
			}
		}
	}

	private static function queryPosts($query_args)
	{
		return new WP_Query($query_args);
	}

	public static function filterPosts(callable $mapFunction, string $search_params = ""): WP_Query
	{
		return self::queryPosts(call_user_func($mapFunction, $search_params));
	}

	private static function mapAjaxArgs($search_params): array
	{
		$query_args = [
			'post_type'         => 'post',
			'post_status'       => array('publish'),
			'has_password'       => false,
			'paged'             => 1,
			'posts_per_page'    => self::$postPerPage,
		];

		if ($search_params) {
			$params_with_terms = explode('&', $search_params);

			if ($params_with_terms) {
				foreach ($params_with_terms as $param_with_terms) {
					$param_with_terms = explode('=', $param_with_terms);
					$param = isset($param_with_terms[0]) ? $param_with_terms[0] : "";
					$param = str_replace('filter-', "",  $param);
					$terms = isset($param_with_terms[1]) ? $param_with_terms[1] : "";
					if (!in_array($param, self::$getParams)) continue;

					if ($param !== 'search' && $param !== 'pageNumber') {

						if ($terms) {
							$filter_terms = [];
							$terms_array = explode('-', $terms);

							if ($terms_array) {
								foreach ($terms_array as $term_ID) {
									$term_info = get_term($term_ID, $param);
									if ($term_info) {
										$filter_terms[] = $term_ID;
									}
								}
							}
						}

						if (isset($filter_terms) && $filter_terms) {
							$tax_query[] = [
								'taxonomy'  => $param,
								'field'     => 'term_id',
								'terms'     => $filter_terms
							];
						}


						if (count($tax_query) > 1) $tax_query['relation'] = 'AND';

						$query_args['tax_query'] = $tax_query;
					} else {
						if ($param === 'pageNumber') {
							$query_args['paged'] = $terms;
						}

						if ($param === 'search') {
							$search_term = $terms;
							$query_args['s'] = $search_term;
						}
					}
				}
			}
		}

		return $query_args;
	}


	public static function ajaxCall()
	{

		if (!wp_verify_nonce($_POST['nonce'], 'security')) {
			die('Nonce key is invalid!');
		}

		$search_params = (isset($_POST['searchParams']) && $_POST['searchParams'] ? (string) wp_strip_all_tags($_POST['searchParams']) : "");


		$query = self::filterPosts([self::class, 'mapAjaxArgs'], $search_params);

		ob_start();

		self::pagination($query->query['paged'], $query);

		$pagination = ob_get_clean();

		if ($query->have_posts()) {

			ob_start();
			while ($query->have_posts()) :
				$query->the_post();
				get_template_part('template-parts/content', 'post');
			endwhile;

			wp_reset_query();

			$posts_html = ob_get_clean();

			$response = [
				'postsHtml'     => $posts_html,
				'pagination'    => $pagination
			];

			wp_send_json($response);
		} else {

			ob_start();
?>
			<p class="filter-no-items"><?php esc_html_e('There are no results for the set filters.', 's-tier') ?></p>
<?php
			$posts_html = ob_get_clean();

			$response = [
				'postsHtml'     => $posts_html,
				'pagination'    => $pagination
			];

			wp_send_json($response);
		}

		die();
	}


	private static function setFilterTaxonomies(): void
	{
		$taxonomies = [];

		$taxonomies[] = [
			'taxonomy_object' => get_terms(array(
				'taxonomy'   => 'category',
				'hide_empty' => true,
				'orderby' => 'name',
				'order' => 'ASC',
			)),
			'taxonomy_title' => __('Categories', 's-tier')
		];

		if ($taxonomies) {
			foreach ($taxonomies as $taxonomy) {
				if (is_array($taxonomy) && $taxonomy) {
					foreach ($taxonomy['taxonomy_object'] as $tax_array) {
						if ($tax_array->slug === 'uncategorized') continue;
						$params = array_values($taxonomy['taxonomy_object']);
						self::$getParams[] = $params[0]->taxonomy;
						if ($tax_array->parent == 0) {
							self::$filterTerms[$tax_array->taxonomy][$tax_array->term_id] = [
								'title'    => $taxonomy['taxonomy_title'],
								'taxonomy' => $tax_array->taxonomy,
								'name'     => $tax_array->name,
								'slug'     => $tax_array->slug
							];
						}
					}
				}
			}
		}
	}

	public static function pagination($page_number = 1, $query = null): void
	{
		global $wp_query;

		isset($_GET['pageNumber']) && $_GET['pageNumber'] ? $page_number = wp_strip_all_tags($_GET['pageNumber']) : "";

		$big = 999999999; // need an unlikely integer

		echo "<div class='nav-links'>"
			. paginate_links(array(
				'base'      => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
				'format'    => '?paged=%#%',
				'current'   => max(1, $page_number),
				'total'     => $query ? $query->max_num_pages : $wp_query->max_num_pages,
			)) . "
            </div>";
	}

	public static function sidebar(): void
	{
		ob_start();

		get_template_part('tax-filters/filters-post', null, ['taxonomies' => self::$filterTerms]);

		echo  ob_get_clean();
	}

	public static function search(): void
	{

		ob_start();

		get_template_part('tax-filters/search-form-post');

		echo ob_get_clean();
	}
}
