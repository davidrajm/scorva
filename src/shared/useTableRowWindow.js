import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import {
	TABLE_VIEWPORT_INITIAL_ROWS,
	TABLE_VIEWPORT_ROW_INCREMENT,
} from './tableStyles';

export { TABLE_VIEWPORT_INITIAL_ROWS, TABLE_VIEWPORT_ROW_INCREMENT };

/**
 * Pure row-window metrics (testable without React).
 *
 * @param {{
 *   totalRows: number,
 *   visibleRows: number,
 *   showAll: boolean,
 *   initialRows?: number,
 *   rowIncrement?: number
 * }} state
 */
export function getTableRowWindowMetrics( {
	totalRows,
	visibleRows,
	showAll,
	initialRows = TABLE_VIEWPORT_INITIAL_ROWS,
	rowIncrement = TABLE_VIEWPORT_ROW_INCREMENT,
} ) {
	const cappedVisibleRows =
		showAll || totalRows <= initialRows
			? totalRows
			: Math.min( visibleRows, totalRows );

	return {
		totalRows,
		heightRows: cappedVisibleRows,
		cappedVisibleRows,
		showAll,
		initialRows,
		rowIncrement,
		showControls: totalRows > initialRows,
		canAddMore:
			! showAll &&
			totalRows > initialRows &&
			visibleRows < totalRows,
		canRemoveRows:
			! showAll && visibleRows > initialRows,
		canShowAll: ! showAll && totalRows > initialRows,
		canShowFewer: showAll,
	};
}

/**
 * Progressive row window for TableDataViewport (height budget, not DOM slicing).
 *
 * @param {number} bodyRowCount Total <tbody> (or grid body) rows in the table.
 * @param {{ initialRows?: number, rowIncrement?: number }} [options]
 */
export function useTableRowWindow( bodyRowCount, options = {} ) {
	const initialRows = options.initialRows ?? TABLE_VIEWPORT_INITIAL_ROWS;
	const rowIncrement = options.rowIncrement ?? TABLE_VIEWPORT_ROW_INCREMENT;
	const totalRows = Math.max( 0, Number( bodyRowCount ) || 0 );
	const [ visibleRows, setVisibleRows ] = useState( initialRows );
	const [ showAll, setShowAll ] = useState( false );

	useEffect( () => {
		setVisibleRows( initialRows );
		setShowAll( false );
	}, [ totalRows, initialRows ] );

	const metrics = useMemo(
		() =>
			getTableRowWindowMetrics( {
				totalRows,
				visibleRows,
				showAll,
				initialRows,
				rowIncrement,
			} ),
		[ totalRows, visibleRows, showAll, initialRows, rowIncrement ]
	);

	const {
		heightRows,
		cappedVisibleRows,
		showControls,
		canAddMore,
		canRemoveRows,
		canShowAll,
		canShowFewer,
	} = metrics;

	const addFive = useCallback( () => {
		setShowAll( false );
		setVisibleRows( ( current ) =>
			Math.min( current + rowIncrement, totalRows )
		);
	}, [ totalRows, rowIncrement ] );

	const removeFive = useCallback( () => {
		setShowAll( false );
		setVisibleRows( ( current ) =>
			Math.max( current - rowIncrement, initialRows )
		);
	}, [ initialRows, rowIncrement ] );

	const showAllRows = useCallback( () => {
		setShowAll( true );
	}, [] );

	const resetRows = useCallback( () => {
		setShowAll( false );
		setVisibleRows( initialRows );
	}, [ initialRows ] );

	return {
		...metrics,
		addFive,
		removeFive,
		showAllRows,
		resetRows,
	};
}
