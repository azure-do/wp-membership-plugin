<?php
/**
 * トークン無効・エラー表示
 *
 * @package LFT_Membership
 * @var string $message
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>エラー - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="lft-membership-register-page lft-error-page">
	<div class="lft-register-wrap">
		<header class="lft-register-header">
			<img src="<?php echo esc_url( LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/images/logo.png' ); ?>" alt="LFT - 生前対策・家族信託コミュニティー ~ Life Family Trust ~" class="lft-register-logo-img" />
		</header>
		<div class="lft-register-card">
			<p class="lft-register-errors"><?php echo wp_kses_post( $message ); ?></p>
			<p class="lft-error-actions"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="lft-btn lft-btn--secondary">トップへ戻る</a></p>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
