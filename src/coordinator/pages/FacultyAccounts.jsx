import { useCallback, useEffect, useState } from '@wordpress/element';
import { get, post } from '../../shared/api';
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

	const debouncedSearch = useDebouncedValue( search, 300 );

	const loadAccounts = useCallback( async () => {
		setLoading( true );
		try {
			const params = new URLSearchParams( {
				page: '1',
				per_page: '500',
			} );
			if ( debouncedSearch ) {
				params.set( 'search', debouncedSearch );
			}
			const data = await get( `/faculty-accounts?${ params.toString() }` );
			setAccounts( data.accounts ?? [] );
			setDirectoryImport( data.directory_import ?? null );
		} catch {
			setAccounts( [] );
			setDirectoryImport( null );
		} finally {
			setLoading( false );
		}
	}, [ debouncedSearch ] );

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

	return (
		<>
			<PageHeader
				title="Faculty accounts"
				description="Maintain reviewer WordPress accounts before assigning them to panels. Import is silent — use Email all reviewers on Open reviews when marking starts."
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
						faculty table was not found. Expected{' '}
						<code className="text-xs">wp_faculty</code> (or your site
						prefix + <code className="text-xs">faculty</code>). CSV
						import still works.
					</Notice>
				</div>
			) : null }

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
					<TableSkeleton columns={ 5 } />
				</ContentLoadingRegion>
			) : showEmpty ? (
				<EmptyState
					title="No faculty accounts yet"
					description="Import a CSV or sync from the faculty directory to build the reviewer pool."
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
					<div className="mt-6">
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
						<TableDataViewport
							className="mt-6 bg-surface-raised shadow-card"
							bodyRowCount={ accounts.length }
							rowIncrement={ TABLE_VIEWPORT_ROW_INCREMENT }
						>
							<table className="w-max min-w-full text-left text-sm">
								<thead>
									<tr className="border-b border-border bg-surface text-text-muted">
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
					) }
					</ContentLoadingRegion>
				</>
			) }
		</>
	);
}
