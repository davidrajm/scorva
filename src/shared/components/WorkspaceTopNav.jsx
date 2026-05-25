import { useEffect, useState } from '@wordpress/element';
import { get } from '../api';

const topNavLinkClass =
	'rounded-md px-3 py-2 text-sm font-medium text-text-muted hover:bg-surface-raised hover:text-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

/**
 * Coordinator workspace link for reviewer header — dual-access users only.
 */
export function CoordinatorWorkspaceLink() {
	const coordinatorHomeUrl = window.prAppData?.coordinatorHomeUrl;
	const canAccessCoordinator = window.prAppData?.canAccessCoordinator;

	if ( ! canAccessCoordinator || ! coordinatorHomeUrl ) {
		return null;
	}

	return (
		<a href={ coordinatorHomeUrl } className={ topNavLinkClass }>
			Coordinator
		</a>
	);
}

/**
 * Top bar nav on coordinator shell — Assignments when user has marking assignments.
 */
export function CoordinatorWorkspaceTopNav() {
	const markingHomeUrl = window.prAppData?.markingHomeUrl;
	const canAccessMarking = window.prAppData?.canAccessMarking;
	const [ showAssignments, setShowAssignments ] = useState( false );

	useEffect( () => {
		if ( ! canAccessMarking ) {
			setShowAssignments( false );
			return;
		}

		let cancelled = false;
		get( '/reviewer/assignments' )
			.then( ( res ) => {
				if ( cancelled ) {
					return;
				}
				const assignments = res?.assignments ?? [];
				setShowAssignments( assignments.length > 0 );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setShowAssignments( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ canAccessMarking ] );

	if ( ! showAssignments || ! markingHomeUrl ) {
		return null;
	}

	return (
		<nav aria-label="Workspaces" className="pr-topbar-nav">
			<ul className="flex items-center gap-1">
				<li>
					<a href={ markingHomeUrl } className={ topNavLinkClass }>
						Assignments
					</a>
				</li>
			</ul>
		</nav>
	);
}
