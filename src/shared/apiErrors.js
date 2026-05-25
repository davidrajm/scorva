/**
 * Extract a user-facing message from a @wordpress/api-fetch error.
 */
export function parseApiErrorMessage( error, fallback ) {
	if ( ! error ) {
		return fallback;
	}

	if ( typeof error.message === 'string' && error.message !== '' ) {
		return error.message;
	}

	const data = error.data;
	if ( data && typeof data.message === 'string' && data.message !== '' ) {
		return data.message;
	}

	return fallback;
}
