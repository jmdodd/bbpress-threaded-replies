<?php


if ( !defined( 'ABSPATH' ) ) exit;


if ( !class_exists( 'UCC_bbPress_Threaded_Replies' ) ) {
class UCC_bbPress_Threaded_Replies {
	public static $instance;
	public static $version;

	public function __construct() {
		self::$instance = $this;
		add_action( 'bbp_init', array( $this, 'init' ), 11 );
		$this->version = '20120915';
	} // __construct

	public function init() {
		// Input handling (metabox, reply input form).
		if ( is_admin() ) {
			add_action( 'bbp_reply_metabox', array( $this, 'extend_reply_metabox' ), 10, 1 );
			add_action( 'bbp_reply_attributes_metabox_save', array( $this, 'extend_reply_attributes_metabox_save' ), 10, 3 );
		} else {
			add_action( 'bbp_theme_before_reply_form_submit_wrapper', array( $this, 'add_form_field' ) );
			add_action( 'bbp_theme_before_reply_form', array( $this, 'add_cancel_link' ) );
		}

		// bbPress action/filter hooks.
		add_filter( 'bbp_has_replies', array( $this, 'has_replies' ), 10, 2 );
		add_filter( 'bbp_new_reply_pre_set_terms', array( $this, 'save_post' ), 10, 3 );
		add_filter( 'bbp_get_reply_url', array( $this, 'reply_url' ), 15, 3 );
		add_filter( 'bbp_get_replies_per_page', array( $this, 'replies_per_page' ), 10, 2 );
		add_filter( 'bbp_replies_pagination', array( $this, 'replies_pagination' ) );
		add_filter( 'bbp_get_topic_pagination', array( $this, 'get_topic_pagination' ), 10, 2 );
		add_action( 'bbp_merge_topic', array( $this, 'merge_topic' ), 10, 2 );
		add_action( 'bbp_pre_split_topic', array( $this, 'split_topic' ), 10, 3 );		

		// Caching interactions.
		add_action( 'bbp_new_reply_pre_extras', array( $this, 'clean_cache' ) );
		add_action( 'bbp_edit_reply_pre_extras', array( $this, 'clean_cache' ) );
		add_action( 'bbp_spam_reply', array( $this, 'clean_cache' ) );
		add_action( 'bbp_unspam_reply', array( $this, 'clean_cache' ) );
		add_action( 'bbp_delete_reply', array( $this, 'clean_cache' ) );
		add_action( 'bbp_trash_reply', array( $this, 'clean_cache' ) );
		add_action( 'bbp_untrash_reply', array( $this, 'clean_cache' ) );
		add_action( 'bbp_post_split_topic', array( $this, 'clean_cache' ) );
		add_action( 'bbp_merged_topic', array( $this, 'clean_cache' ) );
		add_action( 'bbp_closed_topic', array( $this, 'clean_cache' ) );
		add_action( 'bbp_opened_topic', array( $this, 'clean_cache' ) );
		add_action( 'bbp_spammed_topic', array( $this, 'clean_cache' ) );
		add_action( 'bbp_unspammed_topic', array( $this, 'clean_cache' ) );
		add_action( 'bbp_sticked_topic', array( $this, 'clean_cache' ) );
		add_action( 'bbp_unsticked_topic', array( $this, 'clean_cache' ) );
		add_action( 'bbp_deleted_topic', array( $this, 'clean_cache' ) );
		add_action( 'bbp_trashed_topic', array( $this, 'clean_cache' ) );
		add_action( 'bbp_untrashed_topic', array( $this, 'clean_cache' ) );
	} // init

	// Admin-side edit functionality and input handling.
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
	} // extend_reply_metabox

	public function extend_reply_attributes_metabox_save( $reply_id, $topic_id, $forum_id ) {
		$in_reply_to = !empty( $_REQUEST['inreplyto'] ) ? (int) $_REQUEST['inreplyto'] : 0;

		// Trust but verify.
		if ( !isset( $_REQUEST['inreplyto_nonce'] ) )
			return;
		if ( !check_admin_referer( 'inreplyto_metabox', 'inreplyto_nonce' ) )
			return;

		update_post_meta( $reply_id, '_ucc_btr_in_reply_to', $in_reply_to );
	} // extend_reply_attributes_metabox_save

	// Add hidden replyto form field and nonce to reply form.
	public function add_form_field() {
		if ( bbp_is_reply_edit() )
			return;

		// We have to set this for non-JS replies.
		$in_reply_to = !empty( $_REQUEST['inreplyto'] ) ? (int) $_REQUEST['inreplyto'] : 0;

		// Trust but verify.
		if ( !isset( $_REQUEST['inreplyto_nonce'] ) ) {
			$in_reply_to = 0;
		} else {
			if ( !wp_verify_nonce( $_REQUEST['inreplyto_nonce'], 'inreplyto-nonce' ) )
				$in_reply_to = 0;
		}

		echo "<input type='hidden' name='inreplyto' id='inreplyto' value='{$in_reply_to}' />\n";
		echo "\t\t\t\t\t\t<input type='hidden' name='inreplyto_nonce' id='inreplyto_nonce' value='" . wp_create_nonce( 'inreplyto-nonce' ) . "' />\n";
	} // add_form_field

	// Add cancel link to reply form.
	public function add_cancel_link() {
		if ( bbp_is_reply_edit() )
			return;

		echo "<h3><small>" . ucc_btr_get_cancel_in_reply_to_link( 'Cancel reply' ) . "</small></h3>";
	} // add_cancel_link

	// Set up replies for threading on bbPress has_replies().
	public function has_replies( $have_posts, $reply_query ) {
		global $bbp, $wp_rewrite, $overridden_rpage;

		if ( is_object( $bbp ) ) {
			// bbPress version < 2.1 or already initialized.
		} elseif ( function_exists( 'bbpress' ) ) {
			$bbp = bbpress();
		}

		// Heavy lifting for future calls.
		$posts = $reply_query->posts;
		$replies = array();
		if ( !empty( $posts ) ) {
			foreach( $posts as &$post ) {
				// Ignore the topic if included.
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
		}
		$bbp->reply_query->posts = $posts;
		$bbp->replies = $replies;

		// Set up based on options.
		$options = get_option( '_ucc_btr_options' );
		$roots = ucc_btr_get_root_element_count( $bbp->current_topic_id );
		if ( $options['page_replies'] ) {
			$replies_per_page = (int) $options['replies_per_page'];
			$max_num_pages = ceil( (int) $roots / $replies_per_page );
		} else {
			$replies_per_page = -1;
			$max_num_pages = 1;
		}
		$overridden_rpage = false;
		if ( $options['default_replies_page'] == 'newest' )
			$overridden_rpage = true;

		$bbp->reply_query->posts_per_page = $replies_per_page;
		$bbp->reply_query->max_num_pages = $max_num_pages;

		// Deal with reply pagination here.
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
			'prev_text' => __( '&larr;', 'bbpress-threaded-replies' ),
			'next_text' => __( '&rarr;', 'bbpress-threaded-replies' ),
			'total' => $max_num_pages,
			'add_args'  => ( bbp_get_view_all() ) ? array( 'view' => 'all' ) : false
		);
		$bbp->reply_query->pagination_links = paginate_links( $args );

		return $have_posts;
	} // has_replies

	// Add post_meta for in_reply_to on save.
	public function save_post( $terms, $topic_id, $reply_id ) {
		$in_reply_to = !empty( $_REQUEST['inreplyto'] ) ? (int) $_REQUEST['inreplyto'] : 0;

		// Trust but verify.
		if ( !isset( $_REQUEST['inreplyto_nonce'] ) )
			return;
		if ( !wp_verify_nonce( $_REQUEST['inreplyto_nonce'], 'inreplyto-nonce' ) )
			return;

		update_post_meta( $reply_id, '_ucc_btr_in_reply_to', $in_reply_to );

		return $terms;
	} // save_post

	// Reply URL for non-JS users.
	public function reply_url( $url, $reply_id, $redirect_to ) {
		$reply = get_post( $reply_id );
		if ( bbp_get_reply_post_type() == $reply->post_type ) {
			$url = ucc_btr_get_reply_link( $url, $reply_id, $redirect_to );
		}
		return $url;
	} // reply_url

	// Return all replies if on a single topic view.
	public function replies_per_page( $retval, $per ) {
		if ( bbp_is_single_topic() && !( bbp_is_topic_merge() || bbp_is_topic_split() || bbp_is_topic_edit() ) )
			return -1;
		else
			return $retval;
	} // replies_per_page

	// Pagination for threaded replies.
	public function replies_pagination( $args ) {
		global $wp_rewrite;

		if ( $wp_rewrite->using_permalinks() )
			$base = trailingslashit( bbp_get_topic_permalink( bbp_get_topic_id() ) ) . user_trailingslashit( $wp_rewrite->pagination_base . '/%#%/' );
		else
			$base = add_query_arg( 'paged', '%#%', bbp_get_topic_permalink( bbp_get_topic_id() ) );

		$args['base'] = $base;
		return $args;
	} // replies_pagination

	// Fix topic pagination for display on forum pages.
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

		$options = get_option( '_ucc_btr_options' );
		if ( !$options['page_replies'] )
			return '';

		$roots = ucc_btr_get_root_element_count( $topic_id );
		$total = ceil( (int) $roots / (int) $options['replies_per_page'] );

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
	} // get_topic_pagination

	// Handle inheritance on merges.
	public function merge_topic( $destination_topic_id, $source_topic_id ) {
		$replies = (array) get_posts( array(
			'post_parent'    => $source_topic_id,
			'post_type'      => bbp_get_reply_post_type(),
			'posts_per_page' => -1,
			'order'          => 'ASC'
		) );

		// Clean up orphans by setting them as replies to the source topic.
		if ( !empty( $replies ) ) {
			foreach ( $replies as $reply ) {
				$in_reply_to = get_post_meta( $reply->ID, '_ucc_btr_in_reply_to', true );
				if ( empty( $in_reply_to ) )
					update_post_meta( $reply->ID, '_ucc_btr_in_reply_to', $source_topic_id );
			}
		}

		// Fix the source topic.
		update_post_meta( $source_topic_id, '_ucc_btr_in_reply_to', '0' );
	} // merge_topic

	// Handle inheritance on splits.
	public function split_topic( $from_reply_id, $source_topic_id, $destination_topic_id ) {
		global $wpdb;
		$from_reply = get_post( $from_reply_id );

		// get_posts() is not used because it does not have the >= comparison for post_date.
		$replies = (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE {$wpdb->posts}.post_date >= %s AND {$wpdb->posts}.post_parent = %d AND {$wpdb->posts}.post_type = %s ORDER BY {$wpdb->posts}.post_date ASC", $from_reply->post_date, $source_topic_id, bbp_get_reply_post_type() ) );

		// Clean up orphans.
		$ids = array();
		if ( !empty( $replies ) && !is_wp_error( $replies ) ) {
			foreach ( $replies as $reply ) {
				$ids[] = $reply->ID;
				$in_reply_to = get_post_meta( $reply->ID, '_ucc_btr_in_reply_to', true );
				if ( !in_array( $in_reply_to, $ids ) )
					update_post_meta( $reply->ID, '_ucc_btr_in_reply_to', 0 );
				// Special case for new topic from reply.
				if ( ( $from_reply_id == $destination_topic_id ) && ( $from_reply_id == $in_reply_to ) )
					update_post_meta( $reply->ID, '_ucc_btr_in_reply_to', 0 );
			}
		}

		// Fix the parent reply.
		update_post_meta( $from_reply_id, '_ucc_btr_in_reply_to', 0 );
	} // split_topic

	// Tidy up topic information after major changes.
	public function clean_cache() {
		$group = 'ucc_btr';
		$expires = ucc_btr_get_expires();

		$ids = func_get_args();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			$topic_id = null;
			$post_type = get_post_type( $id );
			if ( $post_type == bbp_get_reply_post_type() )
				$topic_id = bbp_get_reply_topic_id( $id );
			elseif ( $post_type == bbp_get_topic_post_type() )
				$topic_id = $id;

			if ( empty( $topic_id ) )
				continue;

                	$keys = array(
                	        'reply_count_all',
                	        'reply_count',
                	        'reply_pages_all',
                	        'reply_pages' );
                	foreach ( $keys as $key ) {
                        	$cache = wp_cache_get( $key, $group );

				if ( is_array( $cache ) && !empty( $cache ) ) {
					if ( array_key_exists( $topic_id, $cache ) ) { 
						unset( $cache[$topic_id] );	
						wp_cache_set( $key, $cache, $group, $expires );
					}
				}
			}
		}
	}

	// Wipe cache completely (when settings change, etc.).
	public function clear_cache() {
		$group = 'ucc_btr';

		$keys = array(
			'reply_count_all',
			'reply_count',
			'reply_pages_all',
			'reply_pages' );
		foreach ( $keys as $key ) {
			wp_cache_delete( $key, $group );
		}
	}
} }
