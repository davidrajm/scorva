const VARIANT_CLASSES = {
	success: 'border-success/30 bg-success/10 text-success',
	error: 'border-danger/30 bg-danger/10 text-danger',
	warning: 'border-warning/30 bg-warning/10 text-warning',
	info: 'border-primary/30 bg-chip-active-bg text-text',
};

export function Notice( { variant = 'info', children, onDismiss, className = '' } ) {
	return (
		<div
			className={ [
				'flex items-start justify-between gap-4 rounded-md border px-4 py-3 text-sm',
				VARIANT_CLASSES[ variant ] ?? VARIANT_CLASSES.info,
				className,
			].join( ' ' ) }
			role="status"
		>
			<div className="flex-1">{ children }</div>
			{ onDismiss ? (
				<button
					type="button"
					onClick={ onDismiss }
					className="text-sm font-medium underline"
				>
					Dismiss
				</button>
			) : null }
		</div>
	);
}
