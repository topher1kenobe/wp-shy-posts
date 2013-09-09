<?php
/*
Plugin Name: Shy Posts
Plugin URI: http://codeventure.net
Description: Provides a mechanism for preventing posts from being rendered on the homepage loop
Author: Topher
Version: 1.2
Author URI: http://codeventure.net
*/

/**
 * Provides a mechanism for preventing posts from being rendered on the homepage loop
 *
 * @package Shy_Posts
 * @since Shy_Posts 1.0
 * @author Topher
 */


/**
 * Instantiate the Shy_Posts object
 * @since Shy_Posts 1.0
 */
return new Shy_Posts_New();

/**
 * Main Shy Posts Class
 *
 * Contains the main functions for the admin side of Shy Posts
 *
 * @class Shy_Posts
 * @version 1.0.0
 * @since 1.0
 * @package Shy_Posts
 * @author Topher
 */
class Shy_Posts_New {

	/**
	 * Shy_Posts Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {

		// only do this in the admin area
		if ( is_admin() ) {
			add_action( 'save_post', array( $this, 'save' ) );
			add_action( 'post_submitbox_misc_actions', array( $this, 'option_hide_in_publish' ) );
		}

		// only do this NOT in the admin area
		if ( !is_admin() ) {
			// filter the homepage loop
			add_action( 'pre_get_posts', array( $this, 'exclude_shy_posts_from_homepage' ) );
		}
	}

	/**
	 * Places checkbox in the Publish meta box
	 *
	 * @access public
	 * @return void
	 */
	public function option_hide_in_publish() {

		global $post;

		// Use nonce for verification
		wp_nonce_field( plugin_basename( __FILE__ ), 'shyposts_nonce' );

		// The actual fields for data entry
		// Get the data
		$value = get_post_meta( $post->ID, 'shy_post', true );

		// get the value of the option we want
		$checked = $value['shy_post'];

		// echo the meta box
		echo '<div class="misc-pub-section misc-pub-section-last">';
		echo '<input type="checkbox" id="shyposts_hide_field" name="shyposts_hide_field" value="1" ' . checked( $checked, true, false ) . '" title="' . esc_attr__('Removes this post from the homepage, but NOT from any other page', 'shyposts') . '"> ';
		echo '<label for="shyposts_hide_field" title="' . esc_attr__('Removes this post from the homepage, but NOT from any other page', 'shyposts') . '">';
		echo __( 'Hide on the homepage?', 'shyposts');
		echo '</label> ';
		echo '</div>';
	}


	/**
	 * Updates the options table with the form data
	 *
	 * @access public
	 * @param int $post_id
	 * @return void
	 */
	public function save( $post_id ) {

		// Check if the current user is authorised to do this action. 
		$post_type = get_post_type_object( get_post( $post_id )->post_type );
		if ( ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
			return;
		}

		// Check if the user intended to change this value.
		if ( ! isset( $_POST['shyposts_nonce'] ) || ! wp_verify_nonce( $_POST['shyposts_nonce'], plugin_basename( __FILE__ ) ) )
			return;

		// Sanitize user input
		$shydata = sanitize_text_field( $_POST['shyposts_hide_field'] );

		// Update or create the key/value
		update_post_meta( $post_id, 'shy_post', $shydata );
	}

	/**
	 * Filter posts on homepage
	 *
	 * @access public
	 * @param mixed $query
	 * @return void
	 */
	public function exclude_shy_posts_from_homepage( $query ) {
		// make sure we're looking in the right place, !is_admin is a safety net
		if ( is_front_page() AND $query->is_main_query() ) {
			// set a meta query to exclude posts that have the shy_posts val set to 1

			$shy_meta_query = array(
				array(
					'key' => 'shy_post',
					'value' => '1',
					'compare' => '!='
				),
				'relation' => 'OR',
				array(
					'key' => 'shy_post',
					'value' => '1',
					'compare' => 'NOT EXISTS'
				),
			);

			$outer_query = $query->get('meta_query');

			if(is_array($query->get('meta_query'))) {
				$meta_query = array_merge($query->get('meta_query'), $shy_meta_query);
			} else {
				$meta_query = $shy_meta_query;
			}

			$query->set('meta_query', $meta_query);
		}
	}

// end class
}
