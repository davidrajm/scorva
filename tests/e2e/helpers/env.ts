export type E2eEnv = {
	baseUrl: string;
	coordUser: string;
	coordPass: string;
	// Reviewer WP credentials are optional — reviewer login now uses the
	// token portal link captured during coordinator setup, not wp-login.php.
	reviewerUser: string;
	reviewerPass: string;
	// Email used when adding the reviewer to the panel in the wizard.
	reviewerEmail: string;
};

export function loadE2eEnv(): E2eEnv | null {
	const coordUser = process.env.PR_E2E_COORD_USER?.trim() ?? '';
	const coordPass = process.env.PR_E2E_COORD_PASS ?? '';
	const reviewerUser = process.env.PR_E2E_REVIEWER_USER?.trim() ?? '';
	const reviewerPass = process.env.PR_E2E_REVIEWER_PASS ?? '';
	const reviewerEmail =
		process.env.PR_E2E_REVIEWER_EMAIL?.trim() ??
		(reviewerUser.includes('@') ? reviewerUser : '');

	// Coordinator credentials and reviewer email are required.
	// Reviewer WP credentials are optional (portal login uses the token link).
	if (!coordUser || !coordPass || !reviewerEmail) {
		return null;
	}

	return {
		baseUrl:
			process.env.PR_E2E_BASE_URL?.replace(/\/$/, '') ||
			'http://localhost:10008',
		coordUser,
		coordPass,
		reviewerUser,
		reviewerPass,
		reviewerEmail,
	};
}

export function missingEnvMessage(): string {
	return [
		'UI E2E skipped: set PR_E2E_COORD_USER, PR_E2E_COORD_PASS,',
		'and PR_E2E_REVIEWER_EMAIL (email added to the panel in the wizard).',
		'PR_E2E_REVIEWER_USER / PR_E2E_REVIEWER_PASS are optional — reviewer login',
		'uses the token portal link captured during coordinator setup.',
		'Optional: PR_E2E_BASE_URL (default http://localhost:10008).',
	].join(' ');
}
