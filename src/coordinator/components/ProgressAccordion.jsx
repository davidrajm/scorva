import { StatusChip } from '../../shared/components';

export function summaryMarkStatusChip( summary ) {
	const total = summary?.marks_total ?? 0;
	const completed = summary?.marks_completed ?? 0;
	const inProgress = summary?.marks_in_progress ?? 0;

	if ( total > 0 && completed === total ) {
		return { variant: 'confirmed', label: 'Complete' };
	}
	if ( completed === 0 && inProgress === 0 ) {
		return { variant: 'draft', label: 'Not started' };
	}
	return { variant: 'unlocked', label: 'In progress' };
}

export function MarkStatusCounts( { summary, className = '' } ) {
	return (
		<span
			className={ [
				'text-sm tabular-nums text-muted',
				className,
			].join( ' ' ) }
		>
			Complete: { summary?.marks_completed ?? 0 }
			<span className="mx-1.5" aria-hidden="true">
				·
			</span>
			In progress: { summary?.marks_in_progress ?? 0 }
			<span className="mx-1.5" aria-hidden="true">
				·
			</span>
			Not started: { summary?.marks_not_started ?? 0 }
		</span>
	);
}

export function ProgressAccordion( {
	id,
	open,
	onToggle,
	title,
	meta,
	summary,
	headingLevel = 2,
	children,
} ) {
	const panelId = `progress-accordion-${ id }`;
	const HeadingTag = headingLevel === 3 ? 'h3' : 'h2';
	const chip = summaryMarkStatusChip( summary );
	const titleClass =
		headingLevel === 3
			? 'block text-base font-medium text-text'
			: 'block text-lg font-semibold text-text';

	return (
		<div className="mb-4 rounded-md border border-border">
			<HeadingTag className="m-0">
				<button
					type="button"
					id={ `${ panelId }-trigger` }
					className="flex w-full items-start justify-between gap-3 px-4 py-3 text-left hover:bg-surface-raised focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
					aria-expanded={ open }
					aria-controls={ panelId }
					onClick={ () => onToggle( ! open ) }
				>
					<span className="min-w-0 flex-1">
						<span className={ titleClass }>{ title }</span>
						{ meta ? (
							<span className="mt-1 block text-sm tabular-nums text-muted">
								{ meta }
							</span>
						) : null }
					</span>
					<span className="flex shrink-0 flex-col items-end gap-2 sm:flex-row sm:items-center">
						<StatusChip variant={ chip.variant } label={ chip.label } />
						<span className="text-muted" aria-hidden="true">
							{ open ? '▾' : '▸' }
						</span>
					</span>
				</button>
			</HeadingTag>
			{ open ? (
				<div
					id={ panelId }
					role="region"
					aria-labelledby={ `${ panelId }-trigger` }
					className="border-t border-border px-4 py-4"
				>
					{ children }
				</div>
			) : null }
		</div>
	);
}
