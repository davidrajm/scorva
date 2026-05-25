/**
 * Client-side rubric score validation (mirrors MarkService rules for UX).
 */

export const MARK_SCORE_STEP = 0.5;

const HALF_STEP_TOLERANCE = 1e-6;

function isHalfPointMultiple( num ) {
	const scaled = num * 2;
	return Math.abs( scaled - Math.round( scaled ) ) < HALF_STEP_TOLERANCE;
}

export function validateCriterionScore( criterion, value ) {
	if ( ! criterion ) {
		return null;
	}

	if ( value === '' || value === null || value === undefined ) {
		return null;
	}

	const num = Number( value );
	if ( Number.isNaN( num ) ) {
		return 'Enter a valid number.';
	}
	if ( num < 0 ) {
		return 'Score must be zero or greater.';
	}
	if ( num > criterion.max_marks ) {
		return `Score cannot exceed ${ criterion.max_marks }.`;
	}
	if ( ! isHalfPointMultiple( num ) ) {
		return 'Enter a score in steps of 0.5 (e.g. 3, 3.5, 4).';
	}

	return null;
}

/**
 * @param {'present'|'absent'|''|null|undefined} attendanceStatus
 */
export function validateAttendanceForSave( attendanceStatus ) {
	if (
		attendanceStatus !== 'present' &&
		attendanceStatus !== 'absent'
	) {
		return {
			formError: {
				message: 'Select whether the student was present or absent.',
				fixBy: null,
			},
		};
	}

	return { formError: null };
}

/**
 * @param {Array<{ id: number, max_marks: number }>} criteria
 * @param {Record<number, string|number>} scores
 * @param {'draft'|'submitted'} status
 */
export function validateMarksForSave( criteria, scores, status ) {
	const fieldErrors = {};
	const isSubmit = status === 'submitted';

	for ( const c of criteria ) {
		const value = scores[ c.id ];

		if (
			isSubmit &&
			( value === '' || value === null || value === undefined )
		) {
			fieldErrors[ c.id ] = 'Enter a score before submitting.';
			continue;
		}

		const fieldError = validateCriterionScore( c, value );
		if ( fieldError ) {
			fieldErrors[ c.id ] = fieldError;
		}
	}

	if ( Object.keys( fieldErrors ).length === 0 ) {
		return { fieldErrors, formError: null };
	}

	const missingOnSubmit = isSubmit &&
		Object.values( fieldErrors ).some( ( msg ) =>
			msg.includes( 'before submitting' )
		);

	return {
		fieldErrors,
		formError: {
			message: missingOnSubmit
				? 'Enter a score for every criterion before submitting.'
				: 'Fix the highlighted scores before saving.',
			fixBy: null,
		},
	};
}
