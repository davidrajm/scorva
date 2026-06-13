/**
 * App shell layout — Direction 1 Structured Academic.
 * Mount inside #pr-root (see templates/app-shell.php).
 */

import { cloneElement, useEffect } from '@wordpress/element';
import { post } from './api';
import { getAppDisplayName } from './appBranding';
import { Icon } from './components/NavIcon';
import { IconRailTooltip } from './components/IconRailTooltip';
import {
	SIDEBAR_MAX_WIDTH,
	SIDEBAR_MIN_WIDTH,
	useSidebarLayout,
} from './useSidebarLayout';

function getCurrentUser() {
	if ( typeof window === 'undefined' ) {
		return null;
	}

	return window.prAppData?.currentUser ?? null;
}

function UserIdentity() {
	const user = getCurrentUser();
	if ( ! user ) {
		return null;
	}

	const displayName = user.displayName?.trim() || 'Signed in user';
	const email = user.email || '';

	return (
		<div
			className="pr-user-identity min-w-0 text-right"
			aria-label={ `Signed in as ${ displayName }` }
		>
			<p className="truncate text-sm font-medium text-text">{ displayName }</p>
			{ email ? (
				<p
					className="truncate text-xs text-text-muted"
					title={ email }
				>
					{ email }
				</p>
			) : null }
		</div>
	);
}

const authLinkClass =
	'text-sm font-medium text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2';

function AuthActions() {
	const loginUrl = window.prAppData?.loginUrl;
	const logoutUrl = window.prAppData?.logoutUrl;
	const user = getCurrentUser();

	if ( user && window.prAppData?.portalMode ) {
		const handlePortalLogout = async () => {
			try {
				await post( '/portal/logout' );
			} catch {
				// Session already gone — fall through to reload.
			}
			window.location.reload();
		};

		return (
			<button
				type="button"
				className={ authLinkClass }
				onClick={ handlePortalLogout }
			>
				Log out
			</button>
		);
	}

	if ( user && logoutUrl ) {
		return (
			<a href={ logoutUrl } rel="nofollow" className={ authLinkClass }>
				Log out
			</a>
		);
	}

	if ( ! user && loginUrl ) {
		return (
			<a href={ loginUrl } className={ authLinkClass }>
				Log in
			</a>
		);
	}

	return null;
}

function SidebarCollapseButton( { collapsed, onClick } ) {
	const label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
	const button = (
		<button
			type="button"
			className="pr-sidebar-collapse-btn"
			onClick={ onClick }
			aria-label={ label }
		>
			<Icon
				name="chevron-right"
				className={ [
					'h-5 w-5 transition-transform',
					collapsed ? '' : 'rotate-180',
				].join( ' ' ) }
			/>
		</button>
	);

	if ( ! collapsed ) {
		return button;
	}

	return <IconRailTooltip label={ label }>{ button }</IconRailTooltip>;
}

export function AppShell( { variant = 'coordinator', children, sidebar, topNav } ) {
	const isCoordinator = variant === 'coordinator';
	const isLanding = variant === 'landing';
	const showIdentity = Boolean( getCurrentUser() ) && ! isLanding;
	const appHomeUrl = window.prAppData?.appHomeUrl;
	const wordmarkClass = 'pr-wordmark m-0 text-xl font-semibold leading-snug text-primary';

	const sidebarLayout = useSidebarLayout();
	const {
		collapsed,
		drawerOpen,
		isLg,
		toggleCollapsed,
		toggleDrawer,
		closeDrawer,
		onResizePointerDown,
		onResizePointerMove,
		onResizePointerUp,
		onResizeKeyDown,
		sidebarWidthForA11y,
	} = sidebarLayout;

	useEffect( () => {
		if ( ! isCoordinator || isLg ) {
			return undefined;
		}
		const onKeyDown = ( event ) => {
			if ( event.key === 'Escape' ) {
				closeDrawer();
			}
		};
		window.addEventListener( 'keydown', onKeyDown );
		return () => window.removeEventListener( 'keydown', onKeyDown );
	}, [ isCoordinator, isLg, closeDrawer ] );

	const displayName = getAppDisplayName();
	const wordmark = ! isCoordinator && appHomeUrl ? (
		<a href={ appHomeUrl } className={ `${ wordmarkClass } no-underline hover:underline` }>
			{ displayName }
		</a>
	) : (
		<p className={ wordmarkClass }>{ displayName }</p>
	);

	const sidebarNav = sidebar
		? cloneElement( sidebar, { collapsed: isLg && collapsed } )
		: null;

	const showDrawerBackdrop = isCoordinator && ! isLg && drawerOpen;
	const sidebarClasses = [
		'pr-sidebar',
		'pr-scroll',
		isLg && collapsed ? 'pr-sidebar--collapsed' : '',
		! isLg ? 'pr-sidebar--drawer' : '',
		! isLg && drawerOpen ? 'pr-sidebar--drawer-open' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<>
			<a href="#pr-main" className="pr-skip-link">
				Skip to main content
			</a>
			<div className="pr-shell" data-app={ variant }>
				<header className="pr-topbar">
					<div className="pr-topbar-inner">
						<div className="pr-topbar-start">
							{ isCoordinator ? (
								<button
									type="button"
									className="pr-sidebar-menu-btn"
									onClick={ toggleDrawer }
									aria-expanded={ drawerOpen }
									aria-controls="pr-sidebar-nav"
									aria-label={
										drawerOpen
											? 'Close navigation menu'
											: 'Open navigation menu'
									}
								>
									<Icon name="panel" className="h-5 w-5" />
								</button>
							) : null }
							{ wordmark }
							{ topNav ? topNav : null }
						</div>
						<div className="pr-topbar-actions flex shrink-0 items-center gap-3">
							{ showIdentity ? <UserIdentity /> : null }
							{ ! isLanding ? <AuthActions /> : null }
						</div>
					</div>
				</header>
				<div className="pr-body">
					{ showDrawerBackdrop ? (
						<button
							type="button"
							className="pr-sidebar-backdrop"
							onClick={ closeDrawer }
							aria-label="Close navigation menu"
						/>
					) : null }
					{ isCoordinator ? (
						<nav
							id="pr-sidebar-nav"
							aria-label="Main"
							className={ sidebarClasses }
						>
							<div className="pr-sidebar-inner">
								{ isLg ? (
									<div className="pr-sidebar-toolbar">
										<SidebarCollapseButton
											collapsed={ collapsed }
											onClick={ toggleCollapsed }
										/>
									</div>
								) : null }
								{ sidebarNav }
							</div>
							{ isLg && ! collapsed ? (
								<button
									type="button"
									className="pr-sidebar-resize-handle"
									role="separator"
									aria-orientation="vertical"
									aria-label="Resize sidebar"
									aria-valuemin={ SIDEBAR_MIN_WIDTH }
									aria-valuemax={ SIDEBAR_MAX_WIDTH }
									aria-valuenow={ sidebarWidthForA11y }
									onPointerDown={ onResizePointerDown }
									onPointerMove={ onResizePointerMove }
									onPointerUp={ onResizePointerUp }
									onPointerCancel={ onResizePointerUp }
									onKeyDown={ onResizeKeyDown }
								/>
							) : null }
						</nav>
					) : null }
					<main id="pr-main" className="pr-main pr-scroll" tabIndex={ -1 }>
						{ children }
					</main>
				</div>
			</div>
		</>
	);
}
