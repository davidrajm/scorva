/**
 * Mirrors Rest_Reviews::review_is_editable() for UI gating.
 */
export function isCriteriaEditable( review ) {
	if ( ! review ) {
		return false;
	}

	const status = review.status ?? 'draft';
	const hasMarks = Boolean( review.has_marks );

	if ( status === 'confirmed' && hasMarks ) {
		return false;
	}

	if ( typeof review.criteria_editable === 'boolean' ) {
		return review.criteria_editable;
	}

	if ( status === 'draft' || status === 'unlocked' ) {
		return true;
	}

	if ( status === 'confirmed' ) {
		return ! hasMarks;
	}

	return false;
}
