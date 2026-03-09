<?php
/**
 * 会員情報編集（ログイン必須・現在のパスワードで確認）
 * 登録ページと同じURL形式（/lft_membership/edit/）で、パスワード確認後に名前・会社名・電話を編集可能
 *
 * @package LFT_Membership
 * @var object $member
 * @var string $message 更新成功メッセージ
 * @var string $error   エラーメッセージ
 */

defined( 'ABSPATH' ) || exit;

$user_name    = ! empty( $member->user_name ) ? esc_attr( $member->user_name ) : '';
$company_name = ! empty( $member->company_name ) ? esc_attr( $member->company_name ) : '';
$phone        = ! empty( $member->phone ) ? esc_attr( $member->phone ) : '';
$email        = ! empty( $member->email ) ? esc_html( $member->email ) : '—';
$login_url    = home_url( '/' . LFT_MEMBERSHIP_SLUG . '/login/' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>会員情報の編集 - <?php bloginfo( 'name' ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="lft-membership-register-page lft-edit-page">
	<div class="lft-register-wrap">
		<header class="lft-register-header">
			<img src="<?php echo esc_url( LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/images/logo.png' ); ?>" alt="LFT - 生前対策・家族信託コミュニティー ~ Life Family Trust ~" class="lft-register-logo-img" />
		</header>

		<div class="lft-register-card">
			<h2 class="lft-register-title">会員情報の編集</h2>
			<p class="lft-edit-desc">本人確認のため、現在のパスワードを入力してください。</p>

			<?php if ( $message ) : ?>
				<p class="lft-register-message"><?php echo esc_html( $message ); ?></p>
			<?php endif; ?>
			<?php if ( $error ) : ?>
				<p class="lft-register-errors"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>

			<form method="post" action="" class="lft-register-form">
				<?php wp_nonce_field( 'lft_edit', 'lft_edit_nonce' ); ?>
				<p class="lft-form-row">
					<label>メール</label>
					<p class="lft-field-readonly-text"><?php echo $email; ?></p>
					<span class="lft-field-hint">（変更できません）</span>
				</p>
				<p class="lft-form-row">
					<label for="lft-edit-current-password">現在のパスワード（確認） <span class="lft-required">*</span></label>
					<input type="password" id="lft-edit-current-password" name="current_password" required class="lft-input" placeholder="パスワードを入力" />
				</p>
				<p class="lft-form-row">
					<label for="lft-edit-name">お名前</label>
					<input type="text" id="lft-edit-name" name="user_name" value="<?php echo $user_name; ?>" class="lft-input" />
				</p>
				<p class="lft-form-row">
					<label for="lft-edit-company">会社名</label>
					<input type="text" id="lft-edit-company" name="company_name" value="<?php echo $company_name; ?>" class="lft-input" />
				</p>
				<p class="lft-form-row">
					<label for="lft-edit-phone">電話番号</label>
					<input type="text" id="lft-edit-phone" name="phone" value="<?php echo $phone; ?>" class="lft-input" placeholder="例: 03-1234-5678" />
				</p>
				<p class="lft-form-actions">
					<button type="submit" class="lft-btn lft-btn--submit">更新する</button>
				</p>
			</form>

			<p class="lft-login-links">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">トップへ戻る</a>
			</p>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
