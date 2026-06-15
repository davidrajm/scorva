import { lazy, Suspense, useEffect } from '@wordpress/element';
import { HashRouter, Route, Routes } from 'react-router-dom';
import { AppShell } from '../shared/AppShell';
import { configureApi } from '../shared/api';
import { RouteFallback, ToastProvider } from '../shared/components';
import { CoordinatorNav } from './CoordinatorNav';
import { NotificationBell } from './components/NotificationBell';
import { CoordinatorWorkspaceTopNav } from '../shared/components/WorkspaceTopNav';

const Dashboard = lazy( () =>
	import( './pages/Dashboard' ).then( ( m ) => ( { default: m.Dashboard } ) )
);
const Registry = lazy( () =>
	import( './pages/Registry' ).then( ( m ) => ( { default: m.Registry } ) )
);
const SessionWizard = lazy( () =>
	import( './pages/SessionWizard' ).then( ( m ) => ( { default: m.SessionWizard } ) )
);
const Reports = lazy( () =>
	import( './pages/Reports' ).then( ( m ) => ( { default: m.Reports } ) )
);
const CloseSession = lazy( () =>
	import( './pages/CloseSession' ).then( ( m ) => ( { default: m.CloseSession } ) )
);
const AuditLog = lazy( () =>
	import( './pages/AuditLog' ).then( ( m ) => ( { default: m.AuditLog } ) )
);
const SessionProgress = lazy( () =>
	import( './pages/SessionProgress' ).then( ( m ) => ( {
		default: m.SessionProgress,
	} ) )
);
const SessionReviewsWizardRedirect = lazy( () =>
	import( './pages/Rubrics' ).then( ( m ) => ( {
		default: m.SessionReviewsWizardRedirect,
	} ) )
);
const PanelReportSettings = lazy( () =>
	import( './pages/PanelReportSettings' ).then( ( m ) => ( {
		default: m.PanelReportSettings,
	} ) )
);
const CoordinatorUnfreezeRequestsPage = lazy( () =>
	import( './pages/UnfreezeRequestsPage' ).then( ( m ) => ( {
		default: m.UnfreezeRequestsPage,
	} ) )
);
function LazyRoute( { label, children } ) {
	return (
		<Suspense fallback={ <RouteFallback label={ label } /> }>
			{ children }
		</Suspense>
	);
}

export function CoordinatorApp() {
	useEffect( () => {
		configureApi();
	}, [] );

	useEffect( () => {
		const data = window.prAppData;
		if ( data?.canAccessCoordinator === false && data?.appHomeUrl ) {
			window.location.replace( data.appHomeUrl );
		}
	}, [] );

	return (
		<ToastProvider>
		<HashRouter>
			<AppShell
				variant="coordinator"
				sidebar={ <CoordinatorNav /> }
				topNav={ <CoordinatorWorkspaceTopNav /> }
				notificationBell={ <NotificationBell /> }
			>
				<Routes>
					<Route
						path="/"
						element={
							<LazyRoute label="Loading dashboard">
								<Dashboard />
							</LazyRoute>
						}
					/>
					<Route
						path="/session/:id/wizard"
						element={
							<LazyRoute label="Loading project wizard">
								<SessionWizard />
							</LazyRoute>
						}
					/>
					<Route
						path="/session/:id/progress"
						element={
							<LazyRoute label="Loading progress">
								<SessionProgress />
							</LazyRoute>
						}
					/>
					<Route
						path="/session/:id/reviews"
						element={
							<LazyRoute label="Loading reviews">
								<SessionReviewsWizardRedirect />
							</LazyRoute>
						}
					/>
					<Route
						path="/session/:id/rubrics"
						element={
							<LazyRoute label="Loading reviews">
								<SessionReviewsWizardRedirect />
							</LazyRoute>
						}
					/>
					<Route
						path="/registry"
						element={
							<LazyRoute label="Loading registry">
								<Registry />
							</LazyRoute>
						}
					/>
					<Route
						path="/session/:id/reports"
						element={
							<LazyRoute label="Loading reports">
								<Reports />
							</LazyRoute>
						}
					/>
					<Route
						path="/session/:id/close"
						element={
							<LazyRoute label="Loading close project">
								<CloseSession />
							</LazyRoute>
						}
					/>
					<Route
						path="/session/:id/audit"
						element={
							<LazyRoute label="Loading audit log">
								<AuditLog />
							</LazyRoute>
						}
					/>
					<Route
						path="/session/:id/settings/panel-report"
						element={
							<LazyRoute label="Loading panel report settings">
								<PanelReportSettings />
							</LazyRoute>
						}
					/>
					<Route
						path="/unfreeze-requests"
						element={
							<LazyRoute label="Loading unfreeze requests">
								<CoordinatorUnfreezeRequestsPage />
							</LazyRoute>
						}
					/>
				</Routes>
			</AppShell>
		</HashRouter>
		</ToastProvider>
	);
}
