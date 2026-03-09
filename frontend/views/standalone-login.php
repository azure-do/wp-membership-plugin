<?php

/**
 * 会員ログインページ（/lft_membership/login/）
 * どのサイトでも使えるスタンドアロン
 *
 * @package LFT_Membership
 * @var string $redirect_to
 * @var string $error_message
 */

defined('ABSPATH') || exit;

$login_action = home_url('/' . LFT_MEMBERSHIP_SLUG . '/login/');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>会員ログイン - <?php bloginfo('name'); ?></title>
	<?php wp_head(); ?>
</head>

<body class="lft-membership-register-page lft-login-page">
	<div class="lft-register-wrap">
		<header class="lft-register-header">
			<img src="<?php echo esc_url( LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/images/logo.png' ); ?>" alt="LFT - 生前対策・家族信託コミュニティー ~ Life Family Trust ~" class="lft-register-logo-img" />
		</header>

		<div class="lft-register-card">
			<h2 class="lft-register-title">会員ログイン</h2>

			<?php if ($error_message) : ?>
				<p class="lft-register-errors"><?php echo esc_html($error_message); ?></p>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url($login_action); ?>" class="lft-register-form">
				<?php wp_nonce_field('lft_member_login', 'lft_member_login_nonce'); ?>
				<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />

				<p class="lft-form-row">
					<label for="lft-standalone-email">メール</label>
					<input type="email" id="lft-standalone-email" name="log" value="" required class="lft-input" placeholder="" />
				</p>
				<p class="lft-form-row">
					<label for="lft-standalone-pwd">パスワード</label>
					<input type="password" id="lft-standalone-pwd" name="pwd" required class="lft-input" />
				</p>
				<p class="lft-form-actions">
					<button type="submit" class="lft-btn lft-btn--submit">ログイン</button>
				</p>
			</form>
			<p class="lft-login-links">
				<a href="<?php echo esc_url(wp_lostpassword_url($redirect_to)); ?>">パスワードをお忘れですか?</a>
			</p>
			<p class="lft-membership-password-form-hint">初めての方は、管理者からお渡しした登録用URLから会員登録を行ってください。</p>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>

</html>