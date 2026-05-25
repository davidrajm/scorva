import {
	attendanceStatusChip,
	studentStatusChip,
} from '../../reviewer/components/markingGridUtils';
import { StatusChip, TableSkeleton } from '../../shared/components';
import { TableDataViewport } from '../../shared/TableScrollViewport';
import {
	TABLE_BODY_ROW,
	regNoStickyClass,
	regNoStickyStyle,
} from '../../shared/tableStyles';
import {
	getAllOverallLeafColumns,
	truncateLabel,
} from './reportsScoresMatrixUtils';

function formatScore( value ) {
	if ( value == null || value === '' ) {
		return '—';
	}

	return Number( value ).toLocaleString( undefined, {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	} );
}

function reportStudentStatusChip( status, coordinatorLocked ) {
	if ( coordinatorLocked || status === 'locked' ) {
		return { label: 'Locked', variant: 'confirmed', icon: 'lock' };
	}

	return studentStatusChip( status );
}

function OverallCell( { cell } ) {
	if ( cell == null || cell.score == null ) {
		return <span className="text-muted">—</span>;
	}

	return (
		<span
			className={ [
				'inline-flex flex-wrap items-center gap-1 tabular-nums',
				cell.draft ? 'text-muted' : 'text-text',
			].join( ' ' ) }
		>
			{ formatScore( cell.score ) }
		</span>
	);
}

function SortIndicator( { active, direction } ) {
	if ( ! active ) {
		return (
			<span className="ml-1 text-muted opacity-40" aria-hidden="true">
				↕
			</span>
		);
	}

	return (
		<span className="ml-1 text-primary" aria-hidden="true">
			{ direction === 'asc' ? '▲' : '▼' }
		</span>
	);
}

function RegNoStickySortableTh( {
	label,
	sortKey,
	activeSortKey,
	sortDirection,
	onSort,
	rowSpan,
} ) {
	return (
		<th
			rowSpan={ rowSpan }
			style={ regNoStickyStyle() }
			className={ [
				regNoStickyClass( { header: true } ),
				'px-4 py-3 font-medium',
				'cursor-pointer select-none hover:text-text',
			].join( ' ' ) }
			aria-sort={
				activeSortKey === sortKey
					? sortDirection === 'asc'
						? 'ascending'
						: 'descending'
					: 'none'
			}
			onClick={ () => onSort( sortKey ) }
		>
			<span className="inline-flex items-center">
				{ label }
				<SortIndicator
					active={ activeSortKey === sortKey }
					direction={ sortDirection }
				/>
			</span>
		</th>
	);
}

function RegNoStickyTd( { children, className = '' } ) {
	return (
		<td
			style={ regNoStickyStyle() }
			className={ [ regNoStickyClass(), 'px-4 py-3', className ]
				.filter( Boolean )
				.join( ' ' ) }
		>
			{ children }
		</td>
	);
}

function PlainSortableTh( {
	label,
	sortKey,
	activeSortKey,
	sortDirection,
	onSort,
	rowSpan,
} ) {
	const isActive = activeSortKey === sortKey;

	return (
		<th
			rowSpan={ rowSpan }
			className="cursor-pointer select-none px-4 py-3 font-medium hover:text-text"
			aria-sort={
				isActive
					? sortDirection === 'asc'
						? 'ascending'
						: 'descending'
					: 'none'
			}
			onClick={ () => onSort( sortKey ) }
		>
			<span className="inline-flex items-center">
				{ label }
				<SortIndicator active={ isActive } direction={ sortDirection } />
			</span>
		</th>
	);
}

function SortableTh( {
	label,
	sortKey,
	activeSortKey,
	sortDirection,
	onSort,
	title,
} ) {
	const isActive = activeSortKey === sortKey;

	return (
		<th
			className="min-w-[5rem] cursor-pointer select-none px-4 py-3 font-medium hover:text-text"
			aria-sort={
				isActive
					? sortDirection === 'asc'
						? 'ascending'
						: 'descending'
					: 'none'
			}
			title={ title || label }
			onClick={ () => onSort( sortKey ) }
		>
			<span className="inline-flex items-center">
				<span className="max-w-[8rem] truncate" title={ title || label }>
					{ label }
				</span>
				<SortIndicator active={ isActive } direction={ sortDirection } />
			</span>
		</th>
	);
}

export function ReportsOverallScoresTable( {
	columns,
	rows,
	loading,
	sortKey,
	sortDirection,
	onSort,
	exporting,
	onDownloadCsv,
	onDownloadExcel,
	exportError,
	coordinatorLocked,
} ) {
	if ( loading ) {
		return <TableSkeleton rows={ 10 } columns={ 6 } />;
	}

	if ( ! rows?.length ) {
		return (
			<p className="text-sm text-text-muted">
				No enrolled students for this project.
			</p>
		);
	}

	const leaves = getAllOverallLeafColumns( columns );
	const fixedColumns = columns.fixed ?? [];

	return (
		<div className="space-y-3">
			<div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
				<p className="text-xs text-muted">
					Panel reviewer slots match the rubric marks layout. Overall columns
					show each reviewer&apos;s total for the student.
				</p>

				<div className="flex flex-wrap gap-2 sm:justify-end">
					<button
						type="button"
						className="rounded-md border border-border bg-surface px-3 py-2 text-sm font-medium text-text hover:bg-surface-raised disabled:opacity-50"
						disabled={ exporting || ! rows.length }
						onClick={ onDownloadExcel }
					>
						{ exporting === 'xlsx'
							? 'Downloading…'
							: 'Download Excel' }
					</button>
					<button
						type="button"
						className="rounded-md border border-border bg-surface px-3 py-2 text-sm font-medium text-text hover:bg-surface-raised disabled:opacity-50"
						disabled={ exporting || ! rows.length }
						onClick={ onDownloadCsv }
					>
						{ exporting === 'csv' ? 'Downloading…' : 'Download CSV' }
					</button>
				</div>
			</div>

			{ exportError ? (
				<p className="text-sm text-danger" role="alert">
					{ exportError }
				</p>
			) : null }

			<TableDataViewport headerRows={ 2 } bodyRowCount={ rows.length }>
				<table className="w-max min-w-full text-sm">
					<thead className="sticky top-0 z-40 bg-surface shadow-sm">
						<tr className="border-b border-border text-left text-muted">
							<RegNoStickySortableTh
								label="Reg no"
								sortKey="reg_no"
								activeSortKey={ sortKey }
								sortDirection={ sortDirection }
								onSort={ onSort }
								rowSpan={ 2 }
							/>
							{ fixedColumns
								.filter( ( col ) => col.key !== 'reg_no' )
								.map( ( col ) => (
									<PlainSortableTh
										key={ col.key }
										label={ col.label }
										sortKey={ col.sortKey }
										activeSortKey={ sortKey }
										sortDirection={ sortDirection }
										onSort={ onSort }
										rowSpan={ 2 }
									/>
								) ) }
							<th
								colSpan={ Math.max( 1, leaves.length ) }
								className="px-4 py-3 text-center font-medium"
							>
								Reviewer overall
							</th>
							<th
								rowSpan={ 2 }
								className="min-w-[8rem] cursor-pointer select-none px-4 py-3 font-medium hover:text-text"
								aria-sort={
									sortKey === 'review_score'
										? sortDirection === 'asc'
											? 'ascending'
											: 'descending'
										: 'none'
								}
								onClick={ () => onSort( 'review_score' ) }
							>
								<span className="inline-flex items-center">
									Weighted review score
									<SortIndicator
										active={ sortKey === 'review_score' }
										direction={ sortDirection }
									/>
								</span>
							</th>
						</tr>
						<tr className="border-b border-border text-left text-xs text-muted">
							{ leaves.map( ( leaf ) => (
								<SortableTh
									key={ leaf.key }
									label={ truncateLabel( leaf.label ) }
									sortKey={ leaf.sortKey }
									activeSortKey={ sortKey }
									sortDirection={ sortDirection }
									onSort={ onSort }
									title={ leaf.fullLabel || leaf.label }
								/>
							) ) }
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row ) => {
							const attendanceChip = attendanceStatusChip(
								row.attendance_status
							);
							const statusChip = reportStudentStatusChip(
								row.mark_status,
								coordinatorLocked
							);

							return (
								<tr
									key={ row.student_id }
									className={ `group ${ TABLE_BODY_ROW }` }
								>
									<RegNoStickyTd className="tabular-nums text-text">
										{ row.reg_no }
									</RegNoStickyTd>
									<td className="px-4 py-3 text-text">{ row.name }</td>
									<td className="px-4 py-3 text-text">
										{ row.panel_name ? (
											<span
												className="block max-w-[10rem] truncate"
												title={ row.panel_name }
											>
												{ row.panel_name }
											</span>
										) : (
											<span className="text-muted">—</span>
										) }
									</td>
									<td className="px-4 py-3 text-text">
										{ row.panel_coordinator_name ? (
											<span
												className="block max-w-[10rem] truncate"
												title={ row.panel_coordinator_name }
											>
												{ row.panel_coordinator_name }
											</span>
										) : (
											<span className="text-muted">—</span>
										) }
									</td>
									<td className="px-4 py-3 text-text">
										{ row.panel_reviewer_names ? (
											<span
												className="block max-w-[12rem] truncate"
												title={ row.panel_reviewer_names }
											>
												{ row.panel_reviewer_names }
											</span>
										) : (
											<span className="text-muted">—</span>
										) }
									</td>
									<td className="px-4 py-3">
										<StatusChip
											variant={ attendanceChip.variant }
											label={ attendanceChip.label }
										/>
									</td>
									<td className="px-4 py-3">
										<StatusChip
											variant={ statusChip.variant }
											label={ statusChip.label }
											icon={ statusChip.icon }
										/>
									</td>
									{ leaves.map( ( leaf ) => (
										<td
											key={ leaf.key }
											className="px-4 py-3 align-top"
										>
											<OverallCell
												cell={ row.cells?.[ leaf.key ] }
											/>
										</td>
									) ) }
									<td className="px-4 py-3 tabular-nums font-medium text-text">
										{ formatScore( row.review_score ) }
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			</TableDataViewport>

			<p className="text-xs text-muted">
				Draft reviewer totals include in-progress marks. Review scores are weighted averages of reviewer mark sums
				are computed on the server from submitted marks only.
			</p>
		</div>
	);
}
