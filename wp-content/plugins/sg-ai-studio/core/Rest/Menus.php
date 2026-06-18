<?php
/**
 * Menus API class for managing WordPress menus via REST API
 *
 * @package SG_AI_Studio
 */

namespace SG_AI_Studio\Rest;

use WP_REST_Response;
use WP_REST_Request;
use WP_Error;
use SG_AI_Studio\Activity_Log\Activity_Log_Helper;
use SG_AI_Studio\Helper\Helper;

/**
 * Handles REST API endpoints for menu operations.
 * Supports both traditional menus and FSE (Full Site Editing) navigation.
 */
class Menus extends Rest_Controller_Base {
	/**
	 * REST API base for menus
	 *
	 * @var string
	 */
	private $base = 'menus';

	/**
	 * REST API base for menu items
	 *
	 * @var string
	 */
	private $items_base = 'menu-items';

	/**
	 * REST API base for menu locations
	 *
	 * @var string
	 */
	private $locations_base = 'menu-locations';

	/**
	 * Check if the current theme uses Full Site Editing
	 *
	 * @return bool
	 */
	private function is_fse_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		// ========== THEME INFO ==========.

		// Get theme menu type (FSE or traditional).
		register_rest_route(
			$this->namespace,
			'/menu-type',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_menu_type' ),
					'permission_callback' => array( $this, 'list_permissions_check' ),
					'description'         => 'Returns whether the theme uses FSE navigation or traditional menus.',
				),
			)
		);

		// ========== MENUS (Containers) ==========.

		// List all menus & Create a new menu.
		register_rest_route(
			$this->namespace,
			'/' . $this->base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_menus' ),
					'permission_callback' => array( $this, 'list_permissions_check' ),
					'description'         => 'Retrieves a list of all menus with their ID, name, and slug.',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_menu' ),
					'permission_callback' => array( $this, 'create_permissions_check' ),
					'args'                => $this->get_create_menu_args(),
					'description'         => 'Creates a new menu with the provided name.',
				),
			)
		);

		// Delete a specific menu.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_menu' ),
					'permission_callback' => array( $this, 'delete_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => 'Unique identifier for the menu.',
							'type'        => 'integer',
							'required'    => true,
						),
					),
					'description'         => 'Deletes a specific menu.',
				),
			)
		);

		// List items in a specific menu.
		register_rest_route(
			$this->namespace,
			'/' . $this->base . '/(?P<id>[\d]+)/items',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_menu_items' ),
					'permission_callback' => array( $this, 'list_permissions_check' ),
					'args'                => array(
						'id' => array(
							'description' => 'Unique identifier for the menu.',
							'type'        => 'integer',
							'required'    => true,
						),
					),
					'description'         => 'Retrieves all items within a specific menu.',
				),
			)
		);

		// ========== MENU ITEMS (The Links) ==========.

		// Add a new menu item.
		register_rest_route(
			$this->namespace,
			'/' . $this->items_base,
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'add_menu_item' ),
					'permission_callback' => array( $this, 'create_permissions_check' ),
					'args'                => $this->get_add_menu_item_args(),
					'description'         => 'Adds a new item to a menu (custom link or post/page object).',
				),
			)
		);

		// Update & Delete a specific menu item.
		register_rest_route(
			$this->namespace,
			'/' . $this->items_base . '/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_menu_item' ),
					'permission_callback' => array( $this, 'update_permissions_check' ),
					'args'                => $this->get_update_menu_item_args(),
					'description'         => 'Updates an existing menu item (title, url, order, parent, etc.).',
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_menu_item' ),
					'permission_callback' => array( $this, 'delete_permissions_check' ),
					'args'                => array(
						'id'      => array(
							'description' => 'Unique identifier for the menu item.',
							'type'        => 'integer',
							'required'    => true,
						),
						'menu_id' => array(
							'description' => 'The navigation post ID (required for FSE themes).',
							'type'        => 'integer',
							'required'    => false,
						),
					),
					'description'         => 'Removes a specific item from its menu.',
				),
			)
		);

		// ========== MENU LOCATIONS (Theme Integration) ==========.

		// List all menu locations & Assign a menu to a location.
		register_rest_route(
			$this->namespace,
			'/' . $this->locations_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_menu_locations' ),
					'permission_callback' => array( $this, 'list_permissions_check' ),
					'description'         => 'Retrieves available theme locations and which menu ID is assigned to each.',
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'assign_menu_location' ),
					'permission_callback' => array( $this, 'update_permissions_check' ),
					'args'                => $this->get_assign_location_args(),
					'description'         => 'Assigns a specific menu to a theme location.',
				),
			)
		);
	}

	// ========== THEME INFO METHODS ==========.

	/**
	 * Get menu type (FSE or traditional)
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function get_menu_type( $request ) {
		$is_fse = $this->is_fse_theme();

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'type'   => $is_fse ? 'fse' : 'traditional',
					'is_fse' => $is_fse,
				),
			),
			200
		);
	}

	// ========== MENUS METHODS ==========.

	/**
	 * Get all menus (supports both traditional and FSE)
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function get_menus( $request ) {
		$data = array();

		if ( $this->is_fse_theme() ) {
			// FSE: Get wp_navigation posts.
			$navigations = get_posts(
				array(
					'post_type'      => 'wp_navigation',
					'post_status'    => array( 'publish', 'draft' ),
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);

			foreach ( $navigations as $nav ) {
				$data[] = array(
					'id'     => $nav->ID,
					'name'   => $nav->post_title,
					'slug'   => $nav->post_name,
					'type'   => 'fse',
					'status' => $nav->post_status,
				);
			}
		} else {
			// Traditional: Get nav menus.
			$menus = wp_get_nav_menus();

			foreach ( $menus as $menu ) {
				$data[] = array(
					'id'   => $menu->term_id,
					'name' => $menu->name,
					'slug' => $menu->slug,
					'type' => 'traditional',
				);
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Create a new menu (supports both traditional and FSE)
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function create_menu( $request ) {
		$menu_name = sanitize_text_field( $request['name'] );

		if ( empty( $menu_name ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Menu name is required.', 'sg-ai-studio' ),
				),
				400
			);
		}

		if ( $this->is_fse_theme() ) {
			// FSE: Create wp_navigation post.
			// wp_navigation posts store navigation-link blocks directly in post_content.
			$nav_post = array(
				'post_type'    => 'wp_navigation',
				'post_title'   => $menu_name,
				'post_status'  => 'publish',
				'post_content' => '',
			);

			$nav_id = wp_insert_post( $nav_post, true );

			if ( is_wp_error( $nav_id ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $nav_id->get_error_message(),
					),
					400
				);
			}

			$nav = get_post( $nav_id );

			if ( ! $nav ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Failed to retrieve created navigation.', 'sg-ai-studio' ),
					),
					500
				);
			}

			// Log the activity.
			/* translators: %1$s is the menu name, %2$d is the menu ID. */
			Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'FSE Navigation Created: %1$s (ID: %2$d)', 'sg-ai-studio' ), $nav->post_title, $nav_id ) );

			// Clear all caches.
			if( \function_exists('\sg_cachepress_purge_cache') ) {
				\sg_cachepress_purge_cache();
				\wp_cache_flush();
			} else {
				\wp_cache_flush();
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array(
						'id'     => $nav->ID,
						'name'   => $nav->post_title,
						'slug'   => $nav->post_name,
						'type'   => 'fse',
						'status' => $nav->post_status,
					),
				),
				201
			);
		} else {
			// Traditional: Create nav menu.
			$menu_id = wp_create_nav_menu( $menu_name );

			if ( is_wp_error( $menu_id ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $menu_id->get_error_message(),
					),
					400
				);
			}

			// Get the created menu.
			$menu = wp_get_nav_menu_object( $menu_id );

			if ( ! $menu ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Failed to retrieve created menu.', 'sg-ai-studio' ),
					),
					500
				);
			}

			// Log the activity.
			/* translators: %1$s is the menu name, %2$d is the menu ID. */
			Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'Menu Created: %1$s (ID: %2$d)', 'sg-ai-studio' ), $menu->name, $menu_id ) );

			// Clear all caches.
			if( \function_exists('\sg_cachepress_purge_cache') ) {
				\sg_cachepress_purge_cache();
				\wp_cache_flush();
			} else {
				\wp_cache_flush();
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'data'    => array(
						'id'   => $menu->term_id,
						'name' => $menu->name,
						'slug' => $menu->slug,
						'type' => 'traditional',
					),
				),
				201
			);
		}
	}

	/**
	 * Delete a menu (supports both traditional and FSE)
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function delete_menu( $request ) {
		// Check if powermode is enabled.
		if ( ! get_option( 'sg_ai_studio_powermode', false ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Powermode is disabled. This operation is not allowed.', 'sg-ai-studio' ),
				),
				412
			);
		}

		$menu_id = absint( $request['id'] );

		if ( $this->is_fse_theme() ) {
			// FSE: Delete wp_navigation post.
			$nav = get_post( $menu_id );

			if ( ! $nav || 'wp_navigation' !== $nav->post_type ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Invalid navigation ID.', 'sg-ai-studio' ),
					),
					404
				);
			}

			$nav_name = $nav->post_title;

			// Delete the navigation.
			$result = wp_delete_post( $menu_id, true );

			if ( ! $result ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Failed to delete navigation.', 'sg-ai-studio' ),
					),
					500
				);
			}

			// Log the activity.
			/* translators: %1$s is the menu name, %2$d is the menu ID. */
			Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'FSE Navigation Deleted: %1$s (ID: %2$d)', 'sg-ai-studio' ), $nav_name, $menu_id ) );

			// Clear all caches.
			if( \function_exists('\sg_cachepress_purge_cache') ) {
				\sg_cachepress_purge_cache();
				\wp_cache_flush();
			} else {
				\wp_cache_flush();
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Navigation deleted successfully.', 'sg-ai-studio' ),
				),
				200
			);
		} else {
			// Traditional: Delete nav menu.
			$menu = wp_get_nav_menu_object( $menu_id );

			if ( ! $menu ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Invalid menu ID.', 'sg-ai-studio' ),
					),
					404
				);
			}

			$menu_name = $menu->name;

			// Delete the menu.
			$result = wp_delete_nav_menu( $menu_id );

			if ( is_wp_error( $result ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => $result->get_error_message(),
					),
					500
				);
			}

			// Log the activity.
			/* translators: %1$s is the menu name, %2$d is the menu ID. */
			Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'Menu Deleted: %1$s (ID: %2$d)', 'sg-ai-studio' ), $menu_name, $menu_id ) );

			// Clear all caches.
			if( \function_exists('\sg_cachepress_purge_cache') ) {
				\sg_cachepress_purge_cache();
				\wp_cache_flush();
			} else {
				\wp_cache_flush();
			}

			return new WP_REST_Response(
				array(
					'success' => true,
					'message' => __( 'Menu deleted successfully.', 'sg-ai-studio' ),
				),
				200
			);
		}
	}

	// ========== MENU ITEMS METHODS ==========.

	/**
	 * Get all items in a specific menu (supports both traditional and FSE)
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function get_menu_items( $request ) {
		$menu_id = absint( $request['id'] );
		$data    = array();

		if ( $this->is_fse_theme() ) {
			// FSE: Parse blocks from wp_navigation post.
			$nav = get_post( $menu_id );

			if ( ! $nav || 'wp_navigation' !== $nav->post_type ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Invalid navigation ID.', 'sg-ai-studio' ),
					),
					404
				);
			}

			// Parse blocks from content.
			$blocks = parse_blocks( $nav->post_content );
			$data   = $this->parse_navigation_blocks( $blocks );

		} else {
			// Traditional: Get nav menu items.
			$menu = wp_get_nav_menu_object( $menu_id );

			if ( ! $menu ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Invalid menu ID.', 'sg-ai-studio' ),
					),
					404
				);
			}

			$items = wp_get_nav_menu_items( $menu_id );

			if ( $items ) {
				foreach ( $items as $item ) {
					$data[] = array(
						'id'          => $item->ID,
						'title'       => $item->title,
						'url'         => $item->url,
						'type'        => $item->type,
						'object'      => $item->object,
						'object_id'   => $item->object_id,
						'parent'      => $item->menu_item_parent,
						'menu_order'  => $item->menu_order,
						'target'      => $item->target,
						'attr_title'  => $item->attr_title,
						'description' => $item->description,
						'classes'     => $item->classes,
						'xfn'         => $item->xfn,
					);
				}
			}
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Parse navigation blocks recursively
	 *
	 * @param array $blocks The blocks to parse.
	 * @param int   $parent_id Parent block ID.
	 * @return array Parsed menu items.
	 */
	private function parse_navigation_blocks( $blocks, $parent_id = 0 ) {
		$items = array();
		$index = 0;

		// List of all blocks allowed in WordPress navigation block.
		$navigation_blocks = array(
			'core/navigation-link',
			'core/navigation-submenu',
			'core/page-list',
			'core/home-link',
			'core/loginout',
			'core/search',
			'core/social-links',
			'core/spacer',
			'core/icon',
			'core/site-title',
			'core/site-logo',
			'core/buttons',
		);

		// Dynamic blocks that generate content server-side.
		$dynamic_blocks = array(
			'core/page-list',
			'core/home-link',
			'core/loginout',
		);

		foreach ( $blocks as $block ) {
			$block_name = $block['blockName'] ?? '';

			// Check if this is a navigation-related block.
			if ( in_array( $block_name, $navigation_blocks, true ) ) {
				$attrs = $block['attrs'] ?? array();

				// Build item based on block type.
				$item = $this->parse_navigation_block_item( $block_name, $attrs, $index, $parent_id );

				// Mark dynamic blocks.
				if ( in_array( $block_name, $dynamic_blocks, true ) ) {
					$item['is_dynamic'] = true;
				}

				$items[] = $item;

				// Parse inner blocks (submenu items or nested content).
				if ( ! empty( $block['innerBlocks'] ) ) {
					$subitems = $this->parse_navigation_blocks( $block['innerBlocks'], $item['id'] );
					$items    = array_merge( $items, $subitems );
				}

				++$index;
			} elseif ( ! empty( $block['innerBlocks'] ) ) {
				// If it's a container block (like core/navigation), parse its children.
				$subitems = $this->parse_navigation_blocks( $block['innerBlocks'], $parent_id );
				$items    = array_merge( $items, $subitems );
			}
		}

		return $items;
	}

	/**
	 * Parse individual navigation block item
	 *
	 * @param string $block_name The block type name.
	 * @param array  $attrs Block attributes.
	 * @param int    $index Item index.
	 * @param int    $parent_id Parent item ID.
	 * @return array Parsed item data.
	 */
	private function parse_navigation_block_item( $block_name, $attrs, $index, $parent_id ) {
		$item = array(
			'id'         => isset( $attrs['id'] ) ? $attrs['id'] : $index,
			'block_type' => $block_name,
			'parent'     => $parent_id,
			'menu_order' => $index,
		);

		// Handle different block types.
		switch ( $block_name ) {
			case 'core/navigation-link':
			case 'core/navigation-submenu':
				$item['title']     = isset( $attrs['label'] ) ? $attrs['label'] : '';
				$item['url']       = isset( $attrs['url'] ) ? $attrs['url'] : '';
				$item['type']      = isset( $attrs['type'] ) ? $attrs['type'] : 'custom';
				$item['kind']      = isset( $attrs['kind'] ) ? $attrs['kind'] : '';
				$item['object_id'] = isset( $attrs['id'] ) ? $attrs['id'] : 0;
				$item['target']    = isset( $attrs['opensInNewTab'] ) && $attrs['opensInNewTab'] ? '_blank' : '';
				$item['classes']   = isset( $attrs['className'] ) ? array( $attrs['className'] ) : array();
				break;

			case 'core/page-list':
				$item['title'] = 'Page List';
				$item['attrs'] = array(
					'parentPageID' => isset( $attrs['parentPageID'] ) ? $attrs['parentPageID'] : 0,
				);
				break;

			case 'core/home-link':
				$item['title'] = isset( $attrs['label'] ) ? $attrs['label'] : 'Home';
				$item['url']   = home_url( '/' );
				break;

			case 'core/loginout':
				$item['title']              = 'Login/Logout';
				$item['displayLoginAsForm'] = isset( $attrs['displayLoginAsForm'] ) ? $attrs['displayLoginAsForm'] : false;
				$item['redirectToCurrent']  = isset( $attrs['redirectToCurrent'] ) ? $attrs['redirectToCurrent'] : true;
				break;

			case 'core/search':
				$item['title'] = 'Search';
				$item['attrs'] = array(
					'buttonPosition' => isset( $attrs['buttonPosition'] ) ? $attrs['buttonPosition'] : 'button-outside',
					'buttonText'     => isset( $attrs['buttonText'] ) ? $attrs['buttonText'] : '',
					'buttonUseIcon'  => isset( $attrs['buttonUseIcon'] ) ? $attrs['buttonUseIcon'] : false,
					'showLabel'      => isset( $attrs['showLabel'] ) ? $attrs['showLabel'] : true,
					'placeholder'    => isset( $attrs['placeholder'] ) ? $attrs['placeholder'] : '',
				);
				break;

			case 'core/social-links':
				$item['title'] = 'Social Links';
				$item['attrs'] = array(
					'iconColor'                => isset( $attrs['iconColor'] ) ? $attrs['iconColor'] : '',
					'iconColorValue'           => isset( $attrs['iconColorValue'] ) ? $attrs['iconColorValue'] : '',
					'iconBackgroundColor'      => isset( $attrs['iconBackgroundColor'] ) ? $attrs['iconBackgroundColor'] : '',
					'iconBackgroundColorValue' => isset( $attrs['iconBackgroundColorValue'] ) ? $attrs['iconBackgroundColorValue'] : '',
					'size'                     => isset( $attrs['size'] ) ? $attrs['size'] : '',
				);
				break;

			case 'core/spacer':
				$item['title'] = 'Spacer';
				$item['attrs'] = array(
					'height' => isset( $attrs['height'] ) ? $attrs['height'] : '100px',
				);
				break;

			case 'core/icon':
				$item['title'] = 'Icon';
				$item['attrs'] = array(
					'icon' => isset( $attrs['icon'] ) ? $attrs['icon'] : '',
				);
				break;

			case 'core/site-title':
				$item['title'] = get_bloginfo( 'name' );
				$item['url']   = home_url( '/' );
				$item['attrs'] = array(
					'level'      => isset( $attrs['level'] ) ? $attrs['level'] : 1,
					'isLink'     => isset( $attrs['isLink'] ) ? $attrs['isLink'] : true,
					'linkTarget' => isset( $attrs['linkTarget'] ) ? $attrs['linkTarget'] : '_self',
				);
				break;

			case 'core/site-logo':
				$item['title'] = 'Site Logo';
				$item['attrs'] = array(
					'width'          => isset( $attrs['width'] ) ? $attrs['width'] : '',
					'isLink'         => isset( $attrs['isLink'] ) ? $attrs['isLink'] : true,
					'linkTarget'     => isset( $attrs['linkTarget'] ) ? $attrs['linkTarget'] : '_self',
					'shouldSyncIcon' => isset( $attrs['shouldSyncIcon'] ) ? $attrs['shouldSyncIcon'] : true,
				);
				break;

			case 'core/buttons':
				$item['title'] = 'Buttons';
				$item['attrs'] = array(
					'layout' => isset( $attrs['layout'] ) ? $attrs['layout'] : array(),
				);
				break;

			default:
				$item['title'] = $block_name;
				$item['attrs'] = $attrs;
				break;
		}

		// Add common attributes if present.
		if ( isset( $attrs['className'] ) && ! isset( $item['classes'] ) ) {
			$item['classes'] = array( $attrs['className'] );
		}

		return $item;
	}

	/**
	 * Add a new menu item (supports both traditional and FSE)
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function add_menu_item( $request ) {
		$menu_id = absint( $request['menu_id'] );
		$type    = isset( $request['type'] ) ? sanitize_text_field( $request['type'] ) : '';

		if ( $this->is_fse_theme() ) {
			return $this->add_fse_menu_item( $request, $menu_id, $type );
		} else {
			return $this->add_traditional_menu_item( $request, $menu_id, $type );
		}
	}

	/**
	 * Build block markup for FSE navigation blocks
	 *
	 * @param string          $block_type The block type to create.
	 * @param WP_REST_Request $request Full details about the request.
	 * @return array|WP_Error Array with 'markup' and 'title' keys, or WP_Error on failure.
	 */
	private function build_fse_block_markup( $block_type, $request ) {
		$attrs = array();
		$title = '';

		switch ( $block_type ) {
			case 'core/navigation-link':
			case 'core/navigation-submenu':
				$title   = sanitize_text_field( $request['title'] );
				$attrs[] = '"label":"' . esc_attr( $title ) . '"';

				$type = isset( $request['type'] ) ? sanitize_text_field( $request['type'] ) : 'custom';

				if ( 'custom' === $type ) {
					if ( empty( $request['url'] ) ) {
						return new \WP_Error( 'missing_url', __( 'URL is required for custom navigation links.', 'sg-ai-studio' ) );
					}
					$url     = esc_url_raw( $request['url'] );
					$attrs[] = '"url":"' . esc_url( $url ) . '"';
					$attrs[] = '"kind":"custom"';
				} elseif ( 'post_type' === $type ) {
					if ( empty( $request['object_id'] ) || empty( $request['object'] ) ) {
						return new \WP_Error( 'missing_object', __( 'object_id and object are required for post_type items.', 'sg-ai-studio' ) );
					}

					$object_id = absint( $request['object_id'] );
					$object    = sanitize_text_field( $request['object'] );

					$post = get_post( $object_id );
					if ( ! $post ) {
						return new \WP_Error( 'invalid_object_id', __( 'Invalid object_id.', 'sg-ai-studio' ) );
					}

					$url     = get_permalink( $object_id );
					$attrs[] = '"id":' . $object_id;
					$attrs[] = '"url":"' . esc_url( $url ) . '"';
					$attrs[] = '"kind":"post-type"';
					$attrs[] = '"type":"' . esc_attr( $object ) . '"';
				}

				if ( isset( $request['target'] ) && '_blank' === $request['target'] ) {
					$attrs[] = '"opensInNewTab":true';
				}
				break;

			case 'core/page-list':
				$title = 'Page List';
				if ( isset( $request['attrs']['parentPageID'] ) ) {
					$attrs[] = '"parentPageID":' . absint( $request['attrs']['parentPageID'] );
				}
				break;

			case 'core/home-link':
				$title   = isset( $request['title'] ) ? sanitize_text_field( $request['title'] ) : 'Home';
				$attrs[] = '"label":"' . esc_attr( $title ) . '"';
				break;

			case 'core/loginout':
				$title = 'Login/Logout';
				if ( isset( $request['attrs']['displayLoginAsForm'] ) && $request['attrs']['displayLoginAsForm'] ) {
					$attrs[] = '"displayLoginAsForm":true';
				}
				if ( isset( $request['attrs']['redirectToCurrent'] ) && ! $request['attrs']['redirectToCurrent'] ) {
					$attrs[] = '"redirectToCurrent":false';
				}
				break;

			case 'core/search':
				$title = 'Search';
				if ( isset( $request['attrs']['buttonPosition'] ) ) {
					$attrs[] = '"buttonPosition":"' . esc_attr( $request['attrs']['buttonPosition'] ) . '"';
				}
				if ( isset( $request['attrs']['buttonText'] ) ) {
					$attrs[] = '"buttonText":"' . esc_attr( $request['attrs']['buttonText'] ) . '"';
				}
				if ( isset( $request['attrs']['buttonUseIcon'] ) ) {
					$attrs[] = '"buttonUseIcon":' . ( $request['attrs']['buttonUseIcon'] ? 'true' : 'false' );
				}
				if ( isset( $request['attrs']['showLabel'] ) ) {
					$attrs[] = '"showLabel":' . ( $request['attrs']['showLabel'] ? 'true' : 'false' );
				}
				if ( isset( $request['attrs']['placeholder'] ) ) {
					$attrs[] = '"placeholder":"' . esc_attr( $request['attrs']['placeholder'] ) . '"';
				}
				break;

			case 'core/social-links':
				$title = 'Social Links';
				break;

			case 'core/spacer':
				$title = 'Spacer';
				if ( isset( $request['attrs']['height'] ) ) {
					$attrs[] = '"height":"' . esc_attr( $request['attrs']['height'] ) . '"';
				}
				break;

			case 'core/icon':
				$title = 'Icon';
				if ( isset( $request['attrs']['icon'] ) ) {
					$attrs[] = '"icon":"' . esc_attr( $request['attrs']['icon'] ) . '"';
				}
				break;

			case 'core/site-title':
				$title = get_bloginfo( 'name' );
				if ( isset( $request['attrs']['level'] ) ) {
					$attrs[] = '"level":' . absint( $request['attrs']['level'] );
				}
				if ( isset( $request['attrs']['isLink'] ) && ! $request['attrs']['isLink'] ) {
					$attrs[] = '"isLink":false';
				}
				if ( isset( $request['attrs']['linkTarget'] ) ) {
					$attrs[] = '"linkTarget":"' . esc_attr( $request['attrs']['linkTarget'] ) . '"';
				}
				break;

			case 'core/site-logo':
				$title = 'Site Logo';
				if ( isset( $request['attrs']['width'] ) ) {
					$attrs[] = '"width":' . absint( $request['attrs']['width'] );
				}
				if ( isset( $request['attrs']['isLink'] ) && ! $request['attrs']['isLink'] ) {
					$attrs[] = '"isLink":false';
				}
				if ( isset( $request['attrs']['linkTarget'] ) ) {
					$attrs[] = '"linkTarget":"' . esc_attr( $request['attrs']['linkTarget'] ) . '"';
				}
				break;

			case 'core/buttons':
				$title = 'Buttons';
				break;

			default:
				return new \WP_Error( 'invalid_block_type', __( 'Invalid block type.', 'sg-ai-studio' ) );
		}

		// Add common attributes.
		if ( isset( $request['classes'] ) && is_array( $request['classes'] ) ) {
			$class_str = esc_attr( implode( ' ', array_map( 'sanitize_html_class', $request['classes'] ) ) );
			$attrs[]   = '"className":"' . $class_str . '"';
		}

		// Build the block markup.
		$block_name = str_replace( 'core/', '', $block_type );
		if ( ! empty( $attrs ) ) {
			$attrs_str = ' {' . implode( ',', $attrs ) . '}';
		} else {
			$attrs_str = '';
		}

		$markup = '<!-- wp:' . $block_name . $attrs_str . ' /-->';

		return array(
			'markup' => $markup,
			'title'  => $title,
		);
	}

	/**
	 * Add menu item for FSE navigation
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param int             $menu_id Menu ID.
	 * @param string          $type Item type (for backward compatibility).
	 * @return WP_REST_Response Response object on success.
	 */
	private function add_fse_menu_item( $request, $menu_id, $type ) {
		$nav = get_post( $menu_id );

		if ( ! $nav || 'wp_navigation' !== $nav->post_type ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid navigation ID.', 'sg-ai-studio' ),
				),
				400
			);
		}

		// Get block type from request, default to navigation-link for backward compatibility.
		$block_type = isset( $request['block_type'] ) ? sanitize_text_field( $request['block_type'] ) : 'core/navigation-link';

		// Build the block markup.
		$block_data = $this->build_fse_block_markup( $block_type, $request );

		if ( is_wp_error( $block_data ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $block_data->get_error_message(),
				),
				400
			);
		}

		$new_block = $block_data['markup'];
		$title     = $block_data['title'];

		// wp_navigation posts store blocks directly in post_content
		// without a wrapping <!-- wp:navigation --> container.
		// Simply append the new block to the existing content.
		$content     = rtrim( $nav->post_content );
		$new_content = $content . "\n\n" . $new_block;

		$result = wp_update_post(
			array(
				'ID'           => $menu_id,
				'post_content' => $new_content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		/* translators: %1$s is the item title, %2$s is the navigation name. */
		Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'Navigation Item Added: %1$s to %2$s', 'sg-ai-studio' ), $title, $nav->post_title ) );

		// Clear all caches.
		if ( \function_exists( '\sg_cachepress_purge_cache' ) ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'title'      => $title,
					'block_type' => $block_type,
				),
			),
			201
		);
	}

	/**
	 * Add menu item for traditional navigation
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param int             $menu_id Menu ID.
	 * @param string          $type Item type.
	 * @return WP_REST_Response Response object on success.
	 */
	private function add_traditional_menu_item( $request, $menu_id, $type ) {
		$menu = wp_get_nav_menu_object( $menu_id );

		if ( ! $menu ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid menu ID.', 'sg-ai-studio' ),
				),
				400
			);
		}

		// Prepare item data.
		$item_data = array(
			'menu-item-title'  => sanitize_text_field( $request['title'] ),
			'menu-item-status' => 'publish',
		);

		// Handle custom link.
		if ( 'custom' === $type ) {
			$item_data['menu-item-type'] = 'custom';
			$item_data['menu-item-url']  = esc_url_raw( $request['url'] );
		} elseif ( 'post_type' === $type ) {
			// Handle post/page object.
			$object_id = absint( $request['object_id'] );
			$object    = sanitize_text_field( $request['object'] );

			if ( empty( $object_id ) || empty( $object ) ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'object_id and object are required for post_type items.', 'sg-ai-studio' ),
					),
					400
				);
			}

			$item_data['menu-item-type']      = 'post_type';
			$item_data['menu-item-object']    = $object;
			$item_data['menu-item-object-id'] = $object_id;
		} else {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid type. Must be "custom" or "post_type".', 'sg-ai-studio' ),
				),
				400
			);
		}

		// Optional parameters.
		if ( isset( $request['parent'] ) ) {
			$item_data['menu-item-parent-id'] = absint( $request['parent'] );
		}

		if ( isset( $request['menu_order'] ) ) {
			$item_data['menu-item-position'] = absint( $request['menu_order'] );
		}

		if ( isset( $request['target'] ) ) {
			$item_data['menu-item-target'] = sanitize_text_field( $request['target'] );
		}

		if ( isset( $request['classes'] ) && is_array( $request['classes'] ) ) {
			$item_data['menu-item-classes'] = implode( ' ', array_map( 'sanitize_html_class', $request['classes'] ) );
		}

		if ( isset( $request['description'] ) ) {
			$item_data['menu-item-description'] = sanitize_text_field( $request['description'] );
		}

		if ( isset( $request['attr_title'] ) ) {
			$item_data['menu-item-attr-title'] = sanitize_text_field( $request['attr_title'] );
		}

		if ( isset( $request['xfn'] ) ) {
			$item_data['menu-item-xfn'] = sanitize_text_field( $request['xfn'] );
		}

		// Add the menu item.
		$item_id = wp_update_nav_menu_item( $menu_id, 0, $item_data );

		if ( is_wp_error( $item_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $item_id->get_error_message(),
				),
				400
			);
		}

		// Get the created item.
		$item = wp_setup_nav_menu_item( get_post( $item_id ) );

		// Log the activity.
		/* translators: %1$s is the item title, %2$s is the menu name. */
		Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'Menu Item Added: %1$s to menu %2$s', 'sg-ai-studio' ), $item->title, $menu->name ) );

		// Clear all caches.
		if( \function_exists('\sg_cachepress_purge_cache') ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'id'          => $item->ID,
					'title'       => $item->title,
					'url'         => $item->url,
					'type'        => $item->type,
					'object'      => $item->object,
					'object_id'   => $item->object_id,
					'parent'      => $item->menu_item_parent,
					'menu_order'  => $item->menu_order,
					'target'      => $item->target,
					'attr_title'  => $item->attr_title,
					'description' => $item->description,
					'classes'     => $item->classes,
					'xfn'         => $item->xfn,
				),
			),
			201
		);
	}

	/**
	 * Delete a menu item
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function delete_menu_item( $request ) {
		// Check if powermode is enabled.
		if ( ! get_option( 'sg_ai_studio_powermode', false ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Powermode is disabled. This operation is not allowed.', 'sg-ai-studio' ),
				),
				412
			);
		}

		$item_id = absint( $request['id'] );

		if ( $this->is_fse_theme() ) {
			return $this->delete_fse_menu_item( $request, $item_id );
		}

		return $this->delete_traditional_menu_item( $item_id );
	}

	/**
	 * Delete a menu item from FSE navigation
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param int             $item_id Item ID or index.
	 * @return WP_REST_Response Response object on success.
	 */
	private function delete_fse_menu_item( $request, $item_id ) {
		$menu_id = isset( $request['menu_id'] ) ? absint( $request['menu_id'] ) : 0;

		if ( empty( $menu_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'menu_id is required for FSE themes.', 'sg-ai-studio' ),
				),
				400
			);
		}

		$nav = get_post( $menu_id );

		if ( ! $nav || 'wp_navigation' !== $nav->post_type ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid navigation ID.', 'sg-ai-studio' ),
				),
				400
			);
		}

		$content = $nav->post_content;
		$blocks  = parse_blocks( $content );

		// Find the navigation-link block to delete by matching its index or attrs.id.
		$block_index   = 0;
		$found         = false;
		$deleted_label = '';

		foreach ( $blocks as $key => $block ) {
			if ( 'core/navigation-link' !== $block['blockName'] && 'core/navigation-submenu' !== $block['blockName'] ) {
				continue;
			}

			$block_item_id = isset( $block['attrs']['id'] ) ? (int) $block['attrs']['id'] : $block_index;

			if ( $block_item_id === $item_id ) {
				$deleted_label = isset( $block['attrs']['label'] ) ? $block['attrs']['label'] : '';
				unset( $blocks[ $key ] );
				$found = true;
				break;
			}

			++$block_index;
		}

		if ( ! $found ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Menu item not found.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Rebuild the content from the remaining blocks.
		$new_content = '';
		foreach ( $blocks as $block ) {
			$new_content .= serialize_block( $block );
		}
		$new_content = trim( $new_content );

		$result = wp_update_post(
			array(
				'ID'           => $menu_id,
				'post_content' => $new_content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		/* translators: %s is the item title. */
		Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'Navigation Item Deleted: %s', 'sg-ai-studio' ), $deleted_label ) );

		// Clear all caches.
		if( \function_exists('\sg_cachepress_purge_cache') ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Menu item deleted successfully.', 'sg-ai-studio' ),
			),
			200
		);
	}

	/**
	 * Delete a traditional menu item
	 *
	 * @param int $item_id The menu item post ID.
	 * @return WP_REST_Response Response object on success.
	 */
	private function delete_traditional_menu_item( $item_id ) {
		$item = get_post( $item_id );

		if ( ! $item || 'nav_menu_item' !== $item->post_type ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid menu item ID.', 'sg-ai-studio' ),
				),
				404
			);
		}

		$item       = wp_setup_nav_menu_item( $item );
		$item_title = $item->title;

		// Delete the menu item.
		$result = wp_delete_post( $item_id, true );

		if ( ! $result ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to delete menu item.', 'sg-ai-studio' ),
				),
				500
			);
		}

		// Log the activity.
		/* translators: %s is the item title. */
		Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'Menu Item Deleted: %s', 'sg-ai-studio' ), $item_title ) );

		// Clear all caches.
		if( \function_exists('\sg_cachepress_purge_cache') ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Menu item deleted successfully.', 'sg-ai-studio' ),
			),
			200
		);
	}

	/**
	 * Update a menu item
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function update_menu_item( $request ) {
		$item_id = absint( $request['id'] );

		if ( $this->is_fse_theme() ) {
			return $this->update_fse_menu_item( $request, $item_id );
		}

		return $this->update_traditional_menu_item( $request, $item_id );
	}

	/**
	 * Update menu item for FSE navigation
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param int             $item_id Item ID or index.
	 * @return WP_REST_Response Response object on success.
	 */
	private function update_fse_menu_item( $request, $item_id ) {
		$menu_id = isset( $request['menu_id'] ) ? absint( $request['menu_id'] ) : 0;

		if ( empty( $menu_id ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'menu_id is required for FSE themes.', 'sg-ai-studio' ),
				),
				400
			);
		}

		$nav = get_post( $menu_id );

		if ( ! $nav || 'wp_navigation' !== $nav->post_type ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid navigation ID.', 'sg-ai-studio' ),
				),
				400
			);
		}

		$content = $nav->post_content;
		$blocks  = parse_blocks( $content );

		// Find and update the navigation block.
		$block_index   = 0;
		$found         = false;
		$found_key     = null;
		$updated_label = '';
		$target_order  = null;

		// First pass: find the block and update its attributes.
		foreach ( $blocks as $key => &$block ) {
			if ( 'core/navigation-link' !== $block['blockName'] && 'core/navigation-submenu' !== $block['blockName'] ) {
				continue;
			}

			$block_item_id = isset( $block['attrs']['id'] ) ? (int) $block['attrs']['id'] : $block_index;

			if ( $block_item_id === $item_id ) {
				// Update the block attributes (preserves existing attrs not in request).
				if ( isset( $request['title'] ) ) {
					$block['attrs']['label'] = sanitize_text_field( $request['title'] );
				}

				if ( isset( $request['url'] ) ) {
					$block['attrs']['url'] = esc_url_raw( $request['url'] );
				}

				if ( isset( $request['target'] ) && '_blank' === $request['target'] ) {
					$block['attrs']['opensInNewTab'] = true;
				} elseif ( isset( $request['target'] ) && '' === $request['target'] ) {
					unset( $block['attrs']['opensInNewTab'] );
				}

				if ( isset( $request['classes'] ) && is_array( $request['classes'] ) ) {
					$class_str                   = esc_attr( implode( ' ', array_map( 'sanitize_html_class', $request['classes'] ) ) );
					$block['attrs']['className'] = $class_str;
				}

				// Get label for activity log.
				$updated_label = isset( $block['attrs']['label'] ) ? $block['attrs']['label'] : '';

				// Check if we need to reorder.
				if ( isset( $request['menu_order'] ) ) {
					$target_order = absint( $request['menu_order'] );
				}

				$found     = true;
				$found_key = $key;
				break;
			}

			++$block_index;
		}
		unset( $block );

		if ( ! $found ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Menu item not found.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Handle reordering if menu_order is provided.
		if ( null !== $target_order && $target_order !== $found_key ) {
			// Extract only navigation blocks for reordering.
			$nav_blocks   = array();
			$other_blocks = array();

			foreach ( $blocks as $key => $block ) {
				if ( 'core/navigation-link' === $block['blockName'] || 'core/navigation-submenu' === $block['blockName'] ) {
					$nav_blocks[] = $block;
				} else {
					$other_blocks[] = $block;
				}
			}

			// Find the block to move in nav_blocks.
			$current_position = 0;
			$block_to_move    = null;
			foreach ( $nav_blocks as $idx => $nav_block ) {
				$nav_item_id = isset( $nav_block['attrs']['id'] ) ? (int) $nav_block['attrs']['id'] : $idx;
				if ( $nav_item_id === $item_id ) {
					$current_position = $idx;
					$block_to_move    = $nav_block;
					break;
				}
			}

			// Remove from current position.
			if ( null !== $block_to_move ) {
				array_splice( $nav_blocks, $current_position, 1 );

				// Insert at new position.
				$new_position = min( $target_order, count( $nav_blocks ) );
				array_splice( $nav_blocks, $new_position, 0, array( $block_to_move ) );
			}

			// Merge back (for now, just use nav_blocks as we typically only have nav blocks in wp_navigation).
			$blocks = $nav_blocks;
		}

		// Rebuild the content from the updated blocks.
		$new_content = '';
		foreach ( $blocks as $block ) {
			$new_content .= serialize_block( $block );
		}
		$new_content = trim( $new_content );

		$result = wp_update_post(
			array(
				'ID'           => $menu_id,
				'post_content' => $new_content,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		/* translators: %s is the item title. */
		Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'Navigation Item Updated: %s', 'sg-ai-studio' ), $updated_label ) );

		// Clear all caches.
		if( \function_exists('\sg_cachepress_purge_cache') ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Menu item updated successfully.', 'sg-ai-studio' ),
			),
			200
		);
	}

	/**
	 * Update a traditional menu item
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @param int             $item_id The menu item post ID.
	 * @return WP_REST_Response Response object on success.
	 */
	private function update_traditional_menu_item( $request, $item_id ) {
		$item = get_post( $item_id );

		if ( ! $item || 'nav_menu_item' !== $item->post_type ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid menu item ID.', 'sg-ai-studio' ),
				),
				404
			);
		}

		// Get current item data.
		$item = wp_setup_nav_menu_item( $item );

		// Get the menu this item belongs to.
		$menus = wp_get_object_terms( $item_id, 'nav_menu' );
		if ( empty( $menus ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Menu item is not associated with any menu.', 'sg-ai-studio' ),
				),
				400
			);
		}
		$menu_id = $menus[0]->term_id;

		// Start with existing data to preserve all fields.
		$item_data = array(
			'menu-item-title'       => $item->title,
			'menu-item-url'         => $item->url,
			'menu-item-type'        => $item->type,
			'menu-item-object'      => $item->object,
			'menu-item-object-id'   => $item->object_id,
			'menu-item-parent-id'   => $item->menu_item_parent,
			'menu-item-position'    => $item->menu_order,
			'menu-item-target'      => $item->target,
			'menu-item-classes'     => is_array( $item->classes ) ? implode( ' ', $item->classes ) : $item->classes,
			'menu-item-description' => $item->description,
			'menu-item-attr-title'  => $item->attr_title,
			'menu-item-xfn'         => $item->xfn,
			'menu-item-status'      => 'publish',
		);

		// Override with provided values.
		if ( isset( $request['title'] ) ) {
			$item_data['menu-item-title'] = sanitize_text_field( $request['title'] );
		}

		if ( isset( $request['url'] ) ) {
			$item_data['menu-item-url'] = esc_url_raw( $request['url'] );
		}

		if ( isset( $request['parent'] ) ) {
			$item_data['menu-item-parent-id'] = absint( $request['parent'] );
		}

		if ( isset( $request['menu_order'] ) ) {
			$item_data['menu-item-position'] = absint( $request['menu_order'] );
		}

		if ( isset( $request['target'] ) ) {
			$item_data['menu-item-target'] = sanitize_text_field( $request['target'] );
		}

		if ( isset( $request['classes'] ) && is_array( $request['classes'] ) ) {
			$item_data['menu-item-classes'] = implode( ' ', array_map( 'sanitize_html_class', $request['classes'] ) );
		}

		if ( isset( $request['description'] ) ) {
			$item_data['menu-item-description'] = sanitize_text_field( $request['description'] );
		}

		if ( isset( $request['attr_title'] ) ) {
			$item_data['menu-item-attr-title'] = sanitize_text_field( $request['attr_title'] );
		}

		if ( isset( $request['xfn'] ) ) {
			$item_data['menu-item-xfn'] = sanitize_text_field( $request['xfn'] );
		}

		// Update the menu item.
		$result = wp_update_nav_menu_item( $menu_id, $item_id, $item_data );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				500
			);
		}

		// Get the updated item.
		$updated_item = wp_setup_nav_menu_item( get_post( $item_id ) );

		// Log the activity.
		/* translators: %s is the item title. */
		Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'Menu Item Updated: %s', 'sg-ai-studio' ), $updated_item->title ) );

		// Clear all caches.
		if( \function_exists('\sg_cachepress_purge_cache') ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Menu item updated successfully.', 'sg-ai-studio' ),
				'data'    => array(
					'id'          => $updated_item->ID,
					'title'       => $updated_item->title,
					'url'         => $updated_item->url,
					'type'        => $updated_item->type,
					'object'      => $updated_item->object,
					'object_id'   => $updated_item->object_id,
					'parent'      => $updated_item->menu_item_parent,
					'menu_order'  => $updated_item->menu_order,
					'target'      => $updated_item->target,
					'attr_title'  => $updated_item->attr_title,
					'description' => $updated_item->description,
					'classes'     => $updated_item->classes,
					'xfn'         => $updated_item->xfn,
				),
			),
			200
		);
	}

	// ========== MENU LOCATIONS METHODS ==========.

	/**
	 * Get all menu locations
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function get_menu_locations( $request ) {
		$locations      = get_registered_nav_menus();
		$assigned_menus = get_nav_menu_locations();
		$data           = array();

		foreach ( $locations as $location => $description ) {
			$menu_id = isset( $assigned_menus[ $location ] ) ? $assigned_menus[ $location ] : null;

			$data[] = array(
				'location'    => $location,
				'description' => $description,
				'menu_id'     => $menu_id,
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			200
		);
	}

	/**
	 * Assign a menu to a location
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response Response object on success.
	 */
	public function assign_menu_location( $request ) {
		$location = sanitize_text_field( $request['location'] );
		$menu_id  = absint( $request['menu_id'] );

		// Validate location exists.
		$locations = get_registered_nav_menus();
		if ( ! isset( $locations[ $location ] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid menu location.', 'sg-ai-studio' ),
				),
				400
			);
		}

		// Validate menu exists (works for both traditional and FSE).
		if ( $this->is_fse_theme() ) {
			$nav = get_post( $menu_id );
			if ( ! $nav || 'wp_navigation' !== $nav->post_type ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Invalid navigation ID.', 'sg-ai-studio' ),
					),
					400
				);
			}
			$menu_name = $nav->post_title;
		} else {
			$menu = wp_get_nav_menu_object( $menu_id );
			if ( ! $menu ) {
				return new WP_REST_Response(
					array(
						'success' => false,
						'message' => __( 'Invalid menu ID.', 'sg-ai-studio' ),
					),
					400
				);
			}
			$menu_name = $menu->name;
		}

		// Get current menu locations.
		$menu_locations = get_nav_menu_locations();

		// Assign the menu to the location.
		$menu_locations[ $location ] = $menu_id;

		// Save the updated locations.
		set_theme_mod( 'nav_menu_locations', $menu_locations );

		// Log the activity.
		/* translators: %1$s is the menu name, %2$s is the location. */
		Activity_Log_Helper::add_log_entry( 'Menus', sprintf( __( 'Menu Assigned: %1$s to location %2$s', 'sg-ai-studio' ), $menu_name, $location ) );

		// Clear all caches.
		if( \function_exists('\sg_cachepress_purge_cache') ) {
			\sg_cachepress_purge_cache();
			\wp_cache_flush();
		} else {
			\wp_cache_flush();
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Menu assigned to location successfully.', 'sg-ai-studio' ),
				'data'    => array(
					'location' => $location,
					'menu_id'  => $menu_id,
				),
			),
			200
		);
	}

	// ========== ARGUMENT DEFINITIONS ==========.

	/**
	 * Get arguments for creating a menu
	 *
	 * @return array
	 */
	protected function get_create_menu_args() {
		return array(
			'name' => array(
				'description' => 'The name for the menu.',
				'type'        => 'string',
				'required'    => true,
			),
		);
	}

	/**
	 * Get arguments for adding a menu item
	 *
	 * @return array
	 */
	protected function get_add_menu_item_args() {
		return array(
			'menu_id'     => array(
				'description' => 'The ID of the menu to add the item to.',
				'type'        => 'integer',
				'required'    => true,
			),
			'title'       => array(
				'description' => 'The title of the menu item.',
				'type'        => 'string',
				'required'    => false,
			),
			'block_type'  => array(
				'description' => 'The block type to create (FSE themes only). Defaults to core/navigation-link.',
				'type'        => 'string',
				'enum'        => array(
					'core/navigation-link',
					'core/navigation-submenu',
					'core/page-list',
					'core/home-link',
					'core/loginout',
					'core/search',
					'core/social-links',
					'core/spacer',
					'core/icon',
					'core/site-title',
					'core/site-logo',
					'core/buttons',
				),
				'default'     => 'core/navigation-link',
				'required'    => false,
			),
			'type'        => array(
				'description' => 'The type of menu item (custom or post_type). Only for navigation-link blocks.',
				'type'        => 'string',
				'enum'        => array( 'custom', 'post_type' ),
				'required'    => false,
			),
			'url'         => array(
				'description' => 'The URL for custom link items.',
				'type'        => 'string',
				'required'    => false,
			),
			'object_id'   => array(
				'description' => 'The ID of the post/page object for post_type items.',
				'type'        => 'integer',
				'required'    => false,
			),
			'object'      => array(
				'description' => 'The object type (page, post, etc.) for post_type items.',
				'type'        => 'string',
				'required'    => false,
			),
			'parent'      => array(
				'description' => 'The parent menu item ID.',
				'type'        => 'integer',
				'required'    => false,
			),
			'menu_order'  => array(
				'description' => 'The order position of the menu item.',
				'type'        => 'integer',
				'required'    => false,
			),
			'target'      => array(
				'description' => 'The link target attribute.',
				'type'        => 'string',
				'required'    => false,
			),
			'classes'     => array(
				'description' => 'CSS classes for the menu item.',
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
				),
				'required'    => false,
			),
			'description' => array(
				'description' => 'The description of the menu item.',
				'type'        => 'string',
				'required'    => false,
			),
			'attr_title'  => array(
				'description' => 'The title attribute of the link.',
				'type'        => 'string',
				'required'    => false,
			),
			'xfn'         => array(
				'description' => 'The XFN relationship.',
				'type'        => 'string',
				'required'    => false,
			),
			'attrs'       => array(
				'description' => 'Block-specific attributes (JSON object). For core/page-list: {parentPageID}, for core/search: {buttonPosition, buttonText, etc.}',
				'type'        => 'object',
				'required'    => false,
			),
		);
	}

	/**
	 * Get arguments for assigning a menu to a location
	 *
	 * @return array
	 */
	protected function get_assign_location_args() {
		return array(
			'location' => array(
				'description' => 'The theme location identifier.',
				'type'        => 'string',
				'required'    => true,
			),
			'menu_id'  => array(
				'description' => 'The ID of the menu to assign.',
				'type'        => 'integer',
				'required'    => true,
			),
		);
	}

	/**
	 * Get arguments for updating a menu item
	 *
	 * @return array
	 */
	protected function get_update_menu_item_args() {
		return array(
			'id'          => array(
				'description' => 'Unique identifier for the menu item.',
				'type'        => 'integer',
				'required'    => true,
			),
			'menu_id'     => array(
				'description' => 'The navigation post ID (required for FSE themes).',
				'type'        => 'integer',
				'required'    => false,
			),
			'title'       => array(
				'description' => 'The title of the menu item.',
				'type'        => 'string',
				'required'    => false,
			),
			'url'         => array(
				'description' => 'The URL for the menu item.',
				'type'        => 'string',
				'required'    => false,
			),
			'parent'      => array(
				'description' => 'The parent menu item ID.',
				'type'        => 'integer',
				'required'    => false,
			),
			'menu_order'  => array(
				'description' => 'The order position of the menu item.',
				'type'        => 'integer',
				'required'    => false,
			),
			'target'      => array(
				'description' => 'The link target attribute (_blank, _self, etc.).',
				'type'        => 'string',
				'required'    => false,
			),
			'classes'     => array(
				'description' => 'CSS classes for the menu item.',
				'type'        => 'array',
				'items'       => array(
					'type' => 'string',
				),
				'required'    => false,
			),
			'description' => array(
				'description' => 'The description of the menu item (traditional menus only).',
				'type'        => 'string',
				'required'    => false,
			),
			'attr_title'  => array(
				'description' => 'The title attribute of the link (traditional menus only).',
				'type'        => 'string',
				'required'    => false,
			),
			'xfn'         => array(
				'description' => 'The XFN relationship (traditional menus only).',
				'type'        => 'string',
				'required'    => false,
			),
		);
	}
}
