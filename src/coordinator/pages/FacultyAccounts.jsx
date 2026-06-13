import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { del, get, post } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import { useDebouncedValue } from '../../shared/hooks/useDebouncedValue';
import {
	Button,
	ContentLoadingRegion,
	EmptyState,
	Notice,
	PageHeader,
	TableSkeleton,
} from '../../shared/components';
import { useLoadingPhase } from '../../shared/hooks/useLoadingPhase';
import { CsvImportMapper } from '../components/CsvImportMapper';
import { TableDataViewport } from '../../shared/TableScrollViewport';
import {
	TABLE_BODY_ROW_SOFT,
	TABLE_VIEWPORT_ROW_INCREMENT,
	regNoStickyClass,
	regNoStickyStyle,
} from '../../shared/tableStyles';

const DEFAULT_PER_PAGE = 20;

function AddFacultyForm( { onSuccess, onError } ) {
	const [ busy, setBusy ] = useState( false );
	const [ fields, setFields ] = useState( {
		name: '',
		email: '',
		emp_id: '',
		designation: '',
		gender: '',
	} );
	const [ fieldErrors, setFieldErrors ] = useState( {} );
	const [ expanded, setExpanded ] = useState( false );

	const set = ( key ) => ( e ) =>
		setFields( ( prev ) => ( { ...prev, [ key ]: e.target.value } ) );

	const handleSubmit = async ( e ) => {
		e.preventDefault();
		setFieldErrors( {} );
		setBusy( true );
		try {
			const result = await post( '/faculty-accounts', fields );
			setFields( { name: '', email: '', emp_id: '', designation: '', gender: '' } );
			setExpanded( false );
			onSuccess( result );
		} catch ( err ) {
			// Field-level errors come back in err.data.fields
			const fields_errs = err?.data?.fields ?? {};
			if ( Object.keys( fields_errs ).length > 0 ) {
				setFieldErrors( fields_errs );
			} else {
				onError( parseApiErrorMessage( err, 'Could not add faculty account.' ) );
			}
		} finally {
			setBusy( false );
		}
	};

	if ( ! expanded ) {
		return (
			<Button variant="primary" onClick={ () => setExpanded( true ) }>
				Add faculty
			</Button>
		);
	}

	return (
		<form
			onSubmit={ handleSubmit }
			className="mt-4 rounded-lg border border-border bg-surface-raised p-4"
		>
			<h3 className="mb-3 text-sm font-semibold text-text">Add a reviewer</h3>
			<div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
				<div>
					<label
						htmlFor="add-faculty-name"
						className="block text-xs font-medium text-text-muted"
					>
						Name <span className="text-error">*</span>
					</label>
					<input
						id="add-faculty-name"
						type="text"
						required
						className="mt-1 w-full rounded border border-border px-2 py-1.5 text-sm"
						value={ fields.name }
						onChange={ set( 'name' ) }
					/>
					{ fieldErrors.name && (
						<p className="mt-1 text-xs text-error">{ fieldErrors.name }</p>
					) }
				</div>
				<div>
					<label
						htmlFor="add-faculty-email"
						className="block text-xs font-medium text-text-muted"
					>
						Email <span className="text-error">*</span>
					</label>
					<input
						id="add-faculty-email"
						type="email"
						required
						className="mt-1 w-full rounded border border-border px-2 py-1.5 text-sm"
						value={ fields.email }
						onChange={ set( 'email' ) }
					/>
					{ fieldErrors.email && (
						<p className="mt-1 text-xs text-error">{ fieldErrors.email }</p>
					) }
				</div>
				<div>
					<label
						htmlFor="add-faculty-empid"
						className="block text-xs font-medium text-text-muted"
					>
						Employee ID
					</label>
					<input
						id="add-faculty-empid"
						type="text"
						className="mt-1 w-full rounded border border-border px-2 py-1.5 text-sm"
						value={ fields.emp_id }
						onChange={ set( 'emp_id' ) }
					/>
				</div>
				<div>
					<label
						htmlFor="add-faculty-designation"
						className="block text-xs font-medium text-text-muted"
					>
						Designation
					</label>
					<input
						id="add-faculty-designation"
						type="text"
						className="mt-1 w-full rounded border border-border px-2 py-1.5 text-sm"
						value={ fields.designation }
						onChange={ set( 'designation' ) }
					/>
				</div>
				<div>
					<label
						htmlFor="add-faculty-gender"
						className="block text-xs font-medium text-text-muted"
					>
						Gender
					</label>
					<input
						id="add-faculty-gender"
						type="text"
						className="mt-1 w-full rounded border border-border px-2 py-1.5 text-sm"
						value={ fields.gender }
						onChange={ set( 'gender' ) }
					/>
				</div>
			</div>
			<div className="mt-4 flex gap-2">
				<Button type="submit" variant="primary" disabled={ busy }>
					{ busy ? 'Adding…' : 'Add reviewer' }
				</Button>
				<Button
					type="button"
					variant="secondary"
					onClick={ () => {
						setExpanded( false );
						setFieldErrors( {} );
					} }
				>
					Cancel
				</Button>
			</div>
		</form>
	);
}

export function FacultyAccounts() {
	const canAssign = window.prAppData?.canAssignReviewers !== false;
	const templateUrl = window.prAppData?.facultyAccountsTemplateUrl ?? '';

	const [ accounts, setAccounts ] = useState( null );
	const [ directoryImport, setDirectoryImport ] = useState( null );
	const [ search, setSearch ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ showImport, setShowImport ] = useState( false );
	const [ importNotice, setImportNotice ] = useState( null );
	const [ syncBusy, setSyncBusy ] = useState( false );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ total, setTotal ] = useState( 0 );
	const [ selected, setSelected ] = useState( new Set() );
	const [ deleteBusy, setDeleteBusy ] = useState( false );
	const [ confirmDelete, setConfirmDelete ] = useState( false );

	const debouncedSearch = useDebouncedValue( search, 300 );

	// Reset to page 1 when search changes.
	const prevSearch = useRef( debouncedSearch );
	useEffect( () => {
		if ( prevSearch.current !== debouncedSearch ) {
			prevSearch.current = debouncedSearch;
			setPage( 1 );
		}
	}, [ debouncedSearch ] );

	const loadAccounts = useCallback( async () => {
		setLoading( true );
		try {
			const params = new URLSearchParams( {
				page: String( page ),
				per_page: String( DEFAULT_PER_PAGE ),
			} );
			if ( debouncedSearch ) {
				params.set( 'search', debouncedSearch );
			}
			const data = await get( `/faculty-accounts?${ params.toString() }` );
			setAccounts( data.accounts ?? [] );
			setDirectoryImport( data.directory_import ?? null );
			setTotalPages( data.total_pages ?? 1 );
			setTotal( data.total ?? 0 );
			setSelected( new Set() );
		} catch {
			setAccounts( [] );
			setDirectoryImport( null );
		} finally {
			setLoading( false );
		}
	}, [ debouncedSearch, page ] );

	useEffect( () => {
		if ( ! canAssign ) {
			return;
		}
		loadAccounts();
	}, [ canAssign, loadAccounts ] );

	const handleSyncDirectory = async () => {
		setSyncBusy( true );
		setImportNotice( null );
		try {
			const result = await post( '/faculty-accounts/sync-directory', {} );
			setImportNotice( {
				variant: ( result.failed ?? 0 ) > 0 ? 'warning' : 'success',
				message: `Directory sync: ${ result.created ?? 0 } created, ${ result.updated ?? 0 } updated, ${ result.skipped ?? 0 } skipped, ${ result.failed ?? 0 } failed.`,
			} );
			setPage( 1 );
			loadAccounts();
		} catch ( err ) {
			setImportNotice( {
				variant: 'error',
				message: parseApiErrorMessage(
					err,
					'Could not sync from faculty directory.'
				),
			} );
		} finally {
			setSyncBusy( false );
		}
	};

	const handleBulkDelete = async () => {
		if ( selected.size === 0 ) {
			return;
		}
		setDeleteBusy( true );
		setConfirmDelete( false );
		try {
			const result = await del( '/faculty-accounts', {
				ids: [ ...selected ],
			} );
			const msg =
				result.deleted > 0
					? `${ result.deleted } reviewer${ result.deleted !== 1 ? 's' : '' } deleted.`
					: 'No reviewers deleted.';
			const errMsg =
				result.failed > 0
					? ` ${ result.failed } could not be deleted (assigned to panels).`
					: '';
			setImportNotice( {
				variant: result.failed > 0 ? 'warning' : 'success',
				message: msg + errMsg,
			} );
			setPage( 1 );
			loadAccounts();
		} catch ( err ) {
			setImportNotice( {
				variant: 'error',
				message: parseApiErrorMessage( err, 'Could not delete reviewers.' ),
			} );
		} finally {
			setDeleteBusy( false );
		}
	};

	const handleToggleSelect = ( userId ) => {
		setSelected( ( prev ) => {
			const next = new Set( prev );
			if ( next.has( userId ) ) {
				next.delete( userId );
			} else {
				next.add( userId );
			}
			return next;
		} );
	};

	const handleSelectAll = ( e ) => {
		if ( e.target.checked ) {
			setSelected( new Set( ( accounts ?? [] ).map( ( a ) => a.user_id ) ) );
		} else {
			setSelected( new Set() );
		}
	};

	if ( ! canAssign ) {
		return (
			<Notice variant="warning">
				You do not have permission to manage faculty accounts.
			</Notice>
		);
	}

	const { showSkeleton, showOverlay } = useLoadingPhase(
		loading,
		accounts !== null
	);

	const bridgeEnabled = Boolean(
		directoryImport?.import_available ??
			window.prAppData?.facultyBridgeEnabled
	);
	const hasAccounts = ( accounts?.length ?? 0 ) > 0;
	const showEmpty = ! hasAccounts && ! debouncedSearch && ! showImport;
	const allSelected =
		hasAccounts &&
		( accounts ?? [] ).every( ( a ) => selected.has( a.user_id ) );

	return (
		<>
			<PageHeader
				title="Faculty accounts"
				description="This is the reviewer pool — people who can be assigned to review panels. Adding someone here does not notify them. Login credentials are emailed separately when a review opens."
				actions={
					<div className="flex flex-wrap gap-2">
						{ bridgeEnabled ? (
							<Button
								variant="secondary"
								disabled={ syncBusy }
								onClick={ handleSyncDirectory }
							>
								{ syncBusy
									? 'Syncing…'
									: 'Import from faculty directory' }
							</Button>
						) : null }
						<Button
							variant="secondary"
							onClick={ () => setShowImport( ( value ) => ! value ) }
						>
							{ showImport ? 'Hide import' : 'Import CSV' }
						</Button>
					</div>
				}
			/>

			{ importNotice ? (
				<div className="mt-4">
					<Notice
						variant={ importNotice.variant }
						onDismiss={ () => setImportNotice( null ) }
					>
						{ importNotice.message }
					</Notice>
				</div>
			) : null }

			{ directoryImport &&
			! directoryImport.import_available &&
			directoryImport.setting_enabled &&
			! directoryImport.table_available ? (
				<div className="mt-4">
					<Notice variant="warning">
						Faculty directory bridge is enabled in settings, but the
						faculty table was not found. Expected{ ' ' }
						<code className="text-xs">wp_faculty</code> (or your site
						prefix + <code className="text-xs">faculty</code>). CSV
						import still works.
					</Notice>
				</div>
			) : null }

			{ /* Add faculty form */ }
			<div className="mt-4">
				<AddFacultyForm
					onSuccess={ () => {
						setImportNotice( {
							variant: 'success',
							message: 'Reviewer added to the pool.',
						} );
						setPage( 1 );
						loadAccounts();
					} }
					onError={ ( msg ) =>
						setImportNotice( { variant: 'error', message: msg } )
					}
				/>
			</div>

			{ showImport ? (
				<CsvImportMapper
					importType="faculty-accounts"
					onImportSuccess={ ( { variant, message } ) => {
						if ( message ) {
							setImportNotice( {
								variant: variant ?? 'success',
								message,
							} );
						}
					} }
					onComplete={ loadAccounts }
					onDownloadTemplate={
						templateUrl
							? () => {
									window.location.href = templateUrl;
							  }
							: null
					}
				/>
			) : null }

			{ showSkeleton ? (
				<ContentLoadingRegion
					busy
					variant="inline"
					label="Loading faculty accounts"
					className="mt-6"
				>
					<TableSkeleton columns={ 6 } />
				</ContentLoadingRegion>
			) : showEmpty ? (
				<EmptyState
					title="No reviewers in the pool yet"
					description="Add reviewers one at a time, import a CSV, or sync from the faculty directory."
					action={
						<div className="flex flex-wrap justify-center gap-2">
							<Button
								variant="primary"
								onClick={ () => setShowImport( true ) }
							>
								Import CSV
							</Button>
							{ bridgeEnabled ? (
								<Button
									variant="secondary"
									disabled={ syncBusy }
									onClick={ handleSyncDirectory }
								>
									Import from directory
								</Button>
							) : null }
						</div>
					}
				/>
			) : (
				<>
					<div className="mt-6 flex flex-wrap items-end justify-between gap-3">
						<div>
							<label
								className="block text-sm font-medium text-text"
								htmlFor="faculty-search"
							>
								Search accounts
							</label>
							<input
								id="faculty-search"
								type="search"
								className="mt-1 w-full max-w-md rounded-md border border-border px-3 py-2 text-sm"
								value={ search }
								onChange={ ( event ) =>
									setSearch( event.target.value )
								}
								placeholder="Name, email, or employee ID"
							/>
						</div>

						{ selected.size > 0 && (
							<div className="flex items-center gap-2">
								<span className="text-sm text-text-muted">
									{ selected.size } selected
								</span>
								{ confirmDelete ? (
									<>
										<span className="text-sm text-error">
											Delete { selected.size } reviewer
											{ selected.size !== 1 ? 's' : '' }?
										</span>
										<Button
											variant="danger"
											disabled={ deleteBusy }
											onClick={ handleBulkDelete }
										>
											{ deleteBusy ? 'Deleting…' : 'Confirm delete' }
										</Button>
										<Button
											variant="secondary"
											onClick={ () => setConfirmDelete( false ) }
										>
											Cancel
										</Button>
									</>
								) : (
									<Button
										variant="secondary"
										onClick={ () => setConfirmDelete( true ) }
									>
										Delete selected
									</Button>
								) }
							</div>
						) }
					</div>

					<ContentLoadingRegion
						busy={ showOverlay }
						variant="overlay"
						label="Loading faculty accounts"
						className="mt-6"
					>
					{ ! hasAccounts ? (
						<p className="text-sm text-text-muted">
							No accounts match your search.
						</p>
					) : (
						<>
							<TableDataViewport
								className="mt-2 bg-surface-raised shadow-card"
								bodyRowCount={ accounts.length }
								rowIncrement={ TABLE_VIEWPORT_ROW_INCREMENT }
							>
								<table className="w-max min-w-full text-left text-sm">
									<thead>
										<tr className="border-b border-border bg-surface text-text-muted">
											<th className="px-4 py-3">
												<input
													type="checkbox"
													aria-label="Select all"
													checked={ allSelected }
													onChange={ handleSelectAll }
												/>
											</th>
											<th
												className={ `${ regNoStickyClass( { header: true } ) } px-4 py-3 font-medium` }
												style={ regNoStickyStyle() }
											>
												Name
											</th>
											<th className="px-4 py-3 font-medium">
												Email
											</th>
											<th className="px-4 py-3 font-medium">
												Emp. ID
											</th>
											<th className="px-4 py-3 font-medium">
												WP user
											</th>
											<th className="px-4 py-3 font-medium">
												Created
											</th>
										</tr>
									</thead>
									<tbody>
										{ accounts.map( ( account ) => (
											<tr
												key={ account.user_id }
												className={ `group ${ TABLE_BODY_ROW_SOFT }` }
											>
												<td className="px-4 py-3">
													<input
														type="checkbox"
														aria-label={ `Select ${ account.display_name || account.email }` }
														checked={ selected.has(
															account.user_id
														) }
														onChange={ () =>
															handleToggleSelect(
																account.user_id
															)
														}
													/>
												</td>
												<td
													className={ `${ regNoStickyClass() } px-4 py-3 font-medium text-text group-hover:bg-surface-raised` }
													style={ regNoStickyStyle() }
												>
													{ account.display_name || '—' }
												</td>
												<td className="px-4 py-3 text-text">
													{ account.email || '—' }
												</td>
												<td className="px-4 py-3 text-text-muted">
													{ account.emp_id || '—' }
												</td>
												<td className="px-4 py-3 text-text-muted">
													{ account.linked
														? `#${ account.user_id }`
														: '—' }
												</td>
												<td className="px-4 py-3 text-text-muted">
													{ account.created_at
														? account.created_at.slice(
																0,
																10
														  )
														: '—' }
												</td>
											</tr>
										) ) }
									</tbody>
								</table>
							</TableDataViewport>

							{ /* Pagination controls */ }
							{ totalPages > 1 && (
								<div className="mt-4 flex items-center justify-between text-sm text-text-muted">
									<span>
										{ total } reviewer
										{ total !== 1 ? 's' : '' } total
									</span>
									<div className="flex items-center gap-2">
										<Button
											variant="secondary"
											disabled={ page <= 1 }
											onClick={ () =>
												setPage( ( p ) => Math.max( 1, p - 1 ) )
											}
										>
											Previous
										</Button>
										<span>
											Page { page } of { totalPages }
										</span>
										<Button
											variant="secondary"
											disabled={ page >= totalPages }
											onClick={ () =>
												setPage( ( p ) =>
													Math.min( totalPages, p + 1 )
												)
											}
										>
											Next
										</Button>
									</div>
								</div>
							) }
						</>
					) }
					</ContentLoadingRegion>
				</>
			) }
		</>
	);
}
