import {
	attendanceStatusChip,
	studentStatusChip,
} from '../../reviewer/components/markingGridUtils';
import { useState } from '@wordpress/element';
import {
	FlaggedMarkChip,
	Notice,
	ShuttleMarkChip,
	StatusChip,
	TableSkeleton,
} from '../../shared/components';
import { MarkOverrideDialog } from './MarkOverrideDialog';
import { TableDataViewport } from '../../shared/TableScrollViewport';
import {
	TABLE_BODY_ROW,
	regNoStickyClass,
	regNoStickyStyle,
} from '../../shared/tableStyles';
import {
	getAllLeafColumns,
	truncateLabel,
} from './reportsMarksMatrixUtils';

function formatScore( value ) {
	if ( value == null || value === '' ) {
		return '—';
	}

	return Number( value ).toLocaleString( undefined, {
		minimumFractionDigits: 0,
		maximumFractionDigits: 2,
	} );
}

function reportStudentStatusChip( status, coordinatorLocked ) {
	if ( coordinatorLocked || status === 'locked' ) {
		return { label: 'Locked', variant: 'confirmed', icon: 'lock' };
	}

	return studentStatusChip( status );
}

function MarkCell( { cell, canOverride, onOverride } ) {
	if ( cell == null || cell.score == null ) {
		return <span className="text-muted">—</span>;
	}

	return (
		<span className="inline-flex flex-wrap items-center gap-1">
			<span
				className={ [
					'tabular-nums',
					cell.draft ? 'text-muted' : 'text-text',
				].join( ' ' ) }
			>
				{ formatScore( cell.score ) }
			</span>
			{ cell.coordinator_overridden ? (
				<ShuttleMarkChip
					fromScore={ cell.overridden_from_score }
					score={ cell.score }
				/>
			) : null }
			{ ! cell.coordinator_overridden && cell.flagged ? (
				<FlaggedMarkChip />
			) : null }
			{ canOverride && cell.markId ? (
				<button
					type="button"
					className="rounded border border-border px-1.5 py-0.5 text-xs text-text-muted hover:bg-surface-raised hover:text-text"
					title="Override mark"
					aria-label="Override mark"
					onClick={ () => onOverride( cell ) }
				>
					Override
				</button>
			) : null }
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
	className = '',
	title,
	rowSpan,
} ) {
	return (
		<th
			rowSpan={ rowSpan }
			style={ regNoStickyStyle() }
			className={ [
				regNoStickyClass( { header: true } ),
				'px-4 py-3 font-medium',
				sortKey ? 'cursor-pointer select-none hover:text-text' : '',
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
			aria-sort={
				activeSortKey === sortKey
					? sortDirection === 'asc'
						? 'ascending'
						: 'descending'
					: 'none'
			}
			title={ title || label }
			onClick={ sortKey ? () => onSort( sortKey ) : undefined }
		>
			<span className="inline-flex items-center">
				<span className="truncate" title={ title || label }>
					{ label }
				</span>
				{ sortKey ? (
					<SortIndicator
						active={ activeSortKey === sortKey }
						direction={ sortDirection }
					/>
				) : null }
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
	className = '',
	title,
	rowSpan,
} ) {
	const isActive = activeSortKey === sortKey;

	return (
		<th
			rowSpan={ rowSpan }
			className={ [
				'px-4 py-3 font-medium',
				sortKey ? 'cursor-pointer select-none hover:text-text' : '',
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
			aria-sort={
				isActive
					? sortDirection === 'asc'
						? 'ascending'
						: 'descending'
					: 'none'
			}
			title={ title || label }
			onClick={ sortKey ? () => onSort( sortKey ) : undefined }
		>
			<span className="inline-flex items-center">
				<span className="truncate" title={ title || label }>
					{ label }
				</span>
				{ sortKey ? (
					<SortIndicator
						active={ isActive }
						direction={ sortDirection }
					/>
				) : null }
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
	className = '',
	title,
} ) {
	const isActive = activeSortKey === sortKey;

	return (
		<th
			className={ [
				'min-w-[5rem] px-4 py-3 font-medium',
				sortKey ? 'cursor-pointer select-none hover:text-text' : '',
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
			aria-sort={
				isActive
					? sortDirection === 'asc'
						? 'ascending'
						: 'descending'
					: 'none'
			}
			title={ title }
			onClick={ sortKey ? () => onSort( sortKey ) : undefined }
		>
			<span className="inline-flex items-center">
				<span className="max-w-[8rem] truncate" title={ title || label }>
					{ label }
				</span>
				{ sortKey ? (
					<SortIndicator
						active={ isActive }
						direction={ sortDirection }
					/>
				) : null }
			</span>
		</th>
	);
}

const LAYOUT_OPTIONS = [
	{
		id: 'rubric',
		label: 'Rubric-first',
		subtitle: 'Rubrics → reviewer scores',
	},
	{
		id: 'reviewer',
		label: 'Reviewer-first',
		subtitle: 'Reviewers → rubric marks',
	},
];

export function ReportsMarksTable( {
	columns,
	rows,
	loading,
	layout,
	onLayoutChange,
	sortKey,
	sortDirection,
	onSort,
	exporting,
	onDownloadCsv,
	onDownloadExcel,
	exportError,
	coordinatorLocked,
	reviewLabel,
	criteria = [],
	onMarksChanged,
} ) {
	const [ overrideTarget, setOverrideTarget ] = useState( null );
	const [ overrideNotice, setOverrideNotice ] = useState( null );

	const criterionLabelById = Object.fromEntries(
		( criteria ?? [] ).map( ( row ) => [
			String( row.id ),
			row.label ?? `Criterion ${ row.id }`,
		] )
	);

	const criterionMaxById = Object.fromEntries(
		( criteria ?? [] ).map( ( row ) => [ String( row.id ), row.max_marks ] )
	);

	const canOverrideCell = ! coordinatorLocked;

	if ( loading ) {
		return (
			<TableSkeleton rows={ 10 } columns={ 6 } />
		);
	}

	if ( ! columns?.groups?.length ) {
		return (
			<p className="text-sm text-text-muted">
				No rubric criteria for this review.
			</p>
		);
	}

	if ( ! rows?.length ) {
		return (
			<p className="text-sm text-text-muted">
				No enrolled students for this project.
			</p>
		);
	}

	const leaves = getAllLeafColumns( columns );
	const fixedColumns = columns.fixed ?? [];
	const activeLayout =
		LAYOUT_OPTIONS.find( ( option ) => option.id === layout ) ??
		LAYOUT_OPTIONS[ 0 ];

	return (
		<div className="space-y-3">
			<div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
				<div className="space-y-2">
					<div
						className="inline-flex rounded-md border border-border p-1"
						role="group"
						aria-label="Matrix layout"
					>
						{ LAYOUT_OPTIONS.map( ( option ) => (
							<button
								key={ option.id }
								type="button"
								className={ [
									'rounded-md px-3 py-2 text-sm font-medium',
									layout === option.id
										? 'bg-chip-active-bg text-primary'
										: 'text-text-muted hover:bg-surface-raised hover:text-text',
								].join( ' ' ) }
								aria-pressed={ layout === option.id }
								onClick={ () => onLayoutChange( option.id ) }
							>
								{ option.label }
							</button>
						) ) }
					</div>
					<p className="text-xs text-muted">{ activeLayout.subtitle }</p>
				</div>

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

			{ coordinatorLocked ? (
				<p className="text-sm text-text-muted">
					Marks are frozen for this review. Unlock the review on this page to
					override scores.
				</p>
			) : null }

			{ overrideNotice ? (
				<Notice variant="success" className="text-sm">
					{ overrideNotice }
				</Notice>
			) : null }

			<MarkOverrideDialog
				open={ Boolean( overrideTarget ) }
				markId={ overrideTarget?.markId }
				reviewLabel={ reviewLabel }
				studentLabel={ overrideTarget?.studentLabel }
				criterionLabel={
					overrideTarget
						? criterionLabelById[ String( overrideTarget.criterionId ) ]
						: ''
				}
				reviewerLabel={ overrideTarget?.reviewerLabel }
				currentScore={ overrideTarget?.score }
				maxMarks={
					overrideTarget
						? criterionMaxById[ String( overrideTarget.criterionId ) ]
						: null
				}
				onClose={ () => setOverrideTarget( null ) }
				onSuccess={ () => {
					setOverrideNotice( 'Mark overridden successfully.' );
					onMarksChanged?.();
				} }
			/>

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
							{ columns.groups.map( ( group ) => (
								<th
									key={ group.id }
									colSpan={ Math.max( 1, group.leaves.length ) }
									className="px-4 py-3 text-center font-medium"
									title={ group.fullLabel || group.label }
								>
									<span
										className="mx-auto block max-w-[12rem] truncate"
										title={ group.fullLabel || group.label }
									>
										{ truncateLabel( group.label ) }
									</span>
								</th>
							) ) }
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
									title={
										leaf.fullLabel ||
										( leaf.maxMarks != null
											? `${ leaf.label } (max ${ leaf.maxMarks })`
											: leaf.label )
									}
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
											<MarkCell
												cell={ row.cells?.[ leaf.key ] }
												canOverride={ canOverrideCell }
												onOverride={ ( cell ) =>
													setOverrideTarget( {
														markId: cell.markId,
														score: cell.score,
														criterionId: cell.criterion_id,
														reviewerLabel:
															cell.reviewer_name ||
															leaf.reviewerName ||
															'',
														studentLabel: `${ row.reg_no } — ${ row.name }`,
													} )
												}
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
				Review scores are weighted averages of reviewer mark sums (server-computed from submitted marks)
				only. They cannot be edited on this page.
			</p>
		</div>
	);
}
