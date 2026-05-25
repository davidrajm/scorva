import { Icon } from '../../shared/components/NavIcon.jsx';

export const DASHBOARD_STATUS_TABS = [
	{ key: 'all', label: 'All projects', icon: 'dashboard' },
	{ key: 'active', label: 'Active projects', icon: 'progress' },
	{ key: 'draft', label: 'Draft projects', icon: 'pencil' },
	{ key: 'closed', label: 'Closed projects', icon: 'close' },
];

export function DashboardStatusNav( { currentStatus, onStatusClick } ) {
	return (
		<nav
			aria-label="Filter projects by status"
			className="mb-8 border-b border-border"
		>
			<ol className="flex flex-wrap gap-0" role="tablist">
				{ DASHBOARD_STATUS_TABS.map( ( item ) => {
					const isCurrent = currentStatus === item.key;

					return (
						<li key={ item.key } role="presentation">
							<button
								type="button"
								role="tab"
								aria-selected={ isCurrent }
								data-testid={ `pr-dashboard-status-${ item.key }` }
								onClick={ () => onStatusClick( item.key ) }
								className={ [
									'flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors -mb-px',
									isCurrent
										? 'border-primary text-primary'
										: 'border-transparent text-text-muted hover:border-border hover:text-text',
								].join( ' ' ) }
							>
								<Icon
									name={ item.icon }
									className="h-4 w-4 shrink-0"
								/>
								{ item.label }
							</button>
						</li>
					);
				} ) }
			</ol>
		</nav>
	);
}
