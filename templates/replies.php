<?php


if ( ! defined( 'ABSPATH' ) ) exit;


//  Source: wp-content/themes/twentyeleven/comments.php
?>

<?php 
if ( post_password_required() ) {
	//  This is handled in the parent template file, but we'll be careful anyway.
	return;
} ?>

<div id="comments"> 

<?php
if ( bbp_has_replies() ) { ?>
	<h2 id="comments-title">
	<?php printf( _n( 'One reply to &ldquo;%2$s&rdquo;', '%1$s replies to &ldquo;%2$s&rdquo;', bbp_get_topic_reply_count(), 'bbpress-threaded-replies' ), number_format_i18n( bbp_get_topic_reply_count() ), '<span>' . get_the_title() . '</span>' ); ?>
	</h2>
	
	<?php
	if ( bbp_get_query_name() || bbp_has_replies() ) { ?>
		<nav id="comment-nav-above">
			<h1 class="assistive-text"><?php _e( 'Reply navigation', 'bbpress-threaded-replies' ); ?></h1>
			<?php bbp_get_template_part( 'bbpress/pagination', 'replies' ); ?>
		</nav>
		<?php
	} ?>

	<ol class="commentlist">
	<?php ucc_btr_list_replies( array( 'callback' => 'ucc_btr_twentyeleven_reply_cb' ) ); ?>
	</ol>

	<?php
	if ( bbp_get_query_name() || bbp_has_replies() ) { ?>
		<nav id="comment-nav-above">
			<h1 class="assistive-text"><?php _e( 'Reply navigation', 'bbpress-threaded-replies' ); ?></h1>
			<?php bbp_get_template_part( 'bbpress/pagination', 'replies' ); ?>
		</nav>
		<?php
	}
} ?>

<?php bbp_get_template_part( 'bbpress/form', 'reply' ); ?>

</div><!-- #comments -->
