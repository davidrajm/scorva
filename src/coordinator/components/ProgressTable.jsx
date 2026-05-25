import { StatusChip } from '../../shared/components';
import { TableDataViewport } from '../../shared/TableScrollViewport';
import { TABLE_BODY_ROW } from '../../shared/tableStyles';

const STATUS_CHIP = {
	complete: { variant: 'confirmed', label: 'Complete' },
	frozen: { variant: 'confirmed', label: 'Frozen', icon: 'lock' },
	in_progress: { variant: 'unlocked', label: 'In progress' },
	not_started: { variant: 'draft', label: 'Not started' },
	not_linked: { variant: 'draft', label: 'Not linked' },
};

function progressStatusChip( row ) {
	if ( row.linked === false ) {
		return STATUS_CHIP.not_linked;
	}

	return STATUS_CHIP[ row.status ] ?? STATUS_CHIP.not_started;
}

export function ProgressTable( {
	rows,
	showPanelColumn = true,
	emptyMessage = 'No panel assignments with enrolled students yet.',
} ) {
	if ( ! rows?.length ) {
		return <p className="text-sm text-muted">{ emptyMessage }</p>;
	}

	return (
		<TableDataViewport bodyRowCount={ rows.length }>
			<table className="min-w-full text-sm">
				<caption className="sr-only">
					Students complete when the reviewer has finished marking each
					student (all criteria scored for present students, or attendance
					recorded for absent students).
				</caption>
				<thead className="sticky top-0 z-10 bg-surface shadow-sm">
					<tr className="border-b border-border text-left text-muted">
						{ showPanelColumn ? (
							<th className="px-4 py-3 font-medium">Panel</th>
						) : null }
						<th className="px-4 py-3 font-medium">Reviewer</th>
						<th className="px-4 py-3 font-medium">Status</th>
						<th className="px-4 py-3 font-medium tabular-nums">
							Students complete
						</th>
						<th className="px-4 py-3 font-medium tabular-nums">%</th>
						<th className="px-4 py-3 font-medium">Progress</th>
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => {
						const chip = progressStatusChip( row );
						const displayName =
							row.reviewer_name ||
							row.reviewer_email ||
							( row.panel_reviewer_id
								? `Reviewer #${ row.panel_reviewer_id }`
								: 'Reviewer' );

						return (
							<tr
								key={ `${ row.panel_id }-${ row.panel_reviewer_id }` }
								className={ TABLE_BODY_ROW }
							>
								{ showPanelColumn ? (
									<td className="px-4 py-3 text-text">
										{ row.panel_name }
									</td>
								) : null }
								<td className="px-4 py-3 text-text">
									<div>{ displayName }</div>
									{ row.reviewer_email &&
									row.reviewer_name &&
									row.reviewer_email !== row.reviewer_name ? (
										<div className="text-xs text-muted">
											{ row.reviewer_email }
										</div>
									) : null }
								</td>
								<td className="px-4 py-3">
									<StatusChip
										variant={ chip.variant }
										label={ chip.label }
										icon={ chip.icon }
									/>
								</td>
								<td className="px-4 py-3 tabular-nums text-text">
									{ row.completed } / { row.total }
								</td>
								<td className="px-4 py-3 tabular-nums text-text">
									{ row.percent }%
								</td>
								<td className="px-4 py-3">
									<ProgressBar percent={ row.percent } />
								</td>
							</tr>
						);
					} ) }
				</tbody>
			</table>
		</TableDataViewport>
	);
}

function ProgressBar( { percent } ) {
	const value = Math.min( 100, Math.max( 0, Number( percent ) || 0 ) );

	return (
		<div
			className="h-2 w-full max-w-xs overflow-hidden rounded-full bg-border"
			role="progressbar"
			aria-valuenow={ value }
			aria-valuemin={ 0 }
			aria-valuemax={ 100 }
		>
			<div
				className="h-full rounded-full bg-primary motion-reduce:transition-none transition-[width] duration-300"
				style={ { width: `${ value }%` } }
			/>
		</div>
	);
}
