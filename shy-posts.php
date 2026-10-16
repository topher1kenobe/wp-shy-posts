<?php
/**
 * Plugin Name: Shy Posts
 * Plugin URI: http://codeventure.net
 * Description: Provides a mechanism for preventing posts from being rendered on the homepage loop
 * Author: Topher
 * Version: 2.0.0
 * Author URI: http://codeventure.net
 * Text Domain: shy-posts
 * License: GPLv2 or later
 *
 * @package Shy_Posts
 * @since Shy_Posts 1.0
 * @author Topher
 */

// The main plugin file can't be renamed to class-shy-posts.php without deactivating the plugin on existing sites.
// phpcs:disable WordPress.Files.FileName

/**
 * Build the list of shy post IDs on activation.
 * This must be registered when the file loads, not on plugins_loaded, or it never fires.
 *
 * @since Shy_Posts 2.0
 */
register_activation_hook( __FILE__, array( 'Shy_Posts', 'install' ) );

/**
 * Instantiate the Shy_Posts instance
 *
 * @since Shy_Posts 1.0
 */
add_action( 'plugins_loaded', array( 'Shy_Posts', 'instance' ) );

/**
 * Main Shy Posts Class
 *
 * Contains the main functions for the admin side of Shy Posts
 *
 * @class Shy_Posts
 * @version 2.0.0
 * @since 1.0
 * @package Shy_Posts
 * @author Topher
 */
class Shy_Posts {

	/**
	 * Holds array of post IDs that are shy. NULL until loaded.
	 *
	 * @access private
	 * @since 1.2
	 * @var array|null
	 */
	private $shy_post_ids = null;

	/**
	 * Option name the shy post IDs are stored in
	 *
	 * @since 2.0
	 * @var string
	 */
	const OPTION_NAME = 'shy_posts_ids';

	/**
	 * Transient name used by versions before 2.0, deleted on rebuild
	 *
	 * @since 2.0
	 * @var string
	 */
	const LEGACY_TRANSIENT_NAME = 'shy_posts_transient';

	/**
	 * Post meta key that marks a post as shy
	 *
	 * @since 2.0
	 * @var string
	 */
	const META_KEY = 'shy_post';

	/**
	 * Plugin version, used to bust the editor script cache
	 *
	 * @since 2.0
	 * @var string
	 */
	const VERSION = '2.0.0';

	/**
	 * Instance handle
	 *
	 * @static
	 * @since 1.2
	 * @var Shy_Posts
	 */
	private static $instance = null;

	/**
	 * Shy_Posts Constructor, actually contains nothing
	 *
	 * @access private
	 * @return void
	 */
	private function __construct() {}

	/**
	 * Instance initiator, runs setup etc.
	 *
	 * @access public
	 * @return self
	 */
	public static function instance() {
		if ( ! is_a( self::$instance, __CLASS__ ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Runs things that would normally be in __construct
	 *
	 * @access private
	 * @return void
	 */
	private function setup() {
		// The meta is exposed to the REST API for the block editor, so register it everywhere.
		add_action( 'init', array( $this, 'register_meta' ) );

		// Keep the stored list in sync however the meta changes: classic editor, block editor (REST), or post deletion.
		add_action( 'added_post_meta', array( $this, 'meta_changed' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'meta_changed' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'meta_deleted' ), 10, 3 );

		// Only do this in the admin area.
		if ( is_admin() ) {
			add_action( 'save_post', array( $this, 'save' ) );
			add_action( 'post_submitbox_misc_actions', array( $this, 'option_hide_in_publish' ) );
			add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );
			add_filter( 'is_protected_meta', array( $this, 'hide_field' ), 10, 2 );
			add_action( 'init', array( $this, 'load_textdomain' ) );
		}

		// Only do this NOT in the admin area.
		if ( ! is_admin() ) {
			// Filter the homepage loop.
			add_action( 'pre_get_posts', array( $this, 'exclude_shy_posts_from_homepage' ) );
		}
	}

	/**
	 * On activation, rebuild the stored list of shy post IDs from post meta
	 *
	 * @static
	 * @return void
	 */
	public static function install() {
		self::rebuild_shy_post_ids();
	}

	/**
	 * Query post meta for every shy post and store the IDs in the option.
	 * Post meta is the source of truth; the option is just a fast lookup.
	 *
	 * @static
	 * @since 2.0
	 * @return array
	 */
	public static function rebuild_shy_post_ids() {

		$args = array(
			'post_type'     => get_post_types(),
			'post_status'   => 'any',
			'no_found_rows' => true,
			'nopaging'      => true,
			'fields'        => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Only runs on activation or when the stored list is missing.
			'meta_query'    => array(
				array(
					'key'     => self::META_KEY,
					'value'   => '1',
					'compare' => '=',
				),
			),
		);

		$shy_post_ids = array_map( 'intval', get_posts( $args ) );

		update_option( self::OPTION_NAME, $shy_post_ids );
		delete_transient( self::LEGACY_TRANSIENT_NAME );

		return $shy_post_ids;
	}

	/**
	 * Load plugin textdomain.
	 *
	 * @access public
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'shy-posts', false, plugin_basename( __DIR__ ) );
	}

	/**
	 * Filter the post meta field so it doesn't appear in the Custom Fields list
	 *
	 * @access public
	 * @param bool   $is_protected Whether the meta key is protected.
	 * @param string $meta_key     Meta key being checked.
	 * @return bool
	 */
	public function hide_field( $is_protected, $meta_key ) {
		if ( self::META_KEY === $meta_key ) {
			$is_protected = true;
		}
		return $is_protected;
	}

	/**
	 * Get shy post IDs, rebuilding the stored list if it's missing
	 *
	 * @access public
	 * @return array
	 */
	public function get_shy_post_ids() {

		if ( is_array( $this->shy_post_ids ) ) {
			return $this->shy_post_ids;
		}

		$shy_post_ids = get_option( self::OPTION_NAME, false );

		// Missing (fresh install, or upgrading from the transient) so build it from post meta.
		if ( ! is_array( $shy_post_ids ) ) {
			$shy_post_ids = self::rebuild_shy_post_ids();
		}

		$this->shy_post_ids = $shy_post_ids;

		return $this->shy_post_ids;
	}

	/**
	 * Add or remove a post ID from the stored list
	 *
	 * @access private
	 * @param int  $post_id Post to add or remove.
	 * @param bool $is_shy  True to add the post, false to remove it.
	 * @return void
	 */
	private function update_shy_post_ids( $post_id, $is_shy ) {

		$post_id      = (int) $post_id;
		$shy_post_ids = $this->get_shy_post_ids();

		if ( $is_shy ) {
			$shy_post_ids[] = $post_id;
		} else {
			$shy_post_ids = array_diff( $shy_post_ids, array( $post_id ) );
		}

		$this->shy_post_ids = array_values( array_unique( array_map( 'intval', $shy_post_ids ) ) );

		// Save it back.
		update_option( self::OPTION_NAME, $this->shy_post_ids );
	}

	/**
	 * Places checkbox in the Publish meta box
	 *
	 * @access public
	 * @return void
	 */
	public function option_hide_in_publish() {

		global $post;

		// Use nonce for verification.
		wp_nonce_field( plugin_basename( __FILE__ ), 'shyposts_nonce' );

		// Get the data.
		$value = get_post_meta( $post->ID, self::META_KEY, true );

		$title = __( 'Removes this post from the homepage, but NOT from any other page', 'shy-posts' );

		// Echo the meta box.
		echo '<div class="misc-pub-section misc-pub-section-last">';
		echo '<input type="checkbox" id="shyposts_hide_field" name="shyposts_hide_field" value="1" ' . checked( $value, '1', false ) . ' title="' . esc_attr( $title ) . '"> ';
		echo '<label for="shyposts_hide_field" title="' . esc_attr( $title ) . '">';
		echo esc_html__( 'Hide on the homepage?', 'shy-posts' );
		echo '</label> ';
		echo '</div>';
	}


	/**
	 * Updates post meta and the stored list with the form data
	 *
	 * @access public
	 * @param int $post_id Post being saved.
	 * @return void
	 */
	public function save( $post_id ) {

		// Autosaves and revisions don't carry the checkbox's meaning.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Check if the user intended to change this value.
		if ( ! isset( $_POST['shyposts_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shyposts_nonce'] ) ), plugin_basename( __FILE__ ) ) ) {
			return;
		}

		// Check if the current user is authorised to do this action.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$post_type = get_post_type_object( $post->post_type );
		if ( ! $post_type || ! current_user_can( $post_type->cap->edit_post, $post_id ) ) {
			return;
		}

		// Unchecked checkboxes aren't submitted at all.
		$is_shy = isset( $_POST['shyposts_hide_field'] ) && '1' === $_POST['shyposts_hide_field'];

		// The meta hooks update the stored list.
		if ( $is_shy ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Register the meta so the block editor can read and write it over REST
	 *
	 * @access public
	 * @since 2.0
	 * @return void
	 */
	public function register_meta() {
		register_post_meta(
			'',
			self::META_KEY,
			array(
				'type'              => 'boolean',
				'single'            => true,
				'default'           => false,
				'show_in_rest'      => true,
				'sanitize_callback' => array( $this, 'sanitize_meta' ),
				// The key is protected, so editing over REST needs an explicit auth callback.
				'auth_callback'     => array( $this, 'can_edit_meta' ),
			)
		);
	}

	/**
	 * Store the flag the same way the classic editor always has: '1' or ''
	 *
	 * @access public
	 * @since 2.0
	 * @param mixed $value Submitted meta value.
	 * @return string
	 */
	public function sanitize_meta( $value ) {
		return rest_sanitize_boolean( $value ) ? '1' : '';
	}

	/**
	 * Only users who can edit the post can change its shy flag
	 *
	 * @access public
	 * @since 2.0
	 * @param bool   $allowed  Whether the user can edit the meta, before this check.
	 * @param string $meta_key Meta key being edited.
	 * @param int    $post_id  Post the meta belongs to.
	 * @return bool
	 */
	public function can_edit_meta( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Load the block editor sidebar control
	 *
	 * @access public
	 * @since 2.0
	 * @return void
	 */
	public function enqueue_block_editor_assets() {
		// This hook also fires in the site and widget editors, which have no post to flag.
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}

		wp_enqueue_script(
			'shy-posts-editor',
			plugins_url( 'js/shy-posts-editor.js', __FILE__ ),
			array( 'wp-plugins', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			self::VERSION,
			true
		);

		wp_set_script_translations( 'shy-posts-editor', 'shy-posts' );
	}

	/**
	 * Update the stored list when the shy meta is added or changed
	 *
	 * @access public
	 * @since 2.0
	 * @param int    $meta_id    ID of the meta row.
	 * @param int    $post_id    Post the meta belongs to.
	 * @param string $meta_key   Meta key that changed.
	 * @param mixed  $meta_value New meta value.
	 * @return void
	 */
	public function meta_changed( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( self::META_KEY !== $meta_key || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$this->update_shy_post_ids( $post_id, '1' === (string) $meta_value );
	}

	/**
	 * Update the stored list when the shy meta is deleted, including when its post is deleted.
	 * Trashed posts keep their meta, so they're still shy if restored.
	 *
	 * @access public
	 * @since 2.0
	 * @param array  $meta_ids IDs of the deleted meta rows.
	 * @param int    $post_id  Post the meta belonged to.
	 * @param string $meta_key Meta key that was deleted.
	 * @return void
	 */
	public function meta_deleted( $meta_ids, $post_id, $meta_key ) {
		if ( self::META_KEY !== $meta_key ) {
			return;
		}

		$this->update_shy_post_ids( $post_id, false );
	}

	/**
	 * Filter posts on homepage
	 *
	 * @access public
	 * @param WP_Query $query Query about to run.
	 * @return void
	 */
	public function exclude_shy_posts_from_homepage( $query ) {
		// Make sure we're looking in the right place.
		if ( ! $query->is_main_query() || ! $query->is_front_page() ) {
			return;
		}

		$shy_post_ids = $this->get_shy_post_ids();
		if ( empty( $shy_post_ids ) ) {
			return;
		}

		// Add to any exclusions something else already set, rather than replacing them.
		$not_in = array_merge( (array) $query->get( 'post__not_in' ), $shy_post_ids );

		$query->set( 'post__not_in', array_values( array_unique( array_map( 'intval', $not_in ) ) ) );
	}

}
