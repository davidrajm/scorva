import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { HashRouter, Navigate, Route, Routes, useParams } from 'react-router-dom';
import { AppShell } from '../shared/AppShell';
import { ReviewerNav } from './ReviewerNav';
import { configureApi, get } from '../shared/api';
import { Notice, ContentLoadingRegion } from '../shared/components';
import { MarkingGrid } from './components/MarkingGrid';
import { MarkAssignments } from './pages/MarkAssignments';
import { PanelReportPage } from './pages/PanelReportPage';
import { PortalLogin } from './PortalLogin';

function applyPortalIdentity( context ) {
	if ( ! window.prAppData ) {
		window.prAppData = {};
	}
	window.prAppData.portalMode = true;
	window.prAppData.currentUser = {
		id: context?.reviewer?.id ?? 0,
		displayName:
			context?.reviewer?.name?.trim() ||
			context?.reviewer?.email ||
			'Reviewer',
		email: context?.reviewer?.email || '',
	};
}

let portalExpiryMiddlewareInstalled = false;

function installPortalExpiryMiddleware() {
	if ( portalExpiryMiddlewareInstalled ) {
		return;
	}
	portalExpiryMiddlewareInstalled = true;
	apiFetch.use( ( options, next ) =>
		next( options ).catch( ( error ) => {
			if ( error?.code === 'pr_portal_unauthorized' ) {
				// Session expired: reload so the login screen shows again.
				window.location.reload();
			}
			throw error;
		} )
	);
}

function MarkingRoute() {
	const { sessionId, reviewId, panelId } = useParams();
	const session = parseInt( sessionId, 10 );
	const review = parseInt( reviewId, 10 );
	const panel = parseInt( panelId, 10 );

	if ( ! session || ! review || ! panel ) {
		return (
			<>
				<Notice variant="error">This marking link is invalid.</Notice>
				<p className="mt-4">
					<a href="#/" className="text-sm font-medium text-primary underline">
						Back to assignments
					</a>
				</p>
			</>
		);
	}

	return (
		<MarkingGrid sessionId={ session } reviewId={ review } panelId={ panel } />
	);
}

export function ReviewerApp() {
	const isWpUser = Boolean( window.prAppData?.isWpUser );
	const portalToken = window.prAppData?.portalToken || '';
	const [ portalState, setPortalState ] = useState(
		isWpUser ? 'ready' : 'checking'
	);

	useEffect( () => {
		configureApi();
	}, [] );

	useEffect( () => {
		if ( isWpUser ) {
			return;
		}
		get( '/portal/session' )
			.then( ( context ) => {
				applyPortalIdentity( context );
				installPortalExpiryMiddleware();
				setPortalState( 'ready' );
			} )
			.catch( () => setPortalState( 'login' ) );
	}, [ isWpUser ] );

	if ( portalState === 'checking' ) {
		return (
			<div className="flex min-h-screen items-center justify-center bg-surface">
				<ContentLoadingRegion busy label="Checking your session…" minHeight="4rem" />
			</div>
		);
	}

	if ( portalState === 'login' ) {
		return (
			<PortalLogin
				token={ portalToken }
				onSuccess={ ( context ) => {
					applyPortalIdentity( context );
					installPortalExpiryMiddleware();
					setPortalState( 'ready' );
				} }
			/>
		);
	}

	return (
		<HashRouter>
			<AppShell variant="reviewer" topNav={ <ReviewerNav /> }>
				<Routes>
					<Route path="/" element={ <MarkAssignments /> } />
					<Route
						path="/mark/:sessionId/:reviewId/:panelId"
						element={ <MarkingRoute /> }
					/>
					<Route
						path="/panel-report/:sessionId/:reviewId/:panelId"
						element={ <PanelReportPage /> }
					/>
					<Route path="*" element={ <Navigate to="/" replace /> } />
				</Routes>
			</AppShell>
		</HashRouter>
	);
}
