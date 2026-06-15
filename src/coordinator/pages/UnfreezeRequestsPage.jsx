import { useCallback, useEffect, useState } from '@wordpress/element';
import { get, post } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import {
	Button,
	ConfirmDialog,
	Notice,
	PageHeader,
} from '../../shared/components';
import { UnfreezeFlowModal } from '../../shared/components/UnfreezeFlowModal';
import { Icon } from '../../shared/components/NavIcon';

function formatDate( value ) {
	if ( ! value ) {
		return '';
	}
	const d = new Date( value );
	if ( isNaN( d.getTime() ) ) {
		return value;
	}
	return d.toLocaleString( undefined, {
		day: 'numeric',
		month: 'short',
		year: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	} );
}

const TABLE_TH =
	'px-3 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-text-muted border-b border-border';
const TABLE_TD = 'px-3 py-3 text-sm text-text align-top border-b border-border';

function RequestsTable( { requests, onApprove } ) {
	if ( ! requests || requests.length === 0 ) {
		return null;
	}

	return (
		<div className="overflow-x-auto rounded-md border border-border">
			<table className="w-full border-collapse text-sm">
				<thead className="bg-surface">
					<tr>
						<th className={ TABLE_TH }>Session</th>
						<th className={ TABLE_TH }>Review</th>
						<th className={ TABLE_TH }>Panel</th>
						<th className={ TABLE_TH }>Reason</th>
						<th className={ TABLE_TH }>
							<span className="sr-only">Actions</span>
						</th>
					</tr>
				</thead>
				<tbody>
					{ requests.map( ( req ) => (
						<tr key={ req.id } className="last:[&>td]:border-b-0">
							<td className={ TABLE_TD }>{ req.session_title }</td>
							<td className={ TABLE_TD }>{ req.review_label }</td>
							<td className={ TABLE_TD }>{ req.panel_name }</td>
							<td className={ TABLE_TD }>
								{ req.reason ? (
									<p>&ldquo;{ req.reason }&rdquo;</p>
								) : null }
								<p className="mt-0.5 text-xs text-text-muted">
									{ formatDate( req.requested_at ) }
								</p>
							</td>
							<td className={ TABLE_TD + ' text-right' }>
								<Button
									type="button"
									variant="primary"
									size="sm"
									aria-label={ `Approve unfreeze for ${ req.session_title } — ${ req.panel_name }` }
									onClick={ () => onApprove( req ) }
								>
									Approve
								</Button>
							</td>
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}

function PanelRequestsSection( { onApprovalDone } ) {
	const [ requests, setRequests ] = useState( null );
	const [ grantTarget, setGrantTarget ] = useState( null );
	const [ granting, setGranting ] = useState( false );
	const [ grantError, setGrantError ] = useState( null );

	const load = useCallback( async () => {
		try {
			const data = await get( '/panel-unfreeze-requests?status=pending' );
			setRequests( Array.isArray( data?.requests ) ? data.requests : [] );
		} catch {
			setRequests( [] );
		}
	}, [] );

	useEffect( () => {
		load();
		const tick = () => {
			if ( document.visibilityState === 'visible' ) {
				load();
			}
		};
		const id = setInterval( tick, 30_000 );
		document.addEventListener( 'visibilitychange', tick );
		window.addEventListener( 'pr:unfreeze-changed', load );
		return () => {
			clearInterval( id );
			document.removeEventListener( 'visibilitychange', tick );
			window.removeEventListener( 'pr:unfreeze-changed', load );
		};
	}, [ load ] );

	const handleGrant = async () => {
		if ( ! grantTarget?.id ) {
			return;
		}
		setGranting( true );
		setGrantError( null );
		try {
			await post( `/panel-unfreeze-requests/${ grantTarget.id }/grant` );
			setGrantTarget( null );
			await load();
			onApprovalDone?.();
			window.dispatchEvent( new Event( 'pr:unfreeze-changed' ) );
		} catch ( err ) {
			setGrantError( parseApiErrorMessage( err, 'Could not approve panel unfreeze.' ) );
		} finally {
			setGranting( false );
		}
	};

	const grantConsequences = grantTarget
		? [
				'Marks revert to draft for all reviewers on this panel.',
				'Combined scores drop until the panel is re-frozen.',
				'The Panel Coordinator can edit immediately after approval.',
				grantTarget.reason ? `Panel Coordinator reason: "${ grantTarget.reason }"` : null,
		  ].filter( Boolean )
		: [];

	const isEmpty = Array.isArray( requests ) && requests.length === 0;

	return (
		<section aria-labelledby="panel-requests-heading">
			<h2 id="panel-requests-heading" className="mb-1 text-base font-semibold text-text">
				Panel Unfreeze Requests
			</h2>
			<p className="mb-4 text-sm text-text-muted">
				Panel Coordinators requesting to unfreeze a full panel report.
			</p>

			{ grantError ? (
				<div className="mb-3" role="alert">
					<Notice variant="error">{ grantError }</Notice>
				</div>
			) : null }

			{ requests === null ? (
				<p className="text-sm text-text-muted" aria-live="polite">Loading…</p>
			) : isEmpty ? (
				<p className="rounded-md border border-border bg-surface-raised px-4 py-3 text-sm text-text-muted">
					No pending panel unfreeze requests.
				</p>
			) : (
				<RequestsTable
					requests={ requests }
					onApprove={ ( req ) => {
						setGrantError( null );
						setGrantTarget( req );
					} }
				/>
			) }

			<ConfirmDialog
				open={ Boolean( grantTarget ) }
				title="Approve panel unfreeze?"
				consequences={ grantConsequences }
				confirmLabel={ granting ? 'Approving…' : 'Approve' }
				confirmDisabled={ granting }
				onConfirm={ handleGrant }
				onCancel={ () => {
					setGrantTarget( null );
					setGrantError( null );
				} }
			/>
		</section>
	);
}

export function UnfreezeRequestsPage() {
	const [ tick, setTick ] = useState( 0 );
	const [ flowOpen, setFlowOpen ] = useState( false );

	return (
		<>
			<PageHeader
				title="Unfreeze Requests"
				description="Approve pending panel unfreeze requests from Panel Coordinators."
				actions={
					<button
						type="button"
						onClick={ () => setFlowOpen( true ) }
						className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-raised px-3 py-1.5 text-sm font-medium text-text-muted hover:bg-surface hover:text-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
					>
						<Icon name="unlock" className="h-4 w-4 shrink-0" />
						How it works
					</button>
				}
			/>

			<PanelRequestsSection
				key={ `panel-${ tick }` }
				onApprovalDone={ () => setTick( ( n ) => n + 1 ) }
			/>

			<UnfreezeFlowModal
				open={ flowOpen }
				onClose={ () => setFlowOpen( false ) }
			/>
		</>
	);
}
