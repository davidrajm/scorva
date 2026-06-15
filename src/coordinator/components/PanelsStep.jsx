import { useState } from '@wordpress/element';
import { del, post, put } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import { Button, ConfirmDialog } from '../../shared/components';

export function PanelsStep( {
	sessionId,
	panels,
	enrolled,
	wizardState,
	onReload,
	onNotice,
	onContinue,
	blockedTitle,
} ) {
	const [ busy, setBusy ] = useState( false );
	const [ editingId, setEditingId ] = useState( null );
	const [ editName, setEditName ] = useState( '' );
	const [ pendingDelete, setPendingDelete ] = useState( null );

	const createPanel = async ( event ) => {
		event.preventDefault();
		const name = event.target.panel_name.value.trim();
		if ( ! name ) {
			return;
		}
		setBusy( true );
		try {
			await post( `/sessions/${ sessionId }/panels`, { name } );
			event.target.reset();
			await onReload?.();
		} catch ( err ) {
			onNotice?.( {
				variant: 'error',
				message: parseApiErrorMessage( err, 'Could not create panel.' ),
			} );
		} finally {
			setBusy( false );
		}
	};

	const assignPanel = async ( studentId, panelId ) => {
		setBusy( true );
		try {
			await put( `/sessions/${ sessionId }/students/${ studentId }`, {
				panel_id: panelId || null,
			} );
			await onReload?.();
		} catch ( err ) {
			if ( err?.code === 'pr_panel_change_blocked' ) {
				onNotice?.( {
					variant: 'error',
					message: parseApiErrorMessage(
						err,
						'This student has scores recorded. Use the Review Assignments step to reassign.'
					),
				} );
			} else {
				onNotice?.( {
					variant: 'error',
					message: 'Could not assign panel.',
				} );
			}
		} finally {
			setBusy( false );
		}
	};

	const startEdit = ( panel ) => {
		setEditingId( panel.id );
		setEditName( panel.name );
	};

	const saveName = async ( panelId ) => {
		const name = editName.trim();
		setEditingId( null );
		if ( ! name ) {
			onNotice?.( {
				variant: 'error',
				message: 'Panel name is required.',
			} );
			return;
		}
		setBusy( true );
		try {
			await put( `/sessions/${ sessionId }/panels/${ panelId }`, { name } );
			await onReload?.();
		} catch ( err ) {
			onNotice?.( {
				variant: 'error',
				message: parseApiErrorMessage( err, 'Could not rename panel.' ),
			} );
		} finally {
			setBusy( false );
		}
	};

	const confirmDeletePanel = async () => {
		if ( ! pendingDelete ) {
			return;
		}
		const panel = pendingDelete;
		setPendingDelete( null );
		setBusy( true );
		try {
			await del( `/sessions/${ sessionId }/panels/${ panel.id }` );
			await onReload?.();
		} catch ( err ) {
			onNotice?.( {
				variant: 'error',
				message: parseApiErrorMessage( err, 'Could not remove panel.' ),
			} );
		} finally {
			setBusy( false );
		}
	};

	const unassignedCount = wizardState?.unassigned_count ?? 0;

	return (
		<section>
			<h2 className="text-lg font-semibold text-text">Panels</h2>
			<p className="mt-1 text-sm text-text-muted">
				Create panels and assign every enrolled student. This is the project
				default template for Review 1; later rounds start as a copy of the
				previous review and can be changed on Panel assignments.
			</p>

			<form onSubmit={ createPanel } className="mt-4 flex flex-wrap gap-2">
				<input
					name="panel_name"
					type="text"
					placeholder="Panel name"
					className="rounded-md border border-border bg-surface px-3 py-2 text-sm"
					required
					disabled={ busy }
				/>
				<Button variant="primary" type="submit" disabled={ busy }>
					Add panel
				</Button>
			</form>

			{ panels.length > 0 ? (
				<ul className="mt-4 space-y-2">
					{ panels.map( ( panel ) => {
						const isEditing = editingId === panel.id;
						const deletable =
							panel.deletable ?? panel.student_count === 0;
						const studentLabel =
							panel.student_count === 1
								? '1 student'
								: `${ panel.student_count } students`;

						return (
							<li
								key={ panel.id }
								className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-surface-raised px-3 py-2 text-sm"
							>
								<div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
									{ isEditing ? (
										<input
											type="text"
											value={ editName }
											onChange={ ( e ) =>
												setEditName( e.target.value )
											}
											onBlur={ () => saveName( panel.id ) }
											onKeyDown={ ( e ) => {
												if ( e.key === 'Enter' ) {
													e.preventDefault();
													saveName( panel.id );
												}
												if ( e.key === 'Escape' ) {
													setEditingId( null );
												}
											} }
											className="min-w-[12rem] flex-1 rounded-md border border-border bg-surface px-2 py-1"
											aria-label={ `Rename panel ${ panel.name }` }
											autoFocus
											disabled={ busy }
										/>
									) : (
										<button
											type="button"
											className="font-medium text-text hover:underline"
											onClick={ () => startEdit( panel ) }
											aria-label={ `Rename panel ${ panel.name }` }
											disabled={ busy }
										>
											{ panel.name }
										</button>
									) }
									<span className="text-text-muted">
										{ studentLabel }
									</span>
								</div>
								<div className="flex flex-wrap items-center gap-2">
									{ deletable ? (
										<Button
											size="sm"
											variant="secondary"
											disabled={ busy }
											onClick={ () =>
												setPendingDelete( panel )
											}
											aria-label={ `Remove panel ${ panel.name }` }
										>
											Remove
										</Button>
									) : (
										<span
											className="text-xs text-text-muted"
											title="Reassign or unassign students before removing this panel."
										>
											Reassign or unassign students before
											removing this panel.
										</span>
									) }
								</div>
							</li>
						);
					} ) }
				</ul>
			) : (
				<p className="mt-4 text-sm text-text-muted">
					Add a panel, then assign each enrolled student below.
				</p>
			) }

			<h3 className="mt-6 text-sm font-semibold text-text">
				Student assignments
			</h3>
			<ul className="mt-2 space-y-4">
				{ enrolled.map( ( row ) => {
					const unassigned = ! row.panel_id;
					return (
						<li
							key={ row.enrolment_id }
							className={ [
								'flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm',
								unassigned
									? 'border-warning bg-warning/10'
									: 'border-border',
							].join( ' ' ) }
						>
							<span className="font-medium text-text">
								{ row.student?.reg_no } — { row.student?.name }
							</span>
							<select
								value={ row.panel_id ?? '' }
								onChange={ ( e ) =>
									assignPanel(
										row.student.id,
										e.target.value
											? Number( e.target.value )
											: null
									)
								}
								className="rounded-md border border-border bg-surface px-2 py-1 text-sm"
								aria-label={ `Panel for ${ row.student?.name }` }
								disabled={ busy }
							>
								<option value="">Unassigned</option>
								{ panels.map( ( panel ) => (
									<option key={ panel.id } value={ panel.id }>
										{ panel.name }
									</option>
								) ) }
							</select>
						</li>
					);
				} ) }
			</ul>

			{ unassignedCount > 0 ? (
				<p className="mt-3 text-sm text-warning">
					{ unassignedCount } student
					{ unassignedCount === 1 ? '' : 's' } still unassigned.
				</p>
			) : null }

			<ConfirmDialog
				open={ pendingDelete !== null }
				title={
					pendingDelete
						? `Remove panel “${ pendingDelete.name }”?`
						: 'Remove panel?'
				}
				consequences={ [
					'Reviewers on this panel will be removed.',
					'This cannot be undone.',
				] }
				confirmLabel="Remove panel"
				confirmVariant="destructive"
				onConfirm={ confirmDeletePanel }
				onCancel={ () => setPendingDelete( null ) }
			/>

			<div className="mt-6 flex justify-end">
				<Button
					variant="primary"
					onClick={ onContinue }
					disabled={ unassignedCount > 0 || busy }
					title={ blockedTitle }
				>
					Continue to Reviewers
				</Button>
			</div>
		</section>
	);
}
