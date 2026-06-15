import { useCallback, useEffect, useState } from '@wordpress/element';
import { Link } from 'react-router-dom';
import { AssignmentCard } from '../components/AssignmentCard';
import {
	CardGridSkeleton,
	ContentLoadingRegion,
	EmptyState,
	Notice,
	PageHeader,
} from '../../shared/components';
import { useLoadingPhase } from '../../shared/hooks/useLoadingPhase';
import { get } from '../../shared/api';
import { fixByLabel } from '../../shared/markErrors';

const BLOCKED_COPY = {
	session_not_active:
		'This project is not open for marking yet. A coordinator must open it for marking on the project wizard.',
	rubric_not_confirmed:
		'The rubric for this review is not confirmed yet. Marking will open once a coordinator confirms it.',
	marking_inactive:
		'This review round is not open for marking. A coordinator must activate marking on the Reviews wizard step.',
	session_closed: 'This project is closed. Marking is no longer available.',
	coordinator_marks_locked:
		'The coordinator locked marking for this review. You cannot change scores.',
};

export function MarkAssignments() {
	const [ assignments, setAssignments ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ pendingUnfreezeCount, setPendingUnfreezeCount ] = useState( 0 );
	const isPanelHead = Boolean( window.prAppData?.isPanelHead );

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const res = await get( '/reviewer/assignments' );
			setAssignments( res.assignments ?? [] );
		} catch ( err ) {
			setError( err?.message || 'Unable to load assignments.' );
			setAssignments( [] );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		if ( ! isPanelHead ) {
			return;
		}
		get( '/reviewer/unfreeze-requests?status=pending' )
			.then( ( data ) =>
				setPendingUnfreezeCount(
					Array.isArray( data?.requests ) ? data.requests.length : 0
				)
			)
			.catch( () => {} );
	}, [ isPanelHead ] );

	const { showSkeleton } = useLoadingPhase( loading, assignments !== null );
	const list = assignments ?? [];
	const markable = list.filter( ( a ) => a.markable );
	const blocked = list.filter( ( a ) => ! a.markable );

	return (
		<>
			<PageHeader
				title="Your assignments"
				description="Select a project, review round, and panel to see your student list."
			/>

			{ isPanelHead && pendingUnfreezeCount > 0 ? (
				<div className="mb-6" role="status">
					<Notice variant="info">
						<span className="font-medium">
							{ pendingUnfreezeCount } pending unfreeze{ ' ' }
							{ pendingUnfreezeCount === 1 ? 'request' : 'requests' } from
							reviewers on your panels.
						</span>{ ' ' }
						<Link
							to="/unfreeze-requests"
							className="font-medium text-primary underline hover:no-underline"
						>
							Review requests
						</Link>
					</Notice>
				</div>
			) : null }

			{ error ? (
				<div className="mb-4">
					<Notice variant="error">{ error }</Notice>
				</div>
			) : null }

			{ showSkeleton ? (
				<ContentLoadingRegion
					busy
					variant="inline"
					label="Loading assignments"
					className="mt-4"
				>
					<CardGridSkeleton count={ 4 } />
				</ContentLoadingRegion>
			) : null }

			{ ! loading && list.length === 0 ? (
				<EmptyState
					title="No assignments yet"
					description="When a coordinator adds you to a panel, links your account, confirms the rubric, and opens the project for marking, your panels will appear here (including under Unavailable assignments while setup is in progress)."
				/>
			) : null }

			{ markable.length > 0 ? (
				<ul className="mb-8 grid gap-4 md:grid-cols-2">
					{ markable.map( ( a ) => (
						<li key={ `${ a.session_id }-${ a.review_id }-${ a.panel_id }` }>
							<AssignmentCard
								sessionTitle={ a.session_title }
								reviewLabel={ a.review_label }
								panelName={ a.panel_name }
								coReviewers={ a.co_reviewers ?? [] }
								markTo={ `/mark/${ a.session_id }/${ a.review_id }/${ a.panel_id }` }
								panelReportTo={
									a.is_panel_coordinator
										? `/panel-report/${ a.session_id }/${ a.review_id }/${ a.panel_id }`
										: undefined
								}
								frozen={
									Boolean( a.review_frozen ) ||
									Boolean( a.panel_scores_frozen )
								}
							/>
						</li>
					) ) }
				</ul>
			) : null }

			{ blocked.length > 0 ? (
				<section>
					<h2 className="mb-3 text-sm font-semibold text-muted">
						Unavailable assignments
					</h2>
					<ul className="grid gap-3">
						{ blocked.map( ( a ) => (
							<li key={ `blocked-${ a.session_id }-${ a.review_id }-${ a.panel_id }` }>
								<Notice variant="info">
									<p className="font-medium text-text">
										{ a.session_title } — { a.review_label } (
										{ a.panel_name })
									</p>
									<p className="mt-1 text-sm">
										{ BLOCKED_COPY[ a.blocked_reason ] ||
											'Marking is not available for this assignment.' }
									</p>
									{ a.blocked_reason === 'rubric_not_confirmed' ||
									a.blocked_reason === 'marking_inactive' ? (
										<p className="mt-1 text-sm opacity-90">
											{ fixByLabel( 'coordinator' ) }
										</p>
									) : null }
									{ a.blocked_reason === 'session_closed' ? (
										<p className="mt-1 text-sm opacity-90">
											{ fixByLabel( 'admin' ) }
										</p>
									) : null }
								</Notice>
							</li>
						) ) }
					</ul>
				</section>
			) : null }
		</>
	);
}
