export function PageHeader( { title, description, actions } ) {
	return (
		<header className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
			<div className="min-w-0">
				<h1 className="text-[32px] font-semibold leading-tight text-text">
					{ title }
				</h1>
				{ description ? (
					<p className="mt-2 text-base text-text-muted">{ description }</p>
				) : null }
			</div>
			{ actions ? (
				<div className="flex shrink-0 flex-wrap items-center gap-2">
					{ actions }
				</div>
			) : null }
		</header>
	);
}
