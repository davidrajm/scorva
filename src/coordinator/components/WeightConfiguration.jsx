import { useEffect, useState } from '@wordpress/element';
import { Card, Notice } from '../../shared/components';

export function WeightConfiguration( {
	reviewWeights = [],
	reviewerWeights = [],
	hasMarks = false,
	onSave,
	busy = false,
} ) {
	const [ reviewRows, setReviewRows ] = useState( reviewWeights );
	const [ reviewerRows, setReviewerRows ] = useState( reviewerWeights );

	useEffect( () => {
		setReviewRows( reviewWeights );
		setReviewerRows( reviewerWeights );
	}, [ reviewWeights, reviewerWeights ] );

	const handleSave = () => {
		onSave( {
			review_weights: reviewRows.map( ( row ) => ( {
				review_id: row.review_id,
				weight: parseFloat( row.weight ) || 1,
			} ) ),
			reviewer_weights: reviewerRows.map( ( row ) => ( {
				review_id: row.review_id,
				reviewer_user_id: row.reviewer_user_id,
				weight: parseFloat( row.weight ) || 1,
			} ) ),
		} );
	};

	return (
		<Card className="space-y-4">
			<h3 className="text-base font-semibold text-text">Weights</h3>
			{ hasMarks ? (
				<Notice variant="warning">
					Marks already exist. Combined scores will recalculate on the
					next read when weights change.
				</Notice>
			) : null }

			<div>
				<h4 className="mb-2 text-sm font-medium text-text-muted">
					Review weights
				</h4>
				<ul className="space-y-2">
					{ reviewRows.map( ( row ) => (
						<li
							key={ `review-weight-${ row.review_id }` }
							className="flex items-center gap-3 text-sm"
						>
							<span className="min-w-[8rem]">{ row.label }</span>
							<input
								type="text"
								inputMode="decimal"
								className="w-24 rounded-md border border-border px-2 py-1.5"
								value={ row.weight }
								disabled={ busy }
								onChange={ ( event ) =>
									setReviewRows( ( current ) =>
										current.map( ( item ) =>
											item.review_id === row.review_id
												? {
														...item,
														weight:
															event.target
																.value,
												  }
												: item
										)
									)
								}
							/>
						</li>
					) ) }
				</ul>
			</div>

			{ reviewerRows.length > 0 ? (
				<div>
					<h4 className="mb-2 text-sm font-medium text-text-muted">
						Reviewer weights
					</h4>
					<ul className="space-y-2">
						{ reviewerRows.map( ( row ) => (
							<li
								key={ `reviewer-weight-${ row.review_id }-${ row.reviewer_user_id }` }
								className="flex items-center gap-3 text-sm"
							>
								<span className="min-w-[8rem]">
									Review { row.review_id } · User{ ' ' }
									{ row.reviewer_user_id }
								</span>
								<input
									type="text"
									inputMode="decimal"
									className="w-24 rounded-md border border-border px-2 py-1.5"
									value={ row.weight }
									disabled={ busy }
									onChange={ ( event ) =>
										setReviewerRows( ( current ) =>
											current.map( ( item ) =>
												item.review_id ===
													row.review_id &&
												item.reviewer_user_id ===
													row.reviewer_user_id
													? {
															...item,
															weight:
																event.target
																	.value,
													  }
													: item
											)
										)
									}
								/>
							</li>
						) ) }
					</ul>
				</div>
			) : null }

			<button
				type="button"
				className="inline-flex items-center justify-center rounded-md border border-border bg-surface-raised px-4 py-2 text-sm font-medium text-primary hover:bg-surface disabled:opacity-50"
				disabled={ busy }
				onClick={ handleSave }
			>
				Save weights
			</button>
		</Card>
	);
}
