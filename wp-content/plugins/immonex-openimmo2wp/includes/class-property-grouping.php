<?php
namespace immonex\OpenImmo2Wp;

/**
 * Property grouping (parent <-> children) related methods.
 */
class Property_Grouping {

	/**
	 * Extract XML property data that will be stored as custom fields.
	 *
	 * @since 4.7.0
	 * @static
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return string[] Meta key:value array.
	 */
	public static function get_grouping_fields( $immobilie ) {
		$group_number = trim( (string) $immobilie->verwaltung_objekt->gruppennummer );
		$group_id = trim( (string) $immobilie->verwaltung_techn->gruppen_kennung );
		$master_id = trim( (string) $immobilie->verwaltung_techn->master );
		$group_master = '';

		if ( $master_id && $master_id === $group_id ) {
			$group_master = in_array(
				strtolower( (string) $immobilie->verwaltung_techn->master['visible'] ),
				array( '1', 'true' )
			) ? 'visible' : 'invisible';
		}

		return array(
			'_immonex_group_number' => $group_number,
			'_immonex_group_id' => $group_id,
			'_immonex_group_master' => $group_master,
		);
	} // get_grouping_fields

	/**
	 * Get the group parent property ID of the child property with the given ID.
	 *
	 * @since 4.7.0
	 * @static
	 *
	 * @param int|string $post_id Child property post ID.
	 *
	 * @return int|bool Parent property post ID or false if not found (or error).
	 */
	public static function get_parent_id( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return false;
		}

		$group_id = get_post_meta( $post_id, '_immonex_group_id', true );
		if ( ! $group_id ) {
			return false;
		}

		$group_master = get_post_meta( $post_id, '_immonex_group_master', true );
		if ( $group_master ) {
			// Property is a master object itself!
			return false;
		}

		$import_folder = get_post_meta( $post_id, '_immonex_import_folder', true );
		if ( ! $import_folder ) {
			$import_folder = 'global';
		}

		$args = array(
			'post_type' => $post_type,
			'numberposts' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_immonex_import_folder',
					'value' => $import_folder
				),
				array(
					'key' => '_immonex_group_id',
					'value' => $group_id
				),
				array(
					'key' => '_immonex_group_master',
					'value' => '',
					'compare' => '!='
				)
			)
		);

		$posts = get_posts( $args );

		return count( $posts ) > 0 ? $posts[0] : false;
	} // get_parent_id

	/**
	 * Get the group children property IDs of the parent property with the given ID.
	 *
	 * @since 4.7.0
	 * @static
	 *
	 * @param int|string $post_id Parent property post ID.
	 *
	 * @return int[]|bool Parent property post IDs or false on error.
	 */
	public static function get_children_ids( $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( ! $post_type ) {
			return false;
		}

		$group_id = get_post_meta( $post_id, '_immonex_group_id', true );
		if ( ! $group_id ) {
			return false;
		}

		$group_master = get_post_meta( $post_id, '_immonex_group_master', true );
		if ( ! $group_master ) {
			// Property is not a master object!
			return false;
		}

		$import_folder = get_post_meta( $post_id, '_immonex_import_folder', true );
		if ( ! $import_folder ) {
			$import_folder = 'global';
		}

		$args = array(
			'post_type' => $post_type,
			'numberposts' => -1,
			'fields' => 'ids',
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key' => '_immonex_import_folder',
					'value' => $import_folder
				),
				array(
					'key' => '_immonex_group_id',
					'value' => $group_id
				),
				array(
					'key' => '_immonex_group_master',
					'value' => '',
					'compare' => '='
				)
			)
		);

		return get_posts( $args );
	} // get_children_ids

} // class Property_Grouping
