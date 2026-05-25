import { useCallback, useEffect, useState } from '@wordpress/element';
import { getViewportRowCapacity } from './getViewportRowCapacity';
import {
	TABLE_VIEWPORT_INITIAL_ROWS,
	TABLE_VIEWPORT_MAX_ROWS,
	TABLE_VIEWPORT_MIN_FIT_ROWS,
} from './tableStyles';

const SAFE_PADDING_PX = 16;

function getMainElement() {
	return document.querySelector( '.pr-main' );
}

function getDataViewportElement( host ) {
	if ( ! host ) {
		return null;
	}
	if ( host.classList.contains( 'pr-table-data-viewport' ) ) {
		return host;
	}
	return host.querySelector( '.pr-table-data-viewport' );
}

/**
 * Remaining vertical space inside `.pr-main` below the table box (scroll-aware).
 *
 * @param {HTMLElement} main
 * @param {HTMLElement} anchor
 */
export function getAvailableHeightBelowAnchor( main, anchor ) {
	const anchorTopInMain =
		anchor.getBoundingClientRect().top -
		main.getBoundingClientRect().top +
		main.scrollTop;
	const mainStyle = window.getComputedStyle( main );
	const paddingBottom = parseFloat( mainStyle.paddingBottom ) || 0;

	return (
		main.clientHeight -
		anchorTopInMain -
		paddingBottom -
		SAFE_PADDING_PX
	);
}

/**
 * Viewport-aware initial row budget from `.pr-main` space below the table box.
 *
 * @param {import('react').RefObject<HTMLElement|null>} hostRef
 * @param {{
 *   headerRows?: number,
 *   totalRows?: number,
 *   rowHeightPx?: number|null,
 *   minRows?: number,
 *   maxRows?: number,
 *   enabled?: boolean,
 * }} options
 */
export function useTableViewportCapacity( hostRef, options = {} ) {
	const {
		headerRows = 1,
		totalRows = 0,
		rowHeightPx = null,
		minRows = TABLE_VIEWPORT_INITIAL_ROWS,
		maxRows = TABLE_VIEWPORT_MAX_ROWS,
		minFitRows = TABLE_VIEWPORT_MIN_FIT_ROWS,
		enabled = true,
	} = options;

	const [ suggestedInitialRows, setSuggestedInitialRows ] = useState(
		() => ( totalRows > 0 ? Math.min( totalRows, minRows ) : 0 )
	);

	const measure = useCallback( () => {
		if ( ! enabled ) {
			return;
		}

		const host = hostRef.current;
		const main = getMainElement();
		if ( ! host || ! main ) {
			setSuggestedInitialRows(
				totalRows > 0 ? Math.min( totalRows, minRows ) : 0
			);
			return;
		}

		const dataViewport = getDataViewportElement( host );
		const anchor = dataViewport || host;
		const availablePx = getAvailableHeightBelowAnchor( main, anchor );
		const fallbackRowPx = 48;
		const rowH =
			rowHeightPx && rowHeightPx > 0 ? rowHeightPx : fallbackRowPx;

		const suggested = getViewportRowCapacity( {
			availablePx,
			headerRows,
			rowHeightPx: rowH,
			minRows,
			maxRows,
			minFitRows,
			totalRows,
			toolbarPx: 0,
			safePaddingPx: 0,
		} );

		setSuggestedInitialRows( suggested );
	}, [
		hostRef,
		headerRows,
		totalRows,
		rowHeightPx,
		minRows,
		maxRows,
		minFitRows,
		enabled,
	] );

	useEffect( () => {
		const runMeasure = () => {
			requestAnimationFrame( measure );
		};

		runMeasure();

		const host = hostRef.current;
		const main = getMainElement();
		const observer = new ResizeObserver( runMeasure );

		if ( host ) {
			observer.observe( host );
		}
		if ( main ) {
			observer.observe( main );
		}

		main?.addEventListener( 'scroll', runMeasure, { passive: true } );
		window.addEventListener( 'resize', runMeasure, { passive: true } );

		return () => {
			observer.disconnect();
			main?.removeEventListener( 'scroll', runMeasure );
			window.removeEventListener( 'resize', runMeasure );
		};
	}, [ hostRef, measure ] );

	return suggestedInitialRows;
}
