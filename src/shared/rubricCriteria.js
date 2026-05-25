/**
 * Shared rubric criterion validation and payload helpers (coordinator RubricTable).
 */

export function parseMaxMarks( value ) {
	const parsed = parseFloat( value );
	return Number.isFinite( parsed ) ? parsed : 0;
}

/**
 * @param {Array<{ label?: string, max_marks?: string|number }>} rows
 * @returns {string|null} Error message or null when valid.
 */
export function validateCriteriaRows( rows ) {
	for ( const row of rows ) {
		const label = ( row.label ?? '' ).trim();
		if ( label === '' ) {
			continue;
		}
		if ( parseMaxMarks( row.max_marks ) <= 0 ) {
			return 'Each criterion needs max marks greater than zero.';
		}
	}

	const validCount = rows.filter(
		( row ) =>
			( row.label ?? '' ).trim() !== '' && parseMaxMarks( row.max_marks ) > 0
	).length;

	if ( validCount === 0 ) {
		return 'Add at least one criterion with a label and max marks greater than zero before saving.';
	}

	return null;
}

/**
 * @param {Array<{ id?: number, label?: string, max_marks?: string|number }>} rows
 * @returns {Array<{ id?: number, label: string, max_marks: number, sort_order: number }>}
 */
export function buildCriteriaPayload( rows ) {
	return rows
		.map( ( row, index ) => {
			const label = ( row.label ?? '' ).trim();
			const max_marks = parseMaxMarks( row.max_marks );
			const payload = {
				label,
				max_marks,
				sort_order: index,
			};
			if ( row.id != null && ! Number.isNaN( row.id ) ) {
				payload.id = row.id;
			}
			return payload;
		} )
		.filter( ( row ) => row.label !== '' && row.max_marks > 0 );
}

/**
 * @param {Array<{ label?: string, max_marks?: string|number }>} rows
 * @returns {number|null} Sum of max_marks for valid rows, or null when none.
 */
export function sumCriteriaMaxMarks( rows ) {
	let sum = 0;
	let hasValid = false;

	for ( const row of rows ) {
		const label = ( row.label ?? '' ).trim();
		const max_marks = parseMaxMarks( row.max_marks );
		if ( label !== '' && max_marks > 0 ) {
			sum += max_marks;
			hasValid = true;
		}
	}

	return hasValid ? sum : null;
}

/**
 * @param {number} sum
 * @returns {string}
 */
export function formatMarksSum( sum ) {
	if ( sum % 1 === 0 ) {
		return String( Math.round( sum ) );
	}
	return String( sum );
}
