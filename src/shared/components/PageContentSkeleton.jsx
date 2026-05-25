import { SkeletonBlock } from './SkeletonBlock';

export function PageContentSkeleton( { showTitle = true, rows = 3 } ) {
	return (
		<div className="space-y-4" aria-hidden="true">
			{ showTitle ? (
				<div className="space-y-2">
					<SkeletonBlock className="h-9 w-64 max-w-full" />
					<SkeletonBlock className="h-4 w-96 max-w-full" />
				</div>
			) : null }
			{ Array.from( { length: rows }, ( _, index ) => (
				<SkeletonBlock key={ index } className="h-16 w-full" />
			) ) }
		</div>
	);
}
