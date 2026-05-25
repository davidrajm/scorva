import { SkeletonBlock } from './SkeletonBlock';

export function CardGridSkeleton( { count = 6 } ) {
	return (
		<ul
			className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3"
			aria-hidden="true"
		>
			{ Array.from( { length: count }, ( _, index ) => (
				<li key={ index }>
					<SkeletonBlock className="h-36 w-full" />
				</li>
			) ) }
		</ul>
	);
}
