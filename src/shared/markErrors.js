/**
 * UX-DR20: map API error codes to user-facing copy and who can fix.
 */
export const MARK_ERROR_MESSAGES = {
	rubric_not_confirmed: {
		message:
			'Marking is not open yet because the rubric for this review has not been confirmed.',
		fixBy: 'coordinator',
	},
	marking_inactive: {
		message: 'This review round is not open for marking.',
		fixBy: 'coordinator',
	},
	session_closed: {
		message: 'This project is closed. Marks can no longer be saved.',
		fixBy: 'admin',
	},
	session_not_active: {
		message: 'This project is not active yet. Marking is not open.',
		fixBy: 'coordinator',
	},
	not_assigned: {
		message: 'You are not assigned to mark this student for this review.',
		fixBy: 'coordinator',
	},
	invalid_score: {
		message: 'One or more scores are outside the allowed range.',
		fixBy: null,
	},
	marks_frozen: {
		message:
			'Scores are frozen for this review. You cannot change marks until a coordinator intervenes.',
		fixBy: 'coordinator',
	},
	coordinator_marks_locked: {
		message:
			'The coordinator locked marking for this review. No further mark changes are allowed.',
		fixBy: 'coordinator',
	},
	panels_not_all_frozen: {
		message:
			'Every participating panel must freeze panel scores before you can freeze this review.',
		fixBy: 'coordinator',
	},
	no_panels_for_review_lock: {
		message:
			'Assign students to panels on this review before freezing.',
		fixBy: 'coordinator',
	},
	not_panel_coordinator: {
		message: 'Only the panel coordinator can access this panel report.',
		fixBy: 'coordinator',
	},
	panel_head_requires_account: {
		message:
			'Provision or link an account before designating a panel coordinator.',
		fixBy: 'coordinator',
	},
	panel_scores_frozen: {
		message:
			'The panel coordinator finalized scores for this panel. Marks cannot be changed.',
		fixBy: 'coordinator',
	},
	panel_freeze_incomplete: {
		message:
			'This panel has no students or reviewers assigned, so it cannot be frozen yet.',
		fixBy: null,
	},
	panel_freeze_incomplete_marks: {
		message:
			'Some reviewers still have students without a score on every criterion.',
		fixBy: null,
	},
	panel_freeze_reviewers_not_frozen: {
		message:
			'Every reviewer must freeze their personal scores for this review before you can freeze the panel.',
		fixBy: null,
	},
	panel_head_already_set: {
		message: 'This panel already has a panel coordinator.',
		fixBy: 'coordinator',
	},
	incomplete_marks: {
		message: 'Some students still need a score on every criterion before you can freeze.',
		fixBy: null,
	},
	not_frozen: {
		message: 'Scores are not frozen, so an unfreeze request is not needed.',
		fixBy: null,
	},
	unfreeze_request_pending: {
		message: 'An unfreeze request is already pending panel coordinator approval.',
		fixBy: 'coordinator',
	},
	panel_not_frozen: {
		message: 'This panel is not frozen, so a panel unfreeze request is not needed.',
		fixBy: null,
	},
	panel_unfreeze_pending: {
		message: 'A panel unfreeze request is already pending project coordinator approval.',
		fixBy: 'coordinator',
	},
	use_panel_head_grant: {
		message:
			'Reviewer score unfreeze must be approved by the panel coordinator in the reviewer app.',
		fixBy: 'coordinator',
	},
	unfreeze_reason_required: {
		message: 'Please explain why you need to edit frozen scores.',
		fixBy: null,
	},
	unfreeze_reason_too_long: {
		message: 'Reason must be 500 characters or fewer.',
		fixBy: null,
	},
	attendance_required: {
		message: 'Select whether the student was present or absent before saving.',
		fixBy: null,
	},
	invalid_attendance: {
		message: 'Attendance must be present or absent.',
		fixBy: null,
	},
	attendance_conflict: {
		message:
			'Attendance must match for all reviewers on this review. Resolve the disagreement before saving.',
		fixBy: null,
	},
};

export function formatAttendanceConflictLabel( attendanceStatus ) {
	return attendanceStatus === 'absent' ? 'Absent' : 'Present';
}

/**
 * When every other panel reviewer shares one status and the current user disagrees.
 */
export function unanimousPeerAttendanceGuidance(
	conflicts,
	attemptedStatus,
	currentUserId
) {
	if ( ! Array.isArray( conflicts ) || conflicts.length === 0 || ! attemptedStatus ) {
		return null;
	}

	const others = conflicts.filter(
		( row ) => Number( row.reviewer_user_id ) !== Number( currentUserId )
	);
	if ( others.length === 0 ) {
		return null;
	}

	const otherStatuses = new Set( others.map( ( row ) => row.attendance_status ) );
	if ( otherStatuses.size !== 1 ) {
		return null;
	}

	const peerStatus = [ ...otherStatuses ][ 0 ];
	if ( peerStatus === attemptedStatus ) {
		return null;
	}

	return `All other reviewers recorded ${ formatAttendanceConflictLabel(
		peerStatus
	) }. Ask the project coordinator to correct attendance if this is wrong.`;
}

export function mapMarkApiError( error ) {
	const code = error?.code || error?.data?.code || '';
	const mapped = MARK_ERROR_MESSAGES[ code ];
	if ( mapped ) {
		const apiMessage =
			typeof error?.message === 'string' ? error.message : '';
		const useApiMessage =
			( code === 'invalid_score' ||
				code === 'incomplete_marks' ||
				code === 'attendance_conflict' ||
				code === 'panel_freeze_incomplete' ||
				code === 'panel_freeze_incomplete_marks' ||
				code === 'panel_freeze_reviewers_not_frozen' ||
				code === 'panels_not_all_frozen' ) &&
			apiMessage !== '';

		const conflicts = Array.isArray( error?.data?.conflicts )
			? error.data.conflicts
			: [];

		return {
			code,
			message: useApiMessage ? apiMessage : mapped.message,
			fixBy: mapped.fixBy,
			...( code === 'attendance_conflict' && conflicts.length > 0
				? { conflicts }
				: {} ),
		};
	}

	return {
		code: code || 'unknown',
		message:
			error?.message ||
			'Something went wrong while saving marks. Please try again.',
		fixBy: null,
	};
}

export function fixByLabel( fixBy ) {
	if ( fixBy === 'coordinator' ) {
		return 'Ask your coordinator to resolve this.';
	}
	if ( fixBy === 'admin' ) {
		return 'Contact a site administrator if you believe this is incorrect.';
	}

	return null;
}
