function escapeCsvCell( value ) {
	const text = String( value ?? '' );
	if ( text.includes( ',' ) || text.includes( '"' ) || text.includes( '\n' ) ) {
		return `"${ text.replace( /"/g, '""' ) }"`;
	}
	return text;
}

function reviewersForPanel( reviewers, panelId ) {
	return reviewers
		.filter( ( row ) => Number( row.panel_id ) === Number( panelId ) )
		.sort( ( a, b ) =>
			String( a.name || a.email ).localeCompare(
				String( b.name || b.email ),
				undefined,
				{ numeric: true }
			)
		);
}

/**
 * Build a wide-format CSV: one row per panel, reviewer_N columns prefilled when present.
 *
 * @param {Array<{ id: number, name: string }>} panels
 * @param {Array<{ panel_id: number, name?: string, email?: string, weight?: number }>} reviewers
 * @param {{ minSlots?: number }} options
 */
export function buildReviewersTemplateCsv( panels, reviewers, options = {} ) {
	const sortedPanels = [ ...panels ].sort( ( a, b ) =>
		String( a.name ).localeCompare( String( b.name ), undefined, {
			numeric: true,
		} )
	);

	const maxOnPanel = sortedPanels.reduce( ( max, panel ) => {
		const count = reviewersForPanel( reviewers, panel.id ).length;
		return Math.max( max, count );
	}, 0 );

	const slotCount = Math.max( options.minSlots ?? 3, maxOnPanel, 1 );
	const headers = [ 'panel' ];
	for ( let slot = 1; slot <= slotCount; slot += 1 ) {
		headers.push(
			`reviewer_${ slot }`,
			`reviewer_${ slot }_email`,
			`reviewer_${ slot }_weight`
		);
	}

	const lines = [ headers.map( escapeCsvCell ).join( ',' ) ];

	sortedPanels.forEach( ( panel ) => {
		const panelReviewers = reviewersForPanel( reviewers, panel.id );
		const cells = [ escapeCsvCell( panel.name ) ];

		for ( let slot = 1; slot <= slotCount; slot += 1 ) {
			const reviewer = panelReviewers[ slot - 1 ];
			cells.push(
				escapeCsvCell( reviewer?.name ?? '' ),
				escapeCsvCell( reviewer?.email ?? '' ),
				escapeCsvCell(
					reviewer?.weight != null && reviewer.weight !== ''
						? reviewer.weight
						: ''
				)
			);
		}

		lines.push( cells.join( ',' ) );
	} );

	return `${ lines.join( '\n' ) }\n`;
}

export function downloadCsvText( csvText, filename ) {
	const blob = new Blob( [ csvText ], { type: 'text/csv;charset=utf-8' } );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	link.click();
	URL.revokeObjectURL( url );
}
