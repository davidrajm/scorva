import { useCallback, useEffect, useState } from '@wordpress/element';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { del, get, getBlob, post } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import {
	Button,
	ConfirmDialog,
	ContentLoadingRegion,
	Notice,
	PageContentSkeleton,
	PageHeader,
	StatusChip,
} from '../../shared/components';

const PRE_CLOSE_STEPS = [
	{
		label: 'Review progress',
		path: 'progress',
		description: 'Confirm marking is complete across panels and reviewers.',
	},
	{
		label: 'Download reports',
		path: 'reports',
		search: '?tab=downloads',
		description: 'Export committee deliverables before accounts are disabled.',
	},
	{
		label: 'Check audit log',
		path: 'audit',
		description: 'Review overrides and governance actions for this project.',
	},
];

export function CloseSession() {
	const { id } = useParams();
	const navigate = useNavigate();
	const basePath = `/session/${ id }`;
	const [ sessionTitle, setSessionTitle ] = useState( '' );
	const [ hasEnteredScores, setHasEnteredScores ] = useState( false );
	const [ preview, setPreview ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ dialogOpen, setDialogOpen ] = useState( false );
	const [ reopenDialogOpen, setReopenDialogOpen ] = useState( false );
	const [ closing, setClosing ] = useState( false );
	const [ reopening, setReopening ] = useState( false );
	const [ success, setSuccess ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ backupLoading, setBackupLoading ] = useState( false );
	const [ backupError, setBackupError ] = useState( '' );
	const [ deleteDialogOpen, setDeleteDialogOpen ] = useState( false );
	const [ deleteDestructiveOpen, setDeleteDestructiveOpen ] = useState( false );
	const [ deletePhrase, setDeletePhrase ] = useState( '' );
	const [ deleting, setDeleting ] = useState( false );
	const [ deleteError, setDeleteError ] = useState( '' );

	const loadPreview = useCallback( async () => {
		const data = await get( `sessions/${ id }/close-preview` );
		setPreview( data );
		return data;
	}, [ id ] );

	const canCloseProject = window.prAppData?.canCloseProject !== false;
	const canManageProjects = window.prAppData?.canManageProjects !== false;

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );

		( async () => {
			try {
				const [ session, previewData ] = await Promise.all( [
					get( `sessions/${ id }` ).catch( () => null ),
					get( `sessions/${ id }/close-preview` ),
				] );
				if ( cancelled ) {
					return;
				}
				setSessionTitle( session?.title || '' );
				setHasEnteredScores( session?.has_entered_scores === true );
				setPreview( previewData );
			} catch ( err ) {
				if ( ! cancelled ) {
					setError(
						err?.message ||
							'Unable to load close summary.'
					);
				}
			} finally {
				if ( ! cancelled ) {
					setLoading( false );
				}
			}
		} )();

		return () => {
			cancelled = true;
		};
	}, [ id ] );

	const handleReopen = async () => {
		if ( ! canCloseProject ) {
			return;
		}
		setError( '' );
		setReopening( true );
		try {
			await post( `sessions/${ id }/reopen` );
			setSuccess( 'Project reopened. Reviewer portal access is restored.' );
			setReopenDialogOpen( false );
			await loadPreview();
		} catch {
			setError( 'Failed to reopen project.' );
		} finally {
			setReopening( false );
		}
	};

	const handleProjectBackup = async () => {
		setBackupError( '' );
		setBackupLoading( true );
		try {
			const response = await getBlob(
				`sessions/${ id }/backup/download`
			);
			const blob = await response.blob();
			const disposition =
				response.headers.get( 'Content-Disposition' ) || '';
			const match = disposition.match( /filename="([^"]+)"/ );
			const filename = match
				? match[ 1 ]
				: 'project-reviews-backup.zip';
			const url = URL.createObjectURL( blob );
			const link = document.createElement( 'a' );
			link.href = url;
			link.download = filename;
			document.body.appendChild( link );
			link.click();
			link.remove();
			URL.revokeObjectURL( url );
		} catch ( err ) {
			setBackupError(
				err?.message || 'Project backup download failed.'
			);
		} finally {
			setBackupLoading( false );
		}
	};

	const openDeleteDialog = () => {
		setDeleteError( '' );
		setDeletePhrase( '' );
		if ( hasEnteredScores ) {
			setDeleteDestructiveOpen( true );
		} else {
			setDeleteDialogOpen( true );
		}
	};

	const closeDeleteDialogs = () => {
		setDeleteDialogOpen( false );
		setDeleteDestructiveOpen( false );
		setDeletePhrase( '' );
		setDeleteError( '' );
	};

	const handleDeleteProject = async () => {
		if ( ! canManageProjects ) {
			return;
		}
		setDeleteError( '' );
		setDeleting( true );
		try {
			const payload = hasEnteredScores
				? { confirm_label: deletePhrase.trim() }
				: undefined;
			await del( `sessions/${ id }`, payload );
			const deletedTitle = sessionTitle.trim() || 'Project';
			closeDeleteDialogs();
			navigate( '/', {
				state: {
					notice: `“${ deletedTitle }” was permanently deleted.`,
				},
			} );
		} catch ( err ) {
			setDeleteError(
				parseApiErrorMessage( err, 'Could not delete project.' )
			);
		} finally {
			setDeleting( false );
		}
	};

	const phraseMatchesDelete =
		deletePhrase.trim() === String( sessionTitle ).trim();

	const handleClose = async () => {
		if ( ! canCloseProject ) {
			return;
		}
		setError( '' );
		setClosing( true );
		try {
			await post( `sessions/${ id }/close` );
			setSuccess( 'Project closed. Reviewer portal access is suspended.' );
			setDialogOpen( false );
			await loadPreview();
		} catch {
			setError( 'Failed to close project.' );
		} finally {
			setClosing( false );
		}
	};

	const isClosed = preview?.status === 'closed';
	const credentialedReviewers = preview?.credentialed_reviewers ?? 0;

	const consequences = [
		'Project status will change to closed.',
		`Reviewer portal access will be suspended — ${ credentialedReviewers } credentialed reviewer${ credentialedReviewers === 1 ? '' : 's' } will be blocked from logging in.`,
		'Marks already submitted are preserved.',
	];

	const reopenConsequences = [
		'Project status will return to active (or draft if it was draft before close).',
		'Reviewer portal access will be restored — credentialed reviewers can log in again.',
		'Marking can resume where rubric, assignment, and freeze rules allow edits.',
		'Does not unlock coordinator marks lock or reviewer submitted scores.',
	];

	return (
		<>
			<PageHeader
				title="Close project"
				description={
					sessionTitle
						? `End marking for “${ sessionTitle }” and suspend reviewer portal access.`
						: 'End marking and suspend reviewer portal access.'
				}
				actions={
					preview?.status ? (
						<StatusChip variant={ preview.status } />
					) : null
				}
			/>
			{ success && <Notice variant="success">{ success }</Notice> }
			{ error && <Notice variant="error">{ error }</Notice> }
			{ backupError && (
				<Notice variant="error">{ backupError }</Notice>
			) }
			{ loading ? (
				<ContentLoadingRegion
					busy
					variant="inline"
					label="Loading close project"
					className="mt-4"
				>
					<PageContentSkeleton rows={ 4 } />
				</ContentLoadingRegion>
			) : null }

			{ ! loading && preview && isClosed && (
				<section
					aria-labelledby="reopen-summary-heading"
					className="max-w-xl space-y-6"
				>
					<h2
						id="reopen-summary-heading"
						className="text-sm font-semibold uppercase tracking-wide text-text-muted"
					>
						Reopen project
					</h2>
					<div className="space-y-4 rounded-md border border-border bg-surface-raised p-6">
						<p className="text-sm text-text">
							This project is closed. Marking is locked and reviewer portal access is suspended.
						</p>
						<dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
							<dt className="text-text-muted">Status</dt>
							<dd>
								<StatusChip variant={ preview.status } />
							</dd>
						</dl>
						{ ! canCloseProject ? (
							<Notice variant="warning">
								You can view this summary but do not have permission to
								reopen projects. Ask an administrator to grant the
								close-project capability.
							</Notice>
						) : (
							<Button
								variant="primary"
								onClick={ () => setReopenDialogOpen( true ) }
							>
								Reopen project…
							</Button>
						) }
					</div>
				</section>
			) }

			{ ! loading && preview && ! isClosed && (
				<div className="space-y-8">
					<section aria-labelledby="close-pre-close-heading">
						<h2
							id="close-pre-close-heading"
							className="text-sm font-semibold uppercase tracking-wide text-text-muted"
						>
							Before you close
						</h2>
						<div className="mt-4 max-w-xl">
							<Button
								variant="secondary"
								onClick={ handleProjectBackup }
								disabled={ backupLoading }
							>
								{ backupLoading
									? 'Preparing backup…'
									: 'Download project backup (ZIP)' }
							</Button>
							<p className="mt-2 text-sm text-text-muted">
								Includes plugin data for this project and Excel
								reports. Store off-site before closing or
								uninstalling the plugin.
							</p>
						</div>
						<ul className="mt-4 grid gap-3 md:grid-cols-3">
							{ PRE_CLOSE_STEPS.map( ( step ) => (
								<li
									key={ step.path + ( step.search ?? '' ) }
									className="rounded-md border border-border bg-surface-raised p-4"
								>
									<Link
										to={
											step.search
												? {
														pathname: `${ basePath }/${ step.path }`,
														search: step.search,
													}
												: `${ basePath }/${ step.path }`
										}
										className="text-sm font-medium text-primary hover:underline"
									>
										{ step.label }
									</Link>
									<p className="mt-2 text-sm text-text-muted">
										{ step.description }
									</p>
								</li>
							) ) }
						</ul>
					</section>

					<section
						aria-labelledby="close-summary-heading"
						className="max-w-xl"
					>
						<h2
							id="close-summary-heading"
							className="text-sm font-semibold uppercase tracking-wide text-text-muted"
						>
							Close summary
						</h2>
						<div className="mt-4 space-y-4 rounded-md border border-border bg-surface-raised p-6">
							<dl className="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
								<dt className="text-text-muted">Status</dt>
								<dd>
									<StatusChip variant={ preview.status } />
								</dd>
								<dt className="text-text-muted">Open marks</dt>
								<dd className="font-medium text-text">
									{ preview.open_marks }
									{ preview.open_marks > 0 ? (
										<span className="mt-1 block text-xs font-normal text-text-muted">
											Incomplete marking may remain in
											exports; closing does not delete
											data.
										</span>
									) : null }
								</dd>
								<dt className="text-text-muted">
									Credentialed reviewers
								</dt>
								<dd className="font-medium text-text">
									{ preview.credentialed_reviewers }
									<span className="mt-1 block text-xs font-normal text-text-muted">
										Portal access is suspended when the
										project is closed.
									</span>
								</dd>
							</dl>
							{ ! canCloseProject ? (
								<Notice variant="warning">
									You can view this summary but do not have
									permission to close projects. Ask an
									administrator to grant the close-project
									capability.
								</Notice>
							) : (
								<Button
									variant="destructive"
									icon="close"
									onClick={ () => setDialogOpen( true ) }
								>
									Close project…
								</Button>
							) }
						</div>
					</section>
				</div>
			) }

			{ ! loading && preview && (
				<section
					aria-labelledby="delete-project-heading"
					className="mt-10 max-w-xl border-t border-border pt-8"
				>
					<h2
						id="delete-project-heading"
						className="text-sm font-semibold uppercase tracking-wide text-text-muted"
					>
						Delete project
					</h2>
					<div className="mt-4 space-y-4 rounded-md border border-danger/30 bg-surface-raised p-6">
						<p className="text-sm text-text">
							Permanently remove this project and all of its data
							(roster, panels, review rounds, rubrics, assignments,
							marks, freezes, and project-scoped audit). WordPress
							user accounts are not deleted.
						</p>
						<p className="text-sm text-text-muted">
							{ isClosed
								? 'Download a project backup from Reports → Downloads before deleting if you need an archive.'
								: 'Download a project backup above before deleting if you need an archive.' }{ ' ' }
							This cannot be undone.
						</p>
						{ ! canManageProjects ? (
							<Notice variant="warning">
								You do not have permission to delete projects.
								Ask an administrator to grant project management
								capability.
							</Notice>
						) : (
							<Button
								variant="destructive"
								type="button"
								data-testid="pr-delete-project"
								onClick={ openDeleteDialog }
							>
								Delete project…
							</Button>
						) }
					</div>
				</section>
			) }

			<ConfirmDialog
				open={ reopenDialogOpen }
				title="Reopen this project?"
				consequences={ reopenConsequences }
				confirmLabel={ reopening ? 'Reopening…' : 'Reopen project' }
				confirmVariant="primary"
				confirmDisabled={ reopening }
				onCancel={ () => setReopenDialogOpen( false ) }
				onConfirm={ handleReopen }
			/>

			<ConfirmDialog
				open={ deleteDialogOpen }
				title={
					sessionTitle
						? `Delete ${ sessionTitle }?`
						: 'Delete project?'
				}
				consequences={ [
					'Student roster enrolment, panels, review rounds, rubrics, and assignments for this project will be permanently removed.',
					'Draft mark rows, panel freezes, unfreeze requests, and project-scoped audit entries will be deleted.',
					'WordPress user accounts are not deleted.',
				] }
				confirmLabel={ deleting ? 'Deleting…' : 'Delete project' }
				confirmVariant="destructive"
				confirmDisabled={ deleting }
				onCancel={ closeDeleteDialogs }
				onConfirm={ handleDeleteProject }
			>
				{ deleteError ? (
					<p className="text-sm text-danger">{ deleteError }</p>
				) : null }
			</ConfirmDialog>

			<ConfirmDialog
				open={ deleteDestructiveOpen }
				title={
					sessionTitle
						? `Delete ${ sessionTitle } and all scores?`
						: 'Delete project?'
				}
				consequences={ [
					'All entered marks and derived scores for this project will be permanently removed.',
					'Student roster, panels, review rounds, rubrics, assignments, freezes, and project-scoped audit will be deleted.',
					'Reviewer portal credentials for this project are permanently removed.',
					'WordPress user accounts are not deleted.',
				] }
				confirmLabel={
					deleting ? 'Deleting…' : 'Delete project and scores'
				}
				confirmVariant="destructive"
				confirmDisabled={ deleting || ! phraseMatchesDelete }
				onCancel={ closeDeleteDialogs }
				onConfirm={ handleDeleteProject }
			>
				<div className="space-y-2 text-sm text-text-muted">
					<p>
						Type the exact project title{ ' ' }
						<strong className="text-text">{ sessionTitle }</strong>{ ' ' }
						to confirm.
					</p>
					<input
						type="text"
						className="w-full rounded-md border border-border bg-surface px-3 py-2 text-text"
						value={ deletePhrase }
						onChange={ ( e ) => setDeletePhrase( e.target.value ) }
						autoComplete="off"
						data-testid="pr-delete-project-confirm-input"
						aria-label="Type project title to confirm deletion"
					/>
					{ deleteError ? (
						<p className="text-danger">{ deleteError }</p>
					) : null }
				</div>
			</ConfirmDialog>

			<ConfirmDialog
				open={ dialogOpen }
				title="Close this project?"
				consequences={ consequences }
				confirmLabel={ closing ? 'Closing…' : 'Close project' }
				confirmVariant="destructive"
				confirmDisabled={ closing }
				onCancel={ () => setDialogOpen( false ) }
				onConfirm={ handleClose }
			/>
		</>
	);
}
