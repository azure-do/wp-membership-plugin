<?php
/**
 * パスワード忘れ：メール入力で新しいトークンURLを送信
 *
 * @package LFT_Membership
 * @var string $message 送信成功メッセージ
 * @var string $error   エラーメッセージ
 */

defined( 'ABSPATH' ) || exit;

$login_url = home_url( '/' . LFT_MEMBERSHIP_SLUG . '/login/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>パスワードをお忘れの方 - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="lft-membership-register-page lft-forgot-page">
	<div class="lft-register-wrap">
		<header class="lft-register-header">
			<img src="<?php echo esc_url( LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/images/logo.png' ); ?>" alt="LFT - 生前対策・家族信託コミュニティー ~ Life Family Trust ~" class="lft-register-logo-img" />
		</header>

		<div class="lft-register-card">
			<h2 class="lft-register-title">パスワードを忘れた方はこちら</h2>
			<p class="lft-forgot-desc">ご登録のメールアドレスを入力してください。パスワード再設定用のメールをお送りします。そのURLからパスワードの再設定またはログインができます。</p>

			<?php if ( $message ) : ?>
				<p class="lft-register-message"><?php echo esc_html( $message ); ?></p>
				<p><a href="<?php echo esc_url( $login_url ); ?>" class="lft-btn lft-btn--submit">ログインページへ</a></p>
			<?php else : ?>
				<?php if ( $error ) : ?>
					<p class="lft-register-errors"><?php echo esc_html( $error ); ?></p>
				<?php endif; ?>

				<form method="post" action="" class="lft-register-form">
					<?php wp_nonce_field( 'lft_forgot', 'lft_forgot_nonce' ); ?>
					<p class="lft-form-row">
						<label for="lft-forgot-email">メールアドレス <span class="lft-required">*</span></label>
						<input type="email" id="lft-forgot-email" name="email" value="" required class="lft-input" placeholder="登録時のメールアドレス" />
					</p>
					<p class="lft-form-actions">
						<button type="submit" class="lft-btn lft-btn--submit">パスワード再設定メールを送信</button>
					</p>
				</form>
			<?php endif; ?>

			<p class="lft-login-links">
				<a href="<?php echo esc_url( $login_url ); ?>">ログインページへ戻る</a>
			</p>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
