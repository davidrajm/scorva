import { expect, type Page } from '@playwright/test';
import {
	loginWordPress,
	gotoCoordinatorHash,
	gotoReviewerHash,
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
		sessionId: null as string | null,
	};
}

export type JourneyContext = ReturnType<typeof createJourneyContext>;

export async function runCoordinatorSetup(
	page: Page,
	env: E2eEnv,
	ctx: JourneyContext,
	onStep?: StepCallback
): Promise<void> {
	const total = 10;
	const step = (n: number, title: string, detail?: string) =>
		onStep ? onStep(n, total, title, detail) : Promise.resolve();

	await step(1, 'Coordinator login', env.coordUser);
	await loginWordPress(page, env.coordUser, env.coordPass);

	await step(2, 'Create project', 'Empty roster — add students in wizard');
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

	await step(3, 'Wizard — Students', 'Add first student');
	await addWizardStudent(page, ctx.studentA);
	await step(4, 'Wizard — Students', 'Add second student');
	await addWizardStudent(page, ctx.studentB);

	await step(5, 'Wizard — Students', 'Confirm roster, continue to Panels');
	await expect(page.getByText(ctx.studentA.regNo)).toBeVisible();
	await page.getByRole('button', { name: 'Continue to Panels' }).click();

	await step(6, 'Wizard — Panels', 'Create panel and assign students');
	await page.getByPlaceholder('Panel name').fill(ctx.panelName);
	await page.getByRole('button', { name: 'Add panel' }).click();
	await expect(
		page.getByRole('button', { name: `Rename panel ${ctx.panelName}` })
	).toBeVisible();
	for (const student of [ctx.studentA, ctx.studentB]) {
		const row = page.locator('li').filter({ hasText: student.regNo });
		await row.getByRole('combobox', { name: new RegExp(student.name) }).selectOption({
			label: ctx.panelName,
		});
	}
	await page.getByRole('button', { name: 'Continue to Reviewers' }).click();

	await step(
		7,
		'Wizard — Reviewers',
		'Add reviewer email and Send credentials → Account linked'
	);
	await page.locator('#add-reviewer-name').fill(ctx.reviewerDisplayName);
	await page.locator('#add-reviewer-email').fill(env.reviewerEmail);
	await page.getByRole('button', { name: 'Add reviewer' }).click();
	const reviewerRow = page.locator('tr').filter({ hasText: env.reviewerEmail });
	await expect(reviewerRow).toBeVisible({ timeout: 30_000 });
	await reviewerRow.getByRole('button', { name: 'Send credentials' }).click();
	await expect(reviewerRow.getByText('Account linked')).toBeVisible({
		timeout: 30_000,
	});

	await step(8, 'Wizard — Rubric', 'Criterion, max marks, Confirm rubric');
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

	await step(9, 'Wizard — Panel assignments', 'Reload so tab unlocks');
	await page.reload();
	await page.locator('#pr-root').waitFor({ state: 'visible' });
	await page.getByRole('tab', { name: /Panel assignments/i }).click();

	await step(10, 'Open project for marking', 'Open reviews → Start marking');
	await page.getByRole('button', { name: 'Continue to Open reviews' }).click({
		timeout: 30_000,
	});
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

export async function runReviewerMarking(
	page: Page,
	env: E2eEnv,
	ctx: Pick<JourneyContext, 'projectTitle' | 'studentA'>,
	onStep?: StepCallback
): Promise<void> {
	const total = 5;
	const step = (n: number, title: string, detail?: string) =>
		onStep ? onStep(n, total, title, detail) : Promise.resolve();

	await step(1, 'Reviewer login', env.reviewerUser);
	await loginWordPress(page, env.reviewerUser, env.reviewerPass, '/reviews/mark/');

	await step(2, 'Assignments', 'Your assignments list');
	await gotoReviewerHash(page, '/');
	await expect(page.getByRole('heading', { name: 'Your assignments' })).toBeVisible();

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
	};
}
