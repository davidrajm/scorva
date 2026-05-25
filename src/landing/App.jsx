import { AppShell } from '../shared/AppShell';
import { getAppDisplayName } from '../shared/appBranding';
import { Button } from '../shared/components/Button';
import { PageHeader } from '../shared/components/PageHeader';

function getAppData() {
	return typeof window !== 'undefined' ? window.prAppData ?? {} : {};
}

function GuestLanding( { loginUrl } ) {
	return (
		<div className="mx-auto w-full max-w-[480px]">
			<PageHeader
				title={ getAppDisplayName() }
				description="Sign in to open the coordinator or marking workspace for your assigned projects."
			/>
			{ loginUrl ? (
				<Button
					variant="primary"
					size="lg"
					className="w-full sm:w-auto"
					onClick={ () => {
						window.location.assign( loginUrl );
					} }
				>
					Log in
				</Button>
			) : null }
		</div>
	);
}

export function LandingApp() {
	const { loginUrl, currentUser } = getAppData();

	return (
		<AppShell variant="landing">
			{ ! currentUser ? <GuestLanding loginUrl={ loginUrl } /> : null }
		</AppShell>
	);
}
