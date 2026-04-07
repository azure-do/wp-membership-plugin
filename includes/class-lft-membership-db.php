<?php

/**
 * 会員テーブルの作成とCRUD
 *
 * @package LFT_Membership
 */

defined('ABSPATH') || exit;

class LFT_Membership_DB
{

	/** 会員テーブル名（プレフィックス付き） */
	const TABLE_MEMBERS = 'lft_members';

	/**
	 * 会員テーブルを作成
	 */
	public static function create_tables()
	{
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_MEMBERS;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			token varchar(64) NOT NULL DEFAULT '',
			user_name varchar(255) NOT NULL DEFAULT '',
			email varchar(255) NOT NULL DEFAULT '',
			pending_email varchar(255) NOT NULL DEFAULT '',
			email_change_token varchar(64) NOT NULL DEFAULT '',
			company_name varchar(255) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			payment_date date DEFAULT NULL,
			deadline date DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			wp_user_id bigint(20) unsigned DEFAULT NULL,
			password_hash varchar(255) DEFAULT NULL,
			email_change_requested_at datetime DEFAULT NULL,
			expiration_notice_sent_at datetime DEFAULT NULL,
			expiration_notice_processing_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY token (token),
			KEY email_change_token (email_change_token),
			KEY status (status),
			KEY email (email)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		// 既存テーブルに password_hash / email change / reminder カラムが無い場合は追加（マイグレーション）
		self::maybe_add_password_hash_column();
		self::maybe_add_email_change_columns();
		self::maybe_add_expiration_notice_column();
	}

	/**
	 * 既存の会員テーブルに password_hash カラムが無ければ追加する
	 */
	public static function maybe_add_password_hash_column()
	{
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_MEMBERS;
		$column = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'password_hash'));
		if (! empty($column)) {
			return;
		}
		// AFTER 付きで追加を試行し、失敗時は AFTER なしで再試行
		$added = $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN password_hash varchar(255) DEFAULT NULL AFTER wp_user_id");
		if (false === $added && $wpdb->last_error) {
			$wpdb->query("ALTER TABLE `{$table}` ADD COLUMN password_hash varchar(255) DEFAULT NULL");
		}
	}

	/**
	 * 既存の会員テーブルにメール変更用カラムが無ければ追加する
	 */
	public static function maybe_add_email_change_columns()
	{
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE_MEMBERS;

		$columns = array(
			'pending_email'             => "ALTER TABLE `{$table}` ADD COLUMN pending_email varchar(255) NOT NULL DEFAULT '' AFTER email",
			'email_change_token'        => "ALTER TABLE `{$table}` ADD COLUMN email_change_token varchar(64) NOT NULL DEFAULT '' AFTER pending_email",
			'email_change_requested_at' => "ALTER TABLE `{$table}` ADD COLUMN email_change_requested_at datetime DEFAULT NULL AFTER password_hash",
		);

		foreach ($columns as $column_name => $sql) {
			$exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column_name));
			if (! empty($exists)) {
				continue;
			}
			$wpdb->query($sql);
		}

		$index_exists = $wpdb->get_results("SHOW INDEX FROM `{$table}` WHERE Key_name = 'email_change_token'");
		if (empty($index_exists)) {
			$wpdb->query("ALTER TABLE `{$table}` ADD KEY email_change_token (email_change_token)");
		}
	}

	/**
	 * 既存の会員テーブルに期限切れ事前通知カラムが無ければ追加する
	 */
	public static function maybe_add_expiration_notice_column()
	{
		global $wpdb;
		$table  = $wpdb->prefix . self::TABLE_MEMBERS;
		$columns = array(
			'expiration_notice_sent_at'       => "ALTER TABLE `{$table}` ADD COLUMN expiration_notice_sent_at datetime DEFAULT NULL AFTER email_change_requested_at",
			'expiration_notice_processing_at' => "ALTER TABLE `{$table}` ADD COLUMN expiration_notice_processing_at datetime DEFAULT NULL AFTER expiration_notice_sent_at",
		);

		foreach ($columns as $column_name => $sql) {
			$exists = $wpdb->get_results($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column_name));
			if (! empty($exists)) {
				continue;
			}
			$wpdb->query($sql);
		}
	}

	/**
	 * 会員テーブル名を取得
	 *
	 * @return string
	 */
	public static function get_table_name()
	{
		global $wpdb;
		return $wpdb->prefix . self::TABLE_MEMBERS;
	}

	/**
	 * 会員を追加
	 *
	 * @param array $data メール、ユーザー名、会社名、電話番号、支払日、退会日、token
	 * @return int|false 挿入ID または false
	 */
	public static function add_member($data)
	{
		global $wpdb;
		$table = self::get_table_name();

		$defaults = array(
			'token'         => '',
			'user_name'     => '',
			'email'         => '',
			'pending_email' => '',
			'email_change_token' => '',
			'company_name'  => '',
			'phone'         => '',
			'payment_date'  => null,
			'deadline'      => null,
			'status'        => 'pending',
			'wp_user_id'    => null,
			'email_change_requested_at' => null,
			'expiration_notice_sent_at' => null,
			'expiration_notice_processing_at' => null,
		);

		$row = wp_parse_args($data, $defaults);
		$row = array_intersect_key($row, $defaults);

		if (empty($row['token'])) {
			return false;
		}

		$wpdb->insert($table, $row, array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s'));
		return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
	}

	/**
	 * 会員を更新
	 *
	 * @param int   $id   会員ID
	 * @param array $data 更新するカラム
	 * @return bool
	 */
	public static function update_member($id, $data)
	{
		global $wpdb;
		$table = self::get_table_name();

		$allowed = array('token', 'user_name', 'email', 'pending_email', 'email_change_token', 'company_name', 'phone', 'payment_date', 'deadline', 'status', 'wp_user_id', 'password_hash', 'email_change_requested_at', 'expiration_notice_sent_at', 'expiration_notice_processing_at');
		$row = array_intersect_key($data, array_flip($allowed));
		if (empty($row)) {
			return false;
		}

		$formats = array();
		foreach (array_keys($row) as $k) {
			$formats[] = ($k === 'wp_user_id') ? '%d' : '%s';
		}

		return false !== $wpdb->update($table, $row, array('id' => $id), $formats, array('%d'));
	}

	/**
	 * 会員を削除
	 *
	 * @param int $id 会員ID
	 * @return bool
	 */
	public static function delete_member($id)
	{
		global $wpdb;
		$table = self::get_table_name();
		return false !== $wpdb->delete($table, array('id' => $id), array('%d'));
	}

	/**
	 * IDで会員を1件取得
	 *
	 * @param int $id 会員ID
	 * @return object|null
	 */
	public static function get_member($id)
	{
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id));
	}

	/**
	 * トークンで会員を1件取得
	 *
	 * @param string $token
	 * @return object|null
	 */
	public static function get_member_by_token($token)
	{
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE token = %s", $token));
	}

	/**
	 * メールで会員を1件取得
	 *
	 * @param string $email
	 * @return object|null
	 */
	public static function get_member_by_email($email)
	{
		if (empty($email) || ! is_email($email)) {
			return null;
		}
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE email = %s", $email));
	}

	/**
	 * メール変更確認トークンで会員を1件取得
	 *
	 * @param string $token
	 * @return object|null
	 */
	public static function get_member_by_email_change_token($token)
	{
		if (empty($token)) {
			return null;
		}
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE email_change_token = %s", $token));
	}

	/**
	 * 明日が締め切りで、まだ事前通知を送っていない会員一覧を取得
	 *
	 * @return array
	 */
	public static function get_members_expiring_tomorrow()
	{
		global $wpdb;
		$table = self::get_table_name();
		// サイトのタイムゾーンで「明日」の日付（deadline は date 型 Y-m-d で保存）
		if (function_exists('wp_timezone')) {
			$tomorrow = (new DateTimeImmutable('now', wp_timezone()))->modify('+1 day')->format('Y-m-d');
		} else {
			$tomorrow = gmdate('Y-m-d', strtotime(current_time('Y-m-d') . ' +1 day'));
		}

		$stale_processing_time = gmdate('Y-m-d H:i:s', strtotime(current_time('mysql') . ' -15 minutes'));

		$query = $wpdb->prepare(
			"SELECT * FROM {$table}
			WHERE deadline = %s
			AND email <> ''
			AND password_hash IS NOT NULL
			AND password_hash <> ''
			AND status NOT IN ('expired', 'suspended')
			AND (expiration_notice_sent_at IS NULL OR expiration_notice_sent_at = '')
			AND (
				expiration_notice_processing_at IS NULL
				OR expiration_notice_processing_at = ''
				OR expiration_notice_processing_at < %s
			)",
			$tomorrow,
			$stale_processing_time
		);

		return $wpdb->get_results($query);
	}

	/**
	 * 期限前日通知送信の処理権を取得する
	 *
	 * @param int $member_id
	 * @return bool
	 */
	public static function claim_expiration_notice($member_id)
	{
		global $wpdb;
		$table = self::get_table_name();
		$now   = current_time('mysql');
		$stale = gmdate('Y-m-d H:i:s', strtotime($now . ' -15 minutes'));

		$updated = $wpdb->query($wpdb->prepare(
			"UPDATE {$table}
			SET expiration_notice_processing_at = %s
			WHERE id = %d
			AND (expiration_notice_sent_at IS NULL OR expiration_notice_sent_at = '')
			AND (
				expiration_notice_processing_at IS NULL
				OR expiration_notice_processing_at = ''
				OR expiration_notice_processing_at < %s
			)",
			$now,
			$member_id,
			$stale
		));

		return $updated === 1;
	}

	/**
	 * 期限前日通知を送信済みにする
	 *
	 * @param int $member_id
	 * @return bool
	 */
	public static function finalize_expiration_notice($member_id)
	{
		return self::update_member($member_id, array(
			'expiration_notice_sent_at'       => current_time('mysql'),
			'expiration_notice_processing_at' => null,
		));
	}

	/**
	 * 期限前日通知の処理ロックを解除する
	 *
	 * @param int $member_id
	 * @return bool
	 */
	public static function release_expiration_notice_claim($member_id)
	{
		return self::update_member($member_id, array(
			'expiration_notice_processing_at' => null,
		));
	}

	/**
	 * WPユーザーIDで会員を1件取得
	 *
	 * @param int $wp_user_id
	 * @return object|null
	 */
	public static function get_member_by_wp_user_id($wp_user_id)
	{
		if (! $wp_user_id) {
			return null;
		}
		global $wpdb;
		$table = self::get_table_name();
		return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE wp_user_id = %d", $wp_user_id));
	}

	/**
	 * 指定WPユーザーが有効な会員か（アクセス許可）かどうか（後方互換・管理画面用）
	 *
	 * @param int $user_id WP user ID
	 * @return bool
	 */
	public static function is_user_active_member($user_id)
	{
		$member = self::get_member_by_wp_user_id($user_id);
		if (! $member) {
			return false;
		}
		return self::is_member_active($member);
	}

	/**
	 * 会員オブジェクトが有効か（アクセス許可）かどうか
	 * 退会日未設定（null）の場合は期限なしで有効
	 *
	 * @param object $member 会員レコード
	 * @return bool
	 */
	public static function is_member_active($member)
	{
		if (! $member) {
			return false;
		}
		if ($member->status === 'suspended' || $member->status === 'expired') {
			return false;
		}
		if (! empty($member->deadline) && strtotime($member->deadline) < strtotime('today')) {
			return false;
		}
		return true;
	}

	/**
	 * 会員一覧を取得（検索・ページネーション対応）
	 *
	 * @param array $args 検索キーワード、ページ、1ページあたり件数
	 * @return array { total, items }
	 */
	public static function get_members($args = array())
	{
		global $wpdb;
		$table = self::get_table_name();

		$defaults = array(
			'search'   => '',
			'paged'    => 1,
			'per_page' => 20,
		);
		$args = wp_parse_args($args, $defaults);

		$where = '1=1';
		$values = array();

		if (! empty($args['search'])) {
			$like = '%' . $wpdb->esc_like($args['search']) . '%';
			$where .= " AND ( user_name LIKE %s OR email LIKE %s OR company_name LIKE %s OR token LIKE %s )";
			$values = array_merge($values, array($like, $like, $like, $like));
		}

		if (! empty($values)) {
			$total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}", $values));
		} else {
			$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
		}

		$per_page = max(1, (int) $args['per_page']);
		$offset   = ((int) $args['paged'] - 1) * $per_page;

		$order_sql = "ORDER BY id DESC LIMIT %d OFFSET %d";
		$values[]  = $per_page;
		$values[]  = $offset;

		if (! empty($args['search'])) {
			$query = $wpdb->prepare("SELECT * FROM {$table} WHERE {$where} {$order_sql}", $values);
		} else {
			$query = $wpdb->prepare("SELECT * FROM {$table} WHERE 1=1 {$order_sql}", $per_page, $offset);
		}

		$items = $wpdb->get_results($query);

		return array(
			'total' => $total,
			'items' => $items,
		);
	}

	/**
	 * ランダムなトークンを生成（重複チェック付き）
	 *
	 * @return string
	 */
	public static function generate_token()
	{
		$table = self::get_table_name();
		do {
			$token = bin2hex(random_bytes(16));
			$exists = self::get_member_by_token($token);
		} while ($exists);
		return $token;
	}

	/**
	 * 退会日を過ぎた会員のステータスを「日付完了」に更新
	 * 今日より前の退会日日なら status を expired にする
	 *
	 * @return int 更新した行数
	 */
	public static function expire_overdue_members()
	{
		global $wpdb;
		$table = self::get_table_name();
		$today  = current_time('Y-m-d');
		// 退会日が今日より前で、かつ suspended/expired 以外の会員を expired に更新
		$updated = $wpdb->query($wpdb->prepare(
			"UPDATE {$table} SET status = 'expired', updated_at = %s WHERE deadline < %s AND status NOT IN ('expired', 'suspended')",
			current_time('mysql'),
			$today
		));
		return false !== $updated ? (int) $updated : 0;
	}
}
