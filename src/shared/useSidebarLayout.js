import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

export const SIDEBAR_STORAGE_COLLAPSED = 'pr-sidebar-collapsed';
export const SIDEBAR_STORAGE_WIDTH = 'pr-sidebar-width';
export const SIDEBAR_DEFAULT_WIDTH = 240;
export const SIDEBAR_MIN_WIDTH = 200;
export const SIDEBAR_MAX_WIDTH = 400;
export const SIDEBAR_LG_MEDIA = '(min-width: 1024px)';

function readCollapsed() {
	if ( typeof window === 'undefined' ) {
		return false;
	}
	return window.localStorage.getItem( SIDEBAR_STORAGE_COLLAPSED ) === '1';
}

function readWidthPx() {
	if ( typeof window === 'undefined' ) {
		return SIDEBAR_DEFAULT_WIDTH;
	}
	const raw = parseInt(
		window.localStorage.getItem( SIDEBAR_STORAGE_WIDTH ) || '',
		10
	);
	if ( Number.isNaN( raw ) ) {
		return SIDEBAR_DEFAULT_WIDTH;
	}
	return Math.min( SIDEBAR_MAX_WIDTH, Math.max( SIDEBAR_MIN_WIDTH, raw ) );
}

function clampWidth( value ) {
	return Math.min( SIDEBAR_MAX_WIDTH, Math.max( SIDEBAR_MIN_WIDTH, value ) );
}

export function useSidebarLayout() {
	const [ collapsed, setCollapsed ] = useState( readCollapsed );
	const [ widthPx, setWidthPx ] = useState( readWidthPx );
	const [ drawerOpen, setDrawerOpen ] = useState( false );
	const [ isLg, setIsLg ] = useState( () =>
		typeof window !== 'undefined'
			? window.matchMedia( SIDEBAR_LG_MEDIA ).matches
			: true
	);
	const [ isDragging, setIsDragging ] = useState( false );
	const dragStartX = useRef( 0 );
	const dragStartWidth = useRef( SIDEBAR_DEFAULT_WIDTH );

	useEffect( () => {
		const mq = window.matchMedia( SIDEBAR_LG_MEDIA );
		const onChange = () => {
			setIsLg( mq.matches );
			if ( mq.matches ) {
				setDrawerOpen( false );
			}
		};
		onChange();
		mq.addEventListener( 'change', onChange );
		return () => mq.removeEventListener( 'change', onChange );
	}, [] );

	useEffect( () => {
		const root = document.getElementById( 'pr-root' );
		if ( ! root ) {
			return;
		}
		const effectiveWidth = collapsed && isLg ? 56 : widthPx;
		root.style.setProperty( '--pr-layout-sidebar-width', `${ effectiveWidth }px` );
	}, [ collapsed, widthPx, isLg ] );

	useEffect( () => {
		if ( isDragging ) {
			document.body.classList.add( 'pr-sidebar-resizing' );
		} else {
			document.body.classList.remove( 'pr-sidebar-resizing' );
		}
		return () => document.body.classList.remove( 'pr-sidebar-resizing' );
	}, [ isDragging ] );

	const persistCollapsed = useCallback( ( next ) => {
		window.localStorage.setItem(
			SIDEBAR_STORAGE_COLLAPSED,
			next ? '1' : '0'
		);
	}, [] );

	const persistWidth = useCallback( ( next ) => {
		window.localStorage.setItem( SIDEBAR_STORAGE_WIDTH, String( next ) );
	}, [] );

	const toggleCollapsed = useCallback( () => {
		setCollapsed( ( prev ) => {
			const next = ! prev;
			persistCollapsed( next );
			return next;
		} );
	}, [ persistCollapsed ] );

	const toggleDrawer = useCallback( () => {
		setDrawerOpen( ( prev ) => ! prev );
	}, [] );

	const closeDrawer = useCallback( () => {
		setDrawerOpen( false );
	}, [] );

	const nudgeWidth = useCallback( ( delta ) => {
		if ( collapsed || ! isLg ) {
			return;
		}
		setWidthPx( ( prev ) => {
			const next = clampWidth( prev + delta );
			persistWidth( next );
			return next;
		} );
	}, [ collapsed, isLg, persistWidth ] );

	const onResizePointerDown = useCallback(
		( event ) => {
			if ( collapsed || ! isLg || event.button !== 0 ) {
				return;
			}
			event.preventDefault();
			dragStartX.current = event.clientX;
			dragStartWidth.current = widthPx;
			setIsDragging( true );
			event.currentTarget.setPointerCapture( event.pointerId );
		},
		[ collapsed, isLg, widthPx ]
	);

	const onResizePointerMove = useCallback(
		( event ) => {
			if ( ! isDragging ) {
				return;
			}
			const delta = event.clientX - dragStartX.current;
			const next = clampWidth( dragStartWidth.current + delta );
			setWidthPx( next );
		},
		[ isDragging ]
	);

	const onResizePointerUp = useCallback(
		( event ) => {
			if ( ! isDragging ) {
				return;
			}
			setIsDragging( false );
			try {
				event.currentTarget.releasePointerCapture( event.pointerId );
			} catch {
				// Pointer may already be released.
			}
			setWidthPx( ( prev ) => {
				persistWidth( prev );
				return prev;
			} );
		},
		[ isDragging, persistWidth ]
	);

	const onResizeKeyDown = useCallback(
		( event ) => {
			if ( event.key === 'ArrowLeft' ) {
				event.preventDefault();
				nudgeWidth( -8 );
			} else if ( event.key === 'ArrowRight' ) {
				event.preventDefault();
				nudgeWidth( 8 );
			}
		},
		[ nudgeWidth ]
	);

	return {
		collapsed,
		widthPx,
		drawerOpen,
		isLg,
		isDragging,
		toggleCollapsed,
		toggleDrawer,
		closeDrawer,
		nudgeWidth,
		onResizePointerDown,
		onResizePointerMove,
		onResizePointerUp,
		onResizeKeyDown,
		sidebarWidthForA11y: collapsed && isLg ? 56 : widthPx,
	};
}
