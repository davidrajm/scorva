import { Icon } from './NavIcon';

const VARIANT_CONFIG = {
	draft: {
		label: 'Draft',
		className: 'bg-chip-draft-bg text-chip-draft',
	},
	active: {
		label: 'Active',
		className: 'bg-chip-active-bg text-chip-active',
	},
	closed: {
		label: 'Closed',
		className: 'bg-chip-closed-bg text-chip-closed',
	},
	confirmed: {
		label: 'Confirmed',
		className: 'bg-chip-confirmed-bg text-chip-confirmed',
	},
	unlocked: {
		label: 'Unlocked',
		className: 'bg-chip-unlocked-bg text-chip-unlocked',
	},
	flagged: {
		label: 'Flagged',
		className: 'bg-chip-flagged-bg text-chip-flagged',
	},
	coordinator_override: {
		label: 'Coordinator',
		className: 'bg-chip-coordinator-bg text-chip-coordinator',
	},
};

export function StatusChip( { variant = 'draft', label, icon } ) {
	const config = VARIANT_CONFIG[ variant ] ?? VARIANT_CONFIG.draft;
	const text = label ?? config.label;

	return (
		<span
			className={ [
				'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium leading-snug',
				config.className,
			].join( ' ' ) }
		>
			{ icon ? (
				<Icon name={ icon } className="h-3.5 w-3.5 shrink-0" />
			) : variant === 'flagged' ? (
				<span aria-hidden="true" className="select-none">
					⚑
				</span>
			) : variant === 'coordinator_override' ? (
				<span aria-hidden="true" className="select-none">
					⇄
				</span>
			) : null }
			<span>{ text }</span>
		</span>
	);
}
