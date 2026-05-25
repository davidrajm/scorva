import { useCallback, useEffect, useState } from '@wordpress/element';
import { del, get, post, put } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import {
	Button,
	ConfirmDialog,
	EmptyState,
	FlaggedMarkChip,
	Notice,
	PageContentSkeleton,
} from '../../shared/components';
import { ReviewRubricBlock } from './ReviewRubricBlock';
import { WeightConfiguration } from './WeightConfiguration';

export function RubricsPanel( {
	sessionId,
	compact = false,
	hideRoundActions = false,
	reloadDependency,
} ) {
	const [ reviews, setReviews ] = useState( [] );
	const [ weights, setWeights ] = useState( {
		review_weights: [],
		reviewer_weights: [],
		has_marks: false,
	} );
	const [ flaggedMarks, setFlaggedMarks ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ deleteTarget, setDeleteTarget ] = useState( null );
	const [ deletePhrase, setDeletePhrase ] = useState( '' );

	const loadRubrics = useCallback( async () => {
		if ( ! sessionId ) {
			return;
		}
		setLoading( true );
		setError( null );
		try {
			const [ reviewData, weightData ] = await Promise.all( [
				get( `/sessions/${ sessionId }/reviews` ),
				get( `/sessions/${ sessionId }/weights` ),
			] );
			const nextReviews = reviewData.reviews ?? [];
			setReviews( nextReviews );
			setWeights( weightData );

			const marksLists = await Promise.all(
				nextReviews.map( ( review ) =>
					get(
						`/sessions/${ sessionId }/reviews/${ review.id }/marks`
					).then( ( data ) => ( {
						reviewId: review.id,
						reviewLabel: review.label,
						marks: data.marks ?? [],
					} ) )
				)
			);
			const flagged = marksLists.flatMap( ( entry ) =>
				entry.marks
					.filter( ( mark ) => mark.flagged )
					.map( ( mark ) => ( {
						...mark,
						review_id: entry.reviewId,
						review_label: entry.reviewLabel,
					} ) )
			);
			setFlaggedMarks( flagged );
		} catch {
			setReviews( [] );
			setError( 'Could not load rubrics for this project.' );
		} finally {
			setLoading( false );
		}
	}, [ sessionId ] );

	useEffect( () => {
		loadRubrics();
	}, [ loadRubrics, reloadDependency ] );

	const ensureReview = async () => {
		if ( reviews.length > 0 ) {
			return reviews;
		}
		await post( `/sessions/${ sessionId }/reviews`, {
			label: 'Review 1',
			criteria: [
				{ label: 'Criterion 1', max_marks: 10, weight: 1 },
			],
		} );
		await loadRubrics();
	};

	const handleCreateReview = async () => {
		setBusy( true );
		try {
			await post( `/sessions/${ sessionId }/reviews`, {
				label: `Review ${ reviews.length + 1 }`,
			} );
			await loadRubrics();
		} catch {
			setError( 'Could not create review round.' );
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
		setError( null );
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
			await loadRubrics();
		} catch ( err ) {
			setError(
				parseApiErrorMessage(
					err,
					'Could not remove review round.'
				)
			);
		} finally {
			setBusy( false );
		}
	};

	const handleSaveWeights = async ( payload ) => {
		setBusy( true );
		setError( null );
		try {
			await put( `/sessions/${ sessionId }/weights`, payload );
			await loadRubrics();
		} catch {
			setError( 'Could not save weights.' );
		} finally {
			setBusy( false );
		}
	};

	if ( loading ) {
		return <PageContentSkeleton rows={ 3 } />;
	}

	return (
		<div className="space-y-6">
			{ ! compact ? (
				<div className="flex justify-end">
					<Button
						variant="secondary"
						disabled={ busy }
						onClick={ handleCreateReview }
					>
						Add review round
					</Button>
				</div>
			) : null }

			{ error ? <Notice variant="error">{ error }</Notice> : null }

			{ flaggedMarks.length > 0 ? (
				<section className="rounded-lg border border-border bg-surface p-4 shadow-sm">
					<h3 className="mb-3 text-base font-semibold text-text">
						Flagged marks
					</h3>
					<ul className="space-y-2 text-sm">
						{ flaggedMarks.map( ( mark ) => (
							<li
								key={ `flagged-${ mark.id }` }
								className="flex flex-wrap items-center gap-2"
							>
								<span>
									{ mark.review_label } · Student{ ' ' }
									{ mark.student_id } · Reviewer{ ' ' }
									{ mark.reviewer_user_id }
								</span>
								<FlaggedMarkChip />
							</li>
						) ) }
					</ul>
				</section>
			) : null }

			{ reviews.length === 0 ? (
				<EmptyState
					title="No review rounds yet"
					description={
						compact
							? 'Use Add review round at the top of Reviews & rubrics, then define criteria here.'
							: 'Create Review 1 to start defining criteria.'
					}
					action={
						compact ? null : (
							<Button
								disabled={ busy }
								onClick={ async () => {
									setBusy( true );
									try {
										await ensureReview();
									} finally {
										setBusy( false );
									}
								} }
							>
								Create Review 1
							</Button>
						)
					}
				/>
			) : (
				<>
					{ reviews.map( ( review ) => {
						const onlyRound = reviews.length <= 1;
						const canOfferDelete = ! onlyRound;

						return (
							<div
								key={ review.id }
								className="rounded-lg border border-border bg-surface shadow-sm"
							>
								{ ! hideRoundActions ? (
									canOfferDelete ? (
										<div className="flex justify-end border-b border-border px-4 py-2">
											<Button
												size="sm"
												variant="secondary"
												disabled={ busy }
												title={
													review.has_entered_scores
														? 'Removes this round and all entered scores (confirmation required)'
														: undefined
												}
												onClick={ () => {
													setDeletePhrase( '' );
													setDeleteTarget( review );
												} }
											>
												Remove review round
											</Button>
										</div>
									) : (
										<div className="flex justify-end border-b border-border px-4 py-2">
											<span className="text-xs text-text-muted">
												This project must keep at least one review round.
											</span>
										</div>
									)
								) : null }
								<div className="p-4">
									<ReviewRubricBlock
										sessionId={ sessionId }
										review={ review }
										busy={ busy }
										onBusyChange={ setBusy }
										onUpdated={ loadRubrics }
									/>
								</div>
							</div>
						);
					} ) }

					<WeightConfiguration
						reviewWeights={ ( weights.review_weights ?? [] ).map(
							( row ) => ( {
								...row,
								weight: String( row.weight ?? 1 ),
							} )
						) }
						reviewerWeights={ (
							weights.reviewer_weights ?? []
						).map( ( row ) => ( {
							...row,
							weight: String( row.weight ?? 1 ),
						} ) ) }
						hasMarks={ weights.has_marks }
						busy={ busy }
						onSave={ handleSaveWeights }
					/>
				</>
			) }

			<ConfirmDialog
				open={
					deleteTarget != null &&
					deleteTarget.has_entered_scores !== true
				}
				title={
					deleteTarget
						? `Remove ${ deleteTarget.label }?`
						: 'Remove review round?'
				}
				consequences={ [
					'This review round, its rubric criteria, weights, and per-round assignments will be deleted.',
				] }
				confirmLabel="Remove review round"
				confirmVariant="destructive"
				onConfirm={ confirmDeleteReview }
				onCancel={ closeDeleteDialog }
				confirmDisabled={ busy }
			/>

			<ConfirmDialog
				open={
					deleteTarget != null &&
					deleteTarget.has_entered_scores === true
				}
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
				confirmDisabled={
					busy ||
					deletePhrase.trim() !==
						String( deleteTarget?.label ?? '' ).trim()
				}
			>
				<div className="space-y-2 text-sm text-text-muted">
					<p>
						Type the exact review round name{ ' ' }
						<strong className="text-text">{ deleteTarget?.label }</strong>
						{ ' ' }
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
		</div>
	);
}
