import {
	Button,
	Card,
	FlaggedMarkChip,
	ShuttleMarkChip,
	StatusChip,
} from '../../shared/components';
import {
	attendanceStatusChip,
	coordinatorOverriddenForCriterion,
	flaggedForCriterion,
	formatScore,
	overriddenFromScoreForCriterion,
	isStudentRowFrozen,
	scoreForCriterion,
	studentStatusChip,
} from './markingGridUtils';

export function MarkingGridStudentCard( {
	rowIndex,
	student,
	criteria,
	reviewFrozen,
	onUpdateScore,
} ) {
	const chip = studentStatusChip( student.mark_status );
	const attendanceChip = attendanceStatusChip( student.attendance_status );
	const isAbsent = student.attendance_status === 'absent';
	const rowFrozen = isStudentRowFrozen( reviewFrozen, student );

	return (
		<Card className="p-3 transition-shadow hover:shadow-md">
			<div className="flex items-start gap-2">
				<div className="min-w-0 flex-1">
					<h3 className="text-sm font-semibold leading-snug text-text">
						<span className="tabular-nums text-text-muted">
							{ rowIndex + 1 }.
						</span>{ ' ' }
						{ student.name }
						<span className="font-normal tabular-nums text-muted">
							{ ' ' }
							({ student.reg_no })
						</span>
					</h3>
					<div className="mt-1.5 flex flex-wrap items-center gap-1.5">
						<StatusChip
							variant={ attendanceChip.variant }
							label={ attendanceChip.label }
						/>
						<StatusChip
							variant={ chip.variant }
							label={ chip.label }
							icon={ chip.icon }
						/>
					</div>
				</div>
				<Button
					type="button"
					variant="secondary"
					size="sm"
					icon="pencil"
					disabled={ rowFrozen }
					className="h-8 w-8 shrink-0 p-0"
					aria-label={ `Update score for ${ student.name }` }
					onClick={ () => onUpdateScore( student ) }
				>
					<span className="sr-only">Update score</span>
				</Button>
			</div>

			{ criteria.length > 0 ? (
				<div
					className="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-border pt-2 text-sm"
					role="group"
					aria-label="Rubric scores"
				>
					{ criteria.map( ( c, criterionIndex ) => {
						const score = isAbsent
							? null
							: scoreForCriterion( student, c.id );
						const isShuttle = coordinatorOverriddenForCriterion(
							student,
							c.id
						);
						const isFlagged = flaggedForCriterion( student, c.id );
						const rubricRef = `R${ criterionIndex + 1 }`;

						return (
							<span
								key={ c.id }
								className="inline-flex flex-wrap items-center gap-1 tabular-nums"
								title={ c.label }
							>
								<span className="text-xs font-medium text-muted">
									{ rubricRef }
								</span>
								<span className="text-text">
									{ formatScore( score ) }
								</span>
								{ isShuttle ? (
									<ShuttleMarkChip
										fromScore={ overriddenFromScoreForCriterion(
											student,
											c.id
										) }
										score={ score }
									/>
								) : null }
								{ isFlagged ? <FlaggedMarkChip /> : null }
							</span>
						);
					} ) }
				</div>
			) : null }
		</Card>
	);
}
