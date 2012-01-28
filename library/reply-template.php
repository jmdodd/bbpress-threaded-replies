<?php


if ( !defined( 'ABSPATH' ) ) exit;


if ( ! function_exists( 'ucc_btr_get_root_element_id' ) ) {
function ucc_btr_get_root_element_id( $reply_id ) {
	$in_reply_to = get_post_meta( $reply_id, '_ucc_btr_in_reply_to', true );
	if ( empty( $in_reply_to ) || ( bbp_get_reply_topic_id( $reply_id ) == $in_reply_to ) )
		return $reply_id;
	else
		return ucc_btr_get_root_element_id( $in_reply_to );
} }


if ( ! function_exists( 'ucc_btr_get_root_element_count' ) ) {
function ucc_btr_get_root_element_count( $topic_id ) {
	global $wpdb;
	
	$post_status = join( ',', array( bbp_get_public_status_id(), bbp_get_closed_status_id() ) );
	if ( bbp_get_view_all( 'edit_others_replies' ) ) {
		$post_status = join( ',', array( bbp_get_public_status_id(), bbp_get_closed_status_id(), bbp_get_spam_status_id(), bbp_get_trash_status_id() ) );
	}
	$post_status = explode( ',', $post_status );
	$post_status_in_array = array();
	foreach ( $post_status as $ps )
		$post_status_in_array[] = $wpdb->prepare( "%s", $ps );
	$post_status_in_array = implode( ',', $post_status_in_array );
	
	//  Direct SQL is more efficient than trying to use WP_Query here.
	$sql = $wpdb->prepare( "
		SELECT COUNT(*) FROM {$wpdb->posts} p 
		WHERE p.post_parent = %d
			AND p.post_type = %s
			AND p.post_status IN ( {$post_status_in_array} )
			AND ( 
				NOT EXISTS ( 
					SELECT pm.* FROM wp_postmeta pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' 
				) OR EXISTS ( 
					SELECT pm.* FROM wp_postmeta pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' AND pm.meta_value = 0 
				) OR EXISTS ( 
					SELECT pm.* FROM wp_postmeta pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' AND pm.meta_value = %d 
		) ) ORDER BY p.post_date ASC",
		$topic_id, bbp_get_reply_post_type(), $topic_id
	);
	$count = $wpdb->get_var( $sql );
	
	return $count;
} }


//  Source: wp-includes/comment-template.php
//  Derived from get_comment_link().
if ( ! function_exists( 'ucc_btr_get_reply_link' ) ) {
function ucc_btr_get_reply_link( $url, $reply_id, $redirect_to ) {
	global $wp_rewrite;
	
	$reply_page = ucc_btr_get_page_of_reply( $reply_id );
	$topic_id = bbp_get_reply_topic_id( $reply_id );
	$topic_url = bbp_get_topic_permalink( $topic_id, $redirect_to );
	$reply_hash = '#reply-' . $reply_id;

	//  Deal with pagination and permalink structure.
	if ( 1 >= $reply_page ) {
		$url = user_trailingslashit( $topic_url ) . $reply_hash;
	} else {
		if ( $wp_rewrite->using_permalinks() ) {
			$url = trailingslashit( $topic_url ) . trailingslashit( $wp_rewrite->pagination_base ) . user_trailingslashit( $reply_page ) . $reply_hash;
		} else {
			$url = add_query_arg( 'paged', $reply_page, user_trailingslashit( $topic_url ) ) . $reply_hash;
		}
	}
	
	//  In case we ever need it.
	if ( bbp_get_view_all() )
		$url = bbp_add_view_all( $url );

	return apply_filters( 'ucc_btr_reply_link', $url, $reply_id, $redirect_to );
} }


//  Source: wp-includes/comment-template.php
//  Derived from get_comment_class().
if ( ! function_exists( 'ucc_btr_get_reply_class' ) ) {
function ucc_btr_get_reply_class( $class = '', $reply_id = null, $topic_id = null ) {
	global $reply, $reply_alt, $reply_depth, $reply_thread_alt;

	if ( null == $reply_id ) 
		$reply_id = bbp_get_reply_id();
	$topic = get_post( bbp_get_reply_topic_id( $reply_id ) );
	$classes = array();
	
	if ( defined( 'REPLIES_COMMENTSTYLE' ) && REPLIES_COMMENTSTYLE )
		$classes[] = 'comment';
	else
		$classes[] = 'reply';

	//  If the reply author is a user, print the cleaned user_nicename.
	if ( $reply->post_author > 0 && $user = get_userdata( $reply->post_author ) ) {
		//  For all registered users, 'byuser'
		$classes[] = 'byuser';
		if ( defined( 'REPLIES_COMMENTSTYLE' ) && REPLIES_COMMENTSTYLE )
			$classes[] = 'comment-author-' . sanitize_html_class( $user->user_nicename, $reply->post_author );
		else
			$classes[] = 'reply-author-' . sanitize_html_class( $user->user_nicename, $reply->post_author );
		//  For reply authors who are the author of the topic
		if ( $reply->post_author === $topic->post_author ) {
			if ( defined( 'REPLIES_COMMENTSTYLE' ) && REPLIES_COMMENTSTYLE )
				$classes[] = 'bypostauthor';
			else
				$classes[] = 'bytopicauthor';
		}
	}

	if ( empty( $reply_alt ) )
		$reply_alt = 0;
	if ( empty( $reply_depth ) )
		$reply_depth = 1;
	if ( empty( $reply_thread_alt ) )
		$reply_thread_alt = 0;

	if ( $reply_alt % 2 ) {
		$classes[] = 'odd';
		$classes[] = 'alt';
	} else {
		$classes[] = 'even';
	}

	$reply_alt++;

	//  Alt for top-level comments
	if ( 1 == $reply_depth ) {
		if ( $reply_thread_alt % 2 ) {
			$classes[] = 'thread-odd';
			$classes[] = 'thread-alt';
		} else {
			$classes[] = 'thread-even';
		}
		$reply_thread_alt++;
	}

	$classes[] = "depth-$reply_depth";
	
	$classes[] = 'status-' . bbp_get_reply_status();

	if ( ! empty( $class ) ) {
		if ( ! is_array( $class ) )
			$class = preg_split( '#\s+#', $class );
		$classes = array_merge( $classes, $class );
	}

	$classes = array_map( 'esc_attr', $classes );

	return apply_filters( 'ucc_btr_reply_class', $classes, $class, $reply->ID, $topic->ID );
} }


//  Source: wp-includes/comment-template.php
//  Derived from comment_class().
if ( ! function_exists( 'ucc_btr_reply_class' ) ) {
function ucc_btr_reply_class( $class = '', $reply_id = null, $topic_id = null, $echo = true ) {
	$class = 'class="' . join( ' ', ucc_btr_get_reply_class( $class, $reply_id, $topic_id ) ) . '"';
	if ( $echo )
		echo $class;
	else
		return $class;
} }


//  Source: wp-includes/comment-template.php
//  Derived from get_comment_reply_link().
if ( ! function_exists( 'ucc_btr_get_in_reply_to_link' ) ) {
function ucc_btr_get_in_reply_to_link( $args = array(), $reply = null, $topic = null ) {
	global $user_ID, $reply;

	$defaults = array( 
		'add_below' => 'reply', 
		'respond_id' => 'new-reply-' . bbp_get_topic_id(), 
		'reply_text' => __( 'Reply to this', 'bbpress-threaded-replies' ),
		'login_text' => __( 'Log in to Reply', 'bbpress-threaded-replies' ), 
		'depth' => 0, 
		'before' => '', 
		'after' => '' 
	);

	$args = wp_parse_args( $args, $defaults );

	if ( 0 == $args['depth'] || $args['max_depth'] <= $args['depth'] )
		return;

	extract( $args, EXTR_SKIP );

	$link = '';

	remove_query_arg( array( 'inreplyto', 'inreplyto_nonce' ) );
	if ( bbp_current_user_can_access_create_reply_form() ) {
    		$link = "<a class='in-reply-to-link' href='" . esc_url( add_query_arg( array( 'inreplyto_nonce' => wp_create_nonce( 'inreplyto-nonce' ), 'inreplyto' => bbp_get_reply_id() ) ) ) . "#" . $respond_id . "' onclick='return addReply.moveForm(\"$add_below-$reply->ID\", \"$reply->ID\", \"$respond_id\", \"$reply->post_parent\")'>$reply_text</a>";
	} elseif ( bbp_is_topic_closed() ) {
	} elseif ( bbp_is_forum_closed( bbp_get_topic_forum_id() ) ) {
	} else {
		$link = is_user_logged_in() ? __( 'You cannot reply to this topic.', 'bbpress-threaded-replies' ) : __( 'You must be logged in to reply to this topic.', 'bbpress-threaded-replies' );
	}

	return apply_filters( 'ucc_btr_in_reply_to_link', $before . $link . $after, $args, $reply, $topic );
} }


//  Source: wp-includes/comment-template.php
//  Derived from comment_reply_link().
if ( ! function_exists( 'ucc_btr_in_reply_to_link' ) ) {
function ucc_btr_in_reply_to_link( $args = array(), $reply = null, $topic = null ) {
	echo ucc_btr_get_in_reply_to_link( $args, $reply, $topic );
} }


//  Source: wp-includes/comment-template.php
//  Derived from get_cancel_comment_reply_link().
if ( ! function_exists( 'ucc_btr_get_cancel_in_reply_to_link' ) ) {
function ucc_btr_get_cancel_in_reply_to_link( $text = '' ) {
	if ( empty( $text ) )
		$text = __( 'Click here to cancel reply.', 'bbpress-threaded-replies' );
		
	$style = isset( $_GET['inreplyto'] ) ? '' : ' style="display:none;"';
	$link = esc_html( remove_query_arg( array( 'inreplyto', 'inreplyto_nonce' ) ) ) . '#respond';
	
	return apply_filters( 'ucc_btr_cancel_in_reply_to_link', '<a rel="nofollow" id="cancel-in-reply-to-link" href="' . $link . '"' . $style . '>' . $text . '</a>', $link, $text );
} }


//  Source: wp-includes/comment-template.php
//  Derived from get_cancel_comment_reply_link().
if ( ! function_exists( 'ucc_btr_cancel_in_reply_to_link' ) ) {
function ucc_btr_cancel_in_reply_to_link( $text = '' ) {
	echo ucc_btr_get_cancel_in_reply_to_link( $text );
} }


//  Source: wp-includes/comment-template.php
//  Derived from Walker_Comment.
if ( ! class_exists( 'UCC_BTR_Walker_Reply' ) ) {
class UCC_BTR_Walker_Reply extends Walker {
	var $tree_type = 'reply';
	var $db_fields = array ( 'parent' => 'in_reply_to', 'id' => 'ID' );

	function start_lvl( &$output, $depth, $args ) {
		$GLOBALS['reply_depth'] = $depth + 1;

		switch ( $args['style'] ) {
			case 'div':
				break;
			case 'ol':
				echo "<ol class='children'>\n";
				break;
			default:
			case 'ul':
				echo "<ul class='children'>\n";
				break;
		}
	}

	function end_lvl( &$output, $depth, $args ) {
		$GLOBALS['reply_depth'] = $depth + 1;

		switch ( $args['style'] ) {
			case 'div':
				break;
			case 'ol':
				echo "</ol>\n";
				break;
			default:
			case 'ul':
				echo "</ul>\n";
				break;
		}
	}

	function display_element( $element, &$children_elements, $max_depth, $depth = 0, $args, &$output ) {
		if ( ! $element )
			return;

		$id_field = $this->db_fields['id'];
		$id = $element->$id_field;

		parent::display_element( $element, $children_elements, $max_depth, $depth, $args, $output );

		//  If we're at the max depth, and the current element still has children, loop over those and display them at this level
		//  This is to prevent them being orphaned to the end of the list.
		if ( $max_depth <= $depth + 1 && isset( $children_elements[$id] ) ) {
			foreach ( $children_elements[ $id ] as $child )
				$this->display_element( $child, $children_elements, $max_depth, $depth, $args, $output );

			unset( $children_elements[ $id ] );
		}
	}

	function start_el( &$output, $_reply, $depth, $args ) {
		global $bbp, $post, $reply;
		
		$original_post = $post;
		$original_reply = $reply;
		
		//  Force WordPress/bbPress functions to use the right reply.
		$post = $_reply;
		$bbp->reply_query->post = $_reply;
		$reply = $_reply;
		
		$depth++;
		$GLOBALS['reply_depth'] = $depth;

		if ( ! empty( $args['callback'] ) ) {
			call_user_func( $args['callback'], $reply, $args, $depth );
			return;
		}
		
		$GLOBALS['reply'] = $reply;
		extract( $args, EXTR_SKIP );

		if ( 'div' == $args['style'] ) {
			$tag = 'div';
			$add_below = 'reply';
		} else {
			$tag = 'li';
			$add_below = 'div-reply';
		} ?>
<<?php echo $tag ?> <?php ucc_btr_reply_class( empty( $args['has_children'] ) ? '' : 'parent' ); ?>>
		<?php 
		if ( 'div' != $args['style'] ) { ?>
<div id="div-reply-<?php bbp_reply_ID(); ?>" class="reply-body">
		<?php
		} ?>
<table class="bbp-replies" id="reply-<?php bbp_reply_ID(); ?>">
	<tbody>
<tr class="noheight">
	<td class="bbp-reply-author noheight"></td>
	<td class="bbp-reply-content noheight"></td>
</tr>
<tr>
<?php bbp_get_template_part( 'bbpress/loop', 'single-reply' ); ?>
</tr>
<tr>
	<td colspan="2" class="ucc-bbp-in-reply-to">
<div class="reply">
<?php ucc_btr_in_reply_to_link( array_merge( $args, array( 'reply_text' => 'Reply &darr;', 'add_below' => $add_below, 'depth' => $depth, 'max_depth' => $args['max_depth'] ) ) ); ?>
</div>
	</td>
</tr>
	</tbody>
</table>
		<?php
		if ( 'div' != $args['style'] ) { ?>
</div>
		<?php 
		}
		
		//  Restore order.
		$post = $original_post;
		$bbp->reply_query->post = $original_post;
		$reply = $original_reply;
	}

	function end_el( &$output, $reply, $depth, $args ) {
		if ( ! empty( $args['end-callback'] ) ) {
			call_user_func( $args['end-callback'], $reply, $args, $depth );
			return;
		}
		if ( 'div' == $args['style'] )
			echo "</div>\n";
		else
			echo "</li>\n";
	}
} }


//  Source: wp-includes/comment-template.php
//  Derived from comments_template().
if ( ! function_exists( 'ucc_btr_replies_template' ) ) {
function ucc_btr_replies_template( $file = '/replies.php' ) {
	global $bbp, $wp_query, $overridden_rpage;
	
	if ( ! defined( 'REPLIES_COMMENTSTYLE' ) || ! REPLIES_COMMENTSTYLE )
		define( 'REPLIES_COMMENTSTYLE', true );

	if ( ! ( bbp_is_single_topic() && ! bbp_is_topic_edit() && ! bbp_is_topic_merge() && ! bbp_is_topic_split() ) )
		return;

	if ( empty( $file ) )
		$file = '/replies.php';

	$replies = $bbp->replies;

	$overridden_rpage = FALSE;
	if ( '' == get_query_var( 'paged' ) && get_option( 'page_comments' ) ) {
		set_query_var( 'paged', 'newest' == get_option( 'default_comments_page' ) ? ucc_btr_get_reply_pages_count() : 1 );
		$overridden_rpage = TRUE;
	}

	if ( ! defined( 'REPLIES_TEMPLATE' ) || ! REPLIES_TEMPLATE )
		define( 'REPLIES_TEMPLATE', true );
	
	//  Step backwards for the template: child theme, theme, plugin.
	$include = apply_filters( 'ucc_btr_replies_template', STYLESHEETPATH . $file );
	if ( file_exists( $include ) )
		require( $include );
	elseif ( file_exists( TEMPLATEPATH . $file ) )
		require( TEMPLATEPATH .  $file );
	else
		require( UCC_BTR_DIR . 'templates/replies.php' );
} }


//  Source: wp-content/themes/twentyeleven/functions.php
//  Derived from twentyeleven_comment().
if ( ! function_exists( 'ucc_btr_twentyeleven_reply_cb' ) ) {
function ucc_btr_twentyeleven_reply_cb( $reply, $args, $depth ) {
	global $reply; ?>
<li <?php ucc_btr_reply_class(); ?> id="li-comment-<?php bbp_reply_id(); ?>">
<article id="reply-<?php bbp_reply_id(); ?>" class="comment">
	<footer class="comment-meta">
		<div class="comment-author vcard">
		<?php
		$avatar_size = 39;
		$in_reply_to = get_post_meta( $reply->ID, '_ucc_btr_in_reply_to', true );
		if ( empty( $in_reply_to ) || ( bbp_get_reply_topic_id( $post->ID ) == $in_reply_to ) )
			$avatar_size = 68;
		echo bbp_get_reply_author_link( array( 'type' => 'avatar', 'size' => $avatar_size ) ); ?>
		<?php do_action( 'bbp_theme_before_reply_admin_links' ); ?>

		<?php bbp_reply_admin_links(); ?>

		<?php do_action( 'bbp_theme_after_reply_admin_links' ); ?>
		<?php
		if ( is_super_admin() ) {
			$author_ip = bbp_get_author_ip( bbp_get_reply_id() );
		} else {
			$author_ip = '';
		} ?>
		
		<?php do_action( 'bbp_theme_before_reply_author_details' ); ?>
		<?php
		/* translators: 1: comment author, 2: date and time */
		printf( __( '%1$s on %2$s <span class="says">said:</span>', 'bbpress-threaded-replies' ),
			sprintf( '<span class="fn">%s %s</span>', bbp_get_reply_author_link( array( 'type' => 'name' ) ), $author_ip ),
			sprintf( '<a href="%1$s"><time pubdate datetime="%2$s">%3$s</time></a>',
			esc_url( bbp_get_reply_url() ),
			get_the_time( 'c', bbp_get_reply_id() ),
			/* translators: 1: date, 2: time */
			sprintf( __( '%1$s at %2$s', 'bbpress-threaded-replies' ), esc_attr( get_the_time( get_option( 'date_format' ), bbp_get_reply_id() ) ), esc_attr( get_the_time( get_option( 'time_format' ), bbp_get_reply_id() ) ) )
		) ); ?>
		<?php do_action( 'bbp_theme_after_reply_author_details' ); ?>
		</div><!-- .comment-author .vcard -->
	</footer>

	<div class="comment-content">
	<?php do_action( 'bbp_theme_after_reply_content' ); ?>

	<?php bbp_reply_content(); ?>

	<?php do_action( 'bbp_theme_before_reply_content' ); ?>	
	</div>

				<div class="reply">
					<?php ucc_btr_in_reply_to_link( array_merge( $args, array( 'reply_text' => __( 'Reply <span>&darr;</span>', 'bbpress-threaded-replies' ), 'depth' => $depth, 'max_depth' => $args['max_depth'], 'add_below' => 'reply' ) ) ); ?>
				</div><!-- .reply -->
			</article><!-- #comment-## -->
<?php
} }


//  Source: wp-includes/comment-template.php
//  Derived from wp_list_comments().
if ( ! function_exists( 'ucc_btr_list_replies' ) ) {
function ucc_btr_list_replies( $args = array(), $replies = null ) {
	global $bbp, $wp_query, $reply_alt, $reply_depth, $reply_thread_alt, $overridden_rpage, $in_reply_loop;

	$in_reply_loop = true;

	$reply_alt = $reply_thread_alt = 0;
	$reply_depth = 1;

	$defaults = array( 
		'walker' => null, 
		'max_depth' => '', 
		'style' => 'ul', 
		'callback' => null, 
		'end-callback' => null, 
		'type' => 'all', 
		'page' => '', 
		'per_page' => '', 
		'avatar_size' => 32, 
		'reverse_top_level' => null, 
		'reverse_children' => ''
	);

	$args = wp_parse_args( $args, $defaults );

	if ( null !== $replies ) {
		$replies = (array) $replies;
		if ( empty( $replies ) )
			return;
		$_replies = $replies;
	} else {
		if ( empty( $bbp->replies ) )
			return;
		$_replies = $bbp->replies;
	}

	if ( '' === $args['per_page'] )
		$args['per_page'] = get_option( '_bbp_replies_per_page' );

	if ( empty( $args['per_page'] ) ) {
		$args['per_page'] = 0;
		$args['page'] = 0;
	}

	if ( '' === $args['max_depth'] ) {
		if ( get_option( 'thread_comments' ) )
			$args['max_depth'] = get_option( 'thread_comments_depth' );
		else
			$args['max_depth'] = -1;
	}

	if ( '' === $args['page'] ) {
		if ( empty( $overridden_rpage ) ) {
			$args['page'] = get_query_var( 'paged' );
		} else {
			$threaded = ( -1 != $args['max_depth'] );
			$args['page'] = ( 'newest' == get_option( 'default_comments_page' ) ) ? ucc_btr_get_reply_pages_count( $_replies, $args['per_page'], $threaded ) : 1;
			set_query_var( 'paged', $args['page'] );
		}
	}
	
	//  Validation check
	$args['page'] = intval( $args['page'] );
	if ( 0 == $args['page'] && 0 != $args['per_page'] )
		$args['page'] = 1;

	if ( null === $args['reverse_top_level'] )
		$args['reverse_top_level'] = ( 'desc' == get_option( 'comment_order' ) );

	extract( $args, EXTR_SKIP );

	if ( empty( $walker ) )
		$walker = new UCC_BTR_Walker_Reply;
	
	$walker->paged_walk( $_replies, $max_depth, $page, $per_page, $args );
	$bbp->max_num_pages = $walker->max_pages;

	$in_reply_loop = false;
} }
