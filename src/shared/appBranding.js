const DEFAULT_APP_DISPLAY_NAME = 'Scorva: The Review Management System';

export function getAppDisplayName() {
	const name = window.prAppData?.appDisplayName?.trim();
	return name || DEFAULT_APP_DISPLAY_NAME;
}

export function getAppShortName() {
	const short = window.prAppData?.appShortName?.trim();
	if ( short ) {
		return short;
	}
	const full = getAppDisplayName();
	const idx = full.indexOf( ':' );
	return idx > 0 ? full.slice( 0, idx ).trim() : full;
}
