import { NavLink } from 'react-router-dom';
import { CoordinatorWorkspaceLink } from '../shared/components/WorkspaceTopNav';

const linkClass = ( { isActive } ) =>
	[
		'rounded-md px-3 py-2 text-sm font-medium',
		isActive
			? 'bg-chip-active-bg text-primary'
			: 'text-text-muted hover:bg-surface-raised hover:text-text',
	].join( ' ' );

export function ReviewerNav() {
	return (
		<nav aria-label="Reviewer" className="pr-topbar-nav">
			<ul className="flex items-center gap-1">
				<li>
					<NavLink to="/" end className={ linkClass }>
						Assignments
					</NavLink>
				</li>
				{ window.prAppData?.canAccessCoordinator ? (
					<li>
						<CoordinatorWorkspaceLink />
					</li>
				) : null }
			</ul>
		</nav>
	);
}
