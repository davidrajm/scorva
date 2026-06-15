import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { createPortal } from 'react-dom';
import { del, get, post, put } from '../../shared/api';
import {
	buildReviewersTemplateCsv,
	downloadCsvText,
} from '../../shared/reviewerTemplateCsv';
import { Button, ConfirmDialog, Notice, useToast } from '../../shared/components';
import { getDialogPortalRoot } from '../../shared/components/ConfirmDialog';
import { Icon } from '../../shared/components/NavIcon';
import { TableScrollWrapper } from '../../shared/TableScrollViewport';
import { TABLE_BODY_ROW_SOFT } from '../../shared/tableStyles';
import { CsvImportMapper } from './CsvImportMapper';

const TABLE_COL_COUNT = 6;

function AccountStatus({ reviewer }) {
	if (reviewer.has_credentials && reviewer.credentials_sent_at) {
		const raw = String(reviewer.credentials_sent_at);
		const normalized = /[Z+]/.test(raw.slice(10)) ? raw : raw.replace(' ', 'T') + 'Z';
		const dt = new Date(normalized);
		const day = dt.getDate();
		const month = dt.toLocaleString('en', { month: 'long' });
		const year = dt.getFullYear();
		const timeStr = dt.toLocaleTimeString('en', { hour: '2-digit', minute: '2-digit', timeZoneName: 'short' });
		const sentDate = `${day} ${month}, ${year}, ${timeStr}`;
		return (
			<span
				className="rounded bg-chip-active-bg px-1.5 py-0.5 text-xs text-chip-active-text"
				title={`Credentials emailed ${reviewer.credentials_sent_at}`}
			>
				Sent {sentDate}
			</span>
		);
	}
	if (reviewer.has_credentials) {
		return (
			<span className="rounded bg-warning/15 px-1.5 py-0.5 text-xs text-warning">
				Generated, not delivered
			</span>
		);
	}
	return (
		<span className="rounded bg-warning/15 px-1.5 py-0.5 text-xs text-warning">
			No credentials
		</span>
	);
}

function CopyField({ value, label, autoFocusRef }) {
	const ownRef = useRef(null);
	const inputRef = autoFocusRef ?? ownRef;
	const [copied, setCopied] = useState(false);

	const handleCopy = () => {
		if (!value) {
			return;
		}
		if (navigator.clipboard) {
			navigator.clipboard.writeText(value).then(() => {
				setCopied(true);
				setTimeout(() => setCopied(false), 2000);
			});
		} else {
			inputRef.current?.select();
			document.execCommand('copy');
			setCopied(true);
			setTimeout(() => setCopied(false), 2000);
		}
	};

	return (
		<div>
			{label && (
				<p className="mb-1 text-xs font-medium text-text-muted">{label}</p>
			)}
			<div className="flex gap-2">
				<input
					ref={inputRef}
					type="text"
					readOnly
					value={value ?? ''}
					className="min-w-0 flex-1 rounded-md border border-border bg-surface px-3 py-2 text-sm font-mono text-text"
					onFocus={(e) => e.target.select()}
					aria-label={label}
				/>
				<Button
					size="sm"
					variant={copied ? 'secondary' : 'primary'}
					onClick={handleCopy}
				>
					{copied ? 'Copied!' : 'Copy'}
				</Button>
			</div>
		</div>
	);
}

function PortalLinkModal({
	open,
	reviewerName,
	portalUrl,
	portalPassword,
	sessionId,
	reviewerId,
	onClose,
	onCredentialsSent,
}) {
	const toast = useToast();
	const dialogRef = useRef(null);
	const firstInputRef = useRef(null);
	const [sending, setSending] = useState(false);

	useEffect(() => {
		if (!open || !dialogRef.current) {
			return;
		}
		firstInputRef.current?.focus();

		const onKeyDown = (e) => {
			if (e.key === 'Escape') {
				onClose();
			}
		};
		document.addEventListener('keydown', onKeyDown);
		return () => document.removeEventListener('keydown', onKeyDown);
	}, [open, onClose]);

	const handleSend = async () => {
		setSending(true);
		try {
			const result = await post(
				`/sessions/${sessionId}/reviewers/${reviewerId}/resend-credentials`
			);
			if (result?.email_sent) {
				toast({ variant: 'success', message: 'Credentials emailed to reviewer.' });
				onCredentialsSent?.(result);
			} else {
				toast({
					variant: 'error',
					message: 'Email could not be sent. Check the SMTP settings.',
				});
			}
		} catch {
			toast({ variant: 'error', message: 'Could not send credentials.' });
		} finally {
			setSending(false);
		}
	};

	if (!open) {
		return null;
	}

	const dialog = (
		<div className="fixed inset-0 z-[150] flex items-center justify-center bg-black/40 p-4">
			<div
				ref={dialogRef}
				role="dialog"
				aria-modal="true"
				aria-labelledby="pr-portal-link-title"
				className="w-full max-w-lg rounded-md border border-border bg-surface-raised p-6 shadow-card"
			>
				<h2
					id="pr-portal-link-title"
					className="text-lg font-semibold text-text"
				>
					Reviewer credentials
				</h2>
				{reviewerName && (
					<p className="mt-1 text-sm text-text-muted">{reviewerName}</p>
				)}
				<div className="mt-4 space-y-3">
					<CopyField
						autoFocusRef={firstInputRef}
						label="Login URL"
						value={portalUrl}
					/>
					{portalPassword ? (
						<CopyField label="Password" value={portalPassword} />
					) : (
						<p className="text-xs text-text-muted">
							Password not available — use "Regenerate" to create a new
							one.
						</p>
					)}
				</div>
				<div className="mt-6 flex items-center justify-between gap-2">
					<Button
						variant="primary"
						size="sm"
						loading={sending}
						disabled={!portalPassword}
						onClick={handleSend}
					>
						Send credentials
					</Button>
					<Button variant="secondary" onClick={onClose}>
						Close
					</Button>
				</div>
			</div>
		</div>
	);

	if (typeof document === 'undefined') {
		return dialog;
	}

	return createPortal(dialog, getDialogPortalRoot());
}

function ReviewerTableRow({
	reviewer,
	sessionId,
	allPanels,
	onProvision,
	onSaved,
	onDeleted,
	onPanelHeadChanged,
	onReviewerUpdated,
}) {
	const toast = useToast();
	const [editing, setEditing] = useState(false);
	const [saving, setSaving] = useState(false);
	const [deleting, setDeleting] = useState(false);
	const [provisioning, setProvisioning] = useState(false);
	const [resending, setResending] = useState(false);
	const [regenerating, setRegenerating] = useState(false);
	const [regenerateConfirmOpen, setRegenerateConfirmOpen] = useState(false);
	const [portalLinkOpen, setPortalLinkOpen] = useState(false);
	const [localCreds, setLocalCreds] = useState({
		portalUrl: reviewer.portal_url ?? null,
		portalPassword: reviewer.portal_password ?? null,
	});
	const [name, setName] = useState(reviewer.name ?? '');
	const [email, setEmail] = useState(reviewer.email ?? '');
	const [weight, setWeight] = useState(String(reviewer.weight ?? 1));
	const [panelId, setPanelId] = useState(String(reviewer.panel_id ?? ''));

	useEffect(() => {
		setName(reviewer.name ?? '');
		setEmail(reviewer.email ?? '');
		setWeight(String(reviewer.weight ?? 1));
		setPanelId(String(reviewer.panel_id ?? ''));
		// Sync creds when the reviewer record is refreshed from the server
		if (reviewer.portal_password) {
			setLocalCreds({
				portalUrl: reviewer.portal_url ?? null,
				portalPassword: reviewer.portal_password,
			});
		}
	}, [reviewer]);

	const displayName = reviewer.name?.trim() || 'Unnamed reviewer';
	const panelIdNum = Number(reviewer.panel_id);

	const handlePanelHeadToggle = async (event) => {
		const checked = event.target.checked;
		const panelName = reviewer.panel_name || `Panel ${panelIdNum}`;

		try {
			const updated = await put(
				`/sessions/${sessionId}/panels/${panelIdNum}/reviewers/${reviewer.id}`,
				{ is_panel_head: checked }
			);
			onPanelHeadChanged(updated, panelIdNum);
			toast({
				variant: 'success',
				message: checked
					? `${displayName} set as panel coordinator for ${panelName}.`
					: `Panel coordinator cleared for ${panelName}.`,
			});
		} catch {
			toast({
				variant: 'error',
				message: 'Could not update panel coordinator.',
			});
		}
	};

	const handleSave = async () => {
		const trimmedName = name.trim();
		const trimmedEmail = email.trim();
		if (!trimmedName && !trimmedEmail) {
			toast({ variant: 'error', message: 'Enter a reviewer name or email.' });
			return;
		}

		const targetPanelId = Number(panelId);
		if (!targetPanelId) {
			toast({ variant: 'error', message: 'Select a panel.' });
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
			toast({
				variant: 'success',
				message:
					targetPanelId !== panelIdNum
						? 'Reviewer updated and moved to another panel.'
						: 'Reviewer updated.',
			});
		} catch (err) {
			const code = err?.code || err?.data?.code;
			toast({
				variant: 'error',
				message:
					code === 'pr_reviewer_email_in_session'
						? 'A reviewer with this email is already in the project.'
						: 'Could not save reviewer.',
			});
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
			toast({ variant: 'success', message: `${displayName} removed.` });
		} catch {
			toast({ variant: 'error', message: 'Could not remove reviewer.' });
		} finally {
			setDeleting(false);
		}
	};

	const handleProvision = async () => {
		setProvisioning(true);
		try {
			await onProvision();
		} finally {
			setProvisioning(false);
		}
	};

	const handleResend = async () => {
		setResending(true);
		try {
			const result = await post(
				`/sessions/${sessionId}/reviewers/${reviewer.id}/resend-credentials`
			);
			toast(
				result?.email_sent
					? {
							variant: 'success',
							message: 'Credentials re-sent. Password unchanged.',
					  }
					: {
							variant: 'error',
							message:
								'Email could not be sent. Check the SMTP settings.',
					  }
			);
			if (result?.credentials_sent_at) {
				onReviewerUpdated?.({
					...reviewer,
					credentials_sent_at: result.credentials_sent_at,
				});
			}
		} catch {
			toast({ variant: 'error', message: 'Could not resend credentials.' });
		} finally {
			setResending(false);
		}
	};

	const handleRegenerateConfirmed = async () => {
		setRegenerateConfirmOpen(false);
		setRegenerating(true);
		try {
			const result = await post(
				`/sessions/${sessionId}/reviewers/${reviewer.id}/generate-credentials`,
				{ send: false }
			);
			setLocalCreds({
				portalUrl: result.portal_url ?? reviewer.portal_url ?? null,
				portalPassword: result.portal_password ?? null,
			});
			setPortalLinkOpen(true);
			toast({ variant: 'success', message: 'Credentials regenerated.' });
			onReviewerUpdated?.({
				...reviewer,
				has_credentials: true,
				portal_url: result.portal_url ?? reviewer.portal_url,
				portal_password: result.portal_password ?? null,
			});
		} catch {
			toast({ variant: 'error', message: 'Could not regenerate credentials.' });
		} finally {
			setRegenerating(false);
		}
	};

	const openViewLink = () => {
		setPortalLinkOpen(true);
	};

	return (
		<>
			<tr className={TABLE_BODY_ROW_SOFT}>
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
						title="Designate as panel coordinator"
					>
						<input
							type="checkbox"
							name={`panel-coordinator-${panelIdNum}`}
							className="h-4 w-4 rounded border-border text-primary focus:ring-primary"
							checked={Boolean(reviewer.is_panel_head)}
							onChange={handlePanelHeadToggle}
							aria-label={`Panel coordinator for ${displayName}`}
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
						{reviewer.email && !reviewer.has_credentials ? (
							<Button
								size="sm"
								variant="primary"
								loading={provisioning}
								onClick={handleProvision}
							>
								Send credentials
							</Button>
						) : null}
						{reviewer.has_credentials ? (
							<>
								<Button
									size="sm"
									variant="secondary"
									loading={resending}
									disabled={regenerating}
									onClick={handleResend}
								>
									Resend
								</Button>
								<Button
									size="sm"
									variant="ghost"
									loading={regenerating}
									disabled={resending}
									onClick={() => setRegenerateConfirmOpen(true)}
								>
									Regenerate
								</Button>
								<Button
									size="sm"
									variant="ghost"
									disabled={resending || regenerating}
									onClick={openViewLink}
								>
									View link
								</Button>
							</>
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
			<ConfirmDialog
				open={regenerateConfirmOpen}
				title={`Regenerate credentials for ${displayName}?`}
				consequences={[
					'A new password will be generated and saved immediately.',
					'The current password stops working right away — the reviewer will be logged out.',
					'The login URL stays the same; only the password changes.',
					'Use "Send credentials" in the next screen to email the new password.',
				]}
				confirmLabel="Regenerate"
				confirmVariant="destructive"
				onCancel={() => setRegenerateConfirmOpen(false)}
				onConfirm={handleRegenerateConfirmed}
			/>
			<PortalLinkModal
				open={portalLinkOpen}
				reviewerName={displayName}
				portalUrl={localCreds.portalUrl ?? reviewer.portal_url ?? null}
				portalPassword={localCreds.portalPassword ?? reviewer.portal_password ?? null}
				sessionId={sessionId}
				reviewerId={reviewer.id}
				onClose={() => setPortalLinkOpen(false)}
				onCredentialsSent={(result) => {
					if (result?.credentials_sent_at) {
						onReviewerUpdated?.({
							...reviewer,
							credentials_sent_at: result.credentials_sent_at,
						});
					}
				}}
			/>
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
	const toast = useToast();
	const defaultPanelId =
		sessionPanels.length === 1 ? String(sessionPanels[0].id) : '';
	const [selectedPanelId, setSelectedPanelId] = useState(defaultPanelId);
	const [name, setName] = useState('');
	const [email, setEmail] = useState('');
	const [weight, setWeight] = useState('1');
	const [submitting, setSubmitting] = useState(false);

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

		const trimmedName = name.trim();
		const trimmedEmail = email.trim();
		if (!trimmedName && !trimmedEmail) {
			toast({ variant: 'error', message: 'Enter a reviewer name or email.' });
			return;
		}

		const panelId = Number(selectedPanelId);
		if (!panelId) {
			toast({ variant: 'error', message: 'Select a panel.' });
			return;
		}

		const panel = sessionPanels.find((p) => Number(p.id) === panelId);
		if (!panel) {
			toast({ variant: 'error', message: 'Select a panel.' });
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
			toast({ variant: 'success', message: `${label} added to ${panel.name}.` });
		} catch (err) {
			const code = err?.code || err?.data?.code;
			toast({
				variant: 'error',
				message:
					code === 'pr_reviewer_email_in_session'
						? 'A reviewer with this email is already in the project.'
						: 'Could not add reviewer.',
			});
		} finally {
			setSubmitting(false);
		}
	};

	return (
		<div className="mt-6 rounded-lg border border-border bg-surface-raised p-5 shadow-card">
			<div className="flex items-center gap-2 mb-4">
				<span className="flex h-8 w-8 items-center justify-center rounded-md bg-primary/10 text-primary">
					<Icon name="users" className="h-4 w-4" />
				</span>
				<h3 className="text-sm font-semibold text-text">Add reviewer</h3>
			</div>
			<form
				className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
				onSubmit={handleSubmit}
			>
				<div>
					<label
						className="block text-xs font-medium text-text-muted mb-1"
						htmlFor="add-reviewer-panel"
					>
						Panel
					</label>
					<div className="relative">
						<span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-text-muted">
							<Icon name="panel" className="h-4 w-4" />
						</span>
						<select
							id="add-reviewer-panel"
							value={selectedPanelId}
							onChange={(e) => setSelectedPanelId(e.target.value)}
							required={sessionPanels.length > 1}
							className="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
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
				</div>
				<div>
					<label
						className="block text-xs font-medium text-text-muted mb-1"
						htmlFor="add-reviewer-name"
					>
						Reviewer name
					</label>
					<div className="relative">
						<span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-text-muted">
							<Icon name="person" className="h-4 w-4" />
						</span>
						<input
							id="add-reviewer-name"
							type="text"
							autoComplete="name"
							value={name}
							onChange={(e) => setName(e.target.value)}
							className="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
							placeholder="Full name"
						/>
					</div>
				</div>
				<div>
					<label
						className="block text-xs font-medium text-text-muted mb-1"
						htmlFor="add-reviewer-email"
					>
						Email
					</label>
					<div className="relative">
						<span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-text-muted">
							<Icon name="email" className="h-4 w-4" />
						</span>
						<input
							id="add-reviewer-email"
							type="email"
							autoComplete="email"
							value={email}
							onChange={(e) => setEmail(e.target.value)}
							className="w-full rounded-md border border-border bg-surface py-2 pl-9 pr-3 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
							placeholder="reviewer@example.com"
						/>
					</div>
				</div>
				<div>
					<label
						className="block text-xs font-medium text-text-muted mb-1"
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
						className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
					/>
				</div>
				<div className="flex items-end">
					<Button
						variant="primary"
						type="submit"
						size="sm"
						loading={submitting}
						className="w-full"
					>
						Add reviewer
					</Button>
				</div>
			</form>
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
								<th className="px-4 py-3 font-medium">Access</th>
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
									onSaved={handleReviewerSaved}
									onDeleted={handleReviewerDeleted}
									onPanelHeadChanged={handlePanelHeadChanged}
									onReviewerUpdated={handleReviewerSaved}
								/>
							))}
						</tbody>
					</table>
				</TableScrollWrapper>
			)}
		</div>
	);
}

const RESEND_ALL_PHRASE = 'RESEND ALL';

export function PanelReviewersStep({
	sessionId,
	panels,
	reviewers,
	setReviewers,
	onNotice,
	onRefreshReviewers,
	onReload,
}) {
	const toast = useToast();
	const smtpConfigured = window.prAppData?.smtpConfigured !== false;
	const [sendFailedEmails, setSendFailedEmails] = useState([]);

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

	const [bulkSending, setBulkSending] = useState(false);
	const [resendAllOpen, setResendAllOpen] = useState(false);
	const [resendAllPhrase, setResendAllPhrase] = useState('');

	const openResendAllDialog = () => {
		setResendAllPhrase('');
		setResendAllOpen(true);
	};

	const closeResendAllDialog = () => {
		setResendAllOpen(false);
		setResendAllPhrase('');
	};

	const handleBulkSend = async (force) => {
		setBulkSending(true);
		setSendFailedEmails([]);
		try {
			const result = await post(`/sessions/${sessionId}/send-all-credentials`, {
				force,
			});
			const parts = [`${result.sent} sent`];
			if (result.skipped > 0) {
				parts.push(`${result.skipped} skipped`);
			}
			if (result.failed > 0) {
				parts.push(`${result.failed} failed`);
			}
			toast({
				variant: result.failed > 0 ? 'error' : 'success',
				message: `Credentials: ${parts.join(', ')}.`,
			});
			if (
				Array.isArray(result.failed_emails) &&
				result.failed_emails.length > 0
			) {
				setSendFailedEmails(result.failed_emails);
			}
			await onRefreshReviewers?.();
		} catch {
			toast({
				variant: 'error',
				message: 'Could not send credentials.',
			});
		} finally {
			setBulkSending(false);
		}
	};

	const handleResendAllConfirm = async () => {
		closeResendAllDialog();
		await handleBulkSend(true);
	};

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
			<div className="flex flex-wrap items-center justify-between gap-2">
				<h2 className="text-lg font-semibold text-text">Reviewers</h2>
				<div className="flex flex-wrap gap-2">
					<Button
						size="sm"
						variant="primary"
						loading={bulkSending}
						onClick={() => handleBulkSend(false)}
					>
						Email credentials to all
					</Button>
					<Button
						size="sm"
						variant="ghost"
						disabled={bulkSending}
						onClick={openResendAllDialog}
					>
						Resend to all
					</Button>
				</div>
			</div>
			<p className="mt-1 text-sm text-text-muted">
				Add reviewers to a panel, download a roster template prefilled with your
				panels and existing reviewers, or import updates from CSV. Each reviewer
				receives a personal review link and password by email — no WordPress
				account needed. Reviewers who already received credentials are skipped
				unless you use "Resend to all".
			</p>

			{ ! smtpConfigured && (
				<div className="mt-3">
					<Notice variant="warning">
						Email will use the server&rsquo;s default mail transport &mdash; SMTP
						is not configured.{ ' ' }
						<a
							href="/wp-admin/admin.php?page=scorva-settings"
							className="underline"
						>
							Go to Settings
						</a>
					</Notice>
				</div>
			) }

			{ sendFailedEmails.length > 0 && (
				<div className="mt-3">
					<Notice
						variant="error"
						onDismiss={ () => setSendFailedEmails( [] ) }
					>
						{ sendFailedEmails.length } email
						{ sendFailedEmails.length === 1 ? '' : 's' } failed to send:{ ' ' }
						{ sendFailedEmails.join( ', ' ) }
					</Notice>
				</div>
			) }

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
					onProvision={async (reviewerId) => {
						const result = await post(
							`/sessions/${sessionId}/reviewers/${reviewerId}/generate-credentials`
						);
						toast(
							result?.email_sent
								? {
										variant: 'success',
										message:
											'Review link and password emailed to reviewer.',
								  }
								: {
										variant: 'error',
										message:
											'Credentials generated but the email could not be sent. Check the SMTP settings.',
								  }
						);
						await onRefreshReviewers?.();
					}}
				/>
			))}

			<ConfirmDialog
				open={resendAllOpen}
				title="Resend credentials to all reviewers?"
				consequences={[
					'New passwords will be generated for every credentialed reviewer.',
					'Each reviewer will receive a fresh email with their review link and new password.',
					'Previous passwords are immediately invalidated.',
					'Reviewers without an email address are skipped.',
				]}
				confirmLabel={bulkSending ? 'Sending…' : 'Resend to all'}
				confirmVariant="destructive"
				confirmDisabled={
					resendAllPhrase.trim() !== RESEND_ALL_PHRASE || bulkSending
				}
				onCancel={closeResendAllDialog}
				onConfirm={handleResendAllConfirm}
			>
				<div className="space-y-2 text-sm text-text-muted">
					<p>
						Type{' '}
						<strong className="font-mono text-text">{RESEND_ALL_PHRASE}</strong>{' '}
						to confirm.
					</p>
					<input
						type="text"
						className="w-full rounded-md border border-border bg-surface px-3 py-2 text-text"
						value={resendAllPhrase}
						onChange={(e) => setResendAllPhrase(e.target.value)}
						autoComplete="off"
						aria-label={`Type ${RESEND_ALL_PHRASE} to confirm`}
					/>
				</div>
			</ConfirmDialog>
		</section>
	);
}
