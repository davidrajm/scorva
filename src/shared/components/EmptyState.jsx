export function EmptyState( { title, description, action } ) {
	return (
		<div className="flex flex-col items-center justify-center rounded-md border border-border bg-surface-raised px-6 py-12 text-center shadow-card">
			<h2 className="text-xl font-semibold text-text">{ title }</h2>
			{ description ? (
				<p className="mt-2 max-w-md text-base text-text-muted">
					{ description }
				</p>
			) : null }
			{ action ? <div className="mt-6">{ action }</div> : null }
		</div>
	);
}
