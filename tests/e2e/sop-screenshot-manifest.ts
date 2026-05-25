/**
 * Canonical SOP screenshot IDs — must match docs/sop/project-reviews-sop.typ placeholders.
 * Used by capture-sop-screenshots.spec.ts and completeness checks.
 */
export const SOP_SCREENSHOT_IDS = [
	'01-landing-login',
	'02-workspace-top-nav',
	'03-coordinator-nav',
	'04-dashboard',
	'05-registry',
	'06-csv-import-mapper',
	'07-wizard-nav',
	'08-wizard-students',
	'09-wizard-panels',
	'10-wizard-reviewers',
	'11-wizard-rubric-builder',
	'12-rubric-confirm-dialog',
	'13-wizard-assignments',
	'14-wizard-open-marking',
	'15-progress-accordion',
	'16-score-breakdown',
	'17-unfreeze-requests',
	'18-reports-tabs',
	'19-reports-marks-matrix',
	'20-reports-downloads',
	'21-offline-scoring-pdf',
	'22-panel-report-settings',
	'23-audit-log',
	'24-close-project',
	'25-reviewer-assignments',
	'26-marking-grid',
	'27-marking-grid-mobile',
	'28-rubric-form',
	'29-validation-error',
	'30-flagged-mark',
	'31-freeze-scores-dialog',
	'32-unfreeze-request',
	'33-panel-head-card',
	'34-panel-report-page',
	'35-panel-head-unfreeze',
] as const;

export type SopScreenshotId = (typeof SOP_SCREENSHOT_IDS)[number];

/** IDs captured automatically by capture-sop-screenshots.spec.ts (extend as UI steps are added). */
export const SOP_SCREENSHOT_IDS_AUTOMATED: SopScreenshotId[] = [
	'01-landing-login',
	'02-workspace-top-nav',
	'03-coordinator-nav',
	'04-dashboard',
	'05-registry',
	'07-wizard-nav',
	'08-wizard-students',
	'09-wizard-panels',
	'10-wizard-reviewers',
	'11-wizard-rubric-builder',
	'12-rubric-confirm-dialog',
	'13-wizard-assignments',
	'14-wizard-open-marking',
	'15-progress-accordion',
	'18-reports-tabs',
	'20-reports-downloads',
	'24-close-project',
	'25-reviewer-assignments',
	'26-marking-grid',
	'28-rubric-form',
];

/** IDs not yet automated — capture manually per docs/sop/screenshots/README.md */
export const SOP_SCREENSHOT_IDS_MANUAL: SopScreenshotId[] = SOP_SCREENSHOT_IDS.filter(
	(id) => !SOP_SCREENSHOT_IDS_AUTOMATED.includes(id as SopScreenshotId)
);
