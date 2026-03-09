<?php
/**
 * 再発行トークンURL用：パスワード変更のみ
 * メールは表示のみ（編集不可）、新しいパスワード・確認の2項目のみ。
 *
 * @package LFT_Membership
 * @var object $member
 * @var string $error_message
 */

defined( 'ABSPATH' ) || exit;

$email_value = ! empty( $member->email ) ? esc_attr( $member->email ) : '';
$login_url   = home_url( '/' . LFT_MEMBERSHIP_SLUG . '/login/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>パスワードの変更 - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="lft-membership-register-page lft-password-reset-page">
	<div class="lft-register-wrap">
		<header class="lft-register-header">
			<img src="<?php echo esc_url( LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/images/logo.png' ); ?>" alt="LFT - 生前対策・家族信託コミュニティー ~ Life Family Trust ~" class="lft-register-logo-img" />
		</header>

		<div class="lft-register-card">
			<h2 class="lft-register-title">パスワードの変更</h2>
			<p class="lft-forgot-desc">再発行されたアクセスURLです。新しいパスワードを設定してください。</p>

			<?php if ( ! empty( $error_message ) ) : ?>
				<p class="lft-register-errors"><?php echo esc_html( $error_message ); ?></p>
			<?php endif; ?>

			<form method="post" action="" class="lft-register-form">
				<?php wp_nonce_field( 'lft_reset_password', 'lft_reset_nonce' ); ?>
				<p class="lft-form-row">
					<label>メール</label>
					<p class="lft-field-readonly-text"><?php echo esc_html( $email_value ); ?></p>
				</p>
				<p class="lft-form-row">
					<label for="lft-new-password">新しいパスワード <span class="lft-required">*</span></label>
					<input type="password" id="lft-new-password" name="new_password" required minlength="8" class="lft-input" placeholder="8文字以上" />
				</p>
				<p class="lft-form-row">
					<label for="lft-new-password-confirm">新しいパスワード（確認） <span class="lft-required">*</span></label>
					<input type="password" id="lft-new-password-confirm" name="new_password_confirm" required minlength="8" class="lft-input" />
				</p>
				<p class="lft-form-actions">
					<button type="submit" class="lft-btn lft-btn--submit">パスワードを設定する</button>
				</p>
			</form>

			<p class="lft-login-links">
				<a href="<?php echo esc_url( $login_url ); ?>">ログインページへ</a>
			</p>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
