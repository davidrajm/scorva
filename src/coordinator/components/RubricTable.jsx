import { useEffect, useMemo, useState } from '@wordpress/element';
import { Button, Card, StatusChip } from '../../shared/components';
import { isCriteriaEditable } from '../../shared/rubricEditable';
import {
	formatMarksSum,
	sumCriteriaMaxMarks,
} from '../../shared/rubricCriteria';
import { TableScrollWrapper } from '../../shared/TableScrollViewport';
import { TABLE_BODY_ROW_SOFT } from '../../shared/tableStyles';

const EMPTY_CRITERION = { label: '', max_marks: '' };

const INPUT_CLASS =
	'w-full rounded-md border border-border px-3 py-1.5 text-sm';
const MAX_MARKS_INPUT_CLASS =
	'w-28 rounded-md border border-border px-3 py-1.5 text-sm';

function mapCriterionRow( row ) {
	const mapped = {
		label: row.label ?? '',
		max_marks: String( row.max_marks ?? '' ),
	};
	if ( row.id != null && row.id !== '' ) {
		mapped.id = Number( row.id );
	}
	return mapped;
}

function statusVariant( status ) {
	if ( status === 'confirmed' ) {
		return 'confirmed';
	}
	if ( status === 'unlocked' ) {
		return 'unlocked';
	}
	return 'draft';
}

function TotalMarksLabel( { sum } ) {
	if ( sum == null ) {
		return (
			<span className="text-sm text-text-muted">
				Total marks:{' '}
				<span className="font-medium text-text">—</span>
			</span>
		);
	}

	return (
		<span className="text-sm text-text-muted">
			Total marks:{' '}
			<span className="font-medium text-text">{ formatMarksSum( sum ) }</span>
		</span>
	);
}

export function RubricTable( {
	review,
	onSave,
	onConfirm,
	onUnlock,
	busy = false,
	embedded = false,
} ) {
	const canConfirm =
		review.status === 'draft' || review.status === 'unlocked';
	const editable = isCriteriaEditable( review );
	const showPreMarkNotice =
		review.status === 'confirmed' &&
		! review.has_marks &&
		editable;
	const [ rows, setRows ] = useState( review.criteria ?? [] );

	useEffect( () => {
		setRows(
			review.criteria?.length
				? review.criteria.map( mapCriterionRow )
				: [ { ...EMPTY_CRITERION } ]
		);
	}, [
		review.id,
		review.criteria,
		review.status,
		review.has_marks,
		review.criteria_editable,
	] );

	const totalMarksSum = useMemo( () => {
		if ( editable ) {
			return sumCriteriaMaxMarks( rows );
		}
		return sumCriteriaMaxMarks( review.criteria ?? [] );
	}, [ editable, rows, review.criteria ] );

	const updateRow = ( index, field, value ) => {
		setRows( ( current ) =>
			current.map( ( row, i ) =>
				i === index ? { ...row, [ field ]: value } : row
			)
		);
	};

	const addRow = () => {
		if ( ! editable ) {
			return;
		}
		setRows( ( current ) => [ ...current, { ...EMPTY_CRITERION } ] );
	};

	const removeRow = ( index ) => {
		if ( ! editable || rows.length <= 1 ) {
			return;
		}
		setRows( ( current ) => current.filter( ( _, i ) => i !== index ) );
	};

	const actionButtons = (
		<div className="flex flex-wrap gap-2">
			{ editable ? (
				<Button
					variant="secondary"
					disabled={ busy }
					onClick={ () => onSave( rows ) }
				>
					Save
				</Button>
			) : null }
			{ review.status === 'confirmed' ? (
				<Button
					variant="secondary"
					disabled={ busy }
					onClick={ onUnlock }
				>
					Unlock
				</Button>
			) : null }
			{ editable && canConfirm ? (
				<Button
					disabled={ busy }
					onClick={ () => onConfirm( rows ) }
				>
					Confirm
				</Button>
			) : null }
		</div>
	);

	return (
		<Card
			className={
				embedded
					? 'space-y-3 border-0 bg-transparent p-0 shadow-none'
					: 'space-y-4'
			}
		>
			{ embedded ? (
				<div className="flex flex-wrap items-center justify-between gap-2">
					<div className="flex flex-wrap items-center gap-3">
						<h4 className="text-sm font-medium text-text-muted">
							Rubric criteria
						</h4>
						<TotalMarksLabel sum={ totalMarksSum } />
					</div>
					{ actionButtons }
				</div>
			) : (
				<div className="flex flex-wrap items-start justify-between gap-2">
					<div className="space-y-1">
						<div className="flex flex-wrap items-center gap-2">
							<h3 className="text-base font-semibold text-text">
								{ review.label }
							</h3>
							<StatusChip
								variant={ statusVariant( review.status ) }
							/>
						</div>
						<TotalMarksLabel sum={ totalMarksSum } />
					</div>
					{ actionButtons }
				</div>
			) }

			{ showPreMarkNotice ? (
				<p className="text-sm text-text-muted">
					Marking is open; criteria remain editable until a score is saved.
				</p>
			) : null }

			<TableScrollWrapper>
				<table className="w-full min-w-[28rem] border-collapse text-sm">
					<thead>
						<tr className="border-b border-border text-left text-text-muted">
							<th className="px-1 py-2 pr-3 font-medium">Criterion</th>
							<th className="px-1 py-2 pr-3 font-medium">Max marks</th>
							{ editable ? (
								<th className="px-1 py-2 w-20 font-medium">
									<span className="sr-only">Actions</span>
								</th>
							) : null }
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( row, index ) => {
							const removeLabel =
								row.label.trim() || `criterion ${ index + 1 }`;
							const canRemoveRow = editable && rows.length > 1;

							return (
								<tr
									key={ row.id ?? `criterion-${ index }` }
									className={ TABLE_BODY_ROW_SOFT }
								>
									<td className="px-1 py-2 pr-3">
										{ editable ? (
											<input
												type="text"
												className={ INPUT_CLASS }
												value={ row.label }
												onChange={ ( event ) =>
													updateRow(
														index,
														'label',
														event.target.value
													)
												}
											/>
										) : (
											row.label
										) }
									</td>
									<td className="px-1 py-2 pr-3">
										{ editable ? (
											<input
												type="text"
												inputMode="decimal"
												className={ MAX_MARKS_INPUT_CLASS }
												value={ row.max_marks }
												onChange={ ( event ) =>
													updateRow(
														index,
														'max_marks',
														event.target.value
													)
												}
											/>
										) : (
											row.max_marks
										) }
									</td>
									{ editable ? (
										<td className="px-1 py-2">
											<Button
												variant="ghost"
												size="sm"
												disabled={ busy || ! canRemoveRow }
												title={
													! canRemoveRow
														? 'At least one criterion is required.'
														: undefined
												}
												aria-label={ `Remove criterion ${ removeLabel }` }
												onClick={ () => removeRow( index ) }
											>
												Remove
											</Button>
										</td>
									) : null }
								</tr>
							);
						} ) }
					</tbody>
				</table>
			</TableScrollWrapper>

			{ editable ? (
				<Button
					variant="secondary"
					size="sm"
					icon="plus"
					disabled={ busy }
					onClick={ addRow }
				>
					Add criterion
				</Button>
			) : null }
		</Card>
	);
}
