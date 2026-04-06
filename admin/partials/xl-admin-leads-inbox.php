<?php
/**
 * Leads Inbox admin page — with status/tag/assignment filtering.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

// ── Read active filters from URL ─────────────────────────────────────────────

$current_status = isset( $_GET['xl_status'] ) ? sanitize_text_field( wp_unslash( $_GET['xl_status'] ) ) : '';
$current_form = isset( $_GET['xl_form'] ) ? absint( $_GET['xl_form'] ) : 0;
$current_filter = isset( $_GET['xl_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['xl_filter'] ) ) : '';
$date_from = isset( $_GET['xl_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['xl_date_from'] ) ) : '';
$date_to = isset( $_GET['xl_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['xl_date_to'] ) ) : '';

// Tag filter — multi-value.
$current_tag_ids = array();
if ( isset( $_GET['xl_tags'] ) && is_array( $_GET['xl_tags'] ) ) {
	$current_tag_ids = array_values( array_filter( array_map( 'absint', wp_unslash( $_GET['xl_tags'] ) ) ) );
}

// Build $filters array for XL_Leads::get_leads_filtered().
$filters = array();

if ( $current_status && array_key_exists( $current_status, XL_Leads::get_statuses() ) ) {
	$filters['status'] = $current_status;
}
if ( $current_form ) {
	$filters['form_id'] = $current_form;
}
if ( $date_from ) {
	$filters['date_from'] = $date_from;
}
if ( $date_to ) {
	$filters['date_to'] = $date_to;
}
if ( ! empty( $current_tag_ids ) ) {
	$filters['tag_ids'] = $current_tag_ids;
}
if ( 'my_leads' === $current_filter ) {
	$filters['assigned_to'] = get_current_user_id();
}

// ── Pagination ───────────────────────────────────────────────────────────────
$per_page = 20;
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result = XL_Leads::get_leads_filtered( $filters, $current_page, $per_page );
$leads = $result['leads'];
$total_leads = $result['total'];
$total_pages = $total_leads > 0 ? (int) ceil( $total_leads / $per_page ) : 1;

// If requested page is beyond last page, redirect to last page.
if ( $current_page > $total_pages && $total_leads > 0 ) {
	wp_safe_redirect( add_query_arg( array( 'page' => 'xtremeleads', 'paged' => $total_pages ), admin_url( 'admin.php' ) ) );
	exit;
}

// Pre-fetch forms and tags for the current page.
$form_ids = array_unique( array_map( static fn( $l ) => (int) $l->form_id, $leads ) );
$forms_cache = XL_Forms::get_forms_by_ids( $form_ids );

$lead_ids_current = array_map( static fn( $l ) => (int) $l->id, $leads );
$tags_by_lead = XL_Tags::get_tags_for_leads( $lead_ids_current );

$statuses = XL_Leads::get_statuses();
$all_tags = XL_Tags::get_all_tags();
$all_forms = XL_Forms::get_all_forms();

// ── Helper: extract field value from lead ────────────────────────────────────

/**
 * @param object $lead Lead row.
 * @param string $field_type Field type to look for.
 * @param array $forms_cache Forms keyed by ID.
 * @return string
 */
if ( ! function_exists( 'xl_inbox_get_field' ) ) :
function xl_inbox_get_field( object $lead, string $field_type, array $forms_cache ): string {
	$form = $forms_cache[ (int) $lead->form_id ] ?? null;
	if ( ! $form ) {
		return '';
	}
	$field_defs = XL_Forms::decode_fields( $form );
	$field_values = XL_Leads::decode_field_values( $lead );

	if ( 'text' === $field_type ) {
		$best_match = null;
		$first_text = null;
		foreach ( $field_defs as $fd ) {
			if ( ( $fd['type'] ?? '' ) === 'text' ) {
				if ( null === $first_text ) {
					$first_text = $fd;
				}
				if ( false !== strpos( strtolower( $fd['label'] ?? '' ), 'name' ) ) {
					$best_match = $fd;
					break;
				}
			}
		}
		$fd = $best_match ?? $first_text;
		if ( ! $fd ) {
			return '';
		}
		$val = $field_values[ $fd['id'] ?? '' ] ?? '';
		return is_array( $val ) ? implode( ', ', $val ) : (string) $val;
	}

	foreach ( $field_defs as $fd ) {
		if ( ( $fd['type'] ?? '' ) === $field_type ) {
			$val = $field_values[ $fd['id'] ?? '' ] ?? '';
			return is_array( $val ) ? implode( ', ', $val ) : (string) $val;
		}
	}
	return '';
}
endif; // xl_inbox_get_field

// ── Build base URL for filters (preserves all active filters in links) ────────
if ( ! function_exists( 'xl_filter_url' ) ) :
function xl_filter_url( array $args = array() ): string {
	$base = array( 'page' => 'xtremeleads-leads' );
	return add_query_arg( array_merge( $base, $args ), admin_url( 'admin.php' ) );
}
endif;

// ── Admin notices ─────────────────────────────────────────────────────────────
$notice_html = '';
if ( ! empty( $_GET['bulk_deleted'] ) ) {
	$count = absint( $_GET['bulk_deleted'] );
	/* translators: %d: number of deleted leads */
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . sprintf( _n( '%d lead deleted.', '%d leads deleted.', $count, 'xtremeleads' ), $count ) . '</p></div>';
} elseif ( ! empty( $_GET['bulk_contacted'] ) ) {
	$count = absint( $_GET['bulk_contacted'] );
	/* translators: %d: number of leads marked as contacted */
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . sprintf( _n( '%d lead marked as contacted.', '%d leads marked as contacted.', $count, 'xtremeleads' ), $count ) . '</p></div>';
} elseif ( isset( $_GET['bulk_error'] ) && 'no_selection' === $_GET['bulk_error'] ) {
	$notice_html = '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No items selected.', 'xtremeleads' ) . '</p></div>';
}

// Export nonce URL (used for full export link).
$export_filters = array( 'page' => 'xtremeleads' );
if ( $current_status ) {
	$export_filters['xl_status'] = $current_status;
}
if ( $current_form ) {
	$export_filters['xl_form'] = $current_form;
}
if ( $date_from ) {
	$export_filters['xl_date_from'] = $date_from;
}
if ( $date_to ) {
	$export_filters['xl_date_to'] = $date_to;
}
if ( ! empty( $current_tag_ids ) ) {
	$export_filters['xl_tags'] = $current_tag_ids;
}
if ( 'my_leads' === $current_filter ) {
	$export_filters['xl_filter'] = 'my_leads';
}
$export_url = wp_nonce_url(
	add_query_arg(
		array_merge( array( 'action' => 'xl_export_leads' ), $export_filters ),
		admin_url( 'admin-post.php' )
	),
	'xl_export_leads'
);
?>
<div class="wrap xl-wrap">
	<h1 class="xl-page-title">
		<?php esc_html_e( 'Leads Inbox', 'xtremeleads' ); ?>
		<span class="xl-badge xl-badge-count"><?php echo esc_html( number_format_i18n( $total_leads ) ); ?></span>
		<a href="<?php echo esc_url( $export_url ); ?>" class="button xl-btn-secondary xl-export-btn">
			<span class="dashicons dashicons-download" style="vertical-align:middle;margin-right:4px;"></span>
			<?php esc_html_e( 'Export CSV', 'xtremeleads' ); ?>
		</a>
	</h1>

	<?php echo wp_kses_post( $notice_html ); ?>

	<!-- Status Filter Tabs -->
	<div class="xl-status-tabs" role="tablist">
		<a
			href="<?php echo esc_url( xl_filter_url() ); ?>"
			class="xl-tab <?php echo ( '' === $current_status && 'my_leads' !== $current_filter ) ? 'xl-tab-active' : ''; ?>"
			role="tab"
		>
			<?php esc_html_e( 'All', 'xtremeleads' ); ?>
		</a>
		<?php foreach ( $statuses as $slug => $label ) : ?>
			<a
				href="<?php echo esc_url( xl_filter_url( array( 'xl_status' => $slug ) ) ); ?>"
				class="xl-tab <?php echo ( $current_status === $slug ) ? 'xl-tab-active' : ''; ?>"
				role="tab"
			>
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
		<a
			href="<?php echo esc_url( xl_filter_url( array( 'xl_filter' => 'my_leads' ) ) ); ?>"
			class="xl-tab xl-tab-my-leads <?php echo ( 'my_leads' === $current_filter ) ? 'xl-tab-active' : ''; ?>"
			role="tab"
		>
			<?php esc_html_e( 'My Leads', 'xtremeleads' ); ?>
		</a>
	</div>

	<!-- Tag / Form / Date Filters -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="xl-filter-form" id="xl-filter-form">
		<input type="hidden" name="page" value="xtremeleads">
		<?php if ( $current_status ) : ?>
			<input type="hidden" name="xl_status" value="<?php echo esc_attr( $current_status ); ?>">
		<?php endif; ?>
		<?php if ( 'my_leads' === $current_filter ) : ?>
			<input type="hidden" name="xl_filter" value="my_leads">
		<?php endif; ?>

		<div class="xl-filter-bar">
			<!-- Tag filter -->
			<?php if ( ! empty( $all_tags ) ) : ?>
				<div class="xl-filter-group">
					<label class="xl-filter-label"><?php esc_html_e( 'Tags:', 'xtremeleads' ); ?></label>
					<div class="xl-tag-checkboxes">
						<?php foreach ( $all_tags as $tag ) : ?>
							<label class="xl-tag-checkbox-label">
								<input
									type="checkbox"
									name="xl_tags[]"
									value="<?php echo esc_attr( $tag->id ); ?>"
									<?php checked( in_array( (int) $tag->id, $current_tag_ids, true ) ); ?>
								>
								<?php echo esc_html( $tag->name ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<!-- Form filter -->
			<?php if ( ! empty( $all_forms ) ) : ?>
				<div class="xl-filter-group">
					<label class="xl-filter-label" for="xl-form-filter"><?php esc_html_e( 'Form:', 'xtremeleads' ); ?></label>
					<select name="xl_form" id="xl-form-filter" class="xl-select-sm">
						<option value=""><?php esc_html_e( 'All Forms', 'xtremeleads' ); ?></option>
						<?php foreach ( $all_forms as $form ) : ?>
							<option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $form->id, $current_form ); ?>>
								<?php echo esc_html( $form->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<!-- Date range -->
			<div class="xl-filter-group">
				<label class="xl-filter-label"><?php esc_html_e( 'From:', 'xtremeleads' ); ?></label>
				<input type="date" name="xl_date_from" class="xl-input" value="<?php echo esc_attr( $date_from ); ?>">
			</div>
			<div class="xl-filter-group">
				<label class="xl-filter-label"><?php esc_html_e( 'To:', 'xtremeleads' ); ?></label>
				<input type="date" name="xl_date_to" class="xl-input" value="<?php echo esc_attr( $date_to ); ?>">
			</div>

			<div class="xl-filter-actions">
				<button type="submit" class="button xl-btn-secondary"><?php esc_html_e( 'Filter', 'xtremeleads' ); ?></button>
				<a href="<?php echo esc_url( xl_filter_url() ); ?>" class="button"><?php esc_html_e( 'Reset', 'xtremeleads' ); ?></a>
			</div>
		</div>
	</form>

	<?php if ( empty( $leads ) ) : ?>
		<div class="xl-empty-state">
			<span class="dashicons dashicons-email-alt xl-empty-icon"></span>
			<?php if ( 'my_leads' === $current_filter ) : ?>
				<h2><?php esc_html_e( 'No leads assigned to you', 'xtremeleads' ); ?></h2>
				<p><?php esc_html_e( 'You currently have no leads assigned to you.', 'xtremeleads' ); ?></p>
			<?php elseif ( ! empty( $filters ) ) : ?>
				<h2><?php esc_html_e( 'No leads match your filters', 'xtremeleads' ); ?></h2>
				<p><?php esc_html_e( 'Try adjusting your filter criteria.', 'xtremeleads' ); ?></p>
				<a href="<?php echo esc_url( xl_filter_url() ); ?>" class="button button-primary xl-btn-primary">
					<?php esc_html_e( 'Clear Filters', 'xtremeleads' ); ?>
				</a>
			<?php else : ?>
				<h2><?php esc_html_e( 'No leads yet', 'xtremeleads' ); ?></h2>
				<p><?php esc_html_e( 'When visitors submit your forms, their leads will appear here.', 'xtremeleads' ); ?></p>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-forms', 'xl_action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary xl-btn-primary">
					<?php esc_html_e( 'Create Your First Form', 'xtremeleads' ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php else : ?>

		<!-- Bulk action bar + table -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xl-leads-form">
			<input type="hidden" name="action" value="xl_bulk_leads">
			<?php wp_nonce_field( 'xl_bulk_leads' ); ?>
			<!-- Preserve filter state in bulk form -->
			<?php if ( $current_status ) : ?><input type="hidden" name="xl_status" value="<?php echo esc_attr( $current_status ); ?>"><?php endif; ?>
			<?php if ( $current_form ) : ?><input type="hidden" name="xl_form" value="<?php echo esc_attr( $current_form ); ?>"><?php endif; ?>
			<?php if ( $date_from ) : ?><input type="hidden" name="xl_date_from" value="<?php echo esc_attr( $date_from ); ?>"><?php endif; ?>
			<?php if ( $date_to ) : ?><input type="hidden" name="xl_date_to" value="<?php echo esc_attr( $date_to ); ?>"><?php endif; ?>
			<?php foreach ( $current_tag_ids as $tid ) : ?><input type="hidden" name="xl_tags[]" value="<?php echo esc_attr( $tid ); ?>"><?php endforeach; ?>
			<?php if ( 'my_leads' === $current_filter ) : ?><input type="hidden" name="xl_filter" value="my_leads"><?php endif; ?>

			<div class="xl-table-toolbar">
				<div class="xl-bulk-actions">
					<select name="bulk_action" id="xl-bulk-action-select" class="xl-select-sm">
						<option value=""><?php esc_html_e( 'Bulk Actions', 'xtremeleads' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'xtremeleads' ); ?></option>
						<option value="mark_contacted"><?php esc_html_e( 'Mark as Contacted', 'xtremeleads' ); ?></option>
						<option value="export_selected"><?php esc_html_e( 'Export Selected', 'xtremeleads' ); ?></option>
					</select>
					<button type="submit" class="button xl-btn-secondary" id="xl-bulk-apply">
						<?php esc_html_e( 'Apply', 'xtremeleads' ); ?>
					</button>
				</div>
				<div class="xl-table-count">
					<?php
					printf(
						/* translators: %d: total leads */
						esc_html( _n( '%d lead total', '%d leads total', $total_leads, 'xtremeleads' ) ),
						esc_html( number_format_i18n( $total_leads ) )
					);
					?>
				</div>
			</div>

			<table class="xl-table widefat" id="xl-leads-table">
				<thead>
					<tr>
						<th class="xl-col-cb"><input type="checkbox" id="xl-select-all" aria-label="<?php esc_attr_e( 'Select all leads', 'xtremeleads' ); ?>"></th>
						<th class="xl-col-id"><?php esc_html_e( 'ID', 'xtremeleads' ); ?></th>
						<th class="xl-col-name"><?php esc_html_e( 'Name', 'xtremeleads' ); ?></th>
						<th class="xl-col-email"><?php esc_html_e( 'Email', 'xtremeleads' ); ?></th>
						<th class="xl-col-form"><?php esc_html_e( 'Source Form', 'xtremeleads' ); ?></th>
						<th class="xl-col-assigned"><?php esc_html_e( 'Assigned To', 'xtremeleads' ); ?></th>
						<th class="xl-col-tags"><?php esc_html_e( 'Tags', 'xtremeleads' ); ?></th>
						<th class="xl-col-status"><?php esc_html_e( 'Status', 'xtremeleads' ); ?></th>
						<th class="xl-col-date"><?php esc_html_e( 'Date', 'xtremeleads' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $leads as $lead ) : ?>
						<?php
						$lead_id = (int) $lead->id;
						$form_name = isset( $forms_cache[ (int) $lead->form_id ] )
							? esc_html( $forms_cache[ (int) $lead->form_id ]->name )
							: esc_html__( '(unknown form)', 'xtremeleads' );
						$lead_name = esc_html( xl_inbox_get_field( $lead, 'text', $forms_cache ) );
						$lead_email = esc_html( xl_inbox_get_field( $lead, 'email', $forms_cache ) );
						$status_key = $lead->status ?? 'new';
						$status_lbl = $statuses[ $status_key ] ?? ucfirst( $status_key );

						// Assigned user.
						$assigned_to = (int) ( $lead->assigned_to ?? 0 );
						$assignee_display = '';
						if ( $assigned_to ) {
							$assignee_user = get_userdata( $assigned_to );
							$assignee_display = $assignee_user ? esc_html( $assignee_user->display_name ) : esc_html__( '(unknown)', 'xtremeleads' );
						}

						// Lead tags.
						$lead_tags = $tags_by_lead[ $lead_id ] ?? array();

						// Duplicate badge data.
						$is_duplicate = ! empty( $lead->is_duplicate );
						$duplicate_status = $lead->duplicate_status ?? '';
						$original_lead_id = ! empty( $lead->original_lead_id ) ? (int) $lead->original_lead_id : null;
						$is_orphaned = 'duplicate_orphaned' === $duplicate_status;

						// Detail page URL.
						$detail_url = add_query_arg(
							array( 'page' => 'xtremeleads-leads', 'xl_action' => 'view', 'lead_id' => $lead_id ),
							admin_url( 'admin.php' )
						);
						?>
						<tr class="xl-lead-row<?php echo $is_duplicate ? ' xl-lead-duplicate' : ''; ?>" data-lead-id="<?php echo esc_attr( $lead_id ); ?>">
							<td class="xl-col-cb" onclick="event.stopPropagation()">
								<input type="checkbox" name="lead_ids[]" value="<?php echo esc_attr( $lead_id ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Select lead #%d', 'xtremeleads' ), $lead_id ) ); ?>">
							</td>
							<td class="xl-col-id">
								<a href="<?php echo esc_url( $detail_url ); ?>" onclick="event.stopPropagation()">
									<code>#<?php echo esc_html( $lead_id ); ?></code>
								</a>
								<?php if ( $is_duplicate ) : ?>
									<span class="xl-status-badge xl-status-duplicate xl-duplicate-badge">
										<?php if ( $is_orphaned ) : ?>
											<?php esc_html_e( 'Duplicate (original deleted)', 'xtremeleads' ); ?>
										<?php else : ?>
											<?php esc_html_e( 'Duplicate', 'xtremeleads' ); ?>
											<?php if ( $original_lead_id ) : ?>
												&mdash;
												<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-leads', 'xl_action' => 'view', 'lead_id' => $original_lead_id ), admin_url( 'admin.php' ) ) ); ?>" onclick="event.stopPropagation()">
													<?php echo esc_html( sprintf( __( 'Original #%d', 'xtremeleads' ), $original_lead_id ) ); ?>
												</a>
											<?php endif; ?>
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</td>
							<td class="xl-col-name">
								<a href="<?php echo esc_url( $detail_url ); ?>" onclick="event.stopPropagation()">
									<?php echo $lead_name ?: '<em class="xl-na">' . esc_html__( 'N/A', 'xtremeleads' ) . '</em>'; ?>
								</a>
							</td>
							<td class="xl-col-email"><?php echo $lead_email ?: '<em class="xl-na">' . esc_html__( 'N/A', 'xtremeleads' ) . '</em>'; ?></td>
							<td class="xl-col-form"><?php echo $form_name; ?></td>
							<td class="xl-col-assigned">
								<?php if ( $assignee_display ) : ?>
									<span class="xl-assignee-label"><?php echo $assignee_display; ?></span>
								<?php else : ?>
									<em class="xl-na"><?php esc_html_e( '—', 'xtremeleads' ); ?></em>
								<?php endif; ?>
							</td>
							<td class="xl-col-tags">
								<?php if ( ! empty( $lead_tags ) ) : ?>
									<div class="xl-tag-pills-sm">
										<?php foreach ( $lead_tags as $t ) : ?>
											<span class="xl-tag-pill-sm"><?php echo esc_html( $t->name ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<em class="xl-na">—</em>
								<?php endif; ?>
							</td>
							<td class="xl-col-status">
								<!-- Inline status badge with change selector -->
								<div class="xl-inline-status-wrap" onclick="event.stopPropagation()">
									<span class="xl-status-badge xl-status-<?php echo esc_attr( $status_key ); ?> xl-inline-status-badge" data-lead-id="<?php echo esc_attr( $lead_id ); ?>">
										<?php echo esc_html( $status_lbl ); ?>
									</span>
									<select class="xl-inline-status-select" data-lead-id="<?php echo esc_attr( $lead_id ); ?>" style="display:none;">
										<?php foreach ( $statuses as $slug => $label ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $status_key ); ?>>
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							</td>
							<td class="xl-col-date">
								<time datetime="<?php echo esc_attr( $lead->created_at ); ?>">
									<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $lead->created_at . ' UTC' ) ) ); ?>
								</time>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

		</form>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="xl-pagination">
				<?php
				$page_links = paginate_links(
					array(
						'base' => add_query_arg( 'paged', '%#%' ),
						'format' => '',
						'prev_text' => '&laquo; ' . __( 'Previous', 'xtremeleads' ),
						'next_text' => __( 'Next', 'xtremeleads' ) . ' &raquo;',
						'total' => $total_pages,
						'current' => $current_page,
					)
				);
				echo wp_kses_post( $page_links );
				?>
			</div>
		<?php endif; ?>

	<?php endif; ?>
</div>
