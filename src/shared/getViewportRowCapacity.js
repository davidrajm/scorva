import {
	TABLE_VIEWPORT_INITIAL_ROWS,
	TABLE_VIEWPORT_MAX_ROWS,
	TABLE_VIEWPORT_MIN_FIT_ROWS,
} from './tableStyles';

/** Header budget at 16px root (matches TableScrollViewport rem constants). */
export const TABLE_HEADER_HEIGHT_PX = {
	single: 48,
	double: 76,
};

/**
 * How many body rows fit in the progressive viewport (pure, testable).
 *
 * @param {{
 *   availablePx: number,
 *   headerRows?: number,
 *   rowHeightPx: number,
 *   minRows?: number,
 *   maxRows?: number,
 *   minFitRows?: number,
 *   totalRows: number,
 *   toolbarPx?: number,
 *   safePaddingPx?: number,
 * }} params
 */
export function getViewportRowCapacity( {
	availablePx,
	headerRows = 1,
	rowHeightPx,
	minRows = TABLE_VIEWPORT_INITIAL_ROWS,
	maxRows = TABLE_VIEWPORT_MAX_ROWS,
	minFitRows = TABLE_VIEWPORT_MIN_FIT_ROWS,
	totalRows,
	toolbarPx = 0,
	safePaddingPx = 16,
} ) {
	const total = Math.max( 0, Number( totalRows ) || 0 );
	if ( total === 0 ) {
		return 0;
	}

	if ( total <= minRows ) {
		return total;
	}

	const headerPx =
		headerRows >= 2
			? TABLE_HEADER_HEIGHT_PX.double
			: TABLE_HEADER_HEIGHT_PX.single;
	const bodyBudget =
		availablePx - headerPx - toolbarPx - safePaddingPx;
	const rowH = Math.max( 1, Number( rowHeightPx ) || minRows );
	const fitCount = Math.floor( bodyBudget / rowH );
	const capped = Math.min( total, maxRows, Math.max( 0, fitCount ) );

	if ( fitCount >= minRows ) {
		return Math.max( minRows, capped );
	}

	if ( total >= minFitRows ) {
		return Math.max( minFitRows, Math.min( capped, total ) );
	}

	return total;
}
