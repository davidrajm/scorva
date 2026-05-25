import { StatusChip } from './StatusChip';

const TOOLTIP = 'Rubric changed after marking';

export function FlaggedMarkChip() {
	return (
		<span className="inline-flex" title={ TOOLTIP }>
			<StatusChip variant="flagged" label="Flagged" />
			<span className="sr-only">{ TOOLTIP }</span>
		</span>
	);
}
