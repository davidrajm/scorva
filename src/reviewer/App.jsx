import { useEffect } from '@wordpress/element';
import { HashRouter, Navigate, Route, Routes, useParams } from 'react-router-dom';
import { AppShell } from '../shared/AppShell';
import { ReviewerNav } from './ReviewerNav';
import { configureApi } from '../shared/api';
import { Notice } from '../shared/components';
import { MarkingGrid } from './components/MarkingGrid';
import { MarkAssignments } from './pages/MarkAssignments';
import { PanelReportPage } from './pages/PanelReportPage';

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
	useEffect( () => {
		configureApi();
	}, [] );

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
