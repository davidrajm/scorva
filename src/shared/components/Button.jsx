import { Icon } from './NavIcon';

const VARIANT_CLASSES = {
	primary:
		'bg-primary text-white hover:bg-primary-hover disabled:opacity-50',
	secondary:
		'bg-surface-raised text-text border border-border hover:bg-surface disabled:opacity-50',
	ghost:
		'bg-transparent text-primary hover:bg-chip-active-bg disabled:opacity-50',
	destructive:
		'bg-danger text-white hover:opacity-90 disabled:opacity-50',
};

const SIZE_CLASSES = {
	sm: 'px-3 py-1.5 text-sm',
	md: 'px-4 py-2 text-sm',
	lg: 'px-6 py-3 text-base',
};

export function Button( {
	variant = 'primary',
	size = 'md',
	disabled = false,
	loading = false,
	type = 'button',
	onClick,
	children,
	className = '',
	icon,
	iconPosition = 'start',
	...rest
} ) {
	const isDisabled = disabled || loading;
	const iconEl = icon ? (
		<Icon name={ icon } className="h-4 w-4 shrink-0" />
	) : null;
	const label = loading ? 'Loading…' : children;

	return (
		<button
			type={ type }
			disabled={ isDisabled }
			onClick={ onClick }
			className={ [
				'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
				VARIANT_CLASSES[ variant ] ?? VARIANT_CLASSES.primary,
				SIZE_CLASSES[ size ] ?? SIZE_CLASSES.md,
				className,
			]
				.filter( Boolean )
				.join( ' ' ) }
			aria-busy={ loading || undefined }
			{ ...rest }
		>
			{ icon && iconPosition === 'start' ? iconEl : null }
			{ label }
			{ icon && iconPosition === 'end' ? iconEl : null }
		</button>
	);
}
