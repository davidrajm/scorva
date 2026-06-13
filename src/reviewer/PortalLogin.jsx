import { useEffect, useState } from '@wordpress/element';
import { get, post } from '../shared/api';
import { getAppDisplayName } from '../shared/appBranding';
import { Button, Notice } from '../shared/components';

function PortalCard( { children } ) {
	return (
		<div className="flex min-h-screen items-center justify-center bg-surface px-4">
			<div className="w-full max-w-md rounded-lg border border-border bg-surface-raised p-8 shadow-card">
				<p className="text-lg font-semibold text-primary">
					{ getAppDisplayName() }
				</p>
				{ children }
			</div>
		</div>
	);
}

function InvalidLink() {
	return (
		<PortalCard>
			<h1 className="mt-4 text-xl font-semibold text-text">
				This review link is not valid
			</h1>
			<p className="mt-2 text-sm text-text-muted">
				The link may be incomplete or may have been replaced. Open the
				most recent invitation email and use the review link from
				there, or contact the coordinator for a new invitation.
			</p>
		</PortalCard>
	);
}

export function PortalLogin( { token, onSuccess } ) {
	const [ tokenState, setTokenState ] = useState(
		token ? 'checking' : 'missing'
	);
	const [ password, setPassword ] = useState( '' );
	const [ submitting, setSubmitting ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		if ( ! token ) {
			return;
		}
		get( `/portal/token-status?token=${ encodeURIComponent( token ) }` )
			.then( ( data ) =>
				setTokenState( data?.valid ? 'ok' : 'invalid' )
			)
			// Network hiccup: let the auth call surface the real error.
			.catch( () => setTokenState( 'ok' ) );
	}, [ token ] );

	if ( tokenState === 'missing' || tokenState === 'invalid' ) {
		return <InvalidLink />;
	}

	const handleSubmit = async ( event ) => {
		event.preventDefault();
		if ( ! password || submitting ) {
			return;
		}

		setSubmitting( true );
		setError( null );
		try {
			const context = await post( '/portal/auth', {
				token,
				password,
			} );
			onSuccess( context );
		} catch ( err ) {
			if ( err?.code === 'pr_portal_invalid_token' ) {
				setTokenState( 'invalid' );
			} else {
				setError(
					err?.message ||
						'Could not sign in. Check your password and try again.'
				);
			}
		} finally {
			setSubmitting( false );
		}
	};

	return (
		<PortalCard>
			<h1 className="mt-4 text-xl font-semibold text-text">
				Reviewer access
			</h1>
			<p className="mt-2 text-sm text-text-muted">
				Enter the password from your invitation email to start
				reviewing. If you received more than one email, use the most
				recent password.
			</p>
			<form className="mt-6" onSubmit={ handleSubmit }>
				<label
					className="block text-sm font-medium text-text"
					htmlFor="pr-portal-password"
				>
					Password
				</label>
				<input
					id="pr-portal-password"
					type="password"
					autoComplete="current-password"
					autoFocus
					value={ password }
					onChange={ ( e ) => setPassword( e.target.value ) }
					disabled={ tokenState === 'checking' || submitting }
					className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
				/>
				{ error ? (
					<div className="mt-3">
						<Notice variant="error" onDismiss={ () => setError( null ) }>
							{ error }
						</Notice>
					</div>
				) : null }
				<Button
					type="submit"
					variant="primary"
					loading={ submitting }
					disabled={ tokenState === 'checking' || ! password }
					className="mt-4 w-full"
				>
					Open review portal
				</Button>
			</form>
		</PortalCard>
	);
}
