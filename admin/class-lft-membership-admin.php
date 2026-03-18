<?php
/**
 * 管理画面: メニュー・ユーザー一覧・モーダル・AJAX
 *
 * @package LFT_Membership
 */

defined( 'ABSPATH' ) || exit;

class LFT_Membership_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		// AJAX: 会員一覧（検索）
		add_action( 'wp_ajax_lft_membership_get_members', array( $this, 'ajax_get_members' ) );
		// AJAX: トークン生成
		add_action( 'wp_ajax_lft_membership_generate_token', array( $this, 'ajax_generate_token' ) );
		// AJAX: 会員追加
		add_action( 'wp_ajax_lft_membership_add_member', array( $this, 'ajax_add_member' ) );
		// AJAX: 会員更新
		add_action( 'wp_ajax_lft_membership_update_member', array( $this, 'ajax_update_member' ) );
		// AJAX: 会員削除
		add_action( 'wp_ajax_lft_membership_delete_member', array( $this, 'ajax_delete_member' ) );
		// AJAX: 一時停止 / 再開
		add_action( 'wp_ajax_lft_membership_toggle_status', array( $this, 'ajax_toggle_status' ) );
		// AJAX: 会員1件取得（編集用）
		add_action( 'wp_ajax_lft_membership_get_member', array( $this, 'ajax_get_member' ) );
		// AJAX: トークン再発行
		add_action( 'wp_ajax_lft_membership_recreate_token', array( $this, 'ajax_recreate_token' ) );
	}

	/**
	 * 管理メニュー追加
	 */
	public function add_menu() {
		$cap = 'manage_options';
		add_menu_page(
			'ユーザー管理',
			'ユーザー管理',
			$cap,
			'lft-membership',
			array( $this, 'render_list_page' ),
			'dashicons-groups',
			30
		);
		add_submenu_page(
			'lft-membership',
			'ユーザーリスト',
			'ユーザーリスト',
			$cap,
			'lft-membership',
			array( $this, 'render_list_page' )
		);
		add_submenu_page(
			'lft-membership',
			'ユーザー登録',
			'ユーザー登録',
			$cap,
			'lft-membership-add',
			array( $this, 'render_list_page' )
		);
	}

	/**
	 * ユーザー一覧ページ（メニュー「ユーザー登録」は同じページを開き、モーダル表示用フラグを付与）
	 * 表示前に退会日過ぎの会員を自動で「日付完了」に更新
	 */
	public function render_list_page() {
		// 退会日を過ぎた会員は自動で status を expired に更新（アクセス無効化）
		LFT_Membership_DB::expire_overdue_members();
		$open_add_modal = isset( $_GET['action'] ) && $_GET['action'] === 'add';
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'admin/views/user-list.php';
	}

	/**
	 * 管理画面用 CSS/JS の読み込み
	 *
	 * @param string $hook_suffix
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( strpos( $hook_suffix, 'lft-membership' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'lft-membership-admin',
			LFT_MEMBERSHIP_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			LFT_MEMBERSHIP_VERSION
		);

		wp_enqueue_script(
			'lft-membership-admin',
			LFT_MEMBERSHIP_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			LFT_MEMBERSHIP_VERSION,
			true
		);

		wp_localize_script( 'lft-membership-admin', 'lftMembership', array(
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'lft_membership_admin' ),
			'baseUrl'   => home_url( '/' ),
			'slug'      => LFT_MEMBERSHIP_SLUG,
			'i18n'      => array(
				'confirmDelete'   => 'このユーザーを削除してもよろしいですか？',
				'copySuccess'     => 'コピーしました',
				'recreateSuccess' => 'トークンを再発行しました。',
				'error'           => 'エラーが発生しました。もう一度お試しください。',
			),
		) );
	}

	/**
	 * ステータス表示用ラベル
	 *
	 * @return array
	 */
	public static function get_status_labels() {
		return array(
			'pending'   => '現在登録中',
			'active'    => '会員登録中',
			'suspended' => '一時停止',
			'expired'   => '日付完了',
		);
	}

	/**
	 * ステータスに応じたCSSクラス
	 *
	 * @param string $status
	 * @return string
	 */
	public static function get_status_class( $status ) {
		$map = array(
			'pending'   => 'lft-status-pending',
			'active'    => 'lft-status-active',
			'suspended' => 'lft-status-suspended',
			'expired'   => 'lft-status-expired',
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : '';
	}

	/**
	 * 日付が期限切れか（退会日を過ぎていれば expired 扱い）
	 *
	 * @param string|null $deadline Y-m-d
	 * @return bool
	 */
	public static function is_expired_by_date( $deadline ) {
		if ( empty( $deadline ) ) {
			return false;
		}
		return strtotime( $deadline ) < strtotime( 'today' );
	}

	// ----- AJAX -----

	public function ajax_get_members() {
		check_ajax_referer( 'lft_membership_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません' ) );
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$paged  = isset( $_POST['paged'] ) ? max( 1, (int) $_POST['paged'] ) : 1;

		$result = LFT_Membership_DB::get_members( array(
			'search'   => $search,
			'paged'    => $paged,
			'per_page' => 20,
		) );

		wp_send_json_success( $result );
	}

	public function ajax_generate_token() {
		check_ajax_referer( 'lft_membership_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません' ) );
		}

		$token = LFT_Membership_DB::generate_token();
		$base  = home_url( '/' );
		$slug  = LFT_MEMBERSHIP_SLUG;
		$url   = rtrim( $base, '/' ) . '/' . $slug . '/new_user/' . $token;

		wp_send_json_success( array( 'token' => $token, 'url' => $url ) );
	}

	/**
	 * AJAX: 会員のトークン再発行（新しいURLを発行）
	 */
	public function ajax_recreate_token() {
		check_ajax_referer( 'lft_membership_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません' ) );
		}
		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => '不正なリクエストです。' ) );
		}
		$member = LFT_Membership_DB::get_member( $id );
		if ( ! $member ) {
			wp_send_json_error( array( 'message' => '会員が見つかりません。' ) );
		}
		$new_token = LFT_Membership_DB::generate_token();
		LFT_Membership_DB::update_member( $id, array( 'token' => $new_token ) );
		$base      = rtrim( home_url( '/' ), '/' );
		$slug      = LFT_MEMBERSHIP_SLUG;
		$path_seg  = ! empty( $member->wp_user_id ) ? 'confirmed_user' : 'new_user';
		$url       = $base . '/' . $slug . '/' . $path_seg . '/' . $new_token . '/';
		wp_send_json_success( array( 'token' => $new_token, 'url' => $url ) );
	}

	public function ajax_add_member() {
		check_ajax_referer( 'lft_membership_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません' ) );
		}

		$token = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => 'トークンを作成してください。' ) );
		}

		$user_name    = isset( $_POST['user_name'] ) ? sanitize_text_field( wp_unslash( $_POST['user_name'] ) ) : '';
		$email        = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : '';
		$phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$payment_date = isset( $_POST['payment_date'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_date'] ) ) : '';
		$deadline     = isset( $_POST['deadline'] ) ? sanitize_text_field( wp_unslash( $_POST['deadline'] ) ) : '';

		if ( empty( $email ) || empty( $user_name ) || empty( $company_name ) || empty( $payment_date ) ) {
			wp_send_json_error( array( 'message' => '必須項目を入力してください。' ) );
		}

		$payment_date = $this->parse_date( $payment_date );
		$deadline     = $this->parse_date( $deadline ); // 空の場合は null（期限なし）

		$id = LFT_Membership_DB::add_member( array(
			'token'        => $token,
			'user_name'    => $user_name,
			'email'        => $email,
			'company_name' => $company_name,
			'phone'        => $phone,
			'payment_date' => $payment_date,
			'deadline'     => $deadline,
			'status'       => 'pending',
		) );

		if ( ! $id ) {
			wp_send_json_error( array( 'message' => '登録に失敗しました。' ) );
		}

		$member = LFT_Membership_DB::get_member( $id );
		// 登録用トークンURLをメールで送信
		$this->send_invitation_email( $member );
		// レスポンスから password_hash を除外
		$member = $this->sanitize_member_for_response( $member );
		wp_send_json_success( array( 'member' => $member, 'message' => 'ユーザーを登録しました。登録用URLをメールで送信しました。' ) );
	}

	/**
	 * 管理画面でユーザー追加時に、登録用トークンURLをメール送信
	 *
	 * @param object $member 会員レコード
	 */
	private function send_invitation_email( $member ) {
		if ( empty( $member->email ) || ! is_email( $member->email ) ) {
			return;
		}
		$base   = rtrim( home_url( '/' ), '/' );
		$slug   = LFT_MEMBERSHIP_SLUG;
		$reg_url = $base . '/' . $slug . '/new_user/' . $member->token . '/';
		$site_name = get_bloginfo( 'name' );
		$subject = '[' . $site_name . '] 会員登録のご案内';
		$body    = $this->get_invitation_email_body( $member->user_name, $reg_url );
		wp_mail( $member->email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
	}

	/**
	 * 招待メール本文（登録用URL付き）
	 *
	 * @param string $user_name
	 * @param string $registration_url
	 * @return string
	 */
	private function get_invitation_email_body( $user_name, $registration_url ) {
		$site_name = get_bloginfo( 'name' );
		$login_url = home_url( '/' . LFT_MEMBERSHIP_SLUG . '/login/' );
		return <<<MAIL
{$user_name} 様

{$site_name} の会員登録のご案内です。

下記のURLより会員登録（パスワード設定）を行ってください。

{$registration_url}

※このURLは一度のみご利用いただけます。登録完了後はログインページからログインしてください。

ログインページ：{$login_url}

---
{$site_name}
MAIL;
	}

	/**
	 * API レスポンス用に会員オブジェクトから password_hash を除去
	 *
	 * @param object $member
	 * @return object
	 */
	private function sanitize_member_for_response( $member ) {
		if ( ! $member || ! is_object( $member ) ) {
			return $member;
		}
		$copy = clone $member;
		if ( isset( $copy->password_hash ) ) {
			unset( $copy->password_hash );
		}
		return $copy;
	}

	public function ajax_update_member() {
		check_ajax_referer( 'lft_membership_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません' ) );
		}

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => '不正なリクエストです。' ) );
		}

		$data = array();
		if ( isset( $_POST['user_name'] ) ) {
			$data['user_name'] = sanitize_text_field( wp_unslash( $_POST['user_name'] ) );
		}
		if ( isset( $_POST['email'] ) ) {
			$data['email'] = sanitize_email( wp_unslash( $_POST['email'] ) );
		}
		if ( isset( $_POST['company_name'] ) ) {
			$data['company_name'] = sanitize_text_field( wp_unslash( $_POST['company_name'] ) );
		}
		if ( isset( $_POST['phone'] ) ) {
			$data['phone'] = sanitize_text_field( wp_unslash( $_POST['phone'] ) );
		}
		if ( isset( $_POST['payment_date'] ) ) {
			$data['payment_date'] = $this->parse_date( sanitize_text_field( wp_unslash( $_POST['payment_date'] ) ) );
		}
		if ( isset( $_POST['deadline'] ) ) {
			$data['deadline'] = $this->parse_date( sanitize_text_field( wp_unslash( $_POST['deadline'] ) ) );
		}
		if ( isset( $_POST['status'] ) && in_array( $_POST['status'], array( 'pending', 'active', 'suspended', 'expired' ), true ) ) {
			$data['status'] = $_POST['status'];
		}

		$ok = LFT_Membership_DB::update_member( $id, $data );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => '更新に失敗しました。' ) );
		}

		$member = LFT_Membership_DB::get_member( $id );
		wp_send_json_success( array( 'member' => $this->sanitize_member_for_response( $member ), 'message' => '更新しました。' ) );
	}

	public function ajax_delete_member() {
		check_ajax_referer( 'lft_membership_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません' ) );
		}

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => '不正なリクエストです。' ) );
		}

		$ok = LFT_Membership_DB::delete_member( $id );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => '削除に失敗しました。' ) );
		}

		wp_send_json_success( array( 'message' => '削除しました。' ) );
	}

	/**
	 * 一時停止 ⇔ 再開（active ⇔ suspended）
	 */
	public function ajax_get_member() {
		check_ajax_referer( 'lft_membership_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません' ) );
		}

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => '不正なリクエストです。' ) );
		}

		$member = LFT_Membership_DB::get_member( $id );
		if ( ! $member ) {
			wp_send_json_error( array( 'message' => 'ユーザーが見つかりません。' ) );
		}

		wp_send_json_success( array( 'member' => $this->sanitize_member_for_response( $member ) ) );
	}

	public function ajax_toggle_status() {
		check_ajax_referer( 'lft_membership_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません' ) );
		}

		$id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => '不正なリクエストです。' ) );
		}

		$member = LFT_Membership_DB::get_member( $id );
		if ( ! $member ) {
			wp_send_json_error( array( 'message' => 'ユーザーが見つかりません。' ) );
		}

		$new_status = ( $member->status === 'suspended' ) ? 'active' : 'suspended';
		$ok = LFT_Membership_DB::update_member( $id, array( 'status' => $new_status ) );
		if ( ! $ok ) {
			wp_send_json_error( array( 'message' => '更新に失敗しました。' ) );
		}

		$member = LFT_Membership_DB::get_member( $id );
		wp_send_json_success( array( 'member' => $this->sanitize_member_for_response( $member ), 'message' => $new_status === 'suspended' ? '一時停止しました。' : '再開しました。' ) );
	}

	/**
	 * 日付文字列を Y-m-d に正規化
	 *
	 * @param string $date
	 * @return string|null
	 */
	private function parse_date( $date ) {
		if ( empty( $date ) ) {
			return null;
		}
		$ts = strtotime( $date );
		return $ts ? gmdate( 'Y-m-d', $ts ) : null;
	}
}
