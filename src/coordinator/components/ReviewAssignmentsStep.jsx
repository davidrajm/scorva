import { useCallback, useEffect, useState } from '@wordpress/element';
import { get, post, put } from '../../shared/api';
import { formatAttendanceConflictLabel } from '../../shared/markErrors';
import { Button, ConfirmDialog, Notice, TableSkeleton } from '../../shared/components';
import { TableScrollWrapper } from '../../shared/TableScrollViewport';
import { TABLE_BODY_ROW_SOFT } from '../../shared/tableStyles';
import { CorrectAttendanceDialog } from './CorrectAttendanceDialog';

function rowKey( studentId ) {
	return String( studentId );
}

export function ReviewAssignmentsStep( {
	sessionId,
	panels,
	wizardState,
	onReload,
	onNotice,
	onContinue,
	isWizardTerminalStep = false,
} ) {
	const [ reviews, setReviews ] = useState( [] );
	const [ selectedReviewId, setSelectedReviewId ] = useState( null );
	const [ students, setStudents ] = useState( [] );
	const [ rowDrafts, setRowDrafts ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( false );
	const [ confirmAction, setConfirmAction ] = useState( null );
	const [ correctionTarget, setCorrectionTarget ] = useState( null );
	const [ attendanceNotice, setAttendanceNotice ] = useState( null );

	const syncDraftsFromStudents = useCallback( ( rows ) => {
		const drafts = {};
		for ( const row of rows ) {
			drafts[ rowKey( row.student_id ) ] = {
				panel_id: row.panel_id ?? '',
				project_title: row.project_title ?? '',
			};
		}
		setRowDrafts( drafts );
	}, [] );

	const loadReviews = useCallback( async () => {
		const data = await get( `/sessions/${ sessionId }/reviews` );
		const items = data.reviews ?? [];
		setReviews( items );
		if ( items.length && ! selectedReviewId ) {
			setSelectedReviewId( items[ 0 ].id );
		}
		return items;
	}, [ sessionId, selectedReviewId ] );

	const loadAssignments = useCallback(
		async ( reviewId ) => {
			if ( ! reviewId ) {
				setStudents( [] );
				setRowDrafts( {} );
				return;
			}
			const data = await get(
				`/sessions/${ sessionId }/reviews/${ reviewId }/assignments`
			);
			const rows = data.students ?? [];
			setStudents( rows );
			syncDraftsFromStudents( rows );
		},
		[ sessionId, syncDraftsFromStudents ]
	);

	useEffect( () => {
		( async () => {
			setLoading( true );
			try {
				await loadReviews();
			} catch {
				onNotice?.( {
					variant: 'error',
					message: 'Could not load review rounds.',
				} );
			} finally {
				setLoading( false );
			}
		} )();
	}, [ loadReviews, onNotice ] );

	useEffect( () => {
		if ( ! selectedReviewId ) {
			return;
		}
		( async () => {
			try {
				await loadAssignments( selectedReviewId );
			} catch {
				onNotice?.( {
					variant: 'error',
					message: 'Could not load assignments for this round.',
				} );
			}
		} )();
	}, [ selectedReviewId, loadAssignments, onNotice ] );

	const selectedReview = reviews.find( ( r ) => r.id === selectedReviewId );
	const reviewMarksLocked = Boolean( selectedReview?.coordinator_marks_locked );
	const previousReview = ( () => {
		const index = reviews.findIndex( ( r ) => r.id === selectedReviewId );
		return index > 0 ? reviews[ index - 1 ] : null;
	} )();

	const updateDraft = ( studentId, patch ) => {
		const key = rowKey( studentId );
		setRowDrafts( ( current ) => ( {
			...current,
			[ key ]: { ...current[ key ], ...patch },
		} ) );
	};

	const isRowDirty = ( row ) => {
		const draft = rowDrafts[ rowKey( row.student_id ) ];
		if ( ! draft ) {
			return false;
		}
		const panelId = draft.panel_id === '' ? null : Number( draft.panel_id );
		return (
			panelId !== row.panel_id ||
			( draft.project_title ?? '' ) !== ( row.project_title ?? '' )
		);
	};

	const saveStudentRow = async ( row ) => {
		const draft = rowDrafts[ rowKey( row.student_id ) ];
		if ( ! draft ) {
			return;
		}
		const panelId = Number( draft.panel_id );
		if ( ! panelId ) {
			onNotice?.( {
				variant: 'error',
				message: 'Select a panel before saving.',
			} );
			return;
		}

		setBusy( true );
		try {
			const data = await put(
				`/sessions/${ sessionId }/reviews/${ selectedReviewId }/assignments/students`,
				{
					students: [
						{
							student_id: row.student_id,
							panel_id: panelId,
							project_title: draft.project_title ?? '',
						},
					],
				}
			);
			setStudents( data.students ?? [] );
			syncDraftsFromStudents( data.students ?? [] );
			await onReload?.();
		} catch {
			onNotice?.( {
				variant: 'error',
				message: 'Could not save assignment.',
			} );
		} finally {
			setBusy( false );
		}
	};

	const runCopyFromPrevious = async () => {
		if ( ! previousReview || ! selectedReviewId ) {
			return;
		}
		setBusy( true );
		try {
			await post(
				`/sessions/${ sessionId }/reviews/${ selectedReviewId }/assignments/copy-from/${ previousReview.id }`
			);
			await loadAssignments( selectedReviewId );
			await onReload?.();
			onNotice?.( {
				variant: 'success',
				message: `Assignments copied from ${ previousReview.label }.`,
			} );
		} catch {
			onNotice?.( {
				variant: 'error',
				message: 'Could not copy assignments.',
			} );
		} finally {
			setBusy( false );
			setConfirmAction( null );
		}
	};

	const runResetToDefaults = async () => {
		if ( ! selectedReviewId ) {
			return;
		}
		setBusy( true );
		try {
			await post(
				`/sessions/${ sessionId }/reviews/${ selectedReviewId }/assignments/reset-to-session-defaults`
			);
			await loadAssignments( selectedReviewId );
			await onReload?.();
			onNotice?.( {
				variant: 'success',
				message: 'Assignments reset to project defaults.',
			} );
		} catch {
			onNotice?.( {
				variant: 'error',
				message: 'Could not reset assignments.',
			} );
		} finally {
			setBusy( false );
			setConfirmAction( null );
		}
	};

	const unassigned = wizardState?.review_assignment_unassigned ?? 0;
	const canContinue = wizardState?.assignments_complete ?? unassigned === 0;

	if ( loading ) {
		return <TableSkeleton rows={ 8 } columns={ 5 } />;
	}

	return (
		<section>
			<h2 className="text-lg font-semibold text-text">Panel assignments</h2>
			<p className="mt-1 text-sm text-text-muted">
				Assign each student to a panel for each review round. Review 1 defaults
				come from the Panels step; later rounds can copy the previous review.
				Save each row after editing. When every student has a panel on every
				round, continue to Open reviews to start or pause marking.
			</p>

			{ reviews.length === 0 ? (
				<p className="mt-4 text-sm text-warning">
					No review rounds found. Add rounds on the Reviews & rubrics step
					first.
				</p>
			) : (
				<>
					<div className="mt-4 flex flex-wrap items-end gap-4">
						<label className="block text-sm">
							<span className="font-medium text-text">Review round</span>
							<select
								className="mt-1 block rounded-md border border-border bg-surface px-3 py-2 text-sm"
								value={ selectedReviewId ?? '' }
								onChange={ ( e ) => {
									setSelectedReviewId( Number( e.target.value ) );
									setAttendanceNotice( null );
									setCorrectionTarget( null );
								} }
							>
								{ reviews.map( ( review ) => (
									<option key={ review.id } value={ review.id }>
										{ review.label }
									</option>
								) ) }
							</select>
						</label>
						<div className="flex flex-wrap gap-2">
							<Button
								variant="secondary"
								size="sm"
								disabled={ busy || ! previousReview }
								onClick={ () => setConfirmAction( 'copy' ) }
							>
								Copy from previous review
							</Button>
							<Button
								variant="secondary"
								size="sm"
								disabled={ busy }
								onClick={ () => setConfirmAction( 'reset' ) }
							>
								Reset to project defaults
							</Button>
						</div>
					</div>

					{ unassigned > 0 ? (
						<div className="mt-4">
							<Notice variant="warning">
								{ unassigned } student
								{ unassigned === 1 ? '' : 's' } still need a panel on one or
								more review rounds.
							</Notice>
						</div>
					) : null }

					{ attendanceNotice ? (
						<div className="mt-4">
							<Notice variant={ attendanceNotice.variant }>
								{ attendanceNotice.message }
							</Notice>
						</div>
					) : null }

					{ selectedReview && students.length > 0 && ! reviewMarksLocked ? (
						<div className="mt-4 rounded-md border border-border bg-surface-raised p-4 text-sm text-text-muted">
							<p className="font-medium text-text">Attendance correction</p>
							<p className="mt-1">
								When all panel reviewers recorded the same attendance but
								that value is wrong, use <strong className="text-text">Correct attendance</strong> on
								the student below. This updates every reviewer on that
								panel for <strong className="text-text">{ selectedReview.label }</strong> only.
								Setting Absent clears all criterion scores for that student
								on this review.
							</p>
						</div>
					) : null }

					{ reviewMarksLocked ? (
						<p className="mt-4 text-sm text-text-muted">
							Review marks are frozen. Unlock this review on Reports before
							correcting attendance.
						</p>
					) : null }

					{ students.length > 0 ? (
						<TableScrollWrapper className="mt-4">
							<table className="min-w-full divide-y divide-border text-sm">
								<thead className="bg-surface-raised text-left text-text-muted">
									<tr>
										<th className="px-3 py-2 font-medium">Student</th>
										<th className="px-3 py-2 font-medium">Project title</th>
										<th className="px-3 py-2 font-medium">Panel</th>
										<th className="px-3 py-2 font-medium text-right">
											Actions
										</th>
									</tr>
								</thead>
								<tbody className="divide-y divide-border bg-surface">
									{ students.map( ( row ) => {
										const draft =
											rowDrafts[ rowKey( row.student_id ) ] ?? {};
										const dirty = isRowDirty( row );

										return (
											<tr
												key={ row.student_id }
												className={ `group ${ TABLE_BODY_ROW_SOFT }` }
											>
												<td className="px-3 py-2 text-text">
													<div>{ row.reg_no } — { row.name }</div>
													{ row.panel_id ? (
														<div className="mt-0.5 text-text-muted">
															{ formatAttendanceConflictLabel(
																row.attendance_status ||
																	'present'
															) }
														</div>
													) : null }
												</td>
												<td className="px-3 py-2">
													<input
														type="text"
														className="w-full min-w-[12rem] rounded-md border border-border bg-surface px-2 py-1"
														value={ draft.project_title ?? '' }
														disabled={ busy }
														aria-label={ `Project title for ${ row.name }` }
														onChange={ ( e ) =>
															updateDraft( row.student_id, {
																project_title: e.target.value,
															} )
														}
													/>
												</td>
												<td className="px-3 py-2">
													<select
														className="w-full min-w-[8rem] rounded-md border border-border bg-surface px-2 py-1"
														value={ draft.panel_id ?? '' }
														disabled={ busy }
														aria-label={ `Panel for ${ row.name }` }
														onChange={ ( e ) =>
															updateDraft( row.student_id, {
																panel_id: e.target.value,
															} )
														}
													>
														<option value="">Unassigned</option>
														{ panels.map( ( panel ) => (
															<option
																key={ panel.id }
																value={ panel.id }
															>
																{ panel.name }
															</option>
														) ) }
													</select>
												</td>
												<td className="px-3 py-2">
													<div className="flex flex-wrap items-center justify-end gap-2">
														{ row.panel_id && ! reviewMarksLocked ? (
															<Button
																variant="secondary"
																size="sm"
																disabled={ busy }
																onClick={ () =>
																	setCorrectionTarget( {
																		studentId:
																			row.student_id,
																		studentLabel: `${ row.reg_no } — ${ row.name }`,
																		currentStatus:
																			row.attendance_status ||
																			'present',
																	} )
																}
															>
																Correct attendance
															</Button>
														) : null }
														<Button
															variant="primary"
															size="sm"
															disabled={ busy || ! dirty }
															onClick={ () => saveStudentRow( row ) }
														>
															Save
														</Button>
													</div>
												</td>
											</tr>
										);
									} ) }
								</tbody>
							</table>
						</TableScrollWrapper>
					) : (
						<p className="mt-4 text-sm text-text-muted">
							No student assignments for this review round yet.
						</p>
					) }
				</>
			) }

			{ correctionTarget && selectedReviewId ? (
				<CorrectAttendanceDialog
					open
					sessionId={ sessionId }
					reviewId={ selectedReviewId }
					studentId={ correctionTarget.studentId }
					reviewLabel={ `${ selectedReview?.label ?? 'Review' } · ${ correctionTarget.studentLabel }` }
					currentStatus={ correctionTarget.currentStatus }
					onClose={ () => setCorrectionTarget( null ) }
					onSuccess={ async ( status ) => {
						setAttendanceNotice( {
							variant: 'success',
							message: `Attendance for ${ correctionTarget.studentLabel } updated to ${ formatAttendanceConflictLabel( status ) }.`,
						} );
						await loadAssignments( selectedReviewId );
					} }
				/>
			) : null }

			<ConfirmDialog
				open={ confirmAction === 'copy' }
				title="Copy assignments from previous review?"
				description={
					previousReview && selectedReview
						? `This replaces assignments for ${ selectedReview.label } only, using ${ previousReview.label } as the source.`
						: ''
				}
				confirmLabel="Copy assignments"
				onConfirm={ runCopyFromPrevious }
				onCancel={ () => setConfirmAction( null ) }
			/>

			<ConfirmDialog
				open={ confirmAction === 'reset' }
				title="Reset to project defaults?"
				description={
					selectedReview
						? `This replaces assignments for ${ selectedReview.label } only, using the project default Panels and Reviewers template.`
						: ''
				}
				confirmLabel="Reset assignments"
				onConfirm={ runResetToDefaults }
				onCancel={ () => setConfirmAction( null ) }
			/>

			{ ! isWizardTerminalStep ? (
				<div className="mt-6 flex justify-end">
					<Button
						variant="primary"
						onClick={ onContinue }
						disabled={ ! canContinue || busy }
						title={
							! canContinue
								? 'Assign every enrolled student to a panel on every review round'
								: undefined
						}
					>
						Continue to Open reviews
					</Button>
				</div>
			) : null }
		</section>
	);
}
