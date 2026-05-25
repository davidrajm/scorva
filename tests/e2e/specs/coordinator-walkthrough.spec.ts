import { test } from '@playwright/test';
import { loadE2eEnv, missingEnvMessage } from '../helpers/env';
import {
	createJourneyContext,
	runCoordinatorSetup,
	runCoordinatorProgressAndReports,
	toWalkthroughState,
} from '../helpers/journey-steps';
import { walkthroughStep } from '../helpers/walkthrough';
import { writeWalkthroughState } from '../helpers/walkthrough-state';

/**
 * Slow, headed walkthrough for coordinators.
 * Each step shows an on-screen banner and pauses (default 3.5s; set PR_E2E_WALKTHROUGH_PAUSE_MS).
 *
 * Run: npm run walkthrough:coordinator
 * Then: npm run walkthrough:reviewer
 */
test.describe.configure({ mode: 'serial', timeout: 600_000 });

test.beforeAll(() => {
	if (!loadE2eEnv()) {
		test.skip(true, missingEnvMessage());
	}
});

test('coordinator walkthrough — setup project for marking', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env, missingEnvMessage());

	const ctx = createJourneyContext();
	const onStep = (n: number, total: number, title: string, detail?: string) =>
		test.step(`${n}/${total}: ${title}`, () =>
			walkthroughStep(page, n, total, title, detail)
		);

	await runCoordinatorSetup(page, env!, ctx, onStep);
	writeWalkthroughState(toWalkthroughState(ctx));
});
