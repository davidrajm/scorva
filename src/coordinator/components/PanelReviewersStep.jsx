import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { del, get, post, put } from '../../shared/api';
import { useDebouncedValue } from '../../shared/hooks/useDebouncedValue';
import {
	buildReviewersTemplateCsv,
	downloadCsvText,
} from '../../shared/reviewerTemplateCsv';
import { Button, Notice } from '../../shared/components';
import { TableScrollWrapper } from '../../shared/TableScrollViewport';
import { TABLE_BODY_ROW_SOFT } from '../../shared/tableStyles';
import { CsvImportMapper } from './CsvImportMapper';

const TABLE_COL_COUNT = 6;

function AccountStatus({ reviewer }) {
	if (reviewer.user_id) {
		return (
			<span className="rounded bg-chip-active-bg px-1.5 py-0.5 text-xs text-chip-active-text">
				Account linked
			</span>
		);
	}
	return (
		<span className="rounded bg-warning/15 px-1.5 py-0.5 text-xs text-warning">
			Not provisioned
		</span>
	);
}

function ReviewerTableRow({
	reviewer,
	sessionId,
	allPanels,
	onProvision,
	onResend,
	onLink,
	onSaved,
	onDeleted,
	onNotice,
	onPanelHeadChanged,
}) {
	const [editing, setEditing] = useState(false);
	const [saving, setSaving] = useState(false);
	const [deleting, setDeleting] = useState(false);
	const [name, setName] = useState(reviewer.name ?? '');
	const [email, setEmail] = useState(reviewer.email ?? '');
	const [weight, setWeight] = useState(String(reviewer.weight ?? 1));
	const [panelId, setPanelId] = useState(String(reviewer.panel_id ?? ''));
	const [userQuery, setUserQuery] = useState('');
	const [userResults, setUserResults] = useState([]);
	const debounced = useDebouncedValue(userQuery, 300);

	useEffect(() => {
		setName(reviewer.name ?? '');
		setEmail(reviewer.email ?? '');
		setWeight(String(reviewer.weight ?? 1));
		setPanelId(String(reviewer.panel_id ?? ''));
	}, [reviewer]);

	useEffect(() => {
		if (!debounced || reviewer.user_id) {
			setUserResults([]);
			return;
		}
		(async () => {
			try {
				const data = await get(
					`/users/search?q=${encodeURIComponent(debounced)}`
				);
				setUserResults(data.users ?? []);
			} catch {
				setUserResults([]);
			}
		})();
	}, [debounced, reviewer.user_id]);

	const displayName = reviewer.name?.trim() || 'Unnamed reviewer';
	const panelIdNum = Number(reviewer.panel_id);
	const showLinkRow = !reviewer.email && !reviewer.user_id && !editing;
	const canBePanelHead = Boolean( reviewer.user_id );

	const handlePanelHeadToggle = async ( event ) => {
		const checked = event.target.checked;
		if ( ! canBePanelHead ) {
			return;
		}

		try {
			const updated = await put(
				`/sessions/${ sessionId }/panels/${ panelIdNum }/reviewers/${ reviewer.id }`,
				{ is_panel_head: checked }
			);
			onPanelHeadChanged( updated, panelIdNum );
			onNotice( {
				variant: 'success',
				message: checked
					? 'Panel coordinator updated.'
					: 'Panel coordinator cleared.',
			} );
		} catch ( err ) {
			const code = err?.code || err?.data?.code;
			onNotice( {
				variant: 'error',
				message:
					code === 'panel_head_requires_account'
						? 'Provision or link an account first.'
						: 'Could not update panel coordinator.',
			} );
		}
	};

	const handleSave = async () => {
		const trimmedName = name.trim();
		const trimmedEmail = email.trim();
		if (!trimmedName && !trimmedEmail) {
			onNotice({
				variant: 'error',
				message: 'Enter a reviewer name or email.',
			});
			return;
		}

		const targetPanelId = Number(panelId);
		if (!targetPanelId) {
			onNotice({ variant: 'error', message: 'Select a panel.' });
			return;
		}

		setSaving(true);
		try {
			const updated = await put(
				`/sessions/${sessionId}/panels/${panelIdNum}/reviewers/${reviewer.id}`,
				{
					name: trimmedName,
					email: trimmedEmail,
					weight: weight || 1,
					panel_id: targetPanelId,
				}
			);
			onSaved(updated);
			setEditing(false);
			onNotice({
				variant: 'success',
				message:
					targetPanelId !== panelIdNum
						? 'Reviewer updated and moved to another panel.'
						: 'Reviewer updated.',
			});
		} catch {
			onNotice({ variant: 'error', message: 'Could not save reviewer.' });
		} finally {
			setSaving(false);
		}
	};

	const handleDelete = async () => {
		if (
			!window.confirm(
				`Remove ${displayName} from this project? This cannot be undone.`
			)
		) {
			return;
		}

		setDeleting(true);
		try {
			await del(
				`/sessions/${sessionId}/panels/${panelIdNum}/reviewers/${reviewer.id}`
			);
			onDeleted(reviewer.id);
			onNotice({ variant: 'success', message: 'Reviewer removed.' });
		} catch {
			onNotice({ variant: 'error', message: 'Could not remove reviewer.' });
		} finally {
			setDeleting(false);
		}
	};

	return (
		<>
			<tr className={ TABLE_BODY_ROW_SOFT }>
				<td className="px-4 py-3 font-medium text-text">{displayName}</td>
				<td className="px-4 py-3 text-text">
					{reviewer.email ? (
						reviewer.email
					) : (
						<span className="text-text-muted">—</span>
					)}
				</td>
				<td className="px-4 py-3 tabular-nums text-text">
					{reviewer.weight ?? 1}
				</td>
				<td className="px-4 py-3">
					<AccountStatus reviewer={reviewer} />
				</td>
				<td className="px-4 py-3">
					<label
						className="inline-flex items-center gap-2"
						title={
							canBePanelHead
								? 'Designate as panel coordinator'
								: 'Provision or link an account first.'
						}
					>
						<input
							type="checkbox"
							name={ `panel-coordinator-${ panelIdNum }` }
							className="h-4 w-4 rounded border-border text-primary focus:ring-primary"
							checked={ Boolean( reviewer.is_panel_head ) }
							disabled={ ! canBePanelHead }
							onChange={ handlePanelHeadToggle }
							aria-label={ `Panel coordinator for ${ displayName }` }
						/>
					</label>
				</td>
				<td className="px-4 py-3">
					<div className="flex flex-wrap gap-2">
						<Button
							size="sm"
							variant="ghost"
							onClick={() => setEditing(true)}
						>
							Edit
						</Button>
						<Button
							size="sm"
							variant="ghost"
							loading={deleting}
							onClick={handleDelete}
						>
							Remove
						</Button>
						{reviewer.email && !reviewer.user_id ? (
							<Button size="sm" variant="primary" onClick={onProvision}>
								Send credentials
							</Button>
						) : null}
						{reviewer.user_id ? (
							<Button size="sm" variant="secondary" onClick={onResend}>
								Resend credentials
							</Button>
						) : null}
					</div>
				</td>
			</tr>
			{editing ? (
				<tr className="border-b border-border/60 bg-surface">
					<td colSpan={TABLE_COL_COUNT} className="px-4 py-3">
						<div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
							<div>
								<label
									className="block text-xs font-medium text-text"
									htmlFor={`edit-name-${reviewer.id}`}
								>
									Name
								</label>
								<input
									id={`edit-name-${reviewer.id}`}
									type="text"
									value={name}
									onChange={(e) => setName(e.target.value)}
									className="mt-1 w-full rounded-md border border-border bg-surface px-2 py-1"
								/>
							</div>
							<div>
								<label
									className="block text-xs font-medium text-text"
									htmlFor={`edit-email-${reviewer.id}`}
								>
									Email
								</label>
								<input
									id={`edit-email-${reviewer.id}`}
									type="email"
									value={email}
									onChange={(e) => setEmail(e.target.value)}
									className="mt-1 w-full rounded-md border border-border bg-surface px-2 py-1"
								/>
							</div>
							<div>
								<label
									className="block text-xs font-medium text-text"
									htmlFor={`edit-weight-${reviewer.id}`}
								>
									Weight
								</label>
								<input
									id={`edit-weight-${reviewer.id}`}
									type="number"
									min="0"
									step="0.1"
									value={weight}
									onChange={(e) => setWeight(e.target.value)}
									className="mt-1 w-full rounded-md border border-border bg-surface px-2 py-1"
								/>
							</div>
							<div>
								<label
									className="block text-xs font-medium text-text"
									htmlFor={`edit-panel-${reviewer.id}`}
								>
									Panel
								</label>
								<select
									id={`edit-panel-${reviewer.id}`}
									value={panelId}
									onChange={(e) => setPanelId(e.target.value)}
									className="mt-1 w-full rounded-md border border-border bg-surface px-2 py-1"
								>
									{allPanels.map((panel) => (
										<option key={panel.id} value={panel.id}>
											{panel.name}
										</option>
									))}
								</select>
							</div>
						</div>
						<div className="mt-3 flex flex-wrap gap-2">
							<Button
								size="sm"
								variant="primary"
								loading={saving}
								onClick={handleSave}
							>
								Save
							</Button>
							<Button
								size="sm"
								variant="secondary"
								onClick={() => setEditing(false)}
								disabled={saving}
							>
								Cancel
							</Button>
						</div>
					</td>
				</tr>
			) : null}
			{showLinkRow ? (
				<tr className="border-b border-border/60 bg-surface">
					<td colSpan={TABLE_COL_COUNT} className="px-4 py-3">
						<label
							className="block text-xs font-medium text-text"
							htmlFor={`link-user-${reviewer.id}`}
						>
							Link WordPress user
						</label>
						<input
							id={`link-user-${reviewer.id}`}
							type="search"
							value={userQuery}
							onChange={(e) => setUserQuery(e.target.value)}
							placeholder="Search by name or email"
							className="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-2 py-1 text-sm"
						/>
						{userResults.map((user) => (
							<button
								key={user.id}
								type="button"
								className="mt-1 block w-full max-w-md rounded-md px-2 py-1 text-left text-sm hover:bg-surface-raised"
								onClick={() => onLink(user.id)}
							>
								{user.display_name} ({user.email})
							</button>
						))}
					</td>
				</tr>
			) : null}
		</>
	);
}

function AddReviewerForm({
	sessionId,
	sessionPanels,
	setReviewers,
	mergePanelReviewers,
	onRefreshReviewers,
}) {
	const defaultPanelId =
		sessionPanels.length === 1 ? String(sessionPanels[0].id) : '';
	const [selectedPanelId, setSelectedPanelId] = useState(defaultPanelId);
	const [name, setName] = useState('');
	const [email, setEmail] = useState('');
	const [weight, setWeight] = useState('1');
	const [submitting, setSubmitting] = useState(false);
	const [formNotice, setFormNotice] = useState(null);

	useEffect(() => {
		if (sessionPanels.length === 1) {
			setSelectedPanelId(String(sessionPanels[0].id));
		}
	}, [sessionPanels]);

	const resetForm = () => {
		setName('');
		setEmail('');
		setWeight('1');
		if (sessionPanels.length !== 1) {
			setSelectedPanelId('');
		}
	};

	const handleSubmit = async (event) => {
		event.preventDefault();
		setFormNotice(null);

		const trimmedName = name.trim();
		const trimmedEmail = email.trim();
		if (!trimmedName && !trimmedEmail) {
			setFormNotice({
				variant: 'error',
				message: 'Enter a reviewer name or email.',
			});
			return;
		}

		const panelId = Number(selectedPanelId);
		if (!panelId) {
			setFormNotice({
				variant: 'error',
				message: 'Select a panel.',
			});
			return;
		}

		const panel = sessionPanels.find((p) => Number(p.id) === panelId);
		if (!panel) {
			setFormNotice({
				variant: 'error',
				message: 'Select a panel.',
			});
			return;
		}

		setSubmitting(true);
		try {
			const created = await post(
				`/sessions/${sessionId}/panels/${panelId}/reviewers`,
				{
					name: trimmedName,
					email: trimmedEmail,
					weight: weight || 1,
				}
			);
			const createdId = Number(created?.id);
			if (createdId > 0) {
				setReviewers((prev) => [
					...prev.filter((row) => Number(row.id) !== createdId),
					{
						...created,
						id: createdId,
						panel_id: panelId,
						panel_name: panel.name,
					},
				]);
			}

			try {
				const panelData = await get(
					`/sessions/${sessionId}/panels/${panelId}/reviewers`
				);
				mergePanelReviewers(panel, panelData.reviewers ?? []);
			} catch {
				if (createdId > 0) {
					await onRefreshReviewers?.();
				}
			}

			resetForm();
			const label = created.name || trimmedEmail;
			setFormNotice({
				variant: 'success',
				message: `${label} added to ${panel.name}.`,
			});
		} catch {
			setFormNotice({
				variant: 'error',
				message: 'Could not add reviewer.',
			});
		} finally {
			setSubmitting(false);
		}
	};

	return (
		<div className="mt-6 rounded-md border border-border bg-surface-raised p-4 shadow-card">
			<h3 className="text-sm font-semibold text-text">Add reviewer</h3>
			<form
				className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5"
				onSubmit={handleSubmit}
			>
				<div>
					<label
						className="block text-sm font-medium text-text"
						htmlFor="add-reviewer-panel"
					>
						Panel
					</label>
					<select
						id="add-reviewer-panel"
						value={selectedPanelId}
						onChange={(e) => setSelectedPanelId(e.target.value)}
						required={sessionPanels.length > 1}
						className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
					>
						{sessionPanels.length > 1 ? (
							<option value="">Select panel…</option>
						) : null}
						{sessionPanels.map((panel) => (
							<option key={panel.id} value={panel.id}>
								{panel.name}
							</option>
						))}
					</select>
				</div>
				<div>
					<label
						className="block text-sm font-medium text-text"
						htmlFor="add-reviewer-name"
					>
						Reviewer name
					</label>
					<input
						id="add-reviewer-name"
						type="text"
						autoComplete="name"
						value={name}
						onChange={(e) => setName(e.target.value)}
						className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
						placeholder="Full name"
					/>
				</div>
				<div>
					<label
						className="block text-sm font-medium text-text"
						htmlFor="add-reviewer-email"
					>
						Email
					</label>
					<input
						id="add-reviewer-email"
						type="email"
						autoComplete="email"
						value={email}
						onChange={(e) => setEmail(e.target.value)}
						className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
						placeholder="reviewer@example.com"
					/>
				</div>
				<div>
					<label
						className="block text-sm font-medium text-text"
						htmlFor="add-reviewer-weight"
					>
						Weight
					</label>
					<input
						id="add-reviewer-weight"
						type="number"
						min="0"
						step="0.1"
						value={weight}
						onChange={(e) => setWeight(e.target.value)}
						className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
					/>
				</div>
				<div className="flex items-end">
					<Button
						variant="primary"
						type="submit"
						size="sm"
						loading={submitting}
						className="w-full sm:w-auto"
					>
						Add reviewer
					</Button>
				</div>
			</form>

			{formNotice ? (
				<div className="mt-3">
					<Notice
						variant={formNotice.variant}
						onDismiss={() => setFormNotice(null)}
					>
						{formNotice.message}
					</Notice>
				</div>
			) : null}
		</div>
	);
}

function PanelReviewerTable({
	sessionId,
	panel,
	panelIndex,
	reviewers,
	allPanels,
	setReviewers,
	onProvision,
	onResend,
	onLink,
	onNotice,
}) {
	const panelId = Number(panel.id);
	const panelReviewers = reviewers.filter(
		(row) => Number(row.panel_id) === panelId
	);

	const handleReviewerSaved = (updated) => {
		const updatedId = Number(updated?.id);
		if (updatedId <= 0) {
			return;
		}
		setReviewers((prev) => {
			const without = prev.filter((row) => Number(row.id) !== updatedId);
			return [
				...without,
				{
					...updated,
					id: updatedId,
					panel_id: Number(updated.panel_id),
					panel_name: updated.panel_name ?? panel.name,
				},
			];
		});
	};

	const handleReviewerDeleted = (reviewerId) => {
		setReviewers((prev) =>
			prev.filter((row) => Number(row.id) !== Number(reviewerId))
		);
	};

	const handlePanelHeadChanged = (updated, panelIdForRow) => {
		const updatedId = Number(updated?.id);
		if (updatedId <= 0) {
			return;
		}
		const isHead = Boolean(updated.is_panel_head);
		setReviewers((prev) =>
			prev.map((row) => {
				if (Number(row.panel_id) !== Number(panelIdForRow)) {
					return row;
				}
				if (Number(row.id) === updatedId) {
					return {
						...row,
						...updated,
						is_panel_head: isHead,
					};
				}
				if (isHead) {
					return { ...row, is_panel_head: false };
				}

				return row;
			})
		);
	};

	return (
		<div className="mt-6">
			<div className="flex flex-wrap items-baseline justify-between gap-2">
				<h3 className="font-semibold text-text">
					Panel {panelIndex + 1}: {panel.name}
				</h3>
				<span className="text-sm text-text-muted">
					{panelReviewers.length} reviewer
					{panelReviewers.length === 1 ? '' : 's'}
					{panel.student_count != null ? (
						<span className="ml-2">
							· {panel.student_count} student
							{panel.student_count === 1 ? '' : 's'}
						</span>
					) : null}
				</span>
			</div>

			{panelReviewers.length === 0 ? (
				<p className="mt-4 rounded-md border border-dashed border-border bg-surface px-3 py-3 text-sm text-text-muted">
					No reviewers for this panel yet. Use the add form above or import
					from CSV.
				</p>
			) : (
				<TableScrollWrapper
					className="mt-4 bg-surface-raised shadow-card"
					aria-live="polite"
				>
					<table className="min-w-full text-left text-sm">
						<thead>
							<tr className="border-b border-border bg-surface text-text-muted">
								<th className="px-4 py-3 font-medium">Name</th>
								<th className="px-4 py-3 font-medium">Email</th>
								<th className="px-4 py-3 font-medium">Weight</th>
								<th className="px-4 py-3 font-medium">Account</th>
								<th
									className="px-4 py-3 font-medium"
									title="One panel coordinator per panel; they can view panel scores and sign off."
								>
									Panel coordinator
								</th>
								<th className="px-4 py-3 font-medium">Actions</th>
							</tr>
						</thead>
						<tbody>
							{panelReviewers.map((row) => (
								<ReviewerTableRow
									key={row.id}
									reviewer={row}
									sessionId={sessionId}
									allPanels={allPanels}
									onProvision={() => onProvision(row.id)}
									onResend={() => onResend(row.id)}
									onLink={(userId) => onLink(row.id, userId)}
									onSaved={handleReviewerSaved}
									onDeleted={handleReviewerDeleted}
									onNotice={onNotice}
									onPanelHeadChanged={handlePanelHeadChanged}
								/>
							))}
						</tbody>
					</table>
				</TableScrollWrapper>
			)}
		</div>
	);
}

export function PanelReviewersStep({
	sessionId,
	panels,
	reviewers,
	setReviewers,
	onNotice,
	onRefreshReviewers,
	onReload,
}) {
	const mergePanelReviewers = (panel, panelRows) => {
		const panelId = Number(panel.id);
		const normalized = panelRows.map((row) => ({
			...row,
			id: Number(row.id),
			panel_id: panelId,
			panel_name: panel.name,
		}));
		setReviewers((prev) => [
			...prev.filter((row) => Number(row.panel_id) !== panelId),
			...normalized,
		]);
	};

	const sessionPanels = useMemo(() => {
		return panels
			.filter(
				(panel) =>
					Number(panel.session_id) === sessionId ||
					panel.session_id == null
			)
			.sort((a, b) =>
				String(a.name).localeCompare(String(b.name), undefined, {
					numeric: true,
				})
			);
	}, [panels, sessionId]);

	const downloadTemplate = useCallback(() => {
		const csv = buildReviewersTemplateCsv(sessionPanels, reviewers);
		downloadCsvText(csv, `session-${sessionId}-reviewers.csv`);
	}, [sessionPanels, reviewers, sessionId]);

	if (sessionPanels.length === 0) {
		return (
			<section>
				<h2 className="text-lg font-semibold text-text">Reviewers</h2>
				<p className="mt-2 text-sm text-warning">
					No panels in this project yet. Go back to the Panels step and create
					at least one panel first.
				</p>
			</section>
		);
	}

	return (
		<section>
			<h2 className="text-lg font-semibold text-text">Reviewers</h2>
			<p className="mt-1 text-sm text-text-muted">
				Add reviewers to a panel, download a roster template prefilled with your
				panels and existing reviewers, or import updates from CSV. Edit, remove,
				or move reviewers between panels in the tables below.
			</p>

			<AddReviewerForm
				sessionId={sessionId}
				sessionPanels={sessionPanels}
				setReviewers={setReviewers}
				mergePanelReviewers={mergePanelReviewers}
				onRefreshReviewers={onRefreshReviewers}
			/>

			<div className="mt-6">
				<CsvImportMapper
					importType="panel-reviewers"
					sessionId={sessionId}
					existingReviewerCount={reviewers.length}
					onComplete={onRefreshReviewers ?? onReload}
					onDownloadTemplate={downloadTemplate}
					templateDownloadLabel="Download roster template CSV"
				/>
			</div>

			{sessionPanels.map((panel, index) => (
				<PanelReviewerTable
					key={panel.id}
					sessionId={sessionId}
					panel={panel}
					panelIndex={index}
					reviewers={reviewers}
					allPanels={sessionPanels}
					setReviewers={setReviewers}
					onNotice={onNotice}
					onProvision={async (reviewerId) => {
						try {
							await post(
								`/sessions/${sessionId}/reviewers/${reviewerId}/provision`
							);
							onNotice({
								variant: 'success',
								message: 'Credentials sent to reviewer.',
							});
							await onRefreshReviewers?.();
						} catch {
							onNotice({
								variant: 'error',
								message: 'Provisioning failed.',
							});
						}
					}}
					onResend={async (reviewerId) => {
						try {
							await post(
								`/sessions/${sessionId}/reviewers/${reviewerId}/resend-credentials`
							);
							onNotice({
								variant: 'success',
								message: 'Invite email resent.',
							});
						} catch {
							onNotice({
								variant: 'error',
								message: 'Could not resend credentials.',
							});
						}
					}}
					onLink={async (reviewerId, userId) => {
						try {
							await post(
								`/sessions/${sessionId}/reviewers/${reviewerId}/link-user`,
								{ user_id: userId }
							);
							onNotice({
								variant: 'success',
								message: 'Reviewer linked to user.',
							});
							await onRefreshReviewers?.();
						} catch {
							onNotice({
								variant: 'error',
								message: 'Could not link user.',
							});
						}
					}}
				/>
			))}
		</section>
	);
}
