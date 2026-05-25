import { test } from '@playwright/test';
import { loadE2eEnv, missingEnvMessage } from '../helpers/env';
import { runReviewerMarking } from '../helpers/journey-steps';
import { walkthroughStep } from '../helpers/walkthrough';
import {
	readWalkthroughState,
	missingWalkthroughStateMessage,
} from '../helpers/walkthrough-state';

/**
 * Slow, headed walkthrough for reviewers.
 * Requires coordinator walkthrough first (writes tests/e2e/.walkthrough-state.json).
 *
 * Run: npm run walkthrough:reviewer
 */
test.describe.configure({ timeout: 300_000 });

test.beforeAll(() => {
	if (!loadE2eEnv()) {
		test.skip(true, missingEnvMessage());
	}
});

test('reviewer walkthrough — open assignment and save a mark', async ({ page }) => {
	const env = loadE2eEnv();
	test.skip(!env, missingEnvMessage());

	const saved = readWalkthroughState();
	test.skip(!saved, missingWalkthroughStateMessage());

	const onStep = (n: number, total: number, title: string, detail?: string) =>
		test.step(`${n}/${total}: ${title}`, () =>
			walkthroughStep(page, n, total, title, detail)
		);

	await runReviewerMarking(
		page,
		env!,
		{
			projectTitle: saved!.projectTitle,
			studentA: saved!.studentA,
		},
		onStep
	);
});
