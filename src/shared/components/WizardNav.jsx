import { Icon } from './NavIcon.jsx';

const STEPS = [
	{ key: 'students', label: 'Students', icon: 'users' },
	{ key: 'panels', label: 'Panels', icon: 'panel' },
	{ key: 'reviewers', label: 'Reviewers', icon: 'users' },
	{
		key: 'reviews',
		label: 'Reviews & rubrics',
		icon: 'rubrics',
	},
	{
		key: 'assignments',
		label: 'Panel assignments',
		icon: 'clipboard',
	},
	{
		key: 'marking',
		label: 'Open reviews',
		icon: 'calendar',
	},
];

export function WizardNav( {
	currentStep,
	completedSteps = [],
	blockedSteps = {},
	onStepClick,
} ) {
	return (
		<nav aria-label="Project setup steps" className="mb-8 mt-6 border-b border-border">
			<ol className="flex flex-wrap gap-0" role="tablist">
				{ STEPS.map( ( step ) => {
					const isCurrent = currentStep === step.key;
					const isComplete = completedSteps.includes( step.key );
					const blockReason = blockedSteps[ step.key ];
					const isBlocked = Boolean( blockReason );
					const canClick =
						! isBlocked && typeof onStepClick === 'function';

					return (
						<li key={ step.key } role="presentation">
							<button
								type="button"
								role="tab"
								disabled={ ! canClick && ! isCurrent }
								title={ blockReason || undefined }
								aria-selected={ isCurrent }
								onClick={ () => canClick && onStepClick( step.key ) }
								className={ [
									'flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors -mb-px',
									isCurrent
										? 'border-primary text-primary'
										: isComplete
											? 'border-transparent text-text hover:border-border hover:text-text'
											: isBlocked
												? 'cursor-not-allowed border-transparent text-text-muted opacity-60'
												: 'border-transparent text-text-muted hover:border-border hover:text-text',
								].join( ' ' ) }
							>
								<Icon name={ step.icon } className="h-4 w-4 shrink-0" />
								{ step.label }
								{ isBlocked ? (
									<span className="sr-only"> (blocked)</span>
								) : null }
							</button>
						</li>
					);
				} ) }
			</ol>
		</nav>
	);
}
