<?php
/**
 * Class Taxonomy_Utils
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Taxonomy related utility methods/hooks.
 */
class Taxonomy_Utils {

	/**
	 * Array of bootstrap data
	 *
	 * @var mixed[]
	 */
	private $data;

	/**
	 * Utility objects
	 *
	 * @var object[]
	 */
	private $utils;

	/**
	 * Prefix for hook names etc.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Constructor
	 *
	 * @since 5.3.12-beta
	 *
	 * @param mixed[]  $bootstrap_data Plugin bootstrap data.
	 * @param object[] $utils          Utility objects.
	 */
	public function __construct( $bootstrap_data, $utils ) {
		$this->data   = $bootstrap_data;
		$this->prefix = $bootstrap_data['plugin_prefix'];
		$this->utils  = $utils;

		add_filter( 'get_terms', [ $this, 'delete_term_name_language_tags' ] );
		add_filter( 'wp_get_object_terms', [ $this, 'delete_term_name_language_tags' ] );

		add_filter( 'immonex_oi2wp_term_multilang', [ $this, 'get_term_multilang_cb' ], 10, 5 );
		add_filter( 'immonex_oi2wp_term_by_slug_no_multilang', [ $this, 'get_term_by_slug_no_multilang_cb' ], 10, 3 );
		add_filter( 'immonex_oi2wp_add_new_term', [ $this, 'insert_term_cb' ], 10, 7 );
	} // __construct

	/**
	 * Get a term's data based on its name in the specified or the
	 * current import language (filter callback).
	 *
	 * @since 5.3.12-beta
	 *
	 * @param mixed[]    $term_data Empty array.
	 * @param string     $term_name Name of the term to search for.
	 * @param string     $taxonomy  The term's taxonomy.
	 * @param string     $language  Query language (optional).
	 * @param int|string $parent_id Parent term ID (optional).
	 *
	 * @return mixed[]|bool Associative array of term data or false if not found.
	 */
	public function get_term_multilang_cb( $term_data, $term_name, $taxonomy, $language = false, $parent_id = false ) {
		return $this->get_term_multilang( $term_name, $taxonomy, $language, $parent_id );
	} // get_term_multilang_cb

	/**
	 * Get a term's data based on its name in the specified or the
	 * current import language.
	 *
	 * @since 5.3.12-beta
	 *
	 * @param string          $term_name Name of the term to search for.
	 * @param string          $taxonomy  The term's taxonomy.
	 * @param string|bool     $language  Query language (optional).
	 * @param int|string|bool $parent_id Parent term ID (optional).
	 *
	 * @return mixed[]|bool Associative array of term data or false if not found.
	 */
	public function get_term_multilang( $term_name, $taxonomy, $language = false, $parent_id = false ) {
		global $wpdb;

		if ( ! $language ) {
			$language = apply_filters( 'immonex_oi2wp_current_import_language', '' );
		}

		if ( ! apply_filters( 'immonex_oi2wp_enable_multilang', true ) ) {
			// Multilingual processing is not enabled: Perform a normal WP term query.
			return get_term_by( 'name', $term_name, $taxonomy, ARRAY_A );
		}

		/**
		 * Get all terms independent from the used translation management solution by a direct DB query,
		 * include "language tag titles" for compatibility reasons.
		 */
		if ( false !== $parent_id ) {
			// Parent term ID given.
			$terms = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->terms terms INNER JOIN $wpdb->term_taxonomy term_taxonomy ON terms.term_id = term_taxonomy.term_id WHERE term_taxonomy.taxonomy = %s AND term_taxonomy.parent = %d AND ( terms.name = %s OR terms.name = %s )",
					$taxonomy,
					$parent_id,
					$term_name,
					$term_name . " @{$language}"
				),
				ARRAY_A
			);
		} else {
			// No parent term ID given.
			$terms = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM $wpdb->terms terms INNER JOIN $wpdb->term_taxonomy term_taxonomy ON terms.term_id = term_taxonomy.term_id WHERE term_taxonomy.taxonomy = %s AND ( terms.name = %s OR terms.name = %s )",
					$taxonomy,
					$term_name,
					$term_name . " @{$language}"
				),
				ARRAY_A
			);
		}

		if ( count( $terms ) > 0 ) {
			foreach ( $terms as $term ) {
				$term_language = $language;

				if (
					function_exists( 'pll_is_translated_taxonomy' ) &&
					pll_is_translated_taxonomy( $taxonomy ) &&
					function_exists( 'pll_get_term_language' )
				) {
					// Polylang available + translated taxonomy.
					$term_language = pll_get_term_language( $term['term_id'] );
				} elseif ( apply_filters( 'wpml_is_translated_taxonomy', null, $taxonomy ) ) {
					// WPML available + translated taxonomy.
					$args = [
						'element_id'   => $term['term_taxonomy_id'],
						'element_type' => $taxonomy
					];
					$term_language = apply_filters( 'wpml_element_language_code', null, $args );
				}

				if ( $term_language === $language ) {
					// Term in current language found via Polylang or WPML.
					return $term;
				}
			}

			/**
			 * No term in specified language found yet, now search for
			 * matching language suffixes in term slugs.
			 */
			foreach ( $terms as $term_raw ) {
				if (
					"-{$language}" === substr( $term_raw['slug'], -3 )
					&& ! preg_match( '/[^a-z\@]' . $language . '$/i', $term_raw['name'] )
				) {
					$term = get_term( $term_raw['term_id'], $taxonomy, ARRAY_A );

					if ( $term ) {
						return $term;
					}
				}
			}

			if ( ! $this->utils['ml']::is_ml_env() ) {
				/**
				 * Polylang or WPML not in use and no term with language suffix found:
				 * Return the first found term.
				 */
				$term = get_term( $terms[0]['term_id'], $taxonomy, ARRAY_A );

				if ( $term ) {
					return $term;
				}
			}
		}

		// No matching term found.
		return false;
	} // get_term_multilang

	/**
	 * Get a term by its exact slug independent of its language or the translation
	 * management solution in use.
	 *
	 * @since 5.3.12-beta
	 *
	 * @param mixed[] $term_data Empty array.
	 * @param string  $term_slug Slug of the term to search for.
	 * @param string  $taxonomy  The term's taxonomy.
	 *
	 * @return WP_Term|bool Term object or false if not found.
	 */
	public function get_term_by_slug_no_multilang_cb( $term_data, $term_slug, $taxonomy ) {
		return $this->get_term_by_slug_no_multilang( $term_slug, $taxonomy );
	} // get_term_by_slug_no_multilang_cb

	/**
	 * Get a term by its exact slug independent of its language or the translation
	 * management solution in use.
	 *
	 * @since 5.3.12-beta
	 *
	 * @param string $term_slug Slug of the term to search for.
	 * @param string $taxonomy  The term's taxonomy.
	 *
	 * @return WP_Term|bool Term object or false if not found.
	 */
	public function get_term_by_slug_no_multilang( $term_slug, $taxonomy ) {
		global $wpdb;

		if ( ! apply_filters( 'immonex_oi2wp_enable_multilang', true ) ) {
			// Multilingual processing is not enabled: Perform a normal WP term query.
			return get_term_by( 'slug', $term_slug, $taxonomy, ARRAY_A );
		}

		$term_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT terms.term_id FROM $wpdb->terms terms INNER JOIN $wpdb->term_taxonomy term_taxonomy ON terms.term_id = term_taxonomy.term_id WHERE term_taxonomy.taxonomy = %s AND terms.slug = %s",
			$taxonomy,
			$term_slug
		) );

		return $term_id ? get_term_by( 'id', $term_id, $taxonomy ) : false;
	} // get_term_by_slug_no_multilang

	/**
	 * Delete "@" language tags from taxonomy term names (filter callback).
	 *
	 * @since 5.3.12-beta
	 *
	 * @param \WP_Term[] $terms Array of taxonomy term objects.
	 *
	 * @return \WP_Term[] Array of terms with cleaned names.
	 */
	public function delete_term_name_language_tags( $terms ) {
		if ( ! empty( $terms ) ) {
			foreach ( $terms as $id => $term ) {
				if ( ! is_object( $term ) ) {
					continue;
				}

				if ( false !== strpos( $term->name, '@' ) ) {
					$terms[ $id ]->name = trim( substr( $term->name, 0, strpos( $term->name, '@' ) ) );
				}
			}
		}

		return $terms;
	} // delete_term_name_language_tags

	/**
	 * Insert a new taxonomy term (filter callback).
	 *
	 * @since 5.3.12-beta
	 *
	 * @param mixed[]                $term_data          Empty array.
	 * @param string                 $taxonomy           The term's taxonomy.
	 * @param int|bool               $parent_id          Parent term ID (false if none).
	 * @param string                 $mapping_field_type Mapping field type/source (parent, title or import_value).
	 * @param mixed[]                $mapping            Related mapping data (optional).
	 * @param \SimpleXMLElement|bool $immobilie          Object of the related property XML node (optional).
	 *
	 * @return mixed[]|string|bool|\WP_Error Term data array on successful inserts, "skip" if creation was prevented
	 *                                       via filter function, false if the taxonomy is invalid or WP_Error
	 *                                       in case of an error during insertion.
	 */
	public function insert_term_cb( $term_data, $term_name, $taxonomy, $parent_id, $mapping_field_type, $mapping = [], $immobilie = false ) {
		return $this->insert_term( $term_name, $taxonomy, $parent_id, $mapping_field_type, $mapping, $immobilie );
	} // insert_term_cb

	/**
	 * Insert a new taxonomy term.
	 *
	 * @since 5.3.12-beta
	 *
	 * @param string                 $term_name          Term name.
	 * @param string                 $taxonomy           The term's taxonomy.
	 * @param int|bool               $parent_id          Parent term ID (false if none).
	 * @param string                 $mapping_field_type Mapping field type/source (parent, title or import_value).
	 * @param mixed[]                $mapping            Related mapping data (optional).
	 * @param \SimpleXMLElement|bool $immobilie          Object of the related property XML node (optional).
	 *
	 * @return mixed[]|string|bool|\WP_Error Term data array on successful inserts, "skip" if creation was prevented
	 *                                       via filter function, false if the taxonomy is invalid or WP_Error
	 *                                       in case of an error during insertion.
	 */
	public function insert_term( $term_name, $taxonomy, $parent_id, $mapping_field_type, $mapping = [], $immobilie = false ) {
		$taxonomy_data = get_taxonomy( $taxonomy );
		if ( ! $taxonomy_data ) {
			return false;
		}

		$args                = [];
		$enable_multilang    = apply_filters( 'immonex_oi2wp_enable_multilang', true );
		$current_import_lang = apply_filters( 'immonex_oi2wp_current_import_language', '' );

		if ( empty( $mapping ) ) {
			$mapping = [
				'dest' => $taxonomy
			];
		}

		/**
		 * Check if a term with the same name (NOT slug!) and parent ID
		 * (if specified) already exists.
		 */
		if ( $parent_id && $taxonomy_data->hierarchical ) {
			$args['parent'] = $parent_id;

			$same_named_term = ! empty(
				get_terms(
					$taxonomy,
					[
						'name'       => $term_name,
						'parent'     => $parent_id,
						'hide_empty' => false
					]
				)
			);
		} else {
			$same_named_term = get_term_by( 'name', $term_name, $taxonomy );
		}

		$args['slug'] = $this->generate_slug( $term_name, $taxonomy, $current_import_lang, $parent_id );

		$new_term = apply_filters(
			'immonex_oi2wp_insert_taxonomy_term',
			[
				'term_value' => $term_name . ( $enable_multilang && $same_named_term ? " @{$current_import_lang}" : '' ),
				'taxonomy'   => $taxonomy,
				'args'       => $args,
				'mapping'    => $mapping,
			],
			$immobilie
		);

		if ( empty( $new_term ) ) {
			return 'skip';
		}

		$term_data = wp_insert_term( $new_term['term_value'], $new_term['taxonomy'], $new_term['args'] );

		if ( ! $term_data || is_wp_error( $term_data ) ) {
			return $term_data;
		}

		if ( $enable_multilang ) {
			$term_data['taxonomy'] = $taxonomy;
			// Assign current language to the new term.
			do_action( 'immonex_oi2wp_multilang_set_taxonomy_term_language', $term_data, $mapping, $mapping_field_type, $current_import_lang );
		}

		do_action( 'immonex_oi2wp_taxonomy_term_inserted', $term_data, [ 'mapping' => $mapping ], $immobilie );

		return $term_data;
	} // insert_term

	/**
	 * Generate an appropriate term slug considering an (optional) parent ID and
	 * the current import language in multilingual environments by checking the
	 * availability in the following order:
	 *
	 * - [slugified-term-name]
	 * - [slugified-term-name]-[parent-slug] (if parent ID is given)
	 * - [slugified-term-name]-[current-import-language-tag]
	 *
	 * @since 5.3.12-beta
	 *
	 * @param string   $term_name  Term name.
	 * @param string   $taxonomy   The term's taxonomy.
	 * @param string   $lang       Current import language.
	 * @param int|bool $parent_id  Parent term ID (optional).
	 *
	 * @return string Term slug.
	 */
	public function generate_slug( $term_name, $taxonomy, $lang, $parent_id = false ) {
		$check_slug = $this->utils['string']::slugify( $term_name );
		$slug       = $this->get_term_by_slug_no_multilang( $check_slug, $taxonomy );

		if ( ! $slug ) {
			// "Pure" slug (without parent slug or language tag) is available: return it!
			return $this->maybe_add_lang_tag( $check_slug, $lang );
		}

		if ( $parent_id ) {
			/**
			 * Pure slug is not available anymore and a parent ID has been specified:
			 * Check an extended variant with the parent slug (without language tag)
			 * attached in the next step.
			 */
			$parent_term      = get_term( $parent_id );
			$parent_term_slug = preg_replace( "/-{$lang}$/", '', $parent_term->slug );

			if ( $parent_term && ! is_wp_error( $parent_term ) ) {
				$ext_check_slug = "{$check_slug}-{$parent_term_slug}";
				$slug           = $this->get_term_by_slug_no_multilang( $ext_check_slug, $taxonomy );

				if ( ! $slug ) {
					return $this->maybe_add_lang_tag( $ext_check_slug, $lang );
				}
			}
		}

		/**
		 * Return the slug with the (current) language tag attached if an extended
		 * variant (incl. parent slug) isn't available either.
		 */
		return $this->maybe_add_lang_tag( $check_slug, $lang, true );
	} // generate_slug

	/**
	 * Add a language tag to the specified slug if required.
	 *
	 * @since 5.3.12-beta
	 *
	 * @param string $slug  Term slug.
	 * @param string $lang  Current import language.
	 * @param bool   $force Force addition of language tag (optional, false by default).
	 *
	 * @return string Term slug (possibly with added language tag).
	 */
	private function maybe_add_lang_tag( $slug, $lang, $force = false ) {
		$enable_multilang    = apply_filters( 'immonex_oi2wp_enable_multilang', true );
		$current_import_lang = apply_filters( 'immonex_oi2wp_current_import_language', '' );
		$force_lang_tags     = $force ? true : apply_filters( 'immonex_oi2wp_force_slug_language_tags', false );

		if ( ! $enable_multilang ) {
			return $slug;
		}

		if ( $force_lang_tags ) {
			return "{$slug}-{$current_import_lang}";
		}

		return $slug;
	} // maybe_add_lang_tag

} // class Taxonomy_Utils
