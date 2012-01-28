<?php
/*
Plugin Name: bbPress Threaded Replies 
Description: Add threaded (nested) reply functionality to bbPress. 
Version: 0.2
Author: Jennifer M. Dodd
Author URI: http://uncommoncontent.com/
Text Domain: bbpress-threaded-replies
*/ 

/*
	Copyright 2012 Jennifer M. Dodd <jmdodd@gmail.com>

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


if ( ! defined( 'ABSPATH' ) ) exit;


define( 'UCC_BTR_DIR', plugin_dir_path( __FILE__ ) );

include( UCC_BTR_DIR . 'library/reply.php' );
include( UCC_BTR_DIR . 'library/reply-template.php' );


if ( ! class_exists( 'UCC_bbPress_Threaded_Replies' ) ) {
class UCC_bbPress_Threaded_Replies {
	public static $instance;
	public static $version;
	
	public function __construct() {
		self::$instance = $this;
		add_action( 'bbp_init', array( $this, 'init' ), 11 );
		$this->version = '20120126';
	}

	public function init() {
		load_plugin_textdomain( 'bbpress-threaded-replies', false, basename( dirname( __FILE__ ) ) . '/l10n' );
			
		//  Form input handling (metabox, reply input form).
		if ( is_admin() ) {
			add_action( 'bbp_reply_metabox', array( $this, 'extend_reply_metabox' ), 10, 1 );
			add_action( 'bbp_reply_attributes_metabox_save', array( $this, 'extend_reply_attributes_metabox_save' ), 10, 3 );
		} else {
			add_action( 'bbp_theme_before_reply_form_submit_wrapper', array( $this, 'add_form_field' ) );
			add_action( 'bbp_theme_before_reply_form', array( $this, 'add_cancel_link' ) );
		}
		
		//  bPress compatability.
		add_filter( 'bbp_new_reply_pre_set_terms', array( $this, 'save_post' ), 10, 3 );
		add_filter( 'bbp_get_replies_per_page', array( $this, 'replies_per_page' ), 10, 2 );
		add_filter( 'bbp_has_replies', array( $this, 'has_replies' ), 10, 2 );
		add_filter( 'bbp_replies_pagination', array( $this, 'replies_pagination' ) );
		add_filter( 'bbp_get_topic_pagination', array( $this, 'get_topic_pagination' ), 10, 2 );
		add_action( 'bbp_get_reply_url', array( $this, 'reply_url' ), 10, 3 );
		add_action( 'bbp_merge_topic', array( $this, 'merge_topic' ), 10, 3 );
		add_action( 'bbp_pre_split_topic', array( $this, 'split_topic' ), 10, 3 );
		
		//  Front-end display handling.
		add_action( 'template_redirect', array( $this, 'template_redirect' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_externals' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'load_externals' ) );
	}
	
	//  Admin-side edit functionality and input handling.
	public function extend_reply_metabox( $reply_id ) {
		$value = absint( get_post_meta( $reply_id, '_ucc_btr_in_reply_to', true ) );
		?>
		<p><strong><?php _e( 'In Reply To', 'bbpress-threaded-replies' ); ?></strong></p>
	
		<p>
			<label class="screen-reader-text" for="inreplyto"><?php _e( 'In Reply To', 'bbpress-threaded-replies' ); ?></label>
			<input type="text" name="inreplyto" id="inreplyto" value="<?php echo $value; ?>" />
			<?php wp_nonce_field( 'inreplyto_metabox', 'inreplyto_nonce' ); ?>
		</p>
		<?php
	}
	
	public function extend_reply_attributes_metabox_save( $reply_id, $topic_id, $forum_id ) {
		$in_reply_to = ! empty( $_REQUEST['inreplyto'] ) ? (int) $_REQUEST['inreplyto'] : 0;
		
		//  Trust but verify.
		if ( ! isset( $_REQUEST['inreplyto_nonce'] ) )
			return;
		if ( ! check_admin_referer( 'inreplyto_metabox', 'inreplyto_nonce' ) )
			return;		

		update_post_meta( $reply_id, '_ucc_btr_in_reply_to', $in_reply_to );
	}
	
	//  Set up replies for threading on bbPress has_replies().
	public function has_replies( $has_replies, $bbp ) {
		global $bbp, $wp_rewrite;

		//  Heavy lifting for future calls.
		$posts = $bbp->reply_query->posts;
		$replies = array();
		foreach( $posts as &$post ) {
			//  Ignore the topic if included.
			if ( bbp_get_reply_post_type() == $post->post_type ) {
				$in_reply_to = get_post_meta( $post->ID, '_ucc_btr_in_reply_to', true );
				if ( empty( $in_reply_to ) || ( bbp_get_reply_topic_id( $post->ID ) == $in_reply_to ) )
					$in_reply_to = 0;
				$post->in_reply_to = $in_reply_to;
				$replies[] = $post;
			} elseif ( bbp_get_topic_post_type() == $post->post_type ) {
				delete_post_meta( $post->ID, '_ucc_btr_in_reply_to' );
			}
		}
		$bbp->reply_query->posts = $posts;
		$bbp->replies = $replies;

		$count = ucc_btr_get_root_element_count( $bbp->current_topic_id );
		$replies_per_page = get_option( '_bbp_replies_per_page' );
		$max_num_pages = ceil( (int) $count / $replies_per_page );
		
		$bbp->reply_query->posts_per_page = $replies_per_page;
		$bbp->reply_query->max_num_pages = $max_num_pages;

		//  Deal with reply pagination here.
		$topic_id = bbp_get_topic_id();
		if ( $wp_rewrite->using_permalinks() )
			$base = trailingslashit( get_permalink( $topic_id ) ) . user_trailingslashit( $wp_rewrite->pagination_base . '/%#%/' );
		else
			$base = add_query_arg( 'paged', '%#%', get_permalink( $topic_id ) );
		
		$args = array(
			'base' => $base,
			'current' => (int) $bbp->reply_query->paged,
			'mid_size'  => 1,
			'end_size' => 1,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'total' => $max_num_pages,
			'add_args'  => ( bbp_get_view_all() ) ? array( 'view' => 'all' ) : false
		);
		$bbp->reply_query->pagination_links = paginate_links( $args );

		return $has_replies;
	}
	
	public function replies_per_page( $retval, $per ) {
		if ( bbp_is_single_topic() && ! ( bbp_is_topic_merge() || bbp_is_topic_split() || bbp_is_topic_edit() ) )
			return -1;
		else
			return $retval;
	}
	
	public function reply_url( $url, $reply_id, $redirect_to ) {
		$reply = get_post( $reply_id );
		if ( bbp_get_reply_post_type() == $reply->post_type ) {
			$url = ucc_btr_get_reply_link( $url, $reply_id, $redirect_to );
		}
		return $url;
	}
	
	public function replies_pagination( $args ) {
		global $wp_rewrite;

		if ( $wp_rewrite->using_permalinks() )
			$base = trailingslashit( bbp_get_topic_permalink( bbp_get_topic_id() ) ) . user_trailingslashit( $wp_rewrite->pagination_base . '/%#%/' );
		else
			$base = add_query_arg( 'paged', '%#%', bbp_get_topic_permalink( bbp_get_topic_id() ) );
			
		$args['base'] = $base;
		return $args;
	}
	
	public function get_topic_pagination( $links, $args ) {
		global $wp_rewrite;

		$defaults = array(
			'topic_id' => bbp_get_topic_id(),
			'before'   => '<span class="bbp-topic-pagination">',
			'after'    => '</span>',
		);
		
		$r = wp_parse_args( $args, $defaults );
		extract( $r );
		
		if ( $wp_rewrite->using_permalinks() )
			$base = trailingslashit( get_permalink( $topic_id ) ) . user_trailingslashit( $wp_rewrite->pagination_base . '/%#%/' );
		else
			$base = add_query_arg( 'paged', '%#%', get_permalink( $topic_id ) );

		$count = ucc_btr_get_root_element_count( $topic_id );
		$total = ceil( (int) $count / (int) get_option( '_bbp_replies_per_page' ) );
		
		$pagination = array(
    		'base'      => $base,
    		'format'    => '',
			'total'     => $total,
			'current'   => 0,
			'prev_next' => false,
			'mid_size'  => 2,
			'end_size'  => 3,
			'add_args'  => ( bbp_get_view_all( 'edit_others_replies' ) ) ? array( 'view' => 'all' ) : false
		);
		
		if ( $pagination_links = paginate_links( $pagination ) ) {
			if ( $wp_rewrite->using_permalinks() )
				$pagination_links = str_replace( $wp_rewrite->pagination_base . '/1/', '', $pagination_links );
			else
				$pagination_links = str_replace( '&#038;paged=1', '', $pagination_links );
			$pagination_links = $before . $pagination_links . $after;
		}
		
		return $pagination_links;
	}
	
	public function merge_topic( $destination_topic_id, $source_topic_id, $source_topic_post_parent ) {
		$replies = (array) get_posts( array(
			'post_parent'    => $source_topic_id,
			'post_type'      => bbp_get_reply_post_type(),
			'posts_per_page' => -1,
			'order'          => 'ASC'
        	) );

		//  Make unaffiliated children obey their parent reply, now that it isn't a topic.
		foreach ( $replies as $reply ) {
			$in_reply_to = get_post_meta( $reply->ID, '_ucc_btr_in_reply_to', true );
			if ( empty( $in_reply_to ) )
				update_post_meta( $reply->ID, '_ucc_btr_in_reply_to', $source_topic_id );
		}
		
		//  Fix the parent reply, too.
		update_post_meta( $source_topic_id, '_ucc_btr_in_reply_to', '0' );
	}
	
	public function split_topic( $from_reply_id, $source_topic_id, $destination_topic_id ) {
		delete_post_meta( $from_reply_id, '_ucc_btr_in_reply_to' );
	}
	
	//  Try to find our template.
	public function template_redirect() {
		if ( bbp_is_single_topic() && ! bbp_is_topic_merge() && ! bbp_is_topic_edit() && ! bbp_is_topic_split() ) {
			if ( 'twentyeleven' == get_option( 'template') )
				$file = 'twentyeleven.php';
			else
				$file = 'single-topic.php';
			$file = apply_filters( 'ucc_btr_template_redirect', $file );
			
			if ( file_exists( get_stylesheet_directory() . '/' . $file ) ) {
				include ( get_stylesheet_directory() . '/' . $file );
				exit;
			} elseif ( file_exists( get_template_directory() . '/' . $file ) ) {
				include ( get_template_directory() . '/' . $file );
				exit;
			} else {
				include ( UCC_BTR_DIR . 'templates/' . $file );
				exit;
			}
		}
	}
	
	public function register_externals() {
		wp_register_style( 'bbpress-threaded-replies', plugin_dir_url( __FILE__ ) . 'css/bbpress-threaded-replies.css', false, $this->version );
		wp_register_style( 'bbpress-threaded-replies-twentyeleven', plugin_dir_url( __FILE__ ) . 'css/bbpress-threaded-replies-twentyeleven.css', false, $this->version );
		wp_register_script( 'bbpress-threaded-replies', plugin_dir_url( __FILE__ ) . 'js/bbpress-threaded-replies.js', false, $this->version, false );
	}
	
	public function load_externals() {
		 if ( bbp_is_single_topic() && ! bbp_is_topic_edit() && ! bbp_is_topic_merge() && ! bbp_is_topic_split() ) {
			if ( 'twentyeleven' == get_option( 'template') )
				wp_enqueue_style( 'bbpress-threaded-replies-twentyeleven' );
			else
				wp_enqueue_style( 'bbpress-threaded-replies' );
			wp_enqueue_script( 'bbpress-threaded-replies' );
		}
	}

	//  User-side input handling.
	public function save_post( $terms, $topic_id, $reply_id ) {
		$in_reply_to = ! empty( $_REQUEST['inreplyto'] ) ? (int) $_REQUEST['inreplyto'] : 0;

		//  Trust but verify.
		if ( ! isset( $_REQUEST['inreplyto_nonce'] ) )
			return;
		if ( ! wp_verify_nonce( $_REQUEST['inreplyto_nonce'], 'inreplyto-nonce' ) )
			return;
	
		update_post_meta( $reply_id, '_ucc_btr_in_reply_to', $in_reply_to );

		return $terms;
	}
	
	//  Add form input and nonce to bbpress/bbp-themes/bbp-twentyten/bbpress/form-reply.php.
	public function add_form_field() {
		if ( bbp_is_reply_edit() )
			return;
			
		//  We have to set this for non-JS replies.
		$in_reply_to = ! empty( $_REQUEST['inreplyto'] ) ? (int) $_REQUEST['inreplyto'] : 0;
		
		//  Trust but verify.
		if ( ! isset( $_REQUEST['inreplyto_nonce'] ) )
			$in_reply_to = 0;
		if ( ! wp_verify_nonce( $_REQUEST['inreplyto_nonce'], 'inreplyto-nonce' ) )
			$in_reply_to = 0;

		echo "<input type='hidden' name='inreplyto' id='inreplyto' value='{$in_reply_to}' />\n";
		echo "\t\t\t\t\t\t<input type='hidden' name='inreplyto_nonce' id='inreplyto_nonce' value='" . wp_create_nonce( 'inreplyto-nonce' ) . "' />\n";
	}
	
	public function add_cancel_link() {
		if ( bbp_is_reply_edit() )
			return;
			
		echo "\t\t\t<h3><small>" . ucc_btr_get_cancel_in_reply_to_link( 'Cancel reply' ) . "</small></h3>";
	}
} }


//  Only load if comment threading is turned on.
if ( get_option( 'thread_comments' ) && in_array( 'bbpress/bbpress.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) { 
	new UCC_bbPress_Threaded_Replies;
}
