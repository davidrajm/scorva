import {
	compareSortValues,
	rowsToCsv as marksRowsToCsv,
	sortRows,
	truncateLabel,
} from './reportsMarksMatrixUtils';

export { compareSortValues, truncateLabel };

export function overallSlotKey( slotIndex ) {
	return `overall_s${ slotIndex }`;
}

/**
 * @param {number} maxPanelReviewerSlots
 */
export function buildScoresColumns( maxPanelReviewerSlots = 0 ) {
	const slotCount = Math.max( 0, Number( maxPanelReviewerSlots ) || 0 );

	const fixed = [
		{ key: 'reg_no', label: 'Reg no', sortKey: 'reg_no' },
		{ key: 'name', label: 'Student', sortKey: 'name' },
		{ key: 'panel', label: 'Panel', sortKey: 'panel' },
		{
			key: 'panel_coordinator',
			label: 'Panel coordinator',
			sortKey: 'panel_coordinator',
		},
		{ key: 'reviewers', label: 'Reviewers', sortKey: 'reviewers' },
		{ key: 'attendance', label: 'Attendance', sortKey: 'attendance' },
		{ key: 'mark_status', label: 'Status', sortKey: 'mark_status' },
	];

	const leaves = [];
	for ( let slot = 0; slot < slotCount; slot += 1 ) {
		leaves.push( {
			key: overallSlotKey( slot ),
			label: `Reviewer ${ slot + 1 }`,
			fullLabel: `Reviewer ${ slot + 1 }`,
			sortKey: overallSlotKey( slot ),
			kind: 'score',
			slotIndex: slot,
		} );
	}

	return {
		fixed,
		groups: [
			{
				id: 'reviewer-overall',
				label: 'Reviewer overall',
				fullLabel: 'Reviewer overall',
				leaves,
			},
		],
		trailing: [
			{
				key: 'review_score',
				label: 'Weighted review score',
				sortKey: 'review_score',
				kind: 'number',
			},
		],
	};
}

export function getAllOverallLeafColumns( columns ) {
	return columns.groups.flatMap( ( group ) => group.leaves );
}

function normalizeTotalCell( raw ) {
	if ( raw == null ) {
		return null;
	}
	if ( typeof raw === 'object' && 'total' in raw ) {
		return normalizeTotalCell( raw.total );
	}
	if ( typeof raw === 'object' && ( 'score' in raw || 'draft' in raw ) ) {
		return {
			score: raw.score ?? null,
			draft: Boolean( raw.draft ),
		};
	}

	return {
		score: raw,
		draft: false,
	};
}

function totalForSlot( markStudent, scoreStudent, slotIndex ) {
	const attendance =
		markStudent?.attendance_status ?? scoreStudent?.attendance_status ?? 'present';
	if ( attendance === 'absent' ) {
		return null;
	}

	const panelReviewers =
		markStudent?.panel_reviewers ?? scoreStudent?.panel_reviewers ?? [];
	const reviewer = panelReviewers.find( ( row ) => row.slot_index === slotIndex );
	if ( ! reviewer?.user_id ) {
		return null;
	}

	if ( reviewer.total != null ) {
		return normalizeTotalCell( reviewer.total );
	}

	const legacy =
		scoreStudent?.reviewer_totals?.[ String( reviewer.user_id ) ] ??
		scoreStudent?.reviewer_totals?.[ reviewer.user_id ];

	return normalizeTotalCell( legacy );
}

/**
 * @param {object[]} markStudents
 * @param {object[]} scoreStudents
 * @param {number} maxPanelReviewerSlots
 */
export function buildScoresRows(
	markStudents,
	scoreStudents,
	maxPanelReviewerSlots = 0
) {
	const reviewScoreByStudent = {};
	const scoreStudentById = {};
	for ( const student of scoreStudents ?? [] ) {
		reviewScoreByStudent[ student.student_id ] = student.review_score;
		scoreStudentById[ student.student_id ] = student;
	}

	const slotCount = Math.max( 0, Number( maxPanelReviewerSlots ) || 0 );

	return ( markStudents ?? [] ).map( ( student ) => {
		const scoreStudent = scoreStudentById[ student.student_id ];
		const cells = {};

		for ( let slot = 0; slot < slotCount; slot += 1 ) {
			cells[ overallSlotKey( slot ) ] = totalForSlot(
				student,
				scoreStudent,
				slot
			);
		}

		return {
			student_id: student.student_id,
			reg_no: student.reg_no,
			name: student.name,
			panel_name: student.panel_name ?? scoreStudent?.panel_name ?? '',
			panel_coordinator_name:
				student.panel_coordinator_name ??
				scoreStudent?.panel_coordinator_name ??
				'',
			panel_reviewer_names:
				student.panel_reviewer_names ??
				scoreStudent?.panel_reviewer_names ??
				'',
			attendance_status:
				student.attendance_status ??
				scoreStudent?.attendance_status ??
				'present',
			mark_status:
				student.mark_status ?? scoreStudent?.mark_status ?? 'not_started',
			cells,
			review_score: reviewScoreByStudent[ student.student_id ] ?? null,
		};
	} );
}

export function sortScoresRows( rows, columns, sortKey, sortDir ) {
	return sortRows( rows, columns, sortKey, sortDir );
}

export function scoresRowsToCsv( columns, sortedRows ) {
	const leaves = getAllOverallLeafColumns( columns );
	const header1Fixed = [
		'Reg no',
		'Student',
		'Panel',
		'Panel coordinator',
		'Reviewers',
	];
	for ( const group of columns.groups ) {
		header1Fixed.push( group.label );
		for ( let i = 1; i < group.leaves.length; i += 1 ) {
			header1Fixed.push( '' );
		}
	}
	header1Fixed.push( columns.trailing[ 0 ]?.label ?? 'Weighted review score' );

	const header2 = [ '', '', '', '', '', ...leaves.map( ( leaf ) => leaf.label ), '' ];

	const dataRows = sortedRows.map( ( row ) => [
		row.reg_no ?? '',
		row.name ?? '',
		row.panel_name ?? '',
		row.panel_coordinator_name ?? '',
		row.panel_reviewer_names ?? '',
		...leaves.map( ( leaf ) => {
			const cell = row.cells?.[ leaf.key ];
			if ( cell == null || cell.score == null ) {
				return '';
			}

			return cell.score;
		} ),
		row.review_score ?? '',
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

export function buildScoresExportFilename( sessionSlug, reviewLabel, format ) {
	const safeSession = sessionSlug || 'session';
	const safeReview = reviewLabel || 'review';
	const ext = format === 'csv' ? 'csv' : 'xlsx';

	return `${ safeSession }_${ safeReview }_scores.${ ext }`;
}
