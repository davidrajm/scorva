import { useEffect, useRef } from '@wordpress/element';
import { createPortal } from 'react-dom';
import { Button } from '../../shared/components/Button';
import { getDialogPortalRoot } from '../../shared/components/ConfirmDialog';

export function ScoreEntryModal( { open, title, onClose, children } ) {
	const dialogRef = useRef( null );

	useEffect( () => {
		if ( ! open || ! dialogRef.current ) {
			return;
		}
		const focusable = dialogRef.current.querySelectorAll(
			'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
		);
		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];
		first?.focus();

		const onKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				onClose?.();
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
	}, [ open, onClose ] );

	if ( ! open ) {
		return null;
	}

	const dialog = (
		<div
			className="fixed inset-0 z-[150] flex items-center justify-center bg-black/40 p-4"
			onClick={ onClose }
			role="presentation"
		>
			<div
				ref={ dialogRef }
				role="dialog"
				aria-modal="true"
				aria-labelledby="pr-score-entry-title"
				className="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-md border border-border bg-surface-raised p-6 shadow-card"
				onClick={ ( e ) => e.stopPropagation() }
			>
				<h2 id="pr-score-entry-title" className="text-lg font-semibold text-text">
					{ title }
				</h2>
				<div className="mt-4">{ children }</div>
				<div className="mt-6 flex justify-end">
					<Button variant="secondary" onClick={ onClose }>
						Cancel
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
