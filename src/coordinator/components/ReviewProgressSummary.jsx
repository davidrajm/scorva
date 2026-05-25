import { useEffect, useState } from '@wordpress/element';
import {
	CircularProgressbarWithChildren,
	buildStyles,
} from 'react-circular-progressbar';
import 'react-circular-progressbar/dist/styles.css';
import { MarkStatusCounts } from './ProgressAccordion';

const CHART_SIZE = 104;

function RadialProgress( { label, completed, total, percent, summary } ) {
	const [ reducedMotion, setReducedMotion ] = useState( false );

	useEffect( () => {
		const mq = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		const update = () => setReducedMotion( mq.matches );
		update();
		mq.addEventListener( 'change', update );
		return () => mq.removeEventListener( 'change', update );
	}, [] );

	const value = Math.min( 100, Math.max( 0, Number( percent ) || 0 ) );
	const inProgress = summary?.marks_in_progress ?? 0;
	const notStarted = summary?.marks_not_started ?? 0;
	const ariaLabel = `${ label }: ${ completed } complete, ${ inProgress } in progress, ${ notStarted } not started, of ${ total } review marks; ${ value } percent complete`;

	const ringStyles = buildStyles( {
		rotation: 0.25,
		strokeLinecap: 'round',
		pathTransitionDuration: reducedMotion ? 0 : 0.55,
		pathColor: 'var(--pr-color-primary)',
		trailColor: 'var(--pr-color-border)',
	} );

	return (
		<figure className="flex flex-col items-center gap-3">
			<div
				className="relative rounded-full bg-surface-raised p-1 shadow-card"
				style={ { width: CHART_SIZE, height: CHART_SIZE } }
				role="img"
				aria-label={ ariaLabel }
			>
				<CircularProgressbarWithChildren
					value={ value }
					strokeWidth={ 10 }
					styles={ ringStyles }
				>
					<div className="flex flex-col items-center justify-center leading-tight">
						<span className="text-sm font-semibold tabular-nums text-text">
							{ completed }/{ total }
						</span>
						<span className="mt-0.5 text-xs font-medium tabular-nums text-muted">
							{ value }%
						</span>
					</div>
				</CircularProgressbarWithChildren>
			</div>
			<figcaption className="flex max-w-[12rem] flex-col items-center gap-1.5 text-center">
				<p className="text-sm font-medium text-text">{ label }</p>
				<MarkStatusCounts
					summary={ summary }
					className="text-xs leading-snug"
				/>
			</figcaption>
		</figure>
	);
}

export function ReviewProgressSummary( { reviews } ) {
	if ( ! reviews?.length ) {
		return null;
	}

	return (
		<div className="mb-8 flex flex-wrap gap-10">
			{ reviews.map( ( review ) => {
				const summary = review.summary || {};
				return (
					<RadialProgress
						key={ review.review_id }
						label={ review.review_label }
						completed={ summary.marks_completed ?? 0 }
						total={ summary.marks_total ?? 0 }
						percent={ summary.percent ?? 0 }
						summary={ summary }
					/>
				);
			} ) }
		</div>
	);
}
