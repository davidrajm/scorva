import {
	TABLE_VIEWPORT_INITIAL_ROWS,
	TABLE_VIEWPORT_ROW_INCREMENT,
	getTableRowWindowMetrics,
} from './useTableRowWindow';

describe( 'getTableRowWindowMetrics', () => {
	it( 'uses a ten-row height budget when more than ten rows exist', () => {
		const metrics = getTableRowWindowMetrics( {
			totalRows: 20,
			visibleRows: TABLE_VIEWPORT_INITIAL_ROWS,
			showAll: false,
		} );

		expect( metrics.heightRows ).toBe( TABLE_VIEWPORT_INITIAL_ROWS );
		expect( metrics.showControls ).toBe( true );
		expect( metrics.canAddMore ).toBe( true );
		expect( metrics.canShowAll ).toBe( true );
		expect( metrics.canShowFewer ).toBe( false );
	} );

	it( 'shrinks to row count when ten or fewer rows', () => {
		const metrics = getTableRowWindowMetrics( {
			totalRows: 8,
			visibleRows: TABLE_VIEWPORT_INITIAL_ROWS,
			showAll: false,
		} );

		expect( metrics.heightRows ).toBe( 8 );
		expect( metrics.showControls ).toBe( false );
	} );

	it( 'addFive state grows the visible window by five', () => {
		const metrics = getTableRowWindowMetrics( {
			totalRows: 20,
			visibleRows:
				TABLE_VIEWPORT_INITIAL_ROWS + TABLE_VIEWPORT_ROW_INCREMENT,
			showAll: false,
		} );

		expect( metrics.heightRows ).toBe(
			TABLE_VIEWPORT_INITIAL_ROWS + TABLE_VIEWPORT_ROW_INCREMENT
		);
	} );

	it( 'showAll expands to full height and enables show fewer', () => {
		const metrics = getTableRowWindowMetrics( {
			totalRows: 12,
			visibleRows: TABLE_VIEWPORT_INITIAL_ROWS,
			showAll: true,
		} );

		expect( metrics.heightRows ).toBe( 12 );
		expect( metrics.canShowFewer ).toBe( true );
		expect( metrics.canAddMore ).toBe( false );
	} );

	it( 'enables remove rows when expanded past initial', () => {
		const metrics = getTableRowWindowMetrics( {
			totalRows: 25,
			visibleRows: 15,
			showAll: false,
			initialRows: 10,
			rowIncrement: 5,
		} );

		expect( metrics.canRemoveRows ).toBe( true );
		expect( metrics.heightRows ).toBe( 15 );
		expect( metrics.showControls ).toBe( true );
	} );
} );
