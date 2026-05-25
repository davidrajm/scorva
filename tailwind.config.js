/** @type {import('tailwindcss').Config} */
module.exports = {
	content: [ './src/**/*.{js,jsx}' ],
	important: '#pr-root',
	theme: {
		extend: {
			colors: {
				primary: 'var(--pr-color-primary)',
				'primary-hover': 'var(--pr-color-primary-hover)',
				surface: 'var(--pr-color-surface)',
				'surface-raised': 'var(--pr-color-surface-raised)',
				border: 'var(--pr-color-border)',
				text: 'var(--pr-color-text)',
				'text-muted': 'var(--pr-color-text-muted)',
				success: 'var(--pr-color-success)',
				warning: 'var(--pr-color-warning)',
				danger: 'var(--pr-color-danger)',
				info: 'var(--pr-color-info)',
				'chip-draft': 'var(--pr-chip-draft-text)',
				'chip-draft-bg': 'var(--pr-chip-draft-bg)',
				'chip-active': 'var(--pr-chip-active-text)',
				'chip-active-bg': 'var(--pr-chip-active-bg)',
				'chip-closed': 'var(--pr-chip-closed-text)',
				'chip-closed-bg': 'var(--pr-chip-closed-bg)',
				'chip-confirmed': 'var(--pr-chip-confirmed-text)',
				'chip-confirmed-bg': 'var(--pr-chip-confirmed-bg)',
				'chip-unlocked': 'var(--pr-chip-unlocked-text)',
				'chip-unlocked-bg': 'var(--pr-chip-unlocked-bg)',
				'chip-flagged': 'var(--pr-chip-flagged-text)',
				'chip-flagged-bg': 'var(--pr-chip-flagged-bg)',
				'chip-coordinator': 'var(--pr-chip-coordinator-text)',
				'chip-coordinator-bg': 'var(--pr-chip-coordinator-bg)',
			},
			borderRadius: {
				md: 'var(--pr-radius-md)',
			},
			boxShadow: {
				card: 'var(--pr-shadow-card)',
			},
			fontFamily: {
				sans: [ 'var(--pr-font-family)' ],
			},
			spacing: {
				1: '4px',
				2: '8px',
				3: '12px',
				4: '16px',
				6: '24px',
				8: '32px',
				12: '48px',
			},
			maxWidth: {
				content: 'var(--pr-layout-content-max-width)',
			},
			height: {
				topbar: 'var(--pr-layout-topbar-height)',
			},
			width: {
				sidebar: 'var(--pr-layout-sidebar-width)',
			},
		},
	},
	plugins: [],
};
