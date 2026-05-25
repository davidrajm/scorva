/**
 * Distinguish initial load (skeleton) from refresh (overlay on stale data).
 *
 * @param {boolean} loading  Fetch in progress.
 * @param {boolean} hasData  Prior data is available to show while refreshing.
 */
export function useLoadingPhase( loading, hasData ) {
	return {
		showSkeleton: loading && ! hasData,
		showOverlay: loading && hasData,
	};
}
