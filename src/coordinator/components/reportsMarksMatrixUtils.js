const LABEL_TRUNCATE_LEN = 32;

export function truncateLabel( label, maxLen = LABEL_TRUNCATE_LEN ) {
	const text = String( label ?? '' );
	if ( text.length <= maxLen ) {
		return text;
	}

	return `${ text.slice( 0, maxLen - 1 ) }…`;
}

/** @deprecated Use slotLeafKey for rubric-first marks matrix columns. */
export function leafKey( criterionId, reviewerUserId ) {
	return `c${ criterionId }_r${ reviewerUserId }`;
}

export function slotLeafKey( criterionId, slotIndex ) {
	return `c${ criterionId }_s${ slotIndex }`;
}

/**
 * @param {string} layout
 * @param {object[]} criteria
 * @param {number} maxPanelReviewerSlots
 */
export function buildColumns( layout, criteria, maxPanelReviewerSlots = 0 ) {
	const fixed = [
		{
			key: 'reg_no',
			label: 'Reg no',
			sortKey: 'reg_no',
			kind: 'string',
		},
		{
			key: 'name',
			label: 'Student',
			sortKey: 'name',
			kind: 'string',
		},
		{
			key: 'panel',
			label: 'Panel',
			sortKey: 'panel',
			kind: 'string',
		},
		{
			key: 'panel_coordinator',
			label: 'Panel coordinator',
			sortKey: 'panel_coordinator',
			kind: 'string',
		},
		{
			key: 'reviewers',
			label: 'Reviewers',
			sortKey: 'reviewers',
			kind: 'string',
		},
		{
			key: 'attendance',
			label: 'Attendance',
			sortKey: 'attendance',
			kind: 'string',
		},
		{
			key: 'mark_status',
			label: 'Status',
			sortKey: 'mark_status',
			kind: 'string',
		},
	];

	const groups = [];
	const critList = criteria ?? [];
	const slotCount = Math.max( 0, Number( maxPanelReviewerSlots ) || 0 );

	if ( layout === 'reviewer' ) {
		for ( let slot = 0; slot < slotCount; slot += 1 ) {
			const leaves = [];
			for ( const criterion of critList ) {
				leaves.push( {
					key: slotLeafKey( criterion.id, slot ),
					label: criterion.label,
					fullLabel: criterion.label,
					sortKey: slotLeafKey( criterion.id, slot ),
					kind: 'score',
					criterionId: criterion.id,
					slotIndex: slot,
					maxMarks: criterion.max_marks,
				} );
			}
			groups.push( {
				id: `reviewer-slot-${ slot }`,
				label: `Reviewer ${ slot + 1 }`,
				fullLabel: `Reviewer ${ slot + 1 }`,
				leaves,
			} );
		}
	} else {
		for ( const criterion of critList ) {
			const leaves = [];
			for ( let slot = 0; slot < slotCount; slot += 1 ) {
				leaves.push( {
					key: slotLeafKey( criterion.id, slot ),
					label: `Reviewer ${ slot + 1 }`,
					fullLabel: `Reviewer ${ slot + 1 }`,
					sortKey: slotLeafKey( criterion.id, slot ),
					kind: 'score',
					criterionId: criterion.id,
					slotIndex: slot,
					maxMarks: criterion.max_marks,
				} );
			}
			groups.push( {
				id: `criterion-${ criterion.id }`,
				label: criterion.label,
				fullLabel: criterion.label,
				leaves,
			} );
		}
	}

	const trailing = [
		{
			key: 'review_score',
			label: 'Weighted review score',
			sortKey: 'review_score',
			kind: 'number',
		},
	];

	return { fixed, groups, trailing };
}

export function getAllLeafColumns( columns ) {
	return columns.groups.flatMap( ( group ) => group.leaves );
}

export function getScore( student, criterionId, reviewerUserId ) {
	if ( student.attendance_status === 'absent' ) {
		return null;
	}

	const entries = student.marks?.[ String( criterionId ) ] ?? [];
	const hit = entries.find(
		( entry ) => entry.reviewer_user_id === reviewerUserId
	);

	if ( ! hit || hit.score == null ) {
		return null;
	}

	const isDraft = hit.status !== 'submitted';

	return {
		markId: hit.id ?? null,
		score: Number( hit.score ),
		draft: isDraft,
		flagged: Boolean( hit.flagged ),
		coordinator_overridden: Boolean( hit.coordinator_overridden ),
		overridden_from_score:
			hit.overridden_from_score != null ? Number( hit.overridden_from_score ) : null,
		reviewer_name: hit.reviewer_name ?? '',
		criterion_id: criterionId,
		entry: hit,
	};
}

/**
 * @param {object[]} markStudents
 * @param {object[]} scoreStudents
 * @param {number} maxPanelReviewerSlots
 */
export function buildRows( markStudents, scoreStudents, maxPanelReviewerSlots = 0 ) {
	const reviewScoreByStudent = {};
	for ( const student of scoreStudents ?? [] ) {
		reviewScoreByStudent[ student.student_id ] = student.review_score;
	}

	const slotCount = Math.max( 0, Number( maxPanelReviewerSlots ) || 0 );

	return ( markStudents ?? [] ).map( ( student ) => {
		const cells = {};
		const panelReviewers = student.panel_reviewers ?? [];

		for ( const criterionId of Object.keys( student.marks ?? {} ) ) {
			const numericId = Number( criterionId );
			for ( let slot = 0; slot < slotCount; slot += 1 ) {
				const reviewer = panelReviewers.find(
					( row ) => row.slot_index === slot
				);
				const key = slotLeafKey( numericId, slot );
				if ( ! reviewer?.user_id ) {
					cells[ key ] = null;
					continue;
				}
				cells[ key ] = getScore(
					student,
					numericId,
					reviewer.user_id
				);
			}
		}

		return {
			student_id: student.student_id,
			reg_no: student.reg_no,
			name: student.name,
			panel_name: student.panel_name ?? '',
			panel_coordinator_name: student.panel_coordinator_name ?? '',
			panel_reviewer_names: student.panel_reviewer_names ?? '',
			attendance_status: student.attendance_status ?? 'present',
			mark_status: student.mark_status ?? 'not_started',
			cells,
			review_score: reviewScoreByStudent[ student.student_id ] ?? null,
		};
	} );
}

function sortValueForRow( row, sortKey ) {
	if ( sortKey === 'reg_no' ) {
		return row.reg_no ?? '';
	}
	if ( sortKey === 'name' ) {
		return row.name ?? '';
	}
	if ( sortKey === 'attendance' ) {
		return row.attendance_status ?? '';
	}
	if ( sortKey === 'mark_status' ) {
		return row.mark_status ?? '';
	}
	if ( sortKey === 'panel' ) {
		return row.panel_name ?? '';
	}
	if ( sortKey === 'panel_coordinator' ) {
		return row.panel_coordinator_name ?? '';
	}
	if ( sortKey === 'reviewers' ) {
		return row.panel_reviewer_names ?? '';
	}
	if ( sortKey === 'review_score' ) {
		return row.review_score;
	}

	const cell = row.cells?.[ sortKey ];
	if ( cell == null ) {
		return null;
	}

	return cell.score ?? null;
}

function isMissingSortValue( value ) {
	return value === null || value === undefined || value === '';
}

/**
 * Null / missing scores: last in ascending, first in descending.
 */
export function compareSortValues( a, b, sortDir ) {
	const aMissing = isMissingSortValue( a );
	const bMissing = isMissingSortValue( b );

	if ( aMissing && bMissing ) {
		return 0;
	}
	if ( aMissing ) {
		return sortDir === 'asc' ? 1 : -1;
	}
	if ( bMissing ) {
		return sortDir === 'asc' ? -1 : 1;
	}

	if ( typeof a === 'number' && typeof b === 'number' ) {
		return a - b;
	}

	return String( a ).localeCompare( String( b ), undefined, {
		sensitivity: 'base',
		numeric: true,
	} );
}

export function sortRows( rows, columns, sortKey, sortDir ) {
	const sorted = [ ...rows ].sort( ( rowA, rowB ) => {
		const a = sortValueForRow( rowA, sortKey );
		const b = sortValueForRow( rowB, sortKey );
		const cmp = compareSortValues( a, b, sortDir );

		if ( cmp !== 0 ) {
			return sortDir === 'desc' ? -cmp : cmp;
		}

		return String( rowA.reg_no ?? '' ).localeCompare(
			String( rowB.reg_no ?? '' ),
			undefined,
			{ sensitivity: 'base', numeric: true }
		);
	} );

	return sorted;
}

function formatExportScore( cell ) {
	if ( cell == null ) {
		return '';
	}
	if ( typeof cell === 'number' ) {
		return cell;
	}
	if ( cell.score != null ) {
		return cell.score;
	}

	return '';
}

function formatCellForExport( cell ) {
	if ( cell == null ) {
		return '';
	}
	if ( cell.score != null ) {
		return cell.score;
	}

	return '';
}

/**
 * Two header rows + data rows for CSV download.
 */
export function rowsToCsv( columns, sortedRows ) {
	const leaves = getAllLeafColumns( columns );
	const header1Fixed = [
		'Reg no',
		'Student',
		'Panel',
		'Panel coordinator',
		'Reviewers',
		'Attendance',
		'Status',
	];
	for ( const group of columns.groups ) {
		header1Fixed.push( group.label );
		for ( let i = 1; i < group.leaves.length; i += 1 ) {
			header1Fixed.push( '' );
		}
	}
	header1Fixed.push( columns.trailing[ 0 ]?.label ?? 'Weighted review score' );

	const header2 = [
		'',
		'',
		'',
		'',
		'',
		'',
		'',
		...leaves.map( ( leaf ) => leaf.label ),
		'',
	];

	const dataRows = sortedRows.map( ( row ) => [
		row.reg_no ?? '',
		row.name ?? '',
		row.panel_name ?? '',
		row.panel_coordinator_name ?? '',
		row.panel_reviewer_names ?? '',
		row.attendance_status === 'absent' ? 'Absent' : 'Present',
		formatMarkStatusExport( row.mark_status ),
		...leaves.map( ( leaf ) => formatCellForExport( row.cells?.[ leaf.key ] ) ),
		formatExportScore( row.review_score ),
	] );

	const lines = [ header1Fixed, header2, ...dataRows ].map( ( row ) =>
		row
			.map( ( cell ) => {
				const value = cell === null || cell === undefined ? '' : String( cell );
				if (
					value.includes( ',' ) ||
					value.includes( '"' ) ||
					value.includes( '\n' )
				) {
					return `"${ value.replace( /"/g, '""' ) }"`;
				}

				return value;
			} )
			.join( ',' )
	);

	return lines.join( '\n' );
}

function formatMarkStatusExport( status ) {
	if ( status === 'locked' ) {
		return 'Locked';
	}
	if ( status === 'frozen' ) {
		return 'Frozen';
	}
	if ( status === 'in_progress' ) {
		return 'In progress';
	}
	if ( status === 'draft' ) {
		return 'Draft';
	}

	return 'Not started';
}

export function buildMarksExportFilename( sessionSlug, reviewLabel, layout, format ) {
	const safeSession = sessionSlug || 'session';
	const safeReview = reviewLabel || 'review';
	const ext = format === 'csv' ? 'csv' : 'xlsx';

	return `${ safeSession }_${ safeReview }_marks_${ layout }.${ ext }`;
}
