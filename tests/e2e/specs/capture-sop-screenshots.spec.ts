import { test, expect } from '@playwright/test';
import { loginWordPress, gotoCoordinatorHash, gotoReviewerHash } from '../helpers/auth';
import { loadE2eEnv, missingEnvMessage } from '../helpers/env';
import { gotoGuestReviewsLanding } from '../helpers/reviews-site';
import {
	captureSopScreenshot,
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

test('01 landing login', async ({ page }) => {
	await gotoGuestReviewsLanding(page);
	await shot(page, '01-landing-login');
});

test('coordinator flow through wizard and reports', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env, missingEnvMessage());

	await loginWordPress(page, env!.coordUser, env!.coordPass);

	await gotoCoordinatorHash(page, '/');
	await shot(page, '04-dashboard');
	await shot(page, '02-workspace-top-nav');
	await shot(page, '03-coordinator-nav');

	// Registry: enrol students before create-project search.
	await gotoCoordinatorHash(page, '/registry');
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
	await expect(reviewerRow.getByText('Account linked')).toBeVisible({
		timeout: 30_000,
	});
	await shot(page, '10-wizard-reviewers');

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
	await page.getByRole('tab', { name: /Panel assignments/i }).click();
	await shot(page, '13-wizard-assignments');
	await page.getByRole('button', { name: 'Continue to Open reviews' }).click({
		timeout: 30_000,
	});
	await shot(page, '14-wizard-open-marking');

	await page.getByRole('button', { name: 'Open for marking' }).click();
	await page.getByRole('button', { name: 'Start marking' }).first().click();
	await expect(page.getByText('Marking open')).toBeVisible({ timeout: 20_000 });

	await gotoCoordinatorHash(page, `/session/${sessionId}/progress`);
	await shot(page, '15-progress-accordion');

	await gotoCoordinatorHash(page, `/session/${sessionId}/reports`);
	await shot(page, '18-reports-tabs');
	await page.getByRole('tab', { name: 'Downloads' }).click();
	await shot(page, '20-reports-downloads');

	await gotoCoordinatorHash(page, `/session/${sessionId}/close`);
	await expect(page.getByRole('heading', { name: 'Close project' })).toBeVisible();
	await page.getByRole('heading', { name: 'Delete project' }).scrollIntoViewIfNeeded();
	await shot(page, '24-close-project');
});

test('reviewer assignments and marking grid', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env || !sessionId, 'Coordinator flow must create a project first');

	await loginWordPress(page, env!.reviewerUser, env!.reviewerPass, '/reviews/mark/');
	await gotoReviewerHash(page, '/');
	await shot(page, '25-reviewer-assignments');

	const assignmentLink = page
		.getByRole('link')
		.filter({ hasText: projectTitle })
		.first();
	await expect(assignmentLink).toBeVisible({ timeout: 30_000 });
	await assignmentLink.click();
	await expect(page.getByTestId('pr-content-loading')).toHaveCount(0, {
		timeout: 30_000,
	});
	await shot(page, '26-marking-grid');

	await page.getByRole('button', { name: 'Update score' }).first().click();
	const scoreDialog = page.getByRole('dialog');
	await expect(scoreDialog).toBeVisible();
	await shot(page, '28-rubric-form');
	await scoreDialog.locator('input[inputmode="decimal"]').first().fill('8');
	await scoreDialog.getByRole('button', { name: 'Save' }).click();
	await expect(scoreDialog).toBeHidden({ timeout: 20_000 });
});
