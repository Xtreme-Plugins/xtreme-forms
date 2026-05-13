<?php
/**
 * Leads Inbox admin page — with status/tag/assignment filtering.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
}

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Capability check enforced above; page callback is also registered with 'manage_options'. GET filter params are sanitized on read.

// ── Read active filters from URL ─────────────────────────────────────────────

$current_status = isset( $_GET['xtremeforms_status'] ) ? sanitize_text_field( wp_unslash( $_GET['xtremeforms_status'] ) ) : '';
$current_form   = isset( $_GET['xtremeforms_form'] ) ? absint( $_GET['xtremeforms_form'] ) : 0;
$current_filter = isset( $_GET['xtremeforms_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['xtremeforms_filter'] ) ) : '';
$date_from      = isset( $_GET['xtremeforms_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['xtremeforms_date_from'] ) ) : '';
$date_to        = isset( $_GET['xtremeforms_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['xtremeforms_date_to'] ) ) : '';

// Tag filter — multi-value.
$current_tag_ids = array();
if ( isset( $_GET['xtremeforms_tags'] ) && is_array( $_GET['xtremeforms_tags'] ) ) {
	$current_tag_ids = array_values( array_filter( array_map( 'absint', wp_unslash( $_GET['xtremeforms_tags'] ) ) ) );
}

// Build $filters array for Xtremeforms_Leads::get_leads_filtered().
$filters = array();

if ( $current_status && array_key_exists( $current_status, Xtremeforms_Leads::get_statuses() ) ) {
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
$per_page     = 20;
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$result      = Xtremeforms_Leads::get_leads_filtered( $filters, $current_page, $per_page );
$leads       = $result['leads'];
$total_leads = $result['total'];
$total_pages = $total_leads > 0 ? (int) ceil( $total_leads / $per_page ) : 1;

// If requested page is beyond last page, redirect to last page.
if ( $current_page > $total_pages && $total_leads > 0 ) {
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'  => 'xtreme-forms',
				'paged' => $total_pages,
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}

// Pre-fetch forms and tags for the current page.
$form_ids    = array_unique( array_map( static fn( $l ) => (int) $l->form_id, $leads ) );
$forms_cache = Xtremeforms_Forms::get_forms_by_ids( $form_ids );

$lead_ids_current = array_map( static fn( $l ) => (int) $l->id, $leads );
$tags_by_lead     = Xtremeforms_Tags::get_tags_for_leads( $lead_ids_current );

$statuses  = Xtremeforms_Leads::get_statuses();
$all_tags  = Xtremeforms_Tags::get_all_tags();
$all_forms = Xtremeforms_Forms::get_all_forms();

// ── Helper: extract field value from lead ────────────────────────────────────

/**
 * @param object $lead Lead row.
 * @param string $field_type Field type to look for.
 * @param array $forms_cache Forms keyed by ID.
 * @return string
 */
if ( ! function_exists( 'xtremeforms_inbox_get_field' ) ) :
	function xtremeforms_inbox_get_field( object $lead, string $field_type, array $forms_cache ): string {
		$form = $forms_cache[ (int) $lead->form_id ] ?? null;
		if ( ! $form ) {
			return '';
		}
		$field_defs   = Xtremeforms_Forms::decode_fields( $form );
		$field_values = Xtremeforms_Leads::decode_field_values( $lead );

		// "Name" column: prefer a text field whose label/ID mentions "name", else first text field.
		if ( 'text' === $field_type ) {
			$best_match = null;
			$first_text = null;
			foreach ( $field_defs as $fd ) {
				if ( ( $fd['type'] ?? '' ) === 'text' ) {
					if ( null === $first_text ) {
						$first_text = $fd;
					}
					$haystack = strtolower( ( $fd['label'] ?? '' ) . ' ' . ( $fd['id'] ?? '' ) );
					// Skip text fields that are clearly email/phone (user-misclassified field types).
					if ( false !== strpos( $haystack, 'email' ) || false !== strpos( $haystack, 'phone' ) || false !== strpos( $haystack, 'cell' ) ) {
						continue;
					}
					if ( false !== strpos( $haystack, 'name' ) ) {
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
			$val = is_array( $val ) ? implode( ', ', $val ) : (string) $val;
			// If the resolved "name" actually looks like an email, treat it as missing
			// so the column doesn't duplicate the Email column.
			return is_email( trim( $val ) ) ? '' : $val;
		}

		// "Email" column: smart detection (handles forms where Email field is type "text").
		if ( 'email' === $field_type ) {
			return Xtremeforms_Leads::detect_email( $field_defs, $field_values );
		}

		// "Phone" column: smart detection.
		if ( 'phone' === $field_type ) {
			return Xtremeforms_Leads::detect_phone( $field_defs, $field_values );
		}

		// Fallback: strict type match.
		foreach ( $field_defs as $fd ) {
			if ( ( $fd['type'] ?? '' ) === $field_type ) {
				$val = $field_values[ $fd['id'] ?? '' ] ?? '';
				return is_array( $val ) ? implode( ', ', $val ) : (string) $val;
			}
		}
		return '';
	}
endif; // xtremeforms_inbox_get_field

// ── Build base URL for filters (preserves all active filters in links) ────────
if ( ! function_exists( 'xtremeforms_filter_url' ) ) :
	function xtremeforms_filter_url( array $args = array() ): string {
		$base = array( 'page' => 'xtreme-forms-leads' );
		return add_query_arg( array_merge( $base, $args ), admin_url( 'admin.php' ) );
	}
endif;

// ── Admin notices ─────────────────────────────────────────────────────────────
$notice_html = '';
if ( ! empty( $_GET['bulk_deleted'] ) ) {
	$count = absint( $_GET['bulk_deleted'] );
	/* translators: %d: number of deleted leads */
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . sprintf( _n( '%d lead deleted.', '%d leads deleted.', $count, 'xtreme-forms' ), $count ) . '</p></div>';
} elseif ( ! empty( $_GET['bulk_contacted'] ) ) {
	$count = absint( $_GET['bulk_contacted'] );
	/* translators: %d: number of leads marked as contacted */
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . sprintf( _n( '%d lead marked as contacted.', '%d leads marked as contacted.', $count, 'xtreme-forms' ), $count ) . '</p></div>';
} elseif ( isset( $_GET['bulk_error'] ) && 'no_selection' === $_GET['bulk_error'] ) {
	$notice_html = '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'No items selected.', 'xtreme-forms' ) . '</p></div>';
}

// Export nonce URL (used for full export link).
$export_filters = array( 'page' => 'xtreme-forms' );
if ( $current_status ) {
	$export_filters['xtremeforms_status'] = $current_status;
}
if ( $current_form ) {
	$export_filters['xtremeforms_form'] = $current_form;
}
if ( $date_from ) {
	$export_filters['xtremeforms_date_from'] = $date_from;
}
if ( $date_to ) {
	$export_filters['xtremeforms_date_to'] = $date_to;
}
if ( ! empty( $current_tag_ids ) ) {
	$export_filters['xtremeforms_tags'] = $current_tag_ids;
}
if ( 'my_leads' === $current_filter ) {
	$export_filters['xtremeforms_filter'] = 'my_leads';
}
$export_url = wp_nonce_url(
	add_query_arg(
		array_merge( array( 'action' => 'xtremeforms_export_leads' ), $export_filters ),
		admin_url( 'admin-post.php' )
	),
	'xtremeforms_export_leads'
);
?>
<div class="wrap xf-wrap">
	<div class="xf-page-header">
		<h1 class="xf-page-title">
			<?php esc_html_e( 'Leads Inbox', 'xtreme-forms' ); ?>
			<span class="xf-badge xf-badge-count"><?php echo esc_html( number_format_i18n( $total_leads ) ); ?></span>
		</h1>
		<div class="xf-header-actions">
			<a href="<?php echo esc_url( $export_url ); ?>" class="xf-btn xf-btn-secondary">
				<span class="dashicons dashicons-download"></span>
				<?php esc_html_e( 'Export CSV', 'xtreme-forms' ); ?>
			</a>
		</div>
	</div>

	<?php echo wp_kses_post( $notice_html ); ?>

	<!-- Status Filter Tabs -->
	<div class="xf-status-tabs" role="tablist">
		<a
			href="<?php echo esc_url( xtremeforms_filter_url() ); ?>"
			class="xf-tab <?php echo ( '' === $current_status && 'my_leads' !== $current_filter ) ? 'xf-tab-active' : ''; ?>"
			role="tab"
		>
			<?php esc_html_e( 'All', 'xtreme-forms' ); ?>
		</a>
		<?php foreach ( $statuses as $slug => $label ) : ?>
			<a
				href="<?php echo esc_url( xtremeforms_filter_url( array( 'xtremeforms_status' => $slug ) ) ); ?>"
				class="xf-tab <?php echo ( $current_status === $slug ) ? 'xf-tab-active' : ''; ?>"
				role="tab"
			>
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
		<a
			href="<?php echo esc_url( xtremeforms_filter_url( array( 'xtremeforms_filter' => 'my_leads' ) ) ); ?>"
			class="xf-tab xf-tab-my-leads <?php echo ( 'my_leads' === $current_filter ) ? 'xf-tab-active' : ''; ?>"
			role="tab"
		>
			<?php esc_html_e( 'My Leads', 'xtreme-forms' ); ?>
		</a>
	</div>

	<!-- Tag / Form / Date Filters -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="xf-filter-form" id="xf-filter-form">
		<input type="hidden" name="page" value="xtreme-forms-leads">
		<?php if ( $current_status ) : ?>
			<input type="hidden" name="xtremeforms_status" value="<?php echo esc_attr( $current_status ); ?>">
		<?php endif; ?>
		<?php if ( 'my_leads' === $current_filter ) : ?>
			<input type="hidden" name="xtremeforms_filter" value="my_leads">
		<?php endif; ?>

		<div class="xf-filter-bar">
			<!-- Tag filter -->
			<?php if ( ! empty( $all_tags ) ) : ?>
				<div class="xf-filter-group">
					<label class="xf-filter-label"><?php esc_html_e( 'Tags:', 'xtreme-forms' ); ?></label>
					<div class="xf-tag-checkboxes">
						<?php foreach ( $all_tags as $tag ) : ?>
							<label class="xf-tag-checkbox-label">
								<input
									type="checkbox"
									name="xf_tags[]"
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
				<div class="xf-filter-group">
					<label class="xf-filter-label" for="xf-form-filter"><?php esc_html_e( 'Form:', 'xtreme-forms' ); ?></label>
					<select name="xtremeforms_form" id="xf-form-filter" class="xf-select-sm">
						<option value=""><?php esc_html_e( 'All Forms', 'xtreme-forms' ); ?></option>
						<?php foreach ( $all_forms as $form ) : ?>
							<option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $form->id, $current_form ); ?>>
								<?php echo esc_html( $form->name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			<?php endif; ?>

			<!-- Date range -->
			<div class="xf-filter-group">
				<label class="xf-filter-label"><?php esc_html_e( 'From:', 'xtreme-forms' ); ?></label>
				<input type="date" name="xtremeforms_date_from" class="xf-input" value="<?php echo esc_attr( $date_from ); ?>">
			</div>
			<div class="xf-filter-group">
				<label class="xf-filter-label"><?php esc_html_e( 'To:', 'xtreme-forms' ); ?></label>
				<input type="date" name="xtremeforms_date_to" class="xf-input" value="<?php echo esc_attr( $date_to ); ?>">
			</div>

			<div class="xf-filter-actions">
				<button type="submit" class="button xf-btn-secondary"><?php esc_html_e( 'Filter', 'xtreme-forms' ); ?></button>
				<a href="<?php echo esc_url( xtremeforms_filter_url() ); ?>" class="button"><?php esc_html_e( 'Reset', 'xtreme-forms' ); ?></a>
			</div>
		</div>
	</form>

	<?php if ( empty( $leads ) ) : ?>
		<div class="xf-empty-state">
			<span class="dashicons dashicons-email-alt xf-empty-icon"></span>
			<?php if ( 'my_leads' === $current_filter ) : ?>
				<h2><?php esc_html_e( 'No leads assigned to you', 'xtreme-forms' ); ?></h2>
				<p><?php esc_html_e( 'You currently have no leads assigned to you.', 'xtreme-forms' ); ?></p>
			<?php elseif ( ! empty( $filters ) ) : ?>
				<h2><?php esc_html_e( 'No leads match your filters', 'xtreme-forms' ); ?></h2>
				<p><?php esc_html_e( 'Try adjusting your filter criteria.', 'xtreme-forms' ); ?></p>
				<a href="<?php echo esc_url( xtremeforms_filter_url() ); ?>" class="button button-primary xf-btn-primary">
					<?php esc_html_e( 'Clear Filters', 'xtreme-forms' ); ?>
				</a>
			<?php else : ?>
				<h2><?php esc_html_e( 'No leads yet', 'xtreme-forms' ); ?></h2>
				<p><?php esc_html_e( 'When visitors submit your forms, their leads will appear here.', 'xtreme-forms' ); ?></p>
				<?php if ( ! empty( $all_forms ) ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtreme-forms-forms' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary xf-btn-primary">
						<?php esc_html_e( 'View Your Forms', 'xtreme-forms' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtreme-forms-forms', 'xtremeforms_action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary xf-btn-primary">
						<?php esc_html_e( 'Create Your First Form', 'xtreme-forms' ); ?>
					</a>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php else : ?>

		<!-- Bulk action bar + table -->
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xf-leads-form">
			<input type="hidden" name="action" value="xtremeforms_bulk_leads">
			<?php wp_nonce_field( 'xtremeforms_bulk_leads' ); ?>
			<!-- Preserve filter state in bulk form -->
			<?php
			if ( $current_status ) :
				?>
				<input type="hidden" name="xtremeforms_status" value="<?php echo esc_attr( $current_status ); ?>"><?php endif; ?>
			<?php
			if ( $current_form ) :
				?>
				<input type="hidden" name="xtremeforms_form" value="<?php echo esc_attr( $current_form ); ?>"><?php endif; ?>
			<?php
			if ( $date_from ) :
				?>
				<input type="hidden" name="xtremeforms_date_from" value="<?php echo esc_attr( $date_from ); ?>"><?php endif; ?>
			<?php
			if ( $date_to ) :
				?>
				<input type="hidden" name="xtremeforms_date_to" value="<?php echo esc_attr( $date_to ); ?>"><?php endif; ?>
			<?php
			foreach ( $current_tag_ids as $tid ) :
				?>
				<input type="hidden" name="xf_tags[]" value="<?php echo esc_attr( $tid ); ?>"><?php endforeach; ?>
			<?php
			if ( 'my_leads' === $current_filter ) :
				?>
				<input type="hidden" name="xtremeforms_filter" value="my_leads"><?php endif; ?>

			<div class="xf-table-toolbar">
				<div class="xf-bulk-actions">
					<select name="bulk_action" id="xf-bulk-action-select" class="xf-select-sm">
						<option value=""><?php esc_html_e( 'Bulk Actions', 'xtreme-forms' ); ?></option>
						<option value="delete"><?php esc_html_e( 'Delete', 'xtreme-forms' ); ?></option>
						<option value="mark_contacted"><?php esc_html_e( 'Mark as Contacted', 'xtreme-forms' ); ?></option>
						<option value="export_selected"><?php esc_html_e( 'Export Selected', 'xtreme-forms' ); ?></option>
					</select>
					<button type="submit" class="button xf-btn-secondary" id="xf-bulk-apply">
						<?php esc_html_e( 'Apply', 'xtreme-forms' ); ?>
					</button>
				</div>
				<div class="xf-table-count">
					<?php
					printf(
						/* translators: %d: total leads */
						esc_html( _n( '%d lead total', '%d leads total', $total_leads, 'xtreme-forms' ) ),
						esc_html( number_format_i18n( $total_leads ) )
					);
					?>
				</div>
			</div>

			<table class="xf-table widefat" id="xf-leads-table">
				<thead>
					<tr>
						<th class="xf-col-cb"><input type="checkbox" id="xf-select-all" aria-label="<?php esc_attr_e( 'Select all leads', 'xtreme-forms' ); ?>"></th>
						<th class="xf-col-id"><?php esc_html_e( 'ID', 'xtreme-forms' ); ?></th>
						<th class="xf-col-name"><?php esc_html_e( 'Name', 'xtreme-forms' ); ?></th>
						<th class="xf-col-email"><?php esc_html_e( 'Email', 'xtreme-forms' ); ?></th>
						<th class="xf-col-form"><?php esc_html_e( 'Source Form', 'xtreme-forms' ); ?></th>
						<th class="xf-col-assigned"><?php esc_html_e( 'Assigned To', 'xtreme-forms' ); ?></th>
						<th class="xf-col-tags"><?php esc_html_e( 'Tags', 'xtreme-forms' ); ?></th>
						<th class="xf-col-status"><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></th>
						<th class="xf-col-date"><?php esc_html_e( 'Date', 'xtreme-forms' ); ?></th>
						<th class="xf-col-actions"><?php esc_html_e( 'Actions', 'xtreme-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $leads as $lead ) : ?>
						<?php
						$lead_id    = (int) $lead->id;
						$form_name  = isset( $forms_cache[ (int) $lead->form_id ] )
							? esc_html( $forms_cache[ (int) $lead->form_id ]->name )
							: esc_html__( '(unknown form)', 'xtreme-forms' );
						$lead_name  = esc_html( xtremeforms_inbox_get_field( $lead, 'text', $forms_cache ) );
						$lead_email = esc_html( xtremeforms_inbox_get_field( $lead, 'email', $forms_cache ) );
						$status_key = $lead->status ?? 'new';
						$status_lbl = $statuses[ $status_key ] ?? ucfirst( $status_key );

						// Assigned user.
						$assigned_to      = (int) ( $lead->assigned_to ?? 0 );
						$assignee_display = '';
						if ( $assigned_to ) {
							$assignee_user    = get_userdata( $assigned_to );
							$assignee_display = $assignee_user ? esc_html( $assignee_user->display_name ) : esc_html__( '(unknown)', 'xtreme-forms' );
						}

						// Lead tags.
						$lead_tags = $tags_by_lead[ $lead_id ] ?? array();

						// Duplicate badge data.
						$is_duplicate     = ! empty( $lead->is_duplicate );
						$duplicate_status = $lead->duplicate_status ?? '';
						$original_lead_id = ! empty( $lead->original_lead_id ) ? (int) $lead->original_lead_id : null;
						$is_orphaned      = 'duplicate_orphaned' === $duplicate_status;

						// Detail page URL.
						$detail_url = add_query_arg(
							array(
								'page'      => 'xtreme-forms-leads',
								'xtremeforms_action' => 'view',
								'lead_id'   => $lead_id,
							),
							admin_url( 'admin.php' )
						);
						?>
						<tr class="xf-lead-row<?php echo $is_duplicate ? ' xf-lead-duplicate' : ''; ?>" data-lead-id="<?php echo esc_attr( $lead_id ); ?>">
							<td class="xf-col-cb" onclick="event.stopPropagation()">
								<input type="checkbox" name="lead_ids[]" value="<?php echo esc_attr( $lead_id ); ?>" aria-label="<?php /* translators: %d: lead ID */ echo esc_attr( sprintf( __( 'Select lead #%d', 'xtreme-forms' ), $lead_id ) ); ?>">
							</td>
							<td class="xf-col-id">
								<a href="<?php echo esc_url( $detail_url ); ?>" onclick="event.stopPropagation()">
									<code>#<?php echo esc_html( $lead_id ); ?></code>
								</a>
								<?php if ( $is_duplicate ) : ?>
									<span class="xf-status-badge xf-status-duplicate xf-duplicate-badge">
										<?php if ( $is_orphaned ) : ?>
											<?php esc_html_e( 'Duplicate (original deleted)', 'xtreme-forms' ); ?>
										<?php else : ?>
											<?php esc_html_e( 'Duplicate', 'xtreme-forms' ); ?>
											<?php if ( $original_lead_id ) : ?>
												&mdash;
												<a href="
												<?php
												echo esc_url(
													add_query_arg(
														array(
															'page' => 'xtreme-forms-leads',
															'xtremeforms_action' => 'view',
															'lead_id' => $original_lead_id,
														),
														admin_url( 'admin.php' )
													)
												);
												?>
															" onclick="event.stopPropagation()">
													<?php /* translators: %d: original lead ID */ echo esc_html( sprintf( __( 'Original #%d', 'xtreme-forms' ), $original_lead_id ) ); ?>
												</a>
											<?php endif; ?>
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</td>
							<td class="xf-col-name">
								<a href="<?php echo esc_url( $detail_url ); ?>" onclick="event.stopPropagation()">
									<?php echo wp_kses_post( $lead_name ?: '<em class="xf-na">' . esc_html__( 'N/A', 'xtreme-forms' ) . '</em>' ); ?>
								</a>
							</td>
							<td class="xf-col-email"><?php echo wp_kses_post( $lead_email ?: '<em class="xf-na">' . esc_html__( 'N/A', 'xtreme-forms' ) . '</em>' ); ?></td>
							<td class="xf-col-form"><?php echo wp_kses_post( $form_name ); ?></td>
							<td class="xf-col-assigned">
								<?php if ( $assignee_display ) : ?>
									<span class="xf-assignee-label"><?php echo wp_kses_post( $assignee_display ); ?></span>
								<?php else : ?>
									<em class="xf-na"><?php esc_html_e( '—', 'xtreme-forms' ); ?></em>
								<?php endif; ?>
							</td>
							<td class="xf-col-tags">
								<?php if ( ! empty( $lead_tags ) ) : ?>
									<div class="xf-tag-pills-sm">
										<?php foreach ( $lead_tags as $t ) : ?>
											<span class="xf-tag-pill-sm"><?php echo esc_html( $t->name ); ?></span>
										<?php endforeach; ?>
									</div>
								<?php else : ?>
									<em class="xf-na">—</em>
								<?php endif; ?>
							</td>
							<td class="xf-col-status">
								<!-- Inline status badge with change selector -->
								<div class="xf-inline-status-wrap" onclick="event.stopPropagation()">
									<span class="xf-status-badge xf-status-<?php echo esc_attr( $status_key ); ?> xf-inline-status-badge" data-lead-id="<?php echo esc_attr( $lead_id ); ?>">
										<?php echo esc_html( $status_lbl ); ?>
									</span>
									<select class="xf-inline-status-select" data-lead-id="<?php echo esc_attr( $lead_id ); ?>" style="display:none;">
										<?php foreach ( $statuses as $slug => $label ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $status_key ); ?>>
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
							</td>
							<td class="xf-col-date">
								<time datetime="<?php echo esc_attr( $lead->created_at ); ?>">
									<?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $lead->created_at . ' UTC' ) ) ); ?>
								</time>
							</td>
							<td class="xf-col-actions" onclick="event.stopPropagation()">
								<button type="button"
									class="button button-small xf-row-resend"
									data-lead-id="<?php echo esc_attr( $lead_id ); ?>"
									title="<?php esc_attr_e( 'Resend lead notification email', 'xtreme-forms' ); ?>"
									aria-label="<?php /* translators: %d: lead ID */ echo esc_attr( sprintf( __( 'Resend notification for lead #%d', 'xtreme-forms' ), $lead_id ) ); ?>">
									<span class="dashicons dashicons-email-alt"></span>
									<?php esc_html_e( 'Resend', 'xtreme-forms' ); ?>
								</button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

		</form>

		<!-- Pagination -->
		<?php if ( $total_pages > 1 ) : ?>
			<div class="xf-pagination">
				<?php
				$page_links = paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo; ' . __( 'Previous', 'xtreme-forms' ),
						'next_text' => __( 'Next', 'xtreme-forms' ) . ' &raquo;',
						'total'     => $total_pages,
						'current'   => $current_page,
					)
				);
				echo wp_kses_post( $page_links );
				?>
			</div>
		<?php endif; ?>

	<?php endif; ?>
</div>
