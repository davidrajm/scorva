import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { del, get, post, put } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import { TableScrollWrapper } from '../../shared/TableScrollViewport';
import { TABLE_BODY_ROW_SOFT } from '../../shared/tableStyles';
import {
	Button,
	ConfirmDialog,
	ContentLoadingRegion,
	Notice,
	PageContentSkeleton,
	PageHeader,
	WizardNav,
	useToast,
} from '../../shared/components';

const CONFIRM_WITH_SCORES_PHRASE = 'Confirm';
import { useLoadingPhase } from '../../shared/hooks/useLoadingPhase';
import { CsvImportMapper } from '../components/CsvImportMapper';
import { PanelReviewersStep } from '../components/PanelReviewersStep';
import { PanelsStep } from '../components/PanelsStep';
import { ReviewAssignmentsStep } from '../components/ReviewAssignmentsStep';
import { ReviewMarkingStep } from '../components/ReviewMarkingStep';
import { ReviewsSetupStep } from '../components/ReviewsSetupStep';

const STEPS = [
	'students',
	'panels',
	'reviewers',
	'reviews',
	'assignments',
	'marking',
];

export function SessionWizard() {
	const { id } = useParams();
	const sessionId = Number( id );
	const navigate = useNavigate();
	const [ searchParams, setSearchParams ] = useSearchParams();
	const stepParam = searchParams.get( 'step' );
	const resolvedStep = stepParam === 'rubrics' ? 'reviews' : stepParam;
	const currentStep = STEPS.includes( resolvedStep )
		? resolvedStep
		: 'students';

	const [ reviewsHubReloadTick, setReviewsHubReloadTick ] = useState( 0 );

	useEffect( () => {
		if ( stepParam === 'rubrics' ) {
			setSearchParams( { step: 'reviews' }, { replace: true } );
		}
	}, [ stepParam, setSearchParams ] );

	const [ session, setSession ] = useState( null );
	const [ wizardState, setWizardState ] = useState( null );
	const [ enrolled, setEnrolled ] = useState( [] );
	const [ panels, setPanels ] = useState( [] );
	const [ reviewers, setReviewers ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ openingSession, setOpeningSession ] = useState( false );
	const [ showStudentImport, setShowStudentImport ] = useState( false );
	const [ showAddStudentForm, setShowAddStudentForm ] = useState( false );
	const [ addStudentForm, setAddStudentForm ] = useState( {
		reg_no: '',
		name: '',
		program: '',
		batch: '',
		guide_emp_id: '',
		guide_name: '',
		project_title: '',
	} );
	const [ addingStudent, setAddingStudent ] = useState( false );
	const [ deleteAllDialogOpen, setDeleteAllDialogOpen ] = useState( false );
	const [ deleteAllScoresDialogOpen, setDeleteAllScoresDialogOpen ] =
		useState( false );
	const [ deleteAllPhrase, setDeleteAllPhrase ] = useState( '' );
	const [ deletingAllStudents, setDeletingAllStudents ] = useState( false );
	const [ removeStudentId, setRemoveStudentId ] = useState( null );
	const [ dirtyFields, setDirtyFields ] = useState( {} );
	const markDirty = ( key ) => setDirtyFields( ( prev ) => ( { ...prev, [ key ]: true } ) );
	const markClean = ( key ) => setDirtyFields( ( prev ) => { const next = { ...prev }; delete next[ key ]; return next; } );

	const refreshSessionData = useCallback(
		async ( { showLoading = false } = {} ) => {
			if ( ! sessionId ) {
				return;
			}
			if ( showLoading ) {
				setLoading( true );
			}
			try {
				const [
					sessionData,
					stateData,
					studentsData,
					panelsData,
					reviewersData,
				] = await Promise.all( [
					get( `/sessions/${ sessionId }` ),
					get( `/sessions/${ sessionId }/wizard-state` ),
					get( `/sessions/${ sessionId }/students` ),
					get( `/sessions/${ sessionId }/panels` ),
					get( `/sessions/${ sessionId }/reviewers` ),
				] );
				setSession( sessionData );
				setWizardState( stateData );
				setEnrolled( studentsData.students ?? [] );
				setPanels( panelsData.panels ?? [] );
				setReviewers( reviewersData.reviewers ?? [] );
				setReviewsHubReloadTick( ( t ) => t + 1 );
			} catch {
				toast( {
					variant: 'error',
					message: 'Could not load project. It may have been removed.',
				} );
			} finally {
				if ( showLoading ) {
					setLoading( false );
				}
			}
		},
		[ sessionId ]
	);

	const loadAll = useCallback( async () => {
		await refreshSessionData( { showLoading: true } );
	}, [ refreshSessionData ] );

	const handleEnrolImportSuccess = useCallback( ( { variant, message } ) => {
		if ( message ) {
			toast( { variant: variant ?? 'success', message } );
		}
	}, [] );

	const handleEnrolImportComplete = useCallback( async () => {
		await refreshSessionData( { showLoading: false } );
	}, [ refreshSessionData ] );

	const refreshReviewers = useCallback( async () => {
		if ( ! sessionId ) {
			return [];
		}
		try {
			const data = await get( `/sessions/${ sessionId }/reviewers` );
			const items = data.reviewers ?? [];
			setReviewers( items );
			return items;
		} catch {
			return [];
		}
	}, [ sessionId ] );

	useEffect( () => {
		loadAll();
	}, [ loadAll ] );

	const enrolledCount = wizardState?.enrolled_count ?? 0;

	const enrolledHasScores = useMemo(
		() => enrolled.some( ( row ) => row.has_scores ),
		[ enrolled ]
	);

	const completedSteps = useMemo( () => {
		const done = [];
		if ( enrolledCount > 0 ) {
			done.push( 'students' );
		}
		if (
			enrolledCount > 0 &&
			( wizardState?.unassigned_count ?? 0 ) === 0
		) {
			done.push( 'panels' );
		}
		if ( reviewers.some( ( row ) => row.user_id ) ) {
			done.push( 'reviewers' );
		}
		if ( wizardState?.can_advance_to_reviews ) {
			done.push( 'reviews' );
		}
		if ( wizardState?.assignments_complete ) {
			done.push( 'assignments' );
			done.push( 'marking' );
		}
		return done;
	}, [ wizardState, reviewers, enrolledCount ] );

	const blockedSteps = useMemo( () => {
		const blocked = {};
		const rosterBlocker = 'Add at least one student to the project roster first.';

		if ( enrolledCount === 0 ) {
			STEPS.slice( 1 ).forEach( ( key ) => {
				blocked[ key ] = rosterBlocker;
			} );
			return blocked;
		}

		if ( ! wizardState?.can_advance_to_panels ) {
			blocked.panels = rosterBlocker;
		}
		if ( ! wizardState?.can_advance_to_reviewers ) {
			const count = wizardState?.unassigned_count ?? 0;
			blocked.reviewers =
				count > 0
					? `${ count } student${ count === 1 ? '' : 's' } still unassigned to a project default panel.`
					: 'Complete the Panels step first.';
		}
		if ( ! wizardState?.can_advance_to_rubrics ) {
			blocked.reviews =
				'Add linked reviewers on the Reviewers step first.';
		}
		if ( ! wizardState?.assignments_complete ) {
			blocked.marking =
				'Complete panel assignments for every review round first.';
		}

		return blocked;
	}, [ wizardState, enrolledCount ] );

	const goToStep = ( step ) => {
		if ( blockedSteps[ step ] ) {
			return;
		}
		setSearchParams( { step } );
	};

	const goNext = () => {
		const index = STEPS.indexOf( currentStep );
		const next = STEPS[ index + 1 ];
		if ( next && ! blockedSteps[ next ] ) {
			setSearchParams( { step: next } );
		}
	};

	const openSessionForMarking = async () => {
		if ( ! sessionId || session?.status === 'active' ) {
			return;
		}
		setOpeningSession( true );
		try {
			const updated = await put( `/sessions/${ sessionId }`, {
				status: 'active',
			} );
			setSession( updated );
			toast( {
				variant: 'success',
				message:
					'Project is now active. Reviewers can see assignments when rubrics are confirmed and marking is on.',
			} );
		} catch {
			toast( {
				variant: 'error',
				message: 'Could not open project for marking.',
			} );
		} finally {
			setOpeningSession( false );
		}
	};

	const saveEnrolmentField = async ( studentId, fields, localPatch, successMessage ) => {
		try {
			await put( `/sessions/${ sessionId }/students/${ studentId }`, fields );
			setEnrolled( ( rows ) =>
				rows.map( ( row ) =>
					row.student?.id === studentId ? { ...row, ...localPatch } : row
				)
			);
			if ( successMessage ) {
				toast( { variant: 'success', message: successMessage } );
			}
		} catch {
			toast( { variant: 'error', message: 'Could not save enrolment details.' } );
		}
	};

	const saveProjectTitle = async ( studentId, projectTitle ) => {
		await saveEnrolmentField(
			studentId,
			{ project_title: projectTitle },
			{ project_title: projectTitle },
			'Project title saved.'
		);
	};

	const saveGuideField = async ( studentId, field, value ) => {
		const label = field === 'guide_emp_id' ? 'Guide emp. ID saved.' : 'Guide name saved.';
		await saveEnrolmentField( studentId, { [ field ]: value }, { [ field ]: value }, label );
	};

	const assignPanel = async ( studentId, panelId ) => {
		const panel = panels.find( ( p ) => p.id === panelId );
		try {
			await put( `/sessions/${ sessionId }/students/${ studentId }`, {
				panel_id: panelId || null,
			} );
			setEnrolled( ( rows ) =>
				rows.map( ( row ) =>
					row.student?.id === studentId
						? { ...row, panel_id: panelId || null, panel_name: panel?.name ?? null }
						: row
				)
			);
			toast( { variant: 'success', message: 'Panel assigned.' } );
		} catch ( err ) {
			if ( err?.code === 'pr_panel_change_blocked' ) {
				toast( {
					variant: 'error',
					message: parseApiErrorMessage(
						err,
						'This student has scores recorded. Use the Review Assignments step to reassign.'
					),
				} );
			} else {
				toast( { variant: 'error', message: 'Could not save enrolment details.' } );
			}
		}
	};

	const removeEnrolment = async ( studentId ) => {
		try {
			await del( `/sessions/${ sessionId }/students/${ studentId }` );
			toast( { variant: 'success', message: 'Student removed from this project.' } );
			loadAll();
		} catch ( err ) {
			toast( {
				variant: 'error',
				message: parseApiErrorMessage( err, 'Could not remove student.' ),
			} );
		}
	};

	const closeDeleteAllDialogs = () => {
		setDeleteAllDialogOpen( false );
		setDeleteAllScoresDialogOpen( false );
		setDeleteAllPhrase( '' );
	};

	const handleDeleteAllStudents = async () => {
		setDeletingAllStudents( true );
		try {
			const payload = enrolledHasScores
				? { confirm_with_scores: CONFIRM_WITH_SCORES_PHRASE }
				: undefined;
			const result = await del(
				`/sessions/${ sessionId }/students`,
				payload
			);
			closeDeleteAllDialogs();
			const removed = result?.removed ?? 0;
			const registryDeleted = result?.registry_deleted ?? 0;
			let message = `Removed ${ removed } student${
				removed === 1 ? '' : 's'
			} from this project.`;
			if ( registryDeleted > 0 ) {
				message += ` ${ registryDeleted } also removed from All Students (not enrolled elsewhere).`;
			}
			toast( { variant: 'success', message } );
			await refreshSessionData( { showLoading: false } );
		} catch ( err ) {
			toast( {
				variant: 'error',
				message: parseApiErrorMessage(
					err,
					'Could not remove all students.'
				),
			} );
		} finally {
			setDeletingAllStudents( false );
		}
	};

	const onDeleteAllFirstConfirm = () => {
		if ( enrolledHasScores ) {
			setDeleteAllDialogOpen( false );
			setDeleteAllScoresDialogOpen( true );
			return;
		}
		handleDeleteAllStudents();
	};

	const phraseMatchesDeleteAllScores =
		deleteAllPhrase.trim() === CONFIRM_WITH_SCORES_PHRASE;

	const addStudentToProject = async ( event ) => {
		event.preventDefault();
		setAddingStudent( true );
		try {
			const result = await post( `/sessions/${ sessionId }/students`, {
				reg_no: addStudentForm.reg_no.trim(),
				name: addStudentForm.name.trim(),
				program: addStudentForm.program.trim(),
				batch: addStudentForm.batch.trim(),
				guide_emp_id: addStudentForm.guide_emp_id.trim(),
				guide_name: addStudentForm.guide_name.trim(),
				project_title: addStudentForm.project_title.trim(),
			} );
			if ( result?.student ) {
				setEnrolled( ( rows ) => {
					const exists = rows.some(
						( row ) => row.student?.id === result.student.student?.id
					);
					if ( exists ) {
						return rows.map( ( row ) =>
							row.student?.id === result.student.student?.id
								? result.student
								: row
						);
					}
					return [ ...rows, result.student ];
				} );
			}
			setAddStudentForm( { reg_no: '', name: '', program: '', batch: '', guide_emp_id: '', guide_name: '', project_title: '' } );
			setShowAddStudentForm( false );
			toast( {
				variant: 'success',
				message: 'Student added to this project.',
			} );
			await refreshSessionData( { showLoading: false } );
		} catch ( err ) {
			toast( {
				variant: 'error',
				message: parseApiErrorMessage(
					err,
					'Could not add student to this project.'
				),
			} );
		} finally {
			setAddingStudent( false );
		}
	};

	const toast = useToast();

	const { showSkeleton } = useLoadingPhase( loading, session !== null );

	if ( ! loading && ! session ) {
		return (
			<PageHeader
				title="Project not found"
				description="Return to the dashboard and pick another project."
			/>
		);
	}

	if ( showSkeleton ) {
		return (
			<>
				<PageHeader
					title="Project setup"
					description="Enrol students and panels, set up reviewers, define review rounds and rubrics, assign panels per round, then open or pause marking."
				/>
				<WizardNav
					currentStep={ currentStep }
					completedSteps={ [] }
					blockedSteps={ [] }
					onStepClick={ () => {} }
				/>
				<ContentLoadingRegion
					busy
					variant="inline"
					label="Loading project"
					className="mt-6"
				>
					<PageContentSkeleton rows={ 4 } />
				</ContentLoadingRegion>
			</>
		);
	}

	return (
		<>
			<PageHeader
				title={ session.title || 'Project setup' }
				description="Enrol students and panels, set up reviewers, define review rounds and rubrics, assign panels per round, then open or pause marking."
				actions={
					<Button variant="secondary" onClick={ () => navigate( '/' ) }>
						Back to dashboard
					</Button>
				}
			/>


			{ session.status === 'draft' ? (
				<div className="mb-6 mt-4 flex flex-col gap-3 rounded-md border border-border bg-surface-raised p-4 sm:flex-row sm:items-center sm:justify-between">
					<div>
						<p className="text-sm font-medium text-text">Draft project</p>
						<p className="mt-1 text-sm text-text-muted">
							Reviewers will not see assignments until you open this project
							for marking. Confirm rubrics on Reviews & rubrics, complete
							panel assignments, then start each round on Open reviews.
						</p>
					</div>
					<Button
						variant="primary"
						disabled={ openingSession }
						onClick={ openSessionForMarking }
					>
						{ openingSession ? 'Opening…' : 'Open for marking' }
					</Button>
				</div>
			) : null }

			{ session.status === 'closed' ? (
				<div className="mt-4">
					<Notice variant="warning">
						This project is closed. Reviewers cannot submit new marks.
					</Notice>
				</div>
			) : null }

			<WizardNav
				currentStep={ currentStep }
				completedSteps={ completedSteps }
				blockedSteps={ blockedSteps }
				onStepClick={ goToStep }
			/>

			{ currentStep === 'students' ? (
				<section>
					<h2 className="text-lg font-semibold text-text">Add students</h2>
					<p className="mt-1 text-sm text-text-muted">
						Import or add students to this project. New registration numbers are
						saved to the{' '}
						<Link
							to="/registry"
							className="font-medium text-primary hover:underline"
						>
							student directory
						</Link>{ ' ' }
						automatically. Custom fields can be managed there.
					</p>

					<div className="mt-4 flex flex-wrap gap-2">
						<Button
							variant="primary"
							onClick={ () => {
								setShowAddStudentForm( ( value ) => ! value );
								setShowStudentImport( false );
							} }
						>
							{ showAddStudentForm ? 'Hide form' : 'Add student' }
						</Button>
						<Button
							variant="secondary"
							onClick={ () => {
								setShowStudentImport( ( value ) => ! value );
								setShowAddStudentForm( false );
							} }
						>
							{ showStudentImport ? 'Hide import' : 'Import Students' }
						</Button>
					</div>

					{ showAddStudentForm ? (
						<form
							className="mt-4 max-w-xl space-y-3 rounded-md border border-border bg-surface-raised p-4"
							onSubmit={ addStudentToProject }
						>
							<div>
								<label
									htmlFor="wizard-student-reg_no"
									className="block text-sm font-medium text-text"
								>
									Registration number
								</label>
								<input
									id="wizard-student-reg_no"
									data-testid="pr-wizard-student-reg-no"
									type="text"
									required
									value={ addStudentForm.reg_no }
									onChange={ ( e ) =>
										setAddStudentForm( ( form ) => ( {
											...form,
											reg_no: e.target.value,
										} ) )
									}
									className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
								/>
							</div>
							<div>
								<label
									htmlFor="wizard-student-name"
									className="block text-sm font-medium text-text"
								>
									Name
								</label>
								<input
									id="wizard-student-name"
									data-testid="pr-wizard-student-name"
									type="text"
									required
									value={ addStudentForm.name }
									onChange={ ( e ) =>
										setAddStudentForm( ( form ) => ( {
											...form,
											name: e.target.value,
										} ) )
									}
									className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
								/>
							</div>
							<div className="grid gap-3 sm:grid-cols-2">
								<div>
									<label
										htmlFor="wizard-student-program"
										className="block text-sm font-medium text-text"
									>
										Program
									</label>
									<input
										id="wizard-student-program"
										data-testid="pr-wizard-student-program"
										type="text"
										value={ addStudentForm.program }
										onChange={ ( e ) =>
											setAddStudentForm( ( form ) => ( {
												...form,
												program: e.target.value,
											} ) )
										}
										className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
									/>
								</div>
								<div>
									<label
										htmlFor="wizard-student-batch"
										className="block text-sm font-medium text-text"
									>
										Batch
									</label>
									<input
										id="wizard-student-batch"
										data-testid="pr-wizard-student-batch"
										type="text"
										value={ addStudentForm.batch }
										onChange={ ( e ) =>
											setAddStudentForm( ( form ) => ( {
												...form,
												batch: e.target.value,
											} ) )
										}
										className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
									/>
								</div>
								<div>
									<label
										htmlFor="wizard-student-guide-emp-id"
										className="block text-sm font-medium text-text"
									>
										Guide emp. ID{ ' ' }
										<span className="text-text-muted font-normal">(optional)</span>
									</label>
									<input
										id="wizard-student-guide-emp-id"
										type="text"
										value={ addStudentForm.guide_emp_id }
										onChange={ ( e ) =>
											setAddStudentForm( ( form ) => ( {
												...form,
												guide_emp_id: e.target.value,
											} ) )
										}
										className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
									/>
								</div>
								<div>
									<label
										htmlFor="wizard-student-guide-name"
										className="block text-sm font-medium text-text"
									>
										Guide name{ ' ' }
										<span className="text-text-muted font-normal">(optional)</span>
									</label>
									<input
										id="wizard-student-guide-name"
										type="text"
										value={ addStudentForm.guide_name }
										onChange={ ( e ) =>
											setAddStudentForm( ( form ) => ( {
												...form,
												guide_name: e.target.value,
											} ) )
										}
										className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
									/>
								</div>
							</div>
							<div>
								<label
									htmlFor="wizard-student-project-title"
									className="block text-sm font-medium text-text"
								>
									Project title{ ' ' }
									<span className="text-text-muted font-normal">(optional)</span>
								</label>
								<input
									id="wizard-student-project-title"
									type="text"
									value={ addStudentForm.project_title }
									onChange={ ( e ) =>
										setAddStudentForm( ( form ) => ( {
											...form,
											project_title: e.target.value,
										} ) )
									}
									className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
								/>
							</div>
							<Button
								type="submit"
								variant="primary"
								disabled={ addingStudent }
							>
								{ addingStudent ? 'Adding…' : 'Add to project' }
							</Button>
						</form>
					) : null }

					{ showStudentImport ? (
						<CsvImportMapper
							importType="session-enrol"
							sessionId={ sessionId }
							onImportSuccess={ handleEnrolImportSuccess }
							onComplete={ handleEnrolImportComplete }
						/>
					) : null }

					<div className="mt-6">
						<div className="flex flex-wrap items-center justify-between gap-2">
							<h3 className="text-base font-semibold text-text">
								Students Added to this Project ({ enrolled.length })
							</h3>
							{ enrolled.length > 0 ? (
								<Button
									type="button"
									variant="destructive"
									size="sm"
									data-testid="pr-wizard-delete-all-students"
									onClick={ () => setDeleteAllDialogOpen( true ) }
								>
									Delete all students
								</Button>
							) : null }
						</div>
						{ enrolled.length === 0 ? (
							<p className="mt-2 text-sm text-warning">
								Add at least one student before continuing to Panels.
							</p>
						) : (
							<TableScrollWrapper className="mt-2">
								<table className="min-w-full divide-y divide-border text-sm">
									<thead className="bg-surface-raised text-left text-text-muted">
										<tr>
											<th className="px-3 py-2 font-medium">Reg no</th>
											<th className="px-3 py-2 font-medium">Name</th>
											<th className="px-3 py-2 font-medium">Program</th>
											<th className="px-3 py-2 font-medium">Batch</th>
											<th className="px-3 py-2 font-medium">
												Guide emp. ID
											</th>
											<th className="px-3 py-2 font-medium">Guide name</th>
											<th className="px-3 py-2 font-medium">Panel</th>
											<th className="px-3 py-2 font-medium">Project title</th>
											<th className="px-3 py-2 font-medium text-right">
												Actions
											</th>
										</tr>
									</thead>
									<tbody className="divide-y divide-border bg-surface">
										{ enrolled.map( ( row ) => (
											<tr
												key={ row.enrolment_id }
												className={ `group ${ TABLE_BODY_ROW_SOFT }` }
											>
												<td className="whitespace-nowrap px-3 py-2 text-text">
													{ row.student?.reg_no }
												</td>
												<td className="px-3 py-2 text-text">
													{ row.student?.name }
												</td>
												<td className="px-3 py-2 text-text">
													{ row.student?.program || '—' }
												</td>
												<td className="px-3 py-2 text-text">
													{ row.student?.batch || '—' }
												</td>
												<td className="px-3 py-2">
													<input
														type="text"
														className="w-full min-w-[6rem] rounded-md border border-border bg-surface px-2 py-1 text-sm"
														value={ row.guide_emp_id ?? '' }
														placeholder="Guide emp. ID"
														onChange={ ( e ) => {
															markDirty( `${ row.enrolment_id }-guide_emp_id` );
															setEnrolled( ( rows ) =>
																rows.map( ( item ) =>
																	item.enrolment_id === row.enrolment_id
																		? { ...item, guide_emp_id: e.target.value }
																		: item
																)
															);
														} }
														onKeyDown={ ( e ) => { if ( e.key === 'Enter' ) { e.preventDefault(); e.target.blur(); } } }
														onBlur={ ( e ) => {
															markClean( `${ row.enrolment_id }-guide_emp_id` );
															saveGuideField( row.student.id, 'guide_emp_id', e.target.value );
														} }
													/>
													{ dirtyFields[ `${ row.enrolment_id }-guide_emp_id` ] && (
														<p className="mt-0.5 text-xs text-text-muted">↵ Enter to save</p>
													) }
												</td>
												<td className="px-3 py-2">
													<input
														type="text"
														className="w-full min-w-[8rem] rounded-md border border-border bg-surface px-2 py-1 text-sm"
														value={ row.guide_name ?? '' }
														placeholder="Guide name"
														onChange={ ( e ) => {
															markDirty( `${ row.enrolment_id }-guide_name` );
															setEnrolled( ( rows ) =>
																rows.map( ( item ) =>
																	item.enrolment_id === row.enrolment_id
																		? { ...item, guide_name: e.target.value }
																		: item
																)
															);
														} }
														onKeyDown={ ( e ) => { if ( e.key === 'Enter' ) { e.preventDefault(); e.target.blur(); } } }
														onBlur={ ( e ) => {
															markClean( `${ row.enrolment_id }-guide_name` );
															saveGuideField( row.student.id, 'guide_name', e.target.value );
														} }
													/>
													{ dirtyFields[ `${ row.enrolment_id }-guide_name` ] && (
														<p className="mt-0.5 text-xs text-text-muted">↵ Enter to save</p>
													) }
												</td>
												<td className="px-3 py-2">
													<select
														value={ row.panel_id ?? '' }
														onChange={ ( e ) =>
															assignPanel(
																row.student.id,
																e.target.value
																	? Number(
																			e.target.value
																	  )
																	: null
															)
														}
														className="w-full min-w-[6rem] rounded-md border border-border bg-surface px-2 py-1 text-sm"
														aria-label={ `Panel for ${ row.student?.name }` }
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
													<input
														type="text"
														className="w-full min-w-[12rem] rounded-md border border-border bg-surface px-2 py-1 text-sm"
														value={ row.project_title ?? '' }
														placeholder="Project title"
														onChange={ ( e ) => {
															markDirty( `${ row.enrolment_id }-project_title` );
															setEnrolled( ( rows ) =>
																rows.map( ( item ) =>
																	item.enrolment_id === row.enrolment_id
																		? { ...item, project_title: e.target.value }
																		: item
																)
															);
														} }
														onKeyDown={ ( e ) => { if ( e.key === 'Enter' ) { e.preventDefault(); e.target.blur(); } } }
														onBlur={ ( e ) => {
															markClean( `${ row.enrolment_id }-project_title` );
															saveProjectTitle( row.student.id, e.target.value );
														} }
													/>
													{ dirtyFields[ `${ row.enrolment_id }-project_title` ] && (
														<p className="mt-0.5 text-xs text-text-muted">↵ Enter to save</p>
													) }
												</td>
												<td className="px-3 py-2 text-right">
													<Button
														size="sm"
														variant="secondary"
														disabled={ row.has_scores }
														title={
															row.has_scores
																? 'Cannot remove: this student has scores in one or more review rounds.'
																: undefined
														}
														onClick={ () => setRemoveStudentId( row.student.id ) }
													>
														Remove
													</Button>
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</TableScrollWrapper>
						) }
					</div>

					<div className="mt-6 flex justify-end">
						<Button
							variant="primary"
							onClick={ goNext }
							disabled={ enrolledCount === 0 }
						>
							Continue to Panels
						</Button>
					</div>
				</section>
			) : null }

			{ currentStep === 'panels' ? (
				<PanelsStep
					sessionId={ sessionId }
					panels={ panels }
					enrolled={ enrolled }
					wizardState={ wizardState }
					onReload={ loadAll }
					onNotice={ toast }
					onContinue={ goNext }
					blockedTitle={ blockedSteps.reviewers }
				/>
			) : null }

			{ currentStep === 'reviewers' ? (
				<PanelReviewersStep
					sessionId={ sessionId }
					panels={ panels }
					reviewers={ reviewers }
					setReviewers={ setReviewers }
					onNotice={ toast }
					onRefreshReviewers={ refreshReviewers }
					onReload={ loadAll }
				/>
			) : null }

			{ currentStep === 'reviews' ? (
				<ReviewsSetupStep
					sessionId={ sessionId }
					onReload={ loadAll }
					onNotice={ toast }
					canAdvanceToAssignments={ ! blockedSteps.assignments }
					onContinue={ goNext }
					rubricsReloadDependency={ reviewsHubReloadTick }
				/>
			) : null }

			{ currentStep === 'assignments' ? (
				<ReviewAssignmentsStep
					sessionId={ sessionId }
					panels={ panels }
					wizardState={ wizardState }
					onReload={ loadAll }
					onNotice={ toast }
					onContinue={ goNext }
				/>
			) : null }

			{ currentStep === 'marking' ? (
				<ReviewMarkingStep
					sessionId={ sessionId }
					sessionStatus={ session.status }
					onReload={ loadAll }
					onNotice={ toast }
					isWizardTerminalStep
				/>
			) : null }

			<ConfirmDialog
				open={ removeStudentId !== null }
				title="Remove this student from the project?"
				consequences={ [
					'The student will be unenrolled from this project only.',
					'If not enrolled elsewhere, they will also be removed from All Students.',
				] }
				confirmLabel="Remove student"
				confirmVariant="destructive"
				onCancel={ () => setRemoveStudentId( null ) }
				onConfirm={ () => {
					removeEnrolment( removeStudentId );
					setRemoveStudentId( null );
				} }
			/>

			<ConfirmDialog
				open={ deleteAllDialogOpen }
				title="Remove all students from this project?"
				consequences={ [
					'Every student will be unenrolled from this project only.',
					'Students not enrolled in any other project will also be removed from All Students.',
					...( enrolledHasScores
						? [
								'Some students have entered scores; you will be asked to type Confirm before marking data is permanently deleted.',
						  ]
						: [] ),
				] }
				confirmLabel={
					enrolledHasScores
						? 'Continue…'
						: deletingAllStudents
						? 'Removing…'
						: 'Remove all students'
				}
				confirmVariant="destructive"
				confirmDisabled={ deletingAllStudents && ! enrolledHasScores }
				onCancel={ closeDeleteAllDialogs }
				onConfirm={ onDeleteAllFirstConfirm }
			/>

			<ConfirmDialog
				open={ deleteAllScoresDialogOpen }
				title="Remove students and all their scores?"
				consequences={ [
					'Entered marks for students in this project will be permanently deleted.',
					'All students will be unenrolled from this project.',
					'Students only in this project will be removed from All Students.',
				] }
				confirmLabel={
					deletingAllStudents ? 'Removing…' : 'Remove all students'
				}
				confirmVariant="destructive"
				confirmDisabled={
					deletingAllStudents || ! phraseMatchesDeleteAllScores
				}
				onCancel={ closeDeleteAllDialogs }
				onConfirm={ handleDeleteAllStudents }
			>
				<div className="space-y-2 text-sm text-text-muted">
					<p>
						Type <strong className="text-text">Confirm</strong> to
						proceed.
					</p>
					<input
						type="text"
						className="w-full rounded-md border border-border bg-surface px-3 py-2 text-text"
						value={ deleteAllPhrase }
						onChange={ ( e ) => setDeleteAllPhrase( e.target.value ) }
						autoComplete="off"
						data-testid="pr-wizard-delete-all-confirm-input"
						aria-label="Type Confirm to remove students with scores"
					/>
				</div>
			</ConfirmDialog>
		</>
	);
}
