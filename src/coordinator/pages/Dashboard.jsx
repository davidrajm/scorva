import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import {
	Link,
	useLocation,
	useNavigate,
	useSearchParams,
} from 'react-router-dom';
import { get, post } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import { useDebouncedValue } from '../../shared/hooks/useDebouncedValue';
import {
	DASHBOARD_STATUS_TABS,
	DashboardStatusNav,
} from '../components/DashboardStatusNav';
import { PanelUnfreezeRequests } from '../components/PanelUnfreezeRequests';
import {
	Button,
	CardGridSkeleton,
	ContentLoadingRegion,
	EmptyState,
	Notice,
	PageHeader,
	SessionCard,
} from '../../shared/components';
import { useLoadingPhase } from '../../shared/hooks/useLoadingPhase';

const STATUS_TAB_IDS = DASHBOARD_STATUS_TABS.map( ( item ) => item.key );

export function Dashboard() {
	const navigate = useNavigate();
	const location = useLocation();
	const [ searchParams, setSearchParams ] = useSearchParams();
	const statusParam = searchParams.get( 'status' );
	const statusFilter = STATUS_TAB_IDS.includes( statusParam )
		? statusParam
		: 'active';
	const apiStatus = statusFilter === 'all' ? '' : statusFilter;
	const [ sessions, setSessions ] = useState( null );
	const [ success, setSuccess ] = useState( '' );
	const [ fetching, setFetching ] = useState( true );
	const [ creating, setCreating ] = useState( false );
	const [ newTitle, setNewTitle ] = useState( '' );
	const [ showCreate, setShowCreate ] = useState( false );
	const [ createError, setCreateError ] = useState( null );
	const [ registrySearch, setRegistrySearch ] = useState( '' );
	const [ registryResults, setRegistryResults ] = useState( [] );
	const [ selectedStudents, setSelectedStudents ] = useState( [] );
	const debouncedSearch = useDebouncedValue( registrySearch, 300 );

	const loadSessions = useCallback( async () => {
		setFetching( true );
		try {
			const query = apiStatus
				? `?status=${ encodeURIComponent( apiStatus ) }`
				: '';
			const data = await get( `/sessions${ query }` );
			setSessions( Array.isArray( data ) ? data : [] );
		} catch {
			setSessions( [] );
		} finally {
			setFetching( false );
		}
	}, [ apiStatus ] );

	const { showSkeleton, showOverlay } = useLoadingPhase(
		fetching,
		sessions !== null
	);

	const setStatusFilter = ( next ) => {
		setSearchParams( ( prev ) => {
			const nextParams = new URLSearchParams( prev );
			nextParams.set( 'status', next );
			return nextParams;
		} );
	};

	useEffect( () => {
		if ( statusParam !== null && ! STATUS_TAB_IDS.includes( statusParam ) ) {
			setSearchParams(
				( prev ) => {
					const next = new URLSearchParams( prev );
					next.set( 'status', 'active' );
					return next;
				},
				{ replace: true }
			);
		}
	}, [ statusParam, setSearchParams ] );

	useEffect( () => {
		loadSessions();
	}, [ loadSessions ] );

	useEffect( () => {
		const notice = location.state?.notice;
		if ( typeof notice !== 'string' || notice === '' ) {
			return;
		}
		setSuccess( notice );
		navigate(
			{ pathname: location.pathname, search: location.search },
			{ replace: true, state: null }
		);
	}, [ location.pathname, location.search, location.state, navigate ] );

	useEffect( () => {
		if ( ! debouncedSearch ) {
			setRegistryResults( [] );
			return;
		}

		( async () => {
			try {
				const data = await get(
					`/students?search=${ encodeURIComponent( debouncedSearch ) }`
				);
				const selectedIds = new Set(
					selectedStudents.map( ( student ) => student.id )
				);
				setRegistryResults(
					( data.students ?? [] ).filter(
						( student ) => ! selectedIds.has( student.id )
					)
				);
			} catch {
				setRegistryResults( [] );
			}
		} )();
	}, [ debouncedSearch, selectedStudents ] );

	const resetCreateForm = () => {
		setNewTitle( '' );
		setRegistrySearch( '' );
		setRegistryResults( [] );
		setSelectedStudents( [] );
	};

	const addStudent = ( student ) => {
		setSelectedStudents( ( current ) => [ ...current, student ] );
		setRegistrySearch( '' );
		setRegistryResults( [] );
	};

	const removeStudent = ( studentId ) => {
		setSelectedStudents( ( current ) =>
			current.filter( ( student ) => student.id !== studentId )
		);
	};

	const handleCreate = async ( event ) => {
		event.preventDefault();
		const title = newTitle.trim();
		if ( ! title ) {
			return;
		}
		setCreating( true );
		setCreateError( null );
		try {
			const payload = { title };
			if ( selectedStudents.length > 0 ) {
				payload.student_ids = selectedStudents.map(
					( student ) => student.id
				);
			}
			const session = await post( '/sessions', payload );
			resetCreateForm();
			setShowCreate( false );
			if ( ! session?.id ) {
				setCreateError(
					'Project was created but the server response was incomplete. Refresh the dashboard and try again.'
				);
				return;
			}
			navigate( `/session/${ session.id }/wizard?step=students` );
		} catch ( err ) {
			setCreateError(
				parseApiErrorMessage( err, 'Could not create project.' )
			);
		} finally {
			setCreating( false );
		}
	};

	const filteredCount = useMemo(
		() => ( sessions ?? [] ).length,
		[ sessions ]
	);

	return (
		<>
			{ success ? (
				<Notice variant="success">{ success }</Notice>
			) : null }

			<PageHeader
				title="Dashboard"
				description="Create and manage review projects."
				actions={
					<Button
						variant="primary"
						type="button"
						data-testid="pr-show-create-project"
						onClick={ () => setShowCreate( true ) }
					>
						Create project
					</Button>
				}
			/>

			{ showCreate ? (
				<form
					data-testid="pr-create-project"
					onSubmit={ handleCreate }
					className="mt-4 space-y-4 rounded-md border border-border bg-surface-raised p-4"
				>
					{ createError ? (
						<p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger">
							{ createError }
						</p>
					) : null }

					<div>
						<label
							htmlFor="session-title"
							className="block text-sm font-medium text-text"
						>
							Project title
						</label>
						<input
							id="session-title"
							data-testid="pr-project-title"
							type="text"
							value={ newTitle }
							onChange={ ( e ) => setNewTitle( e.target.value ) }
							className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
							placeholder="e.g. May 2026 project reviews"
							required
						/>
					</div>

					<div>
						<label
							htmlFor="create-registry-search"
							className="block text-sm font-medium text-text"
						>
							Add students from directory (optional)
						</label>
						<p className="mt-1 text-sm text-text-muted">
							This roster is the default for every review round in
							this project. You can change it later in the wizard.
						</p>
						<input
							id="create-registry-search"
							data-testid="pr-registry-search"
							type="search"
							value={ registrySearch }
							onChange={ ( e ) => setRegistrySearch( e.target.value ) }
							className="mt-2 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-sm"
							placeholder="Registration number or name"
						/>
						{ registryResults.length > 0 ? (
							<ul className="mt-2 max-w-md rounded-md border border-border bg-surface">
								{ registryResults.map( ( student ) => (
									<li
										key={ student.id }
										className="flex items-center justify-between gap-2 border-b border-border/60 px-3 py-2 last:border-0"
									>
										<span className="text-sm text-text">
											{ student.reg_no } — { student.name }
										</span>
										<Button
											size="sm"
											variant="secondary"
											type="button"
											onClick={ () => addStudent( student ) }
										>
											Add
										</Button>
									</li>
								) ) }
							</ul>
						) : null }
						{ selectedStudents.length > 0 ? (
							<ul className="mt-3 flex flex-wrap gap-2">
								{ selectedStudents.map( ( student ) => (
									<li
										key={ student.id }
										className="flex items-center gap-2 rounded-full border border-border bg-surface px-3 py-1 text-sm"
									>
										<span>
											{ student.reg_no } — { student.name }
										</span>
										<button
											type="button"
											className="text-text-muted hover:text-text"
											onClick={ () => removeStudent( student.id ) }
											aria-label={ `Remove ${ student.name }` }
										>
											×
										</button>
									</li>
								) ) }
							</ul>
						) : null }
					</div>

					<p className="text-sm text-text-muted">
						<Link
							to="/registry"
							className="font-medium text-primary hover:underline"
						>
							Manage student directory
						</Link>
						{ ' ' }
						for bulk import, custom fields, or cross-project search.
					</p>

					<div className="flex flex-wrap gap-3">
						<Button variant="primary" type="submit" loading={ creating }>
							Create project
						</Button>
						<Button
							variant="secondary"
							type="button"
							onClick={ () => {
								resetCreateForm();
								setCreateError( null );
								setShowCreate( false );
							} }
						>
							Cancel
						</Button>
					</div>
				</form>
			) : null }

			<PanelUnfreezeRequests className="mt-6" />

			<DashboardStatusNav
				currentStatus={ statusFilter }
				onStatusClick={ setStatusFilter }
			/>

			<p className="mt-4 mb-6 text-sm text-text-muted">
				{ showSkeleton
					? 'Loading projects…'
					: `${ filteredCount } project${ filteredCount === 1 ? '' : 's' }` }
			</p>

			<ContentLoadingRegion
				busy={ showOverlay }
				variant="overlay"
				label="Loading projects"
				className="min-h-[12rem]"
			>
				{ showSkeleton ? (
					<CardGridSkeleton />
				) : ! sessions?.length ? (
					<EmptyState
						title="No review projects yet"
						description="Create a project to attach a student roster, assign panels, and open marking."
						action={
							<Button
								variant="primary"
								type="button"
								onClick={ () => setShowCreate( true ) }
							>
								Create project
							</Button>
						}
					/>
				) : (
					<ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
						{ sessions.map( ( session ) => (
							<li key={ session.id }>
								<SessionCard
									title={ session.title ?? 'Project' }
									status={ session.status ?? 'draft' }
									progress={ session.progress }
									enrolledCount={ session.enrolled_count }
									to={ `/session/${ session.id }/wizard?step=students` }
								/>
							</li>
						) ) }
					</ul>
				) }
			</ContentLoadingRegion>
		</>
	);
}
