import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { useParams, useSearchParams } from 'react-router-dom';
import { OfflineScoringSheetCard } from '../components/OfflineScoringSheetCard';
import { ReportsConsolidatedTable } from '../components/ReportsConsolidatedTable';
import { ReportsMarksTable } from '../components/ReportsMarksTable';
import { REPORTS_TABS, ReportsNav } from '../components/ReportsNav';
import { ReportsOverallScoresTable } from '../components/ReportsOverallScoresTable';
import {
	buildColumns,
	buildMarksExportFilename,
	buildRows,
	rowsToCsv,
	sortRows,
} from '../components/reportsMarksMatrixUtils';
import {
	buildConsolidatedColumns,
	buildConsolidatedExportFilename,
	buildConsolidatedRows,
	consolidatedRowsToCsv,
	sortConsolidatedRows,
} from '../components/reportsConsolidatedUtils';
import {
	buildScoresColumns,
	buildScoresExportFilename,
	buildScoresRows,
	scoresRowsToCsv,
	sortScoresRows,
} from '../components/reportsScoresMatrixUtils';
import { get, post } from '../../shared/api';
import { mapMarkApiError } from '../../shared/markErrors';
import {
	Button,
	CardGridSkeleton,
	ConfirmDialog,
	ContentLoadingRegion,
	EmptyState,
	Notice,
	PageHeader,
	ReportCard,
	StatusChip,
} from '../../shared/components';

const TAB_IDS = REPORTS_TABS.map( ( item ) => item.key );
const LEGACY_TAB_ALIASES = { 'by-review': 'marks' };
const REVIEW_DOWNLOAD_KEYS = [
	'panel_roster',
	'marks_matrix',
	'scores_matrix',
];
const OFFLINE_SCORING_SHEET_KEY = 'offline_scoring_sheet';

export function Reports() {
	const { id } = useParams();
	const [ searchParams, setSearchParams ] = useSearchParams();
	const tabParam = searchParams.get( 'tab' );
	const tab = TAB_IDS.includes( tabParam )
		? tabParam
		: LEGACY_TAB_ALIASES[ tabParam ] ?? 'marks';
	const reviewParam = searchParams.get( 'review' );
	const [ reviews, setReviews ] = useState( [] );
	const [ marksGrid, setMarksGrid ] = useState( null );
	const [ scoresMatrix, setScoresMatrix ] = useState( null );
	const [ reports, setReports ] = useState( [] );
	const [ loadingReviews, setLoadingReviews ] = useState( true );
	const [ loadingView, setLoadingView ] = useState( false );
	const [ loadingReports, setLoadingReports ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ lockOpen, setLockOpen ] = useState( false );
	const [ unlockOpen, setUnlockOpen ] = useState( false );
	const [ locking, setLock ] = useState( false );
	const [ lockError, setLockError ] = useState( '' );
	const [ layout, setLayout ] = useState( 'rubric' );
	const [ sortKey, setSortKey ] = useState( 'reg_no' );
	const [ sortDirection, setSortDirection ] = useState( 'asc' );
	const [ exporting, setExporting ] = useState( null );
	const [ exportError, setExportError ] = useState( '' );
	const [ scoresSortKey, setScoresSortKey ] = useState( 'reg_no' );
	const [ scoresSortDirection, setScoresSortDirection ] = useState( 'asc' );
	const [ scoresExporting, setScoresExporting ] = useState( null );
	const [ scoresExportError, setScoresExportError ] = useState( '' );
	const [ consolidatedScores, setConsolidatedScores ] = useState( null );
	const [ loadingConsolidated, setLoadingConsolidated ] = useState( false );
	const [ consolidatedSortKey, setConsolidatedSortKey ] = useState( 'reg_no' );
	const [ consolidatedSortDirection, setConsolidatedSortDirection ] =
		useState( 'asc' );
	const [ consolidatedExporting, setConsolidatedExporting ] = useState( null );
	const [ consolidatedExportError, setConsolidatedExportError ] =
		useState( '' );

	const confirmedReviews = useMemo(
		() => reviews.filter( ( r ) => r.status === 'confirmed' ),
		[ reviews ]
	);

	const reviewPool = useMemo( () => {
		if ( tab === 'downloads' ) {
			return reviews;
		}
		return confirmedReviews;
	}, [ tab, reviews, confirmedReviews ] );

	const selectedReviewId = useMemo( () => {
		if ( reviewPool.length === 0 ) {
			return '';
		}
		if (
			reviewParam &&
			reviewPool.some( ( review ) => String( review.id ) === String( reviewParam ) )
		) {
			return String( reviewParam );
		}
		const fallback =
			tab === 'marks' || tab === 'scores'
				? confirmedReviews[ 0 ]
				: reviewPool[ 0 ];
		return fallback ? String( fallback.id ) : '';
	}, [ reviewParam, reviewPool, confirmedReviews, tab ] );

	const selectedReview = useMemo(
		() =>
			confirmedReviews.find(
				( r ) => String( r.id ) === String( selectedReviewId )
			),
		[ confirmedReviews, selectedReviewId ]
	 );

	const coordinatorLocked = Boolean(
		marksGrid?.coordinator_marks_locked ||
			scoresMatrix?.coordinator_marks_locked ||
			selectedReview?.coordinator_marks_locked
	);

	const reviewLockReady = Boolean(
		marksGrid?.review_lock_ready ?? scoresMatrix?.review_lock_ready
	);

	const unfrozenPanels =
		marksGrid?.unfrozen_panels?.length > 0
			? marksGrid.unfrozen_panels
			: scoresMatrix?.unfrozen_panels ?? [];

	const maxPanelReviewerSlots =
		marksGrid?.max_panel_reviewer_slots ??
		scoresMatrix?.max_panel_reviewer_slots ??
		0;

	const catalogByKey = useMemo(
		() => Object.fromEntries( reports.map( ( report ) => [ report.key, report ] ) ),
		[ reports ]
	);

	const sessionWideDownload = catalogByKey.consolidated_student_scores;
	const reviewRoundDownloads = useMemo(
		() =>
			REVIEW_DOWNLOAD_KEYS.map( ( key ) => catalogByKey[ key ] ).filter(
				Boolean
			),
		[ catalogByKey ]
	);
	const offlineScoringDownload = catalogByKey[ OFFLINE_SCORING_SHEET_KEY ];

	const matrixDownloadReviewId = useMemo( () => {
		if (
			confirmedReviews.some(
				( review ) => String( review.id ) === String( selectedReviewId )
			)
		) {
			return selectedReviewId;
		}
		return '';
	}, [ confirmedReviews, selectedReviewId ] );

	const setSelectedReviewId = useCallback(
		( reviewId ) => {
			setSearchParams( ( prev ) => {
				const next = new URLSearchParams( prev );
				if ( reviewId ) {
					next.set( 'review', String( reviewId ) );
				} else {
					next.delete( 'review' );
				}
				return next;
			} );
		},
		[ setSearchParams ]
	);

	const goToTab = useCallback(
		( nextTab ) => {
			setSearchParams( ( prev ) => {
				const next = new URLSearchParams( prev );
				next.set( 'tab', nextTab );
				return next;
			} );
		},
		[ setSearchParams ]
	);

	useEffect( () => {
		const legacyTarget = tabParam ? LEGACY_TAB_ALIASES[ tabParam ] : null;
		if ( legacyTarget ) {
			setSearchParams(
				( prev ) => {
					const next = new URLSearchParams( prev );
					next.set( 'tab', legacyTarget );
					return next;
				},
				{ replace: true }
			);
			return;
		}
		if ( tabParam && ! TAB_IDS.includes( tabParam ) ) {
			setSearchParams(
				( prev ) => {
					const next = new URLSearchParams( prev );
					next.set( 'tab', 'marks' );
					return next;
				},
				{ replace: true }
			);
		}
	}, [ tabParam, setSearchParams ] );

	const columns = useMemo(
		() =>
			buildColumns(
				layout,
				marksGrid?.criteria,
				maxPanelReviewerSlots
			),
		[ layout, marksGrid?.criteria, maxPanelReviewerSlots ]
	);

	const matrixRows = useMemo(
		() =>
			buildRows(
				marksGrid?.students,
				scoresMatrix?.students,
				maxPanelReviewerSlots
			),
		[ marksGrid?.students, scoresMatrix?.students, maxPanelReviewerSlots ]
	);

	const sortedRows = useMemo(
		() => sortRows( matrixRows, columns, sortKey, sortDirection ),
		[ matrixRows, columns, sortKey, sortDirection ]
	);

	const scoresColumns = useMemo(
		() => buildScoresColumns( maxPanelReviewerSlots ),
		[ maxPanelReviewerSlots ]
	);

	const scoresMatrixRows = useMemo(
		() =>
			buildScoresRows(
				marksGrid?.students,
				scoresMatrix?.students,
				maxPanelReviewerSlots
			),
		[ marksGrid?.students, scoresMatrix?.students, maxPanelReviewerSlots ]
	);

	const sortedScoresRows = useMemo(
		() =>
			sortScoresRows(
				scoresMatrixRows,
				scoresColumns,
				scoresSortKey,
				scoresSortDirection
			),
		[
			scoresMatrixRows,
			scoresColumns,
			scoresSortKey,
			scoresSortDirection,
		]
	);

	const handleScoresSort = useCallback( ( key ) => {
		setScoresSortKey( ( prevKey ) => {
			if ( prevKey === key ) {
				setScoresSortDirection( ( prevDir ) =>
					prevDir === 'asc' ? 'desc' : 'asc'
				);
				return prevKey;
			}

			setScoresSortDirection( 'asc' );
			return key;
		} );
	}, [] );

	const consolidatedColumns = useMemo(
		() => buildConsolidatedColumns( consolidatedScores?.reviews ?? [] ),
		[ consolidatedScores?.reviews ]
	);

	const consolidatedRows = useMemo(
		() =>
			buildConsolidatedRows(
				consolidatedScores?.students,
				consolidatedScores?.reviews
			),
		[ consolidatedScores?.students, consolidatedScores?.reviews ]
	);

	const sortedConsolidatedRows = useMemo(
		() =>
			sortConsolidatedRows(
				consolidatedRows,
				consolidatedSortKey,
				consolidatedSortDirection
			),
		[
			consolidatedRows,
			consolidatedSortKey,
			consolidatedSortDirection,
		]
	);

	const handleConsolidatedSort = useCallback( ( key ) => {
		setConsolidatedSortKey( ( prevKey ) => {
			if ( prevKey === key ) {
				setConsolidatedSortDirection( ( prevDir ) =>
					prevDir === 'asc' ? 'desc' : 'asc'
				);
				return prevKey;
			}

			setConsolidatedSortDirection( 'asc' );
			return key;
		} );
	}, [] );

	const handleSort = useCallback( ( key ) => {
		setSortKey( ( prevKey ) => {
			if ( prevKey === key ) {
				setSortDirection( ( prevDir ) =>
					prevDir === 'asc' ? 'desc' : 'asc'
				);
				return prevKey;
			}

			setSortDirection( 'asc' );
			return key;
		} );
	}, [] );

	const sessionSlug = useMemo( () => {
		const title = window.prAppData?.sessionTitle;
		if ( title ) {
			return String( title )
				.toLowerCase()
				.replace( /[^a-z0-9]+/g, '-' )
				.replace( /^-+|-+$/g, '' );
		}

		return `session-${ id }`;
	}, [ id ] );

	const reviewSlug = useMemo( () => {
		const label = selectedReview?.label || selectedReviewId;
		return String( label )
			.toLowerCase()
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}, [ selectedReview, selectedReviewId ] );

	const downloadCsv = useCallback( () => {
		setExporting( 'csv' );
		setExportError( '' );
		try {
			const csv = rowsToCsv( columns, sortedRows );
			const blob = new Blob( [ csv ], { type: 'text/csv;charset=utf-8' } );
			const filename = buildMarksExportFilename(
				sessionSlug,
				reviewSlug,
				layout,
				'csv'
			);
			const link = document.createElement( 'a' );
			link.href = URL.createObjectURL( blob );
			link.download = filename;
			link.click();
			URL.revokeObjectURL( link.href );
		} catch {
			setExportError( 'CSV export failed. Please try again.' );
		} finally {
			setExporting( null );
		}
	}, [ columns, sortedRows, sessionSlug, reviewSlug, layout ] );

	const downloadExcel = useCallback( async () => {
		if ( ! selectedReviewId ) {
			return;
		}

		setExporting( 'xlsx' );
		setExportError( '' );
		const apiRoot = window.prAppData?.restUrl || '';
		const params = new URLSearchParams( {
			format: 'xlsx',
			layout,
			sort_key: sortKey,
			sort_dir: sortDirection,
		} );

		try {
			const url = `${ apiRoot }sessions/${ id }/reviews/${ selectedReviewId }/marks-grid/download?${ params }`;
			const response = await fetch( url, {
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': window.prAppData?.nonce || '',
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'download_failed' );
			}
			const blob = await response.blob();
			const disposition = response.headers.get( 'Content-Disposition' ) || '';
			const match = disposition.match( /filename="([^"]+)"/ );
			const filename =
				match?.[ 1 ] ||
				buildMarksExportFilename( sessionSlug, reviewSlug, layout, 'xlsx' );
			const link = document.createElement( 'a' );
			link.href = URL.createObjectURL( blob );
			link.download = filename;
			link.click();
			URL.revokeObjectURL( link.href );
		} catch {
			setExportError( 'Excel export failed. Please try again.' );
		} finally {
			setExporting( null );
		}
	}, [
		id,
		selectedReviewId,
		layout,
		sortKey,
		sortDirection,
		sessionSlug,
		reviewSlug,
	] );

	const downloadConsolidatedCsv = useCallback( () => {
		setConsolidatedExporting( 'csv' );
		setConsolidatedExportError( '' );
		try {
			const csv = consolidatedRowsToCsv(
				consolidatedColumns,
				sortedConsolidatedRows
			);
			const blob = new Blob( [ csv ], { type: 'text/csv;charset=utf-8' } );
			const filename = buildConsolidatedExportFilename( sessionSlug, 'csv' );
			const link = document.createElement( 'a' );
			link.href = URL.createObjectURL( blob );
			link.download = filename;
			link.click();
			URL.revokeObjectURL( link.href );
		} catch {
			setConsolidatedExportError( 'CSV export failed. Please try again.' );
		} finally {
			setConsolidatedExporting( null );
		}
	}, [ consolidatedColumns, sortedConsolidatedRows, sessionSlug ] );

	const downloadConsolidatedExcel = useCallback( async () => {
		setConsolidatedExporting( 'xlsx' );
		setConsolidatedExportError( '' );
		const apiRoot = window.prAppData?.restUrl || '';
		const params = new URLSearchParams( {
			format: 'xlsx',
			sort_key: consolidatedSortKey,
			sort_dir: consolidatedSortDirection,
		} );

		try {
			const url = `${ apiRoot }sessions/${ id }/consolidated-scores/download?${ params }`;
			const response = await fetch( url, {
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': window.prAppData?.nonce || '',
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'download_failed' );
			}
			const blob = await response.blob();
			const disposition = response.headers.get( 'Content-Disposition' ) || '';
			const match = disposition.match( /filename="([^"]+)"/ );
			const filename =
				match?.[ 1 ] ||
				buildConsolidatedExportFilename( sessionSlug, 'xlsx' );
			const link = document.createElement( 'a' );
			link.href = URL.createObjectURL( blob );
			link.download = filename;
			link.click();
			URL.revokeObjectURL( link.href );
		} catch {
			setConsolidatedExportError( 'Excel export failed. Please try again.' );
		} finally {
			setConsolidatedExporting( null );
		}
	}, [
		id,
		consolidatedSortKey,
		consolidatedSortDirection,
		sessionSlug,
	] );

	const downloadScoresCsv = useCallback( () => {
		setScoresExporting( 'csv' );
		setScoresExportError( '' );
		try {
			const csv = scoresRowsToCsv( scoresColumns, sortedScoresRows );
			const blob = new Blob( [ csv ], { type: 'text/csv;charset=utf-8' } );
			const filename = buildScoresExportFilename(
				sessionSlug,
				reviewSlug,
				'csv'
			);
			const link = document.createElement( 'a' );
			link.href = URL.createObjectURL( blob );
			link.download = filename;
			link.click();
			URL.revokeObjectURL( link.href );
		} catch {
			setScoresExportError( 'CSV export failed. Please try again.' );
		} finally {
			setScoresExporting( null );
		}
	}, [ scoresColumns, sortedScoresRows, sessionSlug, reviewSlug ] );

	const downloadScoresExcel = useCallback( async () => {
		if ( ! selectedReviewId ) {
			return;
		}

		setScoresExporting( 'xlsx' );
		setScoresExportError( '' );
		const apiRoot = window.prAppData?.restUrl || '';
		const params = new URLSearchParams( {
			format: 'xlsx',
			sort_key: scoresSortKey,
			sort_dir: scoresSortDirection,
		} );

		try {
			const url = `${ apiRoot }sessions/${ id }/reviews/${ selectedReviewId }/scores-matrix/download?${ params }`;
			const response = await fetch( url, {
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': window.prAppData?.nonce || '',
				},
			} );
			if ( ! response.ok ) {
				throw new Error( 'download_failed' );
			}
			const blob = await response.blob();
			const disposition = response.headers.get( 'Content-Disposition' ) || '';
			const match = disposition.match( /filename="([^"]+)"/ );
			const filename =
				match?.[ 1 ] ||
				buildScoresExportFilename( sessionSlug, reviewSlug, 'xlsx' );
			const link = document.createElement( 'a' );
			link.href = URL.createObjectURL( blob );
			link.download = filename;
			link.click();
			URL.revokeObjectURL( link.href );
		} catch {
			setScoresExportError( 'Excel export failed. Please try again.' );
		} finally {
			setScoresExporting( null );
		}
	}, [
		id,
		selectedReviewId,
		scoresSortKey,
		scoresSortDirection,
		sessionSlug,
		reviewSlug,
	] );

	const loadReviews = useCallback( async () => {
		setLoadingReviews( true );
		try {
			const data = await get( `sessions/${ id }/reviews` );
			const list = Array.isArray( data ) ? data : data?.reviews || [];
			setReviews( list );
		} catch {
			setError( 'Unable to load reviews.' );
		} finally {
			setLoadingReviews( false );
		}
	}, [ id ] );

	const loadDownloads = useCallback( async () => {
		setLoadingReports( true );
		try {
			const data = await get( `sessions/${ id }/reports` );
			setReports( Array.isArray( data ) ? data : [] );
		} catch {
			setError( 'Unable to load report downloads.' );
		} finally {
			setLoadingReports( false );
		}
	}, [ id ] );

	const loadConsolidated = useCallback( async () => {
		setLoadingConsolidated( true );
		setError( '' );
		try {
			const data = await get( `sessions/${ id }/consolidated-scores` );
			setConsolidatedScores( data );
		} catch {
			setError( 'Unable to load consolidated scores.' );
		} finally {
			setLoadingConsolidated( false );
		}
	}, [ id ] );

	const loadLiveView = useCallback( async () => {
		if ( ! selectedReviewId ) {
			setMarksGrid( null );
			setScoresMatrix( null );
			return;
		}

		setLoadingView( true );
		setError( '' );
		try {
			const [ marks, scores ] = await Promise.all( [
				get(
					`sessions/${ id }/reviews/${ selectedReviewId }/marks-grid`
				),
				get(
					`sessions/${ id }/reviews/${ selectedReviewId }/scores-matrix`
				),
			] );
			setMarksGrid( marks );
			setScoresMatrix( scores );
		} catch {
			setError( 'Unable to load report data for this review.' );
		} finally {
			setLoadingView( false );
		}
	}, [ id, selectedReviewId ] );

	useEffect( () => {
		loadReviews();
		loadDownloads();
	}, [ loadReviews, loadDownloads ] );

	useEffect( () => {
		if ( tab === 'marks' || tab === 'scores' ) {
			loadLiveView();
		}
	}, [ tab, loadLiveView ] );

	useEffect( () => {
		if ( tab === 'consolidated' ) {
			loadConsolidated();
		}
	}, [ tab, loadConsolidated ] );

	useEffect( () => {
		setSortKey( 'reg_no' );
		setSortDirection( 'asc' );
		setExportError( '' );
		setScoresSortKey( 'reg_no' );
		setScoresSortDirection( 'asc' );
		setScoresExportError( '' );
	}, [ selectedReviewId ] );

	const handleLock = async () => {
		if ( ! selectedReviewId ) {
			return;
		}

		setLock( true );
		setLockError( '' );
		try {
			await post(
				`sessions/${ id }/reviews/${ selectedReviewId }/lock-marks`,
				{}
			);
			setLockOpen( false );
			await loadReviews();
			await loadLiveView();
		} catch ( err ) {
			const mapped = mapMarkApiError( err );
			setLockError( mapped.message || 'Unable to freeze this review.' );
		} finally {
			setLock( false );
		}
	};

	const handleUnlock = async () => {
		if ( ! selectedReviewId ) {
			return;
		}

		setLock( true );
		setLockError( '' );
		try {
			await post(
				`sessions/${ id }/reviews/${ selectedReviewId }/unlock-marks`,
				{}
			);
			setUnlockOpen( false );
			await loadReviews();
			await loadLiveView();
		} catch ( err ) {
			const mapped = mapMarkApiError( err );
			setLockError( mapped.message || 'Unable to unlock this review.' );
		} finally {
			setLock( false );
		}
	};

	const freezeDisabledReason = useMemo( () => {
		if ( coordinatorLocked || ! selectedReviewId ) {
			return '';
		}
		if ( reviewLockReady ) {
			return '';
		}
		if ( unfrozenPanels.length === 0 ) {
			return 'Assign students to panels and freeze each panel’s scores first (Panel report).';
		}
		const names = unfrozenPanels.map( ( p ) => p.name ).join( ', ' );
		return `Freeze each panel’s scores first: ${ names }.`;
	}, [
		coordinatorLocked,
		selectedReviewId,
		reviewLockReady,
		unfrozenPanels,
	] );

	const apiRoot = window.prAppData?.restUrl || '';
	const marksTableLoading = loadingView || loadingReviews;
	const hasCriteria = ( marksGrid?.criteria?.length ?? 0 ) > 0;

	return (
		<>
			<PageHeader
				title="Reports"
				description="Live marks by review round, project-wide consolidated scores, and committee downloads."
			/>

			{ error ? (
				<div className="mb-4">
					<Notice variant="error">{ error }</Notice>
				</div>
			) : null }

			<ReportsNav currentTab={ tab } onTabClick={ goToTab } />

			{ tab === 'marks' || tab === 'scores' ? (
				<section aria-labelledby="reports-review-round-heading">
					<h2
						id="reports-review-round-heading"
						className="sr-only"
					>
						{ tab === 'marks' ? 'Marks' : 'Overall scores' }
					</h2>
					<p className="mb-6 text-sm text-text-muted">
						{ tab === 'marks'
							? 'Live rubric marks matrix for one review round. Use Override on a scored cell to correct a reviewer mark (requires a reason; changes are labeled as coordinator overrides).'
							: 'Live overall scores matrix for one review round. Choose a round to load data; bookmark or share this page with the review in the URL.' }
					</p>

					<div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
						<div className="flex flex-wrap items-center gap-3">
							<label className="block text-sm">
								<span className="mb-1 block font-medium text-text">
									Review round
								</span>
								<select
									className="min-w-[12rem] rounded-md border border-border bg-surface px-3 py-2 text-sm text-text"
									value={ selectedReviewId }
									onChange={ ( e ) =>
										setSelectedReviewId( e.target.value )
									}
									disabled={
										loadingReviews ||
										confirmedReviews.length === 0
									}
								>
									{ confirmedReviews.length === 0 ? (
										<option value="">
											No confirmed reviews
										</option>
									) : (
										confirmedReviews.map( ( review ) => (
											<option
												key={ review.id }
												value={ review.id }
											>
												{ review.label ||
													`Review ${ review.id }` }
											</option>
										) )
									) }
								</select>
							</label>
							{ coordinatorLocked ? (
								<StatusChip
									variant="confirmed"
									label="Marks locked"
								/>
							) : null }
						</div>
						<div className="flex flex-col items-end gap-2">
							{ coordinatorLocked && selectedReviewId ? (
								<Button
									variant="primary"
									onClick={ () => setUnlockOpen( true ) }
								>
									Unlock review
								</Button>
							) : null }
							{ ! coordinatorLocked && selectedReviewId ? (
								<Button
									variant="destructive"
									disabled={ ! reviewLockReady }
									onClick={ () => setLockOpen( true ) }
								>
									Freeze review
								</Button>
							) : null }
							{ freezeDisabledReason ? (
								<p className="max-w-md text-right text-xs text-text-muted">
									{ freezeDisabledReason }
								</p>
							) : null }
						</div>
					</div>

					{ tab === 'marks' ? (
						confirmedReviews.length === 0 && ! loadingReviews ? (
							<EmptyState
								title="No confirmed rubrics"
								description="Confirm a review rubric in the setup wizard before live rubric marks appear here."
							/>
						) : ! hasCriteria && ! marksTableLoading ? (
							<p className="text-sm text-text-muted">
								No rubric criteria for this review. Add criteria on Reviews &
								rubrics in the setup wizard and confirm the rubric for this
								review round.
							</p>
						) : (
							<ReportsMarksTable
									columns={ columns }
									rows={ sortedRows }
									loading={ marksTableLoading }
									layout={ layout }
									onLayoutChange={ setLayout }
									sortKey={ sortKey }
									sortDirection={ sortDirection }
									onSort={ handleSort }
									exporting={ exporting }
									onDownloadCsv={ downloadCsv }
									onDownloadExcel={ downloadExcel }
									exportError={ exportError }
									coordinatorLocked={ coordinatorLocked }
									reviewLabel={ selectedReview?.label }
									criteria={ marksGrid?.criteria ?? [] }
									onMarksChanged={ loadLiveView }
							/>
						)
					) : null }

					{ tab === 'scores' ? (
						confirmedReviews.length === 0 && ! loadingReviews ? (
							<EmptyState
								title="No confirmed rubrics"
								description="Confirm a review rubric before overall scores are shown."
							/>
						) : (
							<ReportsOverallScoresTable
									columns={ scoresColumns }
									rows={ sortedScoresRows }
									loading={ marksTableLoading }
									sortKey={ scoresSortKey }
									sortDirection={ scoresSortDirection }
									onSort={ handleScoresSort }
									exporting={ scoresExporting }
									onDownloadCsv={ downloadScoresCsv }
									onDownloadExcel={ downloadScoresExcel }
									exportError={ scoresExportError }
									coordinatorLocked={ coordinatorLocked }
							/>
						)
					) : null }
				</section>
			) : null }

			{ tab === 'consolidated' ? (
				<section aria-labelledby="reports-consolidated-heading">
					<h2
						id="reports-consolidated-heading"
						className="sr-only"
					>
						Consolidated
					</h2>
					<p className="mb-6 text-sm text-text-muted">
						All students across every confirmed review round in this
						project. Not tied to a single review selection.
					</p>
					<ReportsConsolidatedTable
						data={ consolidatedScores }
						loading={ loadingConsolidated }
						sortKey={ consolidatedSortKey }
						sortDirection={ consolidatedSortDirection }
						onSort={ handleConsolidatedSort }
						exporting={ consolidatedExporting }
						onDownloadCsv={ downloadConsolidatedCsv }
						onDownloadExcel={ downloadConsolidatedExcel }
						exportError={ consolidatedExportError }
					/>
				</section>
			) : null }

			{ tab === 'downloads' ? (
				<section aria-labelledby="reports-downloads-heading">
					<h2 id="reports-downloads-heading" className="sr-only">
						Downloads
					</h2>
					<p className="mb-6 text-sm text-text-muted">
						Committee deliverables for this project. Project-wide
						exports need no review; per-review files use the round
						selector below.
					</p>
					{ loadingReports ? (
						<ContentLoadingRegion
							busy
							variant="inline"
							label="Loading downloads"
							className="mb-4"
						>
							<CardGridSkeleton count={ 3 } />
						</ContentLoadingRegion>
					) : null }
					{ ! loadingReports && reports.length === 0 ? (
						<EmptyState
							title="No reports"
							description="Reports will appear when the project is configured."
						/>
					) : null }
					{ ! loadingReports && reports.length > 0 ? (
						<>
							<div className="mb-10">
								<h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-text-muted">
									Project-wide
								</h3>
								<div className="grid gap-4 md:grid-cols-2">
									{ sessionWideDownload ? (
										<ReportCard
											key={ sessionWideDownload.key }
											report={ sessionWideDownload }
											sessionId={ id }
											apiRoot={ apiRoot }
										/>
									) : null }
								</div>
							</div>
							<div>
								<h3 className="mb-4 text-sm font-semibold uppercase tracking-wide text-text-muted">
									By review round
								</h3>
								<label className="mb-6 block max-w-md text-sm">
									<span className="mb-1 block font-medium text-text">
										Review round
									</span>
									<select
										className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text"
										value={ selectedReviewId }
										onChange={ ( e ) =>
											setSelectedReviewId( e.target.value )
										}
										disabled={ reviews.length === 0 }
									>
										{ reviews.length === 0 ? (
											<option value="">
												No review rounds
											</option>
										) : (
											reviews.map( ( review ) => (
												<option
													key={ review.id }
													value={ review.id }
												>
													{ review.label ||
														`Review ${ review.id }` }
													{ review.status &&
													review.status !== 'confirmed'
														? ` (${ review.status })`
														: '' }
												</option>
											) )
										) }
									</select>
								</label>
								<div className="grid gap-4 md:grid-cols-2">
									{ reviewRoundDownloads.map( ( report ) => (
										<ReportCard
											key={ report.key }
											report={ report }
											sessionId={ id }
											apiRoot={ apiRoot }
											reviewId={
												report.key === 'panel_roster'
													? selectedReviewId
													: matrixDownloadReviewId
											}
											reviews={
												report.key === 'panel_roster'
													? reviews
													: confirmedReviews
											}
										/>
									) ) }
									{ offlineScoringDownload ? (
										<OfflineScoringSheetCard
											sessionId={ id }
											reviews={ confirmedReviews }
											reviewId={ matrixDownloadReviewId }
											hideReviewSelector
										/>
									) : null }
								</div>
							</div>
						</>
					) : null }
				</section>
			) : null }

			<ConfirmDialog
				open={ lockOpen }
				title="Freeze this review?"
				consequences={ [
					'Reviewers cannot save, freeze, or change marks or attendance for this review round.',
					'Marking is turned off in the setup wizard until you unlock.',
					'Coordinator overrides and reviewer unfreeze grants stay blocked while frozen.',
				] }
				confirmLabel="Freeze review"
				confirmVariant="destructive"
				confirmDisabled={ locking }
				onConfirm={ handleLock }
				onCancel={ () => {
					setLockOpen( false );
					setLockError( '' );
				} }
			>
				{ lockError ? (
					<Notice variant="error">{ lockError }</Notice>
				) : null }
			</ConfirmDialog>

			<ConfirmDialog
				open={ unlockOpen }
				title="Unlock this review?"
				consequences={ [
					'Reviewers can save and freeze marks again (subject to panel and personal freeze rules).',
					'Marking can be turned on again on Open reviews when the project is active.',
					'Panel-level freezes remain until panel coordinators unfreeze them.',
				] }
				confirmLabel="Unlock review"
				confirmVariant="primary"
				confirmDisabled={ locking }
				onConfirm={ handleUnlock }
				onCancel={ () => {
					setUnlockOpen( false );
					setLockError( '' );
				} }
			>
				{ lockError ? (
					<Notice variant="error">{ lockError }</Notice>
				) : null }
			</ConfirmDialog>
		</>
	);
}
