import type { Page } from '@playwright/test';

const BANNER_ID = 'pr-e2e-walkthrough-banner';

export function walkthroughPauseMs(): number {
	const raw = process.env.PR_E2E_WALKTHROUGH_PAUSE_MS?.trim();
	const parsed = raw ? Number.parseInt(raw, 10) : Number.NaN;
	return Number.isFinite(parsed) && parsed >= 0 ? parsed : 3_500;
}

/**
 * Pause between walkthrough steps: on-screen banner + console label.
 * Set PR_E2E_WALKTHROUGH_PAUSE_MS (default 3500) to hold longer on each step.
 */
export async function walkthroughStep(
	page: Page,
	stepNumber: number,
	totalSteps: number,
	title: string,
	detail?: string
): Promise<void> {
	const label = `[${stepNumber}/${totalSteps}] ${title}`;
	const sub = detail ? ` — ${detail}` : '';
	// eslint-disable-next-line no-console
	console.log(`\n▶ Walkthrough: ${label}${sub}\n`);

	await page.evaluate(
		({ id, heading, body }) => {
			let el = document.getElementById(id);
			if (!el) {
				el = document.createElement('div');
				el.id = id;
				Object.assign(el.style, {
					position: 'fixed',
					left: '50%',
					bottom: '1.25rem',
					transform: 'translateX(-50%)',
					zIndex: '2147483647',
					maxWidth: 'min(36rem, calc(100vw - 2rem))',
					padding: '1rem 1.25rem',
					borderRadius: '0.5rem',
					background: 'rgba(15, 23, 42, 0.94)',
					color: '#f8fafc',
					fontFamily:
						'system-ui, -apple-system, Segoe UI, Roboto, sans-serif',
					fontSize: '1rem',
					lineHeight: '1.4',
					boxShadow: '0 8px 32px rgba(0,0,0,0.35)',
					pointerEvents: 'none',
				});
				document.body.appendChild(el);
			}
			el.innerHTML = `<div style="font-weight:600;font-size:1.05rem">${heading}</div>${
				body
					? `<div style="margin-top:0.35rem;opacity:0.9;font-size:0.92rem">${body}</div>`
					: ''
			}`;
		},
		{
			id: BANNER_ID,
			heading: label,
			body: detail ?? '',
		}
	);

	const pause = walkthroughPauseMs();
	if (pause > 0) {
		await page.waitForTimeout(pause);
	}
}

export async function clearWalkthroughBanner(page: Page): Promise<void> {
	await page.evaluate((id) => {
		document.getElementById(id)?.remove();
	}, BANNER_ID);
}
