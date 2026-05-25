export function formatScore( value ) {
	if ( value === null || value === undefined || value === '' ) {
		return '—';
	}

	return Number( value ).toLocaleString( undefined, {
		minimumFractionDigits: 0,
		maximumFractionDigits: 2,
	} );
}

export function scoreForCriterion( student, criterionId ) {
	const scores = student?.scores;
	if ( ! scores ) {
		return null;
	}
	if ( Array.isArray( scores ) ) {
		const row = scores.find(
			( entry ) => Number( entry.criterion_id ) === Number( criterionId )
		);
		return row?.score ?? null;
	}

	return (
		scores[ String( criterionId ) ] ??
		scores[ criterionId ] ??
		null
	);
}

export function coordinatorOverriddenForCriterion( student, criterionId ) {
	const raw = student?.coordinator_overridden;
	if ( ! raw ) {
		return false;
	}
	if ( Array.isArray( raw ) ) {
		return raw.some(
			( entry ) =>
				Number( entry.criterion_id ) === Number( criterionId ) &&
				entry.coordinator_overridden
		);
	}

	return Boolean(
		raw[ String( criterionId ) ] ?? raw[ criterionId ]
	);
}

export function overriddenFromScoreForCriterion( student, criterionId ) {
	const raw = student?.overridden_from_score;
	if ( ! raw ) {
		return null;
	}
	if ( Array.isArray( raw ) ) {
		const row = raw.find(
			( entry ) => Number( entry.criterion_id ) === Number( criterionId )
		);
		return row?.overridden_from_score ?? null;
	}

	const value = raw[ String( criterionId ) ] ?? raw[ criterionId ];
	return value != null && value !== '' ? Number( value ) : null;
}

export function flaggedForCriterion( student, criterionId ) {
	if ( coordinatorOverriddenForCriterion( student, criterionId ) ) {
		return false;
	}

	const flagged = student?.flagged;
	if ( ! flagged ) {
		return false;
	}
	if ( Array.isArray( flagged ) ) {
		return flagged.some(
			( entry ) =>
				Number( entry.criterion_id ) === Number( criterionId ) &&
				entry.flagged
		);
	}

	return Boolean(
		flagged[ String( criterionId ) ] ?? flagged[ criterionId ]
	);
}

export function attendanceStatusChip( attendanceStatus ) {
	if ( attendanceStatus === 'absent' ) {
		return { label: 'Absent', variant: 'draft' };
	}

	return { label: 'Present', variant: 'confirmed' };
}

export function studentStatusChip( status ) {
	if ( status === 'frozen' ) {
		return { label: 'Frozen', variant: 'confirmed', icon: 'lock' };
	}
	if ( status === 'in_progress' ) {
		return { label: 'In progress', variant: 'unlocked' };
	}
	if ( status === 'draft' ) {
		return { label: 'Draft', variant: 'draft' };
	}

	return { label: 'Not started', variant: 'draft' };
}

export function isStudentRowFrozen( reviewFrozen, student ) {
	return reviewFrozen || student.mark_status === 'frozen';
}
