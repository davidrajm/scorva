/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/landing/App.jsx"
/*!*****************************!*\
  !*** ./src/landing/App.jsx ***!
  \*****************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   LandingApp: () => (/* binding */ LandingApp)
/* harmony export */ });
/* harmony import */ var _shared_AppShell__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../shared/AppShell */ "./src/shared/AppShell.jsx");
/* harmony import */ var _shared_appBranding__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../shared/appBranding */ "./src/shared/appBranding.js");
/* harmony import */ var _shared_components_Button__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../shared/components/Button */ "./src/shared/components/Button.jsx");
/* harmony import */ var _shared_components_PageHeader__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../shared/components/PageHeader */ "./src/shared/components/PageHeader.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





function getAppData() {
  return typeof window !== 'undefined' ? window.prAppData ?? {} : {};
}
function GuestLanding({
  loginUrl
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
    className: "mx-auto w-full max-w-[480px]",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components_PageHeader__WEBPACK_IMPORTED_MODULE_3__.PageHeader, {
      title: (0,_shared_appBranding__WEBPACK_IMPORTED_MODULE_1__.getAppDisplayName)(),
      description: "Sign in to open the coordinator or marking workspace for your assigned projects."
    }), loginUrl ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components_Button__WEBPACK_IMPORTED_MODULE_2__.Button, {
      variant: "primary",
      size: "lg",
      className: "w-full sm:w-auto",
      onClick: () => {
        window.location.assign(loginUrl);
      },
      children: "Log in"
    }) : null]
  });
}
function LandingApp() {
  const {
    loginUrl,
    currentUser
  } = getAppData();
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_AppShell__WEBPACK_IMPORTED_MODULE_0__.AppShell, {
    variant: "landing",
    children: !currentUser ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(GuestLanding, {
      loginUrl: loginUrl
    }) : null
  });
}

/***/ },

/***/ "./src/shared/AppShell.jsx"
/*!*********************************!*\
  !*** ./src/shared/AppShell.jsx ***!
  \*********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   AppShell: () => (/* binding */ AppShell)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./api */ "./src/shared/api.js");
/* harmony import */ var _appBranding__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./appBranding */ "./src/shared/appBranding.js");
/* harmony import */ var _components_NavIcon__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./components/NavIcon */ "./src/shared/components/NavIcon.jsx");
/* harmony import */ var _components_IconRailTooltip__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./components/IconRailTooltip */ "./src/shared/components/IconRailTooltip.jsx");
/* harmony import */ var _useSidebarLayout__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./useSidebarLayout */ "./src/shared/useSidebarLayout.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * App shell layout — Direction 1 Structured Academic.
 * Mount inside #pr-root (see templates/app-shell.php).
 */








function getCurrentUser() {
  if (typeof window === 'undefined') {
    return null;
  }
  return window.prAppData?.currentUser ?? null;
}
function UserIdentity() {
  const user = getCurrentUser();
  if (!user) {
    return null;
  }
  const displayName = user.displayName?.trim() || 'Signed in user';
  const email = user.email || '';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    className: "pr-user-identity min-w-0 text-right",
    "aria-label": `Signed in as ${displayName}`,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
      className: "truncate text-sm font-medium text-text",
      children: displayName
    }), email ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
      className: "truncate text-xs text-text-muted",
      title: email,
      children: email
    }) : null]
  });
}
const authLinkClass = 'text-sm font-medium text-primary hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-offset-2';
function AuthActions() {
  const loginUrl = window.prAppData?.loginUrl;
  const logoutUrl = window.prAppData?.logoutUrl;
  const user = getCurrentUser();
  if (user && window.prAppData?.portalMode) {
    const handlePortalLogout = async () => {
      try {
        await (0,_api__WEBPACK_IMPORTED_MODULE_1__.post)('/portal/logout');
      } catch {
        // Session already gone — fall through to reload.
      }
      window.location.reload();
    };
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
      type: "button",
      className: authLinkClass,
      onClick: handlePortalLogout,
      children: "Log out"
    });
  }
  if (user && logoutUrl) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
      href: logoutUrl,
      rel: "nofollow",
      className: authLinkClass,
      children: "Log out"
    });
  }
  if (!user && loginUrl) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
      href: loginUrl,
      className: authLinkClass,
      children: "Log in"
    });
  }
  return null;
}
function SidebarCollapseButton({
  collapsed,
  onClick
}) {
  const label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
  const button = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
    type: "button",
    className: "pr-sidebar-collapse-btn",
    onClick: onClick,
    "aria-label": label,
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_components_NavIcon__WEBPACK_IMPORTED_MODULE_3__.Icon, {
      name: "chevron-right",
      className: ['h-5 w-5 transition-transform', collapsed ? '' : 'rotate-180'].join(' ')
    })
  });
  if (!collapsed) {
    return button;
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_components_IconRailTooltip__WEBPACK_IMPORTED_MODULE_4__.IconRailTooltip, {
    label: label,
    children: button
  });
}
function AppShell({
  variant = 'coordinator',
  children,
  sidebar,
  topNav
}) {
  const isCoordinator = variant === 'coordinator';
  const isLanding = variant === 'landing';
  const showIdentity = Boolean(getCurrentUser()) && !isLanding;
  const appHomeUrl = window.prAppData?.appHomeUrl;
  const wordmarkClass = 'pr-wordmark m-0 text-xl font-semibold leading-snug text-primary';
  const sidebarLayout = (0,_useSidebarLayout__WEBPACK_IMPORTED_MODULE_5__.useSidebarLayout)();
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
    sidebarWidthForA11y
  } = sidebarLayout;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!isCoordinator || isLg) {
      return undefined;
    }
    const onKeyDown = event => {
      if (event.key === 'Escape') {
        closeDrawer();
      }
    };
    window.addEventListener('keydown', onKeyDown);
    return () => window.removeEventListener('keydown', onKeyDown);
  }, [isCoordinator, isLg, closeDrawer]);
  const displayName = (0,_appBranding__WEBPACK_IMPORTED_MODULE_2__.getAppDisplayName)();
  const wordmark = !isCoordinator && appHomeUrl ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
    href: appHomeUrl,
    className: `${wordmarkClass} no-underline hover:underline`,
    children: displayName
  }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
    className: wordmarkClass,
    children: displayName
  });
  const sidebarNav = sidebar ? (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.cloneElement)(sidebar, {
    collapsed: isLg && collapsed
  }) : null;
  const showDrawerBackdrop = isCoordinator && !isLg && drawerOpen;
  const sidebarClasses = ['pr-sidebar', 'pr-scroll', isLg && collapsed ? 'pr-sidebar--collapsed' : '', !isLg ? 'pr-sidebar--drawer' : '', !isLg && drawerOpen ? 'pr-sidebar--drawer-open' : ''].filter(Boolean).join(' ');
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
      href: "#pr-main",
      className: "pr-skip-link",
      children: "Skip to main content"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      className: "pr-shell",
      "data-app": variant,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("header", {
        className: "pr-topbar",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "pr-topbar-inner",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "pr-topbar-start",
            children: [isCoordinator ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
              type: "button",
              className: "pr-sidebar-menu-btn",
              onClick: toggleDrawer,
              "aria-expanded": drawerOpen,
              "aria-controls": "pr-sidebar-nav",
              "aria-label": drawerOpen ? 'Close navigation menu' : 'Open navigation menu',
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_components_NavIcon__WEBPACK_IMPORTED_MODULE_3__.Icon, {
                name: "panel",
                className: "h-5 w-5"
              })
            }) : null, wordmark, topNav ? topNav : null]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "pr-topbar-actions flex shrink-0 items-center gap-3",
            children: [showIdentity ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(UserIdentity, {}) : null, !isLanding ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(AuthActions, {}) : null]
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "pr-body",
        children: [showDrawerBackdrop ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
          type: "button",
          className: "pr-sidebar-backdrop",
          onClick: closeDrawer,
          "aria-label": "Close navigation menu"
        }) : null, isCoordinator ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("nav", {
          id: "pr-sidebar-nav",
          "aria-label": "Main",
          className: sidebarClasses,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
            className: "pr-sidebar-inner",
            children: [isLg ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
              className: "pr-sidebar-toolbar",
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(SidebarCollapseButton, {
                collapsed: collapsed,
                onClick: toggleCollapsed
              })
            }) : null, sidebarNav]
          }), isLg && !collapsed ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("button", {
            type: "button",
            className: "pr-sidebar-resize-handle",
            role: "separator",
            "aria-orientation": "vertical",
            "aria-label": "Resize sidebar",
            "aria-valuemin": _useSidebarLayout__WEBPACK_IMPORTED_MODULE_5__.SIDEBAR_MIN_WIDTH,
            "aria-valuemax": _useSidebarLayout__WEBPACK_IMPORTED_MODULE_5__.SIDEBAR_MAX_WIDTH,
            "aria-valuenow": sidebarWidthForA11y,
            onPointerDown: onResizePointerDown,
            onPointerMove: onResizePointerMove,
            onPointerUp: onResizePointerUp,
            onPointerCancel: onResizePointerUp,
            onKeyDown: onResizeKeyDown
          }) : null]
        }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("main", {
          id: "pr-main",
          className: "pr-main pr-scroll",
          tabIndex: -1,
          children: children
        })]
      })]
    })]
  });
}

/***/ },

/***/ "./src/shared/api.js"
/*!***************************!*\
  !*** ./src/shared/api.js ***!
  \***************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   configureApi: () => (/* binding */ configureApi),
/* harmony export */   del: () => (/* binding */ del),
/* harmony export */   get: () => (/* binding */ get),
/* harmony export */   getBlob: () => (/* binding */ getBlob),
/* harmony export */   post: () => (/* binding */ post),
/* harmony export */   postMarkOverride: () => (/* binding */ postMarkOverride),
/* harmony export */   put: () => (/* binding */ put)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/**
 * Shared REST client — uses wp-api-fetch default root (wp-json/) + wp_rest nonce.
 * Paths are prefixed with project-reviews/v1 (do not add a second root URL middleware;
 * WordPress already registers one and it would overwrite our namespace).
 */

const API_NAMESPACE = '/project-reviews/v1';
function resolvePath(path) {
  const segment = path.startsWith('/') ? path : `/${path}`;
  return `${API_NAMESPACE}${segment}`;
}
function configureApi() {
  const nonce = window.prAppData?.nonce;
  if (nonce && typeof (_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default().createNonceMiddleware) === 'function') {
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default().use(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default().createNonceMiddleware(nonce));
  }
}
function get(path) {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: resolvePath(path)
  });
}
function post(path, data) {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: resolvePath(path),
    method: 'POST',
    data
  });
}
function postMarkOverride(markId, body) {
  return post(`/marks/${markId}/override`, body);
}
function put(path, data) {
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
    path: resolvePath(path),
    method: 'PUT',
    data
  });
}
function del(path, data) {
  const opts = {
    path: resolvePath(path),
    method: 'DELETE'
  };
  if (data !== undefined) {
    opts.data = data;
  }
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()(opts);
}

/**
 * Fetch a binary response (e.g. PDF export) with REST nonce auth.
 */
async function getBlob(path) {
  const root = window.prAppData?.root || '/wp-json';
  const url = `${root.replace(/\/$/, '')}${resolvePath(path)}`;
  const headers = {};
  const nonce = window.prAppData?.nonce;
  if (nonce) {
    headers['X-WP-Nonce'] = nonce;
  }
  const response = await fetch(url, {
    credentials: 'same-origin',
    headers
  });
  if (!response.ok) {
    let payload = {};
    try {
      payload = await response.json();
    } catch {
      payload = {};
    }
    const error = new Error(payload?.message || 'Request failed.');
    error.code = payload?.code;
    error.data = payload?.data;
    throw error;
  }
  return response;
}

/***/ },

/***/ "./src/shared/appBranding.js"
/*!***********************************!*\
  !*** ./src/shared/appBranding.js ***!
  \***********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getAppDisplayName: () => (/* binding */ getAppDisplayName),
/* harmony export */   getAppShortName: () => (/* binding */ getAppShortName)
/* harmony export */ });
const DEFAULT_APP_DISPLAY_NAME = 'Scorva: The Review Management System';
function getAppDisplayName() {
  const name = window.prAppData?.appDisplayName?.trim();
  return name || DEFAULT_APP_DISPLAY_NAME;
}
function getAppShortName() {
  const short = window.prAppData?.appShortName?.trim();
  if (short) {
    return short;
  }
  const full = getAppDisplayName();
  const idx = full.indexOf(':');
  return idx > 0 ? full.slice(0, idx).trim() : full;
}

/***/ },

/***/ "./src/shared/components/Button.jsx"
/*!******************************************!*\
  !*** ./src/shared/components/Button.jsx ***!
  \******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Button: () => (/* binding */ Button)
/* harmony export */ });
/* harmony import */ var _NavIcon__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./NavIcon */ "./src/shared/components/NavIcon.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


const VARIANT_CLASSES = {
  primary: 'bg-primary text-white hover:bg-primary-hover disabled:opacity-50',
  secondary: 'bg-surface-raised text-text border border-border hover:bg-surface disabled:opacity-50',
  ghost: 'bg-transparent text-primary hover:bg-chip-active-bg disabled:opacity-50',
  destructive: 'bg-danger text-white hover:opacity-90 disabled:opacity-50'
};
const SIZE_CLASSES = {
  sm: 'px-3 py-1.5 text-sm',
  md: 'px-4 py-2 text-sm',
  lg: 'px-6 py-3 text-base'
};
function Button({
  variant = 'primary',
  size = 'md',
  disabled = false,
  loading = false,
  type = 'button',
  onClick,
  children,
  className = '',
  icon,
  iconPosition = 'start',
  ...rest
}) {
  const isDisabled = disabled || loading;
  const iconEl = icon ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_NavIcon__WEBPACK_IMPORTED_MODULE_0__.Icon, {
    name: icon,
    className: "h-4 w-4 shrink-0"
  }) : null;
  const label = loading ? 'Loading…' : children;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("button", {
    type: type,
    disabled: isDisabled,
    onClick: onClick,
    className: ['inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary', VARIANT_CLASSES[variant] ?? VARIANT_CLASSES.primary, SIZE_CLASSES[size] ?? SIZE_CLASSES.md, className].filter(Boolean).join(' '),
    "aria-busy": loading || undefined,
    ...rest,
    children: [icon && iconPosition === 'start' ? iconEl : null, label, icon && iconPosition === 'end' ? iconEl : null]
  });
}

/***/ },

/***/ "./src/shared/components/IconRailTooltip.jsx"
/*!***************************************************!*\
  !*** ./src/shared/components/IconRailTooltip.jsx ***!
  \***************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   IconRailTooltip: () => (/* binding */ IconRailTooltip)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_dom__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react-dom */ "react-dom");
/* harmony import */ var react_dom__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_dom__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);



const TOOLTIP_GAP_PX = 8;
function getPortalTarget() {
  if (typeof document === 'undefined') {
    return null;
  }
  return document.body;
}

/**
 * Tooltip for icon-rail navigation (collapsed sidebar). Portaled to document.body
 * with styles from app-shell.css (not Tailwind — utilities are scoped to #pr-root).
 */
function IconRailTooltip({
  label,
  children
}) {
  const tooltipId = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useId)();
  const anchorRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const [visible, setVisible] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [position, setPosition] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    top: 0,
    left: 0
  });
  const updatePosition = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    const el = anchorRef.current;
    if (!el) {
      return;
    }
    const rect = el.getBoundingClientRect();
    setPosition({
      top: rect.top + rect.height / 2,
      left: rect.right + TOOLTIP_GAP_PX
    });
  }, []);
  const show = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    updatePosition();
    setVisible(true);
  }, [updatePosition]);
  const hide = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setVisible(false);
  }, []);
  const portalTarget = getPortalTarget();
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
      ref: anchorRef,
      className: "pr-icon-rail-anchor",
      onMouseEnter: show,
      onMouseLeave: hide,
      onFocus: show,
      onBlur: hide,
      children: children
    }), visible && portalTarget ? (0,react_dom__WEBPACK_IMPORTED_MODULE_1__.createPortal)(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
      id: tooltipId,
      role: "tooltip",
      className: "pr-icon-rail-tooltip",
      style: {
        top: `${position.top}px`,
        left: `${position.left}px`
      },
      children: label
    }), portalTarget) : null]
  });
}

/***/ },

/***/ "./src/shared/components/NavIcon.jsx"
/*!*******************************************!*\
  !*** ./src/shared/components/NavIcon.jsx ***!
  \*******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Icon: () => (/* binding */ Icon),
/* harmony export */   NavIcon: () => (/* binding */ NavIcon)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__);

const ICONS = {
  dashboard: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9.5Z"
  }),
  registry: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      d: "M16 18v-1a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v1"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("circle", {
      cx: "9",
      cy: "7",
      r: "3"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      d: "M20 8v6M23 11h-6"
    })]
  }),
  wizard: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M12 3v2m0 14v2M4.22 4.22l1.42 1.42m12.72 12.72 1.42 1.42M3 12h2m14 0h2M4.22 19.78l1.42-1.42M17.36 6.64l1.42-1.42"
  }),
  progress: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M4 19V9m6 10V5m6 14V11m6 8V3"
  }),
  rubrics: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "m9 11 2 2 4-4M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"
  }),
  reports: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M8 4h8a1 1 0 0 1 1 1v14l-5-3-5 3V5a1 1 0 0 1 1-1Z"
  }),
  audit: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      d: "M12 8v4l2.5 2.5"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("circle", {
      cx: "12",
      cy: "12",
      r: "9"
    })]
  }),
  close: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M7 11V7a5 5 0 0 1 10 0v4M6 11h12v10H6V11Z"
  }),
  'arrow-left': /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"
  }),
  pencil: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"
  }),
  plus: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M12 4.5v15m7.5-7.5h-15"
  }),
  lock: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"
  }),
  unlock: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M13.5 10.5V6.75a4.5 4.5 0 1 1 9 0v3.75M3.75 21h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H3.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"
  }),
  save: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0Z"
  }),
  'chevron-right': /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "m8.25 4.5 7.5 7.5-7.5 7.5"
  }),
  panel: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25A2.25 2.25 0 0 1 13.5 8.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z"
  }),
  settings: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      d: "M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
      strokeLinecap: "round",
      strokeLinejoin: "round",
      d: "M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
    })]
  }),
  users: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.21a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
  }),
  clipboard: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
  }),
  calendar: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("path", {
    strokeLinecap: "round",
    strokeLinejoin: "round",
    d: "M6.75 3v2.25M17.25 3v2.25M3 9.75h18M4.5 5.25h15a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5h-15a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z"
  })
};
function Icon({
  name,
  className = 'h-5 w-5 shrink-0'
}) {
  const paths = ICONS[name];
  if (!paths) {
    return null;
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("svg", {
    className: className,
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: "1.75",
    "aria-hidden": "true",
    children: paths
  });
}

/** @deprecated Use Icon — kept for coordinator nav imports */
function NavIcon({
  name
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(Icon, {
    name: name
  });
}

/***/ },

/***/ "./src/shared/components/PageHeader.jsx"
/*!**********************************************!*\
  !*** ./src/shared/components/PageHeader.jsx ***!
  \**********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PageHeader: () => (/* binding */ PageHeader)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__);

function PageHeader({
  title,
  description,
  actions
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)("header", {
    className: "mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)("div", {
      className: "min-w-0",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("h1", {
        className: "text-[32px] font-semibold leading-tight text-text",
        children: title
      }), description ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("p", {
        className: "mt-2 text-base text-text-muted",
        children: description
      }) : null]
    }), actions ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("div", {
      className: "flex shrink-0 flex-wrap items-center gap-2",
      children: actions
    }) : null]
  });
}

/***/ },

/***/ "./src/shared/useSidebarLayout.js"
/*!****************************************!*\
  !*** ./src/shared/useSidebarLayout.js ***!
  \****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SIDEBAR_DEFAULT_WIDTH: () => (/* binding */ SIDEBAR_DEFAULT_WIDTH),
/* harmony export */   SIDEBAR_LG_MEDIA: () => (/* binding */ SIDEBAR_LG_MEDIA),
/* harmony export */   SIDEBAR_MAX_WIDTH: () => (/* binding */ SIDEBAR_MAX_WIDTH),
/* harmony export */   SIDEBAR_MIN_WIDTH: () => (/* binding */ SIDEBAR_MIN_WIDTH),
/* harmony export */   SIDEBAR_STORAGE_COLLAPSED: () => (/* binding */ SIDEBAR_STORAGE_COLLAPSED),
/* harmony export */   SIDEBAR_STORAGE_WIDTH: () => (/* binding */ SIDEBAR_STORAGE_WIDTH),
/* harmony export */   useSidebarLayout: () => (/* binding */ useSidebarLayout)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);

const SIDEBAR_STORAGE_COLLAPSED = 'pr-sidebar-collapsed';
const SIDEBAR_STORAGE_WIDTH = 'pr-sidebar-width';
const SIDEBAR_DEFAULT_WIDTH = 240;
const SIDEBAR_MIN_WIDTH = 200;
const SIDEBAR_MAX_WIDTH = 400;
const SIDEBAR_LG_MEDIA = '(min-width: 1024px)';
function readCollapsed() {
  if (typeof window === 'undefined') {
    return false;
  }
  return window.localStorage.getItem(SIDEBAR_STORAGE_COLLAPSED) === '1';
}
function readWidthPx() {
  if (typeof window === 'undefined') {
    return SIDEBAR_DEFAULT_WIDTH;
  }
  const raw = parseInt(window.localStorage.getItem(SIDEBAR_STORAGE_WIDTH) || '', 10);
  if (Number.isNaN(raw)) {
    return SIDEBAR_DEFAULT_WIDTH;
  }
  return Math.min(SIDEBAR_MAX_WIDTH, Math.max(SIDEBAR_MIN_WIDTH, raw));
}
function clampWidth(value) {
  return Math.min(SIDEBAR_MAX_WIDTH, Math.max(SIDEBAR_MIN_WIDTH, value));
}
function useSidebarLayout() {
  const [collapsed, setCollapsed] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(readCollapsed);
  const [widthPx, setWidthPx] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(readWidthPx);
  const [drawerOpen, setDrawerOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [isLg, setIsLg] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(() => typeof window !== 'undefined' ? window.matchMedia(SIDEBAR_LG_MEDIA).matches : true);
  const [isDragging, setIsDragging] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const dragStartX = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(0);
  const dragStartWidth = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(SIDEBAR_DEFAULT_WIDTH);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const mq = window.matchMedia(SIDEBAR_LG_MEDIA);
    const onChange = () => {
      setIsLg(mq.matches);
      if (mq.matches) {
        setDrawerOpen(false);
      }
    };
    onChange();
    mq.addEventListener('change', onChange);
    return () => mq.removeEventListener('change', onChange);
  }, []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const root = document.getElementById('pr-root');
    if (!root) {
      return;
    }
    const effectiveWidth = collapsed && isLg ? 56 : widthPx;
    root.style.setProperty('--pr-layout-sidebar-width', `${effectiveWidth}px`);
  }, [collapsed, widthPx, isLg]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (isDragging) {
      document.body.classList.add('pr-sidebar-resizing');
    } else {
      document.body.classList.remove('pr-sidebar-resizing');
    }
    return () => document.body.classList.remove('pr-sidebar-resizing');
  }, [isDragging]);
  const persistCollapsed = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(next => {
    window.localStorage.setItem(SIDEBAR_STORAGE_COLLAPSED, next ? '1' : '0');
  }, []);
  const persistWidth = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(next => {
    window.localStorage.setItem(SIDEBAR_STORAGE_WIDTH, String(next));
  }, []);
  const toggleCollapsed = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setCollapsed(prev => {
      const next = !prev;
      persistCollapsed(next);
      return next;
    });
  }, [persistCollapsed]);
  const toggleDrawer = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setDrawerOpen(prev => !prev);
  }, []);
  const closeDrawer = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setDrawerOpen(false);
  }, []);
  const nudgeWidth = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(delta => {
    if (collapsed || !isLg) {
      return;
    }
    setWidthPx(prev => {
      const next = clampWidth(prev + delta);
      persistWidth(next);
      return next;
    });
  }, [collapsed, isLg, persistWidth]);
  const onResizePointerDown = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(event => {
    if (collapsed || !isLg || event.button !== 0) {
      return;
    }
    event.preventDefault();
    dragStartX.current = event.clientX;
    dragStartWidth.current = widthPx;
    setIsDragging(true);
    event.currentTarget.setPointerCapture(event.pointerId);
  }, [collapsed, isLg, widthPx]);
  const onResizePointerMove = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(event => {
    if (!isDragging) {
      return;
    }
    const delta = event.clientX - dragStartX.current;
    const next = clampWidth(dragStartWidth.current + delta);
    setWidthPx(next);
  }, [isDragging]);
  const onResizePointerUp = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(event => {
    if (!isDragging) {
      return;
    }
    setIsDragging(false);
    try {
      event.currentTarget.releasePointerCapture(event.pointerId);
    } catch {
      // Pointer may already be released.
    }
    setWidthPx(prev => {
      persistWidth(prev);
      return prev;
    });
  }, [isDragging, persistWidth]);
  const onResizeKeyDown = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(event => {
    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      nudgeWidth(-8);
    } else if (event.key === 'ArrowRight') {
      event.preventDefault();
      nudgeWidth(8);
    }
  }, [nudgeWidth]);
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
    sidebarWidthForA11y: collapsed && isLg ? 56 : widthPx
  };
}

/***/ },

/***/ "./src/shared/styles.css"
/*!*******************************!*\
  !*** ./src/shared/styles.css ***!
  \*******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "react-dom"
/*!***************************!*\
  !*** external "ReactDOM" ***!
  \***************************/
(module) {

module.exports = window["ReactDOM"];

/***/ },

/***/ "react/jsx-runtime"
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
(module) {

module.exports = window["ReactJSXRuntime"];

/***/ },

/***/ "@wordpress/api-fetch"
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
(module) {

module.exports = window["wp"]["apiFetch"];

/***/ },

/***/ "@wordpress/element"
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
(module) {

module.exports = window["wp"]["element"];

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!******************************!*\
  !*** ./src/landing/index.js ***!
  \******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _App__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./App */ "./src/landing/App.jsx");
/* harmony import */ var _shared_styles_css__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../shared/styles.css */ "./src/shared/styles.css");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * Landing entry — mounts on #pr-root (pr_app=landing).
 */




const root = document.getElementById('pr-root');
if (root) {
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createRoot)(root).render(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_App__WEBPACK_IMPORTED_MODULE_1__.LandingApp, {}));
}
})();

/******/ })()
;
//# sourceMappingURL=landing.js.map