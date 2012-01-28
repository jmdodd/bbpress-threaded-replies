<?php


if ( ! defined( 'ABSPATH' ) ) exit;


//  Source: bbpress/bbp-themes/bbp-twentyten/single-topic.php
?>
<?php get_header(); ?>

		<div id="container primary">
			<div id="content" role="main">

				<?php do_action( 'bbp_template_notices' ); ?>

				<?php if ( bbp_user_can_view_forum( array( 'forum_id' => bbp_get_topic_forum_id() ) ) ) : ?>

					<?php while ( have_posts() ) : the_post(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h1 class="entry-title"><?php the_title(); ?></h1>
	</header><!-- .entry-header -->

	<div class="entry-content">

						<div id="bbp-topic-wrapper-<?php bbp_topic_id(); ?>" class="bbp-topic-wrapper">

								<?php include( 'bbpress/content-single-topic.php' ); ?>

						</div><!-- #bbp-topic-wrapper-<?php bbp_topic_id(); ?> -->
					
	</div><!-- .entry-content -->

	<footer class="entry-meta">
	</footer><!-- .entry-meta -->
</article><!-- #post- -->

					<?php endwhile; ?>

				<?php elseif ( bbp_is_forum_private( bbp_get_topic_forum_id(), false ) ) : ?>

					<?php bbp_get_template_part( 'bbpress/feedback', 'no-access' ); ?>

				<?php endif; ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_footer(); ?>
