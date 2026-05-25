import { useEffect, useState } from '@wordpress/element';
import { postMarkOverride } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import { ConfirmDialog, Notice } from '../../shared/components';

const MIN_REASON_LENGTH = 10;

export function MarkOverrideDialog( {
	open,
	markId,
	reviewLabel,
	studentLabel,
	criterionLabel,
	reviewerLabel,
	currentScore,
	maxMarks,
	onClose,
	onSuccess,
} ) {
	const [ score, setScore ] = useState( '' );
	const [ reason, setReason ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		setScore(
			currentScore != null && currentScore !== ''
				? String( currentScore )
				: ''
		);
		setReason( '' );
		setError( null );
		setSaving( false );
	}, [ open, currentScore ] );

	const handleClose = () => {
		onClose?.();
	};

	const handleConfirm = async () => {
		const trimmedReason = reason.trim();
		if ( trimmedReason.length < MIN_REASON_LENGTH ) {
			setError( 'Please enter a reason of at least 10 characters.' );
			return;
		}

		const parsed = Number( score );
		if ( Number.isNaN( parsed ) ) {
			setError( 'Enter a valid score.' );
			return;
		}

		setSaving( true );
		setError( null );
		try {
			await postMarkOverride( markId, {
				score: parsed,
				reason: trimmedReason,
			} );
			onSuccess?.();
			handleClose();
		} catch ( err ) {
			setError(
				parseApiErrorMessage(
					err,
					'Could not override this mark. Please try again.'
				)
			);
			setSaving( false );
		}
	};

	return (
		<ConfirmDialog
			open={ open }
			title="Override mark"
			consequences={ [
				'Updates this reviewer’s score for the criterion and records an audit entry with your reason.',
				'The change is labeled as a coordinator override (shuttle marking) in reports and reviewer views.',
			] }
			confirmLabel={ saving ? 'Saving…' : 'Override mark' }
			confirmDisabled={ saving || ! markId }
			onConfirm={ handleConfirm }
			onCancel={ handleClose }
		>
			{ reviewLabel ? (
				<p className="text-sm text-text-muted">
					Review: <span className="text-text">{ reviewLabel }</span>
				</p>
			) : null }
			{ studentLabel ? (
				<p className="mt-1 text-sm text-text-muted">
					Student: <span className="text-text">{ studentLabel }</span>
				</p>
			) : null }
			{ criterionLabel ? (
				<p className="mt-1 text-sm text-text-muted">
					Criterion: <span className="text-text">{ criterionLabel }</span>
				</p>
			) : null }
			{ reviewerLabel ? (
				<p className="mt-1 text-sm text-text-muted">
					Reviewer: <span className="text-text">{ reviewerLabel }</span>
				</p>
			) : null }
			{ currentScore != null ? (
				<p className="mt-2 text-sm text-text-muted">
					Current score:{' '}
					<span className="font-medium tabular-nums text-text">
						{ Number( currentScore ).toLocaleString() }
					</span>
				</p>
			) : null }

			<label className="mt-4 block text-sm font-medium text-text" htmlFor="mark-override-score">
				New score
			</label>
			<input
				id="mark-override-score"
				type="number"
				inputMode="decimal"
				step="0.5"
				min="0"
				max={ maxMarks ?? undefined }
				className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm tabular-nums text-text"
				value={ score }
				onChange={ ( event ) => setScore( event.target.value ) }
				disabled={ saving }
			/>
			{ maxMarks != null ? (
				<p className="mt-1 text-xs text-muted">Maximum { maxMarks } for this criterion.</p>
			) : null }

			<label className="mt-4 block text-sm font-medium text-text" htmlFor="mark-override-reason">
				Reason for override
			</label>
			<textarea
				id="mark-override-reason"
				className="mt-1 min-h-[5rem] w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text"
				value={ reason }
				onChange={ ( event ) => setReason( event.target.value ) }
				aria-required="true"
				disabled={ saving }
			/>
			<p className="mt-1 text-xs text-muted">At least { MIN_REASON_LENGTH } characters.</p>

			{ error ? (
				<Notice variant="error" className="mt-3">
					{ error }
				</Notice>
			) : null }
		</ConfirmDialog>
	);
}
