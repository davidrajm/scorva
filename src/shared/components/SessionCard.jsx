import { Link } from 'react-router-dom';
import { Card } from './Card';
import { StatusChip } from './StatusChip';

export function SessionCard( {
	title,
	status = 'draft',
	progress,
	enrolledCount,
	to,
} ) {
	const rosterLabel =
		enrolledCount == null
			? null
			: `${ enrolledCount } student${ enrolledCount === 1 ? '' : 's' }`;

	const content = (
		<Card className={ to ? 'transition-shadow hover:shadow-md' : undefined }>
			<div className="flex flex-col gap-4">
				<div className="flex flex-wrap items-start justify-between gap-2">
					<h3 className="text-xl font-semibold text-text">{ title }</h3>
					<StatusChip variant={ status } />
				</div>
				{ rosterLabel ? (
					<p className="text-sm text-text-muted">{ rosterLabel }</p>
				) : null }
				<div
					className="h-2 rounded-full bg-surface"
					aria-hidden={ progress == null }
				>
					{ progress != null ? (
						<div
							className="h-2 rounded-full bg-primary"
							style={ { width: `${ Math.min( 100, Math.max( 0, progress ) ) }%` } }
						/>
					) : (
						<div className="h-2 w-1/3 rounded-full bg-border" />
					) }
				</div>
			</div>
		</Card>
	);

	if ( to ) {
		return (
			<Link
				to={ to }
				className="block rounded-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
			>
				{ content }
			</Link>
		);
	}

	return content;
}
