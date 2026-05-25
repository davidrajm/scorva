import { TableSkeleton } from '../../shared/components';
import { TableDataViewport } from '../../shared/TableScrollViewport';
import {
	TABLE_BODY_ROW,
	regNoStickyClass,
	regNoStickyStyle,
} from '../../shared/tableStyles';
import {
	buildConsolidatedColumns,
	buildConsolidatedRows,
	sortConsolidatedRows,
} from './reportsConsolidatedUtils';

function formatScore( value ) {
	if ( value == null || value === '' ) {
		return '—';
	}

	return Number( value ).toLocaleString( undefined, {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
	} );
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
				'cursor-pointer select-none px-4 py-3 font-medium hover:text-text',
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
	return (
		<th
			rowSpan={ rowSpan }
			className="cursor-pointer select-none px-4 py-3 font-medium hover:text-text"
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

function SortableTh( { label, sortKey, activeSortKey, sortDirection, onSort } ) {
	return (
		<th
			className="min-w-[5rem] cursor-pointer select-none px-4 py-3 text-xs font-medium hover:text-text"
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

function TextCell( { value, maxWidth = '10rem' } ) {
	if ( ! value ) {
		return <span className="text-muted">—</span>;
	}

	return (
		<span
			className="block truncate text-text"
			style={ { maxWidth } }
			title={ value }
		>
			{ value }
		</span>
	);
}

export function ReportsConsolidatedTable( {
	data,
	loading,
	sortKey,
	sortDirection,
	onSort,
	exporting,
	onDownloadCsv,
	onDownloadExcel,
	exportError,
} ) {
	if ( loading ) {
		return <TableSkeleton rows={ 10 } columns={ 6 } />;
	}

	const reviews = data?.reviews ?? [];
	const students = data?.students ?? [];

	if ( reviews.length === 0 ) {
		return (
			<p className="text-sm text-text-muted">
				No confirmed reviews yet. Confirm review rubrics on Reviews & rubrics
				in the setup wizard to see consolidated scores.
			</p>
		);
	}

	if ( students.length === 0 ) {
		return (
			<p className="text-sm text-text-muted">
				No enrolled students for this project.
			</p>
		);
	}

	const columns = buildConsolidatedColumns( reviews );
	const rows = sortConsolidatedRows(
		buildConsolidatedRows( students, reviews ),
		sortKey,
		sortDirection
	);

	return (
		<div className="space-y-3">
			<div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
				<p className="text-xs text-muted">
					One row per student. Project title is from the most recent confirmed
					review. Review scores match Overall scores; overall score weights
					confirmed reviews by their configured weights.
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

			<TableDataViewport bodyRowCount={ rows.length }>
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
							<PlainSortableTh
								label="Student"
								sortKey="name"
								activeSortKey={ sortKey }
								sortDirection={ sortDirection }
								onSort={ onSort }
								rowSpan={ 2 }
							/>
							{ columns.identity.map( ( col ) => (
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
							{ columns.reviewGroups.map( ( group ) => (
								<th
									key={ group.reviewId }
									colSpan={ group.subColumns.length }
									className="px-4 py-3 text-center font-medium"
									title={ group.label }
								>
									{ group.label }
								</th>
							) ) }
							<PlainSortableTh
								label={ columns.overall.label }
								sortKey={ columns.overall.sortKey }
								activeSortKey={ sortKey }
								sortDirection={ sortDirection }
								onSort={ onSort }
								rowSpan={ 2 }
							/>
						</tr>
						<tr className="border-b border-border text-left text-xs text-muted">
							{ columns.reviewGroups.flatMap( ( group ) =>
								group.subColumns.map( ( col ) => (
									<SortableTh
										key={ col.sortKey }
										label={ col.label }
										sortKey={ col.sortKey }
										activeSortKey={ sortKey }
										sortDirection={ sortDirection }
										onSort={ onSort }
									/>
								) )
							) }
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row ) => (
							<tr
								key={ row.student_id }
								className={ `group ${ TABLE_BODY_ROW }` }
							>
								<RegNoStickyTd className="tabular-nums text-text">
									{ row.reg_no }
								</RegNoStickyTd>
								<td className="px-4 py-3 text-text">{ row.name }</td>
								<td className="px-4 py-3 text-text">
									<TextCell value={ row.program } />
								</td>
								<td className="px-4 py-3 text-text">
									<TextCell value={ row.batch } />
								</td>
								<td className="px-4 py-3 text-text">
									<TextCell value={ row.guide_emp_id } />
								</td>
								<td className="px-4 py-3 text-text">
									<TextCell value={ row.guide_name } />
								</td>
								<td className="px-4 py-3 text-text">
									<TextCell value={ row.project_title } maxWidth="14rem" />
								</td>
								{ columns.reviewGroups.flatMap( ( group ) =>
									group.subColumns.map( ( col ) => {
										const value = row.cells?.[ col.sortKey ];

										if ( col.key === 'review_score' ) {
											return (
												<td
													key={ col.sortKey }
													className="px-4 py-3 tabular-nums text-text"
												>
													{ formatScore( value ) }
												</td>
											);
										}

										return (
											<td key={ col.sortKey } className="px-4 py-3">
												<TextCell
													value={ value }
													maxWidth={
														col.key === 'reviewers'
															? '12rem'
															: '10rem'
													}
												/>
											</td>
										);
									} )
								) }
								<td className="px-4 py-3 tabular-nums font-medium text-text">
									{ formatScore( row.overall_score ) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</TableDataViewport>
		</div>
	);
}
