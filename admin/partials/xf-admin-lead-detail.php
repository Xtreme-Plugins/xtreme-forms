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

			<!-- Resend Notification -->
			<div class="xf-detail-card">
				<h2>
					<span class="dashicons dashicons-email-alt" style="vertical-align:middle;color:var(--xf-teal);"></span>
					<?php esc_html_e( 'Resend Notification', 'xtreme-forms' ); ?>
				</h2>
				<p class="xf-resend-help">
					<?php esc_html_e( 'Use this if the original email failed to deliver. Leave the recipient blank to use the form\'s configured recipients.', 'xtreme-forms' ); ?>
				</p>
				<div class="xf-field-group">
					<label class="xf-label" for="xf-resend-recipient">
						<?php esc_html_e( 'Recipient (optional)', 'xtreme-forms' ); ?>
					</label>
					<input
						type="email"
						id="xf-resend-recipient"
						class="xf-input xf-input-full"
						placeholder="<?php esc_attr_e( 'leave empty to use form recipients', 'xtreme-forms' ); ?>"
						autocomplete="off"
					>
					<div class="xf-resend-feedback xf-feedback-msg" style="display:none;"></div>
					<button type="button" id="xf-resend-lead" class="button button-primary xf-btn-primary xf-mt-8 xf-btn-full">
						<span class="dashicons dashicons-email" style="vertical-align:middle;font-size:16px;line-height:1.4;"></span>
						<?php esc_html_e( 'Resend Lead Email', 'xtreme-forms' ); ?>
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

<script>
(function () {
	'use strict';

	var leadId = <?php echo absint( $lead_id ); ?>;
	var nonce = '<?php echo esc_js( wp_create_nonce( 'xf_admin_nonce' ) ); ?>';
	var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

	function post( data, callback ) {
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		xhr.onload = function () {
			try {
				var json = JSON.parse( xhr.responseText );
				callback( null, json );
			} catch ( e ) {
				callback( new Error( 'Invalid JSON' ), null );
			}
		};
		xhr.onerror = function () { callback( new Error( 'Network error' ), null ); };
		var params = Object.keys( data ).map( function ( k ) {
			return encodeURIComponent( k ) + '=' + encodeURIComponent( data[ k ] );
		} ).join( '&' );
		xhr.send( params );
	}

	function esc( str ) {
		var d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( String( str ) ) );
		return d.innerHTML;
	}

	function formatDate( utcStr ) {
		if ( ! utcStr ) return '';
		// WordPress stores UTC datetimes; display as-is.
		return utcStr.replace( 'T', ' ' );
	}

	function showFeedback( el, msg, isError ) {
		el.textContent = msg;
		el.style.display = 'block';
		el.className = 'xf-feedback-msg ' + ( isError ? 'xf-feedback-error' : 'xf-feedback-success' );
		setTimeout( function () { el.style.display = 'none'; }, 4000 );
	}

	// ── Status ──────────────────────────────────────────────────────────────

	var statusSelect = document.getElementById( 'xf-status-select' );
	var saveStatusBtn = document.getElementById( 'xf-save-status' );
	var statusFeedback = document.querySelector( '.xf-status-feedback' );
	var statusBadge = document.getElementById( 'xf-detail-status-badge' );

	if ( saveStatusBtn ) {
		saveStatusBtn.addEventListener( 'click', function () {
			var newStatus = statusSelect.value;
			saveStatusBtn.disabled = true;
			saveStatusBtn.textContent = '<?php echo esc_js( __( 'Saving…', 'xtreme-forms' ) ); ?>';

			post( {
				action: 'xf_update_status',
				nonce: nonce,
				lead_id: leadId,
				status: newStatus
			}, function ( err, res ) {
				saveStatusBtn.disabled = false;
				saveStatusBtn.textContent = '<?php echo esc_js( __( 'Save Status', 'xtreme-forms' ) ); ?>';
				if ( err || ! res || ! res.success ) {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : '<?php echo esc_js( __( 'Error updating status.', 'xtreme-forms' ) ); ?>';
					showFeedback( statusFeedback, msg, true );
					return;
				}
				var d = res.data;
				showFeedback( statusFeedback, '<?php echo esc_js( __( 'Status updated.', 'xtreme-forms' ) ); ?>', false );

				// Update badge without page reload.
				if ( statusBadge ) {
					statusBadge.textContent = d.status_label;
					statusBadge.className = 'xf-status-badge xf-status-' + esc( d.status );
				}

				// Append to activity timeline (no page reload needed).
				appendActivity( '<?php echo esc_js( __( 'Status changed', 'xtreme-forms' ) ); ?>: ' + esc( d.status_label ), 'status_change' );
			} );
		} );
	}

	// ── Assignment ──────────────────────────────────────────────────────────

	var assignSelect = document.getElementById( 'xf-assignee-select' );
	var saveAssignBtn = document.getElementById( 'xf-save-assignee' );
	var assignFeedback = document.querySelector( '.xf-assign-feedback' );

	if ( saveAssignBtn ) {
		saveAssignBtn.addEventListener( 'click', function () {
			saveAssignBtn.disabled = true;
			saveAssignBtn.textContent = '<?php echo esc_js( __( 'Saving…', 'xtreme-forms' ) ); ?>';

			post( {
				action: 'xf_assign_lead',
				nonce: nonce,
				lead_id: leadId,
				assigned_to: assignSelect.value
			}, function ( err, res ) {
				saveAssignBtn.disabled = false;
				saveAssignBtn.textContent = '<?php echo esc_js( __( 'Save Assignment', 'xtreme-forms' ) ); ?>';
				if ( err || ! res || ! res.success ) {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : '<?php echo esc_js( __( 'Error saving assignment.', 'xtreme-forms' ) ); ?>';
					showFeedback( assignFeedback, msg, true );
					return;
				}
				var d = res.data;
				var feedbackMsg = '<?php echo esc_js( __( 'Assignment saved.', 'xtreme-forms' ) ); ?>';
				if ( d.email_warning ) {
					feedbackMsg += ' ' + d.email_warning;
				}
				showFeedback( assignFeedback, feedbackMsg, !! d.email_warning );
				// Update activity without page reload.
				var assignLabel = '<?php echo esc_js( __( 'Assigned to', 'xtreme-forms' ) ); ?>: ' + esc( d.assignee_name );
				appendActivity( assignLabel, 'assignment' );
			} );
		} );
	}

	// ── Notes ────────────────────────────────────────────────────────────────

	var noteTextarea = document.getElementById( 'xf-note-textarea' );
	var submitNoteBtn = document.getElementById( 'xf-submit-note' );
	var noteError = document.querySelector( '.xf-note-error' );
	var notesList = document.getElementById( 'xf-notes-list' );
	var notesEmpty = document.getElementById( 'xf-notes-empty' );

	if ( submitNoteBtn ) {
		submitNoteBtn.addEventListener( 'click', function () {
			var content = noteTextarea.value;

			if ( ! content.trim() ) {
				noteError.textContent = '<?php echo esc_js( __( 'Note content cannot be empty.', 'xtreme-forms' ) ); ?>';
				noteError.style.display = 'block';
				return;
			}
			noteError.style.display = 'none';

			submitNoteBtn.disabled = true;
			submitNoteBtn.textContent = '<?php echo esc_js( __( 'Saving…', 'xtreme-forms' ) ); ?>';

			post( {
				action: 'xf_add_note',
				nonce: nonce,
				lead_id: leadId,
				note_content: content
			}, function ( err, res ) {
				submitNoteBtn.disabled = false;
				submitNoteBtn.textContent = '<?php echo esc_js( __( 'Add Note', 'xtreme-forms' ) ); ?>';

				if ( err || ! res || ! res.success ) {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : '<?php echo esc_js( __( 'Error adding note.', 'xtreme-forms' ) ); ?>';
					noteError.textContent = msg;
					noteError.style.display = 'block';
					return;
				}

				var note = res.data.note;
				if ( notesEmpty ) {
					notesEmpty.style.display = 'none';
				}

				var dateStr = note.created_at ? note.created_at : '';
				var noteEl = document.createElement( 'div' );
				noteEl.className = 'xf-note-item';
				noteEl.innerHTML =
					'<div class="xf-note-meta">' +
					'<strong>' + esc( note.author_name ) + '</strong>' +
					'<time class="xf-note-time">' + esc( dateStr ) + '</time>' +
					'</div>' +
					'<div class="xf-note-content">' + esc( note.content ) + '</div>';
				notesList.appendChild( noteEl );

				noteTextarea.value = '';
				appendActivity( '<?php echo esc_js( __( 'Note added.', 'xtreme-forms' ) ); ?>', 'note_added' );
			} );
		} );
	}

	// ── Tags ─────────────────────────────────────────────────────────────────

	var tagInput = document.getElementById( 'xf-tag-input' );
	var tagSuggestions = document.getElementById( 'xf-tag-suggestions' );
	var tagsList = document.getElementById( 'xf-tags-list' );
	var tagFeedback = document.querySelector( '.xf-tag-feedback' );
	var _suggestTimer = null;

	function addTagPill( tag ) {
		// Prevent duplicate pills.
		var existing = tagsList.querySelector( '[data-tag-id="' + tag.id + '"]' );
		if ( existing ) return;

		var pill = document.createElement( 'span' );
		pill.className = 'xf-tag-pill';
		pill.setAttribute( 'data-tag-id', tag.id );
		pill.innerHTML = esc( tag.name ) +
			'<button type="button" class="xf-tag-remove" data-tag-id="' + esc( tag.id ) + '" aria-label="<?php echo esc_js( __( 'Remove tag', 'xtreme-forms' ) ); ?>: ' + esc( tag.name ) + '">&times;</button>';
		tagsList.appendChild( pill );
		bindRemoveTag( pill.querySelector( '.xf-tag-remove' ) );
	}

	function bindRemoveTag( btn ) {
		btn.addEventListener( 'click', function () {
			var tagId = parseInt( btn.getAttribute( 'data-tag-id' ), 10 );
			post( {
				action: 'xf_remove_tag',
				nonce: nonce,
				lead_id: leadId,
				tag_id: tagId
			}, function ( err, res ) {
				if ( err || ! res || ! res.success ) {
					showFeedback( tagFeedback, '<?php echo esc_js( __( 'Error removing tag.', 'xtreme-forms' ) ); ?>', true );
					return;
				}
				var pill = tagsList.querySelector( '[data-tag-id="' + tagId + '"]' );
				if ( pill ) pill.remove();
				showFeedback( tagFeedback, '<?php echo esc_js( __( 'Tag removed.', 'xtreme-forms' ) ); ?>', false );
				appendActivity( '<?php echo esc_js( __( 'Tag removed.', 'xtreme-forms' ) ); ?>', 'tag_removed' );
			} );
		} );
	}

	// Bind existing remove buttons.
	if ( tagsList ) {
		tagsList.querySelectorAll( '.xf-tag-remove' ).forEach( bindRemoveTag );
	}

	if ( tagInput ) {
		tagInput.addEventListener( 'input', function () {
			clearTimeout( _suggestTimer );
			var query = tagInput.value.trim();
			if ( query.length < 1 ) {
				tagSuggestions.hidden = true;
				return;
			}
			_suggestTimer = setTimeout( function () {
				post( {
					action: 'xf_search_tags',
					nonce: nonce,
					query: query
				}, function ( err, res ) {
					if ( err || ! res || ! res.success ) {
						tagSuggestions.hidden = true;
						return;
					}
					var tags = res.data.tags || [];
					tagSuggestions.innerHTML = '';
					if ( ! tags.length ) {
						var li = document.createElement( 'li' );
						li.className = 'xf-suggestion-empty';
						li.textContent = '<?php echo esc_js( __( 'No tags found.', 'xtreme-forms' ) ); ?>';
						tagSuggestions.appendChild( li );
					} else {
						tags.forEach( function ( tag ) {
							var li = document.createElement( 'li' );
							li.className = 'xf-suggestion-item';
							li.setAttribute( 'role', 'option' );
							li.setAttribute( 'data-tag-id', tag.id );
							li.textContent = tag.name;
							li.addEventListener( 'click', function () {
								applyTag( tag );
								tagSuggestions.hidden = true;
								tagInput.value = '';
							} );
							tagSuggestions.appendChild( li );
						} );
					}
					tagSuggestions.hidden = false;
				} );
			}, 200 );
		} );

		tagInput.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				tagSuggestions.hidden = true;
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! tagInput.contains( e.target ) && ! tagSuggestions.contains( e.target ) ) {
				tagSuggestions.hidden = true;
			}
		} );
	}

	function applyTag( tag ) {
		post( {
			action: 'xf_apply_tag',
			nonce: nonce,
			lead_id: leadId,
			tag_id: tag.id
		}, function ( err, res ) {
			if ( err || ! res || ! res.success ) {
				showFeedback( tagFeedback, '<?php echo esc_js( __( 'Error applying tag.', 'xtreme-forms' ) ); ?>', true );
				return;
			}
			addTagPill( res.data.tag );
			showFeedback( tagFeedback, '<?php echo esc_js( __( 'Tag added.', 'xtreme-forms' ) ); ?>', false );
			appendActivity( '<?php echo esc_js( __( 'Tag added.', 'xtreme-forms' ) ); ?>', 'tag_added' );
		} );
	}

	// ── Activity append helper ────────────────────────────────────────────────

	function appendActivity( label, type ) {
		var list = document.getElementById( 'xf-activity-list' );
		if ( ! list ) return;

		var emptyMsg = list.querySelector( '.xf-na' );
		if ( emptyMsg ) emptyMsg.remove();

		var now = new Date();
		var dateStr = now.getFullYear() + '-' + pad( now.getMonth() + 1 ) + '-' + pad( now.getDate() ) +
			' ' + pad( now.getHours() ) + ':' + pad( now.getMinutes() ) + ':' + pad( now.getSeconds() );

		var item = document.createElement( 'div' );
		item.className = 'xf-activity-item xf-activity-' + esc( type );
		item.innerHTML =
			'<div class="xf-activity-dot"></div>' +
			'<div class="xf-activity-body">' +
			'<div class="xf-activity-label">' + esc( label ) + '</div>' +
			'<time class="xf-activity-time">' + esc( dateStr ) + '</time>' +
			'</div>';
		list.appendChild( item );
	}

	function pad( n ) { return n < 10 ? '0' + n : String( n ); }

	// ── Resend Notification ─────────────────────────────────────────────────

	var resendBtn       = document.getElementById( 'xf-resend-lead' );
	var resendRecipient = document.getElementById( 'xf-resend-recipient' );
	var resendFeedback  = document.querySelector( '.xf-resend-feedback' );

	if ( resendBtn ) {
		var resendOriginalLabel = resendBtn.innerHTML;
		resendBtn.addEventListener( 'click', function () {
			var recipient = resendRecipient ? resendRecipient.value.trim() : '';

			// Soft-confirm if no recipient typed (the user is about to send to all configured recipients).
			if ( ! recipient ) {
				if ( ! window.confirm( '<?php echo esc_js( __( 'Resend the notification to the form\'s configured recipients?', 'xtreme-forms' ) ); ?>' ) ) {
					return;
				}
			}

			resendBtn.disabled = true;
			resendBtn.textContent = '<?php echo esc_js( __( 'Sending…', 'xtreme-forms' ) ); ?>';

			post( {
				action: 'xf_resend_lead_notification',
				nonce: nonce,
				lead_id: leadId,
				recipient: recipient
			}, function ( err, res ) {
				resendBtn.disabled = false;
				resendBtn.innerHTML = resendOriginalLabel;

				if ( err || ! res || ! res.success ) {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : '<?php echo esc_js( __( 'Error resending email.', 'xtreme-forms' ) ); ?>';
					showFeedback( resendFeedback, msg, true );
					return;
				}

				showFeedback( resendFeedback, res.data.message, false );
				if ( resendRecipient ) {
					resendRecipient.value = '';
				}
				appendActivity( res.data.message, 'email_resent' );
			} );
		} );
	}

})();
</script>
