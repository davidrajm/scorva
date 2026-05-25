import { test } from '@playwright/test';
import { loginWordPress } from '../helpers/auth';
import { loadE2eEnv, missingEnvMessage } from '../helpers/env';
import {
	createJourneyContext,
	runCoordinatorCloseAndDeleteProject,
	runCoordinatorDeleteDraftProject,
	runCoordinatorSetup,
	runCoordinatorProgressAndReports,
	runReviewerMarking,
} from '../helpers/journey-steps';
import { gotoGuestReviewsLanding } from '../helpers/reviews-site';

/**
 * Full UI journey: registry → create project → wizard setup → reviewer marks → progress/reports.
 * Fast regression (~15s). For a slow demo, use coordinator-walkthrough + reviewer-walkthrough specs.
 */
test.describe.configure({ mode: 'serial' });

const ctx = createJourneyContext();

test.beforeAll(() => {
	if (!loadE2eEnv()) {
		test.skip(true, missingEnvMessage());
	}
});

test('guest landing shows Log in', async ({ page }) => {
	await gotoGuestReviewsLanding(page);
});

test('coordinator: registry, create project, complete wizard', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env, missingEnvMessage());
	await runCoordinatorSetup(page, env!, ctx);
});

test('reviewer: open assignment and save a mark', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env || !ctx.sessionId, 'Wizard must create a project first');
	await runReviewerMarking(page, env!, ctx);
});

test('coordinator: progress and reports export', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env || !ctx.sessionId, 'Wizard must create a project first');
	await runCoordinatorProgressAndReports(page, env!, ctx);
});

test('coordinator: close and delete project', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env || !ctx.sessionId, 'Wizard must create a project first');
	await runCoordinatorCloseAndDeleteProject(page, env!, ctx);
});

test('coordinator: delete draft project without scores', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env, missingEnvMessage());
	await runCoordinatorDeleteDraftProject(page, env!);
});
