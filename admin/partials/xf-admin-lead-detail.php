<?php
/**
 * Lead Detail View – dedicated admin page.
 *
 * Accessible via: ?page=xtremeleads&xf_action=view&lead_id=X
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Filter parameters on this admin display page are read-only GET params — no nonce required for display-only filtering.

$lead_id = isset( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0;

if ( ! $lead_id ) {
	wp_die( esc_html__( 'Invalid lead ID.', 'xtreme-forms' ) );
}

$lead = XF_Leads::get_lead( $lead_id );
if ( ! $lead ) {
	wp_die( esc_html__( 'Lead not found.', 'xtreme-forms' ) );
}

$form         = XF_Forms::get_form( (int) $lead->form_id );
$form_name    = $form ? esc_html( $form->name ) : esc_html__( '(deleted form)', 'xtreme-forms' );
$statuses     = XF_Leads::get_statuses();
$status_key   = $lead->status ?? 'new';
$status_label = $statuses[ $status_key ] ?? ucfirst( $status_key );

// Field values with labels.
$field_values       = XF_Leads::decode_field_values( $lead );
$fields_with_labels = array();

if ( $form ) {
	$field_defs = XF_Forms::decode_fields( $form );
	foreach ( $field_defs as $fd ) {
		$fid   = $fd['id'] ?? '';
		$ftype = $fd['type'] ?? 'text';
		if ( 'hidden' === $ftype ) {
			continue;
		}
		$label = $fd['label'] ?? $fid;
		// Show all fields, even those not in field_values (N/A for missing).
		$val = array_key_exists( $fid, $field_values ) ? $field_values[ $fid ] : null;
		if ( is_array( $val ) ) {
			$val = implode( ', ', $val );
		}
		$fields_with_labels[] = array(
			'label'    => (string) $label,
			'value'    => null !== $val ? (string) $val : null,
			'is_empty' => ( null === $val || '' === (string) $val ),
		);
	}
} else {
	// Form deleted — show raw key/value pairs from stored field_values.
	foreach ( $field_values as $key => $val ) {
		if ( is_array( $val ) ) {
			$val = implode( ', ', $val );
		}
		$fields_with_labels[] = array(
			'label'    => $key,
			'value'    => (string) $val,
			'is_empty' => '' === (string) $val,
		);
	}
}

// Also append any field_values keys not already in form definition (e.g. from removed fields).
if ( $form ) {
	$covered_fids = array();
	foreach ( XF_Forms::decode_fields( $form ) as $fd ) {
		$covered_fids[] = $fd['id'] ?? '';
	}
	foreach ( $field_values as $key => $val ) {
		if ( in_array( $key, $covered_fids, true ) ) {
			continue; // Already covered by form definition loop above.
		}
		if ( is_array( $val ) ) {
			$val = implode( ', ', $val );
		}
		$fields_with_labels[] = array(
			'label'    => $key,
			'value'    => (string) $val,
			'is_empty' => '' === (string) $val,
		);
	}
}

// Assigned user.
$assigned_to   = (int) ( $lead->assigned_to ?? 0 );
$assignee_name = $assigned_to ? '' : esc_html__( 'Unassigned', 'xtreme-forms' );
if ( $assigned_to ) {
	$assignee_user = get_userdata( $assigned_to );
	$assignee_name = $assignee_user ? esc_html( $assignee_user->display_name ) : esc_html__( '(unknown user)', 'xtreme-forms' );
}

// Tags.
$tags = XF_Tags::get_tags_for_lead( $lead_id );

// Notes (oldest first).
$notes = XF_Notes::get_notes_for_lead( $lead_id );

// Activity (oldest first).
$activity = XF_Activity::get_activity_for_lead( $lead_id );

// Eligible users for assignment dropdown.
$eligible_users = XF_Leads::get_eligible_assignees();
$all_tags       = XF_Tags::get_all_tags();

// Back URL.
$back_url = add_query_arg( array( 'page' => 'xtreme-forms' ), admin_url( 'admin.php' ) );

// Admin notice for assignment email warning (from session / transient).
$notice_html    = '';
$email_warn_key = 'xf_assign_email_warn_' . $lead_id . '_' . get_current_user_id();
$email_warn     = get_transient( $email_warn_key );
if ( $email_warn ) {
	delete_transient( $email_warn_key );
	$notice_html = '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $email_warn ) . '</p></div>';
}
?>
<div class="wrap xf-wrap xf-lead-detail-wrap">

	<div class="xf-detail-nav">
		<a href="<?php echo esc_url( $back_url ); ?>" class="xf-back-link">
			<span class="dashicons dashicons-arrow-left-alt2"></span>
			<?php esc_html_e( 'Back to Leads', 'xtreme-forms' ); ?>
		</a>
	</div>

	<div class="xf-page-header">
		<h1 class="xf-page-title">
			<?php
			printf(
				/* translators: %d: lead ID */
				esc_html__( 'Lead #%d', 'xtreme-forms' ),
				absint( $lead_id )
			);
			?>
		</h1>
		<div class="xf-header-actions">
			<span class="xf-status-badge xf-status-<?php echo esc_attr( $status_key ); ?>" id="xf-detail-status-badge">
				<?php echo esc_html( $status_label ); ?>
			</span>
		</div>
	</div>

	<?php echo wp_kses_post( $notice_html ); ?>

	<div class="xf-detail-layout">

		<!-- Left Column: Submitted Data + Meta -->
		<div class="xf-detail-main">

			<!-- Submitted Form Fields -->
			<div class="xf-detail-card">
				<h2><?php esc_html_e( 'Submitted Data', 'xtreme-forms' ); ?></h2>
				<?php if ( empty( $fields_with_labels ) ) : ?>
					<p class="xf-na"><?php esc_html_e( 'No field data available.', 'xtreme-forms' ); ?></p>
				<?php else : ?>
					<table class="xf-detail-fields-table">
						<tbody>
							<?php foreach ( $fields_with_labels as $f ) : ?>
								<tr>
									<th><?php echo esc_html( $f['label'] ); ?></th>
									<td>
										<?php if ( $f['is_empty'] ) : ?>
											<em class="xf-na"><?php esc_html_e( 'N/A', 'xtreme-forms' ); ?></em>
										<?php else : ?>
											<?php echo esc_html( $f['value'] ); ?>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Metadata -->
			<div class="xf-detail-card">
				<h2><?php esc_html_e( 'Lead Metadata', 'xtreme-forms' ); ?></h2>
				<table class="xf-detail-fields-table">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Lead ID', 'xtreme-forms' ); ?></th>
							<td><code>#<?php echo esc_html( $lead_id ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Form', 'xtreme-forms' ); ?></th>
							<td><?php echo wp_kses_post( $form_name ); ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Submission Date', 'xtreme-forms' ); ?></th>
							<td>
								<time datetime="<?php echo esc_attr( $lead->created_at ); ?>">
									<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $lead->created_at . ' UTC' ) ) ); ?>
								</time>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Source URL', 'xtreme-forms' ); ?></th>
							<td>
								<?php if ( $lead->source_url ) : ?>
									<a href="<?php echo esc_url( $lead->source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $lead->source_url ); ?></a>
								<?php else : ?>
									<em class="xf-na"><?php esc_html_e( 'N/A', 'xtreme-forms' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'IP Address', 'xtreme-forms' ); ?></th>
							<td>
								<?php if ( $lead->ip_address ) : ?>
									<code><?php echo esc_html( $lead->ip_address ); ?></code>
								<?php else : ?>
									<em class="xf-na"><?php esc_html_e( 'N/A', 'xtreme-forms' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'User Agent', 'xtreme-forms' ); ?></th>
							<td>
								<?php if ( $lead->user_agent ) : ?>
									<small><?php echo esc_html( $lead->user_agent ); ?></small>
								<?php else : ?>
									<em class="xf-na"><?php esc_html_e( 'N/A', 'xtreme-forms' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
						<?php
						// Display GDPR consent status if the column exists on this lead.
						if ( property_exists( $lead, 'consent_given' ) ) :
							$consent_value = $lead->consent_given;
							?>
						<tr>
							<th><?php esc_html_e( 'GDPR Consent', 'xtreme-forms' ); ?></th>
							<td>
								<?php if ( null === $consent_value ) : ?>
									<em class="xf-na"><?php esc_html_e( 'N/A (no consent field on form)', 'xtreme-forms' ); ?></em>
								<?php elseif ( $consent_value ) : ?>
									<span style="display:inline-block;background:#28A745;color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">
										<?php esc_html_e( 'Consent Given', 'xtreme-forms' ); ?>
									</span>
								<?php else : ?>
									<span style="display:inline-block;background:#DC3545;color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">
										<?php esc_html_e( 'Consent Not Given', 'xtreme-forms' ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Notes Section -->
			<div class="xf-detail-card" id="xf-notes-section">
				<h2><?php esc_html_e( 'Notes', 'xtreme-forms' ); ?></h2>

				<div class="xf-notes-list" id="xf-notes-list">
					<?php if ( empty( $notes ) ) : ?>
						<p class="xf-notes-empty xf-na" id="xf-notes-empty"><?php esc_html_e( 'No notes yet. Add the first note below.', 'xtreme-forms' ); ?></p>
					<?php else : ?>
						<?php foreach ( $notes as $note ) : ?>
							<div class="xf-note-item" data-note-id="<?php echo esc_attr( $note->id ); ?>">
								<div class="xf-note-meta">
									<strong><?php echo esc_html( $note->author_name ); ?></strong>
									<time class="xf-note-time" datetime="<?php echo esc_attr( $note->created_at ); ?>">
										<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $note->created_at . ' UTC' ) ) ); ?>
									</time>
								</div>
								<div class="xf-note-content"><?php echo esc_html( $note->note_content ); ?></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<!-- Add Note Form -->
				<div class="xf-add-note-form">
					<label class="xf-label" for="xf-note-textarea"><?php esc_html_e( 'Add Note', 'xtreme-forms' ); ?></label>
					<textarea
						id="xf-note-textarea"
						class="xf-textarea xf-input-full"
						rows="4"
						placeholder="<?php esc_attr_e( 'Type your note here…', 'xtreme-forms' ); ?>"
					></textarea>
					<div class="xf-note-error xf-error-msg" style="display:none;"></div>
					<button type="button" id="xf-submit-note" class="button button-primary xf-btn-primary xf-mt-8">
						<?php esc_html_e( 'Add Note', 'xtreme-forms' ); ?>
					</button>
				</div>
			</div>

			<!-- Activity Timeline -->
			<div class="xf-detail-card" id="xf-activity-section">
				<h2><?php esc_html_e( 'Activity Timeline', 'xtreme-forms' ); ?></h2>
				<div class="xf-activity-list" id="xf-activity-list">
					<?php if ( empty( $activity ) ) : ?>
						<p class="xf-na"><?php esc_html_e( 'No activity recorded yet.', 'xtreme-forms' ); ?></p>
					<?php else : ?>
						<?php foreach ( $activity as $entry ) : ?>
							<div class="xf-activity-item xf-activity-<?php echo esc_attr( $entry->action_type ); ?>">
								<div class="xf-activity-dot"></div>
								<div class="xf-activity-body">
									<div class="xf-activity-label"><?php echo wp_kses_post( $entry->label ); ?></div>
									<time class="xf-activity-time" datetime="<?php echo esc_attr( $entry->created_at ); ?>">
										<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at . ' UTC' ) ) ); ?>
									</time>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- .xf-detail-main -->

		<!-- Right Column: Actions -->
		<div class="xf-detail-sidebar">

			<!-- Status -->
			<div class="xf-detail-card">
				<h2><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></h2>
				<div class="xf-field-group">
					<select id="xf-status-select" class="xf-select-full">
						<?php foreach ( $statuses as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $status_key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<div class="xf-status-feedback xf-feedback-msg" style="display:none;"></div>
					<button type="button" id="xf-save-status" class="button button-primary xf-btn-primary xf-mt-8 xf-btn-full">
						<?php esc_html_e( 'Save Status', 'xtreme-forms' ); ?>
					</button>
				</div>
			</div>

			<!-- Assignment -->
			<div class="xf-detail-card">
				<h2><?php esc_html_e( 'Assigned To', 'xtreme-forms' ); ?></h2>
				<div class="xf-field-group">
					<select id="xf-assignee-select" class="xf-select-full">
						<option value="0"><?php esc_html_e( 'Unassigned', 'xtreme-forms' ); ?></option>
						<?php foreach ( $eligible_users as $user ) : ?>
							<option value="<?php echo esc_attr( $user['id'] ); ?>" <?php selected( $user['id'], $assigned_to ); ?>>
								<?php echo esc_html( $user['display_name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<div class="xf-assign-feedback xf-feedback-msg" style="display:none;"></div>
					<button type="button" id="xf-save-assignee" class="button button-primary xf-btn-primary xf-mt-8 xf-btn-full">
						<?php esc_html_e( 'Save Assignment', 'xtreme-forms' ); ?>
					</button>
				</div>
			</div>

			<!-- Tags -->
			<div class="xf-detail-card">
				<h2><?php esc_html_e( 'Tags', 'xtreme-forms' ); ?></h2>

				<div class="xf-tags-list" id="xf-tags-list">
					<?php foreach ( $tags as $tag ) : ?>
						<span class="xf-tag-pill" data-tag-id="<?php echo esc_attr( $tag->id ); ?>">
							<?php echo esc_html( $tag->name ); ?>
							<button type="button" class="xf-tag-remove" data-tag-id="<?php echo esc_attr( $tag->id ); ?>" aria-label="<?php /* translators: %s: tag name */ echo esc_attr( sprintf( __( 'Remove tag %s', 'xtreme-forms' ), $tag->name ) ); ?>">&times;</button>
						</span>
					<?php endforeach; ?>
				</div>

				<div class="xf-tag-add-wrap">
					<div class="xf-tag-autocomplete-wrap">
						<input
							type="text"
							id="xf-tag-input"
							class="xf-input xf-input-full"
							placeholder="<?php esc_attr_e( 'Type to search tags…', 'xtreme-forms' ); ?>"
							autocomplete="off"
						>
						<ul class="xf-tag-suggestions" id="xf-tag-suggestions" role="listbox" hidden></ul>
					</div>
					<div class="xf-tag-feedback xf-feedback-msg" style="display:none;"></div>
				</div>
			</div>

		</div><!-- .xf-detail-sidebar -->

	</div><!-- .xf-detail-layout -->

</div><!-- .xf-wrap -->


<?php
/*
 * Lead-detail page bootstrap data + i18n strings.
 *
 * The dedicated JS (admin/js/xf-lead-detail.js) is enqueued via the shared
 * admin enqueue function. Per-render data and translatable strings are
 * attached here so the WordPress.org Plugin Check sees no inline <script>
 * tags in the rendered HTML.
 */
wp_localize_script(
	'xf-lead-detail',
	'xfLeadDetailData',
	array(
		'leadId'  => absint( $lead_id ),
		'nonce'   => wp_create_nonce( 'xf_admin_nonce' ),
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	)
);
wp_localize_script(
	'xf-lead-detail',
	'xfLeadDetailI18n',
	array(
		'saving'                => __( 'Saving…', 'xtreme-forms' ),
		'saveStatus'            => __( 'Save Status', 'xtreme-forms' ),
		'errorUpdatingStatus'   => __( 'Error updating status.', 'xtreme-forms' ),
		'statusUpdated'         => __( 'Status updated.', 'xtreme-forms' ),
		'statusChanged'         => __( 'Status changed', 'xtreme-forms' ),
		'saveAssignment'        => __( 'Save Assignment', 'xtreme-forms' ),
		'errorSavingAssignment' => __( 'Error saving assignment.', 'xtreme-forms' ),
		'assignmentSaved'       => __( 'Assignment saved.', 'xtreme-forms' ),
		'assignedTo'            => __( 'Assigned to', 'xtreme-forms' ),
		'noteEmpty'             => __( 'Note content cannot be empty.', 'xtreme-forms' ),
		'addNote'               => __( 'Add Note', 'xtreme-forms' ),
		'errorAddingNote'       => __( 'Error adding note.', 'xtreme-forms' ),
		'noteAdded'             => __( 'Note added.', 'xtreme-forms' ),
		'removeTag'             => __( 'Remove tag', 'xtreme-forms' ),
		'errorRemovingTag'      => __( 'Error removing tag.', 'xtreme-forms' ),
		'tagRemoved'            => __( 'Tag removed.', 'xtreme-forms' ),
		'errorApplyingTag'      => __( 'Error applying tag.', 'xtreme-forms' ),
		'tagAdded'              => __( 'Tag added.', 'xtreme-forms' ),
		'noTagsFound'           => __( 'No tags found.', 'xtreme-forms' ),
	)
);
