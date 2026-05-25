export function Card( { children, onClick, className = '' } ) {
	const Component = onClick ? 'button' : 'div';
	const interactiveClasses = onClick
		? 'cursor-pointer text-left transition-shadow hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary'
		: '';

	return (
		<Component
			type={ onClick ? 'button' : undefined }
			onClick={ onClick }
			className={ [
				'rounded-md border border-border bg-surface-raised p-6 shadow-card',
				interactiveClasses,
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
		>
			{ children }
		</Component>
	);
}
