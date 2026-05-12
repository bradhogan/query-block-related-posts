<?php
/**
 * Plugin Name: Query Block Related Posts
 * Description: Extends the core Query Loop block with "Hide the current post" and "Show related posts".
 * Version: 1.0.0
 * Author: Your Name
 * Requires at least: 6.5
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Query_Block_Related_Posts {
	/**
	 * Active render settings for the currently rendering Query Loop block.
	 *
	 * @var array|null
	 */
	private static $active_settings = null;

	/**
	 * Boot plugin hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'register_block_type_args', array( __CLASS__, 'register_block_type_args' ), 10, 2 );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_filter( 'render_block_core/query', array( __CLASS__, 'render_block_core_query' ), 10, 2 );
	}

	/**
	 * Register custom attributes on the core/query block.
	 *
	 * @param array  $args       Block type args.
	 * @param string $block_type Block name.
	 * @return array
	 */
	public static function register_block_type_args( $args, $block_type ) {
		if ( 'core/query' !== $block_type ) {
			return $args;
		}

		$args['attributes'] = isset( $args['attributes'] ) && is_array( $args['attributes'] )
			? $args['attributes']
			: array();

		$args['attributes']['qbrpHideCurrentPost'] = array(
			'type'    => 'boolean',
			'default' => false,
		);

		$args['attributes']['qbrpShowRelatedPosts'] = array(
			'type'    => 'boolean',
			'default' => false,
		);

		return $args;
	}

	/**
	 * Enqueue editor-side controls.
	 *
	 * @return void
	 */
	public static function enqueue_editor_assets() {
		$asset_path = plugin_dir_url( __FILE__ ) . 'assets/editor.js';

		wp_enqueue_script(
			'query-block-related-posts-editor',
			$asset_path,
			array(
				'wp-hooks',
				'wp-compose',
				'wp-element',
				'wp-components',
				'wp-block-editor',
			),
			'1.0.0',
			true
		);
	}

	/**
	 * Re-render matching Query Loop blocks while a scoped query filter is active.
	 *
	 * @param string $block_content Rendered block content.
	 * @param array  $block         Parsed block data.
	 * @return string
	 */
	public static function render_block_core_query( $block_content, $block ) {
		if ( is_admin() || ! is_singular() ) {
			return $block_content;
		}

		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$query = isset( $attrs['query'] ) && is_array( $attrs['query'] ) ? $attrs['query'] : array();

		$hide_current_post = ! empty( $attrs['qbrpHideCurrentPost'] ) || ! empty( $query['hideCurrentPost'] );
		$show_related      = ! empty( $attrs['qbrpShowRelatedPosts'] ) || ! empty( $query['showRelatedPosts'] );

		if ( ! $hide_current_post && ! $show_related ) {
			return $block_content;
		}

		$current_post_id = (int) get_queried_object_id();
		$current_post    = $current_post_id ? get_post( $current_post_id ) : null;

		if ( ! $current_post instanceof WP_Post ) {
			return $block_content;
		}

		self::$active_settings = array(
			'hide_current_post' => $hide_current_post,
			'show_related'      => $show_related,
			'current_post_id'   => $current_post_id,
			'current_post_type' => $current_post->post_type,
		);

		add_filter( 'query_loop_block_query_vars', array( __CLASS__, 'filter_query_loop_block_query_vars' ), 999, 2 );

		remove_filter( 'render_block_core/query', array( __CLASS__, 'render_block_core_query' ), 10 );
		$rerendered = render_block( $block );
		add_filter( 'render_block_core/query', array( __CLASS__, 'render_block_core_query' ), 10, 2 );

		remove_filter( 'query_loop_block_query_vars', array( __CLASS__, 'filter_query_loop_block_query_vars' ), 999 );
		self::$active_settings = null;

		return $rerendered;
	}

	/**
	 * Apply custom query modifications to the active Query Loop block.
	 *
	 * @param array    $query Query vars.
	 * @param WP_Block $block Block instance.
	 * @return array
	 */
	public static function filter_query_loop_block_query_vars( $query, $block ) {
		unset( $block );

		if ( empty( self::$active_settings ) ) {
			return $query;
		}

		$current_post_id = (int) self::$active_settings['current_post_id'];
		$current_post    = get_post( $current_post_id );

		if ( ! $current_post instanceof WP_Post ) {
			return $query;
		}

		if ( ! empty( self::$active_settings['hide_current_post'] ) ) {
			$existing = isset( $query['post__not_in'] ) && is_array( $query['post__not_in'] )
				? $query['post__not_in']
				: array();

			$existing[] = $current_post_id;
			$query['post__not_in'] = array_values( array_unique( array_map( 'intval', $existing ) ) );
		}

		if ( ! empty( self::$active_settings['show_related'] ) ) {
			$query['post_type'] = $current_post->post_type;

			$related_tax_query = self::build_related_tax_query( $current_post );

			if ( ! empty( $related_tax_query ) ) {
				$existing_tax_query = isset( $query['tax_query'] ) && is_array( $query['tax_query'] )
					? $query['tax_query']
					: array();

				if ( empty( $existing_tax_query ) ) {
					$query['tax_query'] = $related_tax_query;
				} else {
					$query['tax_query'] = array(
						'relation' => 'AND',
						$existing_tax_query,
						$related_tax_query,
					);
				}
			}
		}

		return $query;
	}

	/**
	 * Build related taxonomy constraints from the current post's public taxonomies.
	 *
	 * @param WP_Post $current_post Current singular post.
	 * @return array
	 */
	private static function build_related_tax_query( $current_post ) {
		$taxonomies = get_object_taxonomies( $current_post->post_type, 'objects' );

		if ( empty( $taxonomies ) || ! is_array( $taxonomies ) ) {
			return array();
		}

		$clauses = array();

		foreach ( $taxonomies as $taxonomy => $taxonomy_obj ) {
			if ( ! $taxonomy_obj || empty( $taxonomy_obj->public ) ) {
				continue;
			}

			$terms = get_the_terms( $current_post->ID, $taxonomy );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			$term_ids = array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) );

			if ( empty( $term_ids ) ) {
				continue;
			}

			$clauses[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_ids,
				'operator' => 'IN',
			);
		}

		if ( empty( $clauses ) ) {
			return array();
		}

		return array_merge(
			array(
				'relation' => 'OR',
			),
			$clauses
		);
	}
}

Query_Block_Related_Posts::init();
