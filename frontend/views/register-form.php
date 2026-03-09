<?php

/**
 * 会員登録フォーム（トークンURL用）
 * メールは管理者登録値を表示のみ・変更不可。名前・会社名・電話はユーザーが編集可。
 *
 * @package LFT_Membership
 * @var object $member
 * @var string $user_name
 * @var string $company_name
 * @var string $phone
 * @var string $email
 * @var string $token
 * @var string $privacy_url
 */

defined('ABSPATH') || exit;

$errors = isset($member->form_errors) ? $member->form_errors : array();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>

<head>
	<meta charset="<?php bloginfo('charset'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>会員登録 - <?php bloginfo('name'); ?></title>
	<?php wp_head(); ?>
</head>

<body class="lft-membership-register-page">
	<div class="lft-register-wrap">
		<header class="lft-register-header">
			<img src="<?php echo esc_url(LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/images/logo.png'); ?>" alt="LFT - 生前対策・家族信託コミュニティー ~ Life Family Trust ~" class="lft-register-logo-img" />
		</header>

		<div class="lft-register-card">
			<h2 class="lft-register-title">会員登録</h2>

			<?php if (! empty($errors)) : ?>
				<ul class="lft-register-errors">
					<?php foreach ($errors as $err) : ?>
						<li><?php echo esc_html($err); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<form method="post" action="" class="lft-register-form" id="lft-register-form">
				<?php wp_nonce_field('lft_register', 'lft_register_nonce'); ?>

				<p class="lft-form-row">
					<label for="lft-email">メール</label>
					<input type="email" id="lft-email" name="email" value="<?php echo esc_attr($email); ?>" readonly class="lft-input lft-input--readonly" />
					<span class="lft-field-hint">（管理者が登録したメールです。変更できません）</span>
				</p>

				<p class="lft-form-row">
					<label for="lft-name">名前 <span class="lft-required">*</span></label>
					<input type="text" id="lft-name" name="user_name" value="<?php echo esc_attr($user_name); ?>" required class="lft-input" placeholder="例: 田中 太郎様" />
				</p>

				<p class="lft-form-row">
					<label for="lft-company">会社名 <span class="lft-required">*</span></label>
					<input type="text" id="lft-company" name="company_name" value="<?php echo esc_attr($company_name); ?>" required class="lft-input" placeholder="" />
				</p>

				<p class="lft-form-row">
					<label for="lft-phone">電話番号</label>
					<input type="text" id="lft-phone" name="phone" value="<?php echo esc_attr($phone); ?>" class="lft-input" placeholder="" />
				</p>

				<p class="lft-form-row">
					<label for="lft-password">パスワード <span class="lft-required">*</span></label>
					<input type="password" id="lft-password" name="password" required minlength="8" autocomplete="new-password" class="lft-input" placeholder="8文字以上" />
				</p>

				<p class="lft-form-row">
					<label for="lft-password-confirm">パスワード確認 <span class="lft-required">*</span></label>
					<input type="password" id="lft-password-confirm" name="password_confirm" required minlength="8" autocomplete="new-password" class="lft-input" placeholder="同じパスワードを入力" />
				</p>

				<p class="lft-form-row lft-form-row--checkbox">
					<label class="lft-checkbox-label">
						<input type="checkbox" name="privacy_agree" value="1" id="lft-privacy" required />
						<?php if ($privacy_url) : ?>
							<a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener">プライバシーポリシー</a>に同意します。
						<?php else : ?>
							プライバシーポリシーに同意します。
						<?php endif; ?>
					</label>
				</p>

				<p class="lft-form-actions">
					<button type="submit" class="lft-btn lft-btn--submit">登録</button>
				</p>
			</form>
		</div>
	</div>
	<?php wp_footer(); ?>
</body>

</html>