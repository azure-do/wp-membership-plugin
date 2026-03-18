<?php

/**
 * ログイン画面（トークンURL用・スタンドアロン共通）
 * トークン時: メール表示のみ・パスワード入力。スタンドアロン時: メール＋パスワード入力。
 *
 * @package LFT_Membership
 * @var object|null $member  会員（null のときスタンドアロン＝メール入力あり）
 * @var string      $redirect_to
 * @var string      $error_message スタンドアロン時のエラー
 */

defined('ABSPATH') || exit;

$is_standalone = (! isset($member) || $member === null);
$login_error   = $is_standalone ? $error_message : (isset($member->login_error) ? $member->login_error : '');
$email_value   = ! $is_standalone && ! empty($member->email) ? esc_attr($member->email) : '';
$show_reset    = ! $is_standalone && ! empty($member->show_reset_form);
if (! isset($redirect_to)) {
	$redirect_to = home_url('/');
}
$login_url  = home_url('/' . LFT_MEMBERSHIP_SLUG . '/login/');
$forgot_url = home_url('/' . LFT_MEMBERSHIP_SLUG . '/forgot/');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>ログイン - <?php bloginfo('name'); ?></title>
	<?php wp_head(); ?>
</head>

<body class="lft-membership-register-page lft-login-page">
	<div class="lft-register-wrap">
		<header class="lft-register-header">
			<img src="<?php echo esc_url( LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/images/logo.png' ); ?>" alt="LFT - 生前対策・家族信託コミュニティー ~ Life Family Trust ~" class="lft-register-logo-img" />
		</header>

		<div class="lft-register-card">
			<h2 class="lft-register-title">ログイン</h2>

			<?php if ($login_error) : ?>
				<p class="lft-register-errors"><?php echo esc_html($login_error); ?></p>
			<?php endif; ?>

			<?php if ($is_standalone) : ?>
				<form method="post" action="<?php echo esc_url($login_url); ?>" class="lft-register-form" id="lft-login-form">
					<?php wp_nonce_field('lft_member_login', 'lft_member_login_nonce'); ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
					<p class="lft-form-row">
						<label for="lft-login-email">メール</label>
						<input type="email" id="lft-login-email" name="log" value="" required class="lft-input" placeholder="メールアドレスを入力" />
					</p>
					<p class="lft-form-row">
						<label for="lft-login-password">パスワード <span class="lft-required">*</span></label>
						<input type="password" id="lft-login-password" name="pwd" required class="lft-input" placeholder="パスワードを入力" />
					</p>
					<p class="lft-form-actions">
						<button type="submit" class="lft-btn lft-btn--submit">ログイン</button>
					</p>
				</form>
			<?php else : ?>
				<form method="post" action="" class="lft-register-form" id="lft-login-form">
					<?php wp_nonce_field('lft_login', 'lft_login_nonce'); ?>
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>" />
					<p class="lft-form-row">
						<label for="lft-login-email">メール</label>
						<input type="email" id="lft-login-email" name="email_display" value="<?php echo $email_value; ?>" readonly class="lft-input lft-input--readonly" />
					</p>
					<p class="lft-form-row">
						<label for="lft-login-password">パスワード <span class="lft-required">*</span></label>
						<input type="password" id="lft-login-password" name="password" required class="lft-input" placeholder="パスワードを入力" />
					</p>
					<p class="lft-form-actions">
						<button type="submit" class="lft-btn lft-btn--submit">ログイン</button>
					</p>
				</form>

				<?php if ($show_reset) : ?>
					<hr class="lft-form-divider" />
					<p class="lft-register-title-small">パスワードを再設定</p>
					<form method="post" action="" class="lft-register-form">
						<?php wp_nonce_field('lft_reset_password', 'lft_reset_nonce'); ?>
						<p class="lft-form-row">
							<label for="lft-new-password">新しいパスワード <span class="lft-required">*</span></label>
							<input type="password" id="lft-new-password" name="new_password" required minlength="8" class="lft-input" placeholder="8文字以上" />
						</p>
						<p class="lft-form-row">
							<label for="lft-new-password-confirm">新しいパスワード（確認） <span class="lft-required">*</span></label>
							<input type="password" id="lft-new-password-confirm" name="new_password_confirm" required minlength="8" class="lft-input" />
						</p>
						<p class="lft-form-actions">
							<button type="submit" class="lft-btn lft-btn--secondary">パスワードを設定する</button>
						</p>
					</form>
				<?php else : ?>
					<?php $reset_url = home_url('/' . LFT_MEMBERSHIP_SLUG . '/new_user/' . rawurlencode($member->token) . '/?action=reset'); ?>
					<p class="lft-login-reset-hint"><a href="<?php echo esc_url($forgot_url); ?>">パスワードを忘れた方はこちら</a> パスワード再設定メールを送信できます。<br />
						<a href="<?php echo esc_url($reset_url); ?>">パスワードを再設定する</a>
					</p>
				<?php endif; ?>
			<?php endif; ?>

			<p class="lft-login-links">
				<a href="<?php echo esc_url($forgot_url); ?>">パスワードを忘れた方はこちら</a>
			</p>
			<p class="lft-login-links">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">トップページへ戻る</a>
			</p>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>

</html>