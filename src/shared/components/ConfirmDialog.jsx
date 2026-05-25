import { useEffect, useRef } from '@wordpress/element';
import { createPortal } from 'react-dom';
import { Button } from './Button';

/** Tailwind is scoped to #pr-root; dialogs must mount there to receive utility styles. */
export function getDialogPortalRoot() {
	return document.getElementById( 'pr-root' ) ?? document.body;
}

export function ConfirmDialog( {
	open,
	title,
	consequences = [],
	confirmLabel = 'Confirm',
	cancelLabel = 'Cancel',
	confirmVariant = 'primary',
	confirmIcon,
	confirmDisabled = false,
	children,
	onConfirm,
	onCancel,
} ) {
	const dialogRef = useRef( null );
	const onCancelRef = useRef( onCancel );
	onCancelRef.current = onCancel;

	useEffect( () => {
		if ( ! open || ! dialogRef.current ) {
			return;
		}

		const focusable = dialogRef.current.querySelectorAll(
			'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
		);
		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];
		const dialogEl = dialogRef.current;
		const active = document.activeElement;
		if ( ! active || ! dialogEl.contains( active ) ) {
			first?.focus();
		}

		const onKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				onCancelRef.current?.();
			}
			if ( event.key !== 'Tab' || focusable.length === 0 ) {
				return;
			}
			if ( event.shiftKey && document.activeElement === first ) {
				event.preventDefault();
				last?.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first?.focus();
			}
		};

		document.addEventListener( 'keydown', onKeyDown );
		return () => document.removeEventListener( 'keydown', onKeyDown );
	}, [ open ] );

	if ( ! open ) {
		return null;
	}

	const dialog = (
		<div className="fixed inset-0 z-[150] flex items-center justify-center bg-black/40 p-4">
			<div
				ref={ dialogRef }
				role="dialog"
				aria-modal="true"
				aria-labelledby="pr-confirm-title"
				className="w-full max-w-md rounded-md border border-border bg-surface-raised p-6 shadow-card"
			>
				<h2 id="pr-confirm-title" className="text-lg font-semibold text-text">
					{ title }
				</h2>
				{ consequences.length > 0 && (
					<ul className="mt-4 list-disc space-y-1 pl-5 text-sm text-text-muted">
						{ consequences.map( ( item ) => (
							<li key={ item }>{ item }</li>
						) ) }
					</ul>
				) }
				{ children && <div className="mt-4">{ children }</div> }
				<div className="mt-6 flex justify-end gap-2">
					<Button variant="secondary" onClick={ onCancel }>
						{ cancelLabel }
					</Button>
					<Button
						variant={ confirmVariant }
						icon={ confirmIcon }
						onClick={ onConfirm }
						disabled={ confirmDisabled }
					>
						{ confirmLabel }
					</Button>
				</div>
			</div>
		</div>
	);

	if ( typeof document === 'undefined' ) {
		return dialog;
	}

	return createPortal( dialog, getDialogPortalRoot() );
}
