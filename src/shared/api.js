/**
 * Shared REST client — uses wp-api-fetch default root (wp-json/) + wp_rest nonce.
 * Paths are prefixed with scorva/v1 (do not add a second root URL middleware;
 * WordPress already registers one and it would overwrite our namespace).
 */
import apiFetch from '@wordpress/api-fetch';

const API_NAMESPACE = '/scorva/v1';

function resolvePath( path ) {
	const segment = path.startsWith( '/' ) ? path : `/${ path }`;

	return `${ API_NAMESPACE }${ segment }`;
}

export function configureApi() {
	const nonce = window.prAppData?.nonce;
	if ( nonce && typeof apiFetch.createNonceMiddleware === 'function' ) {
		apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
	}
}

export function get( path ) {
	return apiFetch( { path: resolvePath( path ) } );
}

export function post( path, data ) {
	return apiFetch( {
		path: resolvePath( path ),
		method: 'POST',
		data,
	} );
}

export function postMarkOverride( markId, body ) {
	return post( `/marks/${ markId }/override`, body );
}

export function put( path, data ) {
	return apiFetch( {
		path: resolvePath( path ),
		method: 'PUT',
		data,
	} );
}

export function del( path, data ) {
	const opts = {
		path: resolvePath( path ),
		method: 'DELETE',
	};
	if ( data !== undefined ) {
		opts.data = data;
	}
	return apiFetch( opts );
}

/**
 * Fetch a binary response (e.g. PDF export) with REST nonce auth.
 */
export async function getBlob( path ) {
	const root = window.prAppData?.root || '/wp-json';
	const url = `${ root.replace( /\/$/, '' ) }${ resolvePath( path ) }`;
	const headers = {};
	const nonce = window.prAppData?.nonce;
	if ( nonce ) {
		headers[ 'X-WP-Nonce' ] = nonce;
	}

	const response = await fetch( url, {
		credentials: 'same-origin',
		headers,
	} );

	if ( ! response.ok ) {
		let payload = {};
		try {
			payload = await response.json();
		} catch {
			payload = {};
		}
		const error = new Error(
			payload?.message || 'Request failed.'
		);
		error.code = payload?.code;
		error.data = payload?.data;
		throw error;
	}

	return response;
}
