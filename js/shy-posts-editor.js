/**
 * Shy Posts block editor control
 *
 * Adds the "Hide on the homepage?" checkbox to the post status panel of the block editor sidebar.
 * Plain ES5 with wp.* globals so there's no build step.
 *
 * @package Shy_Posts
 * @since Shy_Posts 2.0
 */
( function( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var CheckboxControl = wp.components.CheckboxControl;
	var useSelect = wp.data.useSelect;
	var useDispatch = wp.data.useDispatch;

	// moved from wp.editPost to wp.editor in WP 6.6
	var PluginPostStatusInfo = ( wp.editor && wp.editor.PluginPostStatusInfo ) || wp.editPost.PluginPostStatusInfo;

	var META_KEY = 'shy_post';

	function ShyPostsControl() {
		var meta = useSelect( function( select ) {
			return select( 'core/editor' ).getEditedPostAttribute( 'meta' );
		}, [] );
		var editPost = useDispatch( 'core/editor' ).editPost;

		// the meta isn't in REST for post types without custom-fields support
		if ( ! meta || ! Object.prototype.hasOwnProperty.call( meta, META_KEY ) ) {
			return null;
		}

		return el(
			PluginPostStatusInfo,
			{ className: 'shy-posts-status-info' },
			el( CheckboxControl, {
				label: __( 'Hide on the homepage?', 'shy-posts' ),
				help: __( 'Removes this post from the homepage, but NOT from any other page', 'shy-posts' ),
				checked: !! meta[ META_KEY ],
				onChange: function( value ) {
					var newMeta = {};
					newMeta[ META_KEY ] = value;
					editPost( { meta: newMeta } );
				},
			} )
		);
	}

	wp.plugins.registerPlugin( 'shy-posts', { render: ShyPostsControl } );
} )( window.wp );
