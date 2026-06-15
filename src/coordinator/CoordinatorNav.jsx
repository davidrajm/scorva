import { useEffect, useState } from '@wordpress/element';
import { NavLink, useLocation } from 'react-router-dom';
import { get } from '../shared/api';
import { IconRailTooltip } from '../shared/components/IconRailTooltip';
import { NavIcon } from '../shared/components/NavIcon';

const linkClass =
	( tone = 'default', collapsed = false ) =>
	( { isActive } ) => {
		const base = collapsed
			? 'flex items-center justify-center rounded-md p-2'
			: 'flex items-center gap-2 rounded-md px-3 py-2';

		if ( tone === 'destructive' ) {
			return [
				`${ base } text-sm font-medium`,
				isActive
					? 'bg-danger/10 text-danger'
					: 'text-danger/80 hover:bg-danger/5 hover:text-danger',
			].join( ' ' );
		}

		return [
			`${ base } text-sm font-medium`,
			isActive
				? 'bg-chip-active-bg text-primary'
				: 'text-text-muted hover:bg-surface-raised hover:text-text',
		].join( ' ' );
	};

const SESSION_NAV = [
	{ path: 'wizard', label: 'Setup wizard', icon: 'wizard', end: false },
	{ path: 'progress', label: 'Progress', icon: 'progress', end: true },
	{ path: 'reports', label: 'Reports', icon: 'reports', end: true },
	{ path: 'audit', label: 'Audit log', icon: 'audit', end: true },
];

const SETTINGS_NAV = [
	{ path: 'settings/panel-report', label: 'Panel Report', icon: 'settings', end: true },
];

const LIFECYCLE_NAV = [
	{
		path: 'close',
		label: 'Close project',
		icon: 'close',
		end: true,
		tone: 'destructive',
	},
];

const GLOBAL_NAV = [
	{ to: '/', label: 'Dashboard', icon: 'dashboard', end: true },
	{ to: '/unfreeze-requests', label: 'Unfreeze Requests', icon: 'unlock', end: true, requiresManage: true },
];

function sessionIdFromPath( pathname ) {
	const match = pathname.match( /^\/session\/(\d+)(?:\/|$)/ );
	return match ? match[ 1 ] : null;
}

function NavItem( { to, end, icon, label, tone = 'default', collapsed = false } ) {
	const link = (
		<NavLink
			to={ to }
			end={ end }
			className={ linkClass( tone, collapsed ) }
		>
			<NavIcon name={ icon } />
			{ collapsed ? (
				<span className="sr-only">{ label }</span>
			) : (
				<span>{ label }</span>
			) }
		</NavLink>
	);

	if ( ! collapsed ) {
		return link;
	}

	return <IconRailTooltip label={ label }>{ link }</IconRailTooltip>;
}

export function CoordinatorNav( { collapsed = false } ) {
	const { pathname } = useLocation();
	const sessionId = sessionIdFromPath( pathname );
	const [ sessionTitle, setSessionTitle ] = useState( '' );
	const canAssignReviewers = window.prAppData?.canAssignReviewers !== false;
	const canManage = Boolean( window.prAppData?.canManageProjects );
	const canViewClose =
		window.prAppData?.canCloseProject ||
		window.prAppData?.canManageProjects;
	const globalNavItems = GLOBAL_NAV.filter( ( item ) => {
		if ( item.requiresAssignReviewers && ! canAssignReviewers ) {
			return false;
		}
		if ( item.requiresManage && ! canManage ) {
			return false;
		}
		return true;
	} );

	useEffect( () => {
		if ( ! sessionId ) {
			setSessionTitle( '' );
			return;
		}

		let cancelled = false;
		get( `sessions/${ sessionId }` )
			.then( ( data ) => {
				if ( ! cancelled ) {
					setSessionTitle( data?.title || '' );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setSessionTitle( '' );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ sessionId ] );

	return (
		<ul
			className={ [
				'flex flex-col gap-1',
				collapsed ? 'p-2' : 'p-4',
			].join( ' ' ) }
		>
			{ globalNavItems.map( ( item ) => (
				<li key={ item.to }>
					<NavItem { ...item } collapsed={ collapsed } />
				</li>
			) ) }
			{ sessionId ? (
				<li className={ collapsed ? 'mt-2' : 'mt-4' }>
					{ collapsed ? (
						<ul className="flex flex-col gap-1">
							{ SESSION_NAV.map( ( item ) => (
								<li key={ item.path }>
									<NavItem
										to={ `/session/${ sessionId }/${ item.path }` }
										end={ item.end }
										icon={ item.icon }
										label={ item.label }
										collapsed
									/>
								</li>
							) ) }
							{ SETTINGS_NAV.map( ( item ) => (
								<li key={ item.path }>
									<NavItem
										to={ `/session/${ sessionId }/${ item.path }` }
										end={ item.end }
										icon={ item.icon }
										label={ item.label }
										collapsed
									/>
								</li>
							) ) }
							{ canViewClose
								? LIFECYCLE_NAV.map( ( item ) => (
										<li key={ item.path }>
											<NavItem
												to={ `/session/${ sessionId }/${ item.path }` }
												end={ item.end }
												icon={ item.icon }
												label={ item.label }
												tone={ item.tone }
												collapsed
											/>
										</li>
								  ) )
								: null }
						</ul>
					) : (
						<div className="rounded-md border border-border border-l-4 border-l-primary bg-surface-raised px-2 py-3">
							<p className="px-2 text-xs font-semibold uppercase tracking-wide text-text-muted">
								Project
							</p>
							{ sessionTitle ? (
								<p
									className="mt-1 truncate px-2 text-sm font-medium text-text"
									title={ sessionTitle }
								>
									{ sessionTitle }
								</p>
							) : null }
							<ul className="mt-2 flex flex-col gap-1">
								{ SESSION_NAV.map( ( item ) => (
									<li key={ item.path }>
										<NavItem
											to={ `/session/${ sessionId }/${ item.path }` }
											end={ item.end }
											icon={ item.icon }
											label={ item.label }
										/>
									</li>
								) ) }
								<li className="mt-3 px-2 text-xs font-semibold uppercase tracking-wide text-text-muted">
									Settings
								</li>
								{ SETTINGS_NAV.map( ( item ) => (
									<li key={ item.path }>
										<NavItem
											to={ `/session/${ sessionId }/${ item.path }` }
											end={ item.end }
											icon={ item.icon }
											label={ item.label }
										/>
									</li>
								) ) }
								{ canViewClose ? (
									<li className="mt-3 border-t border-border pt-3">
										<p className="px-2 text-xs font-semibold uppercase tracking-wide text-text-muted">
											End project
										</p>
										<ul className="mt-2 flex flex-col gap-1">
											{ LIFECYCLE_NAV.map( ( item ) => (
												<li key={ item.path }>
													<NavItem
														to={ `/session/${ sessionId }/${ item.path }` }
														end={ item.end }
														icon={ item.icon }
														label={ item.label }
														tone={ item.tone }
													/>
												</li>
											) ) }
										</ul>
									</li>
								) : null }
							</ul>
						</div>
					) }
				</li>
			) : null }
		</ul>
	);
}
