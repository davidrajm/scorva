import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { NavLink, useNavigate } from 'react-router-dom';
import { get } from '../shared/api';
import { Icon } from '../shared/components/NavIcon';

const navLinkClass = ( { isActive } ) =>
	[
		'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
		'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
		isActive
			? 'bg-chip-active-bg text-primary'
			: 'text-text-muted hover:bg-surface hover:text-text',
	].join( ' ' );

function NavBadge( { count } ) {
	if ( count <= 0 ) {
		return null;
	}
	return (
		<span className="ml-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-danger px-1 py-0 text-[11px] font-bold leading-5 text-white">
			{ count > 99 ? '99+' : count }
		</span>
	);
}

export function ReviewerNotificationBell() {
	const isPanelHead = Boolean( window.prAppData?.isPanelHead );
	const navigate = useNavigate();
	const [ pendingCount, setPendingCount ] = useState( 0 );

	useEffect( () => {
		if ( ! isPanelHead ) {
			return undefined;
		}
		const fetchCount = () => {
			get( '/reviewer/unfreeze-requests?status=pending' )
				.then( ( data ) =>
					setPendingCount(
						Array.isArray( data?.requests ) ? data.requests.length : 0
					)
				)
				.catch( () => {} );
		};
		fetchCount();
		const tick = () => {
			if ( document.visibilityState === 'visible' ) {
				fetchCount();
			}
		};
		const id = setInterval( tick, 30_000 );
		document.addEventListener( 'visibilitychange', tick );
		window.addEventListener( 'pr:unfreeze-changed', fetchCount );
		return () => {
			clearInterval( id );
			document.removeEventListener( 'visibilitychange', tick );
			window.removeEventListener( 'pr:unfreeze-changed', fetchCount );
		};
	}, [ isPanelHead ] );

	if ( ! isPanelHead ) {
		return null;
	}

	return (
		<button
			type="button"
			onClick={ () => navigate( '/unfreeze-requests' ) }
			className="relative inline-flex items-center justify-center rounded-md p-2 text-text-muted hover:bg-surface hover:text-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
			aria-label={
				pendingCount > 0
					? `${ pendingCount } pending unfreeze request${ pendingCount === 1 ? '' : 's' }`
					: 'No pending unfreeze requests'
			}
		>
			<Icon name="unlock" className="h-5 w-5" />
			{ pendingCount > 0 ? (
				<span
					aria-hidden="true"
					className="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold leading-none text-white"
				>
					{ pendingCount > 99 ? '99+' : pendingCount }
				</span>
			) : null }
		</button>
	);
}

export function ReviewerSidebarNav() {
	const isPanelHead = Boolean( window.prAppData?.isPanelHead );
	const canAccessCoordinator = Boolean( window.prAppData?.canAccessCoordinator );
	const coordinatorHomeUrl = window.prAppData?.coordinatorHomeUrl;

	const [ pendingCount, setPendingCount ] = useState( 0 );
	const intervalRef = useRef( null );

	const fetchCount = useCallback( () => {
		if ( ! isPanelHead ) {
			return;
		}
		get( '/reviewer/unfreeze-requests?status=pending' )
			.then( ( data ) =>
				setPendingCount( Array.isArray( data?.requests ) ? data.requests.length : 0 )
			)
			.catch( () => {} );
	}, [ isPanelHead ] );

	const refresh = useCallback( () => {
		fetchCount();
	}, [ fetchCount ] );

	useEffect( () => {
		if ( ! isPanelHead ) {
			return undefined;
		}
		fetchCount();

		const tick = () => {
			if ( document.visibilityState === 'visible' ) {
				fetchCount();
			}
		};
		intervalRef.current = setInterval( tick, 30_000 );
		document.addEventListener( 'visibilitychange', tick );
		window.addEventListener( 'pr:unfreeze-changed', fetchCount );

		return () => {
			clearInterval( intervalRef.current );
			document.removeEventListener( 'visibilitychange', tick );
			window.removeEventListener( 'pr:unfreeze-changed', fetchCount );
		};
	}, [ isPanelHead, fetchCount ] );

	return (
		<nav aria-label="Reviewer" className="p-4">
			<ul className="flex flex-col gap-1">
				<li>
					<NavLink to="/" end className={ navLinkClass }>
						<Icon name="clipboard" className="h-5 w-5 shrink-0" />
						<span>Assignments</span>
					</NavLink>
				</li>
				{ isPanelHead ? (
					<li>
						<NavLink to="/unfreeze-requests" className={ navLinkClass }>
							<Icon name="unlock" className="h-5 w-5 shrink-0" />
							<span>Unfreeze Requests</span>
							<NavBadge count={ pendingCount } />
						</NavLink>
					</li>
				) : null }
				{ canAccessCoordinator && coordinatorHomeUrl ? (
					<>
						<li className="mt-3 border-t border-border pt-3" aria-hidden="true" />
						<li>
							<a
								href={ coordinatorHomeUrl }
								className={ [
									'flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium',
									'text-text-muted hover:bg-surface hover:text-text',
									'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
								].join( ' ' ) }
							>
								<Icon name="dashboard" className="h-5 w-5 shrink-0" />
								<span>Coordinator workspace</span>
								<Icon name="chevron-right" className="ml-auto h-4 w-4 shrink-0 opacity-50" />
							</a>
						</li>
					</>
				) : null }
			</ul>
		</nav>
	);
}
