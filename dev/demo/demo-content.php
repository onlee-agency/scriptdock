<?php
/**
 * DEV ONLY - never deploy. Content types the Lumen Coffee demo needs without
 * installing WooCommerce: Products (with categories) and Workshops, so
 * targeting can name them. Only active when WP_ENVIRONMENT_TYPE is "local".
 */

add_action(
	'init',
	static function () {
		if ( 'local' !== wp_get_environment_type() ) {
			return;
		}
		register_post_type(
			'product',
			array(
				'label'        => 'Products',
				'labels'       => array(
					'name'          => 'Products',
					'singular_name' => 'Product',
				),
				'public'       => true,
				'show_in_rest' => true,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'product' ),
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'menu_icon'    => 'dashicons-cart',
			)
		);
		register_taxonomy(
			'product_cat',
			'product',
			array(
				'label'        => 'Product categories',
				'labels'       => array(
					'name'          => 'Product categories',
					'singular_name' => 'Product category',
				),
				'public'       => true,
				'hierarchical' => true,
				'show_in_rest' => true,
			)
		);
		register_post_type(
			'workshop',
			array(
				'label'        => 'Workshops',
				'labels'       => array(
					'name'          => 'Workshops',
					'singular_name' => 'Workshop',
				),
				'public'       => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'menu_icon'    => 'dashicons-groups',
			)
		);
	}
);
