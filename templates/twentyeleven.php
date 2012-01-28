<?php


if ( ! defined( 'ABSPATH' ) ) exit;


//  Source: wp-content/themes/twentyeleven/index.php
//  Source: bbpress/bbp-themes/bbp-twentyten/single-topic.php
?>
<?php get_header(); ?>

		<div id="primary">
        	<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php if ( bbp_user_can_view_forum( array( 'forum_id' => bbp_get_topic_forum_id() ) ) ) : ?>

					<?php while ( have_posts() ) : the_post(); ?>
						
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h1 class="entry-title"><?php the_title(); ?></h1>
	</header><!-- .entry-header -->

	<div class="entry-content">
								<?php bbp_breadcrumb(); ?>

								<?php do_action( 'bbp_template_before_single_topic' ); ?>

								<?php if ( post_password_required() ) : ?>

									<?php bbp_get_template_part( 'bbpress/form', 'protected' ); ?>

								<?php else : ?>

									<?php bbp_topic_tag_list(); ?>

									<?php bbp_single_topic_description(); ?>

									<?php bbp_get_template_part( 'bbpress/content', 'single-topic-lead' ); ?>
									
								<?php endif; ?>
								
	</div><!-- .entry-content -->

	<footer class="entry-meta">
	</footer><!-- .entry-meta -->
</article><!-- #post- -->

								<?php if ( ! post_password_required() ) : ?>

									<?php ucc_btr_replies_template(); ?>

								<?php endif; ?>

								<?php do_action( 'bbp_template_after_single_topic' ); ?>

					<?php endwhile; ?>

				<?php elseif ( bbp_is_forum_private( bbp_get_topic_forum_id(), false ) ) : ?>

					<?php bbp_get_template_part( 'bbpress/feedback', 'no-access' ); ?>

				<?php endif; ?>

				</div><!-- #content -->
			</div><!-- #primary -->

<?php get_footer(); ?>
