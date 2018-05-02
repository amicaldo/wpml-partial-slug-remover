<?php
/**
 * @wordpress-plugin
 * Plugin Name:       WPML partial slug remover
 * Plugin URI:        https://www.amicaldo.de
 * Description:       modifies wpml post / archive and taxonomies url. Disable your language folder prefix with wpml custom post and taxonomies options. WPML -> Languages -> PostType Translation / Taxonomies Translation
 * Version:           1.0
 * Author:            amicaldo GmbH
 * License:           No License
 */

/**
 * Disable "translatable" option for posts, pages or taxonomies to remove /$lang/ from url
 *
 * Class amcWPML_RemoveDisabledLanguages
 */
class amcWPMLRemoveDisabledLanguages {
	protected $activeLanguages = NULL;
	protected $enabledTerms = NULL;
	protected $enabledPostTypes = NULL;

	function __construct() {
		$this->registerFilter();
		$this->registerActions();
		$this->activeLanguages = apply_filters( 'wpml_active_languages', NULL);
	}

	public function registerActions() {
	    add_action('registered_post_type', [$this, 'onPostTypeRegistered'], 10, 2);
		add_action( 'icl_post_languages_options_after', [ $this, 'iclPostLanguagesOptionsAfter' ], 11 );
		add_action('save_post', [ $this, 'saveDisabledSlug' ]);
	}

	public function onPostTypeRegistered($name, $postType) {
	    if ($name === "post"){
	        $postType->label = "Blog";
        }
	}

	public function registerFilter() {
		add_filter( 'rewrite_rules_array', [$this, 'addCustomBlogPermalinks'] );
		$this->enabledTerms = apply_filters( 'wpml_setting', NULL,'taxonomies_sync_option');
		$this->enabledPostTypes = apply_filters( 'wpml_setting', NULL,'custom_posts_sync_option');

		foreach ($this->enabledPostTypes AS $name => $enabled){
			if ($enabled) {
				$function = "permalinkRewriteNoLanguageOptional";
			} else {
				$function = "permalinkRewriteNoLanguage";
			}
			add_filter( $name.'_link', [ $this, $function ], 30 );

			if ($name == "post" && !$enabled){
				add_filter( 'get_archives_link', [ $this, 'permalinkRewriteNoLanguage' ], 30 );
				add_filter( 'post_type_archive_link', [ $this, 'permalinkRewriteNoLanguage' ], 30 );
			}
		}

		add_filter( 'term_link', [ $this, 'permalinkTermsRewriteNoLanguage' ], 30, 3 );
	}

	/**
	 * Check if custom_posts_sync_option is set to TRUE so WPML is activated for this post type
	 * otherwise this option is not necessary because /$lang/ get removed in any case
	 *
	 * @return bool
	 */
	public function disableSlugPossible() {
		$post_id = get_the_ID();
		$post = get_post( $post_id );

		if ($this->enabledPostTypes[$post->post_type]) {
			return true;
		}
		return false;
	}

	public function hasDisabledSlug() {
		$post_id = get_the_ID();
		if (empty($post_id)){
			return false;
		}
		return get_post_meta($post_id, '_iclDisabledSlug', true);
	}

	public function saveDisabledSlug() {
		if ($this->disableSlugPossible()){
			$post_id = get_the_ID();

			if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
				return;
			}

			if (!current_user_can('edit_post', $post_id)) {
				return;
			}
			if (isset($_POST['_iclDisabledSlug'])) {
				update_post_meta($post_id, '_iclDisabledSlug', $_POST['_iclDisabledSlug']);
			} else {
				delete_post_meta($post_id, '_iclDisabledSlug');
			}
		}
	}

	public function iclPostLanguagesOptionsAfter() {
		if ($this->disableSlugPossible()) {
			$value = $this->hasDisabledSlug();
			?>

            <div id="iclDisabledSlug" style="display: block;">
                <input type="checkbox" name="_iclDisabledSlug" id="iclDisabledSlug-option" value="1" <?php checked($value, true, true); ?>> <label
                        for="iclDisabledSlug-option" class="selectit">disable language in URL</label><br>
            </div>

			<?php
		}
	}

	public function permalinkTermsRewriteNoLanguage($permalink, $term, $taxonomy) {
		if (isset($this->enabledTerms[$taxonomy])){
			if (!$this->enabledTerms[$taxonomy]){ //new if to ensure default handling = do nothing if isset return false
				return $this->permalinkRewriteNoLanguage($permalink);
			}
		}
		return $permalink;
	}

	public function permalinkRewriteNoLanguageOptional($permalink) {
		if ($this->hasDisabledSlug()){
			return $this->permalinkRewriteNoLanguage($permalink);
		}
		return $permalink;
	}

	public function permalinkRewriteNoLanguage($permalink) {
		foreach ($this->activeLanguages AS $singleLanguage){
			$permalink = str_replace(
				'/'.$singleLanguage['code'].'/',
				'/',
				$permalink
			);
		}

		return $permalink;
	}

	public function addCustomBlogPermalinks($rules) {
		$page_for_posts = get_option( 'page_for_posts' );
		$blogHome = get_post($page_for_posts);

		$customRules = [];
		if ($blogHome) {
			$customRules[ $blogHome->post_name.'/([^/]+)/([^/]+)/?$' ] = 'index.php?name=$matches[2]';
        }

		return $customRules + $rules;
	}
}

new amcWPMLRemoveDisabledLanguages();