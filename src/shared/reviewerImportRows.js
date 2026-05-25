function normalizeKey( key ) {
	return String( key )
		.toLowerCase()
		.replace( /\s+/g, '_' );
}

function rowValueForSuffix( row, suffix ) {
	const target = normalizeKey( suffix );
	for ( const [ key, value ] of Object.entries( row ) ) {
		if ( normalizeKey( key ) === target ) {
			return String( value ?? '' ).trim();
		}
	}
	return '';
}

function rowHasWideReviewerSlots( row ) {
	for ( const [ key, value ] of Object.entries( row ) ) {
		const normalized = normalizeKey( key );
		const match = normalized.match( /^reviewer_(\d+)$/ );
		if ( ! match ) {
			continue;
		}
		const slot = match[ 1 ];
		const name = String( value ?? '' ).trim();
		const email = rowValueForSuffix( row, `reviewer_${ slot }_email` );
		if ( name !== '' || email !== '' ) {
			return true;
		}
	}
	return false;
}

function expandWideRow( row, panelRef, csvRow ) {
	const slots = {};
	for ( const [ key, value ] of Object.entries( row ) ) {
		const normalized = normalizeKey( key );
		const match = normalized.match( /^reviewer_(\d+)$/ );
		if ( ! match ) {
			continue;
		}
		const slot = Number( match[ 1 ] );
		const name = String( value ?? '' ).trim();
		const email = rowValueForSuffix( row, `reviewer_${ slot }_email` );
		if ( name === '' && email === '' ) {
			continue;
		}
		const weightRaw = rowValueForSuffix( row, `reviewer_${ slot }_weight` );
		slots[ slot ] = {
			panel: panelRef,
			reviewer_name: name,
			email: email.toLowerCase(),
			weight: weightRaw !== '' ? weightRaw : 1,
			_csv_row: csvRow,
		};
	}
	return Object.keys( slots )
		.map( Number )
		.sort( ( a, b ) => a - b )
		.map( ( slot ) => slots[ slot ] );
}

/** @param {Record<string, unknown>} row @param {number} sourceIndex */
function csvRowForSource( row, sourceIndex ) {
	const fromRow = Number( row._csv_row );
	return fromRow > 0 ? fromRow : sourceIndex + 2;
}

/**
 * Mirror PanelRepository::expand_import_rows for client-side validation.
 *
 * @param {Array<Record<string, string>>} rows
 * @returns {Array<Record<string, string|number>>}
 */
export function expandReviewerImportRows( rows ) {
	const expanded = [];
	for ( let sourceIndex = 0; sourceIndex < rows.length; sourceIndex++ ) {
		const row = rows[ sourceIndex ];
		const csvRow = csvRowForSource( row, sourceIndex );
		const panelRef = String( row.panel ?? row.panel_number ?? '' ).trim();
		if ( rowHasWideReviewerSlots( row ) ) {
			expanded.push( ...expandWideRow( row, panelRef, csvRow ) );
			continue;
		}
		const longName = String(
			row.reviewer_name ?? row.name ?? ''
		).trim();
		const longEmail = String( row.email ?? '' )
			.trim()
			.toLowerCase();
		if ( longName !== '' || longEmail !== '' ) {
			expanded.push( {
				panel: panelRef,
				reviewer_name: longName,
				email: longEmail,
				weight: row.weight ?? 1,
				_csv_row: csvRow,
			} );
			continue;
		}
		if ( panelRef !== '' ) {
			expanded.push( { ...row, _csv_row: csvRow } );
		}
	}
	return expanded;
}

/**
 * Find reviewers (by email) listed on more than one panel in the same file.
 *
 * @param {Array<Record<string, string>>} rows Mapped import rows (long or wide format).
 * @returns {Array<{ email: string, name: string, panels: string[], rows: number[] }>}
 */
export function findReviewerEmailPanelConflicts( rows ) {
	const expanded = expandReviewerImportRows( rows );
	const assignments = new Map();
	const conflicts = new Map();

	expanded.forEach( ( row ) => {
		const email = String( row.email ?? '' )
			.trim()
			.toLowerCase();
		if ( email === '' ) {
			return;
		}
		const panel = String( row.panel ?? row.panel_number ?? '' ).trim();
		if ( panel === '' ) {
			return;
		}
		const name = String( row.reviewer_name ?? row.name ?? '' ).trim();
		const line = Number( row._csv_row );
		if ( ! Number.isFinite( line ) || line < 1 ) {
			return;
		}

		if ( ! assignments.has( email ) ) {
			assignments.set( email, { panel, name, line } );
			return;
		}

		const existing = assignments.get( email );
		if ( existing.panel === panel ) {
			return;
		}

		if ( ! conflicts.has( email ) ) {
			conflicts.set( email, {
				email,
				name: existing.name || name,
				panels: [ existing.panel, panel ],
				rows: [ existing.line, line ],
			} );
			return;
		}

		const conflict = conflicts.get( email );
		if ( ! conflict.panels.includes( panel ) ) {
			conflict.panels.push( panel );
		}
		if ( ! conflict.rows.includes( line ) ) {
			conflict.rows.push( line );
		}
		if ( ! conflict.name && name ) {
			conflict.name = name;
		}
	} );

	return [ ...conflicts.values() ].map( ( conflict ) => ( {
		...conflict,
		rows: [ ...conflict.rows ].sort( ( a, b ) => a - b ),
	} ) );
}
