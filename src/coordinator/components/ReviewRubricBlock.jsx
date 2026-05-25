import { useState } from '@wordpress/element';
import { post, put } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import { ConfirmDialog, Notice } from '../../shared/components';
import {
	buildCriteriaPayload,
	validateCriteriaRows,
} from '../../shared/rubricCriteria';
import { RubricTable } from './RubricTable';

export function ReviewRubricBlock( {
	sessionId,
	review,
	busy = false,
	onBusyChange,
	onUpdated,
	embedded = false,
} ) {
	const [ dialog, setDialog ] = useState( null );
	const [ pendingCriteria, setPendingCriteria ] = useState( null );
	const [ reconfirmAction, setReconfirmAction ] = useState( 'keep_flag' );
	const [ error, setError ] = useState( null );

	const setBusy = ( value ) => {
		onBusyChange?.( value );
	};

	const reportError = ( err, fallback ) => {
		setError( parseApiErrorMessage( err, fallback ) );
	};

	const clearError = () => {
		setError( null );
	};

	const persistCriteria = async ( criteria ) => {
		await put(
			`/sessions/${ sessionId }/reviews/${ review.id }/criteria`,
			{ criteria }
		);
	};

	const handleSaveCriteria = async ( rows ) => {
		const validationError = validateCriteriaRows( rows );
		if ( validationError ) {
			setError( validationError );
			return;
		}

		setBusy( true );
		clearError();
		try {
			await persistCriteria( buildCriteriaPayload( rows ) );
			await onUpdated?.();
		} catch ( error ) {
			reportError( error, 'Could not save rubric criteria.' );
		} finally {
			setBusy( false );
		}
	};

	const runConfirm = async ( markAction ) => {
		setBusy( true );
		clearError();
		try {
			if ( pendingCriteria ) {
				await persistCriteria( pendingCriteria );
			}
			const body = markAction ? { mark_action: markAction } : {};
			await post(
				`/sessions/${ sessionId }/reviews/${ review.id }/confirm`,
				body
			);
			setDialog( null );
			setPendingCriteria( null );
			await onUpdated?.();
		} catch ( error ) {
			reportError( error, 'Could not confirm rubric.' );
		} finally {
			setBusy( false );
		}
	};

	const runUnlock = async () => {
		setBusy( true );
		clearError();
		try {
			await post(
				`/sessions/${ sessionId }/reviews/${ review.id }/unlock`
			);
			setDialog( null );
			await onUpdated?.();
		} catch ( error ) {
			reportError( error, 'Could not unlock rubric.' );
		} finally {
			setBusy( false );
		}
	};

	const openConfirmDialog = ( rows ) => {
		const validationError = validateCriteriaRows( rows );
		if ( validationError ) {
			setError( validationError );
			return;
		}

		clearError();
		setPendingCriteria( buildCriteriaPayload( rows ) );

		if ( review.status === 'unlocked' && review.has_marks ) {
			setReconfirmAction( 'keep_flag' );
			setDialog( { type: 'reconfirm' } );
			return;
		}

		setDialog( { type: 'confirm' } );
	};

	const dialogs = (
		<>
			<ConfirmDialog
				open={ dialog?.type === 'confirm' }
				title={ `Confirm ${ review.label ?? 'rubric' }?` }
				consequences={ [
					'Reviewers can enter marks for this round when the project is active.',
					'Criteria stay editable until a score is saved; unlock to edit after scoring starts.',
				] }
				confirmLabel="Confirm rubric"
				onConfirm={ () => runConfirm() }
				onCancel={ () => {
					setDialog( null );
					setPendingCriteria( null );
				} }
			/>

			<ConfirmDialog
				open={ dialog?.type === 'unlock' }
				title={ `Unlock ${ review.label ?? 'rubric' }?` }
				consequences={ [
					'Marking is paused until you confirm the rubric again.',
					'Reviewers cannot submit new marks while unlocked.',
				] }
				confirmLabel="Unlock rubric"
				confirmVariant="destructive"
				onConfirm={ runUnlock }
				onCancel={ () => setDialog( null ) }
			/>

			<ConfirmDialog
				open={ dialog?.type === 'reconfirm' }
				title={ `Re-confirm ${ review.label ?? 'rubric' }?` }
				consequences={ [
					'Keep and flag: existing marks stay visible but are flagged for review.',
					'Clear marks: all marks for this review round are removed.',
					'Marking reopens after confirmation when the project is active.',
				] }
				confirmLabel="Re-confirm rubric"
				onConfirm={ () => runConfirm( reconfirmAction ) }
				onCancel={ () => {
					setDialog( null );
					setPendingCriteria( null );
				} }
			>
				<fieldset className="space-y-2 text-sm">
					<label className="flex items-center gap-2">
						<input
							type="radio"
							name={ `mark_action_${ review.id }` }
							value="keep_flag"
							checked={ reconfirmAction === 'keep_flag' }
							onChange={ () => setReconfirmAction( 'keep_flag' ) }
						/>
						Keep and flag existing marks (recommended)
					</label>
					<label className="flex items-center gap-2">
						<input
							type="radio"
							name={ `mark_action_${ review.id }` }
							value="clear"
							checked={ reconfirmAction === 'clear' }
							onChange={ () => setReconfirmAction( 'clear' ) }
						/>
						Clear marks for this review round
					</label>
				</fieldset>
			</ConfirmDialog>
		</>
	);

	return (
		<>
			<RubricTable
				review={ review }
				busy={ busy }
				embedded={ embedded }
				onSave={ handleSaveCriteria }
				onConfirm={ openConfirmDialog }
				onUnlock={ () => setDialog( { type: 'unlock' } ) }
			/>
			{ error ? (
				<div className="mt-3">
					<Notice variant="error">{ error }</Notice>
				</div>
			) : null }
			{ dialogs }
		</>
	);
}
