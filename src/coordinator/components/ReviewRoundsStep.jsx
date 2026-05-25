import { useCallback, useEffect, useState } from '@wordpress/element';
import { del, get, post, put } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import {
	Button,
	ConfirmDialog,
	Notice,
	PageContentSkeleton,
	StatusChip,
} from '../../shared/components';

export function ReviewRoundsStep( {
	sessionId,
	onReload,
	onNotice,
	canAdvanceToAssignments = true,
	onContinue,
	showContinueButton = true,
	suppressIntro = false,
	showMarkingControls = true,
} ) {
	const [ reviews, setReviews ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ editingId, setEditingId ] = useState( null );
	const [ editLabel, setEditLabel ] = useState( '' );
	const [ deleteTarget, setDeleteTarget ] = useState( null );
	const [ deletePhrase, setDeletePhrase ] = useState( '' );

	const loadReviews = useCallback( async () => {
		if ( ! sessionId ) {
			return;
		}
		setLoading( true );
		setError( null );
		try {
			const data = await get( `/sessions/${ sessionId }/reviews` );
			setReviews( data.reviews ?? [] );
		} catch {
			setError( 'Could not load review rounds.' );
			setReviews( [] );
		} finally {
			setLoading( false );
		}
	}, [ sessionId ] );

	useEffect( () => {
		loadReviews();
	}, [ loadReviews ] );

	const refreshAll = async () => {
		await loadReviews();
		await onReload?.();
	};

	const handleAdd = async () => {
		setBusy( true );
		setError( null );
		try {
			await post( `/sessions/${ sessionId }/reviews`, {
				label: `Review ${ reviews.length + 1 }`,
				sort_order: reviews.length,
			} );
			await refreshAll();
		} catch {
			setError( 'Could not create review round.' );
		} finally {
			setBusy( false );
		}
	};

	const startEdit = ( review ) => {
		setEditingId( review.id );
		setEditLabel( review.label );
	};

	const saveLabel = async ( reviewId ) => {
		const label = editLabel.trim();
		setEditingId( null );
		if ( ! label ) {
			return;
		}
		setBusy( true );
		try {
			await put( `/sessions/${ sessionId }/reviews/${ reviewId }`, {
				label,
			} );
			await refreshAll();
		} catch {
			onNotice?.( {
				variant: 'error',
				message: 'Could not rename review round.',
			} );
		} finally {
			setBusy( false );
		}
	};

	const toggleMarkingActive = async ( review, nextActive ) => {
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

	const moveReview = async ( index, direction ) => {
		const targetIndex = index + direction;
		if ( targetIndex < 0 || targetIndex >= reviews.length ) {
			return;
		}
		const current = reviews[ index ];
		const target = reviews[ targetIndex ];
		setBusy( true );
		try {
			await Promise.all( [
				put( `/sessions/${ sessionId }/reviews/${ current.id }`, {
					sort_order: targetIndex,
				} ),
				put( `/sessions/${ sessionId }/reviews/${ target.id }`, {
					sort_order: index,
				} ),
			] );
			await refreshAll();
		} catch {
			onNotice?.( {
				variant: 'error',
				message: 'Could not reorder review rounds.',
			} );
		} finally {
			setBusy( false );
		}
	};

	const closeDeleteDialog = () => {
		setDeleteTarget( null );
		setDeletePhrase( '' );
	};

	const confirmDeleteReview = async () => {
		if ( ! deleteTarget ) {
			return;
		}
		setBusy( true );
		try {
			const payload =
				deleteTarget.has_entered_scores === true
					? { confirm_label: deletePhrase.trim() }
					: undefined;
			await del(
				`/sessions/${ sessionId }/reviews/${ deleteTarget.id }`,
				payload
			);
			closeDeleteDialog();
			await refreshAll();
		} catch ( err ) {
			onNotice?.( {
				variant: 'error',
				message: parseApiErrorMessage(
					err,
					'Could not remove review round.'
				),
			} );
		} finally {
			setBusy( false );
		}
	};

	const destructiveDelete =
		deleteTarget?.has_entered_scores === true;
	const phraseMatchesDelete =
		deletePhrase.trim() === String( deleteTarget?.label ?? '' ).trim();

	if ( loading ) {
		return <PageContentSkeleton showTitle={ false } rows={ 3 } />;
	}

	return (
		<section>
			{ suppressIntro ? null : (
				<>
					<h2 className="text-lg font-semibold text-text">Reviews</h2>
					<p className="mt-1 text-sm text-text-muted">
						Add, rename, reorder, or remove review rounds. Confirm rubrics and
						open marking on the final Open reviews step after panel assignments.
					</p>
				</>
			) }

			{ error ? (
				<div className="mt-4">
					<Notice variant="error">{ error }</Notice>
				</div>
			) : null }

			<div className="mt-4 flex flex-wrap gap-2">
				<Button variant="primary" disabled={ busy } onClick={ handleAdd }>
					Add review round
				</Button>
			</div>

			{ reviews.length === 0 ? (
				<p className="mt-4 text-sm text-warning">
					At least one review round is required.
				</p>
			) : (
				<ul className="mt-4 space-y-3">
					{ reviews.map( ( review, index ) => {
						const onlyRound = reviews.length <= 1;
						const canOfferDelete = ! onlyRound;
						const isEditing = editingId === review.id;
						const marksLocked = Boolean( review.coordinator_marks_locked );
						const markingOn = marksLocked
							? false
							: Boolean( review.marking_active );

						return (
							<li
								key={ review.id }
								className="rounded-md border border-border bg-surface-raised px-3 py-3"
							>
								<div className="flex flex-wrap items-center gap-2 text-sm">
									<div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
										{ isEditing ? (
											<input
												type="text"
												value={ editLabel }
												onChange={ ( e ) =>
													setEditLabel( e.target.value )
												}
												onBlur={ () =>
													saveLabel( review.id )
												}
												onKeyDown={ ( e ) => {
													if ( e.key === 'Enter' ) {
														e.preventDefault();
														saveLabel( review.id );
													}
													if ( e.key === 'Escape' ) {
														setEditingId( null );
													}
												} }
												className="min-w-[12rem] flex-1 rounded-md border border-border bg-surface px-2 py-1"
												autoFocus
											/>
										) : (
											<button
												type="button"
												className="font-medium text-text hover:underline"
												onClick={ () => startEdit( review ) }
											>
												{ review.label }
											</button>
										) }
										<StatusChip variant={ review.status } />
									</div>
									<div className="flex flex-wrap items-center gap-2">
										{ showMarkingControls ? (
											marksLocked ? (
												<>
													<StatusChip
														variant="confirmed"
														label="Marks locked"
													/>
													<span className="text-xs text-text-muted">
														Frozen on Reports — use Unlock
														there to reopen marking.
													</span>
												</>
											) : (
												<label className="flex items-center gap-2 text-text-muted">
													<input
														type="checkbox"
														checked={ markingOn }
														disabled={
															busy ||
															review.status !== 'confirmed'
														}
														onChange={ ( e ) =>
															toggleMarkingActive(
																review,
																e.target.checked
															)
														}
													/>
													<span>
														{ markingOn
															? 'Marking active'
															: 'Marking paused' }
													</span>
												</label>
											)
										) : null }
										<Button
											size="sm"
											variant="secondary"
											disabled={ busy || index === 0 }
											onClick={ () => moveReview( index, -1 ) }
										>
											↑
										</Button>
										<Button
											size="sm"
											variant="secondary"
											disabled={
												busy || index === reviews.length - 1
											}
											onClick={ () => moveReview( index, 1 ) }
										>
											↓
										</Button>
										{ canOfferDelete ? (
											<Button
												size="sm"
												variant="secondary"
												disabled={ busy }
												title={
													onlyRound
														? undefined
														: review.has_entered_scores
															? 'Removes this round and all entered scores'
															: undefined
												}
												onClick={ () => {
													setDeletePhrase( '' );
													setDeleteTarget( review );
												} }
											>
												Remove
											</Button>
										) : (
											<span
												className="text-xs text-text-muted"
												title="Every project keeps at least one review round."
											>
												Cannot remove only round
											</span>
										) }
									</div>
								</div>
								{ showMarkingControls ? (
									review.status !== 'confirmed' ? (
										<p className="mt-2 text-xs text-text-muted">
											Confirm the rubric below before activating marking.
										</p>
									) : (
										<p className="mt-2 text-xs text-text-muted">
											Reviewers can enter marks when the project is
											active, the rubric is confirmed, and marking is
											active for this round.
										</p>
									)
								) : review.status !== 'confirmed' ? (
									<p className="mt-2 text-xs text-text-muted">
										Confirm the rubric below before this round can open on
										the Open reviews step.
									</p>
								) : null }
							</li>
						);
					} ) }
				</ul>
			) }

			{ typeof onContinue === 'function' && showContinueButton ? (
				<div className="mt-6 flex justify-end">
					<Button
						variant="primary"
						onClick={ onContinue }
						disabled={ ! canAdvanceToAssignments || busy }
						title={
							! canAdvanceToAssignments
								? 'Add rubric criteria for every review round before assigning panels'
								: undefined
						}
					>
						Continue to Panel assignments
					</Button>
				</div>
			) : null }

			<ConfirmDialog
				open={ deleteTarget != null && ! destructiveDelete }
				title={
					deleteTarget
						? `Remove ${ deleteTarget.label }?`
						: 'Remove review round?'
				}
				consequences={ [
					'This review round, its rubric criteria, weights, and assignments for this round will be deleted.',
				] }
				confirmLabel="Remove review round"
				confirmVariant="destructive"
				onConfirm={ confirmDeleteReview }
				onCancel={ closeDeleteDialog }
				confirmDisabled={ busy }
			/>

			<ConfirmDialog
				open={ deleteTarget != null && destructiveDelete }
				title={
					deleteTarget
						? `Delete ${ deleteTarget.label } and all scores?`
						: 'Delete review round?'
				}
				consequences={ [
					'All entered marks for this round will be permanently removed.',
					'Panel freezes and unfreeze requests tied to this round will be cleared.',
				] }
				confirmLabel="Delete round and scores"
				confirmVariant="destructive"
				onConfirm={ confirmDeleteReview }
				onCancel={ closeDeleteDialog }
				confirmDisabled={ busy || ! phraseMatchesDelete }
			>
				<div className="space-y-2 text-sm text-text-muted">
					<p>
						Type the exact review round name{' '}
						<strong className="text-text">{ deleteTarget?.label }</strong>{ ' ' }
						to confirm.
					</p>
					<input
						type="text"
						className="w-full rounded-md border border-border bg-surface px-3 py-2 text-text"
						value={ deletePhrase }
						onChange={ ( e ) => setDeletePhrase( e.target.value ) }
						autoComplete="off"
						aria-label="Type review round name to confirm deletion"
					/>
				</div>
			</ConfirmDialog>
		</section>
	);
}
