<?php


if ( !defined( 'ABSPATH' ) ) exit;


// Default table-based callback (bbp-twentyten) for Walker_Comment.
if ( !function_exists( 'ucc_btr_bbpress_reply_cb' ) ) {
function ucc_btr_bbpress_reply_cb( $_reply, $args, $depth ) {
	global $bbp, $post, $reply;

	if ( is_object( $bbp ) ) {
		// bbPress version < 2.1 or already initialized.
	} elseif ( function_exists( 'bbpress' ) ) {
		$bbp = bbpress();
	}

	$original_post = $post;
	$original_reply = $reply;

	// Force WordPress/bbPress functions to use the right reply.
	$post = $_reply;
	$bbp->reply_query->post = $_reply;
	$reply = $_reply;

	if ( 'div' == $args['style'] ) {
		$tag = 'div';
		$add_below = 'reply';
	} else {
		$tag = 'li';
		$add_below = 'reply';
	} 

	$reply_text = __( 'Reply &darr;', 'bbpress-threaded-replies' );
	?>

	<<?php echo $tag; ?> <?php ucc_btr_reply_class( empty( $args['has_children'] ) ? '' : 'parent' ); ?>>
	<table class="bbp-replies" id="reply-<?php bbp_reply_ID(); ?>">
	<tbody>
	<?php bbp_get_template_part( 'bbpress/loop', 'single-reply' ); ?>
	<tr>
		<td colspan="2" class="ucc-bbp-in-reply-to">
		<div class="reply">
		<?php ucc_btr_in_reply_to_link( array_merge( $args, array( 'reply_text' => $reply_text, 'add_below' => $add_below, 'depth' => $depth, 'max_depth' => $args['max_depth'] ) ) ); ?>
		</div>
		</td>
	</tr>
	</tbody>
	</table>

	<?php
	// Restore order.
	$post = $original_post;
	$bbp->reply_query->post = $original_post;
	$reply = $original_reply;
} }


// Div-based callback (bbp-theme-compat) for Walker_Comment. 
if ( !function_exists( 'ucc_btr_bbpress_theme_compat_reply_cb' ) ) {
function ucc_btr_bbpress_theme_compat_reply_cb( $_reply, $args, $depth ) {
	global $bbp, $post, $reply;

	if ( is_object( $bbp ) ) {
		// bbPress version < 2.1 or already initialized.
	} elseif ( function_exists( 'bbpress' ) ) {
		$bbp = bbpress();
	}

	$original_post = $post;
	$original_reply = $reply;

	// Force WordPress/bbPress functions to use the right reply.
	$post = $_reply;
	$bbp->reply_query->post = $_reply;
	$reply = $_reply;

	if ( 'div' == $args['style'] ) {
		$tag = 'div';
		$add_below = 'reply';
	} else {
		$tag = 'li';
		$add_below = 'reply';
	}

	$reply_text = __( 'Reply &darr;', 'bbpress-threaded-replies' );
	?>

<<?php echo $tag; ?>>
<div id="reply-<?php bbp_reply_ID(); ?>" <?php ucc_btr_reply_class( empty( $args['has_children'] ) ? '' : 'parent' ); ?>>
	<?php bbp_get_template_part( 'bbpress/loop', 'single-reply' ); ?>
	<div class="ucc-bbp-in-reply-to">
		<div class="reply">
			<?php ucc_btr_in_reply_to_link( array_merge( $args, array( 'reply_text' => $reply_text, 'add_below' => $add_below, 'depth' => $depth, 'max_depth' => $args['max_depth'] ) ) ); ?>
		</div>
	</div>
</div>
	<?php
	// Restore order.
	$post = $original_post;
	$bbp->reply_query->post = $original_post;
	$reply = $original_reply;
} }
