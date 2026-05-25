import { useEffect, useState } from '@wordpress/element';
import { put } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import { formatAttendanceConflictLabel } from '../../shared/markErrors';
import { ConfirmDialog, Notice } from '../../shared/components';

const MIN_REASON_LENGTH = 10;

export function CorrectAttendanceDialog( {
	open,
	sessionId,
	reviewId,
	studentId,
	reviewLabel,
	currentStatus = 'present',
	onClose,
	onSuccess,
} ) {
	const [ targetStatus, setTargetStatus ] = useState( 'absent' );
	const [ reason, setReason ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		if ( ! open ) {
			return;
		}
		setTargetStatus( currentStatus === 'absent' ? 'present' : 'absent' );
		setReason( '' );
		setError( null );
		setSaving( false );
	}, [ open, currentStatus ] );

	const handleClose = () => {
		onClose?.();
	};

	const handleConfirm = async () => {
		const trimmed = reason.trim();
		if ( trimmed.length < MIN_REASON_LENGTH ) {
			setError( 'Please enter a reason of at least 10 characters.' );
			return;
		}

		setSaving( true );
		setError( null );
		try {
			await put(
				`/sessions/${ sessionId }/reviews/${ reviewId }/students/${ studentId }/attendance`,
				{
					attendance_status: targetStatus,
					reason: trimmed,
				}
			);
			onSuccess?.( targetStatus );
			handleClose();
		} catch ( err ) {
			setError(
				parseApiErrorMessage(
					err,
					'Could not correct attendance. Please try again.'
				)
			);
			setSaving( false );
		}
	};

	const consequences = [
		'Updates attendance for every reviewer on this student’s panel for this review.',
	];
	if ( targetStatus === 'absent' ) {
		consequences.push(
			'Clears all criterion scores for every panel reviewer on this student for this review.'
		);
	}

	return (
		<ConfirmDialog
			open={ open }
			title="Correct attendance"
			consequences={ consequences }
			confirmLabel={ saving ? 'Saving…' : 'Correct attendance' }
			confirmDisabled={ saving }
			onConfirm={ handleConfirm }
			onCancel={ handleClose }
		>
			{ reviewLabel ? (
				<p className="text-sm text-text-muted">
					Review: <span className="text-text">{ reviewLabel }</span>
				</p>
			) : null }
			<p className="mt-2 text-sm text-text-muted">
				Current attendance:{' '}
				<span className="font-medium text-text">
					{ formatAttendanceConflictLabel( currentStatus ) }
				</span>
			</p>

			<fieldset className="mt-4">
				<legend className="text-sm font-medium text-text">
					Set attendance to
				</legend>
				<div
					className="mt-2 flex flex-wrap gap-4"
					role="radiogroup"
					aria-label="Corrected attendance"
				>
					<label className="inline-flex items-center gap-2 text-sm text-text">
						<input
							type="radio"
							name="correct-attendance-target"
							value="present"
							checked={ targetStatus === 'present' }
							disabled={ saving }
							onChange={ () => setTargetStatus( 'present' ) }
						/>
						Present
					</label>
					<label className="inline-flex items-center gap-2 text-sm text-text">
						<input
							type="radio"
							name="correct-attendance-target"
							value="absent"
							checked={ targetStatus === 'absent' }
							disabled={ saving }
							onChange={ () => setTargetStatus( 'absent' ) }
						/>
						Absent
					</label>
				</div>
			</fieldset>

			<label className="mt-4 block text-sm font-medium text-text" htmlFor="attendance-correction-reason">
				Reason
				<textarea
					id="attendance-correction-reason"
					className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
					rows={ 3 }
					value={ reason }
					disabled={ saving }
					aria-required="true"
					placeholder="Explain why attendance is being corrected (min. 10 characters)."
					onChange={ ( event ) => setReason( event.target.value ) }
				/>
			</label>

			{ error ? (
				<div className="mt-3">
					<Notice variant="error">{ error }</Notice>
				</div>
			) : null }
		</ConfirmDialog>
	);
}
