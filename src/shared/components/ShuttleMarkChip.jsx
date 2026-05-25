import { StatusChip } from './StatusChip';

const TOOLTIP = 'Coordinator override';

function formatScore( value ) {
	if ( value == null || value === '' ) {
		return '';
	}

	return Number( value ).toLocaleString( undefined, {
		minimumFractionDigits: 0,
		maximumFractionDigits: 2,
	} );
}

export function ShuttleMarkChip( { fromScore, score } ) {
	const fromLabel = formatScore( fromScore );
	const toLabel = formatScore( score );
	const showTransition = fromLabel !== '' && toLabel !== '';

	return (
		<span className="inline-flex flex-wrap items-center gap-1" title={ TOOLTIP }>
			{ showTransition ? (
				<span className="tabular-nums text-xs text-text-muted" aria-hidden="true">
					<span className="line-through">{ fromLabel }</span>
					<span className="px-0.5">→</span>
					<span className="font-medium text-text">{ toLabel }</span>
				</span>
			) : null }
			<StatusChip variant="coordinator_override" label="Coordinator" />
			<span className="sr-only">
				{ showTransition
					? `Coordinator override: score changed from ${ fromLabel } to ${ toLabel }`
					: TOOLTIP }
			</span>
		</span>
	);
}
