import fs from 'fs';
import path from 'path';
import { defineConfig, devices } from '@playwright/test';

/** Load tests/e2e/.env.local so npm scripts work without `source load-env.sh`. */
function loadE2eEnvFile(): void {
	const envPath = path.join(__dirname, 'tests/e2e/.env.local');
	if (!fs.existsSync(envPath)) {
		return;
	}
	for (const rawLine of fs.readFileSync(envPath, 'utf8').split('\n')) {
		const line = rawLine.replace(/#.*$/, '').trim();
		if (!line || !line.includes('=')) {
			continue;
		}
		const eq = line.indexOf('=');
		const key = line.slice(0, eq).trim();
		const value = line.slice(eq + 1).trim();
		if (key && process.env[key] === undefined) {
			process.env[key] = value;
		}
	}
}

loadE2eEnvFile();

const baseURL =
	process.env.PR_E2E_BASE_URL?.replace(/\/$/, '') || 'http://localhost:10008';

const walkthroughSlowMo = Number.parseInt(
	process.env.PR_E2E_WALKTHROUGH_SLOW_MO ?? '800',
	10
);

export default defineConfig({
	testDir: './tests/e2e/specs',
	testMatch: '**/*.spec.ts',
	fullyParallel: false,
	forbidOnly: Boolean(process.env.CI),
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: [['list'], ['html', { open: 'never' }]],
	timeout: 120_000,
	expect: { timeout: 15_000 },
	use: {
		baseURL,
		testIdAttribute: 'data-testid',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
		{
			name: 'walkthrough',
			testMatch: /-walkthrough\.spec\.ts$/,
			use: {
				...devices['Desktop Chrome'],
				launchOptions: {
					slowMo: Number.isFinite(walkthroughSlowMo) ? walkthroughSlowMo : 800,
				},
			},
		},
	],
});
