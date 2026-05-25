import { useCallback, useEffect, useState } from '@wordpress/element';
import { get, post } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import { Button, ConfirmDialog, Notice } from '../../shared/components';

function formatRequestedAt( value ) {
	if ( ! value ) {
		return '';
	}
	const date = new Date( value );
	if ( Number.isNaN( date.getTime() ) ) {
		return value;
	}
	return date.toLocaleString();
}

export function PanelUnfreezeRequests( { className = '' } ) {
	const [ requests, setRequests ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ grantTarget, setGrantTarget ] = useState( null );
	const [ granting, setGranting ] = useState( false );
	const [ grantError, setGrantError ] = useState( null );
	const [ grantSuccess, setGrantSuccess ] = useState( null );

	const load = useCallback( async () => {
		setLoading( true );
		try {
			const data = await get( '/panel-unfreeze-requests?status=pending' );
			setRequests( Array.isArray( data?.requests ) ? data.requests : [] );
		} catch {
			setRequests( [] );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const handleGrant = async () => {
		if ( ! grantTarget?.id ) {
			return;
		}
		setGranting( true );
		setGrantError( null );
		try {
			await post( `/panel-unfreeze-requests/${ grantTarget.id }/grant` );
			setGrantSuccess(
				`Panel unfreeze approved for ${ grantTarget.panel_name || 'panel' }.`
			);
			setGrantTarget( null );
			await load();
		} catch ( err ) {
			setGrantError(
				parseApiErrorMessage( err, 'Could not approve panel unfreeze.' )
			);
		} finally {
			setGranting( false );
		}
	};

	const grantConsequences = grantTarget
		? [
				'Only the panel lock is removed — reviewer marks stay submitted.',
				'Reviewers can save and freeze personal scores again.',
				'The panel coordinator can freeze the panel again when ready.',
				grantTarget.reason
					? `Panel coordinator reason: “${ grantTarget.reason }”`
					: null,
		  ].filter( Boolean )
		: [];

	if ( ! loading && requests.length === 0 ) {
		return null;
	}

	return (
		<section
			className={ [
				'rounded-md border border-border bg-surface-raised p-4',
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
			aria-labelledby="panel-unfreeze-requests-heading"
		>
			<h2
				id="panel-unfreeze-requests-heading"
				className="text-lg font-semibold text-text"
			>
				Panel unfreeze requests
				{ ! loading && requests.length > 0 ? (
					<span className="ml-2 text-sm font-normal text-text-muted">
						({ requests.length } pending)
					</span>
				) : null }
			</h2>

			{ grantSuccess ? (
				<div className="mt-4">
					<Notice variant="success">{ grantSuccess }</Notice>
				</div>
			) : null }

			{ grantError ? (
				<div className="mt-4">
					<Notice variant="error">{ grantError }</Notice>
				</div>
			) : null }

			{ loading ? (
				<p className="mt-4 text-sm text-text-muted" aria-live="polite">
					Loading panel unfreeze requests…
				</p>
			) : (
				<ul className="mt-4 divide-y divide-border">
					{ requests.map( ( request ) => (
						<li
							key={ request.id }
							className="flex flex-col gap-3 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between"
						>
							<div className="min-w-0 flex-1 text-sm text-text">
								<p className="font-medium">
									{ request.session_title } · { request.review_label }
								</p>
								<p className="mt-1 text-text-muted">
									{ request.panel_name } ·{' '}
									{ request.panel_coordinator_name ||
										`User #${ request.requested_by_user_id }` }
								</p>
								{ request.reason ? (
									<p className="mt-2 text-text">
										<span className="font-medium">Reason: </span>
										{ request.reason }
									</p>
								) : null }
								<p className="mt-1 text-xs text-text-muted">
									Requested { formatRequestedAt( request.requested_at ) }
								</p>
							</div>
							<Button
								type="button"
								variant="primary"
								className="shrink-0"
								onClick={ () => {
									setGrantError( null );
									setGrantSuccess( null );
									setGrantTarget( request );
								} }
							>
								Approve panel unfreeze
							</Button>
						</li>
					) ) }
				</ul>
			) }

			<ConfirmDialog
				open={ Boolean( grantTarget ) }
				title="Approve panel unfreeze?"
				consequences={ grantConsequences }
				confirmLabel={ granting ? 'Approving…' : 'Approve panel unfreeze' }
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
