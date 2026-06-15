import { Link } from 'react-router-dom';
import { Card } from '../../shared/components';
import { Icon } from '../../shared/components/NavIcon';

const BTN_PRIMARY =
	'inline-flex items-center justify-center rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';
const BTN_PRIMARY_DISABLED =
	'inline-flex cursor-not-allowed items-center justify-center rounded-md bg-primary/40 px-3 py-1.5 text-sm font-medium text-white opacity-60';
const BTN_SECONDARY =
	'inline-flex items-center justify-center rounded-md border border-border bg-surface-raised px-3 py-1.5 text-sm font-medium text-text hover:bg-surface focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary';

function FrozenChip() {
	return (
		<span className="inline-flex items-center gap-1.5 rounded-md bg-[var(--pr-chip-unlocked-bg,#fff8c5)] px-2 py-0.5 text-xs font-medium text-[var(--pr-chip-unlocked-text,#9a6700)]">
			<Icon name="lock" className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
			Frozen
		</span>
	);
}

export function AssignmentCard( {
	sessionTitle,
	reviewLabel,
	panelName,
	coReviewers = [],
	markTo,
	panelReportTo,
	frozen = false,
} ) {
	const dualActions = Boolean( panelReportTo );

	if ( ! dualActions ) {
		return (
			<Link
				to={ markTo }
				aria-label={ `${ reviewLabel } — ${ panelName }, ${ frozen ? 'view frozen marks' : 'enter marks' }` }
				className="group block rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
			>
				<Card className="transition-shadow group-hover:shadow-md">
					<AssignmentCardBody
						sessionTitle={ sessionTitle }
						reviewLabel={ reviewLabel }
						panelName={ panelName }
						coReviewers={ coReviewers }
						frozenChip={ frozen ? <FrozenChip /> : null }
						showChevron={ ! frozen }
					/>
				</Card>
			</Link>
		);
	}

	return (
		<Card
			className="transition-shadow hover:shadow-md"
			aria-description={ frozen ? 'This assignment is frozen' : undefined }
		>
			<AssignmentCardBody
				sessionTitle={ sessionTitle }
				reviewLabel={ reviewLabel }
				panelName={ panelName }
				coReviewers={ coReviewers }
				frozenChip={ frozen ? <FrozenChip /> : null }
			/>
			<div className="mt-4 flex flex-wrap gap-2 border-t border-border/60 pt-4">
				<Link to={ markTo } className={ frozen ? BTN_SECONDARY : BTN_PRIMARY }>
					{ frozen ? 'View marks' : 'Enter marks' }
				</Link>
				<Link to={ panelReportTo } className={ BTN_SECONDARY }>
					Panel report
				</Link>
			</div>
		</Card>
	);
}

function AssignmentCardBody( {
	sessionTitle,
	reviewLabel,
	panelName,
	coReviewers,
	showChevron = false,
	frozenChip = null,
} ) {
	return (
		<div className="flex items-start justify-between gap-3">
			<div className="min-w-0 flex-1">
				<p className="text-xs font-medium uppercase tracking-wide text-text-muted">
					{ sessionTitle }
				</p>
				<h3 className="mt-1 text-lg font-semibold text-text">
					{ reviewLabel }
				</h3>
				<p className="mt-2 flex items-center gap-1.5 text-sm text-text-muted">
					<Icon name="panel" className="h-4 w-4 shrink-0" />
					<span className="truncate">{ panelName }</span>
				</p>
				{ coReviewers.length > 0 ? (
					<div
						className="mt-2.5"
						aria-label="Co-reviewers on this panel"
					>
						<div className="inline-flex max-w-full flex-wrap items-center gap-1 rounded-lg border border-border bg-surface-raised/60 p-1">
							<span className="inline-flex shrink-0 items-center gap-1.5 border-r border-border/80 pr-2 pl-1.5 text-xs font-medium text-text-muted">
								<Icon
									name="users"
									className="h-3.5 w-3.5 shrink-0 text-primary"
								/>
								<span>Co-reviewers</span>
							</span>
							{ coReviewers.map( ( reviewer ) => (
								<span
									key={
										reviewer.user_id ?? reviewer.name
									}
									className="inline-flex max-w-[10rem] truncate rounded-md bg-surface px-2 py-0.5 text-xs text-text"
									title={ reviewer.name }
								>
									{ reviewer.name }
								</span>
							) ) }
						</div>
					</div>
				) : null }
			</div>
			<div className="shrink-0">
				{ frozenChip ? frozenChip : null }
				{ ! frozenChip && showChevron ? (
					<Icon
						name="chevron-right"
						className="mt-1 h-5 w-5 shrink-0 text-text-muted transition-transform group-hover:translate-x-0.5"
					/>
				) : null }
			</div>
		</div>
	);
}
