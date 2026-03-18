=== LFT Membership ===

Contributors: legalestate
Plugin Name: LFT Membership
Description: 会員管理プラグイン。管理者がユーザーを登録し、トークン付きアクセスURLで会員専用ページへのアクセスを管理します。どのサイトにもインストールして利用できます。
Version: 1.0.0
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== 説明 ==

* 管理者がユーザーを登録（メール・ユーザー名・会社名・電話番号・支払日・退会日）
* 「トークンを作成」ボタンでアクセスURLを発行し、ユーザーに手渡し
* ユーザーリストで一覧表示・検索・編集・削除
* 会員のアクセスを一時停止・再開可能
* 管理画面のUIはすべて日本語

== インストール ==

1. プラグインフォルダを wp-content/plugins/lft-membership に配置
2. 管理画面の「プラグイン」で「LFT Membership」を有効化
3. サイドメニューに「ユーザー管理」が表示されます

== 使い方 ==

* ユーザーリスト: 登録済み会員の一覧。検索・一時停止/再開・編集・削除
* ユーザー登録: 「追加」でモーダルを開き、項目入力後「トークンを作成」でURLを生成し「確認」で保存
