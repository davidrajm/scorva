/**
 * Shared row hover classes for data tables and CSS-grid "tables".
 *
 * - TABLE_BODY_ROW — semantic <tbody> <tr>
 * - TABLE_BODY_ROW_SOFT — softer dividers (border-border/60)
 * - TABLE_ROW_HOVER — hover only (e.g. audit log border-t rows)
 * - GRID_ROW_GROUP — wrapper with display:contents (MarkingGrid)
 * - GRID_ROW_CELL — append to each grid cell in a body row
 */

export const TABLE_ROW_HOVER = 'transition-colors hover:bg-surface-raised';

export const TABLE_BODY_ROW = `border-b border-border last:border-0 ${ TABLE_ROW_HOVER }`;

export const TABLE_BODY_ROW_SOFT = `border-b border-border/60 last:border-0 ${ TABLE_ROW_HOVER }`;

export const GRID_ROW_GROUP = 'group contents';

export const GRID_ROW_CELL = 'transition-colors group-hover:bg-surface-raised';

/** Serial / row number column width when present before Reg no (e.g. marking grid). */
export const SERIAL_NO_COLUMN_WIDTH_REM = 2.5;

export const REG_NO_COLUMN_WIDTH_REM = 5.5;

/**
 * @param {{ serialNoBefore?: boolean }} options When true, Reg no is not the first column (Sr no precedes it).
 */
export function regNoStickyLeftRem( { serialNoBefore = false } = {} ) {
	return serialNoBefore ? SERIAL_NO_COLUMN_WIDTH_REM : 0;
}

/**
 * @param {{ serialNoBefore?: boolean }} options
 */
export function regNoStickyStyle( { serialNoBefore = false } = {} ) {
	return {
		left: `${ regNoStickyLeftRem( { serialNoBefore } ) }rem`,
		minWidth: `${ REG_NO_COLUMN_WIDTH_REM }rem`,
	};
}

/**
 * @param {{ header?: boolean }} options
 */
export function regNoStickyClass( { header = false } = {} ) {
	return [
		'sticky',
		header ? 'z-30' : 'z-20',
		'bg-surface',
		'group-hover:bg-surface-raised',
		'shadow-[4px_0_6px_-4px_rgba(31,35,40,0.12)]',
	]
		.filter( Boolean )
		.join( ' ' );
}

/** Horizontal scroll wrapper — keeps overflow off the app shell / sidebar. */
export const TABLE_SCROLL_WRAPPER =
	'pr-table-scroll pr-scroll min-w-0 w-full max-w-full overflow-x-auto overscroll-x-contain rounded-md border border-border';

/** Wide table/matrix inside TABLE_SCROLL_WRAPPER — expands horizontally, scrolls in parent. */
export const TABLE_SCROLL_INNER = 'w-max min-w-full';

/** Default body rows shown before Add 5 more / inner scroll (story 1.11). */
export const TABLE_VIEWPORT_INITIAL_ROWS = 10;

/** Maximum default window when viewport has ample height (story 1.11). */
export const TABLE_VIEWPORT_MAX_ROWS = 20;

/** Minimum body rows on very short viewports when data allows (story 1.11). */
export const TABLE_VIEWPORT_MIN_FIT_ROWS = 3;

/** Row increment for Add 5 more (story 1.10). */
export const TABLE_VIEWPORT_ROW_INCREMENT = 5;

/** Semantic `<tr>` rows (e.g. registry py-3). */
export const TABLE_ROW_HEIGHT_COMFORTABLE_REM = 3;

/** Grid / chip rows (e.g. marking grid py-2 + controls). */
export const TABLE_ROW_HEIGHT_DENSE_REM = 3.5;

/**
 * Dual-axis viewport for tall + wide matrices — horizontal bar stays at bottom of
 * the capped box; vertical height from progressive row window.
 */
export const TABLE_DATA_VIEWPORT =
	'pr-table-data-viewport pr-table-scroll pr-scroll min-w-0 w-full max-w-full rounded-md border border-border';
