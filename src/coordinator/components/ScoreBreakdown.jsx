import { PageContentSkeleton } from '../../shared/components';

/**
 * Read-only score breakdown (UX-DR16). No editable aggregate fields.
 */
export function ScoreBreakdown( { breakdown, loading } ) {
	if ( loading ) {
		return <PageContentSkeleton showTitle={ false } rows={ 3 } />;
	}

	if ( ! breakdown ) {
		return (
			<p className="text-sm text-muted">
				Select a student to view their score breakdown.
			</p>
		);
	}

	return (
		<div className="space-y-6 rounded-md border border-border bg-surface p-6">
			<div>
				<h3 className="text-sm font-medium text-muted">Combined score</h3>
				<p className="mt-1 text-2xl font-semibold tabular-nums text-text">
					{ formatScore( breakdown.combined_score ) }
				</p>
				<p className="mt-1 text-xs text-muted">
					Weighted average across confirmed review rounds. Computed on the
					server and cannot be edited here.
				</p>
			</div>

			{ ( breakdown.reviews || [] ).map( ( review ) => (
				<section
					key={ review.review_id }
					className="border-t border-border pt-4"
				>
					<h4 className="font-medium text-text">{ review.label }</h4>
					<p className="text-sm text-muted">
						Review score (weighted average of reviewer totals):{' '}
						<span className="tabular-nums font-medium text-text">
							{ formatScore( review.review_score ) }
						</span>
						{ review.weight != null ? (
							<span className="ml-2 text-muted">
								(review weight { review.weight })
							</span>
						) : null }
					</p>

					{ review.reviewers?.length > 0 ? (
						<ul className="mt-3 space-y-2 text-sm">
							{ review.reviewers.map( ( r ) => (
								<li
									key={ r.reviewer_user_id }
									className="flex justify-between gap-4 tabular-nums"
								>
									<span className="text-muted">
										Reviewer { r.reviewer_user_id } (sum of marks)
									</span>
									<span className="text-text">
										{ formatScore( r.reviewer_total ) }
									</span>
								</li>
							) ) }
						</ul>
					) : (
						<p className="mt-2 text-sm text-muted">
							No submitted marks for this review yet.
						</p>
					) }
				</section>
			) ) }
		</div>
	);
}

function formatScore( value ) {
	if ( value == null || Number.isNaN( Number( value ) ) ) {
		return '—';
	}

	return Number( value ).toFixed( 2 );
}
