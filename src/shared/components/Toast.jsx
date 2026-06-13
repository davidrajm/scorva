import { createContext, useCallback, useContext, useRef, useState } from '@wordpress/element';
import { createPortal } from 'react-dom';

const AUTO_DISMISS_MS = 4500;

const VARIANT_CONFIG = {
	success: {
		bar: '#22c55e',
		bg: '#f0fdf4',
		border: '#bbf7d0',
		text: '#15803d',
		icon: (
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
				<circle cx="8" cy="8" r="7.5" stroke="#22c55e" />
				<path d="M4.5 8l2.5 2.5 4.5-4.5" stroke="#22c55e" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" />
			</svg>
		),
	},
	error: {
		bar: '#ef4444',
		bg: '#fef2f2',
		border: '#fecaca',
		text: '#b91c1c',
		icon: (
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
				<circle cx="8" cy="8" r="7.5" stroke="#ef4444" />
				<path d="M5.5 5.5l5 5M10.5 5.5l-5 5" stroke="#ef4444" strokeWidth="1.5" strokeLinecap="round" />
			</svg>
		),
	},
	warning: {
		bar: '#f59e0b',
		bg: '#fffbeb',
		border: '#fde68a',
		text: '#92400e',
		icon: (
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
				<path d="M8 2L14.5 13.5H1.5L8 2z" stroke="#f59e0b" strokeWidth="1.5" strokeLinejoin="round" />
				<path d="M8 6.5v3" stroke="#f59e0b" strokeWidth="1.5" strokeLinecap="round" />
				<circle cx="8" cy="11.5" r="0.75" fill="#f59e0b" />
			</svg>
		),
	},
	info: {
		bar: '#3b82f6',
		bg: '#eff6ff',
		border: '#bfdbfe',
		text: '#1e40af',
		icon: (
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
				<circle cx="8" cy="8" r="7.5" stroke="#3b82f6" />
				<path d="M8 7v4.5" stroke="#3b82f6" strokeWidth="1.5" strokeLinecap="round" />
				<circle cx="8" cy="4.5" r="0.75" fill="#3b82f6" />
			</svg>
		),
	},
};

const ToastContext = createContext(null);

export function useToast() {
	const ctx = useContext(ToastContext);
	if (!ctx) {
		throw new Error('useToast must be used inside ToastProvider');
	}
	return ctx.toast;
}

function ToastItem({ t, onDismiss }) {
	const cfg = VARIANT_CONFIG[t.variant] ?? VARIANT_CONFIG.info;

	return (
		<div
			role="status"
			style={{
				display: 'flex',
				alignItems: 'flex-start',
				gap: '10px',
				background: cfg.bg,
				border: `1px solid ${cfg.border}`,
				borderLeft: `4px solid ${cfg.bar}`,
				borderRadius: '6px',
				padding: '12px 14px',
				boxShadow: '0 4px 12px rgba(0,0,0,0.12)',
				fontSize: '13px',
				color: cfg.text,
				lineHeight: '1.45',
				minWidth: '260px',
				maxWidth: '360px',
				wordBreak: 'break-word',
			}}
		>
			<span style={{ flexShrink: 0, marginTop: '1px' }}>{cfg.icon}</span>
			<span style={{ flex: 1 }}>{t.message}</span>
			<button
				type="button"
				onClick={() => onDismiss(t.id)}
				aria-label="Dismiss notification"
				style={{
					background: 'none',
					border: 'none',
					cursor: 'pointer',
					padding: '0 0 0 4px',
					color: cfg.text,
					opacity: 0.6,
					fontSize: '14px',
					lineHeight: 1,
					flexShrink: 0,
				}}
			>
				✕
			</button>
		</div>
	);
}

export function ToastProvider({ children }) {
	const [toasts, setToasts] = useState([]);
	const nextId = useRef(1);

	const dismiss = useCallback((id) => {
		setToasts((prev) => prev.filter((t) => t.id !== id));
	}, []);

	const toast = useCallback(
		({ variant = 'info', message }) => {
			const id = nextId.current++;
			setToasts((prev) => [...prev, { id, variant, message }]);
			setTimeout(() => dismiss(id), AUTO_DISMISS_MS);
		},
		[dismiss]
	);

	const container =
		typeof document !== 'undefined'
			? createPortal(
					<div
						aria-live="polite"
						aria-label="Notifications"
						style={{
							position: 'fixed',
							bottom: '20px',
							right: '20px',
							zIndex: 99999,
							display: 'flex',
							flexDirection: 'column',
							gap: '8px',
							pointerEvents: toasts.length ? 'auto' : 'none',
						}}
					>
						{toasts.map((t) => (
							<ToastItem key={t.id} t={t} onDismiss={dismiss} />
						))}
					</div>,
					document.body
			  )
			: null;

	return (
		<ToastContext.Provider value={{ toast }}>
			{children}
			{container}
		</ToastContext.Provider>
	);
}
