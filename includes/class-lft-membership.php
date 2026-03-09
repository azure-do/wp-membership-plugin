<?php
/**
 * プラグイン本体クラス（フロント用は後で拡張）
 *
 * @package LFT_Membership
 */

defined( 'ABSPATH' ) || exit;

class LFT_Membership {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// フロント・トークンルートは後で追加
	}
}
