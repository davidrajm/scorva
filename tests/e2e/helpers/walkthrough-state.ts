import fs from 'fs';
import path from 'path';

export type WalkthroughJourneyState = {
	runId: number;
	sessionId: string;
	projectTitle: string;
	panelName: string;
	reviewerDisplayName: string;
	studentA: { regNo: string; name: string };
	studentB: { regNo: string; name: string };
	studentC: { regNo: string; name: string };
	studentD: { regNo: string; name: string };
	reviewerPortalUrl: string;
	reviewerPortalPassword: string;
};

const STATE_PATH = path.join(__dirname, '..', '.walkthrough-state.json');

export function writeWalkthroughState(state: WalkthroughJourneyState): void {
	fs.writeFileSync(STATE_PATH, JSON.stringify(state, null, 2) + '\n', 'utf8');
}

export function readWalkthroughState(): WalkthroughJourneyState | null {
	if (!fs.existsSync(STATE_PATH)) {
		return null;
	}
	try {
		return JSON.parse(fs.readFileSync(STATE_PATH, 'utf8')) as WalkthroughJourneyState;
	} catch {
		return null;
	}
}

export function missingWalkthroughStateMessage(): string {
	return [
		'Reviewer walkthrough needs coordinator setup first.',
		'Run: npm run walkthrough:coordinator',
		`(writes ${STATE_PATH})`,
	].join(' ');
}
