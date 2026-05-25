import { useCallback, useEffect, useState } from '@wordpress/element';
import {
	TABLE_ROW_HEIGHT_COMFORTABLE_REM,
	TABLE_ROW_HEIGHT_DENSE_REM,
} from './tableStyles';

const ROOT_FONT_PX = 16;

function remToPx( rem ) {
	return rem * ROOT_FONT_PX;
}

/**
 * Measure first body row height inside the table viewport (semantic tr or grid cells).
 *
 * @param {import('react').RefObject<HTMLElement|null>} viewportRef
 * @param {number} bodyRowCount
 * @param {'comfortable'|'dense'|'auto'} rowHeightVariant
 */
export function useMeasuredTableRowHeight(
	viewportRef,
	bodyRowCount,
	rowHeightVariant = 'auto'
) {
	const fallbackPx =
		rowHeightVariant === 'dense'
			? remToPx( TABLE_ROW_HEIGHT_DENSE_REM )
			: remToPx( TABLE_ROW_HEIGHT_COMFORTABLE_REM );

	const [ rowHeightPx, setRowHeightPx ] = useState( fallbackPx );

	const measure = useCallback( () => {
		const viewport = viewportRef.current;
		if ( ! viewport || bodyRowCount <= 0 ) {
			setRowHeightPx( fallbackPx );
			return;
		}

		const tbodyRow = viewport.querySelector( 'tbody tr' );
		if ( tbodyRow ) {
			const height = tbodyRow.getBoundingClientRect().height;
			if ( height > 0 ) {
				setRowHeightPx( height );
				return;
			}
		}

		// Grid tables: skip header row (`contents` only); body rows use `group contents`.
		const bodyRow = viewport.querySelector( '[role="row"].group' );
		if ( bodyRow ) {
			const cells = bodyRow.querySelectorAll( '[role="cell"]' );
			let max = 0;
			cells.forEach( ( cell ) => {
				max = Math.max( max, cell.getBoundingClientRect().height );
			} );
			if ( max > 0 ) {
				setRowHeightPx( max );
				return;
			}
		}

		setRowHeightPx( fallbackPx );
	}, [ viewportRef, bodyRowCount, fallbackPx ] );

	useEffect( () => {
		const runMeasure = () => {
			requestAnimationFrame( measure );
		};

		runMeasure();

		const viewport = viewportRef.current;
		if ( ! viewport ) {
			return undefined;
		}

		const observer = new ResizeObserver( runMeasure );
		observer.observe( viewport );

		const tbodyRow = viewport.querySelector( 'tbody tr' );
		if ( tbodyRow ) {
			observer.observe( tbodyRow );
		}

		// Grid tables: skip header row (`contents` only); body rows use `group contents`.
		const bodyRow = viewport.querySelector( '[role="row"].group' );
		if ( bodyRow ) {
			bodyRow.querySelectorAll( '[role="cell"]' ).forEach( ( cell ) => {
				observer.observe( cell );
			} );
		}

		return () => observer.disconnect();
	}, [ viewportRef, measure, bodyRowCount ] );

	return rowHeightPx;
}
