import { useCallback, useEffect, useState } from '@wordpress/element';
import { useParams } from 'react-router-dom';
import {
	Button,
	ContentLoadingRegion,
	Notice,
	PageContentSkeleton,
	PageHeader,
} from '../../shared/components';
import { useLoadingPhase } from '../../shared/hooks/useLoadingPhase';
import { get } from '../../shared/api';
import {
	MarkStatusCounts,
	ProgressAccordion,
} from '../components/ProgressAccordion';
import { ProgressTable } from '../components/ProgressTable';
import { ReviewProgressSummary } from '../components/ReviewProgressSummary';
import { ScoreBreakdown } from '../components/ScoreBreakdown';

function panelKey(reviewId, panelId) {
	return `${reviewId}-${panelId}`;
}

function formatReviewMeta(summary) {
	const percent = summary?.percent ?? 0;
	const completed = summary?.marks_completed ?? 0;
	const total = summary?.marks_total ?? 0;
	const inProgress = summary?.marks_in_progress ?? 0;
	const notStarted = summary?.marks_not_started ?? 0;

	return `${percent}% · ${completed}/${total} marks · ${inProgress} in progress · ${notStarted} not started`;
}

export function SessionProgress() {
	const { id: sessionId } = useParams();
	const [reviews, setReviews] = useState([]);
	const [students, setStudents] = useState([]);
	const [selectedStudentId, setSelectedStudentId] = useState(null);
	const [breakdown, setBreakdown] = useState(null);
	const [loading, setLoading] = useState(true);
	const [breakdownLoading, setBreakdownLoading] = useState(false);
	const [error, setError] = useState(null);
	const [openReviews, setOpenReviews] = useState(() => new Set());
	const [openPanels, setOpenPanels] = useState(() => new Set());

	const loadProgress = useCallback(async () => {
		setLoading(true);
		setError(null);
		try {
			const [progressRes, studentsRes] = await Promise.all([
				get(`/sessions/${sessionId}/progress`),
				get(`/sessions/${sessionId}/students`),
			]);
			setReviews(progressRes.reviews || []);
			setStudents(studentsRes.students || []);
			setOpenReviews(new Set());
			setOpenPanels(new Set());
		} catch (err) {
			setError(err?.message || 'Unable to load progress.');
		} finally {
			setLoading(false);
		}
	}, [sessionId]);

	useEffect(() => {
		loadProgress();
	}, [loadProgress]);

	useEffect(() => {
		if (students.length === 0) {
			setSelectedStudentId(null);
			return;
		}
		const enrolledIds = students
			.map((row) => row.student?.id)
			.filter((id) => id != null);
		if (
			selectedStudentId != null &&
			enrolledIds.includes(selectedStudentId)
		) {
			return;
		}
		setSelectedStudentId(enrolledIds[0] ?? null);
	}, [students, selectedStudentId]);

	useEffect(() => {
		if (!selectedStudentId) {
			setBreakdown(null);
			return;
		}

		let cancelled = false;
		(async () => {
			setBreakdownLoading(true);
			try {
				const res = await get(
					`/sessions/${sessionId}/students/${selectedStudentId}/scores`
				);
				if (!cancelled) {
					setBreakdown(res);
				}
			} catch {
				if (!cancelled) {
					setBreakdown(null);
				}
			} finally {
				if (!cancelled) {
					setBreakdownLoading(false);
				}
			}
		})();

		return () => {
			cancelled = true;
		};
	}, [sessionId, selectedStudentId]);

	const expandReviews = () => {
		setOpenReviews(new Set(reviews.map((r) => r.review_id)));
		setOpenPanels(new Set());
	};

	const expandAll = () => {
		const reviewIds = new Set(reviews.map((r) => r.review_id));
		const panelKeys = new Set();
		reviews.forEach((review) => {
			(review.panels || []).forEach((panel) => {
				panelKeys.add(panelKey(review.review_id, panel.panel_id));
			});
		});
		setOpenReviews(reviewIds);
		setOpenPanels(panelKeys);
	};

	const collapseAll = () => {
		setOpenReviews(new Set());
		setOpenPanels(new Set());
	};

	const toggleReview = (reviewId, nextOpen) => {
		setOpenReviews((prev) => {
			const next = new Set(prev);
			if (nextOpen) {
				next.add(reviewId);
			} else {
				next.delete(reviewId);
			}
			return next;
		});
	};

	const { showSkeleton, showOverlay } = useLoadingPhase(
		loading,
		reviews.length > 0 || students.length > 0
	);

	const togglePanel = (key, nextOpen) => {
		setOpenPanels((prev) => {
			const next = new Set(prev);
			if (nextOpen) {
				next.add(key);
			} else {
				next.delete(key);
			}
			return next;
		});
	};

	return (
		<>
			<PageHeader
				title="Marking progress"
				description="Track reviewer–student marking obligations (each assigned reviewer on each student counts toward progress). Present students need every criterion scored; absent students count complete when attendance is recorded. Percentages are calculated on the server."
			/>

			{error ? (
				<div className="mb-4">
					<Notice variant="error">{error}</Notice>
				</div>
			) : null}

			<ContentLoadingRegion
				busy={ showOverlay }
				variant="overlay"
				label="Loading progress"
			>
			{showSkeleton ? (
				<PageContentSkeleton rows={ 5 } />
			) : (
				<>
					<section className="mb-10 border border-border rounded-md p-4">
						<h2 className="mb-4 text-lg font-semibold text-text">
							Overall Progress
						</h2>
						<ReviewProgressSummary reviews={reviews} />
					</section>

					{reviews.length ? (
						<div className="mb-6 flex flex-wrap gap-2">
							<Button
								variant="secondary"
								size="sm"
								onClick={expandReviews}
							>
								Expand reviews
							</Button>
							<Button variant="secondary" size="sm" onClick={expandAll}>
								Expand all
							</Button>
							<Button variant="ghost" size="sm" onClick={collapseAll}>
								Collapse all
							</Button>
						</div>
					) : null}

					{reviews.map((review) => {
						const summary = review.summary || {};
						const reviewOpen = openReviews.has(review.review_id);

						return (
							<ProgressAccordion
								key={review.review_id}
								id={`review-${review.review_id}`}
								open={reviewOpen}
								onToggle={(next) =>
									toggleReview(review.review_id, next)
								}
								title={review.review_label}
								meta={formatReviewMeta(summary)}
								summary={summary}
								headingLevel={2}
							>
								{summary.students_total > 0 ? (
									<p className="mb-4 text-sm tabular-nums text-muted">
										{summary.students_completed}/
										{summary.students_total} students fully
										marked by all reviewers
									</p>
								) : null}
								<MarkStatusCounts
									summary={summary}
									className="mb-4 block"
								/>
								{review.panels?.length ? (
									review.panels.map((panel) => {
										const pSummary = panel.summary || {};
										const key = panelKey(
											review.review_id,
											panel.panel_id
										);

										return (
											<ProgressAccordion
												key={panel.panel_id}
												id={key}
												open={openPanels.has(key)}
												onToggle={(next) =>
													togglePanel(key, next)
												}
												title={panel.panel_name}
												meta={`${pSummary.percent ?? 0}% · ${pSummary.marks_completed ?? 0}/${pSummary.marks_total ?? 0} marks · ${pSummary.marks_in_progress ?? 0} in progress · ${pSummary.marks_not_started ?? 0} not started · ${panel.students_total} student${panel.students_total === 1 ? '' : 's'} assigned`}
												summary={pSummary}
												headingLevel={3}
											>
												<ProgressTable
													rows={panel.rows}
													showPanelColumn={false}
													emptyMessage="No reviewers assigned to this panel for this review."
												/>
											</ProgressAccordion>
										);
									})
								) : (
									<ProgressTable
										rows={review.rows}
										emptyMessage="No panel assignments with enrolled students for this review."
									/>
								)}
							</ProgressAccordion>
						);
					})}

					{!reviews.length ? (
						<p className="text-sm text-muted">
							No confirmed reviews with rubric criteria yet.
						</p>
					) : null}
				</>
			)}
			</ContentLoadingRegion>

			<section className="mt-10">
				<h2 className="mb-4 text-lg font-semibold text-text">
					Score breakdown
				</h2>
				{students.length === 0 ? (
					<p className="mb-4 text-sm text-muted">
						Enrol students in the project wizard before viewing score
						breakdown.
					</p>
				) : (
					<label className="mb-4 block max-w-md text-sm">
						<span className="font-medium text-text">Student</span>
						<select
							className="mt-1 block w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
							value={selectedStudentId ?? ''}
							onChange={(e) =>
								setSelectedStudentId(
									e.target.value ? Number(e.target.value) : null
								)
							}
						>
							<option value="">Select a student…</option>
							{students.map((row) => {
								const student = row.student;
								if (!student?.id) {
									return null;
								}
								const label =
									student.name ||
									student.reg_no ||
									`Student #${student.id}`;
								const panelSuffix = row.panel_name
									? ` (${row.panel_name})`
									: '';

								return (
									<option key={student.id} value={student.id}>
										{label}
										{panelSuffix}
									</option>
								);
							})}
						</select>
					</label>
				)}
				<ScoreBreakdown
					breakdown={breakdown}
					loading={breakdownLoading}
				/>
			</section>
		</>
	);
}
