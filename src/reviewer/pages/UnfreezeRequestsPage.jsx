import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { get, post } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import {
	Button,
	ConfirmDialog,
	EmptyState,
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

function StatusChipPill( { status } ) {
	if ( status === 'granted' ) {
		return (
			<span className="inline-flex items-center gap-1 rounded-md bg-success/10 px-2 py-0.5 text-xs font-medium text-success">
				<Icon name="save" className="h-3 w-3 shrink-0" />
				Approved
			</span>
		);
	}
	return (
		<span className="inline-flex items-center gap-1 rounded-md bg-[var(--pr-chip-unlocked-bg,#fff8c5)] px-2 py-0.5 text-xs font-medium text-[var(--pr-chip-unlocked-text,#9a6700)]">
			<Icon name="lock" className="h-3 w-3 shrink-0" />
			Pending
		</span>
	);
}

function ReviewerRequestsSection( { onRefreshBadge } ) {
	const [ requests, setRequests ] = useState( null );
	const [ grantTarget, setGrantTarget ] = useState( null );
	const [ granting, setGranting ] = useState( false );
	const [ grantError, setGrantError ] = useState( null );
	const removingRef = useRef( new Set() );

	const load = useCallback( async () => {
		try {
			const data = await get( '/reviewer/unfreeze-requests?status=pending' );
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
			await post( `/reviewer/unfreeze-requests/${ grantTarget.id }/grant` );
			removingRef.current.add( grantTarget.id );
			setGrantTarget( null );
			await load();
			onRefreshBadge?.();
			window.dispatchEvent( new Event( 'pr:unfreeze-changed' ) );
		} catch ( err ) {
			setGrantError( parseApiErrorMessage( err, 'Could not approve unfreeze.' ) );
		} finally {
			setGranting( false );
		}
	};

	const grantConsequences = grantTarget
		? [
				'Marks revert to draft for this reviewer on this panel.',
				'Combined scores drop until the reviewer freezes again.',
				'The reviewer can edit immediately after approval.',
				grantTarget.reason ? `Reviewer reason: "${ grantTarget.reason }"` : null,
		  ].filter( Boolean )
		: [];

	const isEmpty = Array.isArray( requests ) && requests.length === 0;

	return (
		<section aria-labelledby="reviewer-requests-heading" className="mb-8">
			<h2 id="reviewer-requests-heading" className="mb-1 text-base font-semibold text-text">
				Reviewer requests awaiting your approval
			</h2>
			<p className="mb-4 text-sm text-text-muted">
				These reviewers are asking you to let them edit their frozen marks.
			</p>

			{ grantError ? (
				<div className="mb-3" role="alert">
					<Notice variant="error">{ grantError }</Notice>
				</div>
			) : null }

			{ requests === null ? (
				<p className="text-sm text-text-muted" aria-live="polite">
					Loading…
				</p>
			) : isEmpty ? (
				<p className="rounded-md border border-border bg-surface-raised px-4 py-3 text-sm text-text-muted">
					No pending reviewer requests on your panels.
				</p>
			) : (
				<div className="overflow-x-auto rounded-md border border-border">
					<table className="w-full border-collapse text-sm">
						<caption className="sr-only">Pending reviewer unfreeze requests</caption>
						<thead className="bg-surface">
							<tr>
								<th className={ TABLE_TH }>Session</th>
								<th className={ TABLE_TH }>Review</th>
								<th className={ TABLE_TH }>Panel</th>
								<th className={ TABLE_TH }>Reviewer &amp; reason</th>
								<th className={ TABLE_TH }>
									<span className="sr-only">Actions</span>
								</th>
							</tr>
						</thead>
						<tbody>
							{ requests.map( ( req ) => (
								<tr
									key={ req.id }
									className="transition-opacity last:[&>td]:border-b-0"
								>
									<td className={ TABLE_TD }>{ req.session_title }</td>
									<td className={ TABLE_TD }>{ req.review_label }</td>
									<td className={ TABLE_TD }>{ req.panel_name }</td>
									<td className={ TABLE_TD }>
										<p className="font-medium">{ req.reviewer_name || `User #${ req.reviewer_user_id }` }</p>
										{ req.reason ? (
											<p className="mt-0.5 text-text-muted">
												&ldquo;{ req.reason }&rdquo;
											</p>
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
											aria-label={ `Approve unfreeze for ${ req.reviewer_name || 'reviewer' }` }
											onClick={ () => {
												setGrantError( null );
												setGrantTarget( req );
											} }
										>
											Approve
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }

			<ConfirmDialog
				open={ Boolean( grantTarget ) }
				title="Approve unfreeze?"
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

function PanelRequestsSection() {
	const [ requests, setRequests ] = useState( null );

	const load = useCallback( () => {
		get( '/reviewer/panel-unfreeze-requests/mine' )
			.then( ( data ) =>
				setRequests( Array.isArray( data?.requests ) ? data.requests : [] )
			)
			.catch( () => setRequests( [] ) );
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

	const isEmpty = Array.isArray( requests ) && requests.length === 0;

	return (
		<section aria-labelledby="panel-requests-heading">
			<h2 id="panel-requests-heading" className="mb-1 text-base font-semibold text-text">
				Your panel unfreeze requests
			</h2>
			<p className="mb-4 text-sm text-text-muted">
				These are requests you submitted to the coordinator to unfreeze a full panel report.
			</p>

			{ requests === null ? (
				<p className="text-sm text-text-muted" aria-live="polite">
					Loading…
				</p>
			) : isEmpty ? (
				<p className="rounded-md border border-border bg-surface-raised px-4 py-3 text-sm text-text-muted">
					You have not submitted any panel unfreeze requests.
				</p>
			) : (
				<div className="overflow-x-auto rounded-md border border-border">
					<table className="w-full border-collapse text-sm">
						<caption className="sr-only">Your submitted panel unfreeze requests</caption>
						<thead className="bg-surface">
							<tr>
								<th className={ TABLE_TH }>Session</th>
								<th className={ TABLE_TH }>Review</th>
								<th className={ TABLE_TH }>Panel</th>
								<th className={ TABLE_TH }>Reason</th>
								<th className={ TABLE_TH }>Status</th>
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
									<td className={ TABLE_TD }>
										<StatusChipPill status={ req.status } />
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }
		</section>
	);
}

export function UnfreezeRequestsPage() {
	const isPanelHead = Boolean( window.prAppData?.isPanelHead );
	const [ badgeTick, setBadgeTick ] = useState( 0 );
	const [ flowOpen, setFlowOpen ] = useState( false );

	const howItWorksBtn = (
		<button
			type="button"
			onClick={ () => setFlowOpen( true ) }
			className="inline-flex items-center gap-1.5 rounded-md border border-border bg-surface-raised px-3 py-1.5 text-sm font-medium text-text-muted hover:bg-surface hover:text-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
		>
			<Icon name="unlock" className="h-4 w-4 shrink-0" />
			How it works
		</button>
	);

	if ( ! isPanelHead ) {
		return (
			<>
				<PageHeader title="Unfreeze Requests" actions={ howItWorksBtn } />
				<Notice variant="info">
					This page is only available to panel coordinators (HODs).
				</Notice>
				<UnfreezeFlowModal open={ flowOpen } onClose={ () => setFlowOpen( false ) } />
			</>
		);
	}

	return (
		<>
			<PageHeader
				title="Unfreeze Requests"
				description="Manage incoming reviewer requests and track your panel requests."
				actions={ howItWorksBtn }
			/>
			<ReviewerRequestsSection
				key={ badgeTick }
				onRefreshBadge={ () => setBadgeTick( ( n ) => n + 1 ) }
			/>
			<div className="border-t border-border pt-8">
				<PanelRequestsSection />
			</div>
			<UnfreezeFlowModal open={ flowOpen } onClose={ () => setFlowOpen( false ) } />
		</>
	);
}
