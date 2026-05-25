import { TABLE_SCROLL_WRAPPER } from '../tableStyles';
import { SkeletonBlock } from './SkeletonBlock';

export function TableSkeleton( { rows = 8, columns = 5 } ) {
	return (
		<div className={ TABLE_SCROLL_WRAPPER } aria-hidden="true">
			<div className="min-w-full space-y-2 p-3">
				<div
					className="grid gap-2"
					style={ {
						gridTemplateColumns: `repeat(${ columns }, minmax(4rem, 1fr))`,
					} }
				>
					{ Array.from( { length: columns }, ( _, index ) => (
						<SkeletonBlock key={ `h-${ index }` } className="h-8" />
					) ) }
				</div>
				{ Array.from( { length: rows }, ( _, rowIndex ) => (
					<div
						key={ rowIndex }
						className="grid gap-2"
						style={ {
							gridTemplateColumns: `repeat(${ columns }, minmax(4rem, 1fr))`,
						} }
					>
						{ Array.from( { length: columns }, ( __, colIndex ) => (
							<SkeletonBlock
								key={ `${ rowIndex }-${ colIndex }` }
								className="h-10"
							/>
						) ) }
					</div>
				) ) }
			</div>
		</div>
	);
}
