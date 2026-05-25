import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { Link, useSearchParams } from 'react-router-dom';
import {
	Button,
	ConfirmDialog,
	ContentLoadingRegion,
	FlaggedMarkChip,
	ShuttleMarkChip,
	Notice,
	PageHeader,
	StatusChip,
	TableSkeleton,
} from '../../shared/components';
import { useLoadingPhase } from '../../shared/hooks/useLoadingPhase';
import { Icon } from '../../shared/components/NavIcon';
import { get, post } from '../../shared/api';
import { fixByLabel, mapMarkApiError } from '../../shared/markErrors';
import { MarkingGridStudentCard } from './MarkingGridStudentCard';
import {
	attendanceStatusChip,
	coordinatorOverriddenForCriterion,
	flaggedForCriterion,
	formatScore,
	overriddenFromScoreForCriterion,
	isStudentRowFrozen,
	scoreForCriterion,
	studentStatusChip,
} from './markingGridUtils';
import { TableDataViewport } from '../../shared/TableScrollViewport';
import {
	GRID_ROW_CELL,
	GRID_ROW_GROUP,
	regNoStickyClass,
	regNoStickyStyle,
} from '../../shared/tableStyles';
import { RubricForm } from './RubricForm';
import { ScoreEntryModal } from './ScoreEntryModal';

export function MarkingGrid( { sessionId, reviewId, panelId } ) {
	const [ searchParams, setSearchParams ] = useSearchParams();
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ freezeOpen, setFreezeOpen ] = useState( false );
	const [ freezing, setFreezing ] = useState( false );
	const [ freezeError, setFreezeError ] = useState( null );
	const [ unfreezeOpen, setUnfreezeOpen ] = useState( false );
	const [ unfreezeReason, setUnfreezeReason ] = useState( '' );
	const [ requestingUnfreeze, setRequestingUnfreeze ] = useState( false );
	const [ unfreezeError, setUnfreezeError ] = useState( null );
	const [ unfreezeGrantedNotice, setUnfreezeGrantedNotice ] = useState( false );
	const [ freezeSuccessNotice, setFreezeSuccessNotice ] = useState( false );
	const [ modalStudent, setModalStudent ] = useState( null );
	const prevUnfreezePending = useRef( null );

	const studentParam = searchParams.get( 'student' );

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const res = await get(
				`/reviewer/assignments/${ sessionId }/${ reviewId }/${ panelId }/students`
			);
			setData( res );
		} catch ( err ) {
			setError( mapMarkApiError( err ) );
		} finally {
			setLoading( false );
		}
	}, [ sessionId, reviewId, panelId ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const students = data?.students || [];
	const criteria = data?.criteria || [];
	const coordinatorLocked = Boolean( data?.coordinator_marks_locked );
	const panelFrozen = Boolean( data?.panel_scores_frozen );
	const personalFrozen = Boolean( data?.review_frozen );
	const reviewFrozen =
		personalFrozen || coordinatorLocked || panelFrozen;
	const unfreezePending = data?.unfreeze_request_status === 'pending';

	useEffect( () => {
		const wasPending = prevUnfreezePending.current === true;
		if ( wasPending && ! unfreezePending && ! reviewFrozen ) {
			setUnfreezeGrantedNotice( true );
			setFreezeSuccessNotice( false );
		}
		prevUnfreezePending.current = unfreezePending;
	}, [ unfreezePending, reviewFrozen ] );

	useEffect( () => {
		if ( ! studentParam || students.length === 0 ) {
			return;
		}
		const id = parseInt( studentParam, 10 );
		const match = students.find(
			( s ) => Number( s.id ) === id
		);
		if ( match ) {
			setModalStudent( match );
		}
	}, [ studentParam, students ] );

	const openModal = ( student ) => {
		setModalStudent( student );
		setSearchParams( { student: String( student.id ) } );
	};

	const closeModal = () => {
		setModalStudent( null );
		setSearchParams( {} );
	};

	const handleRequestUnfreeze = async () => {
		const reason = unfreezeReason.trim();
		if ( ! reason ) {
			setUnfreezeError( {
				code: 'unfreeze_reason_required',
				message: 'Please explain why you need to edit frozen scores.',
				fixBy: null,
			} );
			return;
		}

		setRequestingUnfreeze( true );
		setUnfreezeError( null );
		try {
			await post(
				`/reviewer/assignments/${ sessionId }/${ reviewId }/unfreeze-request`,
				{ panel_id: panelId, reason }
			);
			setUnfreezeOpen( false );
			setUnfreezeReason( '' );
			await load();
		} catch ( err ) {
			setUnfreezeError( mapMarkApiError( err ) );
		} finally {
			setRequestingUnfreeze( false );
		}
	};

	const handleFreeze = async () => {
		setFreezing( true );
		setFreezeError( null );
		try {
			await post(
				`/reviewer/assignments/${ sessionId }/${ reviewId }/freeze`,
				{ panel_id: panelId }
			);
			setFreezeOpen( false );
			setFreezeSuccessNotice( true );
			setUnfreezeGrantedNotice( false );
			await load();
		} catch ( err ) {
			setFreezeError( mapMarkApiError( err ) );
		} finally {
			setFreezing( false );
		}
	};

	const gridTemplate = useMemo( () => {
		const criterionCols = criteria
			.map( () => 'minmax(3.5rem, 1fr)' )
			.join( ' ' );
		return `2.5rem 5.5rem 9rem 6rem 5.5rem ${ criterionCols } minmax(6rem, auto)`;
	}, [ criteria ] );

	const regNoSticky = regNoStickyStyle( { serialNoBefore: true } );

	const { showSkeleton, showOverlay } = useLoadingPhase( loading, data !== null );

	if ( error && ! data ) {
		return (
			<>
				<Link
					to="/"
					className="mb-4 inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
				>
					<Icon name="arrow-left" className="h-4 w-4 shrink-0" />
					Back to assignments
				</Link>
				<Notice variant="error">
					<p>{ error.message }</p>
					{ fixByLabel( error.fixBy ) ? (
						<p className="mt-1 text-sm opacity-90">{ fixByLabel( error.fixBy ) }</p>
					) : null }
				</Notice>
			</>
		);
	}

	return (
		<>
			<Link
				to="/"
				className="mb-4 inline-flex items-center gap-2 text-sm font-medium text-primary hover:underline"
			>
				<Icon name="arrow-left" className="h-4 w-4 shrink-0" />
				Back to assignments
			</Link>

			<PageHeader
				title={ data?.review_label || 'Marking' }
				description={ `${ data?.session_title || '' } · ${ data?.panel_name || '' }` }
				actions={
					coordinatorLocked ? (
						<StatusChip variant="confirmed" label="Locked by coordinator" />
					) : panelFrozen ? (
						<StatusChip variant="confirmed" label="Panel frozen" icon="lock" />
					) : personalFrozen ? (
						<div className="flex flex-wrap items-center gap-2">
							{ unfreezePending ? (
								<StatusChip
									variant="unlocked"
									label="Unfreeze requested — awaiting panel coordinator"
								/>
							) : (
								<>
									<StatusChip
										variant="confirmed"
										label="Frozen"
										icon="lock"
									/>
									<Button
										type="button"
										variant="secondary"
										icon="unlock"
										onClick={ () => {
											setUnfreezeReason( '' );
											setUnfreezeError( null );
											setUnfreezeOpen( true );
										} }
									>
										Request unfreeze
									</Button>
								</>
							) }
						</div>
					) : (
						<Button
							type="button"
							variant="primary"
							icon="lock"
							disabled={ students.length === 0 }
							onClick={ () => setFreezeOpen( true ) }
						>
							Freeze scores
						</Button>
					)
				}
			/>

			{ freezeSuccessNotice ? (
				<div className="mb-4">
					<Notice variant="success">
						Scores frozen for this review. Your marks are submitted and
						read-only until your panel coordinator approves an unfreeze request.
					</Notice>
				</div>
			) : null }

			{ panelFrozen && personalFrozen ? (
				<div className="mb-4">
					<Notice variant="info">
						The panel is frozen. You cannot request a personal unfreeze until
						your panel coordinator requests a panel unfreeze from the project
						coordinator.
					</Notice>
				</div>
			) : null }

			{ unfreezeGrantedNotice ? (
				<div className="mb-4">
					<Notice variant="success">
						Your panel coordinator approved unfreeze. You can edit scores and
						freeze again when ready.
					</Notice>
				</div>
			) : null }

			<ContentLoadingRegion
				busy={ showOverlay }
				variant="overlay"
				label="Loading marking grid"
			>
			{ showSkeleton ? (
				<TableSkeleton rows={ 10 } columns={ 6 } />
			) : students.length === 0 ? (
				<p className="text-sm text-muted">No students on this panel.</p>
			) : (
				<>
					<section
						className="lg:hidden"
						aria-labelledby="marking-students-heading"
					>
						<h2
							id="marking-students-heading"
							className="sr-only"
						>
							Students
						</h2>
						<ul className="flex flex-col gap-3">
							{ students.map( ( student, rowIndex ) => (
								<li key={ student.id }>
									<MarkingGridStudentCard
										rowIndex={ rowIndex }
										student={ student }
										criteria={ criteria }
										reviewFrozen={ reviewFrozen }
										onUpdateScore={ openModal }
									/>
								</li>
							) ) }
						</ul>
					</section>

					<TableDataViewport
						className="hidden lg:block"
						bodyRowCount={ students.length }
						rowHeightVariant="dense"
					>
						<div
							className="w-max min-w-full"
							role="table"
					style={ {
						display: 'grid',
						gridTemplateColumns: gridTemplate,
					} }
				>
					<div className="contents font-medium" role="row">
						<div
							className="border-b border-border bg-surface px-2 py-2 text-center text-xs uppercase tracking-wide text-muted"
							role="columnheader"
							scope="col"
						>
							No.
						</div>
						<div
							className={ `${ regNoStickyClass( { header: true } ) } border-b border-border px-2 py-2 text-left text-xs uppercase tracking-wide text-muted` }
							style={ regNoSticky }
							role="columnheader"
							scope="col"
						>
							Reg no
						</div>
						<div
							className="border-b border-border bg-surface px-3 py-2 text-left text-xs uppercase tracking-wide text-muted"
							role="columnheader"
							scope="col"
						>
							Student
						</div>
						<div
							className="border-b border-border bg-surface px-2 py-2 text-left text-xs uppercase tracking-wide text-muted"
							role="columnheader"
							scope="col"
						>
							Attendance
						</div>
						<div
							className="border-b border-border bg-surface px-2 py-2 text-left text-xs uppercase tracking-wide text-muted"
							role="columnheader"
							scope="col"
						>
							Status
						</div>
						{ criteria.map( ( c ) => (
							<div
								key={ c.id }
								className="border-b border-border bg-surface px-2 py-2 text-center text-xs uppercase tracking-wide text-muted"
								role="columnheader"
								scope="col"
								title={ c.label }
							>
								<span className="block truncate sm:max-w-none">{ c.label }</span>
							</div>
						) ) }
						<div
							className="border-b border-border bg-surface px-2 py-2 text-right text-xs uppercase tracking-wide text-muted"
							role="columnheader"
							scope="col"
						>
							Action
						</div>
					</div>

					{ students.map( ( student, rowIndex ) => {
						const chip = studentStatusChip( student.mark_status );
						const attendanceChip = attendanceStatusChip(
							student.attendance_status
						);
						const isAbsent = student.attendance_status === 'absent';
						const rowFrozen = isStudentRowFrozen(
							reviewFrozen,
							student
						);

						return (
							<div
								key={ student.id }
								className={ GRID_ROW_GROUP }
								role="row"
							>
								<div
									className={ `border-b border-border px-2 py-2 text-center text-sm tabular-nums text-text-muted ${ GRID_ROW_CELL }` }
									role="cell"
								>
									{ rowIndex + 1 }
								</div>
								<div
									className={ `${ regNoStickyClass() } border-b border-border px-2 py-2 text-sm tabular-nums text-muted ${ GRID_ROW_CELL }` }
									style={ regNoSticky }
									role="cell"
								>
									{ student.reg_no }
								</div>
								<div
									className={ `border-b border-border px-3 py-2 text-sm font-medium text-text ${ GRID_ROW_CELL }` }
									role="cell"
								>
									{ student.name }
								</div>
								<div
									className={ `border-b border-border px-2 py-2 ${ GRID_ROW_CELL }` }
									role="cell"
								>
									<StatusChip
										variant={ attendanceChip.variant }
										label={ attendanceChip.label }
									/>
								</div>
								<div
									className={ `border-b border-border px-2 py-2 ${ GRID_ROW_CELL }` }
									role="cell"
								>
									<StatusChip
										variant={ chip.variant }
										label={ chip.label }
										icon={ chip.icon }
									/>
								</div>
								{ criteria.map( ( c ) => {
									const score = isAbsent
										? null
										: scoreForCriterion( student, c.id );
									const isShuttle = coordinatorOverriddenForCriterion(
										student,
										c.id
									);
									const isFlagged = flaggedForCriterion(
										student,
										c.id
									);

									return (
										<div
											key={ c.id }
											className={ `border-b border-border px-2 py-2 text-center text-sm tabular-nums ${ GRID_ROW_CELL }` }
											role="cell"
										>
											<span className="inline-flex flex-wrap items-center justify-center gap-1">
												{ formatScore( score ) }
												{ isShuttle ? (
													<ShuttleMarkChip
														fromScore={ overriddenFromScoreForCriterion(
															student,
															c.id
														) }
														score={ score }
													/>
												) : null }
												{ isFlagged ? <FlaggedMarkChip /> : null }
											</span>
										</div>
									);
								} ) }
								<div
									className={ `border-b border-border px-2 py-2 text-right ${ GRID_ROW_CELL }` }
									role="cell"
								>
									<Button
										type="button"
										variant="secondary"
										icon="pencil"
										disabled={ rowFrozen }
										onClick={ () => openModal( student ) }
									>
										Update score
									</Button>
								</div>
							</div>
						);
					} ) }
						</div>
					</TableDataViewport>
				</>
			) }
			</ContentLoadingRegion>

			<ConfirmDialog
				open={ freezeOpen }
				title="Freeze scores for this review?"
				consequences={ [
					'All scores on this panel will be finalized for coordinator reports.',
					'You will not be able to edit marks until a coordinator intervenes.',
				] }
				confirmLabel={ freezing ? 'Freezing…' : 'Freeze scores' }
				confirmIcon="lock"
				onConfirm={ handleFreeze }
				onCancel={ () => {
					setFreezeOpen( false );
					setFreezeError( null );
				} }
			>
				{ freezeError ? (
					<Notice variant="error">
						<p className="whitespace-pre-line">{ freezeError.message }</p>
						{ fixByLabel( freezeError.fixBy ) ? (
							<p className="mt-1 text-sm opacity-90">
								{ fixByLabel( freezeError.fixBy ) }
							</p>
						) : null }
					</Notice>
				) : null }
			</ConfirmDialog>

			<ConfirmDialog
				open={ unfreezeOpen }
				title="Request unfreeze?"
				consequences={ [
					'Your panel coordinator must approve before you can edit scores again.',
					'Your scores stay frozen until approval.',
				] }
				confirmLabel={ requestingUnfreeze ? 'Requesting…' : 'Request unfreeze' }
				confirmDisabled={ requestingUnfreeze || ! unfreezeReason.trim() }
				onConfirm={ handleRequestUnfreeze }
				onCancel={ () => {
					setUnfreezeOpen( false );
					setUnfreezeReason( '' );
					setUnfreezeError( null );
				} }
			>
				{ unfreezeError ? (
					<Notice variant="error" className="mb-4">
						<p>{ unfreezeError.message }</p>
						{ fixByLabel( unfreezeError.fixBy ) ? (
							<p className="mt-1 text-sm opacity-90">
								{ fixByLabel( unfreezeError.fixBy ) }
							</p>
						) : null }
					</Notice>
				) : null }
				<div>
					<label
						htmlFor="unfreeze-reason"
						className="block text-sm font-medium text-text"
					>
						Why do you need to edit scores?
					</label>
					<textarea
						id="unfreeze-reason"
						rows={ 4 }
						maxLength={ 500 }
						value={ unfreezeReason }
						onChange={ ( e ) => {
							setUnfreezeReason( e.target.value );
							if ( unfreezeError?.code === 'unfreeze_reason_required' ) {
								setUnfreezeError( null );
							}
						} }
						className="mt-2 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text"
						placeholder="e.g. I entered the wrong score for one student."
						required
					/>
					<p className="mt-1 text-xs text-text-muted">
						{ unfreezeReason.length }/500 characters
					</p>
				</div>
			</ConfirmDialog>

			<ScoreEntryModal
				open={ Boolean( modalStudent ) }
				title={
					modalStudent
						? `${ modalStudent.name } (${ modalStudent.reg_no })`
						: ''
				}
				onClose={ closeModal }
			>
				{ modalStudent ? (
					<RubricForm
						key={ modalStudent.id }
						embedded
						sessionId={ sessionId }
						reviewId={ reviewId }
						student={ modalStudent }
						readOnly={
							reviewFrozen || modalStudent.mark_status === 'frozen'
						}
						onClose={ closeModal }
						onSaved={ async () => {
							await load();
							closeModal();
						} }
					/>
				) : null }
			</ScoreEntryModal>
		</>
	);
}
