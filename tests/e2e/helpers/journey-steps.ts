import { expect, type Page } from '@playwright/test';
import fs from 'fs';
import os from 'os';
import path from 'path';
import {
	loginWordPress,
	gotoCoordinatorHash,
} from './auth';
import type { E2eEnv } from './env';
import type { WalkthroughJourneyState } from './walkthrough-state';

type StepCallback = (
	n: number,
	total: number,
	title: string,
	detail?: string
) => Promise<void>;

export function createJourneyContext(runId = Date.now()) {
	return {
		runId,
		projectTitle: `E2E Project ${runId}`,
		panelName: `Panel E2E ${runId}`,
		reviewerDisplayName: `E2E Reviewer ${runId}`,
		studentA: {
			regNo: `E2E-${runId}-A`,
			name: `E2E Student A ${runId}`,
		},
		studentB: {
			regNo: `E2E-${runId}-B`,
			name: `E2E Student B ${runId}`,
		},
		// Students C and D are imported via CSV with guide fields
		studentC: {
			regNo: `E2E-${runId}-C`,
			name: `E2E Student C ${runId}`,
		},
		studentD: {
			regNo: `E2E-${runId}-D`,
			name: `E2E Student D ${runId}`,
		},
		sessionId: null as string | null,
		reviewerPortalUrl: null as string | null,
		reviewerPortalPassword: null as string | null,
	};
}

export type JourneyContext = ReturnType<typeof createJourneyContext>;

export async function runCoordinatorSetup(
	page: Page,
	env: E2eEnv,
	ctx: JourneyContext,
	onStep?: StepCallback
): Promise<void> {
	const total = 12;
	const step = (n: number, title: string, detail?: string) =>
		onStep ? onStep(n, total, title, detail) : Promise.resolve();

	// Step 1 — Login
	await step(1, 'Coordinator login', env.coordUser);
	await loginWordPress(page, env.coordUser, env.coordPass);

	// Step 2 — Create project
	await step(2, 'Create project', 'Empty roster — students added in wizard');
	await gotoCoordinatorHash(page, '/');
	await page.getByTestId('pr-show-create-project').click();
	await page.getByTestId('pr-project-title').fill(ctx.projectTitle);
	await page
		.getByTestId('pr-create-project')
		.getByRole('button', { name: 'Create project' })
		.click();
	await expect(page.getByRole('heading', { name: 'Add students' })).toBeVisible({
		timeout: 30_000,
	});

	const idMatch = page.url().match(/\/session\/(\d+)\//);
	expect(idMatch).toBeTruthy();
	ctx.sessionId = idMatch![1];

	// Steps 3–4 — Add students A and B manually
	await step(3, 'Wizard — Students', 'Add student A manually');
	await addWizardStudent(page, ctx.studentA);

	await step(4, 'Wizard — Students', 'Add student B manually');
	await addWizardStudent(page, ctx.studentB);

	// Step 5 — Panels: create panel and assign students A + B
	await step(5, 'Wizard — Panels', 'Create panel and assign students A + B');
	await page.getByRole('button', { name: 'Continue to Panels' }).click();
	await page.getByPlaceholder('Panel name').fill(ctx.panelName);
	await page.getByRole('button', { name: 'Add panel' }).click();
	await expect(
		page.getByRole('button', { name: `Rename panel ${ctx.panelName}` })
	).toBeVisible({ timeout: 20_000 });
	for (const student of [ctx.studentA, ctx.studentB]) {
		const row = page.locator('li').filter({ hasText: student.regNo });
		await row
			.getByRole('combobox', { name: new RegExp(student.name) })
			.selectOption({ label: ctx.panelName });
	}

	// Step 6 — Back to Students tab: import students C + D via CSV with guide fields
	await step(
		6,
		'Wizard — Students (CSV)',
		'Import students C + D with guide_emp_id and guide_name'
	);
	await page.getByRole('tab', { name: 'Students' }).click();
	await page.getByRole('button', { name: 'Import Students' }).click();
	await uploadSessionEnrolCsv(page, ctx);

	// Step 7 — Reviewers: add reviewer to the panel
	await step(7, 'Wizard — Reviewers', 'Add reviewer to panel');
	await page.getByRole('tab', { name: 'Reviewers' }).click();
	await page.locator('#add-reviewer-name').fill(ctx.reviewerDisplayName);
	await page.locator('#add-reviewer-email').fill(env.reviewerEmail);
	await page.getByRole('button', { name: 'Add reviewer' }).click();
	const reviewerRow = page.locator('tr').filter({ hasText: env.reviewerEmail });
	await expect(reviewerRow).toBeVisible({ timeout: 30_000 });

	// Step 8 — Send credentials, then open the portal link modal to capture URL + password
	await step(
		8,
		'Wizard — Credentials',
		'Send credentials → View link → capture portal URL and password'
	);
	await reviewerRow.getByRole('button', { name: 'Send credentials' }).click();
	// After generation the row switches to Resend / Regenerate / View link
	await expect(reviewerRow.getByRole('button', { name: 'Resend' })).toBeVisible({
		timeout: 30_000,
	});
	await reviewerRow.getByRole('button', { name: 'View link' }).click();
	const credModal = page.getByRole('dialog', { name: 'Reviewer credentials' });
	await expect(credModal).toBeVisible({ timeout: 15_000 });
	ctx.reviewerPortalUrl = await credModal.getByLabel('Login URL').inputValue();
	ctx.reviewerPortalPassword = await credModal.getByLabel('Password').inputValue();
	await credModal.getByRole('button', { name: 'Close' }).click();
	await expect(credModal).toBeHidden();

	// Step 9 — Reviews & rubrics: add a criterion and confirm the rubric
	await step(9, 'Wizard — Rubric', 'Add criterion, max marks, Confirm rubric');
	await page.getByRole('tab', { name: 'Reviews & rubrics' }).click();
	const createReview = page.getByRole('button', { name: 'Create Review 1' });
	if (await createReview.isVisible().catch(() => false)) {
		await createReview.click();
	}
	const rubricTable = page.locator('table').filter({
		has: page.getByRole('columnheader', { name: 'Max marks' }),
	});
	await expect(rubricTable).toBeVisible({ timeout: 20_000 });
	const criterionRow = rubricTable.locator('tbody tr').first();
	await criterionRow.getByRole('textbox').nth(0).fill('Technical quality');
	await criterionRow.getByRole('textbox').nth(1).fill('10');
	await page.getByRole('button', { name: 'Save' }).first().click();
	await expect(page.getByText(/Total marks:\s*10/)).toBeVisible({ timeout: 15_000 });
	await page.getByRole('button', { name: 'Confirm' }).first().click();
	await page
		.getByRole('dialog')
		.getByRole('button', { name: 'Confirm rubric' })
		.click();
	await expect(
		page
			.getByRole('heading', { name: 'Review 1', level: 3 })
			.locator('..')
			.getByText('Confirmed')
	).toBeVisible({ timeout: 20_000 });

	// Step 10 — Panel assignments (reload first so tab unlocks after rubric confirm)
	await step(10, 'Wizard — Panel assignments', 'Reload so tab unlocks, then navigate');
	await page.reload();
	await page.locator('#pr-root').waitFor({ state: 'visible' });
	await page.getByRole('tab', { name: /Panel assignments/i }).click();

	// Step 11 — Advance to the Open reviews / Marking step
	await step(11, 'Wizard — Open reviews', 'Continue to the marking step');
	await page.getByRole('button', { name: 'Continue to Open reviews' }).click({
		timeout: 30_000,
	});

	// Step 12 — Open project for marking and start Review 1
	await step(12, 'Open for marking', 'Open → Start marking → Marking open');
	await page.getByRole('button', { name: 'Open for marking' }).click();
	await expect(page.getByText('Draft project')).toBeHidden({ timeout: 20_000 });
	await page.getByRole('button', { name: 'Start marking' }).first().click();
	await expect(page.getByText('Marking open')).toBeVisible({ timeout: 20_000 });
}

export async function runCoordinatorProgressAndReports(
	page: Page,
	env: E2eEnv,
	ctx: JourneyContext,
	onStep?: (n: number, total: number, title: string, detail?: string) => Promise<void>
): Promise<void> {
	if (!ctx.sessionId) {
		throw new Error('sessionId required');
	}
	const total = 2;
	const step = (n: number, title: string, detail?: string) =>
		onStep ? onStep(n, total, title, detail) : Promise.resolve();

	await step(1, 'Coordinator login', env.coordUser);
	await loginWordPress(page, env.coordUser, env.coordPass);

	await step(2, 'Progress & reports', 'Student in progress; download Excel');
	await gotoCoordinatorHash(page, `/session/${ctx.sessionId}/progress`);
	await expect(page.getByRole('heading', { name: /marking progress/i })).toBeVisible();
	await expect(page.getByRole('combobox', { name: 'Student' })).toContainText(
		ctx.studentA.name,
		{ timeout: 30_000 }
	);
	await gotoCoordinatorHash(page, `/session/${ctx.sessionId}/reports`);
	await page.getByRole('tab', { name: 'Downloads' }).click();
	const downloadPromise = page.waitForEvent('download', { timeout: 60_000 });
	await page.getByRole('button', { name: 'Download Excel' }).first().click();
	const download = await downloadPromise;
	expect(download.suggestedFilename()).toMatch(/\.(xlsx|csv)$/i);
}

export async function runCoordinatorCloseAndDeleteProject(
	page: Page,
	env: E2eEnv,
	ctx: JourneyContext,
	onStep?: StepCallback
): Promise<void> {
	if (!ctx.sessionId) {
		throw new Error('sessionId required');
	}

	const total = 4;
	const step = (n: number, title: string, detail?: string) =>
		onStep ? onStep(n, total, title, detail) : Promise.resolve();

	await step(1, 'Coordinator login', env.coordUser);
	await loginWordPress(page, env.coordUser, env.coordPass);

	await step(2, 'End project — close', 'Close project page and confirm');
	await gotoCoordinatorHash(page, `/session/${ctx.sessionId}/close`);
	await expect(page.getByRole('heading', { name: 'Close project' })).toBeVisible();
	await page.getByRole('button', { name: 'Close project…' }).click();
	const closeDialog = page.getByRole('dialog').filter({
		hasText: 'Close this project',
	});
	await expect(closeDialog).toBeVisible({ timeout: 10_000 });
	await closeDialog.getByRole('button', { name: 'Close project', exact: true }).click();
	await expect(page.getByText(/Project closed/i)).toBeVisible({ timeout: 30_000 });
	await expect(page.getByRole('heading', { name: 'Reopen project' })).toBeVisible();

	await step(
		3,
		'End project — delete',
		'Type exact project title (scores exist from reviewer)'
	);
	await page.getByTestId('pr-delete-project').click();
	const deleteDialog = page.getByRole('dialog').filter({
		hasText: /all scores/i,
	});
	await expect(deleteDialog).toBeVisible({ timeout: 10_000 });
	await page.getByTestId('pr-delete-project-confirm-input').fill(ctx.projectTitle);
	await deleteDialog
		.getByRole('button', { name: 'Delete project and scores' })
		.click();

	await step(4, 'Dashboard', 'Success notice; project removed from list');
	await expect(page).toHaveURL(/\/reviews\/#\/?$/, { timeout: 30_000 });
	await expect(page.getByText(/permanently deleted/i)).toBeVisible({
		timeout: 30_000,
	});
	await expect(page.getByText(ctx.projectTitle, { exact: true })).toHaveCount(0);
	await expect(page.getByRole('heading', { level: 1, name: 'Dashboard' })).toBeVisible();
}

/**
 * Creates a draft project with no entered scores and deletes it (simple confirm only).
 */
export async function runCoordinatorDeleteDraftProject(
	page: Page,
	env: E2eEnv,
	onStep?: StepCallback
): Promise<void> {
	const runId = Date.now();
	const draftTitle = `E2E Delete draft ${runId}`;
	const total = 3;
	const step = (n: number, title: string, detail?: string) =>
		onStep ? onStep(n, total, title, detail) : Promise.resolve();

	await step(1, 'Coordinator login', env.coordUser);
	await loginWordPress(page, env.coordUser, env.coordPass);

	await step(2, 'Create draft project', 'No marks — delete uses single confirm');
	await gotoCoordinatorHash(page, '/');
	await page.getByTestId('pr-show-create-project').click();
	await page.getByTestId('pr-project-title').fill(draftTitle);
	await page
		.getByTestId('pr-create-project')
		.getByRole('button', { name: 'Create project' })
		.click();
	await expect(page.getByRole('heading', { name: 'Add students' })).toBeVisible({
		timeout: 30_000,
	});
	const idMatch = page.url().match(/\/session\/(\d+)\//);
	expect(idMatch).toBeTruthy();
	const sessionId = idMatch![1];

	await gotoCoordinatorHash(page, `/session/${sessionId}/close`);
	await page.getByTestId('pr-delete-project').click();
	const deleteDialog = page.getByRole('dialog').filter({
		hasText: `Delete ${draftTitle}`,
	});
	await expect(deleteDialog).toBeVisible({ timeout: 10_000 });
	await deleteDialog.getByRole('button', { name: 'Delete project', exact: true }).click();

	await step(3, 'Dashboard', 'Success notice without typed confirmation');
	await expect(page.getByText(/permanently deleted/i)).toBeVisible({
		timeout: 30_000,
	});
	await expect(page.getByText(draftTitle, { exact: true })).toHaveCount(0);
}

/**
 * Reviewer logs in via the token portal link (not WP admin) and saves a score.
 */
export async function runReviewerMarking(
	page: Page,
	env: E2eEnv,
	ctx: Pick<
		JourneyContext,
		'projectTitle' | 'studentA' | 'reviewerPortalUrl' | 'reviewerPortalPassword'
	>,
	onStep?: StepCallback
): Promise<void> {
	if (!ctx.reviewerPortalUrl || !ctx.reviewerPortalPassword) {
		throw new Error(
			'reviewerPortalUrl and reviewerPortalPassword must be set — run coordinator setup first'
		);
	}

	const total = 5;
	const step = (n: number, title: string, detail?: string) =>
		onStep ? onStep(n, total, title, detail) : Promise.resolve();

	// Reviewers authenticate via the token link, not wp-login.php
	await step(1, 'Reviewer portal login', 'Open token link and enter portal password');
	await page.goto(ctx.reviewerPortalUrl);
	await page.locator('#pr-root').waitFor({ state: 'visible', timeout: 30_000 });
	const passwordField = page.locator('#pr-portal-password');
	await expect(passwordField).toBeVisible({ timeout: 20_000 });
	await passwordField.fill(ctx.reviewerPortalPassword);
	await page.getByRole('button', { name: 'Open review portal' }).click();

	await step(2, 'Assignments', 'Your assignments list');
	await expect(page.getByRole('heading', { name: 'Your assignments' })).toBeVisible({
		timeout: 30_000,
	});

	await step(3, 'Open assignment', ctx.projectTitle);
	const assignmentLink = page
		.getByRole('link')
		.filter({ hasText: ctx.projectTitle })
		.first();
	await expect(assignmentLink).toBeVisible({ timeout: 30_000 });
	await assignmentLink.click();

	await step(4, 'Marking grid', 'Wait for grid, open Update score');
	await expect(page.getByTestId('pr-content-loading')).toHaveCount(0, {
		timeout: 30_000,
	});
	await expect(page.getByRole('button', { name: 'Update score' }).first()).toBeVisible({
		timeout: 30_000,
	});
	await page.getByRole('button', { name: 'Update score' }).first().click();

	await step(5, 'Save score', 'Enter 8 and confirm Draft appears');
	const scoreDialog = page.getByRole('dialog');
	await scoreDialog.locator('input[inputmode="decimal"]').first().fill('8');
	await scoreDialog.getByRole('button', { name: 'Save' }).click();
	await expect(scoreDialog).toBeHidden({ timeout: 20_000 });
	const studentRow = page.getByRole('row').filter({ hasText: ctx.studentA.regNo });
	await expect(studentRow).toContainText('8', { timeout: 20_000 });
	await expect(studentRow).toContainText('Draft');
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

async function addWizardStudent(
	page: Page,
	student: { regNo: string; name: string }
): Promise<void> {
	await page.getByRole('button', { name: 'Add student' }).click();
	await page.getByTestId('pr-wizard-student-reg-no').fill(student.regNo);
	await page.getByTestId('pr-wizard-student-name').fill(student.name);
	await page.getByRole('button', { name: 'Add to project' }).click();
	await expect(page.getByText(student.regNo)).toBeVisible({ timeout: 20_000 });
}

/**
 * Uploads a session-enrol CSV with students C + D (includes guide_emp_id and
 * guide_name). Column names match the CsvImportMapper auto-detect keys exactly.
 * The panel must already exist before calling this.
 */
async function uploadSessionEnrolCsv(page: Page, ctx: JourneyContext): Promise<void> {
	const { runId, panelName, studentC, studentD } = ctx;
	const csvContent = [
		'reg_no,panel,name,batch,guide_emp_id,guide_name',
		`${studentC.regNo},${panelName},${studentC.name},2025,EMP-${runId}-C,Dr. Guide C`,
		`${studentD.regNo},${panelName},${studentD.name},2025,EMP-${runId}-D,Dr. Guide D`,
	].join('\n');

	const tmpPath = path.join(os.tmpdir(), `e2e-enrol-${runId}.csv`);
	fs.writeFileSync(tmpPath, csvContent, 'utf8');

	try {
		const fileInput = page.locator('input[accept=".csv,text/csv"]');
		await fileInput.setInputFiles(tmpPath);
		// Column names match the auto-detect keys — no manual mapping needed
		await expect(
			page.getByRole('button', { name: 'Import students' })
		).toBeVisible({ timeout: 15_000 });
		await page.getByRole('button', { name: 'Import students' }).click();
		await expect(page.getByText(/Enrolment import:/)).toBeVisible({
			timeout: 20_000,
		});
	} finally {
		fs.unlinkSync(tmpPath);
	}
}

export function toWalkthroughState(ctx: JourneyContext): WalkthroughJourneyState {
	if (!ctx.sessionId) {
		throw new Error('sessionId missing');
	}
	return {
		runId: ctx.runId,
		sessionId: ctx.sessionId,
		projectTitle: ctx.projectTitle,
		panelName: ctx.panelName,
		reviewerDisplayName: ctx.reviewerDisplayName,
		studentA: ctx.studentA,
		studentB: ctx.studentB,
		studentC: ctx.studentC,
		studentD: ctx.studentD,
		reviewerPortalUrl: ctx.reviewerPortalUrl ?? '',
		reviewerPortalPassword: ctx.reviewerPortalPassword ?? '',
	};
}
