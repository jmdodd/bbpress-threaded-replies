<?php
// Source: bbp-theme-compat/bbpress/loop-replies.php
?>

<?php do_action( 'bbp_template_before_replies_loop' ); ?>

<ul id="topic-<?php bbp_topic_id(); ?>-replies" class="forums bbp-replies">

	<li class="bbp-header">

		<div class="bbp-reply-author"><?php  _e( 'Author',  'bbpress-threaded-replies' ); ?></div><!-- .bbp-reply-author -->

		<div class="bbp-reply-content">

			<?php if ( !bbp_show_lead_topic() ) : ?>

				<?php _e( 'Posts', 'bbpress-threaded-replies' ); ?>

				<?php bbp_user_subscribe_link(); ?>

				<?php bbp_user_favorites_link(); ?>

			<?php else : ?>

				<?php _e( 'Replies', 'bbpress-threaded-replies' ); ?>

			<?php endif; ?>

		</div><!-- .bbp-reply-content -->

	</li><!-- .bbp-header -->

	<li class="bbp-body">

		<?php if ( bbp_replies() ) : bbp_the_reply(); ?>

			<?php bbp_get_template_part( 'loop', 'single-reply' ); ?>

			<ol class="replylist">

				<?php ucc_btr_list_replies( array( 'callback' => 'ucc_btr_bbpress_theme_compat_reply_cb' ) ); ?>

			</ol>

		<?php endif; ?>

	</li><!-- .bbp-body -->

	<li class="bbp-footer">

		<div class="bbp-reply-author"><?php  _e( 'Author',  'bbpress-threaded-replies' ); ?></div>

		<div class="bbp-reply-content">

			<?php if ( !bbp_show_lead_topic() ) : ?>

				<?php _e( 'Posts', 'bbpress-threaded-replies' ); ?>

			<?php else : ?>

				<?php _e( 'Replies', 'bbpress-threaded-replies' ); ?>

			<?php endif; ?>

		</div><!-- .bbp-reply-content -->

	</li>

</ul><!-- #topic-<?php bbp_topic_id(); ?>-replies -->

<?php do_action( 'bbp_template_after_replies_loop' ); ?>
