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

function normalizeReviewerTotal( raw ) {
	if ( raw == null ) {
		return null;
	}
	if ( typeof raw === 'object' && 'score' in raw ) {
		return {
			score: raw.score,
			draft: Boolean( raw.draft ),
		};
	}

	return {
		score: raw,
		draft: false,
	};
}

function formatScore( value ) {
	if ( value == null || Number.isNaN( Number( value ) ) ) {
		return '—';
	}

	return Number( value ).toFixed( 2 );
}

function reportScoresStatusChip( status, panelFrozen ) {
	if ( panelFrozen ) {
		return { label: 'Panel frozen', variant: 'confirmed', icon: 'lock' };
	}

	return studentStatusChip( status );
}

function reviewerHeaderLabel( reviewer, useOrdinalHeaders ) {
	if ( useOrdinalHeaders && reviewer?.ordinal != null ) {
		return `Reviewer ${ reviewer.ordinal }`;
	}

	return reviewer.name;
}

export function ReportsScoresTable( {
	reviewers,
	students,
	loading,
	showDraftTotals = false,
	showStatusColumn = false,
	panelFrozen = false,
	showSrNo = false,
	showProjectTitle = false,
	showGuideName = false,
	useOrdinalReviewerHeaders = false,
} ) {
	if ( loading ) {
		return <TableSkeleton rows={ 10 } columns={ 6 } />;
	}

	if ( ! students?.length ) {
		return (
			<p className="text-sm text-text-muted">
				No enrolled students for this project.
			</p>
		);
	}

	return (
		<div className="space-y-3">
			<TableDataViewport headerRows={ 2 } bodyRowCount={ students.length }>
				<table className="w-max min-w-full text-sm">
					<thead className="sticky top-0 z-10 bg-surface shadow-sm">
						<tr className="border-b border-border text-left text-muted">
							{ showSrNo ? (
								<th className="px-4 py-3 font-medium">Sr. No.</th>
							) : null }
							<th
								className={ `${ regNoStickyClass( { header: true } ) } px-4 py-3 font-medium` }
								style={ regNoStickyStyle() }
							>
								Reg no
							</th>
							<th className="px-4 py-3 font-medium">Student</th>
							{ showProjectTitle ? (
								<th className="px-4 py-3 font-medium">Project title</th>
							) : null }
							{ showGuideName ? (
								<th className="px-4 py-3 font-medium">Guide</th>
							) : null }
							{ showStatusColumn ? (
								<th className="px-4 py-3 font-medium">Attendance</th>
							) : null }
							{ showStatusColumn ? (
								<th className="px-4 py-3 font-medium">Marks status</th>
							) : null }
							{ ( reviewers || [] ).map( ( reviewer ) => (
								<th
									key={ reviewer.user_id }
									className="min-w-[6rem] px-4 py-3 font-medium"
								>
									{ reviewerHeaderLabel(
										reviewer,
										useOrdinalReviewerHeaders
									) }
								</th>
							) ) }
							<th className="px-4 py-3 font-medium">Review score</th>
						</tr>
					</thead>
					<tbody>
						{ students.map( ( student, index ) => {
							const attendanceChip = showStatusColumn
								? attendanceStatusChip( student.attendance_status )
								: null;
							const statusChip = showStatusColumn
								? reportScoresStatusChip(
										student.mark_status,
										panelFrozen
								  )
								: null;

							return (
							<tr
								key={ student.student_id }
								className={ `group ${ TABLE_BODY_ROW }` }
							>
								{ showSrNo ? (
									<td className="px-4 py-3 tabular-nums text-text">
										{ index + 1 }
									</td>
								) : null }
								<td
									className={ `${ regNoStickyClass() } px-4 py-3 tabular-nums text-text` }
									style={ regNoStickyStyle() }
								>
									{ student.reg_no }
								</td>
								<td className="px-4 py-3 text-text">{ student.name }</td>
								{ showProjectTitle ? (
									<td className="px-4 py-3 text-text">
										{ student.project_title || '' }
									</td>
								) : null }
								{ showGuideName ? (
									<td className="px-4 py-3 text-text">
										{ student.guide_name || '' }
									</td>
								) : null }
								{ attendanceChip ? (
									<td className="px-4 py-3">
										<StatusChip
											variant={ attendanceChip.variant }
											label={ attendanceChip.label }
										/>
									</td>
								) : null }
								{ statusChip ? (
									<td className="px-4 py-3">
										<StatusChip
											variant={ statusChip.variant }
											label={ statusChip.label }
											icon={ statusChip.icon }
										/>
									</td>
								) : null }
								{ ( reviewers || [] ).map( ( reviewer ) => {
									const total = normalizeReviewerTotal(
										student.reviewer_totals?.[
											String( reviewer.user_id )
										] ??
											student.reviewer_totals?.[
												reviewer.user_id
											]
									);

									return (
										<td
											key={ reviewer.user_id }
											className="px-4 py-3 tabular-nums"
										>
											<span
												className={
													total?.draft
														? 'text-muted'
														: 'text-text'
												}
											>
												{ formatScore( total?.score ) }
											</span>
											{ showDraftTotals && total?.draft ? (
												<span className="ml-1 inline-flex align-middle">
													<StatusChip
														variant="draft"
														label="Draft"
													/>
												</span>
											) : null }
										</td>
									);
								} ) }
								<td className="px-4 py-3 tabular-nums font-medium text-text">
									{ formatScore( student.review_score ) }
								</td>
							</tr>
							);
						} ) }
					</tbody>
				</table>
			</TableDataViewport>
			<p className="text-xs text-muted">
				{ showDraftTotals
					? 'Draft reviewer totals include in-progress marks. Review scores are weighted averages of reviewer totals (submitted marks only).'
					: 'Reviewer totals are sums of criterion marks; review scores are weighted averages of those totals. All computed on the server from submitted marks.' }
			</p>
		</div>
	);
}
