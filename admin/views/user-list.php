<?php
/**
 * ユーザー一覧画面（テーブル + 追加モーダル）
 *
 * @package LFT_Membership
 */

defined( 'ABSPATH' ) || exit;

// 「ユーザー登録」メニューから開いた場合はモーダルを表示
$open_add_modal = ( isset( $_GET['page'] ) && $_GET['page'] === 'lft-membership-add' ) || ! empty( $open_add_modal );
$search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$paged          = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$result         = LFT_Membership_DB::get_members( array( 'search' => $search, 'paged' => $paged, 'per_page' => 20 ) );
$members        = $result['items'];
$total          = $result['total'];
$status_labels  = LFT_Membership_Admin::get_status_labels();
$slug           = LFT_MEMBERSHIP_SLUG;
$base_url = home_url( '/' );
$base_path = rtrim( $base_url, '/' ) . '/' . $slug . '/';
$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : 'lft-membership';
?>

<div class="wrap lft-membership-wrap">
	<div class="lft-membership-admin">
		<!-- <aside class="lft-membership-sidebar">
			<div class="lft-membership-sidebar__header">
				<span class="dashicons dashicons-groups"></span>
				<span>ユーザー管理</span>
			</div>
			<nav class="lft-membership-sidebar__nav">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=lft-membership' ) ); ?>" class="lft-membership-nav-item <?php echo $current_page === 'lft-membership' ? 'lft-membership-nav-item--active' : ''; ?>">ユーザーリスト</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=lft-membership-add' ) ); ?>" class="lft-membership-nav-item <?php echo $current_page === 'lft-membership-add' ? 'lft-membership-nav-item--active' : ''; ?>">ユーザー登録</a>
			</nav>
		</aside> -->

		<main class="lft-membership-main">
			<h1 class="lft-membership-title">ユーザーリスト</h1>

			<div class="lft-membership-toolbar">
				<form method="get" action="" class="lft-membership-search" id="lft-membership-search-form">
					<input type="hidden" name="page" value="lft-membership" />
					<label for="lft-membership-search-input" class="screen-reader-text">ユーザー検索</label>
					<input type="search" id="lft-membership-search-input" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="ユーザー検索" class="lft-membership-search__input" />
					<button type="submit" class="button button-primary lft-membership-search__btn">検索</button>
				</form>
				<button type="button" class="button button-primary lft-membership-btn-add" id="lft-membership-btn-add">追加</button>
			</div>

			<div class="lft-membership-table-wrap">
				<table class="wp-list-table widefat fixed striped lft-membership-table">
					<thead>
						<tr>
							<th class="column-no">番号</th>
							<th class="column-user-id">ユーザーID</th>
							<th class="column-user-name">ユーザー名</th>
							<th class="column-email">メール</th>
							<th class="column-company">会社名</th>
							<th class="column-payment">支払日</th>
							<th class="column-deadline">締め切り</th>
							<th class="column-status">現在の状態</th>
							<th class="column-link">登録リンク</th>
							<th class="column-reg-date">登録日</th>
							<th class="column-actions">操作</th>
						</tr>
					</thead>
					<tbody id="lft-membership-tbody">
						<?php
						$no = ( $paged - 1 ) * 20;
						foreach ( $members as $m ) :
							$no++;
							$display_id   = 'user' . $m->id;
							$status_label = isset( $status_labels[ $m->status ] ) ? $status_labels[ $m->status ] : $m->status;
							$status_class = LFT_Membership_Admin::get_status_class( $m->status );
							// 締め切り過ぎは DB で expired に更新済み。表示も「日付完了」
							if ( $m->status === 'expired' || ( $m->status !== 'suspended' && LFT_Membership_Admin::is_expired_by_date( $m->deadline ) ) ) {
								$status_label = '日付完了';
								$status_class = 'lft-status-expired';
							}
							$path_seg   = ( ! empty( $m->password_hash ) || ( isset( $m->wp_user_id ) && (int) $m->wp_user_id > 0 ) ) ? 'confirmed_user' : 'new_user';
							$reg_url    = $base_path . $path_seg . '/' . $m->token;
							$created_at = $m->created_at ? date_i18n( 'Y.m.d', strtotime( $m->created_at ) ) : '—';
							$payment_d  = $m->payment_date ? date_i18n( 'Y.m.d', strtotime( $m->payment_date ) ) : '—';
							$deadline_d  = $m->deadline ? date_i18n( 'Y.m.d', strtotime( $m->deadline ) ) : '—';
							$user_name_d = ! empty( $m->user_name ) ? esc_html( $m->user_name ) : '未登録';
						?>
						<tr data-id="<?php echo esc_attr( $m->id ); ?>">
							<td class="column-no"><?php echo (int) $no; ?></td>
							<td class="column-user-id">
								<strong><?php echo esc_html( $display_id ); ?></strong>
								<div class="row-actions">
									<span class="lft-action"><a href="#" class="lft-edit lft-view-edit" data-id="<?php echo esc_attr( $m->id ); ?>">確認・編集</a></span>
									| <span class="lft-action"><a href="#" class="lft-delete" data-id="<?php echo esc_attr( $m->id ); ?>">削除</a></span>
								</div>
							</td>
							<td class="column-user-name"><?php echo $user_name_d; ?></td>
							<td class="column-email"><?php echo esc_html( $m->email ?: '—' ); ?></td>
							<td class="column-company"><?php echo esc_html( $m->company_name ?: '—' ); ?></td>
							<td class="column-payment"><?php echo esc_html( $payment_d ); ?></td>
							<td class="column-deadline"><?php echo esc_html( $deadline_d ); ?></td>
							<td class="column-status">
								<span class="lft-status-badge <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label ); ?></span>
							</td>
							<td class="column-link">
								<code class="lft-token-short"><?php echo esc_html( strlen( $m->token ) > 18 ? substr( $m->token, 0, 18 ) . '...' : $m->token ); ?></code>
								<button type="button" class="lft-copy-url lft-copy-btn" data-url="<?php echo esc_attr( $reg_url ); ?>" title="URLをコピー" aria-label="URLをコピー">
									<svg class="lft-icon-copy" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
								</button>
								<button type="button" class="lft-recreate-token lft-copy-btn" data-id="<?php echo esc_attr( $m->id ); ?>" title="トークンを再発行" aria-label="トークンを再発行">
									<svg class="lft-icon-recreate" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/><path d="M3 22v-6h6"/><path d="M21 12a9 9 0 0 1-15 6.7L3 16"/></svg>
								</button>
							</td>
							<td class="column-reg-date"><?php echo esc_html( $created_at ); ?></td>
							<td class="column-actions">
								<?php
								$is_expired = ( $m->status === 'expired' ) || ( $m->deadline && strtotime( $m->deadline ) < strtotime( 'today' ) );
								if ( $m->status === 'suspended' ) : ?>
									<button type="button" class="lft-toggle-status lft-btn-resume" data-id="<?php echo esc_attr( $m->id ); ?>" data-action="resume" title="再開" aria-label="再開"><span class="lft-icon-play" aria-hidden="true"></span></button>
								<?php elseif ( ! $is_expired ) : ?>
									<button type="button" class="lft-toggle-status lft-btn-suspend" data-id="<?php echo esc_attr( $m->id ); ?>" data-action="suspend" title="一時停止" aria-label="一時停止"><span class="lft-icon-pause" aria-hidden="true"></span></button>
								<?php else : ?>
									<span class="lft-action-disabled" title="日付完了のため操作不可">—</span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
						<?php if ( empty( $members ) ) : ?>
						<tr>
							<td colspan="11" class="lft-no-items">登録されているユーザーはありません。「追加」から登録してください。</td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $total > 20 ) : ?>
			<div class="lft-membership-pagination">
				<?php
				$paginate_base = add_query_arg( 'paged', '%#%', admin_url( 'admin.php?page=lft-membership' ) );
				if ( $search ) {
					$paginate_base = add_query_arg( 's', $search, $paginate_base );
				}
				echo wp_kses_post( paginate_links( array(
					'base'    => $paginate_base,
					'format'  => '',
					'current' => $paged,
					'total'   => ceil( $total / 20 ),
					'prev_text' => '&laquo; 前へ',
					'next_text' => '次へ &raquo;',
				) ) );
				?>
			</div>
			<?php endif; ?>

			<footer class="lft-membership-footer">
				&copy; 2024-2026. All rights reserved.
			</footer>
		</main>
	</div>
</div>

<!-- ユーザー登録モーダル -->
<div id="lft-membership-modal" class="lft-membership-modal" role="dialog" aria-labelledby="lft-modal-title" aria-hidden="true">
	<div class="lft-membership-modal__backdrop"></div>
	<div class="lft-membership-modal__box">
		<header class="lft-membership-modal__header">
			<h2 id="lft-modal-title">ユーザー登録</h2>
			<button type="button" class="lft-membership-modal__close" aria-label="閉じる">&times;</button>
		</header>
		<div class="lft-membership-modal__body">
			<form id="lft-membership-form" class="lft-membership-form">
				<input type="hidden" name="id" id="lft-field-id" value="" />
				<div class="lft-form-row lft-form-row--two">
					<p class="lft-form-field">
						<label for="lft-field-email">メール <span class="required">*</span></label>
						<input type="email" id="lft-field-email" name="email" required placeholder="" />
					</p>
					<p class="lft-form-field">
						<label for="lft-field-user-name">ユーザー名 <span class="required">*</span></label>
						<input type="text" id="lft-field-user-name" name="user_name" required placeholder="例: 田中 太郎様" />
					</p>
				</div>
				<div class="lft-form-row">
					<p class="lft-form-field">
						<label for="lft-field-company">会社名 <span class="required">*</span></label>
						<input type="text" id="lft-field-company" name="company_name" required placeholder="" />
					</p>
				</div>
				<div class="lft-form-row">
					<p class="lft-form-field">
						<label for="lft-field-phone">電話番号</label>
						<input type="text" id="lft-field-phone" name="phone" placeholder="" />
					</p>
				</div>
				<div class="lft-form-row lft-form-row--two">
					<p class="lft-form-field">
						<label for="lft-field-payment">支払日 <span class="required">*</span></label>
						<input type="date" id="lft-field-payment" name="payment_date" required />
					</p>
					<p class="lft-form-field">
						<label for="lft-field-deadline">締め切り</label>
						<input type="date" id="lft-field-deadline" name="deadline" />
					</p>
				</div>
				<div class="lft-form-row lft-form-row--token" id="lft-form-row-token">
					<p class="lft-form-field">
						<label>アクセスURL</label>
						<span class="lft-token-wrap">
							<input type="text" id="lft-field-access-url" class="lft-field-access-url" readonly placeholder="読み込み中…" />
							<button type="button" id="lft-btn-create-token" class="button">トークンを作成</button>
							<button type="button" id="lft-btn-copy-url" class="button lft-copy-btn" title="URLをコピー" aria-label="URLをコピー">
								<svg class="lft-icon-copy" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
								<span>コピー</span>
							</button>
						</span>
					</p>
				</div>
				<div class="lft-form-actions">
					<button type="submit" class="button button-primary button-large lft-btn-confirm">確認</button>
					<button type="button" class="button button-large lft-btn-cancel">キャンセル</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
	if ( <?php echo $open_add_modal ? 'true' : 'false'; ?> ) {
		var btn = document.getElementById('lft-membership-btn-add');
		if (btn) btn.click();
	}
});
</script>
