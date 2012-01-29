<?php


if ( !defined( 'ABSPATH' ) ) exit;


if ( ! function_exists( 'ucc_btr_get_expires' ) ) {
function ucc_btr_get_expires() {
	$expires = apply_filters( 'ucc_btr_expires', 3600 );
	return $expires;
} }


if ( ! function_exists( 'ucc_btr_get_root_element_id' ) ) {
function ucc_btr_get_root_element_id( $reply_id ) {
	$in_reply_to = get_post_meta( $reply_id, '_ucc_btr_in_reply_to', true );
	if ( empty( $in_reply_to ) || ( bbp_get_reply_topic_id( $reply_id ) == $in_reply_to ) )
		return $reply_id;
	else
		return ucc_btr_get_root_element_id( $in_reply_to );
} }


if ( ! function_exists( 'ucc_btr_get_root_element_count' ) ) {
function ucc_btr_get_root_element_count( $topic_id, $all = false ) {
	global $wpdb;

	$group = 'ucc-btr';
	$expires = ucc_btr_get_expires();
	if ( bbp_get_view_all( 'edit_others_replies' ) || $all )
		$cache_key = 'topic_count_all_' . $topic_id;
	else
		$cache_key = 'topic_count_' . $topic_id;
	
	$count = wp_cache_get( $cache_key, $group );
	if ( false !== $count )
		return $count;
	
	$post_status = join( ',', array( bbp_get_public_status_id(), bbp_get_closed_status_id() ) );
	if ( bbp_get_view_all( 'edit_others_replies' ) || $all ) {
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
					SELECT pm.* FROM {$wpdb->postmeta} pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' 
				) OR EXISTS ( 
					SELECT pm.* FROM {$wpdb->postmeta} pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' AND pm.meta_value = 0 
				) OR EXISTS ( 
					SELECT pm.* FROM {$wpdb->postmeta} pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' AND pm.meta_value = %d 
		) ) ORDER BY p.post_date_gmt ASC",
		$topic_id, bbp_get_reply_post_type(), $topic_id
	);
	$count = $wpdb->get_var( $sql );

	wp_cache_set( $cache_key, $count, $group, $expires );
	
	return $count;
} }


//  Source: wp-includes/comment.php
//  Derived from get_comment_pages_count().
if ( ! function_exists( 'ucc_btr_get_reply_paged_count' ) ) {
function ucc_btr_get_reply_pages_count( $replies = null, $per_page = null, $threaded = null ) {
	global $bbp, $wp_query;

	if ( null === $replies && null === $per_page && null === $threaded && ! empty( $bbp->max_num_pages ) )
		return $bbp->max_num_pages;

	if ( ! $replies || ! is_array( $replies ) )
		$replies = $bbp->replies;

	if ( empty( $replies ) )
		return 0;

	if ( ! isset( $per_page ) )
		$per_page = (int) get_option( '_bbp_replies_per_page' );
	if ( 0 === $per_page )
		$per_page = (int) get_option( 'comments_per_page' );
	if ( 0 === $per_page )
		return 1;

	if ( ! isset( $threaded ) )
		$threaded = get_option( 'thread_comments' );

	if ( $threaded ) {
		$count = (int) ucc_btr_get_root_element_count( bbp_get_topic_id() );
		$total = ceil( $count / $per_page );
	} else {
		$total = ceil( count( $replies ) / $per_page );
	}

	return $total;
} }


//  Source: wp-includes/comment.php
//  Derived from get_page_of_comment().
if ( ! function_exists( 'ucc_btr_get_page_of_reply' ) ) {
function ucc_btr_get_page_of_reply( $reply_id, $all = false ) {
	global $wpdb;

	$topic_id = bbp_get_reply_topic_id( $reply_id );

	$group = 'ucc-btr';
	$expires = ucc_btr_get_expires();
	if ( bbp_get_view_all( 'edit_others_replies' ) || $all )
		$cache_key = 'topic_pages_all_' . $topic_id;
	else
		$cache_key = 'topic_pages_' . $topic_id;

	$pages = wp_cache_get( $cache_key, $group );

	if ( empty( $pages ) ) 
		$pages = array();
	elseif ( is_array( $pages ) && array_key_exists( $reply_id, $pages ) ) 
		return $pages[$reply_id];

	$root_id = ucc_btr_get_root_element_id( $reply_id );
	$post_date_gmt = get_post_time( 'Y-m-d H:i:s', true, $root_id );

	$post_status = join( ',', array( bbp_get_public_status_id(), bbp_get_closed_status_id() ) );
	if ( bbp_get_view_all( 'edit_others_replies' ) || $all ) {
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
			AND p.post_date_gmt < %s
			AND p.post_status IN ( {$post_status_in_array} )
			AND (
				NOT EXISTS (
					SELECT pm.* FROM {$wpdb->postmeta} pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to'
				) OR EXISTS (
					SELECT pm.* FROM {$wpdb->postmeta} pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' AND pm.meta_value = 0
				) OR EXISTS (
					SELECT pm.* FROM {$wpdb->postmeta} pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' AND pm.meta_value = %d
		) ) ORDER BY p.post_date_gmt ASC",
		$topic_id, bbp_get_reply_post_type(), $post_date_gmt, $topic_id
	);
	$previous = $wpdb->get_var( $sql );

	$posts_per_page = get_option( '_bbp_replies_per_page' );
	$page = ceil( ( $previous + 1 ) / $posts_per_page );

	$pages[$reply_id] = $page;
	wp_cache_set( $cache_key, $pages, $group, $expires );	

	return $page;
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

	$topic_url = remove_query_arg( 'view', $topic_url );

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

	if ( bbp_get_view_all() )
		$url = bbp_add_view_all( $url );

	return $url;
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
function ucc_btr_get_in_reply_to_link( $args = array(), $_reply = null, $topic = null ) {
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

	$r = wp_parse_args( $args, $defaults );

	if ( 0 == $r['depth'] || $r['max_depth'] <= $r['depth'] )
		return;

	extract( $r, EXTR_SKIP );

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
		'callback' => 'ucc_btr_bbpress_reply_cb', 
		'end-callback' => null, 
		'type' => 'all', 
		'page' => '', 
		'per_page' => '', 
		'avatar_size' => 32, 
		'reverse_top_level' => null, 
		'reverse_children' => ''
	);

	$r = wp_parse_args( $args, $defaults );

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

	if ( '' === $r['per_page'] )
		$r['per_page'] = get_option( '_bbp_replies_per_page' );

	if ( empty( $r['per_page'] ) ) {
		$r['per_page'] = 0;
		$r['page'] = 0;
	}

	if ( '' === $r['max_depth'] ) {
		if ( get_option( 'thread_comments' ) )
			$r['max_depth'] = get_option( 'thread_comments_depth' );
		else
			$r['max_depth'] = -1;
	}

	if ( '' === $r['page'] ) {
		if ( empty( $overridden_rpage ) ) {
			$r['page'] = get_query_var( 'paged' );
		} else {
			$threaded = ( -1 != $r['max_depth'] );
			$r['page'] = ( 'newest' == get_option( 'default_comments_page' ) ) ? ucc_btr_get_reply_pages_count( $_replies, $r['per_page'], $threaded ) : 1;
			set_query_var( 'paged', $r['page'] );
		}
	}
	
	//  Validation check
	$r['page'] = intval( $r['page'] );
	if ( 0 == $r['page'] && 0 != $r['per_page'] )
		$r['page'] = 1;

	if ( null === $r['reverse_top_level'] )
		$r['reverse_top_level'] = ( 'desc' == get_option( 'comment_order' ) );

	extract( $r, EXTR_SKIP );

	if ( empty( $walker ) )
		$walker = new Walker_Comment;
	$walker->db_fields = array( 'parent' => 'in_reply_to', 'id' => 'ID' );
	
	$walker->paged_walk( $_replies, $max_depth, $page, $per_page, $r );
	$bbp->max_num_pages = $walker->max_pages;

	$in_reply_loop = false;
} }
