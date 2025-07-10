<?php
/**
 * Lead Detail View – dedicated admin page.
 *
 * Accessible via: ?page=xtremeleads&xl_action=view&lead_id=X
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

$lead_id = isset( $_GET['lead_id'] ) ? absint( $_GET['lead_id'] ) : 0;

if ( ! $lead_id ) {
	wp_die( esc_html__( 'Invalid lead ID.', 'xtremeleads' ) );
}

$lead = XL_Leads::get_lead( $lead_id );
if ( ! $lead ) {
	wp_die( esc_html__( 'Lead not found.', 'xtremeleads' ) );
}

$form = XL_Forms::get_form( (int) $lead->form_id );
$form_name = $form ? esc_html( $form->name ) : esc_html__( '(deleted form)', 'xtremeleads' );
$statuses = XL_Leads::get_statuses();
$status_key = $lead->status ?? 'new';
$status_label = $statuses[ $status_key ] ?? ucfirst( $status_key );

// Field values with labels.
$field_values = XL_Leads::decode_field_values( $lead );
$fields_with_labels = array();

if ( $form ) {
	$field_defs = XL_Forms::decode_fields( $form );
	foreach ( $field_defs as $fd ) {
		$fid = $fd['id'] ?? '';
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
			'label' => (string) $label,
			'value' => null !== $val ? (string) $val : null,
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
			'label' => $key,
			'value' => (string) $val,
			'is_empty' => '' === (string) $val,
		);
	}
}

// Also append any field_values keys not already in form definition (e.g. from removed fields).
if ( $form ) {
	$covered_fids = array();
	foreach ( XL_Forms::decode_fields( $form ) as $fd ) {
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
			'label' => $key,
			'value' => (string) $val,
			'is_empty' => '' === (string) $val,
		);
	}
}

// Assigned user.
$assigned_to = (int) ( $lead->assigned_to ?? 0 );
$assignee_name = $assigned_to ? '' : esc_html__( 'Unassigned', 'xtremeleads' );
if ( $assigned_to ) {
	$assignee_user = get_userdata( $assigned_to );
	$assignee_name = $assignee_user ? esc_html( $assignee_user->display_name ) : esc_html__( '(unknown user)', 'xtremeleads' );
}

// Tags.
$tags = XL_Tags::get_tags_for_lead( $lead_id );

// Notes (oldest first).
$notes = XL_Notes::get_notes_for_lead( $lead_id );

// Activity (oldest first).
$activity = XL_Activity::get_activity_for_lead( $lead_id );

// Eligible users for assignment dropdown.
$eligible_users = XL_Leads::get_eligible_assignees();
$all_tags = XL_Tags::get_all_tags();

// Back URL.
$back_url = add_query_arg( array( 'page' => 'xtremeleads' ), admin_url( 'admin.php' ) );

// Admin notice for assignment email warning (from session / transient).
$notice_html = '';
$email_warn_key = 'xl_assign_email_warn_' . $lead_id . '_' . get_current_user_id();
$email_warn = get_transient( $email_warn_key );
if ( $email_warn ) {
	delete_transient( $email_warn_key );
	$notice_html = '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $email_warn ) . '</p></div>';
}
?>
<div class="wrap xl-wrap xl-lead-detail-wrap">

	<div class="xl-detail-nav">
		<a href="<?php echo esc_url( $back_url ); ?>" class="xl-back-link">
			<span class="dashicons dashicons-arrow-left-alt2"></span>
			<?php esc_html_e( 'Back to Leads', 'xtremeleads' ); ?>
		</a>
	</div>

	<h1 class="xl-page-title">
		<?php
		printf(
			/* translators: %d: lead ID */
			esc_html__( 'Lead #%d', 'xtremeleads' ),
			$lead_id
		);
		?>
		<span class="xl-status-badge xl-status-<?php echo esc_attr( $status_key ); ?>" id="xl-detail-status-badge">
			<?php echo esc_html( $status_label ); ?>
		</span>
	</h1>

	<?php echo wp_kses_post( $notice_html ); ?>

	<div class="xl-detail-layout">

		<!-- Left Column: Submitted Data + Meta -->
		<div class="xl-detail-main">

			<!-- Submitted Form Fields -->
			<div class="xl-detail-card">
				<h2><?php esc_html_e( 'Submitted Data', 'xtremeleads' ); ?></h2>
				<?php if ( empty( $fields_with_labels ) ) : ?>
					<p class="xl-na"><?php esc_html_e( 'No field data available.', 'xtremeleads' ); ?></p>
				<?php else : ?>
					<table class="xl-detail-fields-table">
						<tbody>
							<?php foreach ( $fields_with_labels as $f ) : ?>
								<tr>
									<th><?php echo esc_html( $f['label'] ); ?></th>
									<td>
										<?php if ( $f['is_empty'] ) : ?>
											<em class="xl-na"><?php esc_html_e( 'N/A', 'xtremeleads' ); ?></em>
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
			<div class="xl-detail-card">
				<h2><?php esc_html_e( 'Lead Metadata', 'xtremeleads' ); ?></h2>
				<table class="xl-detail-fields-table">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Lead ID', 'xtremeleads' ); ?></th>
							<td><code>#<?php echo esc_html( $lead_id ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Form', 'xtremeleads' ); ?></th>
							<td><?php echo $form_name; ?></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Submission Date', 'xtremeleads' ); ?></th>
							<td>
								<time datetime="<?php echo esc_attr( $lead->created_at ); ?>">
									<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $lead->created_at . ' UTC' ) ) ); ?>
								</time>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Source URL', 'xtremeleads' ); ?></th>
							<td>
								<?php if ( $lead->source_url ) : ?>
									<a href="<?php echo esc_url( $lead->source_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $lead->source_url ); ?></a>
								<?php else : ?>
									<em class="xl-na"><?php esc_html_e( 'N/A', 'xtremeleads' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'IP Address', 'xtremeleads' ); ?></th>
							<td>
								<?php if ( $lead->ip_address ) : ?>
									<code><?php echo esc_html( $lead->ip_address ); ?></code>
								<?php else : ?>
									<em class="xl-na"><?php esc_html_e( 'N/A', 'xtremeleads' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'User Agent', 'xtremeleads' ); ?></th>
							<td>
								<?php if ( $lead->user_agent ) : ?>
									<small><?php echo esc_html( $lead->user_agent ); ?></small>
								<?php else : ?>
									<em class="xl-na"><?php esc_html_e( 'N/A', 'xtremeleads' ); ?></em>
								<?php endif; ?>
							</td>
						</tr>
						<?php
						// Display GDPR consent status if the column exists on this lead.
						if ( property_exists( $lead, 'consent_given' ) ) :
							$consent_value = $lead->consent_given;
						?>
						<tr>
							<th><?php esc_html_e( 'GDPR Consent', 'xtremeleads' ); ?></th>
							<td>
								<?php if ( null === $consent_value ) : ?>
									<em class="xl-na"><?php esc_html_e( 'N/A (no consent field on form)', 'xtremeleads' ); ?></em>
								<?php elseif ( $consent_value ) : ?>
									<span style="display:inline-block;background:#28A745;color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">
										<?php esc_html_e( 'Consent Given', 'xtremeleads' ); ?>
									</span>
								<?php else : ?>
									<span style="display:inline-block;background:#DC3545;color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;">
										<?php esc_html_e( 'Consent Not Given', 'xtremeleads' ); ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Notes Section -->
			<div class="xl-detail-card" id="xl-notes-section">
				<h2><?php esc_html_e( 'Notes', 'xtremeleads' ); ?></h2>

				<div class="xl-notes-list" id="xl-notes-list">
					<?php if ( empty( $notes ) ) : ?>
						<p class="xl-notes-empty xl-na" id="xl-notes-empty"><?php esc_html_e( 'No notes yet. Add the first note below.', 'xtremeleads' ); ?></p>
					<?php else : ?>
						<?php foreach ( $notes as $note ) : ?>
							<div class="xl-note-item" data-note-id="<?php echo esc_attr( $note->id ); ?>">
								<div class="xl-note-meta">
									<strong><?php echo esc_html( $note->author_name ); ?></strong>
									<time class="xl-note-time" datetime="<?php echo esc_attr( $note->created_at ); ?>">
										<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $note->created_at . ' UTC' ) ) ); ?>
									</time>
								</div>
								<div class="xl-note-content"><?php echo esc_html( $note->note_content ); ?></div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<!-- Add Note Form -->
				<div class="xl-add-note-form">
					<label class="xl-label" for="xl-note-textarea"><?php esc_html_e( 'Add Note', 'xtremeleads' ); ?></label>
					<textarea
						id="xl-note-textarea"
						class="xl-textarea xl-input-full"
						rows="4"
						placeholder="<?php esc_attr_e( 'Type your note here…', 'xtremeleads' ); ?>"
					></textarea>
					<div class="xl-note-error xl-error-msg" style="display:none;"></div>
					<button type="button" id="xl-submit-note" class="button button-primary xl-btn-primary xl-mt-8">
						<?php esc_html_e( 'Add Note', 'xtremeleads' ); ?>
					</button>
				</div>
			</div>

			<!-- Activity Timeline -->
			<div class="xl-detail-card" id="xl-activity-section">
				<h2><?php esc_html_e( 'Activity Timeline', 'xtremeleads' ); ?></h2>
				<div class="xl-activity-list" id="xl-activity-list">
					<?php if ( empty( $activity ) ) : ?>
						<p class="xl-na"><?php esc_html_e( 'No activity recorded yet.', 'xtremeleads' ); ?></p>
					<?php else : ?>
						<?php foreach ( $activity as $entry ) : ?>
							<div class="xl-activity-item xl-activity-<?php echo esc_attr( $entry->action_type ); ?>">
								<div class="xl-activity-dot"></div>
								<div class="xl-activity-body">
									<div class="xl-activity-label"><?php echo wp_kses_post( $entry->label ); ?></div>
									<time class="xl-activity-time" datetime="<?php echo esc_attr( $entry->created_at ); ?>">
										<?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created_at . ' UTC' ) ) ); ?>
									</time>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
			</div>

		</div><!-- .xl-detail-main -->

		<!-- Right Column: Actions -->
		<div class="xl-detail-sidebar">

			<!-- Status -->
			<div class="xl-detail-card">
				<h2><?php esc_html_e( 'Status', 'xtremeleads' ); ?></h2>
				<div class="xl-field-group">
					<select id="xl-status-select" class="xl-select-full">
						<?php foreach ( $statuses as $slug => $label ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $status_key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<div class="xl-status-feedback xl-feedback-msg" style="display:none;"></div>
					<button type="button" id="xl-save-status" class="button button-primary xl-btn-primary xl-mt-8 xl-btn-full">
						<?php esc_html_e( 'Save Status', 'xtremeleads' ); ?>
					</button>
				</div>
			</div>

			<!-- Assignment -->
			<div class="xl-detail-card">
				<h2><?php esc_html_e( 'Assigned To', 'xtremeleads' ); ?></h2>
				<div class="xl-field-group">
					<select id="xl-assignee-select" class="xl-select-full">
						<option value="0"><?php esc_html_e( 'Unassigned', 'xtremeleads' ); ?></option>
						<?php foreach ( $eligible_users as $user ) : ?>
							<option value="<?php echo esc_attr( $user['id'] ); ?>" <?php selected( $user['id'], $assigned_to ); ?>>
								<?php echo esc_html( $user['display_name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<div class="xl-assign-feedback xl-feedback-msg" style="display:none;"></div>
					<button type="button" id="xl-save-assignee" class="button button-primary xl-btn-primary xl-mt-8 xl-btn-full">
						<?php esc_html_e( 'Save Assignment', 'xtremeleads' ); ?>
					</button>
				</div>
			</div>

			<!-- Tags -->
			<div class="xl-detail-card">
				<h2><?php esc_html_e( 'Tags', 'xtremeleads' ); ?></h2>

				<div class="xl-tags-list" id="xl-tags-list">
					<?php foreach ( $tags as $tag ) : ?>
						<span class="xl-tag-pill" data-tag-id="<?php echo esc_attr( $tag->id ); ?>">
							<?php echo esc_html( $tag->name ); ?>
							<button type="button" class="xl-tag-remove" data-tag-id="<?php echo esc_attr( $tag->id ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Remove tag %s', 'xtremeleads' ), $tag->name ) ); ?>">&times;</button>
						</span>
					<?php endforeach; ?>
				</div>

				<div class="xl-tag-add-wrap">
					<div class="xl-tag-autocomplete-wrap">
						<input
							type="text"
							id="xl-tag-input"
							class="xl-input xl-input-full"
							placeholder="<?php esc_attr_e( 'Type to search tags…', 'xtremeleads' ); ?>"
							autocomplete="off"
						>
						<ul class="xl-tag-suggestions" id="xl-tag-suggestions" role="listbox" hidden></ul>
					</div>
					<div class="xl-tag-feedback xl-feedback-msg" style="display:none;"></div>
				</div>
			</div>

		</div><!-- .xl-detail-sidebar -->

	</div><!-- .xl-detail-layout -->

</div><!-- .xl-wrap -->

<script>
(function () {
	'use strict';

	var leadId = <?php echo absint( $lead_id ); ?>;
	var nonce = '<?php echo esc_js( wp_create_nonce( 'xl_admin_nonce' ) ); ?>';
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
		el.className = 'xl-feedback-msg ' + ( isError ? 'xl-feedback-error' : 'xl-feedback-success' );
		setTimeout( function () { el.style.display = 'none'; }, 4000 );
	}

	// ── Status ──────────────────────────────────────────────────────────────

	var statusSelect = document.getElementById( 'xl-status-select' );
	var saveStatusBtn = document.getElementById( 'xl-save-status' );
	var statusFeedback = document.querySelector( '.xl-status-feedback' );
	var statusBadge = document.getElementById( 'xl-detail-status-badge' );

	if ( saveStatusBtn ) {
		saveStatusBtn.addEventListener( 'click', function () {
			var newStatus = statusSelect.value;
			saveStatusBtn.disabled = true;
			saveStatusBtn.textContent = '<?php echo esc_js( __( 'Saving…', 'xtremeleads' ) ); ?>';

			post( {
				action: 'xl_update_status',
				nonce: nonce,
				lead_id: leadId,
				status: newStatus
			}, function ( err, res ) {
				saveStatusBtn.disabled = false;
				saveStatusBtn.textContent = '<?php echo esc_js( __( 'Save Status', 'xtremeleads' ) ); ?>';
				if ( err || ! res || ! res.success ) {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : '<?php echo esc_js( __( 'Error updating status.', 'xtremeleads' ) ); ?>';
					showFeedback( statusFeedback, msg, true );
					return;
				}
				var d = res.data;
				showFeedback( statusFeedback, '<?php echo esc_js( __( 'Status updated.', 'xtremeleads' ) ); ?>', false );

				// Update badge without page reload.
				if ( statusBadge ) {
					statusBadge.textContent = d.status_label;
					statusBadge.className = 'xl-status-badge xl-status-' + esc( d.status );
				}

				// Append to activity timeline (no page reload needed).
				appendActivity( '<?php echo esc_js( __( 'Status changed', 'xtremeleads' ) ); ?>: ' + esc( d.status_label ), 'status_change' );
			} );
		} );
	}

	// ── Assignment ──────────────────────────────────────────────────────────

	var assignSelect = document.getElementById( 'xl-assignee-select' );
	var saveAssignBtn = document.getElementById( 'xl-save-assignee' );
	var assignFeedback = document.querySelector( '.xl-assign-feedback' );

	if ( saveAssignBtn ) {
		saveAssignBtn.addEventListener( 'click', function () {
			saveAssignBtn.disabled = true;
			saveAssignBtn.textContent = '<?php echo esc_js( __( 'Saving…', 'xtremeleads' ) ); ?>';

			post( {
				action: 'xl_assign_lead',
				nonce: nonce,
				lead_id: leadId,
				assigned_to: assignSelect.value
			}, function ( err, res ) {
				saveAssignBtn.disabled = false;
				saveAssignBtn.textContent = '<?php echo esc_js( __( 'Save Assignment', 'xtremeleads' ) ); ?>';
				if ( err || ! res || ! res.success ) {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : '<?php echo esc_js( __( 'Error saving assignment.', 'xtremeleads' ) ); ?>';
					showFeedback( assignFeedback, msg, true );
					return;
				}
				var d = res.data;
				var feedbackMsg = '<?php echo esc_js( __( 'Assignment saved.', 'xtremeleads' ) ); ?>';
				if ( d.email_warning ) {
					feedbackMsg += ' ' + d.email_warning;
				}
				showFeedback( assignFeedback, feedbackMsg, !! d.email_warning );
				// Update activity without page reload.
				var assignLabel = '<?php echo esc_js( __( 'Assigned to', 'xtremeleads' ) ); ?>: ' + esc( d.assignee_name );
				appendActivity( assignLabel, 'assignment' );
			} );
		} );
	}

	// ── Notes ────────────────────────────────────────────────────────────────

	var noteTextarea = document.getElementById( 'xl-note-textarea' );
	var submitNoteBtn = document.getElementById( 'xl-submit-note' );
	var noteError = document.querySelector( '.xl-note-error' );
	var notesList = document.getElementById( 'xl-notes-list' );
	var notesEmpty = document.getElementById( 'xl-notes-empty' );

	if ( submitNoteBtn ) {
		submitNoteBtn.addEventListener( 'click', function () {
			var content = noteTextarea.value;

			if ( ! content.trim() ) {
				noteError.textContent = '<?php echo esc_js( __( 'Note content cannot be empty.', 'xtremeleads' ) ); ?>';
				noteError.style.display = 'block';
				return;
			}
			noteError.style.display = 'none';

			submitNoteBtn.disabled = true;
			submitNoteBtn.textContent = '<?php echo esc_js( __( 'Saving…', 'xtremeleads' ) ); ?>';

			post( {
				action: 'xl_add_note',
				nonce: nonce,
				lead_id: leadId,
				note_content: content
			}, function ( err, res ) {
				submitNoteBtn.disabled = false;
				submitNoteBtn.textContent = '<?php echo esc_js( __( 'Add Note', 'xtremeleads' ) ); ?>';

				if ( err || ! res || ! res.success ) {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : '<?php echo esc_js( __( 'Error adding note.', 'xtremeleads' ) ); ?>';
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
				noteEl.className = 'xl-note-item';
				noteEl.innerHTML =
					'<div class="xl-note-meta">' +
					'<strong>' + esc( note.author_name ) + '</strong>' +
					'<time class="xl-note-time">' + esc( dateStr ) + '</time>' +
					'</div>' +
					'<div class="xl-note-content">' + esc( note.content ) + '</div>';
				notesList.appendChild( noteEl );

				noteTextarea.value = '';
				appendActivity( '<?php echo esc_js( __( 'Note added.', 'xtremeleads' ) ); ?>', 'note_added' );
			} );
		} );
	}

	// ── Tags ─────────────────────────────────────────────────────────────────

	var tagInput = document.getElementById( 'xl-tag-input' );
	var tagSuggestions = document.getElementById( 'xl-tag-suggestions' );
	var tagsList = document.getElementById( 'xl-tags-list' );
	var tagFeedback = document.querySelector( '.xl-tag-feedback' );
	var _suggestTimer = null;

	function addTagPill( tag ) {
		// Prevent duplicate pills.
		var existing = tagsList.querySelector( '[data-tag-id="' + tag.id + '"]' );
		if ( existing ) return;

		var pill = document.createElement( 'span' );
		pill.className = 'xl-tag-pill';
		pill.setAttribute( 'data-tag-id', tag.id );
		pill.innerHTML = esc( tag.name ) +
			'<button type="button" class="xl-tag-remove" data-tag-id="' + esc( tag.id ) + '" aria-label="<?php echo esc_js( __( 'Remove tag', 'xtremeleads' ) ); ?>: ' + esc( tag.name ) + '">&times;</button>';
		tagsList.appendChild( pill );
		bindRemoveTag( pill.querySelector( '.xl-tag-remove' ) );
	}

	function bindRemoveTag( btn ) {
		btn.addEventListener( 'click', function () {
			var tagId = parseInt( btn.getAttribute( 'data-tag-id' ), 10 );
			post( {
				action: 'xl_remove_tag',
				nonce: nonce,
				lead_id: leadId,
				tag_id: tagId
			}, function ( err, res ) {
				if ( err || ! res || ! res.success ) {
					showFeedback( tagFeedback, '<?php echo esc_js( __( 'Error removing tag.', 'xtremeleads' ) ); ?>', true );
					return;
				}
				var pill = tagsList.querySelector( '[data-tag-id="' + tagId + '"]' );
				if ( pill ) pill.remove();
				showFeedback( tagFeedback, '<?php echo esc_js( __( 'Tag removed.', 'xtremeleads' ) ); ?>', false );
				appendActivity( '<?php echo esc_js( __( 'Tag removed.', 'xtremeleads' ) ); ?>', 'tag_removed' );
			} );
		} );
	}

	// Bind existing remove buttons.
	if ( tagsList ) {
		tagsList.querySelectorAll( '.xl-tag-remove' ).forEach( bindRemoveTag );
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
					action: 'xl_search_tags',
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
						li.className = 'xl-suggestion-empty';
						li.textContent = '<?php echo esc_js( __( 'No tags found.', 'xtremeleads' ) ); ?>';
						tagSuggestions.appendChild( li );
					} else {
						tags.forEach( function ( tag ) {
							var li = document.createElement( 'li' );
							li.className = 'xl-suggestion-item';
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
			action: 'xl_apply_tag',
			nonce: nonce,
			lead_id: leadId,
			tag_id: tag.id
		}, function ( err, res ) {
			if ( err || ! res || ! res.success ) {
				showFeedback( tagFeedback, '<?php echo esc_js( __( 'Error applying tag.', 'xtremeleads' ) ); ?>', true );
				return;
			}
			addTagPill( res.data.tag );
			showFeedback( tagFeedback, '<?php echo esc_js( __( 'Tag added.', 'xtremeleads' ) ); ?>', false );
			appendActivity( '<?php echo esc_js( __( 'Tag added.', 'xtremeleads' ) ); ?>', 'tag_added' );
		} );
	}

	// ── Activity append helper ────────────────────────────────────────────────

	function appendActivity( label, type ) {
		var list = document.getElementById( 'xl-activity-list' );
		if ( ! list ) return;

		var emptyMsg = list.querySelector( '.xl-na' );
		if ( emptyMsg ) emptyMsg.remove();

		var now = new Date();
		var dateStr = now.getFullYear() + '-' + pad( now.getMonth() + 1 ) + '-' + pad( now.getDate() ) +
			' ' + pad( now.getHours() ) + ':' + pad( now.getMinutes() ) + ':' + pad( now.getSeconds() );

		var item = document.createElement( 'div' );
		item.className = 'xl-activity-item xl-activity-' + esc( type );
		item.innerHTML =
			'<div class="xl-activity-dot"></div>' +
			'<div class="xl-activity-body">' +
			'<div class="xl-activity-label">' + esc( label ) + '</div>' +
			'<time class="xl-activity-time">' + esc( dateStr ) + '</time>' +
			'</div>';
		list.appendChild( item );
	}

	function pad( n ) { return n < 10 ? '0' + n : String( n ); }

})();
</script>
