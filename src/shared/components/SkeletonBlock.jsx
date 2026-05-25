export function SkeletonBlock( { className = '' } ) {
	return (
		<div
			className={ `rounded-md border border-border bg-surface-raised animate-pulse motion-reduce:animate-none ${ className }`.trim() }
			aria-hidden="true"
		/>
	);
}
