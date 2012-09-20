<?php


if ( !defined( 'ABSPATH' ) ) exit;


if ( !function_exists( 'ucc_btr_get_expires' ) ) {
function ucc_btr_get_expires() {
	$expires = apply_filters( 'ucc_btr_expires', 3600 );
	return $expires;
} }


if ( !function_exists( 'ucc_btr_get_root_element_id' ) ) {
function ucc_btr_get_root_element_id( $reply_id ) {
	$in_reply_to = get_post_meta( $reply_id, '_ucc_btr_in_reply_to', true );
	if ( empty( $in_reply_to ) || ( bbp_get_reply_topic_id( $reply_id ) == $in_reply_to ) )
		return $reply_id;
	else
		return ucc_btr_get_root_element_id( $in_reply_to );
} }


if ( !function_exists( 'ucc_btr_get_root_element_count' ) ) {
function ucc_btr_get_root_element_count( $topic_id, $all = false ) {
	global $wpdb;

	// Check cache first.
	$group = 'ucc_btr';
	$expires = ucc_btr_get_expires();
	if ( bbp_get_view_all( 'edit_others_replies' ) || $all )
		$key = 'reply_count_all';
	else
		$key = 'reply_count';

	$counts = wp_cache_get( $key, $group );
	if ( empty( $counts ) ) {
		$counts = array();
	} elseif ( is_array( $counts ) && array_key_exists( $topic_id, $counts ) ) {
		return $counts[$topic_id];
	}

	$post_status = join( ',', array( bbp_get_public_status_id(), bbp_get_closed_status_id() ) );
	if ( bbp_get_view_all( 'edit_others_replies' ) || $all ) 
		$post_status = join( ',', array( bbp_get_public_status_id(), bbp_get_closed_status_id(), bbp_get_spam_status_id(), bbp_get_trash_status_id() ) );
	$post_status = explode( ',', $post_status );
	$post_status_in_array = array();
	foreach ( (array) $post_status as $ps )
		$post_status_in_array[] = $wpdb->prepare( "%s", $ps );
	$post_status_in_array = implode( ',', $post_status_in_array );

	// Direct SQL is more efficient than trying to use WP_Query here.
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
	$reply_count = (int) $wpdb->get_var( $sql );

	// Populate cache.
	$counts[$topic_id] = (int) $reply_count;
	wp_cache_set( $key, $counts, $group, $expires );

	return $reply_count;
} }


// Source: wp-includes/comment.php
// Derived from get_comment_pages_count().
if ( !function_exists( 'ucc_btr_get_reply_pages_count' ) ) {
function ucc_btr_get_reply_pages_count( $replies = null, $per_page = null, $threaded = null ) {
	global $bbp, $wp_query;

	if ( is_object( $bbp ) ) {
		// bbPress version < 2.1 or already initialized.
	} elseif ( function_exists( 'bbpress' ) ) {
		$bbp = bbpress();
	}

	if ( null === $replies && null === $per_page && null === $threaded && !empty( $bbp->max_num_pages ) )
		return $bbp->max_num_pages;

	if ( !$replies || !is_array( $replies ) )
		$replies = $bbp->replies;

	if ( empty( $replies ) )
		return 0;

	$options = get_option( '_ucc_btr_options' );

	if ( !$options['page_replies'] )
		return 0;

	if ( !isset( $per_page ) )
		$per_page = (int) $options['replies_per_page'];
	if ( 0 === $per_page )
		return 1;

	if ( !isset( $threaded ) )
		$threaded = $options['thread_replies']; 

	if ( $threaded ) {
		$count = (int) ucc_btr_get_root_element_count( bbp_get_topic_id() );
		$total = ceil( $count / $per_page );
	} else {
		$total = ceil( count( $replies ) / $per_page );
	}

	return $total;
} }


// Source: wp-includes/comment.php
// Derived from get_page_of_comment().
if ( !function_exists( 'ucc_btr_get_page_of_reply' ) ) {
function ucc_btr_get_page_of_reply( $reply_id, $all = false ) {
	global $wpdb;

	$topic_id = bbp_get_reply_topic_id( $reply_id );

	// Check for paging.
	$options = get_option( '_ucc_btr_options' );
	if ( !$options['page_replies'] )
		return 1;

	// Check cache first.
	$group = 'ucc_btr';
	$expires = ucc_btr_get_expires();
	if ( bbp_get_view_all( 'edit_others_replies' ) || $all )
		$key = 'reply_pages_all';
	else
		$key = 'reply_pages';

	$pages = wp_cache_get( $key, $group );
	if ( empty( $pages ) ) {
		$pages = array();
	} elseif ( is_array( $pages ) ) {
		if ( array_key_exists( $topic_id, $pages ) ) {
			if ( array_key_exists( $reply_id, $pages[$topic_id] ) )
				return $pages[$topic_id][$reply_id];
		} else {
			$pages[$topic_id] = array();
		}
	}

	$root_id = ucc_btr_get_root_element_id( $reply_id );
	$post_date_gmt = get_post_time( 'Y-m-d H:i:s', true, $root_id );

	$post_status = join( ',', array( bbp_get_public_status_id(), bbp_get_closed_status_id() ) );
	if ( bbp_get_view_all( 'edit_others_replies' ) || $all ) 
		$post_status = join( ',', array( bbp_get_public_status_id(), bbp_get_closed_status_id(), bbp_get_spam_status_id(), bbp_get_trash_status_id() ) );
	$post_status = explode( ',', $post_status );
	$post_status_in_array = array();
	foreach ( (array) $post_status as $ps )
		$post_status_in_array[] = $wpdb->prepare( "%s", $ps );
	$post_status_in_array = implode( ',', $post_status_in_array );

	// Direct SQL is more efficient than trying to use WP_Query here.
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

	// Calculate page.
	$options = get_option( '_ucc_btr_options' );
	$replies_per_page = $options['replies_per_page']; 
	$page = ceil( ( $previous + 1 ) / $replies_per_page );

        // Deal with $overridden_rpage.
        $options = get_option( '_ucc_btr_options' );
        if ( array_key_exists( 'default_replies_page', $options ) && $options['default_replies_page'] == 'newest' ) {
		$total = ceil( ucc_btr_get_root_element_count( $topic_id ) / $replies_per_page );
		$page = $total - $page + 1;
        }

	// Populate cache.
	$pages[$topic_id][$reply_id] = $page;
	wp_cache_set( $key, $pages, $group, $expires );

	return $page;
} }


// Source: wp-includes/comment-template.php
// Derived from get_comment_link().
if ( !function_exists( 'ucc_btr_get_reply_link' ) ) {
function ucc_btr_get_reply_link( $url, $reply_id, $redirect_to ) {
	global $wp_rewrite;

	$reply_page = ucc_btr_get_page_of_reply( $reply_id );
	$topic_id = bbp_get_reply_topic_id( $reply_id );
	$topic_url = bbp_get_topic_permalink( $topic_id, $redirect_to );
	$reply_hash = '#reply-' . $reply_id;

	$topic_url = remove_query_arg( 'view', $topic_url );

	// Deal with pagination and permalink structure.
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


// Source: wp-includes/comment-template.php
// Derived from get_comment_class().
if ( !function_exists( 'ucc_btr_get_reply_class' ) ) {
function ucc_btr_get_reply_class( $class = '', $reply_id = null, $topic_id = null ) {
	global $reply, $reply_alt, $reply_depth, $reply_thread_alt;

	if ( null == $reply_id )
		$reply_id = bbp_get_reply_id();
	$topic = get_post( bbp_get_reply_topic_id( $reply_id ) );
	$classes = array();

	$classes[] = 'reply';

	// If the reply author is a user, print the cleaned user_nicename.
	if ( $reply->post_author > 0 && $user = get_userdata( $reply->post_author ) ) {
		// For all registered users, 'byuser'.
		$classes[] = 'byuser';
		$classes[] = 'reply-author-' . sanitize_html_class( $user->user_nicename, $reply->post_author );
		// For reply authors who are the author of the topic.
		if ( $reply->post_author === $topic->post_author ) {
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

	// Alt for top-level replies.
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

	if ( !empty( $class ) ) {
		if ( !is_array( $class ) )
			$class = preg_split( '#\s+#', $class );
		$classes = array_merge( $classes, $class );
	}

	$classes = array_map( 'esc_attr', $classes );

	return apply_filters( 'ucc_btr_reply_class', $classes, $class, $reply->ID, $topic->ID );
} }


// Source: wp-includes/comment-template.php
// Derived from comment_class().
if ( !function_exists( 'ucc_btr_reply_class' ) ) {
function ucc_btr_reply_class( $class = '', $reply_id = null, $topic_id = null, $echo = true ) {
	$class = 'class="' . join( ' ', ucc_btr_get_reply_class( $class, $reply_id, $topic_id ) ) . '"';
	if ( $echo )
		echo $class;
	else
		return $class;
} }


// Source: wp-includes/comment-template.php
// Derived from get_comment_reply_link().
if ( !function_exists( 'ucc_btr_get_in_reply_to_link' ) ) {
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


// Source: wp-includes/comment-template.php
// Derived from comment_reply_link().
if ( !function_exists( 'ucc_btr_in_reply_to_link' ) ) {
function ucc_btr_in_reply_to_link( $args = array(), $reply = null, $topic = null ) {
	echo ucc_btr_get_in_reply_to_link( $args, $reply, $topic );
} }


// Source: wp-includes/comment-template.php
// Derived from get_cancel_comment_reply_link().
if ( !function_exists( 'ucc_btr_get_cancel_in_reply_to_link' ) ) {
function ucc_btr_get_cancel_in_reply_to_link( $text = '' ) {
	if ( empty( $text ) )
		$text = __( 'Click here to cancel reply.', 'bbpress-threaded-replies' );

	$style = isset( $_GET['inreplyto'] ) ? '' : ' style="display:none;"';
	$link = esc_html( remove_query_arg( array( 'inreplyto', 'inreplyto_nonce' ) ) ) . '#respond';

	return apply_filters( 'ucc_btr_cancel_in_reply_to_link', '<a rel="nofollow" id="cancel-in-reply-to-link" href="' . $link . '"' . $style . '>' . $text . '</a>', $link, $text );
} }


// Source: wp-includes/comment-template.php
// Derived from get_cancel_comment_reply_link().
if ( !function_exists( 'ucc_btr_cancel_in_reply_to_link' ) ) {
function ucc_btr_cancel_in_reply_to_link( $text = '' ) {
	echo ucc_btr_get_cancel_in_reply_to_link( $text );
} }


// Source: wp-includes/comment-template.php
// Derived from wp_list_comments().
if ( !function_exists( 'ucc_btr_list_replies' ) ) {
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

	$options = get_option( '_ucc_btr_options' );

	if ( '' === $r['per_page'] && $options['page_replies'] )
		$r['per_page'] = $options['replies_per_page'];

	if ( empty( $r['per_page'] ) ) {
		$r['per_page'] = 0;
		$r['page'] = 0;
	}

	if ( '' === $r['max_depth'] ) {
		if ( $options['thread_replies'] )
			$r['max_depth'] = $options['thread_replies_depth'];
		else
			$r['max_depth'] = -1;
	}

	if ( '' === $r['page'] ) {
		if ( empty( $overridden_rpage ) ) {
			$r['page'] = get_query_var( 'paged' );
		} else {
			// Handle $overridden_rpage here.
			$threaded = ( -1 != $r['max_depth'] );
			$paged = get_query_var( 'paged' );
			$paged = ( !empty( $paged ) ) ? (int) $paged : 1; 
			$r['page'] = ( 'newest' == $options['default_replies_page'] ) ? ucc_btr_get_reply_pages_count( $_replies, $r['per_page'], $threaded ) - $paged + 1 : 1;
			set_query_var( 'paged', $r['page'] );
			$page = $r['page'];
		}
	}

	// Validation check.
	$r['page'] = intval( $r['page'] );
	if ( 0 == $r['page'] && 0 != $r['per_page'] )
		$r['page'] = 1;

	if ( null === $r['reverse_top_level'] )
		$r['reverse_top_level'] = ( 'desc' == $options['reply_order'] );

	extract( $r, EXTR_SKIP );

	if ( empty( $walker ) )
		$walker = new Walker_Comment;
	$walker->db_fields = array( 'parent' => 'in_reply_to', 'id' => 'ID' );

	$walker->paged_walk( $_replies, $max_depth, $page, $per_page, $r );
	$bbp->max_num_pages = $walker->max_pages;

	$in_reply_loop = false;
} }

