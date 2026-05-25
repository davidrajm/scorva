import {
	useCallback,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';
import { TABLE_DATA_VIEWPORT, TABLE_SCROLL_WRAPPER } from './tableStyles';
import {
	TABLE_VIEWPORT_INITIAL_ROWS,
	useTableRowWindow,
} from './useTableRowWindow';
import { useMeasuredTableRowHeight } from './useMeasuredTableRowHeight';
import { useTableViewportCapacity } from './useTableViewportCapacity';

const HEADER_HEIGHT_SINGLE = '3rem';
const HEADER_HEIGHT_DOUBLE = '4.75rem';

function useScrollCue( ref, enabled ) {
	const [ cueRight, setCueRight ] = useState( false );
	const [ cueLeft, setCueLeft ] = useState( false );
	const [ scrollable, setScrollable ] = useState( false );

	const update = useCallback( () => {
		const el = ref.current;
		if ( ! el || ! enabled ) {
			setCueRight( false );
			setCueLeft( false );
			setScrollable( false );
			return;
		}
		const canScrollX = el.scrollWidth > el.clientWidth + 1;
		const canScrollY = el.scrollHeight > el.clientHeight + 1;
		setScrollable( canScrollX || canScrollY );
		setCueLeft( canScrollX && el.scrollLeft > 4 );
		setCueRight(
			canScrollX &&
				el.scrollLeft + el.clientWidth < el.scrollWidth - 4
		);
	}, [ ref, enabled ] );

	useEffect( () => {
		const el = ref.current;
		if ( ! el ) {
			return undefined;
		}
		update();
		el.addEventListener( 'scroll', update, { passive: true } );
		const observer = new ResizeObserver( update );
		observer.observe( el );
		return () => {
			el.removeEventListener( 'scroll', update );
			observer.disconnect();
		};
	}, [ ref, update, enabled ] );

	return { cueRight, cueLeft, scrollable };
}

function cueClasses( cueRight, cueLeft ) {
	return [
		cueRight ? 'pr-table-scroll--cue-right' : '',
		cueLeft ? 'pr-table-scroll--cue-left' : '',
	]
		.filter( Boolean )
		.join( ' ' );
}

function viewportStyleVars( headerRows, heightRows, rowHeightPx ) {
	return {
		'--pr-table-visible-rows': String( heightRows ),
		'--pr-table-header-height':
			headerRows >= 2 ? HEADER_HEIGHT_DOUBLE : HEADER_HEIGHT_SINGLE,
		'--pr-table-row-height': `${ rowHeightPx }px`,
	};
}

function preserveHorizontalScroll( ref, action ) {
	const scrollLeft = ref.current?.scrollLeft ?? 0;
	action();
	requestAnimationFrame( () => {
		if ( ref.current ) {
			ref.current.scrollLeft = scrollLeft;
		}
	} );
}

/**
 * Tall data table: progressive row height (10 default), Add 5 more / Show all.
 * Page scroll stays on `.pr-main`; inner vertical scroll only inside this box.
 */
export function TableDataViewport( {
	className = '',
	children,
	bodyRowCount = 0,
	headerRows = 1,
	initialRows: initialRowsProp,
	rowIncrement,
	rowHeightVariant = 'auto',
	showControls: showControlsProp,
	...rest
} ) {
	const hostRef = useRef( null );
	const viewportRef = useRef( null );
	const totalRows = Math.max( 0, Number( bodyRowCount ) || 0 );

	const measuredRowHeightPx = useMeasuredTableRowHeight(
		viewportRef,
		totalRows,
		rowHeightVariant === 'dense'
			? 'dense'
			: rowHeightVariant === 'comfortable'
				? 'comfortable'
				: 'auto'
	);

	const capacityInitialRows = useTableViewportCapacity( hostRef, {
		headerRows,
		totalRows,
		rowHeightPx: measuredRowHeightPx,
	} );

	const resolvedInitialRows =
		initialRowsProp ?? capacityInitialRows ?? TABLE_VIEWPORT_INITIAL_ROWS;

	const rowWindow = useTableRowWindow( bodyRowCount, {
		initialRows: resolvedInitialRows,
		rowIncrement,
	} );
	const {
		totalRows: windowTotalRows,
		heightRows,
		showAll,
		showControls: autoShowControls,
		canAddMore,
		canRemoveRows,
		canShowAll,
		canShowFewer,
		cappedVisibleRows,
		initialRows: resolvedInitialRowsForControls,
		rowIncrement: resolvedIncrement,
		addFive,
		removeFive,
		showAllRows,
		resetRows,
	} = rowWindow;

	const showControls =
		showControlsProp !== undefined ? showControlsProp : autoShowControls;

	const { cueRight, cueLeft, scrollable } = useScrollCue( viewportRef, true );

	const viewportClasses = [
		TABLE_DATA_VIEWPORT,
		showAll ? 'pr-table-data-viewport--show-all' : '',
		cueClasses( cueRight, cueLeft ),
		className,
	]
		.filter( Boolean )
		.join( ' ' );

	const handleAddFive = () => {
		preserveHorizontalScroll( viewportRef, addFive );
	};

	const handleShowAll = () => {
		preserveHorizontalScroll( viewportRef, showAllRows );
	};

	const handleRemoveFive = () => {
		preserveHorizontalScroll( viewportRef, removeFive );
	};

	const handleReset = () => {
		preserveHorizontalScroll( viewportRef, resetRows );
	};

	const helperText =
		showControls && windowTotalRows > resolvedInitialRowsForControls
			? showAll
				? `Showing all ${ windowTotalRows } rows`
				: `Showing ${ cappedVisibleRows } of ${ windowTotalRows } rows`
			: null;

	const viewport = (
		<div
			ref={ viewportRef }
			className={ viewportClasses }
			style={ viewportStyleVars(
				headerRows,
				heightRows,
				measuredRowHeightPx
			) }
			tabIndex={ scrollable ? 0 : undefined }
			{ ...rest }
		>
			{ children }
		</div>
	);

	return (
		<div ref={ hostRef } className="pr-table-viewport-host">
			{ showControls ? (
				<div className="pr-table-viewport-toolbar">
					{ helperText ? (
						<p
							className="pr-table-viewport-helper"
							aria-live="polite"
						>
							{ helperText }
						</p>
					) : (
						<span />
					) }
					<div className="pr-table-viewport-actions">
						{ canAddMore ? (
							<button
								type="button"
								className="pr-table-viewport-toggle"
								onClick={ handleAddFive }
								aria-label={ `Add ${ resolvedIncrement } more rows to the table view` }
							>
								Add { resolvedIncrement } more
							</button>
						) : null }
						{ canRemoveRows ? (
							<button
								type="button"
								className="pr-table-viewport-toggle"
								onClick={ handleRemoveFive }
								aria-label={ `Remove ${ resolvedIncrement } rows from the table view` }
							>
								Remove { resolvedIncrement }
							</button>
						) : null }
						{ canShowAll ? (
							<button
								type="button"
								className="pr-table-viewport-toggle"
								onClick={ handleShowAll }
								aria-label="Show all table rows"
							>
								Show all
							</button>
						) : null }
						{ canShowFewer ? (
							<button
								type="button"
								className="pr-table-viewport-toggle"
								onClick={ handleReset }
								aria-label={ `Reset table view to ${ resolvedInitialRowsForControls } rows` }
							>
								Show fewer
							</button>
						) : null }
					</div>
				</div>
			) : null }
			{ viewport }
		</div>
	);
}

export function TableScrollWrapper( { className = '', children, ...rest } ) {
	const ref = useRef( null );
	const { cueRight, cueLeft, scrollable } = useScrollCue( ref, true );

	return (
		<div
			ref={ ref }
			className={ [
				TABLE_SCROLL_WRAPPER,
				cueClasses( cueRight, cueLeft ),
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
			tabIndex={ scrollable ? 0 : undefined }
			{ ...rest }
		>
			{ children }
		</div>
	);
}
