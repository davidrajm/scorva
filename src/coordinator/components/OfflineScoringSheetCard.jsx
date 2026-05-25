import { useEffect, useMemo, useState } from '@wordpress/element';
import { getBlob } from '../../shared/api';
import { Button, Card } from '../../shared/components';

export function OfflineScoringSheetCard( {
	sessionId,
	reviews = [],
	reviewId: controlledReviewId = '',
	onReviewIdChange,
	hideReviewSelector = false,
} ) {
	const [ internalReviewId, setInternalReviewId ] = useState( '' );
	const [ downloading, setDownloading ] = useState( false );
	const [ error, setError ] = useState( '' );

	const confirmedReviews = useMemo(
		() => reviews.filter( ( review ) => review.status === 'confirmed' ),
		[ reviews ]
	);

	const reviewId = onReviewIdChange ? controlledReviewId : internalReviewId;
	const setReviewId = onReviewIdChange || setInternalReviewId;

	useEffect( () => {
		if ( onReviewIdChange ) {
			return;
		}
		if ( confirmedReviews.length === 0 ) {
			setInternalReviewId( '' );
			return;
		}
		setInternalReviewId( ( prev ) =>
			prev &&
			confirmedReviews.some( ( r ) => String( r.id ) === String( prev ) )
				? prev
				: String( confirmedReviews[ 0 ].id )
		);
	}, [ confirmedReviews, onReviewIdChange ] );

	const reviewRequired = ! reviewId;
	const noConfirmedReviews = confirmedReviews.length === 0;

	const downloadPdf = async () => {
		if ( reviewRequired || noConfirmedReviews || ! sessionId ) {
			return;
		}

		setDownloading( true );
		setError( '' );
		try {
			const response = await getBlob(
				`/sessions/${ sessionId }/reviews/${ reviewId }/offline-scoring-sheet/pdf`
			);
			const blob = await response.blob();
			const disposition = response.headers.get( 'Content-Disposition' ) || '';
			const match = disposition.match( /filename="([^"]+)"/ );
			const filename = match
				? match[ 1 ]
				: `offline-scoring-sheet-review-${ reviewId }.pdf`;
			const link = document.createElement( 'a' );
			link.href = URL.createObjectURL( blob );
			link.download = filename;
			link.click();
			URL.revokeObjectURL( link.href );
		} catch ( err ) {
			setError( err?.message || 'Download failed. Please try again.' );
		} finally {
			setDownloading( false );
		}
	};

	return (
		<Card className="flex flex-col gap-4">
			<div>
				<h3 className="text-base font-semibold text-text">
					Offline scoring sheet (PDF)
				</h3>
				<p className="mt-1 text-sm text-text-muted">
					Institutional Review Report layout with blank reviewer score
					cells for handwriting. One PDF per review round, with a page
					break between panels.
				</p>
			</div>
			{ noConfirmedReviews ? (
				<p className="text-sm text-text-muted">
					Confirm a review rubric on Reviews & rubrics in the setup wizard
					before downloading offline scoring sheets.
				</p>
			) : hideReviewSelector ? (
				<p className="text-sm text-text-muted">
					Uses the review round selected above.
				</p>
			) : (
				<label className="block text-sm">
					<span className="mb-1 block font-medium text-text">
						Review round
					</span>
					<select
						className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text"
						value={ reviewId }
						onChange={ ( e ) => setReviewId( e.target.value ) }
						disabled={ downloading }
					>
						{ confirmedReviews.map( ( review ) => (
							<option key={ review.id } value={ review.id }>
								{ review.label || `Review ${ review.id }` }
							</option>
						) ) }
					</select>
				</label>
			) }
			{ error ? (
				<p className="text-sm text-danger" role="alert">
					{ error }
				</p>
			) : null }
			<div className="flex flex-wrap gap-2">
				<Button
					variant="secondary"
					disabled={ downloading || noConfirmedReviews || reviewRequired }
					onClick={ downloadPdf }
				>
					{ downloading ? 'Downloading…' : 'Download PDF' }
				</Button>
			</div>
		</Card>
	);
}
