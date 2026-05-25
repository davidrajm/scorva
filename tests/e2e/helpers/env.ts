export type E2eEnv = {
	baseUrl: string;
	coordUser: string;
	coordPass: string;
	reviewerUser: string;
	reviewerPass: string;
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

	if (!coordUser || !coordPass || !reviewerUser || !reviewerPass || !reviewerEmail) {
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
		'PR_E2E_REVIEWER_USER, PR_E2E_REVIEWER_PASS, and PR_E2E_REVIEWER_EMAIL',
		'(WordPress accounts with project_reviews_coordinator / project_reviews_reviewer roles).',
		'Optional: PR_E2E_BASE_URL (default http://localhost:10008).',
	].join(' ');
}
