<?php

/**
 * フロント: トークンURLでの会員登録画面・ログイン画面
 *
 * @package LFT_Membership
 */

defined('ABSPATH') || exit;

class LFT_Membership_Frontend
{

	private static $instance = null;
	private $slug;

	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		$this->slug = LFT_MEMBERSHIP_SLUG;
		add_action('init', array($this, 'add_rewrite_rules'));
		add_filter('query_vars', array($this, 'add_query_vars'));
		add_action('template_redirect', array($this, 'redirect_protected_page_to_login'), 1);
		add_action('template_redirect', array($this, 'handle_registration_page'), 5);
		add_action('template_redirect', array($this, 'handle_confirmed_user_page'), 5);
		add_action('template_redirect', array($this, 'handle_login_page'), 5);
		add_action('template_redirect', array($this, 'handle_forgot_page'), 5);
		add_action('template_redirect', array($this, 'handle_edit_page'), 20);
		add_action('template_redirect', array($this, 'handle_logout'), 5);
		add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_register_assets'), 20);
		add_action('wp_enqueue_scripts', array($this, 'enqueue_logout_button_assets'), 20);
		add_action('wp_head', array($this, 'inject_logout_button_script'), 999);
		// テーマの the_password_form より後に出すため priority 999（どのサイトでもプラグインのログインフォームを表示）
		add_filter('post_password_required', array($this, 'filter_post_password_required'), 10, 2);
		add_filter('the_password_form', array($this, 'filter_the_password_form'), 999, 1);
		add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_password_form_assets'), 20);
	}

	/**
	 * リライトルール追加: /lft_membership/new_user/{token}
	 * 有効化時にも呼ぶため static で定義
	 */
	public function add_rewrite_rules()
	{
		self::register_rewrite_rule();
	}

	/**
	 * リライトルールを 1 回だけ登録（有効化フックからも呼ぶ）
	 */
	public static function register_rewrite_rule()
	{
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
		add_rewrite_rule(
			$slug . '/logout/?$',
			'index.php?lft_membership=logout',
			'top'
		);
	}

	public function add_query_vars($vars)
	{
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
	private function redirect_to_path($full_url)
	{
		$home = trailingslashit(home_url('/'));
		$path = str_replace($home, '', $full_url);
		return is_string($path) ? $path : '';
	}

	/**
	 * ログイン後のデフォルト遷移先（会員専用ページのトップ想定）
	 *
	 * @return string
	 */
	private function get_default_redirect_url()
	{
		// lft_membership 固有の会員トップページ（ユーザー側で作成する想定）
		return home_url('/' . $this->slug . '/');
	}

	/**
	 * GET/POST の redirect_to（パスまたはフルURL）をフルURLに復元
	 *
	 * @param string $path_or_url
	 * @return string
	 */
	private function redirect_from_path($path_or_url)
	{
		if (empty($path_or_url)) {
			return $this->get_default_redirect_url();
		}
		$path_or_url = wp_unslash($path_or_url);
		if (strpos($path_or_url, 'http') === 0) {
			return wp_validate_redirect(esc_url_raw($path_or_url), home_url('/')) ?: home_url('/');
		}
		// サブディレクトリ設置でも正しいフルURLにする（base + path）
		$base = trailingslashit(home_url('/'));
		return $base . ltrim($path_or_url, '/');
	}

	/** 会員セッション用 Cookie 名 */
	const COOKIE_NAME = 'lft_member_session';

	/** セッション有効日数 */
	const COOKIE_DAYS = 14;

	/**
	 * 会員ログイン用 Cookie をセット
	 *
	 * @param int $member_id 会員ID
	 */
	private function set_member_cookie($member_id)
	{
		$expiry = time() + (self::COOKIE_DAYS * DAY_IN_SECONDS);
		$payload = array(
			'id' => (int) $member_id,
			'e'  => $expiry,
		);
		$sig = hash_hmac('sha256', $payload['id'] . '|' . $payload['e'], wp_salt('auth'));
		$payload['sig'] = $sig;
		$value = base64_encode(wp_json_encode($payload));
		$expire_str = gmdate('D, d M Y H:i:s', $expiry) . ' GMT';
		if (PHP_VERSION_ID >= 70300) {
			setcookie(self::COOKIE_NAME, $value, array(
				'expires'  => $expiry,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			));
		} else {
			setcookie(self::COOKIE_NAME, $value, $expiry, '/; samesite=Lax', '', is_ssl(), true);
		}
	}

	/**
	 * 会員セッション Cookie を削除（ログアウト）
	 */
	private function clear_member_cookie()
	{
		if (PHP_VERSION_ID >= 70300) {
			setcookie(self::COOKIE_NAME, '', array(
				'expires'  => time() - 3600,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			));
		} else {
			setcookie(self::COOKIE_NAME, '', time() - 3600, '/', '', is_ssl(), true);
		}
	}

	/**
	 * 現在ログイン中の会員を取得（Cookie ベース）。プラグイン専用DBのみ使用し WP ユーザーは使わない。
	 *
	 * @return object|null 会員レコードまたは null
	 */
	public function get_current_lft_member()
	{
		if (empty($_COOKIE[self::COOKIE_NAME])) {
			return null;
		}
		$raw = base64_decode($_COOKIE[self::COOKIE_NAME], true);
		if ($raw === false) {
			return null;
		}
		$payload = json_decode($raw, true);
		if (! is_array($payload) || empty($payload['id']) || empty($payload['e']) || empty($payload['sig'])) {
			return null;
		}
		$expected_sig = hash_hmac('sha256', $payload['id'] . '|' . $payload['e'], wp_salt('auth'));
		if (! hash_equals($expected_sig, $payload['sig']) || $payload['e'] < time()) {
			return null;
		}
		$member = LFT_Membership_DB::get_member((int) $payload['id']);
		if (! $member || ! LFT_Membership_DB::is_member_active($member)) {
			return null;
		}
		return $member;
	}

	/**
	 * 会員専用（パスワード保護）ページでは、未ログイン時はログインページへリダイレクト
	 * 認証はプラグインの会員DB＋Cookie のみ使用（WP ユーザーではログインしない）
	 */
	public function redirect_protected_page_to_login()
	{
		if (! is_singular()) {
			return;
		}
		$post = get_queried_object();
		if (! $post || ! post_password_required($post)) {
			return;
		}
		$member = $this->get_current_lft_member();
		if ($member && LFT_Membership_DB::is_member_active($member)) {
			return;
		}
		$redirect_to = get_permalink($post);
		if (! $redirect_to) {
			$redirect_to = home_url('/');
		}
		$login_url = home_url('/' . $this->slug . '/login/');
		$login_url = add_query_arg('redirect_to', $this->redirect_to_path($redirect_to), $login_url);
		wp_safe_redirect($login_url);
		exit;
	}

	/**
	 * 登録ページ用のアセットは登録ページ表示時のみ読み込む
	 */
	public function maybe_enqueue_register_assets()
	{
		if (! $this->is_registration_page()) {
			return;
		}
		wp_enqueue_style(
			'lft-membership-register',
			LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/register.css',
			array(),
			LFT_MEMBERSHIP_VERSION
		);
	}

	private function is_registration_page()
	{
		return get_query_var('lft_membership') === 'new_user' && get_query_var('lft_token');
	}

	/**
	 * パスワード保護ページで、ログイン中の有効会員にはパスワード不要にする
	 * 認証はプラグインの会員DB＋Cookie のみ使用
	 */
	public function filter_post_password_required($required, $post)
	{
		if (! $required || ! $post) {
			return $required;
		}
		$member = $this->get_current_lft_member();
		if ($member && LFT_Membership_DB::is_member_active($member)) {
			return false;
		}
		return $required;
	}

	/**
	 * 会員専用ページではリダイレクトするため、ここではリンクのみ表示（フォールバック用）
	 */
	public function filter_the_password_form($output)
	{
		$redirect_to = get_permalink();
		if (! $redirect_to) {
			$redirect_to = home_url('/');
		}
		$login_url = home_url('/' . $this->slug . '/login/');
		$login_url = add_query_arg('redirect_to', $this->redirect_to_path($redirect_to), $login_url);
		ob_start();
?>
		<div class="lft-membership-password-form-wrap">
			<p class="lft-membership-password-form-desc">このページは会員専用です。会員の方は<a href="<?php echo esc_url($login_url); ?>">ログインページ</a>からアクセスしてください。</p>
			<p class="lft-membership-password-form-hint">初めての方は、管理者からお渡しした登録用URLから会員登録を行ってください。</p>
		</div>
	<?php
		return ob_get_clean();
	}

	public function maybe_enqueue_password_form_assets()
	{
		if (! is_singular()) {
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
	 * 固定ログアウトボタン用のCSSを読み込み（会員ログイン時のみ・ヘッダー/フッターに依存しない）
	 */
	public function enqueue_logout_button_assets()
	{
		$member = $this->get_current_lft_member();
		if (! $member) {
			return;
		}
		wp_enqueue_style(
			'lft-membership-logout-button',
			LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/logout-button.css',
			array(),
			LFT_MEMBERSHIP_VERSION
		);
	}

	/**
	 * 固定ログアウトボタンをJSで body に注入（ヘッダー・フッターの有無に依存しない）
	 */
	public function inject_logout_button_script()
	{
		if (is_admin()) {
			return;
		}
		$member = $this->get_current_lft_member();
		if (! $member) {
			return;
		}
		$logout_url = home_url('/' . $this->slug . '/logout/');
	?>
		<script>
			(function() {
				var url = <?php echo json_encode($logout_url); ?>;
				var title = <?php echo json_encode(__('ログアウト', 'lft-membership')); ?>;

				function addLogoutBtn() {
					if (document.getElementById('lft-membership-logout-btn')) return;
					var a = document.createElement('a');
					a.id = 'lft-membership-logout-btn';
					a.href = url;
					a.className = 'lft-membership-logout-btn';
					a.title = title;
					a.setAttribute('aria-label', title);
					a.innerHTML =
						'<span class="lft-membership-logout-btn__icon-wrap">' +
						'<svg class="lft-membership-logout-btn__icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>' +
						'</span>' +
						'<span class="lft-membership-logout-btn__label">' + title + '</span>';
					document.body.appendChild(a);
				}
				if (document.body) {
					addLogoutBtn();
				} else {
					document.addEventListener('DOMContentLoaded', addLogoutBtn);
				}
			})();
		</script>
<?php
	}

	/**
	 * /lft_membership/login/ の表示とPOST処理。ログイン後は redirect_to へ（会員専用ページならそのURLへ）
	 * リライトが未反映の場合は REQUEST_URI でログインページかどうか判定する
	 */
	public function handle_login_page()
	{
		$is_login = (get_query_var('lft_membership') === 'login');
		if (! $is_login && ! empty($_SERVER['REQUEST_URI'])) {
			$path = wp_unslash($_SERVER['REQUEST_URI']);
			$path = preg_replace('#\?.*$#', '', $path);
			$path = trim($path, '/');
			$is_login = ($path === $this->slug . '/login' || strpos($path, $this->slug . '/login/') !== false);
		}
		if (! $is_login) {
			return;
		}
		// POST 時は redirect_to をリクエストから取得（パスまたはフルURL）。フルURLに復元して使用。
		$redirect_to = $this->get_default_redirect_url();
		if (isset($_POST['redirect_to']) && is_string($_POST['redirect_to'])) {
			$redirect_to = $this->redirect_from_path($_POST['redirect_to']);
		} elseif (isset($_GET['redirect_to']) && is_string($_GET['redirect_to'])) {
			$redirect_to = $this->redirect_from_path($_GET['redirect_to']);
		}
		if (! $redirect_to) {
			$redirect_to = $this->get_default_redirect_url();
		}
		if (isset($_POST['lft_member_login_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lft_member_login_nonce'])), 'lft_member_login')) {
			$this->process_member_login($redirect_to);
			exit;
		}
		// 既に会員Cookieでログイン済みならリダイレクト
		$member = $this->get_current_lft_member();
		if ($member && LFT_Membership_DB::is_member_active($member)) {
			$redirect_to = wp_validate_redirect($redirect_to, $this->get_default_redirect_url());
			wp_safe_redirect($redirect_to);
			exit;
		}
		$this->render_login_form_page(null, $redirect_to, '');
		exit;
	}

	/**
	 * スタンドアロンログイン処理（メール＋パスワード）。会員DBのみ使用し WP ユーザーは使わない。
	 */
	private function process_member_login($redirect_to)
	{
		$log = isset($_POST['log']) ? sanitize_text_field(wp_unslash($_POST['log'])) : '';
		$pwd = isset($_POST['pwd']) ? $_POST['pwd'] : '';
		if (! $log || ! $pwd) {
			$this->render_login_form_page(null, $redirect_to, 'メールとパスワードを入力してください。');
			return;
		}
		$member = LFT_Membership_DB::get_member_by_email($log);
		if (! $member || empty($member->password_hash)) {
			$this->render_login_form_page(null, $redirect_to, 'メールアドレスまたはパスワードが正しくありません。');
			return;
		}
		if (! wp_check_password($pwd, $member->password_hash, false)) {
			$this->render_login_form_page(null, $redirect_to, 'メールアドレスまたはパスワードが正しくありません。');
			return;
		}
		if (! LFT_Membership_DB::is_member_active($member)) {
			$this->render_login_form_page(null, $redirect_to, 'このアカウントではアクセスできません。管理者にお問い合わせください。');
			return;
		}
		$this->set_member_cookie($member->id);
		$redirect_to = wp_validate_redirect($redirect_to, $this->get_default_redirect_url());
		wp_safe_redirect($redirect_to);
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
	private function render_login_form_page($member, $redirect_to = '', $error_message = '')
	{
		wp_enqueue_style(
			'lft-membership-register',
			LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/register.css',
			array(),
			LFT_MEMBERSHIP_VERSION
		);
		if (! $redirect_to) {
			$redirect_to = $this->get_default_redirect_url();
		}
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'frontend/views/login-form.php';
	}

	/**
	 * 登録ページの表示またはPOST処理
	 */
	public function handle_registration_page()
	{
		if (! $this->is_registration_page()) {
			return;
		}

		$token = get_query_var('lft_token');
		$member = LFT_Membership_DB::get_member_by_token($token);

		// POST: 登録処理
		if (isset($_POST['lft_register_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lft_register_nonce'])), 'lft_register')) {
			$this->process_registration($member, $token);
			return;
		}

		// トークン無効または会員がいない（登録済みでトークン更新後はログインへ）
		if (! $member) {
			$login_url = home_url('/' . $this->slug . '/login/');
			$this->render_error('この登録リンクは無効か、既に使用済みです。<a href="' . esc_url($login_url) . '">ログインページ</a>からアクセスしてください。');
			return;
		}

		// 退会日過ぎ・停止中は登録不可
		if ($member->status === 'expired' || $member->status === 'suspended') {
			$this->render_error('この登録リンクでは登録できません。管理者にお問い合わせください。');
			return;
		}

		// POST: パスワード再設定（トークンURLから「パスワードを忘れた」で来た場合）
		if (isset($_POST['lft_reset_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lft_reset_nonce'])), 'lft_reset_password')) {
			$this->process_password_reset($member);
			return;
		}

		// POST: ログイン処理（メールは表示しないのでトークン＋パスワードで認証）
		if (isset($_POST['lft_login_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lft_login_nonce'])), 'lft_login')) {
			$this->process_login($member, $token);
			return;
		}

		// 既登録者：パスワード設定済み（password_hash あり）または旧 WP ユーザー紐づけの場合はログイン/パスワード変更フォームへ
		if (! empty($member->password_hash)) {
			$this->render_login_form_page($member, home_url('/'), '');
			exit;
		}
		if (! empty($member->wp_user_id) && get_user_by('id', $member->wp_user_id)) {
			$this->render_password_reset_only_form($member, '');
			exit;
		}

		$this->render_register_form($member);
		exit;
	}

	/**
	 * /lft_membership/confirmed_user/{token} 再発行トークン用（既登録者のパスワード変更のみ）
	 */
	public function handle_confirmed_user_page()
	{
		$is_confirmed = (get_query_var('lft_membership') === 'confirmed_user');
		if (! $is_confirmed && ! empty($_SERVER['REQUEST_URI'])) {
			$path = wp_unslash($_SERVER['REQUEST_URI']);
			$path = preg_replace('#\?.*$#', '', $path);
			$path = trim($path, '/');
			$is_confirmed = (strpos($path, $this->slug . '/confirmed_user/') !== false);
		}
		if (! $is_confirmed) {
			return;
		}
		$token = get_query_var('lft_token');
		if (! $token && ! empty($_SERVER['REQUEST_URI'])) {
			$path = wp_unslash($_SERVER['REQUEST_URI']);
			$path = preg_replace('#\?.*$#', '', $path);
			if (preg_match('#/' . preg_quote($this->slug, '#') . '/confirmed_user/([^/]+)/?#', $path, $m)) {
				$token = $m[1];
			}
		}
		$member = $token ? LFT_Membership_DB::get_member_by_token($token) : null;
		if (! $member) {
			$login_url = home_url('/' . $this->slug . '/login/');
			$this->render_error('このリンクは無効か、既に使用済みです。<a href="' . esc_url($login_url) . '">ログインページ</a>からアクセスしてください。');
			exit;
		}
		if ($member->status === 'expired' || $member->status === 'suspended') {
			$this->render_error('このリンクではご利用できません。管理者にお問い合わせください。');
			exit;
		}
		// 未登録者（password_hash も wp_user_id もない）は new_user へリダイレクト
		$has_credentials = ! empty($member->password_hash) || (! empty($member->wp_user_id) && get_user_by('id', $member->wp_user_id));
		if (! $has_credentials) {
			$new_user_url = home_url('/' . $this->slug . '/new_user/' . $token . '/');
			wp_safe_redirect($new_user_url);
			exit;
		}
		if (isset($_POST['lft_reset_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lft_reset_nonce'])), 'lft_reset_password')) {
			$this->process_password_reset($member);
			exit;
		}
		$this->render_password_reset_only_form($member, '');
		exit;
	}

	/**
	 * 登録フォーム表示（メールは管理者登録値を表示のみ・変更不可。名前・会社名・電話はユーザーが編集可）
	 *
	 * @param object $member 会員レコード
	 */
	private function render_register_form($member)
	{
		$user_name    = $member->user_name ? esc_attr($member->user_name) : '';
		$company_name = $member->company_name ? esc_attr($member->company_name) : '';
		$phone        = $member->phone ? esc_attr($member->phone) : '';
		$email        = $member->email ? esc_attr($member->email) : '';
		$token        = esc_attr(get_query_var('lft_token'));
		$privacy_url  = get_privacy_policy_url();
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'frontend/views/register-form.php';
	}

	/**
	 * トークンURL上のパスワード再設定処理（会員DBの password_hash を更新）
	 */
	private function process_password_reset($member)
	{
		if (! $member) {
			$this->render_error('無効なリクエストです。');
			return;
		}
		$new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
		$confirm      = isset($_POST['new_password_confirm']) ? $_POST['new_password_confirm'] : '';
		if (strlen($new_password) < 8) {
			$this->render_password_reset_only_form($member, 'パスワードは8文字以上で入力してください。');
			exit;
		}
		if ($new_password !== $confirm) {
			$this->render_password_reset_only_form($member, 'パスワードと確認が一致しません。');
			exit;
		}
		$password_hash = wp_hash_password($new_password);
		$new_token = LFT_Membership_DB::generate_token();
		LFT_Membership_DB::update_member($member->id, array('password_hash' => $password_hash, 'token' => $new_token));
		// 旧 WP ユーザー紐づけがある場合もパスワードを同期（後方互換）
		if (! empty($member->wp_user_id) && get_user_by('id', $member->wp_user_id)) {
			wp_set_password($new_password, $member->wp_user_id);
		}
		// トークンURL（管理者発行）でパスワード変更した旨をメールで通知
		$this->send_password_changed_email($member->email, $member->user_name);
		wp_safe_redirect(home_url('/' . $this->slug . '/login/'));
		exit;
	}

	/**
	 * 再発行トークンURL用：パスワード変更フォームのみ表示（メールは表示のみ・新パスワード・確認）
	 *
	 * @param object $member
	 * @param string $error_message
	 */
	private function render_password_reset_only_form($member, $error_message = '')
	{
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
	private function render_error($message)
	{
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
	private function process_registration($member, $token)
	{
		if (! $member || $member->token !== $token) {
			$this->render_error('無効なリクエストです。');
			return;
		}

		$password = isset($_POST['password']) ? $_POST['password'] : '';
		$confirm  = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
		$agree    = isset($_POST['privacy_agree']) && $_POST['privacy_agree'] === '1';

		$errors = array();

		if (strlen($password) < 8) {
			$errors[] = 'パスワードは8文字以上で入力してください。';
		}
		if ($password !== $confirm) {
			$errors[] = 'パスワードとパスワード確認が一致しません。';
		}
		if (! $agree) {
			$errors[] = 'プライバシーポリシーに同意してください。';
		}

		if (! empty($errors)) {
			$member->form_errors = $errors;
			$this->render_register_form($member);
			return;
		}

		$email = $member->email;
		if (empty($email) || ! is_email($email)) {
			$this->render_error('登録用メールアドレスが設定されていません。管理者にお問い合わせください。');
			return;
		}

		// 登録時に名前・会社名・電話を更新（ユーザーが編集可能なため）
		$user_name    = isset($_POST['user_name']) ? sanitize_text_field(wp_unslash($_POST['user_name'])) : $member->user_name;
		$company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : $member->company_name;
		$phone        = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : $member->phone;
		LFT_Membership_DB::update_member($member->id, array('user_name' => $user_name, 'company_name' => $company_name, 'phone' => $phone));

		// テーブルに password_hash カラムが存在することを保証してから保存
		LFT_Membership_DB::create_tables();

		// プラグイン専用DBのみ使用：WP ユーザーは作成せず、会員テーブルにパスワードハッシュを保存
		$password_hash = wp_hash_password($password);
		$new_token = LFT_Membership_DB::generate_token();
		$updated = LFT_Membership_DB::update_member($member->id, array(
			'password_hash' => $password_hash,
			'status'        => 'active',
			'token'         => $new_token,
		));

		if (! $updated) {
			$member->form_errors = array('登録の保存に失敗しました。しばらくしてから再度お試しください。解決しない場合は管理者にお問い合わせください。');
			$this->render_register_form($member);
			return;
		}

		// 登録完了メールを送信
		$this->send_registration_confirmation_email($member->email, $user_name);

		// 会員Cookieをセットしてログイン状態にし、会員専用ページへリダイレクト
		$this->set_member_cookie($member->id);
		wp_safe_redirect($this->get_default_redirect_url());
		exit;
	}

	/**
	 * ログイン処理（トークンURL上：会員DBの password_hash で認証し Cookie をセット）
	 */
	private function process_login($member, $token)
	{
		if (! $member || $member->token !== $token) {
			$this->render_error('無効なリクエストです。');
			return;
		}
		$password = isset($_POST['password']) ? $_POST['password'] : '';
		if (empty($password)) {
			$member->login_error = 'パスワードを入力してください。';
			$this->render_login_form_page($member, home_url('/'), '');
			exit;
		}
		if (empty($member->password_hash)) {
			$member->login_error = 'まだ登録が完了していません。下のフォームで会員登録を完了してください。';
			$this->render_register_form($member);
			exit;
		}
		// 退会日・ステータスチェック（期限切れ・一時停止はログイン不可）
		if (! LFT_Membership_DB::is_member_active($member)) {
			$member->login_error = 'このアカウントではアクセスできません。管理者にお問い合わせください。';
			$this->render_login_form_page($member, home_url('/'), '');
			exit;
		}
		if (! wp_check_password($password, $member->password_hash, false)) {
			$member->login_error = 'メールアドレスまたはパスワードが正しくありません。';
			$this->render_login_form_page($member, home_url('/'), '');
			exit;
		}
		$this->set_member_cookie($member->id);
		$redirect_to = isset($_POST['redirect_to']) ? $this->redirect_from_path($_POST['redirect_to']) : $this->get_default_redirect_url();
		$redirect_to = wp_validate_redirect($redirect_to, $this->get_default_redirect_url());
		if (! $redirect_to) {
			$redirect_to = $this->get_default_redirect_url();
		}
		wp_safe_redirect($redirect_to);
		exit;
	}

	/**
	 * /lft_membership/forgot/ パスワード忘れ：メール入力で新しいトークンURLを送信
	 */
	public function handle_forgot_page()
	{
		if (get_query_var('lft_membership') !== 'forgot') {
			return;
		}
		$message = '';
		$error   = '';
		if (isset($_POST['lft_forgot_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lft_forgot_nonce'])), 'lft_forgot')) {
			$email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
			if (empty($email) || ! is_email($email)) {
				$error = '有効なメールアドレスを入力してください。';
			} else {
				$member = LFT_Membership_DB::get_member_by_email($email);
				if (! $member) {
					$error = 'このメールアドレスは登録されていません。';
				} elseif ($member->status === 'expired' || $member->status === 'suspended') {
					$error = 'このアカウントでは利用できません。管理者にお問い合わせください。';
				} else {
					$new_token = LFT_Membership_DB::generate_token();
					LFT_Membership_DB::update_member($member->id, array('token' => $new_token));
					$reset_url = home_url('/' . $this->slug . '/confirmed_user/' . $new_token . '/');
					$display   = ! empty($member->user_name) ? trim((string) $member->user_name) : $email;
					$subject   = apply_filters(
						'lft_membership_password_reset_email_subject',
						'[' . wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES) . '] パスワード再設定のご案内',
						$member,
						$reset_url
					);
					$body = $this->get_password_reset_email_body($reset_url, $display);
					$body = apply_filters('lft_membership_password_reset_email_body', $body, $member, $reset_url);
					$sent = wp_mail($email, $subject, $body, array('Content-Type: text/plain; charset=UTF-8'));
					if ($sent) {
						$message = 'ご登録のメールアドレスにパスワード再設定メールを送信しました。';
					} else {
						$error = '送信に失敗しました。しばらくしてから再度お試しください。';
					}
				}
			}
		}
		wp_enqueue_style('lft-membership-register', LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/register.css', array(), LFT_MEMBERSHIP_VERSION);
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'frontend/views/forgot-form.php';
		exit;
	}

	/**
	 * パスワード再設定依頼メール本文（パスワード忘れフォームから送信）
	 *
	 * @param string $reset_url パスワード再設定用URL（confirmed_user）
	 * @param string $user_name 宛名用の登録名
	 * @return string
	 */
	private function get_password_reset_email_body($reset_url, $user_name = '')
	{
		$name = is_string($user_name) ? trim($user_name) : '';
		if ('' === $name) {
			$name = 'ご登録者';
		}
		$url = $reset_url;
		return <<<MAIL
【{$name}】様

LFT事務局でございます。
パスワードの再設定リクエストを受け付けました。

お手数ですが、以下のURLより新しいパスワードの設定をお願いいたします。
■ パスワード再設定用URL
{$url}
※本URLの有効期限は発行から24時間です。

本メールに心当たりがない場合は、
第三者が誤って入力した可能性がございます。

その場合は、本メールを破棄していただくか、
事務局までご連絡ください。

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
株式会社リーガルエステート
Tel             045-620-2240
Email(共通）　  info@s-legalestate.com
Email(セミナー）seminar@s-legalestate.com
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MAIL;
	}

	/**
	 * /lft_membership/edit/ 会員情報編集（会員Cookie でログイン必須・パスワード確認）
	 */
	public function handle_edit_page()
	{
		$is_edit = (get_query_var('lft_membership') === 'edit');
		if (! $is_edit && ! empty($_SERVER['REQUEST_URI'])) {
			$path = wp_unslash($_SERVER['REQUEST_URI']);
			$path = preg_replace('#\?.*$#', '', $path);
			$path = trim($path, '/');
			$is_edit = ($path === $this->slug . '/edit' || $path === $this->slug . '/edit/' || strpos($path, $this->slug . '/edit') !== false);
		}
		if (! $is_edit) {
			return;
		}
		$member = $this->get_current_lft_member();
		if (! $member) {
			$edit_url  = home_url('/' . $this->slug . '/edit/');
			$login_url = home_url('/' . $this->slug . '/login/');
			wp_safe_redirect(add_query_arg('redirect_to', $this->redirect_to_path($edit_url), $login_url));
			exit;
		}
		$message = '';
		$error   = '';
		if (isset($_POST['lft_edit_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['lft_edit_nonce'])), 'lft_edit')) {
			$current_password = isset($_POST['current_password']) ? $_POST['current_password'] : '';
			if (empty($current_password)) {
				$error = '現在のパスワードを入力してください。';
			} elseif (empty($member->password_hash) || ! wp_check_password($current_password, $member->password_hash, false)) {
				$error = '現在のパスワードが正しくありません。';
			} else {
				$user_name    = isset($_POST['user_name']) ? sanitize_text_field(wp_unslash($_POST['user_name'])) : $member->user_name;
				$company_name = isset($_POST['company_name']) ? sanitize_text_field(wp_unslash($_POST['company_name'])) : $member->company_name;
				$phone        = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : $member->phone;
				LFT_Membership_DB::update_member($member->id, array('user_name' => $user_name, 'company_name' => $company_name, 'phone' => $phone));
				$message = '会員情報を更新しました。';
			}
		}
		wp_enqueue_style('lft-membership-register', LFT_MEMBERSHIP_PLUGIN_URL . 'frontend/css/register.css', array(), LFT_MEMBERSHIP_VERSION);
		include LFT_MEMBERSHIP_PLUGIN_DIR . 'frontend/views/edit-form.php';
		exit;
	}

	/**
	 * /lft_membership/logout/ ログアウト（会員Cookie を削除し、トップへリダイレクト）
	 */
	public function handle_logout()
	{
		if (get_query_var('lft_membership') !== 'logout') {
			return;
		}
		$this->clear_member_cookie();
		wp_safe_redirect(home_url('/'));
		exit;
	}

	/**
	 * 登録完了メールを送信（トークンURLで会員登録が成功したとき）
	 *
	 * @param string $email 送信先メール
	 * @param string $user_name 会員名
	 */
	private function send_registration_confirmation_email($email, $user_name)
	{
		if (empty($email) || ! is_email($email)) {
			return;
		}
		$site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
		$display   = is_string($user_name) ? trim($user_name) : '';
		if ('' === $display) {
			$display = $email;
		}
		$member_page_url = untrailingslashit(home_url('/' . $this->slug));
		$subject         = apply_filters(
			'lft_membership_registration_confirmation_subject',
			'[' . $site_name . '] 会員専用サイトご登録のお礼とご案内',
			$display,
			$email
		);
		$body = $this->get_registration_confirmation_email_body($display, $member_page_url);
		$body = apply_filters(
			'lft_membership_registration_confirmation_body',
			$body,
			$display,
			$email,
			$member_page_url
		);
		wp_mail($email, $subject, $body, array('Content-Type: text/plain; charset=UTF-8'));
	}

	/**
	 * 登録完了メール本文（パスワード設定完了直後）
	 *
	 * @param string $user_name        宛名に使う登録名
	 * @param string $member_page_url  会員専用ページのフルURL
	 * @return string
	 */
	private function get_registration_confirmation_email_body($user_name, $member_page_url)
	{
		$name = $user_name;
		$url  = $member_page_url;
		return <<<MAIL
【{$name}】様

LFT事務局の根岸でございます。
会員専用サイトへのご登録（パスワード設定）、誠にありがとうございました。

これより、すべてのツールダウンロードやコミュニティー機能をご利用いただけます。
本会員様向けの各種サービスについて、大切なご案内を5点にまとめました。

これからの活動に欠かせない内容ですので、ぜひ本メールを保存のうえ、
一つずつご確認・ご登録をお願いいたします。

━━━━━━━━━━━━━━━━━━━━━━━━━━
１）セミナー動画視聴・実務営業ツールダウンロードについて
━━━━━━━━━━━━━━━━━━━━━━━━━━

2018年から最新のセミナー動画、レジュメ、受任用ツールをすべて掲載しています。
以下のページより、先ほど設定されたパスワードでログインしてご活用ください。

■ 会員専用ページURL
{$url}

━━━━━━━━━━━━━━━━━━━━━━━━━━
２）Facebookグループへの参加登録
━━━━━━━━━━━━━━━━━━━━━━━━━━

勉強会の詳細情報や、会員同士の活発なコミュニケーション、案件相談を行っています。
以下のURLより「グループに参加」をクリックしてください。

■ 生前対策・家族信託コミュニティー（動画・ツール配信）
https://www.facebook.com/groups/578291085901240/

■ 生前対策・家族信託研究室（実務相談・情報共有）
https://www.facebook.com/groups/449218122220066/

━━━━━━━━━━━━━━━━━━━━━━━━━━
3）「相続・家族信託ガイド」への掲載【希望者のみ】
━━━━━━━━━━━━━━━━━━━━━━━━━━

運営サイトにて御社のページを無料掲載し、案件をご紹介するサービスです。
掲載を希望される方は、以下のフォームより詳細情報の入力をお願いいたします。

【申込用フォーム】
https://1lejend.com/stepmail/kd.php?no=eylTqJqOsk

━━━━━━━━━━━━━━━━━━━━━━━━━━

LFTを最大限ご活用いただけるよう
事務局一同、精一杯サポートさせていただきます。

末永いお付き合いのほど、よろしくお願い申し上げます。


━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
株式会社リーガルエステート
Tel             045-620-2240
Email(共通）　  info@s-legalestate.com
Email(セミナー）seminar@s-legalestate.com
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MAIL;
	}

	/**
	 * パスワード変更完了メールを送信（管理者発行のトークンURLでパスワードを変更したとき）
	 *
	 * @param string $email    送信先メール
	 * @param string $user_name 会員名
	 */
	private function send_password_changed_email($email, $user_name)
	{
		if (empty($email) || ! is_email($email)) {
			return;
		}
		$site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
		$login_url = home_url('/' . $this->slug . '/login/');
		$display   = is_string($user_name) ? trim($user_name) : '';
		if ('' === $display) {
			$display = $email;
		}
		$subject = apply_filters(
			'lft_membership_password_changed_email_subject',
			'[' . $site_name . '] パスワード変更が完了しました',
			$email,
			$login_url
		);
		$body = $this->get_password_changed_email_body($display, $login_url);
		$body = apply_filters('lft_membership_password_changed_email_body', $body, $email, $login_url, $display);
		wp_mail($email, $subject, $body, array('Content-Type: text/plain; charset=UTF-8'));
	}

	/**
	 * パスワード変更完了メール本文（confirmed_user 等でパスワード変更後）
	 *
	 * @param string $user_name 宛名用の登録名
	 * @param string $login_url ログインページのフルURL
	 * @return string
	 */
	private function get_password_changed_email_body($user_name, $login_url)
	{
		$name = $user_name;
		$url  = untrailingslashit($login_url) . '/';
		return <<<MAIL
【{$name}】様

LFT事務局でございます。
パスワードの変更手続きが正常に完了いたしました。

今後は、新しく設定いただいたパスワードにてログインをお願いいたします。

■ ログインページはこちら
{$url}

※このメールはセキュリティ保護のため、
　パスワード変更が行われた際に自動送信しております。
※お客様ご自身で変更された場合は、本メールへの返信は不要です。

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
株式会社リーガルエステート
Tel             045-620-2240
Email(共通）　  info@s-legalestate.com
Email(セミナー）seminar@s-legalestate.com
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
MAIL;
	}
}
