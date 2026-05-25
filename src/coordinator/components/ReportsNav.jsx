import { Icon } from '../../shared/components/NavIcon.jsx';

export const REPORTS_TABS = [
	{ key: 'marks', label: 'Marks', icon: 'rubrics' },
	{ key: 'scores', label: 'Overall scores', icon: 'progress' },
	{ key: 'consolidated', label: 'Consolidated', icon: 'clipboard' },
	{ key: 'downloads', label: 'Downloads', icon: 'reports' },
];

export function ReportsNav( { currentTab, onTabClick } ) {
	return (
		<nav
			aria-label="Report sections"
			className="mb-8 border-b border-border"
		>
			<ol className="flex flex-wrap gap-0" role="tablist">
				{ REPORTS_TABS.map( ( item ) => {
					const isCurrent = currentTab === item.key;

					return (
						<li key={ item.key } role="presentation">
							<button
								type="button"
								role="tab"
								aria-selected={ isCurrent }
								onClick={ () => onTabClick( item.key ) }
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
