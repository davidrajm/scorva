"use strict";
(globalThis["webpackChunkproject_reviews"] = globalThis["webpackChunkproject_reviews"] || []).push([["src_coordinator_pages_CloseSession_jsx"],{

/***/ "./src/coordinator/pages/CloseSession.jsx"
/*!************************************************!*\
  !*** ./src/coordinator/pages/CloseSession.jsx ***!
  \************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   CloseSession: () => (/* binding */ CloseSession)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_router_dom__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react-router-dom */ "./node_modules/react-router-dom/dist/index.js");
/* harmony import */ var react_router_dom__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react-router-dom */ "./node_modules/react-router/dist/index.js");
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_apiErrors__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../shared/apiErrors */ "./src/shared/apiErrors.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);






const PRE_CLOSE_STEPS = [{
  label: 'Review progress',
  path: 'progress',
  description: 'Confirm marking is complete across panels and reviewers.'
}, {
  label: 'Download reports',
  path: 'reports',
  search: '?tab=downloads',
  description: 'Export committee deliverables before accounts are disabled.'
}, {
  label: 'Check audit log',
  path: 'audit',
  description: 'Review overrides and governance actions for this project.'
}];
function CloseSession() {
  const {
    id
  } = (0,react_router_dom__WEBPACK_IMPORTED_MODULE_2__.useParams)();
  const navigate = (0,react_router_dom__WEBPACK_IMPORTED_MODULE_2__.useNavigate)();
  const basePath = `/session/${id}`;
  const [sessionTitle, setSessionTitle] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [hasEnteredScores, setHasEnteredScores] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [preview, setPreview] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [dialogOpen, setDialogOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [reopenDialogOpen, setReopenDialogOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [closing, setClosing] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [reopening, setReopening] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [success, setSuccess] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [backupLoading, setBackupLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [backupError, setBackupError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [deleteDialogOpen, setDeleteDialogOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [deleteDestructiveOpen, setDeleteDestructiveOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [deletePhrase, setDeletePhrase] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [deleting, setDeleting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [deleteError, setDeleteError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const loadPreview = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    const data = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.get)(`sessions/${id}/close-preview`);
    setPreview(data);
    return data;
  }, [id]);
  const canCloseProject = window.prAppData?.canCloseProject !== false;
  const canManageProjects = window.prAppData?.canManageProjects !== false;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    let cancelled = false;
    setLoading(true);
    setError('');
    (async () => {
      try {
        const [session, previewData] = await Promise.all([(0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.get)(`sessions/${id}`).catch(() => null), (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.get)(`sessions/${id}/close-preview`)]);
        if (cancelled) {
          return;
        }
        setSessionTitle(session?.title || '');
        setHasEnteredScores(session?.has_entered_scores === true);
        setPreview(previewData);
      } catch (err) {
        if (!cancelled) {
          setError(err?.message || 'Unable to load close summary.');
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [id]);
  const handleReopen = async () => {
    if (!canCloseProject) {
      return;
    }
    setError('');
    setReopening(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.post)(`sessions/${id}/reopen`);
      setSuccess('Project reopened. Reviewer portal access is restored.');
      setReopenDialogOpen(false);
      await loadPreview();
    } catch {
      setError('Failed to reopen project.');
    } finally {
      setReopening(false);
    }
  };
  const handleProjectBackup = async () => {
    setBackupError('');
    setBackupLoading(true);
    try {
      const response = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.getBlob)(`sessions/${id}/backup/download`);
      const blob = await response.blob();
      const disposition = response.headers.get('Content-Disposition') || '';
      const match = disposition.match(/filename="([^"]+)"/);
      const filename = match ? match[1] : 'project-reviews-backup.zip';
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    } catch (err) {
      setBackupError(err?.message || 'Project backup download failed.');
    } finally {
      setBackupLoading(false);
    }
  };
  const openDeleteDialog = () => {
    setDeleteError('');
    setDeletePhrase('');
    if (hasEnteredScores) {
      setDeleteDestructiveOpen(true);
    } else {
      setDeleteDialogOpen(true);
    }
  };
  const closeDeleteDialogs = () => {
    setDeleteDialogOpen(false);
    setDeleteDestructiveOpen(false);
    setDeletePhrase('');
    setDeleteError('');
  };
  const handleDeleteProject = async () => {
    if (!canManageProjects) {
      return;
    }
    setDeleteError('');
    setDeleting(true);
    try {
      const payload = hasEnteredScores ? {
        confirm_label: deletePhrase.trim()
      } : undefined;
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.del)(`sessions/${id}`, payload);
      const deletedTitle = sessionTitle.trim() || 'Project';
      closeDeleteDialogs();
      navigate('/', {
        state: {
          notice: `“${deletedTitle}” was permanently deleted.`
        }
      });
    } catch (err) {
      setDeleteError((0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_4__.parseApiErrorMessage)(err, 'Could not delete project.'));
    } finally {
      setDeleting(false);
    }
  };
  const phraseMatchesDelete = deletePhrase.trim() === String(sessionTitle).trim();
  const handleClose = async () => {
    if (!canCloseProject) {
      return;
    }
    setError('');
    setClosing(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.post)(`sessions/${id}/close`);
      setSuccess('Project closed. Reviewer portal access is suspended.');
      setDialogOpen(false);
      await loadPreview();
    } catch {
      setError('Failed to close project.');
    } finally {
      setClosing(false);
    }
  };
  const isClosed = preview?.status === 'closed';
  const credentialedReviewers = preview?.credentialed_reviewers ?? 0;
  const consequences = ['Project status will change to closed.', `Reviewer portal access will be suspended — ${credentialedReviewers} credentialed reviewer${credentialedReviewers === 1 ? '' : 's'} will be blocked from logging in.`, 'Marks already submitted are preserved.'];
  const reopenConsequences = ['Project status will return to active (or draft if it was draft before close).', 'Reviewer portal access will be restored — credentialed reviewers can log in again.', 'Marking can resume where rubric, assignment, and freeze rules allow edits.', 'Does not unlock coordinator marks lock or reviewer submitted scores.'];
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.PageHeader, {
      title: "Close project",
      description: sessionTitle ? `End marking for “${sessionTitle}” and suspend reviewer portal access.` : 'End marking and suspend reviewer portal access.',
      actions: preview?.status ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.StatusChip, {
        variant: preview.status
      }) : null
    }), success && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.Notice, {
      variant: "success",
      children: success
    }), error && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.Notice, {
      variant: "error",
      children: error
    }), backupError && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.Notice, {
      variant: "error",
      children: backupError
    }), loading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.ContentLoadingRegion, {
      busy: true,
      variant: "inline",
      label: "Loading close project",
      className: "mt-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.PageContentSkeleton, {
        rows: 4
      })
    }) : null, !loading && preview && isClosed && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("section", {
      "aria-labelledby": "reopen-summary-heading",
      className: "max-w-xl space-y-6",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h2", {
        id: "reopen-summary-heading",
        className: "text-sm font-semibold uppercase tracking-wide text-text-muted",
        children: "Reopen project"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "space-y-4 rounded-md border border-border bg-surface-raised p-6",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          className: "text-sm text-text",
          children: "This project is closed. Marking is locked and reviewer portal access is suspended."
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("dl", {
          className: "grid grid-cols-2 gap-x-4 gap-y-3 text-sm",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("dt", {
            className: "text-text-muted",
            children: "Status"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("dd", {
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.StatusChip, {
              variant: preview.status
            })
          })]
        }), !canCloseProject ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.Notice, {
          variant: "warning",
          children: "You can view this summary but do not have permission to reopen projects. Ask an administrator to grant the close-project capability."
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
          variant: "primary",
          onClick: () => setReopenDialogOpen(true),
          children: "Reopen project\u2026"
        })]
      })]
    }), !loading && preview && !isClosed && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      className: "space-y-8",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("section", {
        "aria-labelledby": "close-pre-close-heading",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h2", {
          id: "close-pre-close-heading",
          className: "text-sm font-semibold uppercase tracking-wide text-text-muted",
          children: "Before you close"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "mt-4 max-w-xl",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
            variant: "secondary",
            onClick: handleProjectBackup,
            disabled: backupLoading,
            children: backupLoading ? 'Preparing backup…' : 'Download project backup (ZIP)'
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
            className: "mt-2 text-sm text-text-muted",
            children: "Includes plugin data for this project and Excel reports. Store off-site before closing or uninstalling the plugin."
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("ul", {
          className: "mt-4 grid gap-3 md:grid-cols-3",
          children: PRE_CLOSE_STEPS.map(step => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("li", {
            className: "rounded-md border border-border bg-surface-raised p-4",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(react_router_dom__WEBPACK_IMPORTED_MODULE_1__.Link, {
              to: step.search ? {
                pathname: `${basePath}/${step.path}`,
                search: step.search
              } : `${basePath}/${step.path}`,
              className: "text-sm font-medium text-primary hover:underline",
              children: step.label
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
              className: "mt-2 text-sm text-text-muted",
              children: step.description
            })]
          }, step.path + (step.search ?? '')))
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("section", {
        "aria-labelledby": "close-summary-heading",
        className: "max-w-xl",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h2", {
          id: "close-summary-heading",
          className: "text-sm font-semibold uppercase tracking-wide text-text-muted",
          children: "Close summary"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "mt-4 space-y-4 rounded-md border border-border bg-surface-raised p-6",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("dl", {
            className: "grid grid-cols-2 gap-x-4 gap-y-3 text-sm",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("dt", {
              className: "text-text-muted",
              children: "Status"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("dd", {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.StatusChip, {
                variant: preview.status
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("dt", {
              className: "text-text-muted",
              children: "Open marks"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("dd", {
              className: "font-medium text-text",
              children: [preview.open_marks, preview.open_marks > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
                className: "mt-1 block text-xs font-normal text-text-muted",
                children: "Incomplete marking may remain in exports; closing does not delete data."
              }) : null]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("dt", {
              className: "text-text-muted",
              children: "Credentialed reviewers"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("dd", {
              className: "font-medium text-text",
              children: [preview.credentialed_reviewers, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
                className: "mt-1 block text-xs font-normal text-text-muted",
                children: "Portal access is suspended when the project is closed."
              })]
            })]
          }), !canCloseProject ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.Notice, {
            variant: "warning",
            children: "You can view this summary but do not have permission to close projects. Ask an administrator to grant the close-project capability."
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
            variant: "destructive",
            icon: "close",
            onClick: () => setDialogOpen(true),
            children: "Close project\u2026"
          })]
        })]
      })]
    }), !loading && preview && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("section", {
      "aria-labelledby": "delete-project-heading",
      className: "mt-10 max-w-xl border-t border-border pt-8",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h2", {
        id: "delete-project-heading",
        className: "text-sm font-semibold uppercase tracking-wide text-text-muted",
        children: "Delete project"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "mt-4 space-y-4 rounded-md border border-danger/30 bg-surface-raised p-6",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          className: "text-sm text-text",
          children: "Permanently remove this project and all of its data (roster, panels, review rounds, rubrics, assignments, marks, freezes, and project-scoped audit). WordPress user accounts are not deleted."
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
          className: "text-sm text-text-muted",
          children: [isClosed ? 'Download a project backup from Reports → Downloads before deleting if you need an archive.' : 'Download a project backup above before deleting if you need an archive.', ' ', "This cannot be undone."]
        }), !canManageProjects ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.Notice, {
          variant: "warning",
          children: "You do not have permission to delete projects. Ask an administrator to grant project management capability."
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.Button, {
          variant: "destructive",
          type: "button",
          "data-testid": "pr-delete-project",
          onClick: openDeleteDialog,
          children: "Delete project\u2026"
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.ConfirmDialog, {
      open: reopenDialogOpen,
      title: "Reopen this project?",
      consequences: reopenConsequences,
      confirmLabel: reopening ? 'Reopening…' : 'Reopen project',
      confirmVariant: "primary",
      confirmDisabled: reopening,
      onCancel: () => setReopenDialogOpen(false),
      onConfirm: handleReopen
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.ConfirmDialog, {
      open: deleteDialogOpen,
      title: sessionTitle ? `Delete ${sessionTitle}?` : 'Delete project?',
      consequences: ['Student roster enrolment, panels, review rounds, rubrics, and assignments for this project will be permanently removed.', 'Draft mark rows, panel freezes, unfreeze requests, and project-scoped audit entries will be deleted.', 'WordPress user accounts are not deleted.'],
      confirmLabel: deleting ? 'Deleting…' : 'Delete project',
      confirmVariant: "destructive",
      confirmDisabled: deleting,
      onCancel: closeDeleteDialogs,
      onConfirm: handleDeleteProject,
      children: deleteError ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
        className: "text-sm text-danger",
        children: deleteError
      }) : null
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.ConfirmDialog, {
      open: deleteDestructiveOpen,
      title: sessionTitle ? `Delete ${sessionTitle} and all scores?` : 'Delete project?',
      consequences: ['All entered marks and derived scores for this project will be permanently removed.', 'Student roster, panels, review rounds, rubrics, assignments, freezes, and project-scoped audit will be deleted.', 'Reviewer portal credentials for this project are permanently removed.', 'WordPress user accounts are not deleted.'],
      confirmLabel: deleting ? 'Deleting…' : 'Delete project and scores',
      confirmVariant: "destructive",
      confirmDisabled: deleting || !phraseMatchesDelete,
      onCancel: closeDeleteDialogs,
      onConfirm: handleDeleteProject,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "space-y-2 text-sm text-text-muted",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
          children: ["Type the exact project title", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
            className: "text-text",
            children: sessionTitle
          }), ' ', "to confirm."]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
          type: "text",
          className: "w-full rounded-md border border-border bg-surface px-3 py-2 text-text",
          value: deletePhrase,
          onChange: e => setDeletePhrase(e.target.value),
          autoComplete: "off",
          "data-testid": "pr-delete-project-confirm-input",
          "aria-label": "Type project title to confirm deletion"
        }), deleteError ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
          className: "text-danger",
          children: deleteError
        }) : null]
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_5__.ConfirmDialog, {
      open: dialogOpen,
      title: "Close this project?",
      consequences: consequences,
      confirmLabel: closing ? 'Closing…' : 'Close project',
      confirmVariant: "destructive",
      confirmDisabled: closing,
      onCancel: () => setDialogOpen(false),
      onConfirm: handleClose
    })]
  });
}

/***/ },

/***/ "./src/shared/apiErrors.js"
/*!*********************************!*\
  !*** ./src/shared/apiErrors.js ***!
  \*********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   parseApiErrorMessage: () => (/* binding */ parseApiErrorMessage)
/* harmony export */ });
/**
 * Extract a user-facing message from a @wordpress/api-fetch error.
 */
function parseApiErrorMessage(error, fallback) {
  if (!error) {
    return fallback;
  }
  if (typeof error.message === 'string' && error.message !== '') {
    return error.message;
  }
  const data = error.data;
  if (data && typeof data.message === 'string' && data.message !== '') {
    return data.message;
  }
  return fallback;
}

/***/ }

}]);
//# sourceMappingURL=src_coordinator_pages_CloseSession_jsx.js.map?ver=687a00be74fabc3b9420