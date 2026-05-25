import { useCallback, useEffect, useState } from '@wordpress/element';
import { get, post, put } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import {
	Button,
	ConfirmDialog,
	Notice,
	PageContentSkeleton,
	StatusChip,
} from '../../shared/components';

/**
 * Per-review marking lifecycle: start (open) and pause marking for each round.
 * Shown after panel assignments are complete.
 */
export function ReviewMarkingStep( {
	sessionId,
	sessionStatus,
	onReload,
	onNotice,
	isWizardTerminalStep = false,
} ) {
	const canAssignReviewers = window.prAppData?.canAssignReviewers !== false;
	const [ reviews, setReviews ] = useState( [] );
	const [ reviewerCount, setReviewerCount ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ showInviteConfirm, setShowInviteConfirm ] = useState( false );
	const [ inviteNotice, setInviteNotice ] = useState( null );

	const loadReviews = useCallback( async () => {
		if ( ! sessionId ) {
			return;
		}
		setLoading( true );
		setError( null );
		try {
			const [ reviewsData, reviewersData ] = await Promise.all( [
				get( `/sessions/${ sessionId }/reviews` ),
				canAssignReviewers
					? get( `/sessions/${ sessionId }/reviewers` )
					: Promise.resolve( { reviewers: [] } ),
			] );
			setReviews( reviewsData.reviews ?? [] );
			const emails = new Set();
			( reviewersData.reviewers ?? [] ).forEach( ( reviewer ) => {
				const email = ( reviewer.email ?? '' ).trim().toLowerCase();
				if ( email ) {
					emails.add( email );
				}
			} );
			setReviewerCount( emails.size );
		} catch {
			setError( 'Could not load review rounds.' );
			setReviews( [] );
			setReviewerCount( 0 );
		} finally {
			setLoading( false );
		}
	}, [ sessionId, canAssignReviewers ] );

	useEffect( () => {
		loadReviews();
	}, [ loadReviews ] );

	const refreshAll = async () => {
		await loadReviews();
		await onReload?.();
	};

	const setMarkingActive = async ( review, nextActive ) => {
		setBusy( true );
		try {
			await put( `/sessions/${ sessionId }/reviews/${ review.id }`, {
				marking_active: nextActive,
			} );
			await refreshAll();
		} catch {
			onNotice?.( {
				variant: 'error',
				message: 'Could not update marking status.',
			} );
		} finally {
			setBusy( false );
		}
	};

	if ( loading ) {
		return <PageContentSkeleton showTitle={ false } rows={ 3 } />;
	}

	const projectInactive = sessionStatus === 'draft';
	const projectClosed = sessionStatus === 'closed';
	const canBulkInvite =
		canAssignReviewers &&
		! projectInactive &&
		! projectClosed &&
		reviewerCount > 0;

	const handleBulkInvite = async () => {
		setBusy( true );
		setInviteNotice( null );
		try {
			const result = await post(
				`/sessions/${ sessionId }/invite-reviewers`,
				{}
			);
			setInviteNotice( {
				variant: ( result.failed ?? 0 ) > 0 ? 'warning' : 'success',
				message: `Email all reviewers: ${ result.sent ?? 0 } sent, ${ result.skipped ?? 0 } skipped, ${ result.failed ?? 0 } failed.`,
			} );
			await refreshAll();
		} catch ( err ) {
			setInviteNotice( {
				variant: 'error',
				message: parseApiErrorMessage(
					err,
					'Could not email reviewers.'
				),
			} );
		} finally {
			setBusy( false );
			setShowInviteConfirm( false );
		}
	};

	return (
		<section>
			<h2 className="text-lg font-semibold text-text">Open reviews</h2>
			<p className="mt-1 text-sm text-text-muted">
				Start marking when reviewers should score a round; pause marking to
				stop new entries without deleting data. Each round needs a confirmed
				rubric before it can open.
			</p>

			{ canAssignReviewers ? (
				<div className="mt-4 flex flex-wrap items-center gap-3">
					<Button
						variant="secondary"
						size="sm"
						disabled={ busy || ! canBulkInvite }
						title={
							projectInactive
								? 'Open the project for marking first'
								: projectClosed
									? 'Project is closed'
									: reviewerCount === 0
										? 'Assign panel reviewers first'
										: undefined
						}
						onClick={ () => setShowInviteConfirm( true ) }
					>
						Email all reviewers
					</Button>
					{ reviewerCount > 0 ? (
						<span className="text-xs text-text-muted">
							{ reviewerCount } distinct reviewer
							{ reviewerCount === 1 ? '' : 's' } on this project
						</span>
					) : null }
				</div>
			) : null }

			{ inviteNotice ? (
				<div className="mt-4">
					<Notice
						variant={ inviteNotice.variant }
						onDismiss={ () => setInviteNotice( null ) }
					>
						{ inviteNotice.message }
					</Notice>
				</div>
			) : null }

			<ConfirmDialog
				open={ showInviteConfirm }
				title="Email all reviewers?"
				consequences={ [
					`${ reviewerCount } distinct reviewer email${ reviewerCount === 1 ? '' : 's' } will be contacted.`,
					'New or provisioned accounts receive a temporary password; existing accounts keep their password.',
					'Credentials remain valid until this project is closed.',
				] }
				confirmLabel="Send emails"
				confirmVariant="primary"
				confirmDisabled={ busy }
				onConfirm={ handleBulkInvite }
				onCancel={ () => setShowInviteConfirm( false ) }
			/>

			{ projectInactive ? (
				<div className="mt-4 rounded-md border border-border bg-surface-raised p-4">
					<p className="text-sm text-text-muted">
						This project is still a draft. Use <strong className="text-text">Open for marking</strong> at the top of the wizard so reviewers can see assignments, then start each round below.
					</p>
				</div>
			) : null }

			{ projectClosed ? (
				<div className="mt-4">
					<Notice variant="warning">
						This project is closed. Marking cannot be started or paused.
					</Notice>
				</div>
			) : null }

			{ error ? (
				<div className="mt-4">
					<Notice variant="error">{ error }</Notice>
				</div>
			) : null }

			{ reviews.length === 0 ? (
				<p className="mt-4 text-sm text-warning">
					No review rounds found. Add rounds on the Reviews & rubrics step first.
				</p>
			) : (
				<ul className="mt-6 space-y-4">
					{ reviews.map( ( review ) => {
						const marksLocked = Boolean( review.coordinator_marks_locked );
						const markingOn = marksLocked
							? false
							: Boolean( review.marking_active );
						const rubricConfirmed = review.status === 'confirmed';
						const canStart =
							! projectClosed &&
							! projectInactive &&
							! marksLocked &&
							rubricConfirmed &&
							! markingOn;
						const canPause =
							! projectClosed &&
							! marksLocked &&
							markingOn;

						return (
							<li
								key={ review.id }
								className="rounded-lg border border-border bg-surface-raised p-4 shadow-sm"
							>
								<div className="flex flex-wrap items-start justify-between gap-3">
									<div>
										<h3 className="text-base font-semibold text-text">
											{ review.label }
										</h3>
										<div className="mt-2 flex flex-wrap items-center gap-2">
											<StatusChip variant={ review.status } />
											{ marksLocked ? (
												<StatusChip
													variant="confirmed"
													label="Marks locked"
												/>
											) : markingOn ? (
												<StatusChip
													variant="active"
													label="Marking open"
												/>
											) : (
												<StatusChip
													variant="draft"
													label="Marking paused"
												/>
											) }
										</div>
									</div>
									<div className="flex flex-wrap gap-2">
										{ marksLocked ? (
											<span className="text-xs text-text-muted">
												Frozen on Reports — unlock there to change marking.
											</span>
										) : (
											<>
												<Button
													variant="primary"
													size="sm"
													disabled={ busy || ! canStart }
													title={
														! rubricConfirmed
															? 'Confirm the rubric on Reviews & rubrics first'
															: projectInactive
																? 'Open the project for marking first'
																: undefined
													}
													onClick={ () =>
														setMarkingActive( review, true )
													}
												>
													Start marking
												</Button>
												<Button
													variant="secondary"
													size="sm"
													disabled={ busy || ! canPause }
													onClick={ () =>
														setMarkingActive( review, false )
													}
												>
													Pause marking
												</Button>
											</>
										) }
									</div>
								</div>
								{ ! rubricConfirmed ? (
									<p className="mt-3 text-xs text-text-muted">
										Confirm the rubric on Reviews & rubrics before starting
										this round.
									</p>
								) : markingOn ? (
									<p className="mt-3 text-xs text-text-muted">
										Reviewers can enter marks for this round while marking is
										open and the project is active.
									</p>
								) : (
									<p className="mt-3 text-xs text-text-muted">
										Marking is paused. Reviewers cannot save new marks until
										you start marking again.
									</p>
								) }
							</li>
						);
					} ) }
				</ul>
			) }

			{ isWizardTerminalStep ? (
				<p className="mt-8 text-sm text-text-muted">
					Setup is complete for this project. Return to the dashboard or use
					Progress and Reports as marking proceeds.
				</p>
			) : null }
		</section>
	);
}
