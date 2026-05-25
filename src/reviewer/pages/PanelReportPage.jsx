import { useCallback, useEffect, useState } from '@wordpress/element';
import { Link, useParams } from 'react-router-dom';
import { get, getBlob, post } from '../../shared/api';
import {
	Button,
	ConfirmDialog,
	Notice,
	PageHeader,
	StatusChip,
} from '../../shared/components';
import { ReportsScoresTable } from '../../coordinator/components/ReportsScoresTable';
import { mapMarkApiError } from '../../shared/markErrors';

export function PanelReportPage() {
	const { sessionId, reviewId, panelId } = useParams();
	const session = parseInt( sessionId, 10 );
	const review = parseInt( reviewId, 10 );
	const panel = parseInt( panelId, 10 );

	const [ report, setReport ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ freezeOpen, setFreezeOpen ] = useState( false );
	const [ freezing, setFreezing ] = useState( false );
	const [ freezeError, setFreezeError ] = useState( null );
	const [ pdfError, setPdfError ] = useState( null );
	const [ downloading, setDownloading ] = useState( false );
	const [ unfreezeOpen, setUnfreezeOpen ] = useState( false );
	const [ unfreezeReason, setUnfreezeReason ] = useState( '' );
	const [ requestingUnfreeze, setRequestingUnfreeze ] = useState( false );
	const [ unfreezeError, setUnfreezeError ] = useState( null );

	const load = useCallback( async () => {
		if ( ! session || ! review || ! panel ) {
			return;
		}
		setLoading( true );
		setError( null );
		try {
			const data = await get(
				`/reviewer/panel-reports/${ session }/${ review }/${ panel }`
			);
			setReport( data );
		} catch ( err ) {
			setError( mapMarkApiError( err ) );
			setReport( null );
		} finally {
			setLoading( false );
		}
	}, [ session, review, panel ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const handleDownloadPdf = async () => {
		setDownloading( true );
		setPdfError( null );
		try {
			const response = await getBlob(
				`/reviewer/panel-reports/${ session }/${ review }/${ panel }/pdf`
			);
			const blob = await response.blob();
			const disposition = response.headers.get( 'Content-Disposition' ) || '';
			const match = disposition.match( /filename="?([^";]+)"?/ );
			const filename = match?.[ 1 ] || `panel-report-${ session }.pdf`;
			const url = URL.createObjectURL( blob );
			const anchor = document.createElement( 'a' );
			anchor.href = url;
			anchor.download = filename;
			anchor.click();
			URL.revokeObjectURL( url );
		} catch ( err ) {
			setPdfError( mapMarkApiError( err ) );
		} finally {
			setDownloading( false );
		}
	};

	const handleRequestPanelUnfreeze = async () => {
		const reason = unfreezeReason.trim();
		if ( ! reason ) {
			setUnfreezeError( {
				code: 'unfreeze_reason_required',
				message: 'Please explain why the panel should be unfrozen.',
				fixBy: null,
			} );
			return;
		}

		setRequestingUnfreeze( true );
		setUnfreezeError( null );
		try {
			await post(
				`/reviewer/panel-reports/${ session }/${ review }/${ panel }/unfreeze-request`,
				{ reason }
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
				`/reviewer/panel-reports/${ session }/${ review }/${ panel }/freeze`,
				{}
			);
			setFreezeOpen( false );
			await load();
		} catch ( err ) {
			setFreezeError( mapMarkApiError( err ) );
		} finally {
			setFreezing( false );
		}
	};

	if ( ! session || ! review || ! panel ) {
		return (
			<>
				<Notice variant="error">This panel report link is invalid.</Notice>
				<p className="mt-4">
					<Link to="/" className="text-sm font-medium text-primary underline">
						Back to assignments
					</Link>
				</p>
			</>
		);
	}

	const title = report
		? `${ report.session_title } — ${ report.review_label } — ${ report.panel_name }`
		: 'Panel report';

	return (
		<>
			<PageHeader
				title={ title }
				description="Overall scores for your panel. Download a signable PDF or freeze the panel when every reviewer has frozen their personal scores for this review."
				actions={
					<Link
						to="/"
						className="text-sm font-medium text-primary underline"
					>
						Back to assignments
					</Link>
				}
			/>

			{ error ? (
				<div className="mb-4 space-y-3">
					<Notice variant="error">{ error.message }</Notice>
					{ error.code === 'not_panel_coordinator' ? (
						<p>
							<Link
								to="/"
								className="text-sm font-medium text-primary underline"
							>
								Return to assignments
							</Link>
						</p>
					) : null }
				</div>
			) : null }

			{ pdfError ? (
				<div className="mb-4">
					<Notice variant="error">{ pdfError.message }</Notice>
				</div>
			) : null }

			{ report?.panel_frozen ? (
				<div className="mb-4 flex flex-wrap items-center gap-2">
					<StatusChip variant="confirmed" label="Panel frozen" icon="lock" />
					{ report.panel_unfreeze_request_status === 'pending' ? (
						<StatusChip
							variant="unlocked"
							label="Panel unfreeze requested"
						/>
					) : (
						<Button
							type="button"
							variant="secondary"
							icon="unlock"
							disabled={ loading || Boolean( error ) }
							onClick={ () => {
								setUnfreezeReason( '' );
								setUnfreezeError( null );
								setUnfreezeOpen( true );
							} }
						>
							Request panel unfreeze
						</Button>
					) }
				</div>
			) : null }

			{ ! report?.panel_report_settings_frozen && ! loading && ! error ? (
				<div className="mb-4">
					<Notice variant="warning">
						PDF download is available after the project coordinator freezes panel
						report settings.
					</Notice>
				</div>
			) : null }

			<div className="mb-6 flex flex-wrap gap-2">
				<Button
					variant="secondary"
					loading={ downloading }
					disabled={
						loading ||
						Boolean( error ) ||
						! report?.panel_report_settings_frozen
					}
					onClick={ handleDownloadPdf }
				>
					Download PDF
				</Button>
				{ ! report?.panel_frozen ? (
					<Button
						variant="primary"
						disabled={ loading || Boolean( error ) }
						onClick={ () => {
							setFreezeError( null );
							setFreezeOpen( true );
						} }
					>
						Freeze panel scores
					</Button>
				) : null }
			</div>

			<section>
				<h2 className="mb-3 text-sm font-semibold text-text-muted">
					Overall scores
				</h2>
				<ReportsScoresTable
					reviewers={ report?.reviewers }
					students={ report?.students }
					loading={ loading }
					showDraftTotals
					showStatusColumn
					showSrNo
					showProjectTitle
					showGuideName
					useOrdinalReviewerHeaders
					panelFrozen={ Boolean( report?.panel_frozen ) }
				/>
			</section>

			<ConfirmDialog
				open={ freezeOpen }
				title="Freeze panel scores?"
				confirmLabel={ freezing ? 'Freezing…' : 'Freeze panel' }
				confirmDisabled={ freezing }
				onConfirm={ handleFreeze }
				onCancel={ () => {
					setFreezeOpen( false );
					setFreezeError( null );
				} }
			>
				<ul className="list-disc space-y-1 pl-5 text-sm text-text-muted">
					<li>
						All reviewers on this panel will no longer be able to edit marks
						for this review round.
					</li>
					<li>
						Individual reviewer freeze and unfreeze requests will not apply
						after panel freeze.
					</li>
					<li>
						Request panel unfreeze from the project coordinator if the panel
						was frozen by mistake.
					</li>
				</ul>
				{ freezeError ? (
					<div className="mt-3">
						<Notice variant="error">{ freezeError.message }</Notice>
					</div>
				) : null }
			</ConfirmDialog>

			<ConfirmDialog
				open={ unfreezeOpen }
				title="Request panel unfreeze?"
				consequences={ [
					'The project coordinator must approve before the panel lock is removed.',
					'Reviewer marks stay submitted until each reviewer requests a personal unfreeze.',
					'The panel stays frozen until approval.',
				] }
				confirmLabel={
					requestingUnfreeze ? 'Requesting…' : 'Request panel unfreeze'
				}
				confirmDisabled={ requestingUnfreeze || ! unfreezeReason.trim() }
				onConfirm={ handleRequestPanelUnfreeze }
				onCancel={ () => {
					setUnfreezeOpen( false );
					setUnfreezeReason( '' );
					setUnfreezeError( null );
				} }
			>
				{ unfreezeError ? (
					<Notice variant="error" className="mb-4">
						<p>{ unfreezeError.message }</p>
					</Notice>
				) : null }
				<div>
					<label
						htmlFor="panel-unfreeze-reason"
						className="block text-sm font-medium text-text"
					>
						Why should this panel be unfrozen?
					</label>
					<textarea
						id="panel-unfreeze-reason"
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
						placeholder="e.g. Panel was frozen before a reviewer finished submitting."
						required
					/>
					<p className="mt-1 text-xs text-text-muted">
						{ unfreezeReason.length }/500 characters
					</p>
				</div>
			</ConfirmDialog>
		</>
	);
}
