<?php
/**
 * フロント: トークンURLでの会員登録画面・ログイン画面
 *
 * @package LFT_Membership
 */

defined( 'ABSPATH' ) || exit;

class LFT_Membership_Frontend {

	private static $instance = null;
	private $slug;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->slug = LFT_MEMBERSHIP_SLUG;
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'redirect_protected_page_to_login' ), 1 );
		add_action( 'template_redirect', array( $this, 'handle_registration_page' ), 5 );
		add_action( 'template_redirect', array( $this, 'handle_confirmed_user_page' ), 5 );
		add_action( 'template_redirect', array( $this, 'handle_login_page' ), 5 );
		add_action( 'template_redirect', array( $this, 'handle_forgot_page' ), 5 );
		add_action( 'template_redirect', array( $this, 'handle_edit_page' ), 20 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_register_assets' ), 20 );
		// テーマの the_password_form より後に出すため priority 999（どのサイトでもプラグインのログインフォームを表示）
		add_filter( 'post_password_required', array( $this, 'filter_post_password_required' ), 10, 2 );
		add_filter( 'the_password_form', array( $this, 'filter_the_password_form' ), 999, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_password_form_assets' ), 20 );
	}

	/**
	 * リライトルール追加: /lft_membership/new_user/{token}
	 * 有効化時にも呼ぶため static で定義
	 */
	public function add_rewrite_rules() {
		self::register_rewrite_rule();
	}

	/**
	 * リライトルールを 1 回だけ登録（有効化フックからも呼ぶ）
	 */
	public static function register_rewrite_rule() {
		$slug = LFT_MEMBERSHIP_SLUG;
		add_rewrite_rule(
			$slug . '/new_user/([^/]+)/?$',
			'index.php?lft_membership=new_user&lft_token=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			$slug . '/confirmed_user/([^/]+)/?$',
			'index.php?lft_membership=confirmed_user&lft_token=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			$slug . '/login/?$',
			'index.php?lft_membership=login',
			'top'
		);
		add_rewrite_rule(
			$slug . '/forgot/?$',
			'index.php?lft_membership=forgot',
			'top'
		);
		add_rewrite_rule(
			$slug . '/edit/?$',
			'index.php?lft_membership=edit',
			'top'
		);
	}

	public function add_query_vars( $vars ) {
		$vars[] = 'lft_membership';
		$vars[] = 'lft_token';
		return $vars;
	}

	/**
	 * フルURLをサイトホーム相対のパスに変換（redirect_to を短くするため）
	 *
	 * @param string $full_url
	 * @return string 例: lft_membership/edit/ または空（ホーム）
	 */
	private function redirect_to_path( $full_url ) {
		$home = trailingslashit( home_url( '/' ) );
		$path = str_replace( $home, '', $full_url );
		return is_string( $path ) ? $path : '';
	}

	/**
	 * GET/POST の redirect_to（パスまたはフルURL）をフルURLに復元
	 *
	 * @param string $path_or_url
	 * @return string
	 */
	private function redirect_from_path( $path_or_url ) {
		if ( empty( $path_or_url ) ) {
			return home_url( '/' );
		}
		$path_or_url = wp_unslash( $path_or_url );
		if ( strpos( $path_or_url, 'http' ) === 0 ) {
			return wp_validate_redirect( esc_url_raw( $path_or_url ), home_url( '/' ) ) ?: home_url( '/' );
		}
		// サブディレクトリ設置でも正しいフルURLにする（base + path）
		$base = trailingslashit( home_url( '/' ) );
		return $base . ltrim( $path_or_url, '/' );
	}

	/**
	 * 会員専用（パスワード保護）ページでは、未ログイン時はログインページへリダイレクト
	 */
	public function redirect_protected_page_to_login() {
		if ( ! is_singular() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post || ! post_password_required( $post ) ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( $user_id && LFT_Membership_DB::is_user_active_member( $user_id ) ) {
			return;
		}
		$redirect_to = get_permalink( $post );
		if ( ! $redirect_to ) {
			$redirect_to = home_url( '/' );
		}
		$login_url = home_url( '/' . $this->slug . '/login/' );
		$login_url = add_query_arg( 'redirect_to', $this->redirect_to_path( $redirect_to ), $login_url );
		wp_safe_redirect( $login_url );
		exit;
	}

	/**
	 * 登録ページ用のアセットは登録ページ表示時のみ読み込む
	 */
	public function maybe_enqueue_register_assets() {
		if ( ! $this->is_registration_page() ) {
			return;
		}
		wp_enqueue_style(
			'lft-membership-register',
			LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/register.css',
			array(),
			LFT_MEMBERSHIP_VERSION
		);
	}

	private function is_registration_page() {
		return get_query_var( 'lft_membership' ) === 'new_user' && get_query_var( 'lft_token' );
	}

	/**
	 * パスワード保護ページで、ログイン中の有効会員にはパスワード不要にする
	 */
	public function filter_post_password_required( $required, $post ) {
		if ( ! $required || ! $post ) {
			return $required;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $required;
		}
		if ( LFT_Membership_DB::is_user_active_member( $user_id ) ) {
			return false;
		}
		return $required;
	}

	/**
	 * 会員専用ページではリダイレクトするため、ここではリンクのみ表示（フォールバック用）
	 */
	public function filter_the_password_form( $output ) {
		$redirect_to = get_permalink();
		if ( ! $redirect_to ) {
			$redirect_to = home_url( '/' );
		}
		$login_url = home_url( '/' . $this->slug . '/login/' );
		$login_url = add_query_arg( 'redirect_to', $this->redirect_to_path( $redirect_to ), $login_url );
		ob_start();
		?>
		<div class="lft-membership-password-form-wrap">
			<p class="lft-membership-password-form-desc">このページは会員専用です。会員の方は<a href="<?php echo esc_url( $login_url ); ?>">ログインページ</a>からアクセスしてください。</p>
			<p class="lft-membership-password-form-hint">初めての方は、管理者からお渡しした登録用URLから会員登録を行ってください。</p>
		</div>
		<?php
		return ob_get_clean();
	}

	public function maybe_enqueue_password_form_assets() {
		if ( ! is_singular() ) {
			return;
		}
		wp_enqueue_style(
			'lft-membership-password-form',
			LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/password-form.css',
			array(),
			LFT_MEMBERSHIP_VERSION
		);
	}

	/**
	 * /lft_membership/login/ の表示とPOST処理。ログイン後は redirect_to へ（会員専用ページならそのURLへ）
	 * リライトが未反映の場合は REQUEST_URI でログインページかどうか判定する
	 */
	public function handle_login_page() {
		$is_login = ( get_query_var( 'lft_membership' ) === 'login' );
		if ( ! $is_login && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$path = wp_unslash( $_SERVER['REQUEST_URI'] );
			$path = preg_replace( '#\?.*$#', '', $path );
			$path = trim( $path, '/' );
			$is_login = ( $path === $this->slug . '/login' || strpos( $path, $this->slug . '/login/' ) !== false );
		}
		if ( ! $is_login ) {
			return;
		}
		// POST 時は redirect_to をリクエストから取得（パスまたはフルURL）。フルURLに復元して使用。
		$redirect_to = home_url( '/' );
		if ( isset( $_POST['redirect_to'] ) && is_string( $_POST['redirect_to'] ) ) {
			$redirect_to = $this->redirect_from_path( $_POST['redirect_to'] );
		} elseif ( isset( $_GET['redirect_to'] ) && is_string( $_GET['redirect_to'] ) ) {
			$redirect_to = $this->redirect_from_path( $_GET['redirect_to'] );
		}
		if ( ! $redirect_to ) {
			$redirect_to = home_url( '/' );
		}
		if ( isset( $_POST['lft_member_login_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lft_member_login_nonce'] ) ), 'lft_member_login' ) ) {
			$this->process_member_login( $redirect_to );
			exit;
		}
		$user_id = get_current_user_id();
		if ( $user_id && LFT_Membership_DB::is_user_active_member( $user_id ) ) {
			wp_safe_redirect( $redirect_to );
			exit;
		}
		$this->render_login_form_page( null, $redirect_to, '' );
		exit;
	}

	private function process_member_login( $redirect_to ) {
		$log = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
		$pwd = isset( $_POST['pwd'] ) ? $_POST['pwd'] : '';
		if ( ! $log || ! $pwd ) {
			$this->render_login_form_page( null, $redirect_to, 'メールとパスワードを入力してください。' );
			return;
		}
		$user = get_user_by( 'email', $log );
		if ( ! $user ) {
			$user = get_user_by( 'login', $log );
		}
		if ( ! $user ) {
			$this->render_login_form_page( null, $redirect_to, 'メールアドレスまたはパスワードが正しくありません。' );
			return;
		}
		if ( ! LFT_Membership_DB::is_user_active_member( $user->ID ) ) {
			$this->render_login_form_page( null, $redirect_to, 'このアカウントではアクセスできません。管理者にお問い合わせください。' );
			return;
		}
		$result = wp_signon( array(
			'user_login'    => $user->user_login,
			'user_password' => $pwd,
			'remember'      => true,
		), is_ssl() );
		if ( is_wp_error( $result ) ) {
			$this->render_login_form_page( null, $redirect_to, 'メールアドレスまたはパスワードが正しくありません。' );
			return;
		}
		// ログイン成功時は会員専用ページ（redirect_to）へ（同一サイト内のみ許可）
		$redirect_to = wp_validate_redirect( $redirect_to, home_url( '/' ) );
		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * ログイン画面を表示（login-form.php を共通利用）
	 * $member あり＝トークンURL用、null＝スタンドアロン（メール入力）
	 *
	 * @param object|null $member
	 * @param string      $redirect_to
	 * @param string      $error_message
	 */
	private function render_login_form_page( $member, $redirect_to = '', $error_message = '' ) {
		wp_enqueue_style(
			'lft-membership-register',
			LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/register.css',
			array(),
			LFT_MEMBERSHIP_VERSION
		);
		if ( ! $redirect_to ) {
			$redirect_to = home_url( '/' );
		}
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'frontend/views/login-form.php';
	}

	/**
	 * 登録ページの表示またはPOST処理
	 */
	public function handle_registration_page() {
		if ( ! $this->is_registration_page() ) {
			return;
		}

		$token = get_query_var( 'lft_token' );
		$member = LFT_Membership_DB::get_member_by_token( $token );

		// POST: 登録処理
		if ( isset( $_POST['lft_register_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lft_register_nonce'] ) ), 'lft_register' ) ) {
			$this->process_registration( $member, $token );
			return;
		}

		// トークン無効または会員がいない（登録済みでトークン更新後はログインへ）
		if ( ! $member ) {
			$login_url = home_url( '/' . $this->slug . '/login/' );
			$this->render_error( 'この登録リンクは無効か、既に使用済みです。<a href="' . esc_url( $login_url ) . '">ログインページ</a>からアクセスしてください。' );
			return;
		}

		// 締め切り過ぎ・停止中は登録不可
		if ( $member->status === 'expired' || $member->status === 'suspended' ) {
			$this->render_error( 'この登録リンクでは登録できません。管理者にお問い合わせください。' );
			return;
		}

		// POST: パスワード再設定（トークンURLから「パスワードを忘れた」で来た場合）
		if ( isset( $_POST['lft_reset_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lft_reset_nonce'] ) ), 'lft_reset_password' ) ) {
			$this->process_password_reset( $member );
			return;
		}

		// POST: ログイン処理（メールは表示しないのでトークン＋パスワードで認証）
		if ( isset( $_POST['lft_login_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lft_login_nonce'] ) ), 'lft_login' ) ) {
			$this->process_login( $member, $token );
			return;
		}

		// 既登録者：再発行トークンURLではパスワード変更フォームのみ表示（メールは表示のみ・新パスワード・確認のみ）
		if ( ! empty( $member->wp_user_id ) && get_user_by( 'id', $member->wp_user_id ) ) {
			$this->render_password_reset_only_form( $member, '' );
			exit;
		}

		$this->render_register_form( $member );
		exit;
	}

	/**
	 * /lft_membership/confirmed_user/{token} 再発行トークン用（既登録者のパスワード変更のみ）
	 */
	public function handle_confirmed_user_page() {
		$is_confirmed = ( get_query_var( 'lft_membership' ) === 'confirmed_user' );
		if ( ! $is_confirmed && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$path = wp_unslash( $_SERVER['REQUEST_URI'] );
			$path = preg_replace( '#\?.*$#', '', $path );
			$path = trim( $path, '/' );
			$is_confirmed = ( strpos( $path, $this->slug . '/confirmed_user/' ) !== false );
		}
		if ( ! $is_confirmed ) {
			return;
		}
		$token = get_query_var( 'lft_token' );
		if ( ! $token && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$path = wp_unslash( $_SERVER['REQUEST_URI'] );
			$path = preg_replace( '#\?.*$#', '', $path );
			if ( preg_match( '#/' . preg_quote( $this->slug, '#' ) . '/confirmed_user/([^/]+)/?#', $path, $m ) ) {
				$token = $m[1];
			}
		}
		$member = $token ? LFT_Membership_DB::get_member_by_token( $token ) : null;
		if ( ! $member ) {
			$login_url = home_url( '/' . $this->slug . '/login/' );
			$this->render_error( 'このリンクは無効か、既に使用済みです。<a href="' . esc_url( $login_url ) . '">ログインページ</a>からアクセスしてください。' );
			exit;
		}
		if ( $member->status === 'expired' || $member->status === 'suspended' ) {
			$this->render_error( 'このリンクではご利用できません。管理者にお問い合わせください。' );
			exit;
		}
		// 未登録者（wp_user_id なし）は new_user へリダイレクトして初回登録を表示
		if ( empty( $member->wp_user_id ) || ! get_user_by( 'id', $member->wp_user_id ) ) {
			$new_user_url = home_url( '/' . $this->slug . '/new_user/' . $token . '/' );
			wp_safe_redirect( $new_user_url );
			exit;
		}
		if ( isset( $_POST['lft_reset_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lft_reset_nonce'] ) ), 'lft_reset_password' ) ) {
			$this->process_password_reset( $member );
			exit;
		}
		$this->render_password_reset_only_form( $member, '' );
		exit;
	}

	/**
	 * 登録フォーム表示（メールは管理者登録値を表示のみ・変更不可。名前・会社名・電話はユーザーが編集可）
	 *
	 * @param object $member 会員レコード
	 */
	private function render_register_form( $member ) {
		$user_name    = $member->user_name ? esc_attr( $member->user_name ) : '';
		$company_name = $member->company_name ? esc_attr( $member->company_name ) : '';
		$phone        = $member->phone ? esc_attr( $member->phone ) : '';
		$email        = $member->email ? esc_attr( $member->email ) : '';
		$token        = esc_attr( get_query_var( 'lft_token' ) );
		$privacy_url  = get_privacy_policy_url();
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'frontend/views/register-form.php';
	}

	/**
	 * トークンURL上のパスワード再設定処理（new_user/TOKEN で既登録者が新しいパスワードを設定）
	 */
	private function process_password_reset( $member ) {
		if ( ! $member || empty( $member->wp_user_id ) ) {
			$this->render_error( '無効なリクエストです。' );
			return;
		}
		$new_password = isset( $_POST['new_password'] ) ? $_POST['new_password'] : '';
		$confirm      = isset( $_POST['new_password_confirm'] ) ? $_POST['new_password_confirm'] : '';
		if ( strlen( $new_password ) < 8 ) {
			$this->render_password_reset_only_form( $member, 'パスワードは8文字以上で入力してください。' );
			exit;
		}
		if ( $new_password !== $confirm ) {
			$this->render_password_reset_only_form( $member, 'パスワードと確認が一致しません。' );
			exit;
		}
		wp_set_password( $new_password, $member->wp_user_id );
		// 再設定後もトークンは更新して再利用不可に
		$new_token = LFT_Membership_DB::generate_token();
		LFT_Membership_DB::update_member( $member->id, array( 'token' => $new_token ) );
		wp_safe_redirect( home_url( '/' . $this->slug . '/login/' ) );
		exit;
	}

	/**
	 * 再発行トークンURL用：パスワード変更フォームのみ表示（メールは表示のみ・新パスワード・確認）
	 *
	 * @param object $member
	 * @param string $error_message
	 */
	private function render_password_reset_only_form( $member, $error_message = '' ) {
		wp_enqueue_style(
			'lft-membership-register',
			LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/register.css',
			array(),
			LFT_MEMBERSHIP_VERSION
		);
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'frontend/views/password-reset-form.php';
	}

	/**
	 * エラー表示（register.css を読み込みスタイルを適用）
	 *
	 * @param string $message
	 */
	private function render_error( $message ) {
		wp_enqueue_style(
			'lft-membership-register',
			LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/register.css',
			array(),
			LFT_MEMBERSHIP_VERSION
		);
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'frontend/views/error.php';
		exit;
	}

	/**
	 * 登録フォームの送信処理: WPユーザー作成・会員紐づけ・ログイン
	 *
	 * @param object|null $member
	 * @param string      $token
	 */
	private function process_registration( $member, $token ) {
		if ( ! $member || $member->token !== $token ) {
			$this->render_error( '無効なリクエストです。' );
			return;
		}

		$password = isset( $_POST['password'] ) ? $_POST['password'] : '';
		$confirm  = isset( $_POST['password_confirm'] ) ? $_POST['password_confirm'] : '';
		$agree    = isset( $_POST['privacy_agree'] ) && $_POST['privacy_agree'] === '1';

		$errors = array();

		if ( strlen( $password ) < 8 ) {
			$errors[] = 'パスワードは8文字以上で入力してください。';
		}
		if ( $password !== $confirm ) {
			$errors[] = 'パスワードとパスワード確認が一致しません。';
		}
		if ( ! $agree ) {
			$errors[] = 'プライバシーポリシーに同意してください。';
		}

		if ( ! empty( $errors ) ) {
			$member->form_errors = $errors;
			$this->render_register_form( $member );
			return;
		}

		$email = $member->email;
		if ( empty( $email ) || ! is_email( $email ) ) {
			$this->render_error( '登録用メールアドレスが設定されていません。管理者にお問い合わせください。' );
			return;
		}

		// 登録時に名前・会社名・電話を更新（ユーザーが編集可能なため）
		$user_name    = isset( $_POST['user_name'] ) ? sanitize_text_field( wp_unslash( $_POST['user_name'] ) ) : $member->user_name;
		$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : $member->company_name;
		$phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : $member->phone;
		LFT_Membership_DB::update_member( $member->id, array( 'user_name' => $user_name, 'company_name' => $company_name, 'phone' => $phone ) );

		// 既存の WP ユーザーがいる場合はパスワードのみ更新し、トークンも更新してログインページへ
		$user = get_user_by( 'email', $email );
		if ( $user ) {
			wp_set_password( $password, $user->ID );
			LFT_Membership_DB::update_member( $member->id, array( 'wp_user_id' => $user->ID, 'status' => 'active' ) );
			$new_token = LFT_Membership_DB::generate_token();
			LFT_Membership_DB::update_member( $member->id, array( 'token' => $new_token ) );
			wp_safe_redirect( home_url( '/' . $this->slug . '/login/' ) );
			exit;
		}

		// 新規 WP ユーザー作成（ログイン名はメールの@前＋会員IDで一意に）
		$login = sanitize_user( preg_replace( '/@.+$/', '', $email ) . '_' . $member->id, true );
		if ( username_exists( $login ) ) {
			$login = $login . '_' . wp_rand( 100, 999 );
		}

		$user_id = wp_create_user( $login, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			$member->form_errors = array( $user_id->get_error_message() );
			$this->render_register_form( $member );
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( $user ) {
			$display_name = ! empty( $user_name ) ? $user_name : $member->user_name;
			wp_update_user( array(
				'ID'           => $user_id,
				'display_name' => $display_name,
				'nickname'     => $display_name,
			) );
		}

		LFT_Membership_DB::update_member( $member->id, array( 'wp_user_id' => $user_id, 'status' => 'active' ) );

		// 登録後はトークンを更新し、同じURLの再利用を防ぐ（パスワード忘れ時は管理者が新しいURLを発行可能）
		$new_token = LFT_Membership_DB::generate_token();
		LFT_Membership_DB::update_member( $member->id, array( 'token' => $new_token ) );

		// 登録完了後はプラグインのログインページへ（メール・パスワードでログインしてもらう）
		wp_safe_redirect( home_url( '/' . $this->slug . '/login/' ) );
		exit;
	}

	/**
	 * ログイン処理（トークンで会員を特定し、メールは表示せずパスワードのみでログイン）
	 */
	private function process_login( $member, $token ) {
		if ( ! $member || $member->token !== $token ) {
			$this->render_error( '無効なリクエストです。' );
			return;
		}
		$password = isset( $_POST['password'] ) ? $_POST['password'] : '';
		if ( empty( $password ) ) {
			$member->login_error = 'パスワードを入力してください。';
			$this->render_login_form_page( $member, home_url( '/' ), '' );
			exit;
		}
		$user = get_user_by( 'email', $member->email );
		if ( ! $user ) {
			$member->login_error = 'ユーザーが見つかりません。';
			$this->render_login_form_page( $member, home_url( '/' ), '' );
			exit;
		}
		$result = wp_signon( array(
			'user_login'    => $user->user_login,
			'user_password' => $password,
			'remember'     => true,
		), is_ssl() );
		if ( is_wp_error( $result ) ) {
			$member->login_error = 'メールアドレスまたはパスワードが正しくありません。';
			$this->render_login_form_page( $member, home_url( '/' ), '' );
			exit;
		}
		$redirect_to = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : home_url( '/' );
		$redirect_to = wp_validate_redirect( $redirect_to, home_url( '/' ) );
		if ( ! $redirect_to ) {
			$redirect_to = home_url( '/' );
		}
		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * /lft_membership/forgot/ パスワード忘れ：メール入力で新しいトークンURLを送信
	 */
	public function handle_forgot_page() {
		if ( get_query_var( 'lft_membership' ) !== 'forgot' ) {
			return;
		}
		$message = '';
		$error   = '';
		if ( isset( $_POST['lft_forgot_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lft_forgot_nonce'] ) ), 'lft_forgot' ) ) {
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			if ( empty( $email ) || ! is_email( $email ) ) {
				$error = '有効なメールアドレスを入力してください。';
			} else {
				$member = LFT_Membership_DB::get_member_by_email( $email );
				if ( ! $member ) {
					$error = 'このメールアドレスは登録されていません。';
				} elseif ( $member->status === 'expired' || $member->status === 'suspended' ) {
					$error = 'このアカウントでは利用できません。管理者にお問い合わせください。';
				} else {
					$new_token = LFT_Membership_DB::generate_token();
					LFT_Membership_DB::update_member( $member->id, array( 'token' => $new_token ) );
					$reset_url = home_url( '/' . $this->slug . '/confirmed_user/' . $new_token . '/' );
					$subject   = '[' . get_bloginfo( 'name' ) . '] パスワード変更用URLのご案内';
					$body      = $this->get_password_reset_email_body( $reset_url );
					$sent      = wp_mail( $email, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ) );
					if ( $sent ) {
						$message = 'ご登録のメールアドレスに新しいアクセスURLを送信しました。';
					} else {
						$error = '送信に失敗しました。しばらくしてから再度お試しください。';
					}
				}
			}
		}
		wp_enqueue_style( 'lft-membership-register', LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/register.css', array(), LFT_MEMBERSHIP_VERSION );
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'frontend/views/forgot-form.php';
		exit;
	}

	/**
	 * パスワード変更依頼メール本文を返す
	 *
	 * @param string $reset_url パスワード変更用URL（confirmed_user）
	 * @return string
	 */
	private function get_password_reset_email_body( $reset_url ) {
		$site_name = get_bloginfo( 'name' );
		$login_url = home_url( '/' . $this->slug . '/login/' );
		return <<<MAIL
{$site_name} をご利用いただきありがとうございます。

パスワード変更のご依頼を承りました。
下記のURLをクリックし、新しいパスワードを設定してください。

{$reset_url}

※このURLは一度のみ有効です。パスワード設定後はログインページからログインしてください。
※心当たりがない場合は、このメールを破棄してください。

ログインページ：{$login_url}

---
{$site_name}
MAIL;
	}

	/**
	 * /lft_membership/edit/ 会員情報編集（ログイン必須・パスワード確認）
	 * リライトが未反映の場合は REQUEST_URI で編集ページかどうか判定する
	 */
	public function handle_edit_page() {
		$is_edit = ( get_query_var( 'lft_membership' ) === 'edit' );
		if ( ! $is_edit && ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$path = wp_unslash( $_SERVER['REQUEST_URI'] );
			$path = preg_replace( '#\?.*$#', '', $path );
			$path = trim( $path, '/' );
			$is_edit = ( $path === $this->slug . '/edit' || $path === $this->slug . '/edit/' || strpos( $path, $this->slug . '/edit' ) !== false );
		}
		if ( ! $is_edit ) {
			return;
		}
		$user_id = get_current_user_id();
		$member  = $user_id ? LFT_Membership_DB::get_member_by_wp_user_id( $user_id ) : null;
		// ログイン済みで会員レコードがある場合のみ編集ページを表示（active でなくても編集画面へ）
		if ( ! $user_id || ! $member ) {
			$edit_url  = home_url( '/' . $this->slug . '/edit/' );
			$login_url = home_url( '/' . $this->slug . '/login/' );
			wp_safe_redirect( add_query_arg( 'redirect_to', $this->redirect_to_path( $edit_url ), $login_url ) );
			exit;
		}
		$message = '';
		$error   = '';
		if ( isset( $_POST['lft_edit_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lft_edit_nonce'] ) ), 'lft_edit' ) ) {
			$current_password = isset( $_POST['current_password'] ) ? $_POST['current_password'] : '';
			if ( empty( $current_password ) ) {
				$error = '現在のパスワードを入力してください。';
			} else {
				$user = get_user_by( 'id', $user_id );
				if ( ! $user || ! wp_check_password( $current_password, $user->user_pass, $user->ID ) ) {
					$error = '現在のパスワードが正しくありません。';
				} else {
					$user_name    = isset( $_POST['user_name'] ) ? sanitize_text_field( wp_unslash( $_POST['user_name'] ) ) : $member->user_name;
					$company_name = isset( $_POST['company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['company_name'] ) ) : $member->company_name;
					$phone        = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : $member->phone;
					LFT_Membership_DB::update_member( $member->id, array( 'user_name' => $user_name, 'company_name' => $company_name, 'phone' => $phone ) );
					if ( $user ) {
						wp_update_user( array( 'ID' => $user->ID, 'display_name' => $user_name, 'nickname' => $user_name ) );
					}
					$message = '会員情報を更新しました。';
				}
			}
		}
		wp_enqueue_style( 'lft-membership-register', LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/register.css', array(), LFT_MEMBERSHIP_VERSION );
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'frontend/views/edit-form.php';
		exit;
	}
}
