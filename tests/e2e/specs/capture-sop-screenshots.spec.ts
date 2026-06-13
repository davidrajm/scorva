import { test, expect } from '@playwright/test';
import { loginWordPress, gotoCoordinatorHash, gotoReviewerHash } from '../helpers/auth';
import { loadE2eEnv, missingEnvMessage } from '../helpers/env';
import { gotoGuestReviewsLanding } from '../helpers/reviews-site';
import {
	captureSopScreenshot,
	sopScreenshotAbsPath,
	printTypstScreenshotDirInstructions,
	resolveSopScreenshotRunDir,
	writeSopScreenshotRunManifest,
} from '../helpers/sop-screenshots';
import type { SopScreenshotId } from '../sop-screenshot-manifest';

/**
 * Captures SOP PNGs into docs/sop/screenshots/YYYY-MM-DD_HHmmss/
 * Flow aligned with full-plugin-ui-journey.spec.ts (known-good locators).
 */
test.describe.configure({ mode: 'serial' });

const runId = Date.now();
const projectTitle = `SOP Capture ${runId}`;
const studentA = { regNo: `SOP-${runId}-A`, name: `SOP Student A ${runId}` };
const studentB = { regNo: `SOP-${runId}-B`, name: `SOP Student B ${runId}` };
const panelName = `SOP Panel ${runId}`;
const reviewerDisplayName = `SOP Reviewer ${runId}`;

let typstDir: string;
let sessionId: string | null = null;
let markReviewId: string | null = null;
let markPanelId: string | null = null;
let portalLoginUrl: string | null = null;
const captured: SopScreenshotId[] = [];

test.beforeAll(() => {
	typstDir = resolveSopScreenshotRunDir();
	const env = loadE2eEnv();
	if (!env) {
		test.skip(true, missingEnvMessage());
	}
});

test.afterAll(() => {
	writeSopScreenshotRunManifest(typstDir, captured);
	printTypstScreenshotDirInstructions(typstDir);
});

async function shot(page: import('@playwright/test').Page, id: SopScreenshotId) {
	await captureSopScreenshot(page, typstDir, id);
	captured.push(id);
}

/** Screenshot a specific element rather than the full page. */
async function shotEl(
	locator: import('@playwright/test').Locator,
	id: SopScreenshotId
) {
	await locator.screenshot({ path: sopScreenshotAbsPath(typstDir, id) });
	captured.push(id);
}

/** Wait for the coordinator SPA shell to finish loading its lazy-routed content. */
async function waitForCoordinatorContent(page: import('@playwright/test').Page) {
	await page.waitForLoadState('networkidle', { timeout: 30_000 });
	await page
		.getByTestId('pr-content-loading')
		.waitFor({ state: 'hidden', timeout: 20_000 })
		.catch(() => {/* may not be present */});
}

/** Wait for all SkeletonBlock (.animate-pulse) elements to clear from the page. */
async function waitForSkeletons(page: import('@playwright/test').Page) {
	await page
		.waitForFunction(() => document.querySelectorAll('.animate-pulse').length === 0, {
			timeout: 15_000,
		})
		.catch(() => {/* take screenshot anyway if timeout */});
}

test('01 landing login', async ({ page }) => {
	await gotoGuestReviewsLanding(page);
	await shot(page, '01-landing-login');
});

test('coordinator flow through wizard and reports', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env, missingEnvMessage());

	await loginWordPress(page, env!.coordUser, env!.coordPass);

	await gotoCoordinatorHash(page, '/');
	await waitForCoordinatorContent(page);
	await shot(page, '04-dashboard');
	await shotEl(page.locator('header.pr-topbar'), '02-workspace-top-nav');
	await shotEl(page.locator('#pr-sidebar-nav'), '03-coordinator-nav');

	// Registry: enrol students before create-project search.
	await gotoCoordinatorHash(page, '/registry');
	await waitForCoordinatorContent(page);
	for (const student of [studentA, studentB]) {
		await page.getByRole('button', { name: 'Add student' }).first().click();
		const addStudentForm = page.locator('form').filter({
			has: page.locator('#student-reg_no'),
		});
		await addStudentForm.locator('#student-reg_no').fill(student.regNo);
		await addStudentForm.locator('#student-name').fill(student.name);
		await addStudentForm.getByRole('button', { name: 'Add student' }).click();
		await expect(page.getByText(student.regNo)).toBeVisible({ timeout: 20_000 });
	}
	await shot(page, '05-registry');

	// CSV import mapper: show the importer and upload a sample CSV to trigger column-mapping UI.
	await page.getByRole('button', { name: 'Import CSV' }).first().click();
	const csvContent =
		`reg_no,name,program,batch\n` +
		`25MDT1001,Alice Sample,MDT,2025\n` +
		`25MDT1002,Bob Sample,MDT,2025\n` +
		`25MDT1003,Carol Sample,MDT,2025`;
	await page.locator('#csv-file-students').setInputFiles({
		name: 'students-sample.csv',
		mimeType: 'text/csv',
		buffer: Buffer.from(csvContent),
	});
	await page.locator('#map-reg_no').waitFor({ state: 'visible', timeout: 10_000 });
	await shot(page, '06-csv-import-mapper');
	await page.getByRole('button', { name: 'Hide import' }).first().click();

	await gotoCoordinatorHash(page, '/');
	await page.getByTestId('pr-show-create-project').click();
	await page.getByTestId('pr-project-title').fill(projectTitle);
	for (const student of [studentA, studentB]) {
		await page.getByTestId('pr-registry-search').fill(student.regNo);
		const row = page.locator('li').filter({ hasText: student.regNo }).first();
		await expect(row).toBeVisible({ timeout: 15_000 });
		await row.getByRole('button', { name: 'Add' }).click();
	}
	await page.getByTestId('pr-create-project').getByRole('button', { name: 'Create project' }).click();
	await expect(page.getByRole('heading', { name: 'Add students' })).toBeVisible({
		timeout: 30_000,
	});

	const idMatch = page.url().match(/\/session\/(\d+)\//);
	expect(idMatch).toBeTruthy();
	sessionId = idMatch![1];

	await shot(page, '07-wizard-nav');
	await shot(page, '08-wizard-students');

	await page.getByRole('button', { name: 'Continue to Panels' }).click();
	await page.getByPlaceholder('Panel name').fill(panelName);
	await page.getByRole('button', { name: 'Add panel' }).click();
	await expect(
		page.getByRole('button', { name: `Rename panel ${panelName}` })
	).toBeVisible();
	for (const student of [studentA, studentB]) {
		const row = page.locator('li').filter({ hasText: student.regNo });
		await row.getByRole('combobox', { name: new RegExp(student.name) }).selectOption({
			label: panelName,
		});
	}
	await shot(page, '09-wizard-panels');
	await page.getByRole('button', { name: 'Continue to Reviewers' }).click();

	await page.locator('#add-reviewer-name').fill(reviewerDisplayName);
	await page.locator('#add-reviewer-email').fill(env!.reviewerEmail);
	await page.getByRole('button', { name: 'Add reviewer' }).click();
	const reviewerRow = page.locator('tr').filter({ hasText: env!.reviewerEmail });
	await expect(reviewerRow).toBeVisible({ timeout: 30_000 });
	await reviewerRow.getByRole('button', { name: 'Send credentials' }).click();
	await expect(
		reviewerRow.locator('span').filter({ hasText: /^(Sent |Generated,)/ })
	).toBeVisible({ timeout: 30_000 });
	await shot(page, '10-wizard-reviewers');

	// Grab the portal URL from "View link" for the portal-login screenshot.
	await reviewerRow.getByRole('button', { name: 'View link' }).click();
	const credDialog = page.getByRole('dialog', { name: 'Reviewer credentials' });
	await expect(credDialog).toBeVisible({ timeout: 10_000 });
	portalLoginUrl = await credDialog.getByRole('textbox', { name: 'Login URL' }).inputValue();
	await page.keyboard.press('Escape');

	// Designate reviewer as panel coordinator (enabled after credentials are sent).
	// Use .click() not .check() — React controlled inputs revert before the async API
	// response arrives, so Playwright's post-click state assertion in .check() fails.
	await page
		.getByRole('checkbox', { name: `Panel coordinator for ${reviewerDisplayName}` })
		.click();
	// Wait for the success toast confirming the API saved the designation.
	await page
		.getByText(/set as panel coordinator/i)
		.waitFor({ state: 'visible', timeout: 15_000 })
		.catch(() => {/* toast may have already faded */});

	await page.getByRole('tab', { name: 'Reviews & rubrics' }).click();
	const createReview = page.getByRole('button', { name: 'Create Review 1' });
	if (await createReview.isVisible().catch(() => false)) {
		await createReview.click();
	}

	const rubricTable = page.locator('table').filter({
		has: page.getByRole('columnheader', { name: 'Max marks' }),
	});
	const criterionRow = rubricTable.locator('tbody tr').first();
	await criterionRow.getByRole('textbox').nth(0).fill('Technical quality');
	await criterionRow.getByRole('textbox').nth(1).fill('10');
	await shot(page, '11-wizard-rubric-builder');
	await page.getByRole('button', { name: 'Save' }).first().click();
	await expect(page.getByText(/Total marks:\s*10/)).toBeVisible({ timeout: 15_000 });
	await page.getByRole('button', { name: 'Confirm' }).first().click();
	await expect(page.getByRole('dialog')).toBeVisible({ timeout: 10_000 });
	await shot(page, '12-rubric-confirm-dialog');
	await page.getByRole('dialog').getByRole('button', { name: 'Confirm rubric' }).click();
	await expect(
		page
			.getByRole('heading', { name: 'Review 1', level: 3 })
			.locator('..')
			.getByText('Confirmed')
	).toBeVisible({ timeout: 20_000 });

	await page.reload();
	await page.locator('#pr-root').waitFor({ state: 'visible' });
	await waitForCoordinatorContent(page);
	await page.getByRole('tab', { name: /Panel assignments/i }).click();
	await waitForCoordinatorContent(page);
	await page
		.locator('table, p:has-text("No student assignments"), p:has-text("No review rounds")')
		.first()
		.waitFor({ state: 'visible', timeout: 20_000 })
		.catch(() => {});
	const unassignedNotice = page.locator('text=/still need a panel/');
	if (await unassignedNotice.isVisible().catch(() => false)) {
		await page.getByRole('button', { name: 'Reset to project defaults' }).click();
		await page
			.getByRole('button', { name: 'Reset assignments' })
			.click({ timeout: 10_000 })
			.catch(() => {});
		await waitForCoordinatorContent(page);
	}
	await shot(page, '13-wizard-assignments');
	await page.getByRole('button', { name: 'Continue to Open reviews' }).click({
		timeout: 30_000,
	});
	await waitForCoordinatorContent(page);
	await waitForSkeletons(page);
	await shot(page, '14-wizard-open-marking');

	await page.getByRole('button', { name: 'Open for marking' }).click();
	await page.getByRole('button', { name: 'Start marking' }).first().click();
	await expect(page.getByText('Marking open')).toBeVisible({ timeout: 20_000 });

	await gotoCoordinatorHash(page, `/session/${sessionId}/progress`);
	await waitForCoordinatorContent(page);
	await waitForSkeletons(page);
	await shot(page, '15-progress-accordion');

	await gotoCoordinatorHash(page, `/session/${sessionId}/reports`);
	await waitForCoordinatorContent(page);
	await shot(page, '18-reports-tabs');
	await page.getByRole('tab', { name: 'Downloads' }).click();
	await shot(page, '20-reports-downloads');

	await gotoCoordinatorHash(page, `/session/${sessionId}/close`);
	await waitForCoordinatorContent(page);
	await expect(page.getByRole('heading', { name: 'Close project' })).toBeVisible();
	await page.getByRole('heading', { name: 'Delete project' }).scrollIntoViewIfNeeded();
	await shot(page, '24-close-project');
});

test('reviewer assignments and marking grid', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env || !sessionId, 'Coordinator flow must create a project first');

	await loginWordPress(page, env!.reviewerUser, env!.reviewerPass, '/reviews/mark/');
	await gotoReviewerHash(page, '/');
	await page.waitForLoadState('networkidle', { timeout: 30_000 });
	await shot(page, '25-reviewer-assignments');

	// Double-filter to get only assignment cards (has "Enter marks" link), not unfreeze-request
	// list items that also contain the project title text.
	const assignmentCard = page
		.locator('li')
		.filter({ hasText: projectTitle })
		.filter({ has: page.getByRole('link', { name: 'Enter marks' }) })
		.first();
	await expect(assignmentCard).toBeVisible({ timeout: 30_000 });

	// 33: assignment card showing "Panel report" link (panel coordinator dual-action layout).
	await expect(assignmentCard.getByRole('link', { name: 'Panel report' }))
		.toBeVisible({ timeout: 15_000 })
		.catch(() => {});
	await shotEl(assignmentCard, '33-panel-head-card');

	// 34: panel report page — navigate via the card's "Panel report" link while not yet frozen.
	await assignmentCard.getByRole('link', { name: 'Panel report' }).click();
	await page.waitForLoadState('networkidle', { timeout: 30_000 });
	await waitForSkeletons(page);
	await shot(page, '34-panel-report-page');

	// Return to assignments, then enter the marking grid via "Enter marks".
	await gotoReviewerHash(page, '/');
	await page.waitForLoadState('networkidle', { timeout: 30_000 });
	const enterMarksLink = page
		.locator('li')
		.filter({ hasText: projectTitle })
		.filter({ has: page.getByRole('link', { name: 'Enter marks' }) })
		.first()
		.getByRole('link', { name: 'Enter marks' });
	await expect(enterMarksLink).toBeVisible({ timeout: 15_000 });
	await enterMarksLink.click();
	await expect(page.getByTestId('pr-content-loading')).toHaveCount(0, {
		timeout: 30_000,
	});
	await waitForSkeletons(page);

	// Capture review/panel IDs from the hash URL for direct panel-report navigation later.
	const markingUrl = page.url();
	const markMatch = markingUrl.match(/#\/mark\/\d+\/(\d+)\/(\d+)/);
	if (markMatch) {
		markReviewId = markMatch[1];
		markPanelId = markMatch[2];
	}

	// 27: mobile card layout — resize to a phone viewport.
	await page.setViewportSize({ width: 390, height: 844 });
	await waitForSkeletons(page);
	await shot(page, '27-marking-grid-mobile');
	await page.setViewportSize({ width: 1280, height: 720 });
	await waitForSkeletons(page);

	// 26: desktop marking grid.
	await shot(page, '26-marking-grid');

	// Open score entry dialog for the first student.
	await page.getByRole('button', { name: 'Update score' }).first().click();
	const scoreDialog = page.getByRole('dialog');
	await expect(scoreDialog).toBeVisible();
	await waitForSkeletons(page);

	// 29: trigger a validation error by entering a value above max (criterion max = 10).
	const criterionInput = scoreDialog.locator('input[inputmode="decimal"]').first();
	await criterionInput.fill('99');
	await criterionInput.press('Tab');
	await page
		.waitForFunction(() => document.querySelector('[aria-invalid="true"]') !== null, {
			timeout: 5_000,
		})
		.catch(() => {});
	await shot(page, '29-validation-error');
	await criterionInput.clear();

	// 28: empty rubric form (after clearing the invalid value).
	await shot(page, '28-rubric-form');

	// Enter a valid score and save for Student A.
	await criterionInput.fill('8');
	await scoreDialog.getByRole('button', { name: 'Save' }).click();
	await expect(scoreDialog).toBeHidden({ timeout: 20_000 });

	// Also save a score for Student B — freeze requires all students to have scores.
	await page.getByRole('button', { name: 'Update score' }).nth(1).click();
	const studentBDialog = page.getByRole('dialog');
	await expect(studentBDialog).toBeVisible({ timeout: 10_000 });
	await waitForSkeletons(page);
	await studentBDialog.locator('input[inputmode="decimal"]').first().fill('7');
	await studentBDialog.getByRole('button', { name: 'Save' }).click();
	await expect(studentBDialog).toBeHidden({ timeout: 20_000 });

	// 31: freeze scores confirmation dialog.
	// Scope by title — multiple ConfirmDialogs share role="dialog" in MarkingGrid.
	await page.getByRole('button', { name: 'Freeze scores' }).click();
	const freezeDialog = page.getByRole('dialog', { name: 'Freeze scores for this review?' });
	await expect(freezeDialog).toBeVisible({ timeout: 10_000 });
	await shot(page, '31-freeze-scores-dialog');
	await freezeDialog.getByRole('button', { name: 'Freeze scores' }).click();
	await expect(freezeDialog).toBeHidden({ timeout: 20_000 });
	await page.waitForLoadState('networkidle', { timeout: 20_000 });

	// 32: request unfreeze dialog (appears after personal freeze).
	await page
		.getByRole('button', { name: 'Request unfreeze' })
		.waitFor({ state: 'visible', timeout: 15_000 });
	await page.getByRole('button', { name: 'Request unfreeze' }).click();
	const unfreezeDialog = page.getByRole('dialog', { name: 'Request unfreeze?' });
	await expect(unfreezeDialog).toBeVisible({ timeout: 10_000 });
	await shot(page, '32-unfreeze-request');
	// Submit the request so it shows in the panel head queue on the assignments page (for 35).
	await unfreezeDialog.locator('#unfreeze-reason').fill(
		'Entered incorrect marks — need to revise before panel freeze.'
	);
	await unfreezeDialog.getByRole('button', { name: 'Request unfreeze' }).click();
	await expect(unfreezeDialog).toBeHidden({ timeout: 20_000 });
	await page.waitForLoadState('networkidle', { timeout: 20_000 });

	// 35: panel head sees pending reviewer unfreeze requests on the assignments page.
	await gotoReviewerHash(page, '/');
	await page.waitForLoadState('networkidle', { timeout: 30_000 });
	await page
		.getByRole('heading', { name: /Reviewer unfreeze requests/i })
		.waitFor({ state: 'visible', timeout: 20_000 })
		.catch(() => {});
	await shot(page, '35-panel-head-unfreeze');

	// Navigate to the panel report page directly (avoids depending on frozen card layout).
	if (sessionId && markReviewId && markPanelId) {
		await gotoReviewerHash(page, `/panel-report/${sessionId}/${markReviewId}/${markPanelId}`);
		await page.waitForLoadState('networkidle', { timeout: 30_000 });
		await waitForSkeletons(page);

		// Freeze the panel so the coordinator's dashboard shows a pending panel unfreeze (for 17).
		const freezePanelBtn = page.getByRole('button', { name: 'Freeze panel scores' });
		if (await freezePanelBtn.isVisible({ timeout: 10_000 }).catch(() => false)) {
			await freezePanelBtn.click();
			const panelFreezeDialog = page.getByRole('dialog', { name: 'Freeze panel scores?' });
			await expect(panelFreezeDialog).toBeVisible({ timeout: 10_000 });
			await panelFreezeDialog.getByRole('button', { name: 'Freeze panel' }).click();
			await expect(panelFreezeDialog).toBeHidden({ timeout: 20_000 });
			await page.waitForLoadState('networkidle', { timeout: 20_000 });

			// Request a panel unfreeze to populate coordinator's 17-unfreeze-requests view.
			const requestPanelUnfreezeBtn = page.getByRole('button', {
				name: 'Request panel unfreeze',
			});
			await requestPanelUnfreezeBtn.waitFor({ state: 'visible', timeout: 15_000 });
			await requestPanelUnfreezeBtn.click();
			const panelUnfreezeDialog = page.getByRole('dialog', {
				name: 'Request panel unfreeze?',
			});
			await expect(panelUnfreezeDialog).toBeVisible({ timeout: 10_000 });
			await panelUnfreezeDialog
				.locator('#panel-unfreeze-reason')
				.fill('Panel was frozen before all review rounds completed.');
			await panelUnfreezeDialog
				.getByRole('button', { name: 'Request panel unfreeze' })
				.click();
			await expect(panelUnfreezeDialog).toBeHidden({ timeout: 20_000 });
		}
	}
});

test('coordinator dashboard after reviewer actions', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env || !sessionId, 'Coordinator and reviewer flows must run first');

	await loginWordPress(page, env!.coordUser, env!.coordPass);

	// 17: coordinator sees pending panel unfreeze request on the dashboard.
	await gotoCoordinatorHash(page, '/');
	await waitForCoordinatorContent(page);
	await page
		.getByRole('heading', { name: /Panel unfreeze requests/i })
		.waitFor({ state: 'visible', timeout: 30_000 })
		.catch(() => {/* take screenshot regardless */});
	await shot(page, '17-unfreeze-requests');

	// 22: panel report settings page.
	await gotoCoordinatorHash(page, `/session/${sessionId}/settings/panel-report`);
	await waitForCoordinatorContent(page);
	await shot(page, '22-panel-report-settings');

	// 23: audit log — has entries from all the wizard, marking, and freeze operations.
	await gotoCoordinatorHash(page, `/session/${sessionId}/audit`);
	await waitForCoordinatorContent(page);
	await shot(page, '23-audit-log');
});

test('portal login screen', async ({ page }) => {
	const url = portalLoginUrl ?? '/reviews/mark/';
	await page.goto(url);
	await page.waitForLoadState('networkidle');

	const isPortalForm = await page
		.getByRole('heading', { name: /Reviewer access|This review link is not valid/i })
		.isVisible()
		.catch(() => false);
	if (!isPortalForm) {
		await page.goto('/wp-login.php?action=logout');
		const confirmLink = page.locator('a').filter({ hasText: /log out/i }).first();
		if (await confirmLink.isVisible().catch(() => false)) {
			await confirmLink.click();
		}
		await page.goto(url);
		await page.waitForLoadState('networkidle');
	}

	await page.waitForLoadState('networkidle');
	await expect(
		page.getByRole('heading', { name: 'Reviewer access' })
	).toBeVisible({ timeout: 15_000 });
	await shot(page, '36-portal-login');
});
