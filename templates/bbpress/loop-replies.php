<?php


if ( ! defined( 'ABSPATH' ) ) exit;


//  Source: bbpress/bbp-themes/bbp-twentyten/bbpress/loop-replies.php
?>

	<?php do_action( 'bbp_template_before_replies_loop' ); ?>

	<table class="bbp-replies" id="topic-<?php bbp_topic_id(); ?>-replies">
		<thead>
			<tr>
				<th class="bbp-reply-author"><?php  _e( 'Author',  'bbpress' ); ?></th>
				<th class="bbp-reply-content">

					<?php if ( !bbp_show_lead_topic() ) : ?>

						<?php _e( 'Posts', 'bbpress' ); ?>

						<?php bbp_user_subscribe_link(); ?>

						<?php bbp_user_favorites_link(); ?>

					<?php else : ?>

						<?php _e( 'Replies', 'bbpress' ); ?>

					<?php endif; ?>

				</th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<th class="bbp-reply-author"><?php  _e( 'Author',  'bbpress' ); ?></th>
				<th class="bbp-reply-content">

					<?php if ( !bbp_show_lead_topic() ) : ?>

						<?php _e( 'Posts', 'bbpress' ); ?>

					<?php else : ?>

						<?php _e( 'Replies', 'bbpress' ); ?>

					<?php endif; ?>

				</th>
			</tr>
		</tfoot>

		<tbody>
			
			<?php if ( ! bbp_show_lead_topic() ) : ?>
				
				<?php bbp_the_reply(); ?>
				
				<?php bbp_get_template_part( 'bbpress/loop', 'single-reply' ); ?>
				
			<?php endif; ?>
		
		</tbody>
	</table>
	
	<ol class="replylist">
			
	<?php ucc_btr_list_replies(); ?>

	</ol>

	<?php do_action( 'bbp_template_after_replies_loop' ); ?>
