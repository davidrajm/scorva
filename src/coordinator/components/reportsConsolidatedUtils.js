import { compareSortValues } from './reportsMarksMatrixUtils';

const REVIEW_SUB_COLUMNS = [
	{ key: 'panel', label: 'Panel' },
	{ key: 'panel_coordinator', label: 'Panel coordinator' },
	{ key: 'reviewers', label: 'Reviewers' },
	{ key: 'review_score', label: 'Review score' },
];

const IDENTITY_COLUMNS = [
	{ key: 'program', label: 'Program', sortKey: 'program' },
	{ key: 'batch', label: 'Batch', sortKey: 'batch' },
	{ key: 'guide_emp_id', label: 'Guide emp. ID', sortKey: 'guide_emp_id' },
	{ key: 'guide_name', label: 'Guide name', sortKey: 'guide_name' },
	{ key: 'project_title', label: 'Project title', sortKey: 'project_title' },
];

const OVERALL_SCORE_COLUMN = {
	key: 'overall_score',
	label: 'Overall score',
	sortKey: 'overall_score',
	kind: 'number',
};

export function buildConsolidatedColumns( reviews = [] ) {
	const reviewGroups = ( reviews ?? [] ).map( ( review ) => ( {
		reviewId: review.review_id,
		label: review.label || `Review ${ review.review_id }`,
		subColumns: REVIEW_SUB_COLUMNS.map( ( col ) => ( {
			...col,
			sortKey: `review_${ review.review_id }_${ col.key }`,
		} ) ),
	} ) );

	return {
		leading: [
			{ key: 'reg_no', label: 'Reg no', sortKey: 'reg_no' },
			{ key: 'name', label: 'Student', sortKey: 'name' },
		],
		identity: IDENTITY_COLUMNS,
		reviewGroups,
		overall: OVERALL_SCORE_COLUMN,
	};
}

export function buildConsolidatedRows( students = [], reviews = [] ) {
	const reviewOrder = ( reviews ?? [] ).map( ( review ) => review.review_id );

	return ( students ?? [] ).map( ( student ) => {
		const reviewById = {};
		for ( const block of student.reviews ?? [] ) {
			reviewById[ block.review_id ] = block;
		}

		const perReview = {};
		for ( const reviewId of reviewOrder ) {
			const block = reviewById[ reviewId ] ?? {};
			perReview[ `review_${ reviewId }_panel` ] = block.panel_name ?? '';
			perReview[ `review_${ reviewId }_panel_coordinator` ] =
				block.panel_coordinator_name ?? '';
			perReview[ `review_${ reviewId }_reviewers` ] =
				block.panel_reviewer_names ?? '';
			perReview[ `review_${ reviewId }_review_score` ] =
				block.review_score ?? null;
		}

		return {
			student_id: student.student_id,
			reg_no: student.reg_no ?? '',
			name: student.name ?? '',
			program: student.program ?? '',
			batch: student.batch ?? '',
			guide_emp_id: student.guide_emp_id ?? '',
			guide_name: student.guide_name ?? '',
			project_title: student.project_title ?? '',
			overall_score: student.overall_score ?? null,
			cells: perReview,
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
	if ( sortKey === 'program' ) {
		return row.program ?? '';
	}
	if ( sortKey === 'batch' ) {
		return row.batch ?? '';
	}
	if ( sortKey === 'guide_emp_id' ) {
		return row.guide_emp_id ?? '';
	}
	if ( sortKey === 'guide_name' ) {
		return row.guide_name ?? '';
	}
	if ( sortKey === 'project_title' ) {
		return row.project_title ?? '';
	}
	if ( sortKey === 'overall_score' ) {
		return row.overall_score;
	}

	return row.cells?.[ sortKey ] ?? null;
}

export function sortConsolidatedRows( rows, sortKey, sortDir ) {
	return [ ...rows ].sort( ( rowA, rowB ) => {
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
}

function formatExportScore( value ) {
	if ( value == null || value === '' ) {
		return '';
	}

	return value;
}

export function consolidatedRowsToCsv( columns, sortedRows ) {
	const header1 = [
		...columns.leading.map( ( col ) => col.label ),
		...columns.identity.map( ( col ) => col.label ),
	];
	for ( const group of columns.reviewGroups ) {
		header1.push( group.label );
		for ( let i = 1; i < group.subColumns.length; i += 1 ) {
			header1.push( '' );
		}
	}
	header1.push( columns.overall.label );

	const header2 = [
		...columns.leading.map( () => '' ),
		...columns.identity.map( () => '' ),
		...columns.reviewGroups.flatMap( ( group ) =>
			group.subColumns.map( ( col ) => col.label )
		),
		'',
	];

	const dataRows = sortedRows.map( ( row ) => [
		row.reg_no ?? '',
		row.name ?? '',
		row.program ?? '',
		row.batch ?? '',
		row.guide_emp_id ?? '',
		row.guide_name ?? '',
		row.project_title ?? '',
		...columns.reviewGroups.flatMap( ( group ) =>
			group.subColumns.map( ( col ) =>
				formatExportScore( row.cells?.[ col.sortKey ] )
			)
		),
		formatExportScore( row.overall_score ),
	] );

	const lines = [ header1, header2, ...dataRows ].map( ( row ) =>
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

export function buildConsolidatedExportFilename( sessionSlug, format ) {
	const safeSession = sessionSlug || 'session';
	const ext = format === 'csv' ? 'csv' : 'xlsx';

	return `${ safeSession }_consolidated_scores.${ ext }`;
}
