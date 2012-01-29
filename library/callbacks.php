<?php


if ( !defined( 'ABSPATH' ) ) exit;


//  Source: bbpress/bbp-themes/bbp-twentyten/bbpress/loop-replies.php
//  Default callback.
if ( ! function_exists( 'ucc_btr_bbpress_reply_cb' ) ) {
function ucc_btr_bbpress_reply_cb( $_reply, $args, $depth ) {
        global $bbp, $post, $reply;

        $original_post = $post;
        $original_reply = $reply;

        //  Force WordPress/bbPress functions to use the right reply.
        $post = $_reply;
        $bbp->reply_query->post = $_reply;
        $reply = $_reply;

	if ( 'div' == $args['style'] ) {
		$tag = 'div';
		$add_below = 'reply';
	} else {
		$tag = 'li';
		$add_below = 'reply';
	} ?>

	<<?php echo $tag; ?> <?php ucc_btr_reply_class( empty( $args['has_children'] ) ? '' : 'parent' ); ?>>
	<table class="bbp-replies" id="reply-<?php bbp_reply_ID(); ?>">
	<tbody>
	<tr class="noheight">
		<td class="bbp-reply-author noheight"></td>
		<td class="bbp-reply-content noheight"></td>
	</tr>
	<?php bbp_get_template_part( 'bbpress/loop', 'single-reply' ); ?>
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
	//  Restore order.
	$post = $original_post;
	$bbp->reply_query->post = $original_post;
	$reply = $original_reply;
} }


//  Source: wp-content/themes/twentyeleven/functions.php
//  Derived from twentyeleven_comment().
if ( ! function_exists( 'ucc_btr_twentyeleven_reply_cb' ) ) {
function ucc_btr_twentyeleven_reply_cb( $_reply, $args, $depth ) {
	global $bbp, $post, $reply;

	$original_post = $post;
	$original_reply = $reply;

	//  Force WordPress/bbPress functions to use the right reply.
	$post = $_reply;
	$bbp->reply_query->post = $_reply;
	$reply = $_reply;

	if ( 'div' == $args['style'] ) {
		$tag = 'div';
		$add_below = 'reply';
	} else {
		$tag = 'li';
		$add_below = 'reply';
        } ?>

	<<?php echo $tag; ?> <?php ucc_btr_reply_class(); ?> id="li-comment-<?php bbp_reply_id(); ?>">
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
		if ( is_super_admin() )
			$author_ip = bbp_get_author_ip( bbp_get_reply_id() );
		else
			$author_ip = ''; ?>

		<?php do_action( 'bbp_theme_before_reply_author_details' ); ?>
		<?php
		/* translators: 1: comment author, 2: date and time */
		printf( __( '%1$s on %2$s <span class="says">said:</span>', 'bbpress-threaded-replies' ),
			sprintf( '<span class="fn">%s %s</span>', 
				bbp_get_reply_author_link( array( 'type' => 'name' ) ), 
				$author_ip ),
			sprintf( '<a href="%1$s"><time pubdate datetime="%2$s">%3$s</time></a>',
				esc_url( bbp_get_reply_url() ),
				get_the_time( 'c', bbp_get_reply_id() ),
				/* translators: 1: date, 2: time */
				sprintf( __( '%1$s at %2$s', 'bbpress-threaded-replies' ), 
					esc_attr( get_the_time( get_option( 'date_format' ), bbp_get_reply_id() ) ), 
					esc_attr( get_the_time( get_option( 'time_format' ), bbp_get_reply_id() ) ) )
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
	//  Restore order.
	$post = $original_post;
	$bbp->reply_query->post = $original_post;
	$reply = $original_reply;
} }
