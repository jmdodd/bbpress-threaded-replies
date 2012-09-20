<?php
/*
Plugin Name: bbPress Threaded Replies 
Description: Add threaded (nested) reply functionality to bbPress. 
Version: 0.4
License: GPL
Author: Jennifer M. Dodd
Author URI: http://uncommoncontent.com/
Text Domain: bbpress-threaded-replies
Domain Path: /languages/

================================================================================

Copyright 2012 Jennifer M. Dodd

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.
	
This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


if ( !defined( 'ABSPATH' ) ) exit;


if ( !class_exists( 'UCC_bbPress_Threaded_Replies_Loader' ) ) {
class UCC_bbPress_Threaded_Replies_Loader {
	public static $instance;
	public static $version;
	public $plugin_dir;
	public $template_dir;
	public $lang_dir;
	
	public function __construct() {
		self::$instance = $this;
		$this->version = '20120915';
		$this->plugin_dir   = plugin_dir_path( __FILE__ );
		$this->plugin_url   = plugins_url( __FILE__ );
		$this->template_dir = $this->plugin_dir . 'templates';

		// Languages.
		load_plugin_textdomain( 'bbpress-threaded-replies', false, basename( dirname( __FILE__ ) ) . '/languages' );

		// Default settings.
		$options = get_option( '_ucc_btr_options' );
		if ( !$options ) {
			$options = array(
				'thread_replies' => true,
				'thread_replies_depth' => 5,
				'page_replies' => true,
				'replies_per_page' => 5,
				'default_replies_page' => 'oldest',
				'reply_order' => 'asc'
			);
			update_option( '_ucc_btr_options', $options );
		}

		// Admin-side plugin settings.
		if ( is_admin() )
			add_action( 'bbp_admin_init', array( $this, 'register_admin_settings' ), 15 );

		// Includes.
		$this->includes();

		// Load main.
		if ( $options['thread_replies'] ) {
			// Template and script handlers.
			if ( function_exists( 'bbpress' ) ) {
			      add_action( 'get_template_part_loop', array( $this, 'get_template_part' ), 10, 2 );
			      add_filter( 'bbp_get_template_part', array( $this, 'bbp_get_template_part' ), 10, 3 );
			} else { // bbPress < 2.1 compat.
				add_action( 'template_redirect', array( $this, 'template_redirect' ), 15 );
			}
			add_action( 'wp_enqueue_scripts', array( $this, 'register_externals' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'load_externals' ) );

			new UCC_bbPress_Threaded_Replies;
		}

		// Caching.
		wp_cache_add_global_groups( 'ucc_btr' );
	} // __construct

	// Includes.
	public function includes() {
		require( $this->plugin_dir . 'includes/bbpress-threaded-replies.php' );
		require( $this->plugin_dir . 'includes/ucc-btr-callbacks.php' );
		require( $this->plugin_dir . 'includes/ucc-btr-functions.php' );
	} // includes

	// Admin-side settings.
	public function register_admin_settings() {
		add_settings_section( 'threaded_replies', __( 'Threaded Replies', 'bbpress-threaded-replies' ), array( $this, 'threaded_replies_text' ), 'bbpress' );
		add_settings_field( '_ucc_btr_options', __( 'Threaded replies', 'bbpress-threaded-replies' ), array( $this, 'threaded_replies_settings' ), 'bbpress', 'threaded_replies' );
		register_setting( 'bbpress', '_ucc_btr_options', array( $this, 'validate_settings' ) );
	} // register_admin_settings

	public function threaded_replies_text() {
		_e( 'You can set up threading for replies here. If disabled, topics will display in flat date order. Existing threading data will be preserved, but no new threading data will be added.', 'bbpress-threaded-replies' );
	} // threaded_replies_text

	public function threaded_replies_settings() {
		$options = (array) get_option( '_ucc_btr_options' );
		$thread_replies = array_key_exists( 'thread_replies', $options ) ? $options['thread_replies'] : 0;
		$thread_replies_depth = array_key_exists( 'thread_replies_depth', $options ) ? $options['thread_replies_depth'] : 5;
		$maxdeep = (int) apply_filters( 'ucc_btr_replies_depth_max', 10 );
		$page_replies = array_key_exists( 'page_replies', $options ) ? $options['page_replies'] : 0;
		$replies_per_page = array_key_exists( 'replies_per_page', $options ) ? $options['replies_per_page'] : 5;
		$default_replies_page = array_key_exists( 'default_replies_page', $options ) ? $options['default_replies_page'] : 'oldest';
		$reply_order = array_key_exists( 'reply_order', $options ) ? $options['reply_order'] : 'asc';
		?>
		<input name="_ucc_btr_options[thread_replies]" type="checkbox" value="1" <?php checked( '1', $thread_replies ); ?> />
		<label for="_ucc_btr_options[thread_replies]"><?php _e( 'Enable threaded replies', 'bbpress-threaded-replies' ); ?></label>
		<select name="_ucc_btr_options[thread_replies_depth]">
		<?php
		for ( $i = 2; $i <= $maxdeep; $i++ ) {
			echo '<option value="' . esc_attr( $i )  . '"';
			if ( $thread_replies_depth == $i )
				echo ' selected="selected"';
			echo ">$i</option>";
		}
		?>
		</select> <label for="_ucc_btr_options[thread_replies_depth]"><?php _e( 'levels deep', 'bbpress-threaded-replies' ); ?></label>
		<br /><input name="_ucc_btr_options[page_replies]" type="checkbox" value="1" <?php checked( '1', $page_replies ); ?> />
		<label for="_ucc_btr_options[page_replies]"><?php _e( 'Break replies into pages with', 'bbpress-threaded-replies' ); ?></label>
		<input name="_ucc_btr_options[replies_per_page]" type="text" value="<?php esc_attr_e( $replies_per_page ); ?>" class="small-text" />
		<label for="_ucc_btr_options[replies_per_page]"><?php _e( 'top level replies per page and the', 'bbpress-threaded-replies' ); ?></label>
		<select name="_ucc_btr_options[default_replies_page]">
			<option value="newest" <?php selected( 'newest', $default_replies_page ); ?>><?php _e( 'last', 'bbpress-threaded-replies' ); ?></option>
			<option value="oldest" <?php selected( 'oldest', $default_replies_page ); ?>><?php _e( 'first', 'bbpress-threaded-replies' ); ?></option>
		</select>
		<label for="_ucc_btr_options[default_replies_page]"><?php _e( 'page displayed by default', 'bbpress-threaded-replies' ); ?></label>
		<br /><?php _e( 'Replies should be displayed with the', 'bbpress-threaded-replies' ); ?>
		<select name="_ucc_btr_options[reply_order]">
			<option value="desc" <?php selected( 'desc', $reply_order ); ?>><?php _e( 'newer', 'bbpress-threaded-replies' ); ?></option>
			<option value="asc" <?php selected( 'asc', $reply_order ); ?>><?php _e( 'older', 'bbpress-threaded-replies' ); ?></option>
		</select>
		<label for="_ucc_btr_options[reply_order]"><?php _e( 'replies at the top of the page', 'bbpress-threaded-replies' ); ?></label>
		<?php
	} // threaded_replies_settings

	public function validate_settings( $_options ) {
		$_options = (array) $_options;

		$options['thread_replies'] = array_key_exists( 'thread_replies', $_options ) ? 1 : 0;
		$options['thread_replies_depth'] = array_key_exists( 'thread_replies_depth', $_options ) ? absint( $_options['thread_replies_depth'] ) : 5;
		$options['page_replies'] = array_key_exists( 'page_replies', $_options ) ? 1 : 0;
		$options['replies_per_page'] = array_key_exists( 'replies_per_page', $_options ) ? absint( $_options['replies_per_page'] ) : 5;
		$options['default_replies_page'] = ( array_key_exists( 'default_replies_page', $_options ) && ( $_options['default_replies_page'] == 'newest' ) ) ? 'newest' : 'oldest';
		$options['reply_order'] = ( array_key_exists( 'reply_order', $_options ) && ( $_options['reply_order'] == 'desc' ) ) ? 'desc' : 'asc';

		// Clear the cache when options are updated,
		$group = 'ucc_btr';
		$expires = ucc_btr_get_expires();

		$keys = array(
			'reply_count_all',
			'reply_count',
			'reply_pages_all',
			'reply_pages' );
		foreach ( $keys as $key ) {
			wp_cache_delete( $key, $group );
		}

		return $options;
	} // validate_settings

	// New way of doing this.
	public function get_template_part( $slug, $name ) {
		if ( $slug == 'loop' && $name == 'replies' && bbp_is_single_topic() && !bbp_is_topic_merge() && !bbp_is_topic_edit() && !bbp_is_topic_split() ) {
			if ( bbp_is_theme_compat_active() )
				$template_name = 'bbpress/ucc-loop-compat.php';
			else
				$template_name = 'bbpress/ucc-loop-replies.php';

			// Check child theme first
			if ( file_exists( trailingslashit( STYLESHEETPATH ) . $template_name ) ) {
				$located = trailingslashit( STYLESHEETPATH ) . $template_name;
			// Check parent theme next
			} elseif ( file_exists( trailingslashit( TEMPLATEPATH ) . $template_name ) ) {
				$located = trailingslashit( TEMPLATEPATH ) . $template_name;
			// Check theme compatibility
			} elseif ( file_exists( trailingslashit( bbp_get_theme_compat_dir() ) . $template_name ) ) {
				$located = trailingslashit( bbp_get_theme_compat_dir() ) . $template_name;
			// Use default plugin template
			} else { 
				$located = trailingslashit( $this->template_dir ) . $template_name;
			}
			load_template( $located, true );
		}
	} // get_template_part

	public function bbp_get_template_part( $templates, $slug, $name ) {
		if ( $slug == 'loop' && $name == 'replies' && bbp_is_single_topic() && !bbp_is_topic_merge() && !bbp_is_topic_edit() && !bbp_is_topic_split() )
			return array();
		return $templates;
	} // bbp_get_template_part

	// bbPress 2.0 compat, simplified.
	public function template_redirect() {
		if ( bbp_is_single_topic() && !bbp_is_topic_merge() && !bbp_is_topic_edit() && !bbp_is_topic_split() ) {
			$file = 'ucc-single-topic.php';
			$file = apply_filters( 'ucc_btr_template_redirect', $file );

			if ( file_exists( trailingslashit( get_stylesheet_directory() ) . $file ) ) {
				include ( trailingslashit( get_stylesheet_directory() ) . $file );
				exit;
			} elseif ( file_exists( trailingslashit( get_template_directory() ) . $file ) ) {
				include ( trailingslashit( get_template_directory() ) . $file );
				exit;
			} else {
				include ( trailingslashit( $this->template_dir ) . $file );
				exit;
			}
		}
	} // template_redirect

	public function register_externals() {
		wp_register_style( 'bbpress-threaded-replies', plugins_url( 'css/bbpress-threaded-replies.css', __FILE__ ), false, $this->version );
		wp_register_script( 'bbpress-threaded-replies', plugins_url( 'js/bbpress-threaded-replies.js', __FILE__ ), false, $this->version, false );
	} // register_externals

	public function load_externals() {
		if ( bbp_is_single_topic() && !bbp_is_topic_edit() && !bbp_is_topic_merge() && !bbp_is_topic_split() ) {
			wp_enqueue_style( 'bbpress-threaded-replies' );
			wp_enqueue_script( 'bbpress-threaded-replies' );
		}
	} // load_externals
} }

// Only load if bbPress is active.
function ucc_btr_loader() {
	if ( in_array( 'bbpress/bbpress.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
		new UCC_bbPress_Threaded_Replies_Loader;
}
add_action( 'init', 'ucc_btr_loader' );
