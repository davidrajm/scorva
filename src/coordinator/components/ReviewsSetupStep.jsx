import { Button } from '../../shared/components';
import { RubricsPanel } from './RubricsPanel';
import { ReviewRoundsStep } from './ReviewRoundsStep';

/**
 * Wizard step: review round CRUD + rubric criteria, weights, and flagged marks.
 * Per-round start/pause marking lives on the Open reviews step after assignments.
 */
export function ReviewsSetupStep( {
	sessionId,
	onReload,
	onNotice,
	canAdvanceToAssignments,
	onContinue,
	rubricsReloadDependency,
} ) {
	return (
		<section className="space-y-10">
			<div>
				<h2 className="text-lg font-semibold text-text">Reviews & rubrics</h2>
				<p className="mt-1 text-sm text-text-muted">
					Create and order review rounds, then define and confirm rubric
					criteria and weights. Open or pause marking for each round on the
					final step after panel assignments.
				</p>
				<div className="mt-6">
					<ReviewRoundsStep
						sessionId={ sessionId }
						onReload={ onReload }
						onNotice={ onNotice }
						showContinueButton={ false }
						showMarkingControls={ false }
						suppressIntro
					/>
				</div>
			</div>

			<div className="border-t border-border pt-10">
				<h3 className="text-lg font-semibold text-text">
					Rubric criteria & weights
				</h3>
				<p className="mt-1 text-sm text-text-muted">
					Confirm each rubric before opening that round for marking. Adjust
					weight ratios when combining rounds into overall scores.
				</p>
				<div className="mt-6">
					<RubricsPanel
						sessionId={ sessionId }
						compact
						hideRoundActions
						reloadDependency={ rubricsReloadDependency }
					/>
				</div>
			</div>

			<div className="flex justify-end border-t border-border pt-6">
				<Button
					variant="primary"
					onClick={ onContinue }
					disabled={ ! canAdvanceToAssignments }
					title={
						! canAdvanceToAssignments
							? 'Add rubric criteria for every review round first'
							: undefined
					}
				>
					Continue to Panel assignments
				</Button>
			</div>
		</section>
	);
}
