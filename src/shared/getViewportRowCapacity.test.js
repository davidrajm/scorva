import { getViewportRowCapacity } from './getViewportRowCapacity';

describe( 'getViewportRowCapacity', () => {
	const rowHeightPx = 48;

	it( 'returns 0 when there are no rows', () => {
		expect(
			getViewportRowCapacity( {
				availablePx: 900,
				rowHeightPx,
				totalRows: 0,
			} )
		).toBe( 0 );
	} );

	it( 'shows all rows when total is at or below the minimum default (10)', () => {
		expect(
			getViewportRowCapacity( {
				availablePx: 400,
				rowHeightPx,
				totalRows: 8,
			} )
		).toBe( 8 );
	} );

	it( 'defaults to at least 10 rows when space fits 10 but not many more', () => {
		expect(
			getViewportRowCapacity( {
				availablePx: 560,
				rowHeightPx,
				totalRows: 50,
			} )
		).toBe( 10 );
	} );

	it( 'expands toward viewport fit up to the max cap (20)', () => {
		expect(
			getViewportRowCapacity( {
				availablePx: 1400,
				rowHeightPx,
				totalRows: 50,
				maxRows: 20,
			} )
		).toBe( 20 );
	} );

	it( 'uses a short-viewport floor of 3 rows when fewer than 10 fit', () => {
		expect(
			getViewportRowCapacity( {
				availablePx: 220,
				rowHeightPx,
				totalRows: 25,
			} )
		).toBe( 3 );
	} );

	it( 'accounts for double header height', () => {
		const single = getViewportRowCapacity( {
			availablePx: 600,
			headerRows: 1,
			rowHeightPx,
			totalRows: 30,
		} );
		const doubleHeader = getViewportRowCapacity( {
			availablePx: 600,
			headerRows: 2,
			rowHeightPx,
			totalRows: 30,
		} );
		expect( doubleHeader ).toBeLessThanOrEqual( single );
	} );
} );
