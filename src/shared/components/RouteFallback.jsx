import { PageContentSkeleton } from './PageContentSkeleton';

export function RouteFallback( { label = 'Loading page' } ) {
	return (
		<div aria-live="polite" data-testid="pr-content-loading">
			<span className="sr-only">{ label }</span>
			<PageContentSkeleton rows={ 4 } />
		</div>
	);
}
