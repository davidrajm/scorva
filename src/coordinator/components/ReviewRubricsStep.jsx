import { useCallback, useEffect, useState } from '@wordpress/element';
import { get } from '../../shared/api';
import { Button, Notice, PageContentSkeleton } from '../../shared/components';
import { ReviewRubricBlock } from './ReviewRubricBlock';

export function ReviewRubricsStep( {
	sessionId,
	wizardState,
	onNotice,
	onContinue,
} ) {
	const [ reviews, setReviews ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const loadReviews = useCallback( async () => {
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

	const allHaveCriteria =
		reviews.length > 0 &&
		reviews.every( ( review ) => ( review.criteria?.length ?? 0 ) > 0 );
	const canContinue =
		wizardState?.can_advance_to_reviews ?? allHaveCriteria;

	if ( loading ) {
		return <PageContentSkeleton showTitle={ false } rows={ 3 } />;
	}

	return (
		<section>
			<h2 className="text-lg font-semibold text-text">Rubrics</h2>
			<p className="mt-1 text-sm text-text-muted">
				Define and confirm rubric criteria for each review round. Confirm a
				rubric before turning marking on for that round.
			</p>

			{ error ? (
				<div className="mt-4">
					<Notice variant="error">{ error }</Notice>
				</div>
			) : null }

			{ reviews.length === 0 ? (
				<p className="mt-4 text-sm text-warning">No review rounds found.</p>
			) : (
				<ul className="mt-4 space-y-4">
					{ reviews.map( ( review ) => (
						<li
							key={ review.id }
							className="rounded-md border border-border bg-surface px-3 py-4"
						>
							<h3 className="mb-3 text-base font-semibold text-text">
								{ review.label }
							</h3>
							<ReviewRubricBlock
								sessionId={ sessionId }
								review={ review }
								busy={ busy }
								onBusyChange={ setBusy }
								onUpdated={ loadReviews }
								embedded
							/>
						</li>
					) ) }
				</ul>
			) }

			<div className="mt-6 flex justify-end">
				<Button
					variant="primary"
					onClick={ onContinue }
					disabled={ ! canContinue || busy }
					title={
						! canContinue
							? 'Add rubric criteria for every review round first'
							: undefined
					}
				>
					Continue to Reviews
				</Button>
			</div>
		</section>
	);
}
