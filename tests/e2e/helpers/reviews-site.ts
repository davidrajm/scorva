import { expect, type Page } from '@playwright/test';

export const DEFAULT_APP_DISPLAY_NAME =
	'Scorva: The Review Management System';

export function expectedAppDisplayName(): string {
	return process.env.PR_APP_DISPLAY_NAME || DEFAULT_APP_DISPLAY_NAME;
}

export const PERMALINK_FLUSH_HELP =
	'/reviews/ did not load the review app (got the theme or another page instead). ' +
	'In WP Admin go to Settings → Permalinks → Save Changes (no edits needed), ' +
	'or run: wp rewrite flush --path=/path/to/public. ' +
	`Then open PR_E2E_BASE_URL/reviews/ in the browser and confirm you see "${ DEFAULT_APP_DISPLAY_NAME }" and a Log in button.`;

/**
 * Guest landing at /reviews/ (requires plugin rewrite rules).
 */
export async function gotoGuestReviewsLanding(page: Page): Promise<void> {
	await page.goto('/reviews/', { waitUntil: 'domcontentloaded' });
	await expect(
		page.getByRole('heading', { name: expectedAppDisplayName() }),
		PERMALINK_FLUSH_HELP
	).toBeVisible({ timeout: 20_000 });
	await expect(page.getByRole('button', { name: 'Log in' })).toBeVisible();
}
