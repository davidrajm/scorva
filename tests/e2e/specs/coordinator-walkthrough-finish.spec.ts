import { test } from '@playwright/test';
import { loadE2eEnv, missingEnvMessage } from '../helpers/env';
import {
	createJourneyContext,
	runCoordinatorCloseAndDeleteProject,
	runCoordinatorProgressAndReports,
} from '../helpers/journey-steps';
import { walkthroughStep } from '../helpers/walkthrough';
import {
	readWalkthroughState,
	missingWalkthroughStateMessage,
} from '../helpers/walkthrough-state';

/**
 * Coordinator walkthrough part 2: progress, reports, close, delete (run after reviewer).
 *
 * Run: npm run walkthrough:coordinator:finish
 */
test.describe.configure({ timeout: 300_000 });

test.beforeAll(() => {
	if (!loadE2eEnv()) {
		test.skip(true, missingEnvMessage());
	}
});

test('coordinator walkthrough — progress, reports, close, delete', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env, missingEnvMessage());

	const saved = readWalkthroughState();
	test.skip(!saved, missingWalkthroughStateMessage());

	const ctx = createJourneyContext(saved!.runId);
	ctx.sessionId = saved!.sessionId;
	ctx.projectTitle = saved!.projectTitle;
	ctx.panelName = saved!.panelName;
	ctx.reviewerDisplayName = saved!.reviewerDisplayName;
	ctx.studentA = saved!.studentA;
	ctx.studentB = saved!.studentB;
	ctx.studentC = saved!.studentC;
	ctx.studentD = saved!.studentD;
	ctx.reviewerPortalUrl = saved!.reviewerPortalUrl;
	ctx.reviewerPortalPassword = saved!.reviewerPortalPassword;

	const onStep = (n: number, total: number, title: string, detail?: string) =>
		test.step(`${n}/${total}: ${title}`, () =>
			walkthroughStep(page, n, total, title, detail)
		);

	await runCoordinatorProgressAndReports(page, env!, ctx, onStep);
	await runCoordinatorCloseAndDeleteProject(page, env!, ctx, onStep);
});
