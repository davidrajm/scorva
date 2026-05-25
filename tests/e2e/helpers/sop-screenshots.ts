import fs from 'fs';
import path from 'path';
import type { Page } from '@playwright/test';
import { SOP_SCREENSHOT_IDS, type SopScreenshotId } from '../sop-screenshot-manifest';

const SOP_ROOT = path.join(process.cwd(), 'docs', 'sop');
const SCREENSHOTS_ROOT = path.join(SOP_ROOT, 'screenshots');

function pad2(n: number): string {
	return String(n).padStart(2, '0');
}

/** Local timestamp folder: YYYY-MM-DD_HHmmss */
export function formatScreenshotRunDirName(date = new Date()): string {
	return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}_${pad2(date.getHours())}${pad2(date.getMinutes())}${pad2(date.getSeconds())}`;
}

/**
 * Typst-relative dir under docs/sop/ (trailing slash), e.g. screenshots/2026-05-21_143022/
 */
export function resolveSopScreenshotRunDir(): string {
	const fromEnv = process.env.PR_SOP_SCREENSHOTS_DIR?.trim();
	if (fromEnv) {
		const normalized = fromEnv.endsWith('/') ? fromEnv : `${fromEnv}/`;
		const abs = path.join(SOP_ROOT, normalized);
		fs.mkdirSync(abs, { recursive: true });
		return normalized;
	}

	const dirName = formatScreenshotRunDirName();
	const typstDir = `screenshots/${dirName}/`;
	fs.mkdirSync(path.join(SOP_ROOT, typstDir), { recursive: true });
	return typstDir;
}

export function sopScreenshotAbsPath(typstDir: string, id: SopScreenshotId): string {
	return path.join(SOP_ROOT, typstDir, `${id}.png`);
}

export async function captureSopScreenshot(
	page: Page,
	typstDir: string,
	id: SopScreenshotId,
	options: { fullPage?: boolean } = {}
): Promise<string> {
	const filePath = sopScreenshotAbsPath(typstDir, id);
	await page.screenshot({
		path: filePath,
		fullPage: options.fullPage ?? true,
	});
	return filePath;
}

export function writeSopScreenshotRunManifest(
	typstDir: string,
	captured: SopScreenshotId[]
): void {
	const absDir = path.join(SOP_ROOT, typstDir);
	const manifest = {
		generated_at: new Date().toISOString(),
		typst_dir: typstDir,
		captured,
		pending: SOP_SCREENSHOT_IDS.filter((id) => !captured.includes(id)),
	};
	fs.writeFileSync(
		path.join(absDir, 'manifest.json'),
		`${JSON.stringify(manifest, null, 2)}\n`,
		'utf8'
	);
}

export function printTypstScreenshotDirInstructions(typstDir: string): void {
	const pending = path.join(SOP_ROOT, typstDir);
	// eslint-disable-next-line no-console
	console.log('\n── SOP screenshots ──────────────────────────────────────────');
	// eslint-disable-next-line no-console
	console.log(`Saved PNGs under: docs/sop/${typstDir}`);
	// eslint-disable-next-line no-console
	console.log(`Absolute path: ${pending}`);
	// eslint-disable-next-line no-console
	console.log('\nUpdate docs/sop/lib/theme.typ:');
	// eslint-disable-next-line no-console
	console.log(`  #let sop-screenshots-dir = "${typstDir}"`);
	// eslint-disable-next-line no-console
	console.log('  #let use-live-screenshots = true');
	// eslint-disable-next-line no-console
	console.log('\nThen: cd docs/sop && typst compile project-reviews-sop.typ\n');
}
