<?php
/**
 * 新規会員登録用URL案内メール（管理者がユーザーを追加したとき）
 *
 * @package LFT_Membership
 */

defined( 'ABSPATH' ) || exit;

class LFT_Membership_Registration_Email {

	/**
	 * 案内メールを送信する
	 *
	 * @param string $user_name      登録名（表示用）
	 * @param string $email          宛先・ログインID表示用
	 * @param string $register_url   パスワード設定用URL（new_user/トークン）
	 * @return bool
	 */
	public static function send_invite( $user_name, $email, $register_url ) {
		$user_name = is_string( $user_name ) ? trim( $user_name ) : '';
		$email     = sanitize_email( $email );
		if ( empty( $email ) || ! is_email( $email ) || empty( $register_url ) ) {
			return false;
		}
		$display_name = $user_name !== '' ? $user_name : $email;
		$valid_days   = (int) apply_filters( 'lft_membership_invite_url_valid_days', 14 );
		if ( $valid_days < 1 ) {
			$valid_days = 14;
		}

		$subject = apply_filters(
			'lft_membership_registration_invite_subject',
			'[' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . '] 会員専用ページ パスワード設定のご案内',
			$display_name,
			$email,
			$register_url
		);

		$body = self::build_body( $display_name, $email, $register_url, $valid_days );
		$body = apply_filters( 'lft_membership_registration_invite_body', $body, $display_name, $email, $register_url, $valid_days );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$bcc     = apply_filters( 'lft_membership_mail_bcc', 'seminar@s-legalestate.com', 'registration_invite', $email );
		if ( is_string( $bcc ) && '' !== trim( $bcc ) ) {
			$bcc = sanitize_email( trim( $bcc ) );
			if ( is_email( $bcc ) && strtolower( $bcc ) !== strtolower( $email ) ) {
				$headers[] = 'Bcc: ' . $bcc;
			}
		}
		return wp_mail( $email, $subject, $body, $headers );
	}

	/**
	 * メール本文（プレーンテキスト）
	 *
	 * @param string $display_name
	 * @param string $email
	 * @param string $register_url
	 * @param int    $valid_days
	 * @return string
	 */
	private static function build_body( $display_name, $email, $register_url, $valid_days ) {
		$name_esc = $display_name;
		$url_esc  = $register_url;
		$mail_esc = $email;

		return <<<TEXT
【{$name_esc}】様

LFT事務局の根岸でございます。
先ほど、ご入会手続き完了のご案内をお送りいたしました。

本メールでは、会員専用ページをご利用いただくための
「ログインパスワード設定」についてご案内いたします。

以下の専用URLより、ご希望のパスワードを設定してください。

設定が完了次第、すぐにすべてのサービス（ツールダウンロード等）を
ご利用いただけるようになります。

■ パスワード設定専用URL
[{$url_esc}]
※上記URLをクリックして設定画面へお進みください。
※本URLの有効期限は発行から{$valid_days}日間です。

■ ログインID（メールアドレス）
[{$mail_esc}]

━━━━━━━━━━━━━━━━━━━━━━━━━━━
【設定後の流れ】
━━━━━━━━━━━━━━━━━━━━━━━━━━━

パスワード設定後、そのままログイン状態となります。

設定完了と同時に、各種サービスの
詳細な利用案内メールが自動で届きますので、ご確認ください。

ご不明な点がございましたら、
LFT事務局（045-420-2240）へお気軽にお電話ください。
引き続き、よろしくお願いいたします。

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
株式会社リーガルエステート
Tel             045-620-2240
Email(共通）　  info@s-legalestate.com
Email(セミナー）seminar@s-legalestate.com
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TEXT;
	}
}
