import { Spinner } from '@wordpress/components';

/**
 * Content-area loading wrapper — keeps layout stable; supports overlay refresh.
 */
export function ContentLoadingRegion( {
	busy = false,
	variant = 'inline',
	label = 'Loading',
	minHeight = '12rem',
	className = '',
	children,
} ) {
	return (
		<div
			className={ `relative ${ className }`.trim() }
			aria-busy={ busy ? 'true' : 'false' }
			aria-live="polite"
			data-testid={ busy ? 'pr-content-loading' : undefined }
			style={
				busy && variant === 'inline' && ! children
					? { minHeight }
					: undefined
			}
		>
			{ busy ? (
				<span className="sr-only">{ label }</span>
			) : null }
			{ children }
			{ busy && variant === 'overlay' ? (
				<div
					className="absolute inset-0 z-10 flex items-center justify-center rounded-md bg-surface/70"
					aria-hidden="true"
				>
					<Spinner />
				</div>
			) : null }
		</div>
	);
}
