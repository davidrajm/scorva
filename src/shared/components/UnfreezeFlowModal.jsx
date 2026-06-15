import { useEffect, useRef } from '@wordpress/element';
import { createPortal } from 'react-dom';
import { Icon } from './NavIcon';
import { getDialogPortalRoot } from './ConfirmDialog';

function FlowStep( { icon, label, detail, connector = true } ) {
	return (
		<div className="flex gap-3">
			<div className="flex flex-col items-center">
				<div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-chip-active-bg text-primary">
					<Icon name={ icon } className="h-4 w-4" />
				</div>
				{ connector ? (
					<div className="mt-1 w-px flex-1 bg-border min-h-[1.25rem]" />
				) : null }
			</div>
			<div className={ `min-w-0 ${ connector ? 'pb-4' : '' }` }>
				<p className="text-sm font-medium text-text">{ label }</p>
				{ detail ? (
					<p className="mt-0.5 text-sm text-text-muted">{ detail }</p>
				) : null }
			</div>
		</div>
	);
}

function FlowSection( { title, badge, steps } ) {
	return (
		<div className="rounded-md border border-border bg-surface p-4">
			<div className="mb-4 flex items-center gap-2">
				<span className="inline-flex items-center rounded-md bg-chip-active-bg px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-primary">
					{ badge }
				</span>
				<h3 className="text-sm font-semibold text-text">{ title }</h3>
			</div>
			<div>
				{ steps.map( ( step, i ) => (
					<FlowStep
						key={ i }
						icon={ step.icon }
						label={ step.label }
						detail={ step.detail }
						connector={ i < steps.length - 1 }
					/>
				) ) }
			</div>
		</div>
	);
}

const REVIEWER_FLOW = {
	badge: 'Tier 1',
	title: 'Reviewer mark unfreeze',
	steps: [
		{
			icon: 'lock',
			label: 'Reviewer freezes marks',
			detail: 'Scores are submitted and locked for the panel.',
		},
		{
			icon: 'unlock',
			label: 'Reviewer requests unfreeze',
			detail: 'The reviewer submits a request with a reason explaining why they need to edit.',
		},
		{
			icon: 'bell',
			label: 'Panel Coordinator is notified',
			detail: 'A badge appears on the bell icon in the header and on the Unfreeze Requests page.',
		},
		{
			icon: 'save',
			label: 'Panel Coordinator approves',
			detail: 'The reviewer\'s marks revert to draft. They can edit and re-freeze when ready.',
		},
	],
};

const PANEL_FLOW = {
	badge: 'Tier 2',
	title: 'Panel report unfreeze',
	steps: [
		{
			icon: 'lock',
			label: 'Panel Coordinator freezes the panel report',
			detail: 'All reviewer scores for the panel are locked and the PDF becomes downloadable.',
		},
		{
			icon: 'unlock',
			label: 'Panel Coordinator requests panel unfreeze',
			detail: 'The Panel Coordinator submits a request with a reason to the Coordinator.',
		},
		{
			icon: 'bell',
			label: 'Coordinator is notified',
			detail: 'A badge appears on the bell icon in the Coordinator header.',
		},
		{
			icon: 'save',
			label: 'Coordinator approves',
			detail: 'The panel report becomes editable. The Panel Coordinator must re-freeze when done.',
		},
	],
};

export function UnfreezeFlowModal( { open, onClose } ) {
	const dialogRef = useRef( null );
	const onCloseRef = useRef( onClose );
	onCloseRef.current = onClose;

	useEffect( () => {
		if ( ! open || ! dialogRef.current ) {
			return undefined;
		}
		dialogRef.current.querySelector( 'button' )?.focus();
		const onKeyDown = ( e ) => {
			if ( e.key === 'Escape' ) {
				onCloseRef.current?.();
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
				aria-labelledby="pr-unfreeze-flow-title"
				className="flex max-h-[90vh] w-full max-w-2xl flex-col rounded-md border border-border bg-surface-raised shadow-card"
			>
				<div className="flex shrink-0 items-center justify-between gap-4 border-b border-border px-6 py-4">
					<h2 id="pr-unfreeze-flow-title" className="text-base font-semibold text-text">
						How unfreeze requests work
					</h2>
					<button
						type="button"
						onClick={ onClose }
						className="shrink-0 rounded p-1 text-text-muted hover:bg-surface hover:text-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
						aria-label="Close"
					>
						<Icon name="dismiss" className="h-5 w-5" />
					</button>
				</div>

				<div className="overflow-y-auto p-6 pr-scroll">
					<p className="mb-5 text-sm text-text-muted">
						The unfreeze process has two tiers. Reviewer-level requests are handled
						by the Panel Coordinator. Panel-level requests are handled by the
						Coordinator.
					</p>
					<div className="space-y-4">
						<FlowSection { ...REVIEWER_FLOW } />
						<FlowSection { ...PANEL_FLOW } />
					</div>
				</div>

				<div className="shrink-0 border-t border-border px-6 py-4">
					<button
						type="button"
						onClick={ onClose }
						className="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-hover focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
					>
						Got it
					</button>
				</div>
			</div>
		</div>
	);

	if ( typeof document === 'undefined' ) {
		return dialog;
	}

	return createPortal( dialog, getDialogPortalRoot() );
}
