import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import {
	Button,
	FlaggedMarkChip,
	Notice,
	ShuttleMarkChip,
	PageContentSkeleton,
} from '../../shared/components';
import { get, post } from '../../shared/api';
import {
	fixByLabel,
	formatAttendanceConflictLabel,
	mapMarkApiError,
	unanimousPeerAttendanceGuidance,
} from '../../shared/markErrors';
import {
	MARK_SCORE_STEP,
	validateAttendanceForSave,
	validateCriterionScore,
	validateMarksForSave,
} from '../../shared/markValidation';

function scoresFromStudent( student ) {
	const next = {};
	const raw = student?.scores;
	if ( ! raw ) {
		return next;
	}

	const entries = Array.isArray( raw )
		? raw.map( ( row, index ) => [
				row.criterion_id ?? index,
				row.score,
		  ] )
		: Object.entries( raw );

	for ( const [ critId, val ] of entries ) {
		if ( val !== null && val !== undefined && val !== '' ) {
			next[ Number( critId ) ] = val;
		}
	}

	return next;
}

function flaggedFromStudent( student ) {
	const next = {};
	const raw = student?.flagged;
	if ( ! raw ) {
		return next;
	}

	if ( Array.isArray( raw ) ) {
		for ( const row of raw ) {
			if ( row.flagged && row.criterion_id != null ) {
				next[ Number( row.criterion_id ) ] = true;
			}
		}
		return next;
	}

	for ( const [ critId, val ] of Object.entries( raw ) ) {
		if ( val ) {
			next[ Number( critId ) ] = true;
		}
	}

	return next;
}

export function RubricForm( {
	sessionId,
	reviewId,
	student,
	embedded = false,
	readOnly = false,
	onBack,
	onClose,
	onSaved,
} ) {
	const [ criteria, setCriteria ] = useState( [] );
	const [ attendanceStatus, setAttendanceStatus ] = useState(
		student?.attendance_status === 'absent' ? 'absent' : 'present'
	);
	const [ scores, setScores ] = useState( {} );
	const [ flaggedByCriterion, setFlaggedByCriterion ] = useState( {} );
	const [ shuttleByCriterion, setShuttleByCriterion ] = useState( {} );
	const [ shuttleFromByCriterion, setShuttleFromByCriterion ] = useState( {} );
	const [ fieldErrors, setFieldErrors ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ success, setSuccess ] = useState( '' );
	const liveRef = useRef( null );

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );
		setScores( scoresFromStudent( student ) );
		setFlaggedByCriterion( flaggedFromStudent( student ) );
		setAttendanceStatus(
			student?.attendance_status === 'absent' ? 'absent' : 'present'
		);
		try {
			const [ rubric, marksRes ] = await Promise.all( [
				get(
					`/reviewer/assignments/${ sessionId }/${ reviewId }/rubric`
				),
				get(
					`/sessions/${ sessionId }/reviews/${ reviewId }/students/${ student.id }/marks`
				),
			] );
			setCriteria( rubric.criteria || [] );
			if ( marksRes.attendance_status === 'absent' ) {
				setAttendanceStatus( 'absent' );
			} else if ( marksRes.attendance_status === 'present' ) {
				setAttendanceStatus( 'present' );
			}
			const next = { ...scoresFromStudent( student ) };
			const flagged = { ...flaggedFromStudent( student ) };
			const shuttle = {};
			const shuttleFrom = {};
			for ( const row of marksRes.marks || [] ) {
				const criterionId = Number( row.criterion_id );
				if ( row.score !== null && row.score !== undefined ) {
					next[ criterionId ] = row.score;
				} else {
					next[ criterionId ] = '';
				}
				if ( row.coordinator_overridden ) {
					shuttle[ criterionId ] = true;
					if ( row.overridden_from_score != null ) {
						shuttleFrom[ criterionId ] = row.overridden_from_score;
					}
					flagged[ criterionId ] = false;
				} else if ( row.flagged ) {
					flagged[ criterionId ] = true;
				}
			}
			setScores( next );
			setFlaggedByCriterion( flagged );
			setShuttleByCriterion( shuttle );
			setShuttleFromByCriterion( shuttleFrom );
			setFieldErrors( {} );
		} catch ( err ) {
			const mapped = mapMarkApiError( err );
			setError( mapped );
		} finally {
			setLoading( false );
		}
	}, [ sessionId, reviewId, student ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const saveDraft = async () => {
		setSaving( true );
		setError( null );
		setSuccess( '' );

		const attendanceCheck = validateAttendanceForSave( attendanceStatus );
		if ( attendanceCheck.formError ) {
			setError( attendanceCheck.formError );
			setSaving( false );
			return;
		}

		const isAbsent = attendanceStatus === 'absent';
		let payload = [];

		if ( ! isAbsent ) {
			payload = criteria
				.filter( ( c ) => {
					const val = scores[ c.id ];
					return val !== '' && val !== null && val !== undefined;
				} )
				.map( ( c ) => ( {
					criterion_id: c.id,
					score: Number( scores[ c.id ] ),
				} ) );

			if ( payload.length === 0 ) {
				setError( {
					message: 'Enter at least one score before saving.',
					fixBy: null,
				} );
				setSaving( false );
				return;
			}

			const { fieldErrors: nextFieldErrors, formError } = validateMarksForSave(
				criteria,
				scores,
				'draft'
			);
			if ( formError ) {
				setFieldErrors( nextFieldErrors );
				setError( formError );
				setSaving( false );
				return;
			}
		} else {
			setFieldErrors( {} );
		}

		try {
			await post(
				`/sessions/${ sessionId }/reviews/${ reviewId }/students/${ student.id }/marks`,
				{
					status: 'draft',
					attendance_status: attendanceStatus,
					criteria: payload,
				}
			);
			const msg = 'Draft saved successfully.';
			setSuccess( msg );
			if ( liveRef.current ) {
				liveRef.current.textContent = msg;
			}
			onSaved?.( 'draft' );
		} catch ( err ) {
			setError( mapMarkApiError( err ) );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) {
		return <PageContentSkeleton showTitle={ false } rows={ 4 } />;
	}

	const handleBack = onClose || onBack;
	const isAbsent = attendanceStatus === 'absent';
	const criteriaDisabled = readOnly || isAbsent;
	const currentUserId = window.prAppData?.currentUser?.id ?? 0;
	const unanimousGuidance =
		error?.code === 'attendance_conflict'
			? unanimousPeerAttendanceGuidance(
					error.conflicts,
					attendanceStatus,
					currentUserId
			  )
			: null;

	return (
		<div className={ embedded ? '' : 'mx-auto w-full max-w-[640px]' }>
			{ ! embedded && handleBack ? (
				<button
					type="button"
					onClick={ handleBack }
					className="mb-4 text-sm font-medium text-primary underline"
				>
					← Back to students
				</button>
			) : null }

			{ ! embedded ? (
				<>
					<h2 className="mb-1 text-lg font-semibold text-text">
						{ student.name }
					</h2>
					<p className="mb-6 text-sm text-muted">{ student.reg_no }</p>
				</>
			) : null }

			<div ref={ liveRef } className="sr-only" aria-live="polite" />

			{ error ? (
				<div className="mb-4">
					<Notice variant="error">
						<p>{ error.message }</p>
						{ error.conflicts?.length ? (
							<ul className="mt-2 list-inside list-disc space-y-1 text-sm">
								{ error.conflicts.map( ( row ) => (
									<li key={ row.reviewer_user_id }>
										{ row.reviewer_name ||
											`Reviewer #${ row.reviewer_user_id }` }
										{ ' — ' }
										{ formatAttendanceConflictLabel(
											row.attendance_status
										) }
									</li>
								) ) }
							</ul>
						) : null }
						{ unanimousGuidance ? (
							<p className="mt-2 text-sm opacity-90">{ unanimousGuidance }</p>
						) : null }
						{ fixByLabel( error.fixBy ) ? (
							<p className="mt-1 text-sm opacity-90">
								{ fixByLabel( error.fixBy ) }
							</p>
						) : null }
					</Notice>
				</div>
			) : null }

			{ success ? (
				<div className="mb-4">
					<Notice variant="success">{ success }</Notice>
				</div>
			) : null }

			<form
				className="space-y-4"
				onSubmit={ ( e ) => {
					e.preventDefault();
					if ( ! readOnly ) {
						saveDraft();
					}
				} }
			>
				<fieldset className="rounded-md border border-border p-4">
					<legend className="px-1 text-sm font-medium text-text">
						Attendance
					</legend>
					<div
						className="mt-2 flex flex-wrap gap-4"
						role="radiogroup"
						aria-required="true"
						aria-label="Attendance"
					>
						<label className="inline-flex items-center gap-2 text-sm text-text">
							<input
								type="radio"
								name="attendance"
								value="present"
								disabled={ readOnly }
								checked={ attendanceStatus === 'present' }
								onChange={ () => setAttendanceStatus( 'present' ) }
							/>
							Present
						</label>
						<label className="inline-flex items-center gap-2 text-sm text-text">
							<input
								type="radio"
								name="attendance"
								value="absent"
								disabled={ readOnly }
								checked={ attendanceStatus === 'absent' }
								onChange={ () => setAttendanceStatus( 'absent' ) }
							/>
							Absent
						</label>
					</div>
				</fieldset>

				{ criteria.map( ( c ) => {
					const fieldError = fieldErrors[ c.id ];
					const errorId = `criterion-${ c.id }-error`;

					return (
						<div
							key={ c.id }
							className={ [
								'rounded-md border border-border px-3 py-3 transition-colors hover:bg-surface-raised',
								isAbsent ? 'opacity-50' : null,
							]
								.filter( Boolean )
								.join( ' ' ) }
						>
							<div className="mb-1 flex flex-wrap items-center gap-2">
								<label
									htmlFor={ `criterion-${ c.id }` }
									className="block text-sm font-medium text-text"
								>
									{ c.label }
								</label>
								{ shuttleByCriterion[ c.id ] ? (
									<ShuttleMarkChip
										fromScore={ shuttleFromByCriterion[ c.id ] }
										score={ scores[ c.id ] }
									/>
								) : null }
								{ flaggedByCriterion[ c.id ] ? (
									<FlaggedMarkChip />
								) : null }
							</div>
							<input
								id={ `criterion-${ c.id }` }
								type="number"
								min={ 0 }
								max={ c.max_marks }
								step={ String( MARK_SCORE_STEP ) }
								inputMode="decimal"
								disabled={ criteriaDisabled }
								aria-invalid={ fieldError ? 'true' : 'false' }
								aria-describedby={
									fieldError ? errorId : undefined
								}
								className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm tabular-nums disabled:opacity-60"
								placeholder={ `Score (0–${ c.max_marks })` }
								value={ scores[ c.id ] ?? '' }
								onChange={ ( e ) => {
									setScores( ( prev ) => ( {
										...prev,
										[ c.id ]: e.target.value,
									} ) );
									if ( fieldErrors[ c.id ] ) {
										setFieldErrors( ( prev ) => {
											const next = { ...prev };
											delete next[ c.id ];
											return next;
										} );
									}
								} }
								onBlur={ () => {
									const blurError = validateCriterionScore(
										c,
										scores[ c.id ]
									);
									setFieldErrors( ( prev ) => {
										const next = { ...prev };
										if ( blurError ) {
											next[ c.id ] = blurError;
										} else {
											delete next[ c.id ];
										}
										return next;
									} );
								} }
							/>
							{ fieldError ? (
								<p
									id={ errorId }
									className="mt-1 text-sm text-danger"
									role="alert"
								>
									{ fieldError }
								</p>
							) : null }
						</div>
					);
				} ) }

				{ ! readOnly ? (
					<div className="flex flex-wrap gap-3 pt-4">
						<Button
							type="submit"
							variant="primary"
							icon="save"
							disabled={ saving }
						>
							{ saving ? 'Saving…' : 'Save' }
						</Button>
					</div>
				) : null }
			</form>
		</div>
	);
}
