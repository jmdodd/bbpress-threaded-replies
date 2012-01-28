<?php


if ( !defined( 'ABSPATH' ) ) exit;


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
function ucc_btr_get_page_of_reply( $reply_id ) {
	global $wpdb;
	
	$root_id = ucc_btr_get_root_element_id( $reply_id );
	$reply = get_post( $root_id );
	$topic_id = bbp_get_reply_topic_id( $reply_id );
	
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
			AND p.post_date_gmt < %s
			AND p.post_status IN ( {$post_status_in_array} )
			AND ( 
				NOT EXISTS ( 
					SELECT pm.* FROM wp_postmeta pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' 
				) OR EXISTS ( 
					SELECT pm.* FROM wp_postmeta pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' AND pm.meta_value = 0 
				) OR EXISTS ( 
					SELECT pm.* FROM wp_postmeta pm WHERE p.ID = pm.post_id AND pm.meta_key = '_ucc_btr_in_reply_to' AND pm.meta_value = %d 
		) ) ORDER BY p.post_date ASC",
		$topic_id, bbp_get_reply_post_type(), $reply->post_date_gmt, $topic_id
	);
	$previous = $wpdb->get_var( $sql );

	$posts_per_page = get_option( '_bbp_replies_per_page' );
	$page = ceil( ( $previous + 1 ) / $posts_per_page );
	
	return $page;
} }


if ( ! class_exists( 'UCC_BTR_Reply_Query' ) ) {
class UCC_BTR_Reply_Query {
	function query( $query_vars ) {
		global $bbp, $wpdb, $wp_query;

		$defaults = array(
			'post_type' => '',
			'topic_id' => 0,
			'reply_id' => '',
			'reply_status' => '',
			'in_reply_to' => '',
			'number' => '',
			'offset' => '',
			'orderby' => '',
			'order' => 'ASC',
			'posts_per_page' => -1,
			'count' => false
		);

		$this->query_vars = wp_parse_args( $query_vars, $defaults );
		do_action_ref_array( 'ucc_btr_pre_get_replies', array( &$this ) );
		extract( $this->query_vars, EXTR_SKIP );

		$key = md5( serialize( compact( array_keys( $defaults ) ) ) );
		$last_changed = wp_cache_get( 'last_changed', 'ucc_btr_reply' );
		if ( ! $last_changed ) {
			$last_changed = time();
			wp_cache_set( 'last_changed', $last_changed, 'ucc_btr_reply' );
		}

		if ( $cache = wp_cache_get( $cache_key, 'ucc_btr_reply' ) ) {
			return $cache;
		}

		$post_type = empty( $post_type ) ? bbp_get_reply_post_type() : $post_type; 

		$topic_id = absint( $topic_id );
		if ( empty( $topic_id ) )
			$topic_id = $bbp->current_topic_id;

		$in_reply_to = absint( $in_reply_to );
		$in_reply_to = ( 0 == $in_reply_to ) ? array() : array( 'key' => '_ucc_btr_in_reply_to', 'value' => $in_reply_to ); 
		
		if ( empty( $reply_status ) ) {
			$reply_status = join( ',', array( bbp_get_public_status_id(), bbp_get_closed_status_id() ) );
			if ( bbp_get_view_all( 'edit_others_replies' ) ) {
				$reply_status = join( ',', array( bbp_get_public_status_id(), bbp_get_closed_status_id(), bbp_get_spam_status_id(), bbp_get_trash_status_id() ) );
			}
			$reply_status = explode( ',', $reply_status );
		}

		$number = absint( $number );
		$offset = absint( $offset );

		if ( ! empty( $orderby ) ) {
			$ordersby = is_array( $orderby ) ? $orderby : preg_split( '/[,\s]/', $orderby );
			$ordersby = array_intersect(
				$ordersby,
				array(
					'post_author',
					'post_date',
					'post_date_gmt',
					'post_content',
					'post_title',
					'post_name',
					'post_modified',
					'post_modified_gmt',
					'post_parent',
					'post_type'
				)
			);
			$orderby = empty( $ordersby ) ? 'post_date_gmt' : $ordersby;
		} else {
			$orderby = 'post_date_gmt';
		}
		
		$order = ( 'ASC' == strtoupper( $order ) ) ? 'ASC' : 'DESC';

		$args = array(
			'post_type' => $post_type,
			'post_parent' => $topic_id,
			'post_status' => $reply_status,
			'orderby' => $orderby,
			'order' => $order,
			'posts_per_page' => $posts_per_page
		);
		
		if ( ! empty( $number ) )
			$args = array_merge( $args, array( 'number' => $number ) );
		if ( ! empty( $offset ) )
			$args = array_merge( $args, array( 'offset' => $offset ) );
		if ( ! empty( $in_reply_to ) )
			$args = array_merge( $args, array( 'meta_query' => $in_reply_to ) );

		$replies = new WP_Query( $args );
		$replies = apply_filters_ref_array( 'ucc_btr_the_replies', array( $replies, &$this ) );
		
		if ( $count )
			return $replies->found_posts;

		wp_cache_add( $cache_key, $replies, 'ucc_btr_reply' );

		$this->found_posts = $replies->found_posts;
		
		//  Fix in_reply_to, since post_parent is already taken.
		$posts = &$replies->posts;
		foreach ( $posts as $id => &$post ) {
			$post->in_reply_to = absint( get_post_meta( $post->ID, '_ucc_btr_in_reply_to', true ) );
		}
		
		$wp_query->replies = $replies->posts;
		$bbp->replies = $replies->posts;
		return $replies;
	} 
} }


if ( ! function_exists( 'ucc_btr_get_replies' ) ) {
function ucc_btr_get_replies( $args = '' ) {
	$query = new UCC_BTR_Reply_Query;
	return $query->query( $args );
} }
