import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { useNavigate } from 'react-router-dom';
import { get } from '../../shared/api';
import { Icon } from '../../shared/components/NavIcon';

function useUnfreezeSummary() {
	const [ summary, setSummary ] = useState( { reviewer_pending: 0, panel_pending: 0 } );

	const fetchSummary = useCallback( () => {
		get( '/unfreeze-summary' )
			.then( ( data ) => {
				if ( data && typeof data === 'object' ) {
					setSummary( {
						reviewer_pending: Number( data.reviewer_pending ?? 0 ),
						panel_pending: Number( data.panel_pending ?? 0 ),
					} );
				}
			} )
			.catch( () => {} );
	}, [] );

	useEffect( () => {
		fetchSummary();

		const tick = () => {
			if ( document.visibilityState === 'visible' ) {
				fetchSummary();
			}
		};
		const id = setInterval( tick, 30_000 );
		document.addEventListener( 'visibilitychange', tick );
		window.addEventListener( 'pr:unfreeze-changed', fetchSummary );

		return () => {
			clearInterval( id );
			document.removeEventListener( 'visibilitychange', tick );
			window.removeEventListener( 'pr:unfreeze-changed', fetchSummary );
		};
	}, [ fetchSummary ] );

	const total = summary.panel_pending;

	return { total, refresh: fetchSummary };
}

export function NotificationBell() {
	const canManage = Boolean( window.prAppData?.canManageProjects );
	const navigate = useNavigate();
	const { total } = useUnfreezeSummary();

	if ( ! canManage ) {
		return null;
	}

	return (
		<button
			type="button"
			onClick={ () => navigate( '/unfreeze-requests' ) }
			className="relative inline-flex items-center justify-center rounded-md p-2 text-text-muted hover:bg-surface hover:text-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
			aria-label={
				total > 0
					? `${ total } pending unfreeze request${ total === 1 ? '' : 's' }`
					: 'No pending unfreeze requests'
			}
		>
			<Icon name="unlock" className="h-5 w-5" />
			{ total > 0 ? (
				<span
					aria-hidden="true"
					className="absolute -right-0.5 -top-0.5 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold leading-none text-white"
				>
					{ total > 99 ? '99+' : total }
				</span>
			) : null }
		</button>
	);
}
