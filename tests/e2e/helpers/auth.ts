import { expect, type Page } from '@playwright/test';

export async function loginWordPress(
	page: Page,
	username: string,
	password: string,
	redirectTo = '/reviews/'
): Promise<void> {
	const redirect = encodeURIComponent(redirectTo);
	await page.goto(`/wp-login.php?redirect_to=${redirect}`);
	const userField = page.locator('#user_login');
	const passField = page.locator('#user_pass');
	await userField.click();
	await userField.fill('');
	await userField.fill(username);
	await passField.click();
	await passField.fill('');
	await passField.fill(password);
	await expect(userField).toHaveValue(username);
	await expect(passField).toHaveValue(password);
	await page.locator('#wp-submit').click();
	await page.waitForURL((url) => !url.pathname.includes('wp-login.php'), {
		timeout: 30_000,
	});
}

export async function gotoCoordinatorHash(
	page: Page,
	hashPath: string
): Promise<void> {
	const path = hashPath.startsWith('/') ? hashPath : `/${hashPath}`;
	await page.goto(`/reviews/#${path}`);
	await page.locator('#pr-root').waitFor({ state: 'visible', timeout: 30_000 });
}

export async function gotoReviewerHash(
	page: Page,
	hashPath = '/'
): Promise<void> {
	const path = hashPath.startsWith('/') ? hashPath : `/${hashPath}`;
	await page.goto(`/reviews/mark/#${path}`);
	await page.locator('#pr-root').waitFor({ state: 'visible', timeout: 30_000 });
}

export async function logoutWordPress(page: Page): Promise<void> {
	await page.goto('/wp-login.php?action=logout');
	const confirm = page.locator('a').filter({ hasText: /log out/i }).first();
	if (await confirm.isVisible().catch(() => false)) {
		await confirm.click();
	}
}
