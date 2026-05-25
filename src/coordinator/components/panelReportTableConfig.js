/**
 * Panel Report PDF table column config (shared by settings page and WYSIWYG preview).
 */
export const TABLE_COLUMNS = [
	{
		showKey: 'show_sr_no',
		labelKey: 'sr_no_column_header',
		defaultLabel: 'Sr. No.',
		name: 'Serial number',
		alwaysOn: true,
		className: 'col-sr num',
		shrink: true,
	},
	{
		showKey: 'show_reg_no',
		labelKey: 'reg_no_column_header',
		defaultLabel: 'Reg No',
		name: 'Registration number',
		className: 'col-reg num',
		shrink: false,
	},
	{
		showKey: 'show_student_name',
		labelKey: 'student_column_header',
		defaultLabel: 'Student',
		name: 'Student name',
		alwaysOn: true,
		className: 'col-student',
		shrink: false,
	},
	{
		showKey: 'show_attendance',
		labelKey: 'attendance_column_header',
		defaultLabel: 'At',
		name: 'Attendance',
		hint: 'Short header recommended (1–2 letters). Cells show P or A.',
		className: 'col-att num',
		shrink: true,
	},
	{
		showKey: 'show_project_title',
		labelKey: 'project_title_column_header',
		defaultLabel: 'Project title',
		name: 'Project title',
		className: 'col-title',
		shrink: false,
	},
	{
		showKey: 'show_guide_name',
		labelKey: 'guide_column_header',
		defaultLabel: 'Guide',
		name: 'Guide',
		className: 'col-guide',
		shrink: false,
	},
];

export function formatReviewerHeader( pattern, ordinal ) {
	return ( pattern || 'R{n}' ).replace( '{n}', String( ordinal ) );
}

/**
 * @param {object} tableCfg settings.table
 * @param {Array<{ ordinal: number }>} reviewers
 */
export function buildPreviewScoreColumns( tableCfg, reviewers ) {
	const columns = [];
	const table = tableCfg || {};

	for ( const col of TABLE_COLUMNS ) {
		const enabled = col.alwaysOn || table[ col.showKey ] !== false;
		if ( ! enabled ) {
			continue;
		}
		columns.push( {
			...col,
			header: table[ col.labelKey ] ?? col.defaultLabel,
		} );
	}

	const pattern = table.reviewer_header_pattern || 'R{n}';
	for ( const reviewer of reviewers ) {
		columns.push( {
			header: formatReviewerHeader( pattern, reviewer.ordinal ),
			className: 'col-reviewer score',
			shrink: true,
			isReviewer: true,
		} );
	}

	columns.push( {
		header: table.final_marks_column_header || 'Final Marks',
		className: 'col-final score',
		shrink: true,
		isFinal: true,
	} );

	return columns;
}

/**
 * @param {object} student
 * @param {ReturnType<typeof buildPreviewScoreColumns>} columns
 */
export function previewScoreRowCells( student, columns ) {
	return columns.map( ( column ) => {
		if ( column.isReviewer ) {
			return student.attendance_label === 'A' ? '—' : '36.50';
		}
		if ( column.isFinal ) {
			return student.review_score == null
				? '—'
				: Number( student.review_score ).toFixed( 2 );
		}
		const cls = column.className || '';
		if ( cls.includes( 'col-sr' ) ) {
			return String( student.sr_no ?? '' );
		}
		if ( cls.includes( 'col-reg' ) ) {
			return student.reg_no ?? '';
		}
		if ( cls.includes( 'col-student' ) ) {
			return student.name ?? '';
		}
		if ( cls.includes( 'col-att' ) ) {
			return student.attendance_label ?? 'P';
		}
		if ( cls.includes( 'col-title' ) ) {
			return student.project_title ?? '';
		}
		if ( cls.includes( 'col-guide' ) ) {
			return student.guide_name ?? '';
		}
		return '';
	} );
}
