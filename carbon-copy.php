<?php

/**
 * -----------------------------------------------------------------------------
 * Plugin Name: Carbon Copy
 * Description: One-click duplication of ClassicPress posts, pages, and custom post types. Copies taxonomy data and meta data, too!
 * Version: 0.1.0
 * Author: Code Potent
 * Author URI: https://codepotent.com
 * Plugin URI: https://github.com/johnalarcon/carbon-copy
 * Text Domain: carbon-copy
 * Domain Path: /languages
 * -----------------------------------------------------------------------------
 * This is free software released under the terms of the General Public License,
 * version 2, or later. It is distributed WITHOUT ANY WARRANTY; without even the
 * implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. Full
 * text of the license is available at https://www.gnu.org/licenses/gpl-2.0.txt.
 * -----------------------------------------------------------------------------
 * Copyright © 2019 - CodePotent
 * -----------------------------------------------------------------------------
 *           ____          _      ____       _             _
 *          / ___|___   __| | ___|  _ \ ___ | |_ ___ _ __ | |_
 *         | |   / _ \ / _` |/ _ \ |_) / _ \| __/ _ \ '_ \| __|
 *         | |__| (_) | (_| |  __/  __/ (_) | ||  __/ | | | |_
 *          \____\___/ \__,_|\___|_|   \___/ \__\___|_| |_|\__|.com
 *
 * -----------------------------------------------------------------------------
 */

// Declare the namespace.
namespace CodePotent\CarbonCopy;

// Prevent direct access.
if (!defined('ABSPATH')) {
	die();
}

/**
 * Carbon Copy
 *
 * A class to insert a "Copy" link in the admin rows of posts, pages, and custom
 * post types. When clicked, the item is duplicated along with its taxonomy data
 * and any meta data associated with it.
 *
 * @author John Alarcon
 *
 */
class CarbonCopy {
	
	/**
	 * If a lightweight constructor steps on your foot, you won't even feel it.
	 *
	 * @author John Alarcon
	 *
	 * @since 0.1.0
	 *
	 */
	public function __construct() {
		
		// Initialize the plugin.
		$this->init();
		
	}
	
	/**
	 * Initialize the plugin.
	 *
	 * This method adds actions and filters to hook the plugin into the system.
	 *
	 * @author John Alarcon
	 *
	 * @since 0.1.0
	 *
	 */
	public function init() {
		
		// Hook the duplication method into the admin request process.
		add_action('admin_action_carbon_copy', [$this, 'carbon_copy']);
	
		// Inject duplication link into non-hierarchical item rows (post, CPT).
		add_filter('post_row_actions', [$this, 'action_link'], 10, 2);
		
		// Inject duplication link into hierarchical item rows (page, CPT).
		add_filter('page_row_actions', [$this, 'action_link'], 10, 2);
	
	}
	
	/**
	 * Inject duplication link.
	 *
	 * This method injects the copy link into the admin rows.
	 *
	 * @author John Alarcon
	 *
	 * @since 0.1.0
	 *
	 * @param array $actions Actions currently associated with the item type.
	 * @param array $post The current post object.
	 * @return array Possibly-amended array of actions.
	 */
	public function action_link($actions, $post) {
		
		// If user can't edit posts, no link for you!
		if (!current_user_can('edit_posts')) {
			$actions;
		}
		
		// Add the duplication link to the $actions array.
		$actions['duplicate'] = sprintf(
			'<a href="%s" title="%s" rel="permalink">%s</a>',
			'admin.php?action=carbon_copy&amp;post='.$post->ID,
			__('Copy Item', 'carbon-copy'),
			__('Copy', 'carbon-copy')
			);
		
		// Return the amended actions array.
		return $actions;
	
	}
		
	/**
	 * Copy an item.
	 *
	 * This method duplicates a page, post, or custom post type. The duplication
	 * process also covers any taxonomy and meta data.
	 *
	 * @author John Alarcon
	 *
	 * @since 0.1.0
	 *
	 */
	public function carbon_copy(){
		
		// If user can't edit items, send'em off into the weeds.
		if (!current_user_can('edit_posts')) {
			wp_die(__('A higher level of permission is required to perform this action.', 'carbon-copy'));
		}
		
		// If no item id is present, bail.
		if (!isset($_REQUEST['post'])) {
			wp_die(__('Item could not be duplicated.', 'carbon-copy'));
		}
		
		// Extract the item id from the request.
		$source_id = (isset($_GET['post']) ? (int)$_GET['post'] : (int)$_POST['post']);
		
		// Get source item.
		$source = get_post($source_id);
		
		// If source item wasn't retrieved, bail.
		if (!is_object($source)) {
			wp_die(__('Source item could not be found.', 'carbon-copy'));
		}
		
		// Copy item and retrieve id of new item.
		$target_id = $this->copy_item($source);
		
		// Copy any taxonomy data over to the target id.
		$this->copy_taxonomies($source, $target_id);
		
		// Copy any meta data over to the target id.
		$this->copy_meta($source, $target_id);
		
		// Redirect user to the new item's edit screen.
		wp_redirect(admin_url('post.php?action=edit&post='.$target_id));
		
		// Exit stage left.
		exit;
		
	}
	
	/**
	 * Copy source item.
	 *
	 * This method duplicates the given post/page/cpt and returns the new item's
	 * id number.
	 *
	 * @author John Alarcon
	 *
	 * @since 0.1.0
	 *
	 * @param object $source A post, page, or custom post type item.
	 * @return int Id of the newly created item.
	 */
	private function copy_item($source) {
		
		// Array of arguments for the new item.
		$target = array(
				'post_author'    => $source->post_author,
				'post_content'   => $source->post_content,
				'post_excerpt'   => $source->post_excerpt,
				'post_name'      => $source->post_name,
				'post_parent'    => $source->post_parent,
				'post_password'  => $source->post_password,
				'post_status'    => 'draft',
				'post_title'     => $source->post_title,
				'post_type'      => $source->post_type,
				'comment_status' => $source->comment_status,
				'ping_status'    => $source->ping_status,
				'to_ping'        => $source->to_ping,
				'menu_order'     => $source->menu_order
		);
		
		// Insert new item.
		$target_id = wp_insert_post($target);
		
		// Return the new item's id.
		return $target_id;
		
	}
	
	/**
	 * Copy source item's taxonomy data.
	 *
	 * The method duplicates any existing taxonomy data associated with the item
	 * being copied.
	 *
	 * @author John Alarcon
	 *
	 * @since 0.1.0
	 *
	 * @param object $source A post, page, or custom post type item.
	 * @param int $target_id Id of new item to which taxonomy is associated.
	 * @return void
	 */
	private function copy_taxonomies($source, $target_id) {
		
		// Retrieve any taxonomies associated with the source item.
		$taxonomies = get_object_taxonomies($source->post_type);
		
		// Iterate over any found taxonomies.
		foreach ($taxonomies as $taxonomy) {
			
			// Get the slugs of the terms associated with current taxonomy.
			$terms = wp_get_object_terms($source->ID, $taxonomy, array('fields'=>'slugs'));
			
			// Copy the source term slugs to the target item.
			wp_set_object_terms($target_id, $terms, $taxonomy, false);
			
		}
		
	}
	
	/**
	 * Copy source item's metadata.
	 *
	 * This method duplicates existing metadata associated with the item that is
	 * being copied.
	 *
	 * @author John Alarcon
	 *
	 * @since 0.1.0
	 *
	 * @param object $source A post, page, or custom post type item.
	 * @param int $target_id Id of new item to which metadata is associated.
	 * @return void
	 */
	private function copy_meta($source, $target_id) {

		// Retrieve meta data associated with the source item.
		$metadata = get_post_custom($source->ID);
		
		// Iterate and duplicate any found meta data. Note: make more efficient.
		foreach ($metadata as $key=>$values) {
			foreach ($values as $value) {
				add_post_meta($target_id, $key, $value);
			}
		}
		
	}
	
}

// Dish up the awesomesauce!
new CarbonCopy;