/**
 * Static sample data for Panel Report settings WYSIWYG preview (no REST).
 */

/** Last two digits of the batch / academic-year start (calendar year). */
function academicYearPrefix() {
	return String( new Date().getFullYear() ).slice( -2 );
}

const REG_PREFIX = academicYearPrefix();

export const PANEL_REPORT_PREVIEW_FIXTURE = {
	review_label: 'Review 1',
	panel_name: 'Panel A',
	reviewers: [
		{ ordinal: 1, name: 'Dr. Sample One' },
		{ ordinal: 2, name: 'Dr. Sample Two' },
		{ ordinal: 3, name: 'Dr. Sample Three' },
	],
	students: [
		{
			sr_no: 1,
			reg_no: `${ REG_PREFIX }MDT1001`,
			name: 'Alex Sample',
			attendance_label: 'P',
			project_title: 'Smart Campus IoT Network',
			guide_name: 'Prof. Guide Alpha',
			review_score: 42.5,
		},
		{
			sr_no: 2,
			reg_no: `${ REG_PREFIX }MDT1002`,
			name: 'Jordan Sample',
			attendance_label: 'A',
			project_title: 'ML-Based Energy Forecasting',
			guide_name: 'Prof. Guide Beta',
			review_score: null,
		},
		{
			sr_no: 3,
			reg_no: `${ REG_PREFIX }MDT1003`,
			name: 'Sam Sample',
			attendance_label: 'P',
			project_title: 'Blockchain Supply Chain Audit',
			guide_name: 'Prof. Guide Gamma',
			review_score: 38.0,
		},
	],
};
