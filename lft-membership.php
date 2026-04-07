<?php

/**
 * Plugin Name: LFT Membership
 * Plugin URI: https://s-legalestate.com
 * Description: 会員管理プラグイン。管理者がユーザーを登録し、トークン付きアクセスURLで会員専用ページへのアクセスを管理します。
 * Version: 1.0.11
 * Author: Legal Estate
 * Text Domain: lft-membership
 * Domain Path: /languages
 * License: GPL v2 or later
 */

defined('ABSPATH') || exit;

define('LFT_MEMBERSHIP_VERSION', '1.0.11');
define('LFT_MEMBERSHIP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LFT_MEMBERSHIP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LFT_MEMBERSHIP_SLUG', 'lft_membership');

require_once LFT_MEMBERSHIP_PLUGIN_DIR . 'includes/class-lft-membership-db.php';
require_once LFT_MEMBERSHIP_PLUGIN_DIR . 'includes/class-lft-membership-registration-email.php';
require_once LFT_MEMBERSHIP_PLUGIN_DIR . 'includes/class-lft-membership.php';
require_once LFT_MEMBERSHIP_PLUGIN_DIR . 'includes/class-lft-membership-frontend.php';
require_once LFT_MEMBERSHIP_PLUGIN_DIR . 'admin/class-lft-membership-admin.php';

/**
 * プラグイン有効化: テーブル作成・リライトルール登録＆フラッシュ
 * （init より前にルールを登録してから flush しないと 404 になる）
 */
function lft_membership_activate()
{
	LFT_Membership_DB::create_tables();
	LFT_Membership_Frontend::register_rewrite_rule();
	flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'lft_membership_activate');

/**
 * プラグイン無効化
 */
function lft_membership_deactivate()
{
	flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'lft_membership_deactivate');

/**
 * プラグイン初期化
 */
function lft_membership_init()
{
	load_plugin_textdomain('lft-membership', false, dirname(plugin_basename(__FILE__)) . '/languages');
	LFT_Membership::instance();
	if (wp_doing_ajax()) {
		LFT_Membership_Admin::instance();
		LFT_Membership_Frontend::instance();
	} elseif (is_admin()) {
		LFT_Membership_Admin::instance();
	} else {
		LFT_Membership_Frontend::instance();
	}
}

/**
 * 保存しているバージョンと違う場合に
 * - DBテーブルのマイグレーション（create_tables）
 * - リライトルールの再登録＆フラッシュ
 *
 * init で実行（plugins_loaded では $wp_rewrite が未初期化のため add_rewrite_rule が使えない）
 */
function lft_membership_maybe_flush_rewrite_rules()
{
	$saved = get_option('lft_membership_rewrite_rules_version', '');
	if ($saved === LFT_MEMBERSHIP_VERSION) {
		return;
	}
	// バージョンが変わったタイミングでテーブル定義も最新化しておく
	LFT_Membership_DB::create_tables();
	LFT_Membership_Frontend::register_rewrite_rule();
	flush_rewrite_rules();
	update_option('lft_membership_rewrite_rules_version', LFT_MEMBERSHIP_VERSION);
}
add_action('plugins_loaded', 'lft_membership_init');
add_action('init', 'lft_membership_maybe_flush_rewrite_rules', 999);
