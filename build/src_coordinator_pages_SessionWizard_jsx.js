"use strict";
(globalThis["webpackChunkproject_reviews"] = globalThis["webpackChunkproject_reviews"] || []).push([["src_coordinator_pages_SessionWizard_jsx"],{

/***/ "./src/coordinator/components/CorrectAttendanceDialog.jsx"
/*!****************************************************************!*\
  !*** ./src/coordinator/components/CorrectAttendanceDialog.jsx ***!
  \****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   CorrectAttendanceDialog: () => (/* binding */ CorrectAttendanceDialog)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/apiErrors */ "./src/shared/apiErrors.js");
/* harmony import */ var _shared_markErrors__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/markErrors */ "./src/shared/markErrors.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






const MIN_REASON_LENGTH = 10;
function CorrectAttendanceDialog({
  open,
  sessionId,
  reviewId,
  studentId,
  reviewLabel,
  currentStatus = 'present',
  onClose,
  onSuccess
}) {
  const [targetStatus, setTargetStatus] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('absent');
  const [reason, setReason] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [saving, setSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!open) {
      return;
    }
    setTargetStatus(currentStatus === 'absent' ? 'present' : 'absent');
    setReason('');
    setError(null);
    setSaving(false);
  }, [open, currentStatus]);
  const handleClose = () => {
    onClose?.();
  };
  const handleConfirm = async () => {
    const trimmed = reason.trim();
    if (trimmed.length < MIN_REASON_LENGTH) {
      setError('Please enter a reason of at least 10 characters.');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/reviews/${reviewId}/students/${studentId}/attendance`, {
        attendance_status: targetStatus,
        reason: trimmed
      });
      onSuccess?.(targetStatus);
      handleClose();
    } catch (err) {
      setError((0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Could not correct attendance. Please try again.'));
      setSaving(false);
    }
  };
  const consequences = ['Updates attendance for every reviewer on this student’s panel for this review.'];
  if (targetStatus === 'absent') {
    consequences.push('Clears all criterion scores for every panel reviewer on this student for this review.');
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.ConfirmDialog, {
    open: open,
    title: "Correct attendance",
    consequences: consequences,
    confirmLabel: saving ? 'Saving…' : 'Correct attendance',
    confirmDisabled: saving,
    onConfirm: handleConfirm,
    onCancel: handleClose,
    children: [reviewLabel ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("p", {
      className: "text-sm text-text-muted",
      children: ["Review: ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
        className: "text-text",
        children: reviewLabel
      })]
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("p", {
      className: "mt-2 text-sm text-text-muted",
      children: ["Current attendance:", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {
        className: "font-medium text-text",
        children: (0,_shared_markErrors__WEBPACK_IMPORTED_MODULE_3__.formatAttendanceConflictLabel)(currentStatus)
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("fieldset", {
      className: "mt-4",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("legend", {
        className: "text-sm font-medium text-text",
        children: "Set attendance to"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        className: "mt-2 flex flex-wrap gap-4",
        role: "radiogroup",
        "aria-label": "Corrected attendance",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("label", {
          className: "inline-flex items-center gap-2 text-sm text-text",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("input", {
            type: "radio",
            name: "correct-attendance-target",
            value: "present",
            checked: targetStatus === 'present',
            disabled: saving,
            onChange: () => setTargetStatus('present')
          }), "Present"]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("label", {
          className: "inline-flex items-center gap-2 text-sm text-text",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("input", {
            type: "radio",
            name: "correct-attendance-target",
            value: "absent",
            checked: targetStatus === 'absent',
            disabled: saving,
            onChange: () => setTargetStatus('absent')
          }), "Absent"]
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("label", {
      className: "mt-4 block text-sm font-medium text-text",
      htmlFor: "attendance-correction-reason",
      children: ["Reason", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("textarea", {
        id: "attendance-correction-reason",
        className: "mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm",
        rows: 3,
        value: reason,
        disabled: saving,
        "aria-required": "true",
        placeholder: "Explain why attendance is being corrected (min. 10 characters).",
        onChange: event => setReason(event.target.value)
      })]
    }), error ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      className: "mt-3",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Notice, {
        variant: "error",
        children: error
      })
    }) : null]
  });
}

/***/ },

/***/ "./src/coordinator/components/CsvImportMapper.jsx"
/*!********************************************************!*\
  !*** ./src/coordinator/components/CsvImportMapper.jsx ***!
  \********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   CsvImportMapper: () => (/* binding */ CsvImportMapper)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/apiErrors */ "./src/shared/apiErrors.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var _shared_reviewerImportRows__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../shared/reviewerImportRows */ "./src/shared/reviewerImportRows.js");
/* harmony import */ var _shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../shared/TableScrollViewport */ "./src/shared/TableScrollViewport.jsx");
/* harmony import */ var _shared_tableStyles__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../shared/tableStyles */ "./src/shared/tableStyles.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);








const STORAGE_PREFIX = 'pr_csv_mapping_';
const STUDENT_REQUIRED = [{
  key: 'reg_no',
  label: 'Registration number'
}, {
  key: 'name',
  label: 'Name'
}];
const STUDENT_OPTIONAL = [{
  key: 'program',
  label: 'Program'
}, {
  key: 'batch',
  label: 'Batch'
}];
const IMPORT_TYPE_CONFIG = {
  students: {
    title: 'Import students from CSV',
    description: 'Map columns, preview the first three rows, then import.',
    required: STUDENT_REQUIRED,
    optional: STUDENT_OPTIONAL,
    submitLabel: 'Import students',
    supportsDuplicates: true
  },
  'session-enrol': {
    title: 'Add Student from CSV',
    description: 'Map registration numbers and panel columns. Include name for new students; program and batch are optional.',
    required: [{
      key: 'reg_no',
      label: 'Registration number'
    }, {
      key: 'panel',
      label: 'Panel'
    }],
    optional: [{
      key: 'name',
      label: 'Name'
    }, {
      key: 'program',
      label: 'Program'
    }, {
      key: 'batch',
      label: 'Batch'
    }, {
      key: 'project_title',
      label: 'Project title'
    }, {
      key: 'guide_emp_id',
      label: 'Guide employee ID'
    }, {
      key: 'guide_name',
      label: 'Guide name'
    }],
    submitLabel: 'Import students',
    supportsDuplicates: false
  },
  'faculty-accounts': {
    title: 'Import faculty accounts from CSV',
    description: 'Map employee ID, name, and email. Accounts are created without sending email.',
    required: [{
      key: 'empId',
      label: 'Employee ID'
    }, {
      key: 'name',
      label: 'Name'
    }, {
      key: 'email',
      label: 'Email'
    }],
    optional: [{
      key: 'designation',
      label: 'Designation'
    }, {
      key: 'gender',
      label: 'Gender'
    }],
    submitLabel: 'Import accounts',
    supportsDuplicates: true,
    duplicateKeys: ['empId', 'email']
  },
  'panel-reviewers': {
    title: 'Import panel reviewers from CSV',
    description: 'Use the template (one row per panel with reviewer_1, reviewer_1_email, …) or long format: panel, reviewer name, and email per row.',
    required: [{
      key: 'panel',
      label: 'Panel name or number'
    }],
    optional: [{
      key: 'reviewer_name',
      label: 'Reviewer name (long format)'
    }, {
      key: 'email',
      label: 'Email (long format)'
    }, {
      key: 'weight',
      label: 'Weight (long format)'
    }, {
      key: 'panel_coordinator',
      label: 'Panel coordinator (1, yes, true)'
    }],
    submitLabel: 'Import reviewers',
    supportsDuplicates: false,
    supportsReplaceChoice: true,
    wideFormat: true
  }
};
function parseCsv(text) {
  const rawLines = text.split(/\r?\n/);
  let headerLineIndex = -1;
  for (let i = 0; i < rawLines.length; i++) {
    if (rawLines[i].trim() !== '') {
      headerLineIndex = i;
      break;
    }
  }
  if (headerLineIndex < 0) {
    return {
      headers: [],
      rows: []
    };
  }
  const headers = rawLines[headerLineIndex].split(',').map(h => h.trim());
  const rows = [];
  for (let lineIndex = headerLineIndex + 1; lineIndex < rawLines.length; lineIndex++) {
    const line = rawLines[lineIndex].trim();
    if (line === '') {
      continue;
    }
    const values = line.split(',').map(v => v.trim());
    const row = {
      _csv_row: lineIndex + 1
    };
    headers.forEach((header, index) => {
      row[header] = values[index] ?? '';
    });
    rows.push(row);
  }
  return {
    headers,
    rows
  };
}
function formatCsvRowRefs(rowNumbers) {
  return rowNumbers.map(row => `Row ${row}`).join(', ');
}
function loadMapping(importType) {
  try {
    const raw = localStorage.getItem(`${STORAGE_PREFIX}${importType}`);
    return raw ? JSON.parse(raw) : {};
  } catch {
    return {};
  }
}
function saveMapping(importType, mapping) {
  localStorage.setItem(`${STORAGE_PREFIX}${importType}`, JSON.stringify(mapping));
}
function applyMapping(rows, mapping, customFieldKeys) {
  return rows.map(row => {
    const mapped = {};
    Object.entries(mapping).forEach(([target, source]) => {
      if (source && row[source] !== undefined) {
        mapped[target] = row[source];
      }
    });
    customFieldKeys.forEach(key => {
      const source = mapping[key];
      if (source && row[source] !== undefined) {
        mapped[key] = row[source];
      }
    });
    return mapped;
  });
}
function hasWideReviewerColumns(headers) {
  return headers.some(header => /^reviewer_\d+$/i.test(header.trim().replace(/\s+/g, '_')));
}
function findDuplicateRegNos(rows) {
  const seen = new Set();
  const duplicates = new Set();
  rows.forEach(row => {
    const reg = (row.reg_no ?? '').trim();
    if (!reg) {
      return;
    }
    if (seen.has(reg)) {
      duplicates.add(reg);
    }
    seen.add(reg);
  });
  return [...duplicates];
}
function findDuplicateFieldValues(rows, field) {
  const seen = new Set();
  const duplicates = new Set();
  rows.forEach(row => {
    const value = (row[field] ?? '').trim();
    if (!value) {
      return;
    }
    const key = field === 'email' ? value.toLowerCase() : value;
    if (seen.has(key)) {
      duplicates.add(value);
    }
    seen.add(key);
  });
  return [...duplicates];
}
function CsvImportMapper({
  importType = 'students',
  sessionId = null,
  customFields = [],
  existingReviewerCount = 0,
  onComplete,
  onImportSuccess = null,
  onDownloadTemplate = null,
  templateDownloadLabel = 'Download template CSV'
}) {
  const typeConfig = IMPORT_TYPE_CONFIG[importType] ?? IMPORT_TYPE_CONFIG.students;
  const [csvText, setCsvText] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [mapping, setMapping] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(() => loadMapping(importType));
  const [duplicatePolicy, setDuplicatePolicy] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('skip');
  const [showDuplicateChoice, setShowDuplicateChoice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [importMode, setImportMode] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('append');
  const [showReplaceChoice, setShowReplaceChoice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [showReviewerConflictChoice, setShowReviewerConflictChoice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [reviewerConflictsAcknowledged, setReviewerConflictsAcknowledged] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [pendingRows, setPendingRows] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [importing, setImporting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const fileInputRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const resetImportForm = () => {
    setCsvText('');
    setPendingRows([]);
    setShowDuplicateChoice(false);
    setShowReplaceChoice(false);
    setShowReviewerConflictChoice(false);
    setReviewerConflictsAcknowledged(false);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };
  const {
    headers,
    rows
  } = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => parseCsv(csvText), [csvText]);
  const previewRows = rows.slice(0, 3);
  const wideReviewerFormat = importType === 'panel-reviewers' && hasWideReviewerColumns(headers);
  const targets = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    const base = [...typeConfig.required, ...typeConfig.optional];
    if (importType !== 'students') {
      return base;
    }
    return [...base, ...customFields.map(field => ({
      key: field.field_key,
      label: field.label || field.field_key
    }))];
  }, [customFields, importType, typeConfig]);
  const customFieldKeys = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => customFields.map(field => field.field_key), [customFields]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (headers.length === 0) {
      return;
    }
    setMapping(current => {
      const next = {
        ...current
      };
      let changed = false;
      targets.forEach(target => {
        if (next[target.key]) {
          return;
        }
        if (importType === 'panel-reviewers' && hasWideReviewerColumns(headers) && target.key !== 'panel') {
          return;
        }
        const match = headers.find(header => header.toLowerCase().replace(/\s+/g, '_') === target.key.toLowerCase());
        if (match) {
          next[target.key] = match;
          changed = true;
        }
      });
      return changed ? next : current;
    });
  }, [headers, targets, importType]);
  const handleMappingChange = (targetKey, sourceColumn) => {
    const next = {
      ...mapping,
      [targetKey]: sourceColumn
    };
    setMapping(next);
    saveMapping(importType, next);
  };
  const handleFileChange = event => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      setCsvText(String(reader.result ?? ''));
      setNotice(null);
      setShowDuplicateChoice(false);
      setShowReplaceChoice(false);
      setShowReviewerConflictChoice(false);
      setReviewerConflictsAcknowledged(false);
    };
    reader.readAsText(file);
  };
  const runImport = async (mappedRows, policy) => {
    setImporting(true);
    setNotice(null);
    try {
      let result;
      if (importType === 'session-enrol') {
        if (!sessionId) {
          throw new Error('Project id is required.');
        }
        result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/enrol`, {
          rows: mappedRows
        });
      } else if (importType === 'panel-reviewers') {
        if (!sessionId) {
          throw new Error('Project id is required.');
        }
        result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/reviewers/import`, {
          rows: mappedRows,
          import_mode: policy
        });
      } else if (importType === 'faculty-accounts') {
        result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)('/faculty-accounts/import', {
          rows: mappedRows,
          duplicate_policy: policy
        });
      } else {
        result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)('/students/import', {
          rows: mappedRows,
          duplicate_policy: policy
        });
      }
      let message;
      if (importType === 'session-enrol') {
        message = `Enrolment import: ${result.enrolled ?? 0} added, ${result.updated ?? 0} updated, ${result.failed ?? 0} failed.`;
      } else if (importType === 'panel-reviewers') {
        const cleared = result.cleared ?? 0;
        const clearedNote = cleared > 0 ? `, ${cleared} removed` : '';
        message = `Reviewer import: ${result.imported ?? 0} added, ${result.updated ?? 0} updated${clearedNote}, ${result.failed ?? 0} failed.`;
      } else if (importType === 'faculty-accounts') {
        message = `Faculty import: ${result.imported ?? 0} added, ${result.updated ?? 0} updated, ${result.skipped ?? 0} skipped, ${result.failed ?? 0} failed.`;
      } else {
        const imported = result.imported ?? 0;
        const updated = result.updated ?? 0;
        const skipped = result.skipped ?? 0;
        const failed = result.failed ?? 0;
        message = `Import complete: ${imported} added, ${updated} updated, ${skipped} skipped, ${failed} failed.`;
      }
      const failed = result.failed ?? 0;
      const variant = failed > 0 ? 'warning' : 'success';
      const successPayload = {
        variant,
        message,
        errorCsv: result.error_csv || ''
      };
      if (onImportSuccess) {
        onImportSuccess(successPayload);
      } else {
        setNotice(successPayload);
      }
      resetImportForm();
      onComplete?.(successPayload);
    } catch (err) {
      setNotice({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Import failed. Check your file and try again.')
      });
    } finally {
      setImporting(false);
      setShowDuplicateChoice(false);
      setShowReplaceChoice(false);
      setShowReviewerConflictChoice(false);
    }
  };
  const mapRowsForImport = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    if (wideReviewerFormat) {
      return rows.map((row, index) => {
        const panelColumn = mapping.panel;
        const panelValue = panelColumn && row[panelColumn] !== undefined ? row[panelColumn] : row.panel ?? '';
        return {
          ...row,
          panel: panelValue,
          _csv_row: row._csv_row ?? index + 2
        };
      });
    }
    return rows.map((row, index) => ({
      ...row,
      ...applyMapping([row], mapping, customFieldKeys)[0],
      _csv_row: row._csv_row ?? index + 2
    }));
  }, [wideReviewerFormat, rows, mapping, customFieldKeys]);
  const reviewerConflicts = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    if (importType !== 'panel-reviewers' || rows.length === 0) {
      return [];
    }
    return (0,_shared_reviewerImportRows__WEBPACK_IMPORTED_MODULE_4__.findReviewerEmailPanelConflicts)(mapRowsForImport());
  }, [importType, rows.length, mapRowsForImport]);
  const proceedAfterReviewerConflictCheck = mappedRows => {
    let duplicates = [];
    if (typeConfig.supportsDuplicates) {
      if (importType === 'faculty-accounts') {
        const empDupes = findDuplicateFieldValues(mappedRows, 'empId');
        const emailDupes = findDuplicateFieldValues(mappedRows, 'email');
        duplicates = [...empDupes, ...emailDupes];
      } else {
        duplicates = findDuplicateRegNos(mappedRows);
      }
    }
    if (duplicates.length > 0 && !showDuplicateChoice) {
      setPendingRows(mappedRows);
      setShowDuplicateChoice(true);
      return;
    }
    if (typeConfig.supportsReplaceChoice && existingReviewerCount > 0 && !showReplaceChoice) {
      setPendingRows(mappedRows);
      setShowReplaceChoice(true);
      return;
    }
    const reviewerPolicy = importType === 'panel-reviewers' ? importMode : duplicatePolicy;
    runImport(mappedRows, reviewerPolicy);
  };
  const handleSubmit = () => {
    const mappedRows = mapRowsForImport();
    if (importType === 'panel-reviewers' && reviewerConflicts.length > 0 && !showReviewerConflictChoice) {
      setPendingRows(mappedRows);
      setShowReviewerConflictChoice(true);
      return;
    }
    proceedAfterReviewerConflictCheck(mappedRows);
  };
  const handleConfirmReviewerConflicts = () => {
    setShowReviewerConflictChoice(false);
    setReviewerConflictsAcknowledged(true);
    proceedAfterReviewerConflictCheck(pendingRows);
  };
  const handleConfirmDuplicates = () => {
    if (importType === 'panel-reviewers' && (0,_shared_reviewerImportRows__WEBPACK_IMPORTED_MODULE_4__.findReviewerEmailPanelConflicts)(pendingRows).length > 0 && !showReviewerConflictChoice) {
      setShowReviewerConflictChoice(true);
      return;
    }
    if (typeConfig.supportsReplaceChoice && existingReviewerCount > 0 && !showReplaceChoice) {
      setShowReplaceChoice(true);
      return;
    }
    const reviewerPolicy = importType === 'panel-reviewers' ? importMode : duplicatePolicy;
    runImport(pendingRows, reviewerPolicy);
  };
  const handleConfirmReplaceChoice = () => {
    if (!reviewerConflictsAcknowledged && (0,_shared_reviewerImportRows__WEBPACK_IMPORTED_MODULE_4__.findReviewerEmailPanelConflicts)(pendingRows).length > 0) {
      setShowReviewerConflictChoice(true);
      return;
    }
    runImport(pendingRows, importMode);
  };
  const downloadErrorCsv = () => {
    if (!notice?.errorCsv) {
      return;
    }
    const blob = new Blob([notice.errorCsv], {
      type: 'text/csv;charset=utf-8'
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'import-errors.csv';
    link.click();
    URL.revokeObjectURL(url);
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
    className: "mt-6 rounded-md border border-border bg-surface-raised p-4 shadow-card",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("h3", {
      className: "text-lg font-semibold text-text",
      children: typeConfig.title
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
      className: "mt-1 text-sm text-text-muted",
      children: typeConfig.description
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      className: "mt-4",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("label", {
        className: "block text-sm font-medium text-text",
        htmlFor: `csv-file-${importType}`,
        children: "CSV file"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("input", {
        ref: fileInputRef,
        id: `csv-file-${importType}`,
        type: "file",
        accept: ".csv,text/csv",
        onChange: handleFileChange,
        className: "mt-1 block w-full text-sm text-text"
      }), importType === 'students' && window.prAppData?.studentImportTemplateUrl ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("p", {
        className: "mt-2 text-sm text-text-muted",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("a", {
          href: window.prAppData.studentImportTemplateUrl,
          download: "students-import-template.csv",
          className: "font-medium text-primary hover:underline",
          children: "Download template CSV"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
          children: [' ', "\u2014 sample reg. nos. like 25MDT1001 (academic year + programme)"]
        })]
      }) : null, importType === 'session-enrol' && window.prAppData?.sessionEnrolTemplateUrl ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("p", {
        className: "mt-2 text-sm text-text-muted",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("a", {
          href: window.prAppData.sessionEnrolTemplateUrl,
          download: "session-enrol-template.csv",
          className: "font-medium text-primary hover:underline",
          children: "Download enrol template CSV"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
          children: " \u2014 reg. no, panel, and optional project title"
        })]
      }) : null, importType === 'panel-reviewers' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("p", {
        className: "mt-2 text-sm text-text-muted",
        children: [onDownloadTemplate ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("button", {
          type: "button",
          onClick: onDownloadTemplate,
          className: "font-medium text-primary hover:underline",
          children: templateDownloadLabel
        }) : window.prAppData?.reviewerImportTemplateUrl ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("a", {
          href: window.prAppData.reviewerImportTemplateUrl,
          download: "reviewers-import-template.csv",
          className: "font-medium text-primary hover:underline",
          children: templateDownloadLabel
        }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
          children: [' ', "\u2014 one row per panel; existing reviewers are prefilled when available."]
        })]
      }) : null]
    }), headers.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
        className: "mt-4 grid gap-3 sm:grid-cols-2",
        children: targets.map(target => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("label", {
            className: "block text-sm font-medium text-text",
            htmlFor: `map-${target.key}`,
            children: [target.label, typeConfig.required.some(item => item.key === target.key) ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
              className: "text-danger",
              children: " *"
            }) : null]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("select", {
            id: `map-${target.key}`,
            value: mapping[target.key] ?? '',
            onChange: e => handleMappingChange(target.key, e.target.value),
            className: "mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("option", {
              value: "",
              children: "\u2014 Select column \u2014"
            }), headers.map(header => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("option", {
              value: header,
              children: header
            }, header))]
          })]
        }, target.key))
      }), previewRows.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: "mt-4",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          className: "mb-2 text-sm font-medium text-text",
          children: "Preview (first 3 rows)"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_5__.TableScrollWrapper, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("table", {
            className: "min-w-full text-left text-sm",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("thead", {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("tr", {
                className: "border-b border-border text-text-muted",
                children: targets.map(target => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("th", {
                  className: "px-2 py-1",
                  children: target.label
                }, target.key))
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("tbody", {
              children: previewRows.map((row, index) => {
                const mapped = applyMapping([row], mapping, customFieldKeys)[0];
                return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("tr", {
                  className: _shared_tableStyles__WEBPACK_IMPORTED_MODULE_6__.TABLE_BODY_ROW_SOFT,
                  children: targets.map(target => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("td", {
                    className: "px-2 py-1 text-text",
                    children: mapped[target.key] ?? '—'
                  }, target.key))
                }, index);
              })
            })]
          })
        })]
      }) : null, reviewerConflicts.length > 0 && !showReviewerConflictChoice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
        className: "mt-4",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
          variant: "warning",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
            className: "font-medium",
            children: "Repeated reviewer emails across panels"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
            className: "mt-1",
            children: "Each reviewer can belong to only one panel per project. Fix the file or continue \u2014 only the first panel per email will import; later rows will fail."
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("ul", {
            className: "mt-2 list-inside list-disc text-sm",
            children: reviewerConflicts.map(conflict => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("li", {
              children: [conflict.name ? `${conflict.name} (${conflict.email})` : conflict.email, ": ", ' ', conflict.panels.join(', '), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
                className: "text-text-muted",
                children: [' ', "(", formatCsvRowRefs(conflict.rows), ")"]
              })]
            }, conflict.email))
          })]
        })
      }) : null, showReviewerConflictChoice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: "mt-4 rounded-md border border-warning/40 bg-warning/10 p-4",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          className: "text-sm font-medium text-text",
          children: "Repeated reviewer emails across panels"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          className: "mt-1 text-sm text-text",
          children: "The same email appears on more than one panel in this file. Each reviewer can belong to only one panel per project. If you continue, only the first assignment per email is imported; later rows are reported as failed."
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("ul", {
          className: "mt-3 list-inside list-disc text-sm text-text",
          children: reviewerConflicts.map(conflict => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("li", {
            children: [conflict.name ? `${conflict.name} (${conflict.email})` : conflict.email, ": ", ' ', conflict.panels.join(', '), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("span", {
              className: "text-text-muted",
              children: [' ', "(", formatCsvRowRefs(conflict.rows), ")"]
            })]
          }, conflict.email))
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "mt-3 flex gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "primary",
            loading: importing,
            onClick: handleConfirmReviewerConflicts,
            children: "Continue import anyway"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "secondary",
            onClick: () => setShowReviewerConflictChoice(false),
            children: "Cancel"
          })]
        })]
      }) : showReplaceChoice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: "mt-4 rounded-md border border-warning/40 bg-warning/10 p-4",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("p", {
          className: "text-sm text-text",
          children: ["This project already has ", existingReviewerCount, " reviewer", existingReviewerCount === 1 ? '' : 's', ". Choose whether to replace the full roster or add and update from this file only."]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("fieldset", {
          className: "mt-3 space-y-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("label", {
            className: "flex items-center gap-2 text-sm",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("input", {
              type: "radio",
              name: "reviewer-import-mode",
              value: "append",
              checked: importMode === 'append',
              onChange: () => setImportMode('append')
            }), "Keep existing reviewers and append or update from CSV"]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("label", {
            className: "flex items-center gap-2 text-sm",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("input", {
              type: "radio",
              name: "reviewer-import-mode",
              value: "replace",
              checked: importMode === 'replace',
              onChange: () => setImportMode('replace')
            }), "Clear all existing reviewers and import only this file"]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "mt-3 flex gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "primary",
            loading: importing,
            onClick: handleConfirmReplaceChoice,
            children: "Continue import"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "secondary",
            onClick: () => setShowReplaceChoice(false),
            children: "Cancel"
          })]
        })]
      }) : showDuplicateChoice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: "mt-4 rounded-md border border-warning/40 bg-warning/10 p-4",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          className: "text-sm text-text",
          children: "Duplicate registration numbers were found in this file. Choose how to handle rows that already exist in the registry."
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("fieldset", {
          className: "mt-3 space-y-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("label", {
            className: "flex items-center gap-2 text-sm",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("input", {
              type: "radio",
              name: "duplicate-policy",
              value: "skip",
              checked: duplicatePolicy === 'skip',
              onChange: () => setDuplicatePolicy('skip')
            }), "Skip existing students"]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("label", {
            className: "flex items-center gap-2 text-sm",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("input", {
              type: "radio",
              name: "duplicate-policy",
              value: "update",
              checked: duplicatePolicy === 'update',
              onChange: () => setDuplicatePolicy('update')
            }), "Update existing students"]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "mt-3 flex gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "primary",
            loading: importing,
            onClick: handleConfirmDuplicates,
            children: "Continue import"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "secondary",
            onClick: () => setShowDuplicateChoice(false),
            children: "Cancel"
          })]
        })]
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
        className: "mt-4",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
          variant: "primary",
          loading: importing,
          onClick: handleSubmit,
          disabled: rows.length === 0 || (importType === 'panel-reviewers' ? !mapping.panel && !headers.some(header => /^reviewer_\d+$/i.test(header.trim().replace(/\s+/g, '_'))) : typeConfig.required.some(item => !mapping[item.key])),
          children: typeConfig.submitLabel
        })
      })]
    }) : null, notice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
      className: "mt-4 space-y-2",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        variant: notice.variant,
        onDismiss: () => setNotice(null),
        children: notice.message
      }), notice.errorCsv ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "secondary",
        size: "sm",
        onClick: downloadErrorCsv,
        children: "Download error CSV"
      }) : null]
    }) : null]
  });
}

/***/ },

/***/ "./src/coordinator/components/PanelReviewersStep.jsx"
/*!***********************************************************!*\
  !*** ./src/coordinator/components/PanelReviewersStep.jsx ***!
  \***********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PanelReviewersStep: () => (/* binding */ PanelReviewersStep)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_dom__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react-dom */ "react-dom");
/* harmony import */ var react_dom__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_dom__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_reviewerTemplateCsv__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/reviewerTemplateCsv */ "./src/shared/reviewerTemplateCsv.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var _shared_components_ConfirmDialog__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../shared/components/ConfirmDialog */ "./src/shared/components/ConfirmDialog.jsx");
/* harmony import */ var _shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../shared/TableScrollViewport */ "./src/shared/TableScrollViewport.jsx");
/* harmony import */ var _shared_tableStyles__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../shared/tableStyles */ "./src/shared/tableStyles.js");
/* harmony import */ var _CsvImportMapper__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./CsvImportMapper */ "./src/coordinator/components/CsvImportMapper.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);










const TABLE_COL_COUNT = 6;
function AccountStatus({
  reviewer
}) {
  if (reviewer.has_credentials && reviewer.credentials_sent_at) {
    const sentDate = String(reviewer.credentials_sent_at).slice(0, 10);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("span", {
      className: "rounded bg-chip-active-bg px-1.5 py-0.5 text-xs text-chip-active-text",
      title: `Credentials emailed ${reviewer.credentials_sent_at}`,
      children: ["Sent ", sentDate]
    });
  }
  if (reviewer.has_credentials) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
      className: "rounded bg-warning/15 px-1.5 py-0.5 text-xs text-warning",
      children: "Generated, not delivered"
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
    className: "rounded bg-warning/15 px-1.5 py-0.5 text-xs text-warning",
    children: "No credentials"
  });
}
function CopyField({
  value,
  label,
  autoFocusRef
}) {
  const ownRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const inputRef = autoFocusRef ?? ownRef;
  const [copied, setCopied] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
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
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
    children: [label && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
      className: "mb-1 text-xs font-medium text-text-muted",
      children: label
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      className: "flex gap-2",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
        ref: inputRef,
        type: "text",
        readOnly: true,
        value: value ?? '',
        className: "min-w-0 flex-1 rounded-md border border-border bg-surface px-3 py-2 text-sm font-mono text-text",
        onFocus: e => e.target.select(),
        "aria-label": label
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
        size: "sm",
        variant: copied ? 'secondary' : 'primary',
        onClick: handleCopy,
        children: copied ? 'Copied!' : 'Copy'
      })]
    })]
  });
}
function PortalLinkModal({
  open,
  reviewerName,
  portalUrl,
  portalPassword,
  sessionId,
  reviewerId,
  onClose,
  onCredentialsSent
}) {
  const toast = (0,_shared_components__WEBPACK_IMPORTED_MODULE_4__.useToast)();
  const dialogRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const firstInputRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const [sending, setSending] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!open || !dialogRef.current) {
      return;
    }
    firstInputRef.current?.focus();
    const onKeyDown = e => {
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
      const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.post)(`/sessions/${sessionId}/reviewers/${reviewerId}/resend-credentials`);
      if (result?.email_sent) {
        toast({
          variant: 'success',
          message: 'Credentials emailed to reviewer.'
        });
        onCredentialsSent?.(result);
      } else {
        toast({
          variant: 'error',
          message: 'Email could not be sent. Check the SMTP settings.'
        });
      }
    } catch {
      toast({
        variant: 'error',
        message: 'Could not send credentials.'
      });
    } finally {
      setSending(false);
    }
  };
  if (!open) {
    return null;
  }
  const dialog = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
    className: "fixed inset-0 z-[150] flex items-center justify-center bg-black/40 p-4",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      ref: dialogRef,
      role: "dialog",
      "aria-modal": "true",
      "aria-labelledby": "pr-portal-link-title",
      className: "w-full max-w-lg rounded-md border border-border bg-surface-raised p-6 shadow-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("h2", {
        id: "pr-portal-link-title",
        className: "text-lg font-semibold text-text",
        children: "Reviewer credentials"
      }), reviewerName && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
        className: "mt-1 text-sm text-text-muted",
        children: reviewerName
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: "mt-4 space-y-3",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(CopyField, {
          autoFocusRef: firstInputRef,
          label: "Login URL",
          value: portalUrl
        }), portalPassword ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(CopyField, {
          label: "Password",
          value: portalPassword
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
          className: "text-xs text-text-muted",
          children: "Password not available \u2014 use \"Regenerate\" to create a new one."
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: "mt-6 flex items-center justify-between gap-2",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
          variant: "primary",
          size: "sm",
          loading: sending,
          disabled: !portalPassword,
          onClick: handleSend,
          children: "Send credentials"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
          variant: "secondary",
          onClick: onClose,
          children: "Close"
        })]
      })]
    })
  });
  if (typeof document === 'undefined') {
    return dialog;
  }
  return (0,react_dom__WEBPACK_IMPORTED_MODULE_1__.createPortal)(dialog, (0,_shared_components_ConfirmDialog__WEBPACK_IMPORTED_MODULE_5__.getDialogPortalRoot)());
}
function ReviewerTableRow({
  reviewer,
  sessionId,
  allPanels,
  onProvision,
  onSaved,
  onDeleted,
  onPanelHeadChanged,
  onReviewerUpdated
}) {
  const toast = (0,_shared_components__WEBPACK_IMPORTED_MODULE_4__.useToast)();
  const [editing, setEditing] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [saving, setSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [deleting, setDeleting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [provisioning, setProvisioning] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [resending, setResending] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [regenerating, setRegenerating] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [regenerateConfirmOpen, setRegenerateConfirmOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [portalLinkOpen, setPortalLinkOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [localCreds, setLocalCreds] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    portalUrl: reviewer.portal_url ?? null,
    portalPassword: reviewer.portal_password ?? null
  });
  const [name, setName] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(reviewer.name ?? '');
  const [email, setEmail] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(reviewer.email ?? '');
  const [weight, setWeight] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(String(reviewer.weight ?? 1));
  const [panelId, setPanelId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(String(reviewer.panel_id ?? ''));
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    setName(reviewer.name ?? '');
    setEmail(reviewer.email ?? '');
    setWeight(String(reviewer.weight ?? 1));
    setPanelId(String(reviewer.panel_id ?? ''));
    // Sync creds when the reviewer record is refreshed from the server
    if (reviewer.portal_password) {
      setLocalCreds({
        portalUrl: reviewer.portal_url ?? null,
        portalPassword: reviewer.portal_password
      });
    }
  }, [reviewer]);
  const displayName = reviewer.name?.trim() || 'Unnamed reviewer';
  const panelIdNum = Number(reviewer.panel_id);
  const canBePanelHead = reviewer.has_credentials || Boolean(reviewer.user_id);
  const handlePanelHeadToggle = async event => {
    const checked = event.target.checked;
    if (!canBePanelHead) {
      return;
    }
    const panelName = reviewer.panel_name || `Panel ${panelIdNum}`;
    try {
      const updated = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.put)(`/sessions/${sessionId}/panels/${panelIdNum}/reviewers/${reviewer.id}`, {
        is_panel_head: checked
      });
      onPanelHeadChanged(updated, panelIdNum);
      toast({
        variant: 'success',
        message: checked ? `${displayName} set as panel coordinator for ${panelName}.` : `Panel coordinator cleared for ${panelName}.`
      });
    } catch (err) {
      const code = err?.code || err?.data?.code;
      toast({
        variant: 'error',
        message: code === 'panel_head_requires_account' ? 'Provision or link an account first.' : 'Could not update panel coordinator.'
      });
    }
  };
  const handleSave = async () => {
    const trimmedName = name.trim();
    const trimmedEmail = email.trim();
    if (!trimmedName && !trimmedEmail) {
      toast({
        variant: 'error',
        message: 'Enter a reviewer name or email.'
      });
      return;
    }
    const targetPanelId = Number(panelId);
    if (!targetPanelId) {
      toast({
        variant: 'error',
        message: 'Select a panel.'
      });
      return;
    }
    setSaving(true);
    try {
      const updated = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.put)(`/sessions/${sessionId}/panels/${panelIdNum}/reviewers/${reviewer.id}`, {
        name: trimmedName,
        email: trimmedEmail,
        weight: weight || 1,
        panel_id: targetPanelId
      });
      onSaved(updated);
      setEditing(false);
      toast({
        variant: 'success',
        message: targetPanelId !== panelIdNum ? 'Reviewer updated and moved to another panel.' : 'Reviewer updated.'
      });
    } catch (err) {
      const code = err?.code || err?.data?.code;
      toast({
        variant: 'error',
        message: code === 'pr_reviewer_email_in_session' ? 'A reviewer with this email is already in the project.' : 'Could not save reviewer.'
      });
    } finally {
      setSaving(false);
    }
  };
  const handleDelete = async () => {
    if (!window.confirm(`Remove ${displayName} from this project? This cannot be undone.`)) {
      return;
    }
    setDeleting(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.del)(`/sessions/${sessionId}/panels/${panelIdNum}/reviewers/${reviewer.id}`);
      onDeleted(reviewer.id);
      toast({
        variant: 'success',
        message: `${displayName} removed.`
      });
    } catch {
      toast({
        variant: 'error',
        message: 'Could not remove reviewer.'
      });
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
      const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.post)(`/sessions/${sessionId}/reviewers/${reviewer.id}/resend-credentials`);
      toast(result?.email_sent ? {
        variant: 'success',
        message: 'Credentials re-sent. Password unchanged.'
      } : {
        variant: 'error',
        message: 'Email could not be sent. Check the SMTP settings.'
      });
      if (result?.credentials_sent_at) {
        onReviewerUpdated?.({
          ...reviewer,
          credentials_sent_at: result.credentials_sent_at
        });
      }
    } catch {
      toast({
        variant: 'error',
        message: 'Could not resend credentials.'
      });
    } finally {
      setResending(false);
    }
  };
  const handleRegenerateConfirmed = async () => {
    setRegenerateConfirmOpen(false);
    setRegenerating(true);
    try {
      const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.post)(`/sessions/${sessionId}/reviewers/${reviewer.id}/generate-credentials`, {
        send: false
      });
      setLocalCreds({
        portalUrl: result.portal_url ?? reviewer.portal_url ?? null,
        portalPassword: result.portal_password ?? null
      });
      setPortalLinkOpen(true);
      toast({
        variant: 'success',
        message: 'Credentials regenerated.'
      });
      onReviewerUpdated?.({
        ...reviewer,
        has_credentials: true,
        portal_url: result.portal_url ?? reviewer.portal_url,
        portal_password: result.portal_password ?? null
      });
    } catch {
      toast({
        variant: 'error',
        message: 'Could not regenerate credentials.'
      });
    } finally {
      setRegenerating(false);
    }
  };
  const openViewLink = () => {
    setPortalLinkOpen(true);
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("tr", {
      className: _shared_tableStyles__WEBPACK_IMPORTED_MODULE_7__.TABLE_BODY_ROW_SOFT,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
        className: "px-4 py-3 font-medium text-text",
        children: displayName
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
        className: "px-4 py-3 text-text",
        children: reviewer.email ? reviewer.email : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
          className: "text-text-muted",
          children: "\u2014"
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
        className: "px-4 py-3 tabular-nums text-text",
        children: reviewer.weight ?? 1
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
        className: "px-4 py-3",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(AccountStatus, {
          reviewer: reviewer
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
        className: "px-4 py-3",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
          className: "inline-flex items-center gap-2",
          title: canBePanelHead ? 'Designate as panel coordinator' : 'Send credentials first.',
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
            type: "checkbox",
            name: `panel-coordinator-${panelIdNum}`,
            className: "h-4 w-4 rounded border-border text-primary focus:ring-primary",
            checked: Boolean(reviewer.is_panel_head),
            disabled: !canBePanelHead,
            onChange: handlePanelHeadToggle,
            "aria-label": `Panel coordinator for ${displayName}`
          })
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
        className: "px-4 py-3",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: "flex flex-wrap gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
            size: "sm",
            variant: "ghost",
            onClick: () => setEditing(true),
            children: "Edit"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
            size: "sm",
            variant: "ghost",
            loading: deleting,
            onClick: handleDelete,
            children: "Remove"
          }), reviewer.email && !reviewer.has_credentials ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
            size: "sm",
            variant: "primary",
            loading: provisioning,
            onClick: handleProvision,
            children: "Send credentials"
          }) : null, reviewer.has_credentials ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
              size: "sm",
              variant: "secondary",
              loading: resending,
              disabled: regenerating,
              onClick: handleResend,
              children: "Resend"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
              size: "sm",
              variant: "ghost",
              loading: regenerating,
              disabled: resending,
              onClick: () => setRegenerateConfirmOpen(true),
              children: "Regenerate"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
              size: "sm",
              variant: "ghost",
              disabled: resending || regenerating,
              onClick: openViewLink,
              children: "View link"
            })]
          }) : null]
        })
      })]
    }), editing ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("tr", {
      className: "border-b border-border/60 bg-surface",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("td", {
        colSpan: TABLE_COL_COUNT,
        className: "px-4 py-3",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: "grid gap-3 sm:grid-cols-2 lg:grid-cols-4",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
              className: "block text-xs font-medium text-text",
              htmlFor: `edit-name-${reviewer.id}`,
              children: "Name"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
              id: `edit-name-${reviewer.id}`,
              type: "text",
              value: name,
              onChange: e => setName(e.target.value),
              className: "mt-1 w-full rounded-md border border-border bg-surface px-2 py-1"
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
              className: "block text-xs font-medium text-text",
              htmlFor: `edit-email-${reviewer.id}`,
              children: "Email"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
              id: `edit-email-${reviewer.id}`,
              type: "email",
              value: email,
              onChange: e => setEmail(e.target.value),
              className: "mt-1 w-full rounded-md border border-border bg-surface px-2 py-1"
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
              className: "block text-xs font-medium text-text",
              htmlFor: `edit-weight-${reviewer.id}`,
              children: "Weight"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
              id: `edit-weight-${reviewer.id}`,
              type: "number",
              min: "0",
              step: "0.1",
              value: weight,
              onChange: e => setWeight(e.target.value),
              className: "mt-1 w-full rounded-md border border-border bg-surface px-2 py-1"
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
              className: "block text-xs font-medium text-text",
              htmlFor: `edit-panel-${reviewer.id}`,
              children: "Panel"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("select", {
              id: `edit-panel-${reviewer.id}`,
              value: panelId,
              onChange: e => setPanelId(e.target.value),
              className: "mt-1 w-full rounded-md border border-border bg-surface px-2 py-1",
              children: allPanels.map(panel => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("option", {
                value: panel.id,
                children: panel.name
              }, panel.id))
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: "mt-3 flex flex-wrap gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
            size: "sm",
            variant: "primary",
            loading: saving,
            onClick: handleSave,
            children: "Save"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
            size: "sm",
            variant: "secondary",
            onClick: () => setEditing(false),
            disabled: saving,
            children: "Cancel"
          })]
        })]
      })
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.ConfirmDialog, {
      open: regenerateConfirmOpen,
      title: `Regenerate credentials for ${displayName}?`,
      consequences: ['A new password will be generated and saved immediately.', 'The current password stops working right away — the reviewer will be logged out.', 'The login URL stays the same; only the password changes.', 'Use "Send credentials" in the next screen to email the new password.'],
      confirmLabel: "Regenerate",
      confirmVariant: "destructive",
      onCancel: () => setRegenerateConfirmOpen(false),
      onConfirm: handleRegenerateConfirmed
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(PortalLinkModal, {
      open: portalLinkOpen,
      reviewerName: displayName,
      portalUrl: localCreds.portalUrl ?? reviewer.portal_url ?? null,
      portalPassword: localCreds.portalPassword ?? reviewer.portal_password ?? null,
      sessionId: sessionId,
      reviewerId: reviewer.id,
      onClose: () => setPortalLinkOpen(false),
      onCredentialsSent: result => {
        if (result?.credentials_sent_at) {
          onReviewerUpdated?.({
            ...reviewer,
            credentials_sent_at: result.credentials_sent_at
          });
        }
      }
    })]
  });
}
function AddReviewerForm({
  sessionId,
  sessionPanels,
  setReviewers,
  mergePanelReviewers,
  onRefreshReviewers
}) {
  const defaultPanelId = sessionPanels.length === 1 ? String(sessionPanels[0].id) : '';
  const [selectedPanelId, setSelectedPanelId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(defaultPanelId);
  const [name, setName] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [email, setEmail] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [weight, setWeight] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('1');
  const [submitting, setSubmitting] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [formNotice, setFormNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
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
  const handleSubmit = async event => {
    event.preventDefault();
    setFormNotice(null);
    const trimmedName = name.trim();
    const trimmedEmail = email.trim();
    if (!trimmedName && !trimmedEmail) {
      setFormNotice({
        variant: 'error',
        message: 'Enter a reviewer name or email.'
      });
      return;
    }
    const panelId = Number(selectedPanelId);
    if (!panelId) {
      setFormNotice({
        variant: 'error',
        message: 'Select a panel.'
      });
      return;
    }
    const panel = sessionPanels.find(p => Number(p.id) === panelId);
    if (!panel) {
      setFormNotice({
        variant: 'error',
        message: 'Select a panel.'
      });
      return;
    }
    setSubmitting(true);
    try {
      const created = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.post)(`/sessions/${sessionId}/panels/${panelId}/reviewers`, {
        name: trimmedName,
        email: trimmedEmail,
        weight: weight || 1
      });
      const createdId = Number(created?.id);
      if (createdId > 0) {
        setReviewers(prev => [...prev.filter(row => Number(row.id) !== createdId), {
          ...created,
          id: createdId,
          panel_id: panelId,
          panel_name: panel.name
        }]);
      }
      try {
        const panelData = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.get)(`/sessions/${sessionId}/panels/${panelId}/reviewers`);
        mergePanelReviewers(panel, panelData.reviewers ?? []);
      } catch {
        if (createdId > 0) {
          await onRefreshReviewers?.();
        }
      }
      resetForm();
      const label = created.name || trimmedEmail;
      setFormNotice({
        variant: 'success',
        message: `${label} added to ${panel.name}.`
      });
    } catch (err) {
      const code = err?.code || err?.data?.code;
      setFormNotice({
        variant: 'error',
        message: code === 'pr_reviewer_email_in_session' ? 'A reviewer with this email is already in the project.' : 'Could not add reviewer.'
      });
    } finally {
      setSubmitting(false);
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
    className: "mt-6 rounded-md border border-border bg-surface-raised p-4 shadow-card",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("h3", {
      className: "text-sm font-semibold text-text",
      children: "Add reviewer"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("form", {
      className: "mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5",
      onSubmit: handleSubmit,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
          className: "block text-sm font-medium text-text",
          htmlFor: "add-reviewer-panel",
          children: "Panel"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("select", {
          id: "add-reviewer-panel",
          value: selectedPanelId,
          onChange: e => setSelectedPanelId(e.target.value),
          required: sessionPanels.length > 1,
          className: "mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm",
          children: [sessionPanels.length > 1 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("option", {
            value: "",
            children: "Select panel\u2026"
          }) : null, sessionPanels.map(panel => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("option", {
            value: panel.id,
            children: panel.name
          }, panel.id))]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
          className: "block text-sm font-medium text-text",
          htmlFor: "add-reviewer-name",
          children: "Reviewer name"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
          id: "add-reviewer-name",
          type: "text",
          autoComplete: "name",
          value: name,
          onChange: e => setName(e.target.value),
          className: "mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm",
          placeholder: "Full name"
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
          className: "block text-sm font-medium text-text",
          htmlFor: "add-reviewer-email",
          children: "Email"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
          id: "add-reviewer-email",
          type: "email",
          autoComplete: "email",
          value: email,
          onChange: e => setEmail(e.target.value),
          className: "mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm",
          placeholder: "reviewer@example.com"
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
          className: "block text-sm font-medium text-text",
          htmlFor: "add-reviewer-weight",
          children: "Weight"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
          id: "add-reviewer-weight",
          type: "number",
          min: "0",
          step: "0.1",
          value: weight,
          onChange: e => setWeight(e.target.value),
          className: "mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
        className: "flex items-end",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
          variant: "primary",
          type: "submit",
          size: "sm",
          loading: submitting,
          className: "w-full sm:w-auto",
          children: "Add reviewer"
        })
      })]
    }), formNotice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
      className: "mt-3",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Notice, {
        variant: formNotice.variant,
        onDismiss: () => setFormNotice(null),
        children: formNotice.message
      })
    }) : null]
  });
}
function PanelReviewerTable({
  sessionId,
  panel,
  panelIndex,
  reviewers,
  allPanels,
  setReviewers,
  onProvision
}) {
  const panelId = Number(panel.id);
  const panelReviewers = reviewers.filter(row => Number(row.panel_id) === panelId);
  const handleReviewerSaved = updated => {
    const updatedId = Number(updated?.id);
    if (updatedId <= 0) {
      return;
    }
    setReviewers(prev => {
      const without = prev.filter(row => Number(row.id) !== updatedId);
      return [...without, {
        ...updated,
        id: updatedId,
        panel_id: Number(updated.panel_id),
        panel_name: updated.panel_name ?? panel.name
      }];
    });
  };
  const handleReviewerDeleted = reviewerId => {
    setReviewers(prev => prev.filter(row => Number(row.id) !== Number(reviewerId)));
  };
  const handlePanelHeadChanged = (updated, panelIdForRow) => {
    const updatedId = Number(updated?.id);
    if (updatedId <= 0) {
      return;
    }
    const isHead = Boolean(updated.is_panel_head);
    setReviewers(prev => prev.map(row => {
      if (Number(row.panel_id) !== Number(panelIdForRow)) {
        return row;
      }
      if (Number(row.id) === updatedId) {
        return {
          ...row,
          ...updated,
          is_panel_head: isHead
        };
      }
      if (isHead) {
        return {
          ...row,
          is_panel_head: false
        };
      }
      return row;
    }));
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
    className: "mt-6",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      className: "flex flex-wrap items-baseline justify-between gap-2",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("h3", {
        className: "font-semibold text-text",
        children: ["Panel ", panelIndex + 1, ": ", panel.name]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("span", {
        className: "text-sm text-text-muted",
        children: [panelReviewers.length, " reviewer", panelReviewers.length === 1 ? '' : 's', panel.student_count != null ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("span", {
          className: "ml-2",
          children: ["\xB7 ", panel.student_count, " student", panel.student_count === 1 ? '' : 's']
        }) : null]
      })]
    }), panelReviewers.length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
      className: "mt-4 rounded-md border border-dashed border-border bg-surface px-3 py-3 text-sm text-text-muted",
      children: "No reviewers for this panel yet. Use the add form above or import from CSV."
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_6__.TableScrollWrapper, {
      className: "mt-4 bg-surface-raised shadow-card",
      "aria-live": "polite",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("table", {
        className: "min-w-full text-left text-sm",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("thead", {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("tr", {
            className: "border-b border-border bg-surface text-text-muted",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
              className: "px-4 py-3 font-medium",
              children: "Name"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
              className: "px-4 py-3 font-medium",
              children: "Email"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
              className: "px-4 py-3 font-medium",
              children: "Weight"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
              className: "px-4 py-3 font-medium",
              children: "Access"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
              className: "px-4 py-3 font-medium",
              title: "One panel coordinator per panel; they can view panel scores and sign off.",
              children: "Panel coordinator"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
              className: "px-4 py-3 font-medium",
              children: "Actions"
            })]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("tbody", {
          children: panelReviewers.map(row => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(ReviewerTableRow, {
            reviewer: row,
            sessionId: sessionId,
            allPanels: allPanels,
            onProvision: () => onProvision(row.id),
            onSaved: handleReviewerSaved,
            onDeleted: handleReviewerDeleted,
            onPanelHeadChanged: handlePanelHeadChanged,
            onReviewerUpdated: handleReviewerSaved
          }, row.id))
        })]
      })
    })]
  });
}
const RESEND_ALL_PHRASE = 'RESEND ALL';
function PanelReviewersStep({
  sessionId,
  panels,
  reviewers,
  setReviewers,
  onNotice,
  onRefreshReviewers,
  onReload
}) {
  const toast = (0,_shared_components__WEBPACK_IMPORTED_MODULE_4__.useToast)();
  const mergePanelReviewers = (panel, panelRows) => {
    const panelId = Number(panel.id);
    const normalized = panelRows.map(row => ({
      ...row,
      id: Number(row.id),
      panel_id: panelId,
      panel_name: panel.name
    }));
    setReviewers(prev => [...prev.filter(row => Number(row.panel_id) !== panelId), ...normalized]);
  };
  const sessionPanels = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    return panels.filter(panel => Number(panel.session_id) === sessionId || panel.session_id == null).sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, {
      numeric: true
    }));
  }, [panels, sessionId]);
  const downloadTemplate = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    const csv = (0,_shared_reviewerTemplateCsv__WEBPACK_IMPORTED_MODULE_3__.buildReviewersTemplateCsv)(sessionPanels, reviewers);
    (0,_shared_reviewerTemplateCsv__WEBPACK_IMPORTED_MODULE_3__.downloadCsvText)(csv, `session-${sessionId}-reviewers.csv`);
  }, [sessionPanels, reviewers, sessionId]);
  const [bulkSending, setBulkSending] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [resendAllOpen, setResendAllOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [resendAllPhrase, setResendAllPhrase] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const openResendAllDialog = () => {
    setResendAllPhrase('');
    setResendAllOpen(true);
  };
  const closeResendAllDialog = () => {
    setResendAllOpen(false);
    setResendAllPhrase('');
  };
  const handleBulkSend = async force => {
    setBulkSending(true);
    try {
      const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.post)(`/sessions/${sessionId}/send-all-credentials`, {
        force
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
        message: `Credentials: ${parts.join(', ')}.`
      });
      await onRefreshReviewers?.();
    } catch {
      toast({
        variant: 'error',
        message: 'Could not send credentials.'
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
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("section", {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("h2", {
        className: "text-lg font-semibold text-text",
        children: "Reviewers"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
        className: "mt-2 text-sm text-warning",
        children: "No panels in this project yet. Go back to the Panels step and create at least one panel first."
      })]
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("section", {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      className: "flex flex-wrap items-center justify-between gap-2",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("h2", {
        className: "text-lg font-semibold text-text",
        children: "Reviewers"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: "flex flex-wrap gap-2",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
          size: "sm",
          variant: "primary",
          loading: bulkSending,
          onClick: () => handleBulkSend(false),
          children: "Email credentials to all"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
          size: "sm",
          variant: "ghost",
          disabled: bulkSending,
          onClick: openResendAllDialog,
          children: "Resend to all"
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
      className: "mt-1 text-sm text-text-muted",
      children: "Add reviewers to a panel, download a roster template prefilled with your panels and existing reviewers, or import updates from CSV. Each reviewer receives a personal review link and password by email \u2014 no WordPress account needed. Reviewers who already received credentials are skipped unless you use \"Resend to all\"."
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(AddReviewerForm, {
      sessionId: sessionId,
      sessionPanels: sessionPanels,
      setReviewers: setReviewers,
      mergePanelReviewers: mergePanelReviewers,
      onRefreshReviewers: onRefreshReviewers
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
      className: "mt-6",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_CsvImportMapper__WEBPACK_IMPORTED_MODULE_8__.CsvImportMapper, {
        importType: "panel-reviewers",
        sessionId: sessionId,
        existingReviewerCount: reviewers.length,
        onComplete: onRefreshReviewers ?? onReload,
        onDownloadTemplate: downloadTemplate,
        templateDownloadLabel: "Download roster template CSV"
      })
    }), sessionPanels.map((panel, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(PanelReviewerTable, {
      sessionId: sessionId,
      panel: panel,
      panelIndex: index,
      reviewers: reviewers,
      allPanels: sessionPanels,
      setReviewers: setReviewers,
      onProvision: async reviewerId => {
        const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.post)(`/sessions/${sessionId}/reviewers/${reviewerId}/generate-credentials`);
        toast(result?.email_sent ? {
          variant: 'success',
          message: 'Review link and password emailed to reviewer.'
        } : {
          variant: 'error',
          message: 'Credentials generated but the email could not be sent. Check the SMTP settings.'
        });
        await onRefreshReviewers?.();
      }
    }, panel.id)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.ConfirmDialog, {
      open: resendAllOpen,
      title: "Resend credentials to all reviewers?",
      consequences: ['New passwords will be generated for every credentialed reviewer.', 'Each reviewer will receive a fresh email with their review link and new password.', 'Previous passwords are immediately invalidated.', 'Reviewers without an email address are skipped.'],
      confirmLabel: bulkSending ? 'Sending…' : 'Resend to all',
      confirmVariant: "destructive",
      confirmDisabled: resendAllPhrase.trim() !== RESEND_ALL_PHRASE || bulkSending,
      onCancel: closeResendAllDialog,
      onConfirm: handleResendAllConfirm,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: "space-y-2 text-sm text-text-muted",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("p", {
          children: ["Type", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("strong", {
            className: "font-mono text-text",
            children: RESEND_ALL_PHRASE
          }), ' ', "to confirm."]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
          type: "text",
          className: "w-full rounded-md border border-border bg-surface px-3 py-2 text-text",
          value: resendAllPhrase,
          onChange: e => setResendAllPhrase(e.target.value),
          autoComplete: "off",
          "aria-label": `Type ${RESEND_ALL_PHRASE} to confirm`
        })]
      })
    })]
  });
}

/***/ },

/***/ "./src/coordinator/components/PanelsStep.jsx"
/*!***************************************************!*\
  !*** ./src/coordinator/components/PanelsStep.jsx ***!
  \***************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PanelsStep: () => (/* binding */ PanelsStep)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/apiErrors */ "./src/shared/apiErrors.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





function PanelsStep({
  sessionId,
  panels,
  enrolled,
  wizardState,
  onReload,
  onNotice,
  onContinue,
  blockedTitle
}) {
  const [busy, setBusy] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [editingId, setEditingId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [editName, setEditName] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [pendingDelete, setPendingDelete] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const createPanel = async event => {
    event.preventDefault();
    const name = event.target.panel_name.value.trim();
    if (!name) {
      return;
    }
    setBusy(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/panels`, {
        name
      });
      event.target.reset();
      await onReload?.();
    } catch (err) {
      onNotice?.({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Could not create panel.')
      });
    } finally {
      setBusy(false);
    }
  };
  const assignPanel = async (studentId, panelId) => {
    setBusy(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/students/${studentId}`, {
        panel_id: panelId || null
      });
      await onReload?.();
    } catch {
      onNotice?.({
        variant: 'error',
        message: 'Could not assign panel.'
      });
    } finally {
      setBusy(false);
    }
  };
  const startEdit = panel => {
    setEditingId(panel.id);
    setEditName(panel.name);
  };
  const saveName = async panelId => {
    const name = editName.trim();
    setEditingId(null);
    if (!name) {
      onNotice?.({
        variant: 'error',
        message: 'Panel name is required.'
      });
      return;
    }
    setBusy(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/panels/${panelId}`, {
        name
      });
      await onReload?.();
    } catch (err) {
      onNotice?.({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Could not rename panel.')
      });
    } finally {
      setBusy(false);
    }
  };
  const confirmDeletePanel = async () => {
    if (!pendingDelete) {
      return;
    }
    const panel = pendingDelete;
    setPendingDelete(null);
    setBusy(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.del)(`/sessions/${sessionId}/panels/${panel.id}`);
      await onReload?.();
    } catch (err) {
      onNotice?.({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Could not remove panel.')
      });
    } finally {
      setBusy(false);
    }
  };
  const unassignedCount = wizardState?.unassigned_count ?? 0;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("section", {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h2", {
      className: "text-lg font-semibold text-text",
      children: "Panels"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      className: "mt-1 text-sm text-text-muted",
      children: "Create panels and assign every enrolled student. This is the project default template for Review 1; later rounds start as a copy of the previous review and can be changed on Panel assignments."
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("form", {
      onSubmit: createPanel,
      className: "mt-4 flex flex-wrap gap-2",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("input", {
        name: "panel_name",
        type: "text",
        placeholder: "Panel name",
        className: "rounded-md border border-border bg-surface px-3 py-2 text-sm",
        required: true,
        disabled: busy
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "primary",
        type: "submit",
        disabled: busy,
        children: "Add panel"
      })]
    }), panels.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("ul", {
      className: "mt-4 space-y-2",
      children: panels.map(panel => {
        const isEditing = editingId === panel.id;
        const deletable = panel.deletable ?? panel.student_count === 0;
        const studentLabel = panel.student_count === 1 ? '1 student' : `${panel.student_count} students`;
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("li", {
          className: "flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-surface-raised px-3 py-2 text-sm",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            className: "flex min-w-0 flex-1 flex-wrap items-center gap-2",
            children: [isEditing ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("input", {
              type: "text",
              value: editName,
              onChange: e => setEditName(e.target.value),
              onBlur: () => saveName(panel.id),
              onKeyDown: e => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  saveName(panel.id);
                }
                if (e.key === 'Escape') {
                  setEditingId(null);
                }
              },
              className: "min-w-[12rem] flex-1 rounded-md border border-border bg-surface px-2 py-1",
              "aria-label": `Rename panel ${panel.name}`,
              autoFocus: true,
              disabled: busy
            }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("button", {
              type: "button",
              className: "font-medium text-text hover:underline",
              onClick: () => startEdit(panel),
              "aria-label": `Rename panel ${panel.name}`,
              disabled: busy,
              children: panel.name
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
              className: "text-text-muted",
              children: studentLabel
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
            className: "flex flex-wrap items-center gap-2",
            children: deletable ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
              size: "sm",
              variant: "secondary",
              disabled: busy,
              onClick: () => setPendingDelete(panel),
              "aria-label": `Remove panel ${panel.name}`,
              children: "Remove"
            }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
              className: "text-xs text-text-muted",
              title: "Reassign or unassign students before removing this panel.",
              children: "Reassign or unassign students before removing this panel."
            })
          })]
        }, panel.id);
      })
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      className: "mt-4 text-sm text-text-muted",
      children: "Add a panel, then assign each enrolled student below."
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h3", {
      className: "mt-6 text-sm font-semibold text-text",
      children: "Student assignments"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("ul", {
      className: "mt-2 space-y-4",
      children: enrolled.map(row => {
        const unassigned = !row.panel_id;
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("li", {
          className: ['flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm', unassigned ? 'border-warning bg-warning/10' : 'border-border'].join(' '),
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
            className: "font-medium text-text",
            children: [row.student?.reg_no, " \u2014 ", row.student?.name]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("select", {
            value: row.panel_id ?? '',
            onChange: e => assignPanel(row.student.id, e.target.value ? Number(e.target.value) : null),
            className: "rounded-md border border-border bg-surface px-2 py-1 text-sm",
            "aria-label": `Panel for ${row.student?.name}`,
            disabled: busy,
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("option", {
              value: "",
              children: "Unassigned"
            }), panels.map(panel => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("option", {
              value: panel.id,
              children: panel.name
            }, panel.id))]
          })]
        }, row.enrolment_id);
      })
    }), unassignedCount > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("p", {
      className: "mt-3 text-sm text-warning",
      children: [unassignedCount, " student", unassignedCount === 1 ? '' : 's', " still unassigned."]
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: pendingDelete !== null,
      title: pendingDelete ? `Remove panel “${pendingDelete.name}”?` : 'Remove panel?',
      consequences: ['Reviewers on this panel will be removed.', 'This cannot be undone.'],
      confirmLabel: "Remove panel",
      confirmVariant: "destructive",
      onConfirm: confirmDeletePanel,
      onCancel: () => setPendingDelete(null)
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "mt-6 flex justify-end",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "primary",
        onClick: onContinue,
        disabled: unassignedCount > 0 || busy,
        title: blockedTitle,
        children: "Continue to Reviewers"
      })
    })]
  });
}

/***/ },

/***/ "./src/coordinator/components/ReviewAssignmentsStep.jsx"
/*!**************************************************************!*\
  !*** ./src/coordinator/components/ReviewAssignmentsStep.jsx ***!
  \**************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ReviewAssignmentsStep: () => (/* binding */ ReviewAssignmentsStep)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_markErrors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/markErrors */ "./src/shared/markErrors.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var _shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../shared/TableScrollViewport */ "./src/shared/TableScrollViewport.jsx");
/* harmony import */ var _shared_tableStyles__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../shared/tableStyles */ "./src/shared/tableStyles.js");
/* harmony import */ var _CorrectAttendanceDialog__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./CorrectAttendanceDialog */ "./src/coordinator/components/CorrectAttendanceDialog.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);








function rowKey(studentId) {
  return String(studentId);
}
function ReviewAssignmentsStep({
  sessionId,
  panels,
  wizardState,
  onReload,
  onNotice,
  onContinue,
  isWizardTerminalStep = false
}) {
  const [reviews, setReviews] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [selectedReviewId, setSelectedReviewId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [students, setStudents] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [rowDrafts, setRowDrafts] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [busy, setBusy] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [confirmAction, setConfirmAction] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [correctionTarget, setCorrectionTarget] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [attendanceNotice, setAttendanceNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const syncDraftsFromStudents = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(rows => {
    const drafts = {};
    for (const row of rows) {
      drafts[rowKey(row.student_id)] = {
        panel_id: row.panel_id ?? '',
        project_title: row.project_title ?? ''
      };
    }
    setRowDrafts(drafts);
  }, []);
  const loadReviews = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    const data = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.get)(`/sessions/${sessionId}/reviews`);
    const items = data.reviews ?? [];
    setReviews(items);
    if (items.length && !selectedReviewId) {
      setSelectedReviewId(items[0].id);
    }
    return items;
  }, [sessionId, selectedReviewId]);
  const loadAssignments = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async reviewId => {
    if (!reviewId) {
      setStudents([]);
      setRowDrafts({});
      return;
    }
    const data = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.get)(`/sessions/${sessionId}/reviews/${reviewId}/assignments`);
    const rows = data.students ?? [];
    setStudents(rows);
    syncDraftsFromStudents(rows);
  }, [sessionId, syncDraftsFromStudents]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    (async () => {
      setLoading(true);
      try {
        await loadReviews();
      } catch {
        onNotice?.({
          variant: 'error',
          message: 'Could not load review rounds.'
        });
      } finally {
        setLoading(false);
      }
    })();
  }, [loadReviews, onNotice]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!selectedReviewId) {
      return;
    }
    (async () => {
      try {
        await loadAssignments(selectedReviewId);
      } catch {
        onNotice?.({
          variant: 'error',
          message: 'Could not load assignments for this round.'
        });
      }
    })();
  }, [selectedReviewId, loadAssignments, onNotice]);
  const selectedReview = reviews.find(r => r.id === selectedReviewId);
  const reviewMarksLocked = Boolean(selectedReview?.coordinator_marks_locked);
  const previousReview = (() => {
    const index = reviews.findIndex(r => r.id === selectedReviewId);
    return index > 0 ? reviews[index - 1] : null;
  })();
  const updateDraft = (studentId, patch) => {
    const key = rowKey(studentId);
    setRowDrafts(current => ({
      ...current,
      [key]: {
        ...current[key],
        ...patch
      }
    }));
  };
  const isRowDirty = row => {
    const draft = rowDrafts[rowKey(row.student_id)];
    if (!draft) {
      return false;
    }
    const panelId = draft.panel_id === '' ? null : Number(draft.panel_id);
    return panelId !== row.panel_id || (draft.project_title ?? '') !== (row.project_title ?? '');
  };
  const saveStudentRow = async row => {
    const draft = rowDrafts[rowKey(row.student_id)];
    if (!draft) {
      return;
    }
    const panelId = Number(draft.panel_id);
    if (!panelId) {
      onNotice?.({
        variant: 'error',
        message: 'Select a panel before saving.'
      });
      return;
    }
    setBusy(true);
    try {
      const data = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/reviews/${selectedReviewId}/assignments/students`, {
        students: [{
          student_id: row.student_id,
          panel_id: panelId,
          project_title: draft.project_title ?? ''
        }]
      });
      setStudents(data.students ?? []);
      syncDraftsFromStudents(data.students ?? []);
      await onReload?.();
    } catch {
      onNotice?.({
        variant: 'error',
        message: 'Could not save assignment.'
      });
    } finally {
      setBusy(false);
    }
  };
  const runCopyFromPrevious = async () => {
    if (!previousReview || !selectedReviewId) {
      return;
    }
    setBusy(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/reviews/${selectedReviewId}/assignments/copy-from/${previousReview.id}`);
      await loadAssignments(selectedReviewId);
      await onReload?.();
      onNotice?.({
        variant: 'success',
        message: `Assignments copied from ${previousReview.label}.`
      });
    } catch {
      onNotice?.({
        variant: 'error',
        message: 'Could not copy assignments.'
      });
    } finally {
      setBusy(false);
      setConfirmAction(null);
    }
  };
  const runResetToDefaults = async () => {
    if (!selectedReviewId) {
      return;
    }
    setBusy(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/reviews/${selectedReviewId}/assignments/reset-to-session-defaults`);
      await loadAssignments(selectedReviewId);
      await onReload?.();
      onNotice?.({
        variant: 'success',
        message: 'Assignments reset to project defaults.'
      });
    } catch {
      onNotice?.({
        variant: 'error',
        message: 'Could not reset assignments.'
      });
    } finally {
      setBusy(false);
      setConfirmAction(null);
    }
  };
  const unassigned = wizardState?.review_assignment_unassigned ?? 0;
  const canContinue = wizardState?.assignments_complete ?? unassigned === 0;
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.TableSkeleton, {
      rows: 8,
      columns: 5
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("section", {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("h2", {
      className: "text-lg font-semibold text-text",
      children: "Panel assignments"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
      className: "mt-1 text-sm text-text-muted",
      children: "Assign each student to a panel for each review round. Review 1 defaults come from the Panels step; later rounds can copy the previous review. Save each row after editing. When every student has a panel on every round, continue to Open reviews to start or pause marking."
    }), reviews.length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
      className: "mt-4 text-sm text-warning",
      children: "No review rounds found. Add rounds on the Reviews & rubrics step first."
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: "mt-4 flex flex-wrap items-end gap-4",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("label", {
          className: "block text-sm",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
            className: "font-medium text-text",
            children: "Review round"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("select", {
            className: "mt-1 block rounded-md border border-border bg-surface px-3 py-2 text-sm",
            value: selectedReviewId ?? '',
            onChange: e => {
              setSelectedReviewId(Number(e.target.value));
              setAttendanceNotice(null);
              setCorrectionTarget(null);
            },
            children: reviews.map(review => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("option", {
              value: review.id,
              children: review.label
            }, review.id))
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "flex flex-wrap gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "secondary",
            size: "sm",
            disabled: busy || !previousReview,
            onClick: () => setConfirmAction('copy'),
            children: "Copy from previous review"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
            variant: "secondary",
            size: "sm",
            disabled: busy,
            onClick: () => setConfirmAction('reset'),
            children: "Reset to project defaults"
          })]
        })]
      }), unassigned > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
        className: "mt-4",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
          variant: "warning",
          children: [unassigned, " student", unassigned === 1 ? '' : 's', " still need a panel on one or more review rounds."]
        })
      }) : null, attendanceNotice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
        className: "mt-4",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
          variant: attendanceNotice.variant,
          children: attendanceNotice.message
        })
      }) : null, selectedReview && students.length > 0 && !reviewMarksLocked ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
        className: "mt-4 rounded-md border border-border bg-surface-raised p-4 text-sm text-text-muted",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          className: "font-medium text-text",
          children: "Attendance correction"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("p", {
          className: "mt-1",
          children: ["When all panel reviewers recorded the same attendance but that value is wrong, use ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
            className: "text-text",
            children: "Correct attendance"
          }), " on the student below. This updates every reviewer on that panel for ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
            className: "text-text",
            children: selectedReview.label
          }), " only. Setting Absent clears all criterion scores for that student on this review."]
        })]
      }) : null, reviewMarksLocked ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
        className: "mt-4 text-sm text-text-muted",
        children: "Review marks are frozen. Unlock this review on Reports before correcting attendance."
      }) : null, students.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_4__.TableScrollWrapper, {
        className: "mt-4",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("table", {
          className: "min-w-full divide-y divide-border text-sm",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("thead", {
            className: "bg-surface-raised text-left text-text-muted",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("tr", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("th", {
                className: "px-3 py-2 font-medium",
                children: "Student"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("th", {
                className: "px-3 py-2 font-medium",
                children: "Project title"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("th", {
                className: "px-3 py-2 font-medium",
                children: "Panel"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("th", {
                className: "px-3 py-2 font-medium text-right",
                children: "Actions"
              })]
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("tbody", {
            className: "divide-y divide-border bg-surface",
            children: students.map(row => {
              const draft = rowDrafts[rowKey(row.student_id)] ?? {};
              const dirty = isRowDirty(row);
              return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("tr", {
                className: `group ${_shared_tableStyles__WEBPACK_IMPORTED_MODULE_5__.TABLE_BODY_ROW_SOFT}`,
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("td", {
                  className: "px-3 py-2 text-text",
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
                    children: [row.reg_no, " \u2014 ", row.name]
                  }), row.panel_id ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
                    className: "mt-0.5 text-text-muted",
                    children: (0,_shared_markErrors__WEBPACK_IMPORTED_MODULE_2__.formatAttendanceConflictLabel)(row.attendance_status || 'present')
                  }) : null]
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("td", {
                  className: "px-3 py-2",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("input", {
                    type: "text",
                    className: "w-full min-w-[12rem] rounded-md border border-border bg-surface px-2 py-1",
                    value: draft.project_title ?? '',
                    disabled: busy,
                    "aria-label": `Project title for ${row.name}`,
                    onChange: e => updateDraft(row.student_id, {
                      project_title: e.target.value
                    })
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("td", {
                  className: "px-3 py-2",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("select", {
                    className: "w-full min-w-[8rem] rounded-md border border-border bg-surface px-2 py-1",
                    value: draft.panel_id ?? '',
                    disabled: busy,
                    "aria-label": `Panel for ${row.name}`,
                    onChange: e => updateDraft(row.student_id, {
                      panel_id: e.target.value
                    }),
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("option", {
                      value: "",
                      children: "Unassigned"
                    }), panels.map(panel => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("option", {
                      value: panel.id,
                      children: panel.name
                    }, panel.id))]
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("td", {
                  className: "px-3 py-2",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
                    className: "flex flex-wrap items-center justify-end gap-2",
                    children: [row.panel_id && !reviewMarksLocked ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
                      variant: "secondary",
                      size: "sm",
                      disabled: busy,
                      onClick: () => setCorrectionTarget({
                        studentId: row.student_id,
                        studentLabel: `${row.reg_no} — ${row.name}`,
                        currentStatus: row.attendance_status || 'present'
                      }),
                      children: "Correct attendance"
                    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
                      variant: "primary",
                      size: "sm",
                      disabled: busy || !dirty,
                      onClick: () => saveStudentRow(row),
                      children: "Save"
                    })]
                  })
                })]
              }, row.student_id);
            })
          })]
        })
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
        className: "mt-4 text-sm text-text-muted",
        children: "No student assignments for this review round yet."
      })]
    }), correctionTarget && selectedReviewId ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_CorrectAttendanceDialog__WEBPACK_IMPORTED_MODULE_6__.CorrectAttendanceDialog, {
      open: true,
      sessionId: sessionId,
      reviewId: selectedReviewId,
      studentId: correctionTarget.studentId,
      reviewLabel: `${selectedReview?.label ?? 'Review'} · ${correctionTarget.studentLabel}`,
      currentStatus: correctionTarget.currentStatus,
      onClose: () => setCorrectionTarget(null),
      onSuccess: async status => {
        setAttendanceNotice({
          variant: 'success',
          message: `Attendance for ${correctionTarget.studentLabel} updated to ${(0,_shared_markErrors__WEBPACK_IMPORTED_MODULE_2__.formatAttendanceConflictLabel)(status)}.`
        });
        await loadAssignments(selectedReviewId);
      }
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: confirmAction === 'copy',
      title: "Copy assignments from previous review?",
      description: previousReview && selectedReview ? `This replaces assignments for ${selectedReview.label} only, using ${previousReview.label} as the source.` : '',
      confirmLabel: "Copy assignments",
      onConfirm: runCopyFromPrevious,
      onCancel: () => setConfirmAction(null)
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: confirmAction === 'reset',
      title: "Reset to project defaults?",
      description: selectedReview ? `This replaces assignments for ${selectedReview.label} only, using the project default Panels and Reviewers template.` : '',
      confirmLabel: "Reset assignments",
      onConfirm: runResetToDefaults,
      onCancel: () => setConfirmAction(null)
    }), !isWizardTerminalStep ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
      className: "mt-6 flex justify-end",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "primary",
        onClick: onContinue,
        disabled: !canContinue || busy,
        title: !canContinue ? 'Assign every enrolled student to a panel on every review round' : undefined,
        children: "Continue to Open reviews"
      })
    }) : null]
  });
}

/***/ },

/***/ "./src/coordinator/components/ReviewMarkingStep.jsx"
/*!**********************************************************!*\
  !*** ./src/coordinator/components/ReviewMarkingStep.jsx ***!
  \**********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ReviewMarkingStep: () => (/* binding */ ReviewMarkingStep)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/apiErrors */ "./src/shared/apiErrors.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





/**
 * Per-review marking lifecycle: start (open) and pause marking for each round.
 * Shown after panel assignments are complete.
 */

function ReviewMarkingStep({
  sessionId,
  sessionStatus,
  onReload,
  onNotice,
  isWizardTerminalStep = false
}) {
  const canAssignReviewers = window.prAppData?.canAssignReviewers !== false;
  const [reviews, setReviews] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [reviewerCount, setReviewerCount] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(0);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [busy, setBusy] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [showInviteConfirm, setShowInviteConfirm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [inviteNotice, setInviteNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const loadReviews = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!sessionId) {
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [reviewsData, reviewersData] = await Promise.all([(0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.get)(`/sessions/${sessionId}/reviews`), canAssignReviewers ? (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.get)(`/sessions/${sessionId}/reviewers`) : Promise.resolve({
        reviewers: []
      })]);
      setReviews(reviewsData.reviews ?? []);
      const emails = new Set();
      (reviewersData.reviewers ?? []).forEach(reviewer => {
        const email = (reviewer.email ?? '').trim().toLowerCase();
        if (email) {
          emails.add(email);
        }
      });
      setReviewerCount(emails.size);
    } catch {
      setError('Could not load review rounds.');
      setReviews([]);
      setReviewerCount(0);
    } finally {
      setLoading(false);
    }
  }, [sessionId, canAssignReviewers]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    loadReviews();
  }, [loadReviews]);
  const refreshAll = async () => {
    await loadReviews();
    await onReload?.();
  };
  const setMarkingActive = async (review, nextActive) => {
    setBusy(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/reviews/${review.id}`, {
        marking_active: nextActive
      });
      await refreshAll();
    } catch {
      onNotice?.({
        variant: 'error',
        message: 'Could not update marking status.'
      });
    } finally {
      setBusy(false);
    }
  };
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.PageContentSkeleton, {
      showTitle: false,
      rows: 3
    });
  }
  const projectInactive = sessionStatus === 'draft';
  const projectClosed = sessionStatus === 'closed';
  const canBulkInvite = canAssignReviewers && !projectInactive && !projectClosed && reviewerCount > 0;
  const handleBulkInvite = async () => {
    setBusy(true);
    setInviteNotice(null);
    try {
      const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/send-all-credentials`, {
        force: false
      });
      setInviteNotice({
        variant: (result.failed ?? 0) > 0 ? 'warning' : 'success',
        message: `Email all reviewers: ${result.sent ?? 0} sent, ${result.skipped ?? 0} skipped, ${result.failed ?? 0} failed.`
      });
      await refreshAll();
    } catch (err) {
      setInviteNotice({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Could not email reviewers.')
      });
    } finally {
      setBusy(false);
      setShowInviteConfirm(false);
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("section", {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h2", {
      className: "text-lg font-semibold text-text",
      children: "Open reviews"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      className: "mt-1 text-sm text-text-muted",
      children: "Start marking when reviewers should score a round; pause marking to stop new entries without deleting data. Each round needs a confirmed rubric before it can open."
    }), canAssignReviewers ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "mt-4 flex flex-wrap items-center gap-3",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "secondary",
        size: "sm",
        disabled: busy || !canBulkInvite,
        title: projectInactive ? 'Open the project for marking first' : projectClosed ? 'Project is closed' : reviewerCount === 0 ? 'Assign panel reviewers first' : undefined,
        onClick: () => setShowInviteConfirm(true),
        children: "Email all reviewers"
      }), reviewerCount > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("span", {
        className: "text-xs text-text-muted",
        children: [reviewerCount, " distinct reviewer", reviewerCount === 1 ? '' : 's', " on this project"]
      }) : null]
    }) : null, inviteNotice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "mt-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        variant: inviteNotice.variant,
        onDismiss: () => setInviteNotice(null),
        children: inviteNotice.message
      })
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: showInviteConfirm,
      title: "Email all reviewers?",
      consequences: [`${reviewerCount} distinct reviewer email${reviewerCount === 1 ? '' : 's'} will be contacted.`, 'Reviewers who have already received credentials are skipped — use "Resend to all" on the Reviewers step to force a refresh.', 'Reviewer portal access is suspended when you close the project.'],
      confirmLabel: "Send emails",
      confirmVariant: "primary",
      confirmDisabled: busy,
      onConfirm: handleBulkInvite,
      onCancel: () => setShowInviteConfirm(false)
    }), projectInactive ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "mt-4 rounded-md border border-border bg-surface-raised p-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("p", {
        className: "text-sm text-text-muted",
        children: ["This project is still a draft. Use ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
          className: "text-text",
          children: "Open for marking"
        }), " at the top of the wizard so reviewers can see assignments, then start each round below."]
      })
    }) : null, projectClosed ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "mt-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        variant: "warning",
        children: "This project is closed. Marking cannot be started or paused."
      })
    }) : null, error ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "mt-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        variant: "error",
        children: error
      })
    }) : null, reviews.length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      className: "mt-4 text-sm text-warning",
      children: "No review rounds found. Add rounds on the Reviews & rubrics step first."
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("ul", {
      className: "mt-6 space-y-4",
      children: reviews.map(review => {
        const marksLocked = Boolean(review.coordinator_marks_locked);
        const markingOn = marksLocked ? false : Boolean(review.marking_active);
        const rubricConfirmed = review.status === 'confirmed';
        const canStart = !projectClosed && !projectInactive && !marksLocked && rubricConfirmed && !markingOn;
        const canPause = !projectClosed && !marksLocked && markingOn;
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("li", {
          className: "rounded-lg border border-border bg-surface-raised p-4 shadow-sm",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            className: "flex flex-wrap items-start justify-between gap-3",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h3", {
                className: "text-base font-semibold text-text",
                children: review.label
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
                className: "mt-2 flex flex-wrap items-center gap-2",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.StatusChip, {
                  variant: review.status
                }), marksLocked ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.StatusChip, {
                  variant: "confirmed",
                  label: "Marks locked"
                }) : markingOn ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.StatusChip, {
                  variant: "active",
                  label: "Marking open"
                }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.StatusChip, {
                  variant: "draft",
                  label: "Marking paused"
                })]
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
              className: "flex flex-wrap gap-2",
              children: marksLocked ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                className: "text-xs text-text-muted",
                children: "Frozen on Reports \u2014 unlock there to change marking."
              }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
                  variant: "primary",
                  size: "sm",
                  disabled: busy || !canStart,
                  title: !rubricConfirmed ? 'Confirm the rubric on Reviews & rubrics first' : projectInactive ? 'Open the project for marking first' : undefined,
                  onClick: () => setMarkingActive(review, true),
                  children: "Start marking"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
                  variant: "secondary",
                  size: "sm",
                  disabled: busy || !canPause,
                  onClick: () => setMarkingActive(review, false),
                  children: "Pause marking"
                })]
              })
            })]
          }), !rubricConfirmed ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "mt-3 text-xs text-text-muted",
            children: "Confirm the rubric on Reviews & rubrics before starting this round."
          }) : markingOn ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "mt-3 text-xs text-text-muted",
            children: "Reviewers can enter marks for this round while marking is open and the project is active."
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "mt-3 text-xs text-text-muted",
            children: "Marking is paused. Reviewers cannot save new marks until you start marking again."
          })]
        }, review.id);
      })
    }), isWizardTerminalStep ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      className: "mt-8 text-sm text-text-muted",
      children: "Setup is complete for this project. Return to the dashboard or use Progress and Reports as marking proceeds."
    }) : null]
  });
}

/***/ },

/***/ "./src/coordinator/components/ReviewRoundsStep.jsx"
/*!*********************************************************!*\
  !*** ./src/coordinator/components/ReviewRoundsStep.jsx ***!
  \*********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ReviewRoundsStep: () => (/* binding */ ReviewRoundsStep)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/apiErrors */ "./src/shared/apiErrors.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





function ReviewRoundsStep({
  sessionId,
  onReload,
  onNotice,
  canAdvanceToAssignments = true,
  onContinue,
  showContinueButton = true,
  suppressIntro = false,
  showMarkingControls = true
}) {
  const [reviews, setReviews] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [busy, setBusy] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [editingId, setEditingId] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [editLabel, setEditLabel] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [deleteTarget, setDeleteTarget] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [deletePhrase, setDeletePhrase] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const loadReviews = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!sessionId) {
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const data = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.get)(`/sessions/${sessionId}/reviews`);
      setReviews(data.reviews ?? []);
    } catch {
      setError('Could not load review rounds.');
      setReviews([]);
    } finally {
      setLoading(false);
    }
  }, [sessionId]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    loadReviews();
  }, [loadReviews]);
  const refreshAll = async () => {
    await loadReviews();
    await onReload?.();
  };
  const handleAdd = async () => {
    setBusy(true);
    setError(null);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/reviews`, {
        label: `Review ${reviews.length + 1}`,
        sort_order: reviews.length
      });
      await refreshAll();
    } catch {
      setError('Could not create review round.');
    } finally {
      setBusy(false);
    }
  };
  const startEdit = review => {
    setEditingId(review.id);
    setEditLabel(review.label);
  };
  const saveLabel = async reviewId => {
    const label = editLabel.trim();
    setEditingId(null);
    if (!label) {
      return;
    }
    setBusy(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/reviews/${reviewId}`, {
        label
      });
      await refreshAll();
    } catch {
      onNotice?.({
        variant: 'error',
        message: 'Could not rename review round.'
      });
    } finally {
      setBusy(false);
    }
  };
  const toggleMarkingActive = async (review, nextActive) => {
    setBusy(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/reviews/${review.id}`, {
        marking_active: nextActive
      });
      await refreshAll();
    } catch {
      onNotice?.({
        variant: 'error',
        message: 'Could not update marking status.'
      });
    } finally {
      setBusy(false);
    }
  };
  const moveReview = async (index, direction) => {
    const targetIndex = index + direction;
    if (targetIndex < 0 || targetIndex >= reviews.length) {
      return;
    }
    const current = reviews[index];
    const target = reviews[targetIndex];
    setBusy(true);
    try {
      await Promise.all([(0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/reviews/${current.id}`, {
        sort_order: targetIndex
      }), (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/reviews/${target.id}`, {
        sort_order: index
      })]);
      await refreshAll();
    } catch {
      onNotice?.({
        variant: 'error',
        message: 'Could not reorder review rounds.'
      });
    } finally {
      setBusy(false);
    }
  };
  const closeDeleteDialog = () => {
    setDeleteTarget(null);
    setDeletePhrase('');
  };
  const confirmDeleteReview = async () => {
    if (!deleteTarget) {
      return;
    }
    setBusy(true);
    try {
      const payload = deleteTarget.has_entered_scores === true ? {
        confirm_label: deletePhrase.trim()
      } : undefined;
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.del)(`/sessions/${sessionId}/reviews/${deleteTarget.id}`, payload);
      closeDeleteDialog();
      await refreshAll();
    } catch (err) {
      onNotice?.({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Could not remove review round.')
      });
    } finally {
      setBusy(false);
    }
  };
  const destructiveDelete = deleteTarget?.has_entered_scores === true;
  const phraseMatchesDelete = deletePhrase.trim() === String(deleteTarget?.label ?? '').trim();
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.PageContentSkeleton, {
      showTitle: false,
      rows: 3
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("section", {
    children: [suppressIntro ? null : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("h2", {
        className: "text-lg font-semibold text-text",
        children: "Reviews"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
        className: "mt-1 text-sm text-text-muted",
        children: "Add, rename, reorder, or remove review rounds. Confirm rubrics and open marking on the final Open reviews step after panel assignments."
      })]
    }), error ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "mt-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        variant: "error",
        children: error
      })
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "mt-4 flex flex-wrap gap-2",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "primary",
        disabled: busy,
        onClick: handleAdd,
        children: "Add review round"
      })
    }), reviews.length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
      className: "mt-4 text-sm text-warning",
      children: "At least one review round is required."
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("ul", {
      className: "mt-4 space-y-3",
      children: reviews.map((review, index) => {
        const onlyRound = reviews.length <= 1;
        const canOfferDelete = !onlyRound;
        const isEditing = editingId === review.id;
        const marksLocked = Boolean(review.coordinator_marks_locked);
        const markingOn = marksLocked ? false : Boolean(review.marking_active);
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("li", {
          className: "rounded-md border border-border bg-surface-raised px-3 py-3",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
            className: "flex flex-wrap items-center gap-2 text-sm",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              className: "flex min-w-0 flex-1 flex-wrap items-center gap-2",
              children: [isEditing ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("input", {
                type: "text",
                value: editLabel,
                onChange: e => setEditLabel(e.target.value),
                onBlur: () => saveLabel(review.id),
                onKeyDown: e => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    saveLabel(review.id);
                  }
                  if (e.key === 'Escape') {
                    setEditingId(null);
                  }
                },
                className: "min-w-[12rem] flex-1 rounded-md border border-border bg-surface px-2 py-1",
                autoFocus: true
              }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("button", {
                type: "button",
                className: "font-medium text-text hover:underline",
                onClick: () => startEdit(review),
                children: review.label
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.StatusChip, {
                variant: review.status
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
              className: "flex flex-wrap items-center gap-2",
              children: [showMarkingControls ? marksLocked ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.StatusChip, {
                  variant: "confirmed",
                  label: "Marks locked"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                  className: "text-xs text-text-muted",
                  children: "Frozen on Reports \u2014 use Unlock there to reopen marking."
                })]
              }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("label", {
                className: "flex items-center gap-2 text-text-muted",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("input", {
                  type: "checkbox",
                  checked: markingOn,
                  disabled: busy || review.status !== 'confirmed',
                  onChange: e => toggleMarkingActive(review, e.target.checked)
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                  children: markingOn ? 'Marking active' : 'Marking paused'
                })]
              }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
                size: "sm",
                variant: "secondary",
                disabled: busy || index === 0,
                onClick: () => moveReview(index, -1),
                children: "\u2191"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
                size: "sm",
                variant: "secondary",
                disabled: busy || index === reviews.length - 1,
                onClick: () => moveReview(index, 1),
                children: "\u2193"
              }), canOfferDelete ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
                size: "sm",
                variant: "secondary",
                disabled: busy,
                title: onlyRound ? undefined : review.has_entered_scores ? 'Removes this round and all entered scores' : undefined,
                onClick: () => {
                  setDeletePhrase('');
                  setDeleteTarget(review);
                },
                children: "Remove"
              }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
                className: "text-xs text-text-muted",
                title: "Every project keeps at least one review round.",
                children: "Cannot remove only round"
              })]
            })]
          }), showMarkingControls ? review.status !== 'confirmed' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "mt-2 text-xs text-text-muted",
            children: "Confirm the rubric below before activating marking."
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "mt-2 text-xs text-text-muted",
            children: "Reviewers can enter marks when the project is active, the rubric is confirmed, and marking is active for this round."
          }) : review.status !== 'confirmed' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("p", {
            className: "mt-2 text-xs text-text-muted",
            children: "Confirm the rubric below before this round can open on the Open reviews step."
          }) : null]
        }, review.id);
      })
    }), typeof onContinue === 'function' && showContinueButton ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      className: "mt-6 flex justify-end",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "primary",
        onClick: onContinue,
        disabled: !canAdvanceToAssignments || busy,
        title: !canAdvanceToAssignments ? 'Add rubric criteria for every review round before assigning panels' : undefined,
        children: "Continue to Panel assignments"
      })
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: deleteTarget != null && !destructiveDelete,
      title: deleteTarget ? `Remove ${deleteTarget.label}?` : 'Remove review round?',
      consequences: ['This review round, its rubric criteria, weights, and assignments for this round will be deleted.'],
      confirmLabel: "Remove review round",
      confirmVariant: "destructive",
      onConfirm: confirmDeleteReview,
      onCancel: closeDeleteDialog,
      confirmDisabled: busy
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: deleteTarget != null && destructiveDelete,
      title: deleteTarget ? `Delete ${deleteTarget.label} and all scores?` : 'Delete review round?',
      consequences: ['All entered marks for this round will be permanently removed.', 'Panel freezes and unfreeze requests tied to this round will be cleared.'],
      confirmLabel: "Delete round and scores",
      confirmVariant: "destructive",
      onConfirm: confirmDeleteReview,
      onCancel: closeDeleteDialog,
      confirmDisabled: busy || !phraseMatchesDelete,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
        className: "space-y-2 text-sm text-text-muted",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("p", {
          children: ["Type the exact review round name", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {
            className: "text-text",
            children: deleteTarget?.label
          }), ' ', "to confirm."]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("input", {
          type: "text",
          className: "w-full rounded-md border border-border bg-surface px-3 py-2 text-text",
          value: deletePhrase,
          onChange: e => setDeletePhrase(e.target.value),
          autoComplete: "off",
          "aria-label": "Type review round name to confirm deletion"
        })]
      })
    })]
  });
}

/***/ },

/***/ "./src/coordinator/components/ReviewRubricBlock.jsx"
/*!**********************************************************!*\
  !*** ./src/coordinator/components/ReviewRubricBlock.jsx ***!
  \**********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ReviewRubricBlock: () => (/* binding */ ReviewRubricBlock)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/apiErrors */ "./src/shared/apiErrors.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var _shared_rubricCriteria__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../shared/rubricCriteria */ "./src/shared/rubricCriteria.js");
/* harmony import */ var _RubricTable__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./RubricTable */ "./src/coordinator/components/RubricTable.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);







function ReviewRubricBlock({
  sessionId,
  review,
  busy = false,
  onBusyChange,
  onUpdated,
  embedded = false
}) {
  const [dialog, setDialog] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [pendingCriteria, setPendingCriteria] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [reconfirmAction, setReconfirmAction] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('keep_flag');
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const setBusy = value => {
    onBusyChange?.(value);
  };
  const reportError = (err, fallback) => {
    setError((0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, fallback));
  };
  const clearError = () => {
    setError(null);
  };
  const persistCriteria = async criteria => {
    await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/reviews/${review.id}/criteria`, {
      criteria
    });
  };
  const handleSaveCriteria = async rows => {
    const validationError = (0,_shared_rubricCriteria__WEBPACK_IMPORTED_MODULE_4__.validateCriteriaRows)(rows);
    if (validationError) {
      setError(validationError);
      return;
    }
    setBusy(true);
    clearError();
    try {
      await persistCriteria((0,_shared_rubricCriteria__WEBPACK_IMPORTED_MODULE_4__.buildCriteriaPayload)(rows));
      await onUpdated?.();
    } catch (error) {
      reportError(error, 'Could not save rubric criteria.');
    } finally {
      setBusy(false);
    }
  };
  const runConfirm = async markAction => {
    setBusy(true);
    clearError();
    try {
      if (pendingCriteria) {
        await persistCriteria(pendingCriteria);
      }
      const body = markAction ? {
        mark_action: markAction
      } : {};
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/reviews/${review.id}/confirm`, body);
      setDialog(null);
      setPendingCriteria(null);
      await onUpdated?.();
    } catch (error) {
      reportError(error, 'Could not confirm rubric.');
    } finally {
      setBusy(false);
    }
  };
  const runUnlock = async () => {
    setBusy(true);
    clearError();
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/reviews/${review.id}/unlock`);
      setDialog(null);
      await onUpdated?.();
    } catch (error) {
      reportError(error, 'Could not unlock rubric.');
    } finally {
      setBusy(false);
    }
  };
  const openConfirmDialog = rows => {
    const validationError = (0,_shared_rubricCriteria__WEBPACK_IMPORTED_MODULE_4__.validateCriteriaRows)(rows);
    if (validationError) {
      setError(validationError);
      return;
    }
    clearError();
    setPendingCriteria((0,_shared_rubricCriteria__WEBPACK_IMPORTED_MODULE_4__.buildCriteriaPayload)(rows));
    if (review.status === 'unlocked' && review.has_marks) {
      setReconfirmAction('keep_flag');
      setDialog({
        type: 'reconfirm'
      });
      return;
    }
    setDialog({
      type: 'confirm'
    });
  };
  const dialogs = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: dialog?.type === 'confirm',
      title: `Confirm ${review.label ?? 'rubric'}?`,
      consequences: ['Reviewers can enter marks for this round when the project is active.', 'Criteria stay editable until a score is saved; unlock to edit after scoring starts.'],
      confirmLabel: "Confirm rubric",
      onConfirm: () => runConfirm(),
      onCancel: () => {
        setDialog(null);
        setPendingCriteria(null);
      }
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: dialog?.type === 'unlock',
      title: `Unlock ${review.label ?? 'rubric'}?`,
      consequences: ['Marking is paused until you confirm the rubric again.', 'Reviewers cannot submit new marks while unlocked.'],
      confirmLabel: "Unlock rubric",
      confirmVariant: "destructive",
      onConfirm: runUnlock,
      onCancel: () => setDialog(null)
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: dialog?.type === 'reconfirm',
      title: `Re-confirm ${review.label ?? 'rubric'}?`,
      consequences: ['Keep and flag: existing marks stay visible but are flagged for review.', 'Clear marks: all marks for this review round are removed.', 'Marking reopens after confirmation when the project is active.'],
      confirmLabel: "Re-confirm rubric",
      onConfirm: () => runConfirm(reconfirmAction),
      onCancel: () => {
        setDialog(null);
        setPendingCriteria(null);
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("fieldset", {
        className: "space-y-2 text-sm",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("label", {
          className: "flex items-center gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
            type: "radio",
            name: `mark_action_${review.id}`,
            value: "keep_flag",
            checked: reconfirmAction === 'keep_flag',
            onChange: () => setReconfirmAction('keep_flag')
          }), "Keep and flag existing marks (recommended)"]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("label", {
          className: "flex items-center gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
            type: "radio",
            name: `mark_action_${review.id}`,
            value: "clear",
            checked: reconfirmAction === 'clear',
            onChange: () => setReconfirmAction('clear')
          }), "Clear marks for this review round"]
        })]
      })
    })]
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_RubricTable__WEBPACK_IMPORTED_MODULE_5__.RubricTable, {
      review: review,
      busy: busy,
      embedded: embedded,
      onSave: handleSaveCriteria,
      onConfirm: openConfirmDialog,
      onUnlock: () => setDialog({
        type: 'unlock'
      })
    }), error ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      className: "mt-3",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        variant: "error",
        children: error
      })
    }) : null, dialogs]
  });
}

/***/ },

/***/ "./src/coordinator/components/ReviewsSetupStep.jsx"
/*!*********************************************************!*\
  !*** ./src/coordinator/components/ReviewsSetupStep.jsx ***!
  \*********************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ReviewsSetupStep: () => (/* binding */ ReviewsSetupStep)
/* harmony export */ });
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var _RubricsPanel__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./RubricsPanel */ "./src/coordinator/components/RubricsPanel.jsx");
/* harmony import */ var _ReviewRoundsStep__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./ReviewRoundsStep */ "./src/coordinator/components/ReviewRoundsStep.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);




/**
 * Wizard step: review round CRUD + rubric criteria, weights, and flagged marks.
 * Per-round start/pause marking lives on the Open reviews step after assignments.
 */

function ReviewsSetupStep({
  sessionId,
  onReload,
  onNotice,
  canAdvanceToAssignments,
  onContinue,
  rubricsReloadDependency
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("section", {
    className: "space-y-10",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("h2", {
        className: "text-lg font-semibold text-text",
        children: "Reviews & rubrics"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
        className: "mt-1 text-sm text-text-muted",
        children: "Create and order review rounds, then define and confirm rubric criteria and weights. Open or pause marking for each round on the final step after panel assignments."
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
        className: "mt-6",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_ReviewRoundsStep__WEBPACK_IMPORTED_MODULE_2__.ReviewRoundsStep, {
          sessionId: sessionId,
          onReload: onReload,
          onNotice: onNotice,
          showContinueButton: false,
          showMarkingControls: false,
          suppressIntro: true
        })
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
      className: "border-t border-border pt-10",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("h3", {
        className: "text-lg font-semibold text-text",
        children: "Rubric criteria & weights"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
        className: "mt-1 text-sm text-text-muted",
        children: "Confirm each rubric before opening that round for marking. Adjust weight ratios when combining rounds into overall scores."
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
        className: "mt-6",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_RubricsPanel__WEBPACK_IMPORTED_MODULE_1__.RubricsPanel, {
          sessionId: sessionId,
          compact: true,
          hideRoundActions: true,
          reloadDependency: rubricsReloadDependency
        })
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      className: "flex justify-end border-t border-border pt-6",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_0__.Button, {
        variant: "primary",
        onClick: onContinue,
        disabled: !canAdvanceToAssignments,
        title: !canAdvanceToAssignments ? 'Add rubric criteria for every review round first' : undefined,
        children: "Continue to Panel assignments"
      })
    })]
  });
}

/***/ },

/***/ "./src/coordinator/components/RubricTable.jsx"
/*!****************************************************!*\
  !*** ./src/coordinator/components/RubricTable.jsx ***!
  \****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   RubricTable: () => (/* binding */ RubricTable)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var _shared_rubricEditable__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/rubricEditable */ "./src/shared/rubricEditable.js");
/* harmony import */ var _shared_rubricCriteria__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/rubricCriteria */ "./src/shared/rubricCriteria.js");
/* harmony import */ var _shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../shared/TableScrollViewport */ "./src/shared/TableScrollViewport.jsx");
/* harmony import */ var _shared_tableStyles__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../shared/tableStyles */ "./src/shared/tableStyles.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);







const EMPTY_CRITERION = {
  label: '',
  max_marks: ''
};
const INPUT_CLASS = 'w-full rounded-md border border-border px-3 py-1.5 text-sm';
const MAX_MARKS_INPUT_CLASS = 'w-28 rounded-md border border-border px-3 py-1.5 text-sm';
function mapCriterionRow(row) {
  const mapped = {
    label: row.label ?? '',
    max_marks: String(row.max_marks ?? '')
  };
  if (row.id != null && row.id !== '') {
    mapped.id = Number(row.id);
  }
  return mapped;
}
function statusVariant(status) {
  if (status === 'confirmed') {
    return 'confirmed';
  }
  if (status === 'unlocked') {
    return 'unlocked';
  }
  return 'draft';
}
function TotalMarksLabel({
  sum
}) {
  if (sum == null) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
      className: "text-sm text-text-muted",
      children: ["Total marks:", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
        className: "font-medium text-text",
        children: "\u2014"
      })]
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
    className: "text-sm text-text-muted",
    children: ["Total marks:", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
      className: "font-medium text-text",
      children: (0,_shared_rubricCriteria__WEBPACK_IMPORTED_MODULE_3__.formatMarksSum)(sum)
    })]
  });
}
function RubricTable({
  review,
  onSave,
  onConfirm,
  onUnlock,
  busy = false,
  embedded = false
}) {
  const canConfirm = review.status === 'draft' || review.status === 'unlocked';
  const editable = (0,_shared_rubricEditable__WEBPACK_IMPORTED_MODULE_2__.isCriteriaEditable)(review);
  const showPreMarkNotice = review.status === 'confirmed' && !review.has_marks && editable;
  const [rows, setRows] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(review.criteria ?? []);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    setRows(review.criteria?.length ? review.criteria.map(mapCriterionRow) : [{
      ...EMPTY_CRITERION
    }]);
  }, [review.id, review.criteria, review.status, review.has_marks, review.criteria_editable]);
  const totalMarksSum = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    if (editable) {
      return (0,_shared_rubricCriteria__WEBPACK_IMPORTED_MODULE_3__.sumCriteriaMaxMarks)(rows);
    }
    return (0,_shared_rubricCriteria__WEBPACK_IMPORTED_MODULE_3__.sumCriteriaMaxMarks)(review.criteria ?? []);
  }, [editable, rows, review.criteria]);
  const updateRow = (index, field, value) => {
    setRows(current => current.map((row, i) => i === index ? {
      ...row,
      [field]: value
    } : row));
  };
  const addRow = () => {
    if (!editable) {
      return;
    }
    setRows(current => [...current, {
      ...EMPTY_CRITERION
    }]);
  };
  const removeRow = index => {
    if (!editable || rows.length <= 1) {
      return;
    }
    setRows(current => current.filter((_, i) => i !== index));
  };
  const actionButtons = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    className: "flex flex-wrap gap-2",
    children: [editable ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
      variant: "secondary",
      disabled: busy,
      onClick: () => onSave(rows),
      children: "Save"
    }) : null, review.status === 'confirmed' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
      variant: "secondary",
      disabled: busy,
      onClick: onUnlock,
      children: "Unlock"
    }) : null, editable && canConfirm ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
      disabled: busy,
      onClick: () => onConfirm(rows),
      children: "Confirm"
    }) : null]
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_shared_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
    className: embedded ? 'space-y-3 border-0 bg-transparent p-0 shadow-none' : 'space-y-4',
    children: [embedded ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      className: "flex flex-wrap items-center justify-between gap-2",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "flex flex-wrap items-center gap-3",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h4", {
          className: "text-sm font-medium text-text-muted",
          children: "Rubric criteria"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(TotalMarksLabel, {
          sum: totalMarksSum
        })]
      }), actionButtons]
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
      className: "flex flex-wrap items-start justify-between gap-2",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "space-y-1",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "flex flex-wrap items-center gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h3", {
            className: "text-base font-semibold text-text",
            children: review.label
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_1__.StatusChip, {
            variant: statusVariant(review.status)
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(TotalMarksLabel, {
          sum: totalMarksSum
        })]
      }), actionButtons]
    }), showPreMarkNotice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
      className: "text-sm text-text-muted",
      children: "Marking is open; criteria remain editable until a score is saved."
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_4__.TableScrollWrapper, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("table", {
        className: "w-full min-w-[28rem] border-collapse text-sm",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("thead", {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("tr", {
            className: "border-b border-border text-left text-text-muted",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("th", {
              className: "px-1 py-2 pr-3 font-medium",
              children: "Criterion"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("th", {
              className: "px-1 py-2 pr-3 font-medium",
              children: "Max marks"
            }), editable ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("th", {
              className: "px-1 py-2 w-20 font-medium",
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
                className: "sr-only",
                children: "Actions"
              })
            }) : null]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("tbody", {
          children: rows.map((row, index) => {
            const removeLabel = row.label.trim() || `criterion ${index + 1}`;
            const canRemoveRow = editable && rows.length > 1;
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("tr", {
              className: _shared_tableStyles__WEBPACK_IMPORTED_MODULE_5__.TABLE_BODY_ROW_SOFT,
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("td", {
                className: "px-1 py-2 pr-3",
                children: editable ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
                  type: "text",
                  className: INPUT_CLASS,
                  value: row.label,
                  onChange: event => updateRow(index, 'label', event.target.value)
                }) : row.label
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("td", {
                className: "px-1 py-2 pr-3",
                children: editable ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
                  type: "text",
                  inputMode: "decimal",
                  className: MAX_MARKS_INPUT_CLASS,
                  value: row.max_marks,
                  onChange: event => updateRow(index, 'max_marks', event.target.value)
                }) : row.max_marks
              }), editable ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("td", {
                className: "px-1 py-2",
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
                  variant: "ghost",
                  size: "sm",
                  disabled: busy || !canRemoveRow,
                  title: !canRemoveRow ? 'At least one criterion is required.' : undefined,
                  "aria-label": `Remove criterion ${removeLabel}`,
                  onClick: () => removeRow(index),
                  children: "Remove"
                })
              }) : null]
            }, row.id ?? `criterion-${index}`);
          })
        })]
      })
    }), editable ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
      variant: "secondary",
      size: "sm",
      icon: "plus",
      disabled: busy,
      onClick: addRow,
      children: "Add criterion"
    }) : null]
  });
}

/***/ },

/***/ "./src/coordinator/components/RubricsPanel.jsx"
/*!*****************************************************!*\
  !*** ./src/coordinator/components/RubricsPanel.jsx ***!
  \*****************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   RubricsPanel: () => (/* binding */ RubricsPanel)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/apiErrors */ "./src/shared/apiErrors.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var _ReviewRubricBlock__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./ReviewRubricBlock */ "./src/coordinator/components/ReviewRubricBlock.jsx");
/* harmony import */ var _WeightConfiguration__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./WeightConfiguration */ "./src/coordinator/components/WeightConfiguration.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);







function RubricsPanel({
  sessionId,
  compact = false,
  hideRoundActions = false,
  reloadDependency
}) {
  const [reviews, setReviews] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [weights, setWeights] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    review_weights: [],
    reviewer_weights: [],
    has_marks: false
  });
  const [flaggedMarks, setFlaggedMarks] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [busy, setBusy] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [deleteTarget, setDeleteTarget] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [deletePhrase, setDeletePhrase] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const loadRubrics = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!sessionId) {
      return;
    }
    setLoading(true);
    setError(null);
    try {
      const [reviewData, weightData] = await Promise.all([(0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.get)(`/sessions/${sessionId}/reviews`), (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.get)(`/sessions/${sessionId}/weights`)]);
      const nextReviews = reviewData.reviews ?? [];
      setReviews(nextReviews);
      setWeights(weightData);
      const marksLists = await Promise.all(nextReviews.map(review => (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.get)(`/sessions/${sessionId}/reviews/${review.id}/marks`).then(data => ({
        reviewId: review.id,
        reviewLabel: review.label,
        marks: data.marks ?? []
      }))));
      const flagged = marksLists.flatMap(entry => entry.marks.filter(mark => mark.flagged).map(mark => ({
        ...mark,
        review_id: entry.reviewId,
        review_label: entry.reviewLabel
      })));
      setFlaggedMarks(flagged);
    } catch {
      setReviews([]);
      setError('Could not load rubrics for this project.');
    } finally {
      setLoading(false);
    }
  }, [sessionId]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    loadRubrics();
  }, [loadRubrics, reloadDependency]);
  const ensureReview = async () => {
    if (reviews.length > 0) {
      return reviews;
    }
    await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/reviews`, {
      label: 'Review 1',
      criteria: [{
        label: 'Criterion 1',
        max_marks: 10,
        weight: 1
      }]
    });
    await loadRubrics();
  };
  const handleCreateReview = async () => {
    setBusy(true);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)(`/sessions/${sessionId}/reviews`, {
        label: `Review ${reviews.length + 1}`
      });
      await loadRubrics();
    } catch {
      setError('Could not create review round.');
    } finally {
      setBusy(false);
    }
  };
  const closeDeleteDialog = () => {
    setDeleteTarget(null);
    setDeletePhrase('');
  };
  const confirmDeleteReview = async () => {
    if (!deleteTarget) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const payload = deleteTarget.has_entered_scores === true ? {
        confirm_label: deletePhrase.trim()
      } : undefined;
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.del)(`/sessions/${sessionId}/reviews/${deleteTarget.id}`, payload);
      closeDeleteDialog();
      await loadRubrics();
    } catch (err) {
      setError((0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Could not remove review round.'));
    } finally {
      setBusy(false);
    }
  };
  const handleSaveWeights = async payload => {
    setBusy(true);
    setError(null);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.put)(`/sessions/${sessionId}/weights`, payload);
      await loadRubrics();
    } catch {
      setError('Could not save weights.');
    } finally {
      setBusy(false);
    }
  };
  if (loading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.PageContentSkeleton, {
      rows: 3
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    className: "space-y-6",
    children: [!compact ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      className: "flex justify-end",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        variant: "secondary",
        disabled: busy,
        onClick: handleCreateReview,
        children: "Add review round"
      })
    }) : null, error ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
      variant: "error",
      children: error
    }) : null, flaggedMarks.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("section", {
      className: "rounded-lg border border-border bg-surface p-4 shadow-sm",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h3", {
        className: "mb-3 text-base font-semibold text-text",
        children: "Flagged marks"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("ul", {
        className: "space-y-2 text-sm",
        children: flaggedMarks.map(mark => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("li", {
          className: "flex flex-wrap items-center gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("span", {
            children: [mark.review_label, " \xB7 Student", ' ', mark.student_id, " \xB7 Reviewer", ' ', mark.reviewer_user_id]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.FlaggedMarkChip, {})]
        }, `flagged-${mark.id}`))
      })]
    }) : null, reviews.length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.EmptyState, {
      title: "No review rounds yet",
      description: compact ? 'Use Add review round at the top of Reviews & rubrics, then define criteria here.' : 'Create Review 1 to start defining criteria.',
      action: compact ? null : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
        disabled: busy,
        onClick: async () => {
          setBusy(true);
          try {
            await ensureReview();
          } finally {
            setBusy(false);
          }
        },
        children: "Create Review 1"
      })
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.Fragment, {
      children: [reviews.map(review => {
        const onlyRound = reviews.length <= 1;
        const canOfferDelete = !onlyRound;
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
          className: "rounded-lg border border-border bg-surface shadow-sm",
          children: [!hideRoundActions ? canOfferDelete ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            className: "flex justify-end border-b border-border px-4 py-2",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
              size: "sm",
              variant: "secondary",
              disabled: busy,
              title: review.has_entered_scores ? 'Removes this round and all entered scores (confirmation required)' : undefined,
              onClick: () => {
                setDeletePhrase('');
                setDeleteTarget(review);
              },
              children: "Remove review round"
            })
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            className: "flex justify-end border-b border-border px-4 py-2",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
              className: "text-xs text-text-muted",
              children: "This project must keep at least one review round."
            })
          }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
            className: "p-4",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_ReviewRubricBlock__WEBPACK_IMPORTED_MODULE_4__.ReviewRubricBlock, {
              sessionId: sessionId,
              review: review,
              busy: busy,
              onBusyChange: setBusy,
              onUpdated: loadRubrics
            })
          })]
        }, review.id);
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_WeightConfiguration__WEBPACK_IMPORTED_MODULE_5__.WeightConfiguration, {
        reviewWeights: (weights.review_weights ?? []).map(row => ({
          ...row,
          weight: String(row.weight ?? 1)
        })),
        reviewerWeights: (weights.reviewer_weights ?? []).map(row => ({
          ...row,
          weight: String(row.weight ?? 1)
        })),
        hasMarks: weights.has_marks,
        busy: busy,
        onSave: handleSaveWeights
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: deleteTarget != null && deleteTarget.has_entered_scores !== true,
      title: deleteTarget ? `Remove ${deleteTarget.label}?` : 'Remove review round?',
      consequences: ['This review round, its rubric criteria, weights, and per-round assignments will be deleted.'],
      confirmLabel: "Remove review round",
      confirmVariant: "destructive",
      onConfirm: confirmDeleteReview,
      onCancel: closeDeleteDialog,
      confirmDisabled: busy
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
      open: deleteTarget != null && deleteTarget.has_entered_scores === true,
      title: deleteTarget ? `Delete ${deleteTarget.label} and all scores?` : 'Delete review round?',
      consequences: ['All entered marks for this round will be permanently removed.', 'Panel freezes and unfreeze requests tied to this round will be cleared.'],
      confirmLabel: "Delete round and scores",
      confirmVariant: "destructive",
      onConfirm: confirmDeleteReview,
      onCancel: closeDeleteDialog,
      confirmDisabled: busy || deletePhrase.trim() !== String(deleteTarget?.label ?? '').trim(),
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
        className: "space-y-2 text-sm text-text-muted",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("p", {
          children: ["Type the exact review round name", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("strong", {
            className: "text-text",
            children: deleteTarget?.label
          }), ' ', "to confirm."]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("input", {
          type: "text",
          className: "w-full rounded-md border border-border bg-surface px-3 py-2 text-text",
          value: deletePhrase,
          onChange: e => setDeletePhrase(e.target.value),
          autoComplete: "off",
          "aria-label": "Type review round name to confirm deletion"
        })]
      })
    })]
  });
}

/***/ },

/***/ "./src/coordinator/components/WeightConfiguration.jsx"
/*!************************************************************!*\
  !*** ./src/coordinator/components/WeightConfiguration.jsx ***!
  \************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   WeightConfiguration: () => (/* binding */ WeightConfiguration)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);



function WeightConfiguration({
  reviewWeights = [],
  reviewerWeights = [],
  hasMarks = false,
  onSave,
  busy = false
}) {
  const [reviewRows, setReviewRows] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(reviewWeights);
  const [reviewerRows, setReviewerRows] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(reviewerWeights);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    setReviewRows(reviewWeights);
    setReviewerRows(reviewerWeights);
  }, [reviewWeights, reviewerWeights]);
  const handleSave = () => {
    onSave({
      review_weights: reviewRows.map(row => ({
        review_id: row.review_id,
        weight: parseFloat(row.weight) || 1
      })),
      reviewer_weights: reviewerRows.map(row => ({
        review_id: row.review_id,
        reviewer_user_id: row.reviewer_user_id,
        weight: parseFloat(row.weight) || 1
      }))
    });
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)(_shared_components__WEBPACK_IMPORTED_MODULE_1__.Card, {
    className: "space-y-4",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("h3", {
      className: "text-base font-semibold text-text",
      children: "Weights"
    }), hasMarks ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_1__.Notice, {
      variant: "warning",
      children: "Marks already exist. Combined scores will recalculate on the next read when weights change."
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("h4", {
        className: "mb-2 text-sm font-medium text-text-muted",
        children: "Review weights"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("ul", {
        className: "space-y-2",
        children: reviewRows.map(row => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("li", {
          className: "flex items-center gap-3 text-sm",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
            className: "min-w-[8rem]",
            children: row.label
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
            type: "text",
            inputMode: "decimal",
            className: "w-24 rounded-md border border-border px-2 py-1.5",
            value: row.weight,
            disabled: busy,
            onChange: event => setReviewRows(current => current.map(item => item.review_id === row.review_id ? {
              ...item,
              weight: event.target.value
            } : item))
          })]
        }, `review-weight-${row.review_id}`))
      })]
    }), reviewerRows.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("h4", {
        className: "mb-2 text-sm font-medium text-text-muted",
        children: "Reviewer weights"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("ul", {
        className: "space-y-2",
        children: reviewerRows.map(row => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("li", {
          className: "flex items-center gap-3 text-sm",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("span", {
            className: "min-w-[8rem]",
            children: ["Review ", row.review_id, " \xB7 User", ' ', row.reviewer_user_id]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("input", {
            type: "text",
            inputMode: "decimal",
            className: "w-24 rounded-md border border-border px-2 py-1.5",
            value: row.weight,
            disabled: busy,
            onChange: event => setReviewerRows(current => current.map(item => item.review_id === row.review_id && item.reviewer_user_id === row.reviewer_user_id ? {
              ...item,
              weight: event.target.value
            } : item))
          })]
        }, `reviewer-weight-${row.review_id}-${row.reviewer_user_id}`))
      })]
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("button", {
      type: "button",
      className: "inline-flex items-center justify-center rounded-md border border-border bg-surface-raised px-4 py-2 text-sm font-medium text-primary hover:bg-surface disabled:opacity-50",
      disabled: busy,
      onClick: handleSave,
      children: "Save weights"
    })]
  });
}

/***/ },

/***/ "./src/coordinator/pages/SessionWizard.jsx"
/*!*************************************************!*\
  !*** ./src/coordinator/pages/SessionWizard.jsx ***!
  \*************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SessionWizard: () => (/* binding */ SessionWizard)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_router_dom__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react-router-dom */ "./node_modules/react-router-dom/dist/index.js");
/* harmony import */ var react_router_dom__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react-router-dom */ "./node_modules/react-router/dist/index.js");
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_apiErrors__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../shared/apiErrors */ "./src/shared/apiErrors.js");
/* harmony import */ var _shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../shared/TableScrollViewport */ "./src/shared/TableScrollViewport.jsx");
/* harmony import */ var _shared_tableStyles__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../shared/tableStyles */ "./src/shared/tableStyles.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var _shared_hooks_useLoadingPhase__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../../shared/hooks/useLoadingPhase */ "./src/shared/hooks/useLoadingPhase.js");
/* harmony import */ var _components_CsvImportMapper__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../components/CsvImportMapper */ "./src/coordinator/components/CsvImportMapper.jsx");
/* harmony import */ var _components_PanelReviewersStep__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ../components/PanelReviewersStep */ "./src/coordinator/components/PanelReviewersStep.jsx");
/* harmony import */ var _components_PanelsStep__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ../components/PanelsStep */ "./src/coordinator/components/PanelsStep.jsx");
/* harmony import */ var _components_ReviewAssignmentsStep__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ../components/ReviewAssignmentsStep */ "./src/coordinator/components/ReviewAssignmentsStep.jsx");
/* harmony import */ var _components_ReviewMarkingStep__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ../components/ReviewMarkingStep */ "./src/coordinator/components/ReviewMarkingStep.jsx");
/* harmony import */ var _components_ReviewsSetupStep__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(/*! ../components/ReviewsSetupStep */ "./src/coordinator/components/ReviewsSetupStep.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__);







const CONFIRM_WITH_SCORES_PHRASE = 'Confirm';








const STEPS = ['students', 'panels', 'reviewers', 'reviews', 'assignments', 'marking'];
function SessionWizard() {
  const {
    id
  } = (0,react_router_dom__WEBPACK_IMPORTED_MODULE_2__.useParams)();
  const sessionId = Number(id);
  const navigate = (0,react_router_dom__WEBPACK_IMPORTED_MODULE_2__.useNavigate)();
  const [searchParams, setSearchParams] = (0,react_router_dom__WEBPACK_IMPORTED_MODULE_1__.useSearchParams)();
  const stepParam = searchParams.get('step');
  const resolvedStep = stepParam === 'rubrics' ? 'reviews' : stepParam;
  const currentStep = STEPS.includes(resolvedStep) ? resolvedStep : 'students';
  const [reviewsHubReloadTick, setReviewsHubReloadTick] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(0);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (stepParam === 'rubrics') {
      setSearchParams({
        step: 'reviews'
      }, {
        replace: true
      });
    }
  }, [stepParam, setSearchParams]);
  const [session, setSession] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [wizardState, setWizardState] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [enrolled, setEnrolled] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [panels, setPanels] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [reviewers, setReviewers] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [openingSession, setOpeningSession] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [showStudentImport, setShowStudentImport] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [showAddStudentForm, setShowAddStudentForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [addStudentForm, setAddStudentForm] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    reg_no: '',
    name: '',
    program: '',
    batch: ''
  });
  const [addingStudent, setAddingStudent] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [deleteAllDialogOpen, setDeleteAllDialogOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [deleteAllScoresDialogOpen, setDeleteAllScoresDialogOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [deleteAllPhrase, setDeleteAllPhrase] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [deletingAllStudents, setDeletingAllStudents] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const refreshSessionData = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async ({
    showLoading = false
  } = {}) => {
    if (!sessionId) {
      return;
    }
    if (showLoading) {
      setLoading(true);
    }
    try {
      const [sessionData, stateData, studentsData, panelsData, reviewersData] = await Promise.all([(0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.get)(`/sessions/${sessionId}`), (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.get)(`/sessions/${sessionId}/wizard-state`), (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.get)(`/sessions/${sessionId}/students`), (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.get)(`/sessions/${sessionId}/panels`), (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.get)(`/sessions/${sessionId}/reviewers`)]);
      setSession(sessionData);
      setWizardState(stateData);
      setEnrolled(studentsData.students ?? []);
      setPanels(panelsData.panels ?? []);
      setReviewers(reviewersData.reviewers ?? []);
      setReviewsHubReloadTick(t => t + 1);
    } catch {
      setNotice({
        variant: 'error',
        message: 'Could not load project. It may have been removed.'
      });
    } finally {
      if (showLoading) {
        setLoading(false);
      }
    }
  }, [sessionId]);
  const loadAll = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    await refreshSessionData({
      showLoading: true
    });
  }, [refreshSessionData]);
  const handleEnrolImportSuccess = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(({
    variant,
    message
  }) => {
    if (message) {
      setNotice({
        variant: variant ?? 'success',
        message
      });
    }
  }, []);
  const handleEnrolImportComplete = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    await refreshSessionData({
      showLoading: false
    });
  }, [refreshSessionData]);
  const refreshReviewers = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!sessionId) {
      return [];
    }
    try {
      const data = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.get)(`/sessions/${sessionId}/reviewers`);
      const items = data.reviewers ?? [];
      setReviewers(items);
      return items;
    } catch {
      return [];
    }
  }, [sessionId]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    loadAll();
  }, [loadAll]);
  const enrolledCount = wizardState?.enrolled_count ?? 0;
  const enrolledHasScores = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => enrolled.some(row => row.has_scores), [enrolled]);
  const completedSteps = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    const done = [];
    if (enrolledCount > 0) {
      done.push('students');
    }
    if (enrolledCount > 0 && (wizardState?.unassigned_count ?? 0) === 0) {
      done.push('panels');
    }
    if (reviewers.some(row => row.user_id)) {
      done.push('reviewers');
    }
    if (wizardState?.can_advance_to_reviews) {
      done.push('reviews');
    }
    if (wizardState?.assignments_complete) {
      done.push('assignments');
      done.push('marking');
    }
    return done;
  }, [wizardState, reviewers, enrolledCount]);
  const blockedSteps = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    const blocked = {};
    const rosterBlocker = 'Add at least one student to the project roster first.';
    if (enrolledCount === 0) {
      STEPS.slice(1).forEach(key => {
        blocked[key] = rosterBlocker;
      });
      return blocked;
    }
    if (!wizardState?.can_advance_to_panels) {
      blocked.panels = rosterBlocker;
    }
    if (!wizardState?.can_advance_to_reviewers) {
      const count = wizardState?.unassigned_count ?? 0;
      blocked.reviewers = count > 0 ? `${count} student${count === 1 ? '' : 's'} still unassigned to a project default panel.` : 'Complete the Panels step first.';
    }
    if (!wizardState?.can_advance_to_rubrics) {
      blocked.reviews = 'Add linked reviewers on the Reviewers step first.';
    }
    if (!wizardState?.can_advance_to_assignments) {
      blocked.assignments = 'Add rubric criteria for every review round first.';
    }
    if (!wizardState?.assignments_complete) {
      blocked.marking = 'Complete panel assignments for every review round first.';
    }
    return blocked;
  }, [wizardState, enrolledCount]);
  const goToStep = step => {
    if (blockedSteps[step]) {
      return;
    }
    setSearchParams({
      step
    });
  };
  const goNext = () => {
    const index = STEPS.indexOf(currentStep);
    const next = STEPS[index + 1];
    if (next && !blockedSteps[next]) {
      setSearchParams({
        step: next
      });
    }
  };
  const openSessionForMarking = async () => {
    if (!sessionId || session?.status === 'active') {
      return;
    }
    setOpeningSession(true);
    try {
      const updated = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.put)(`/sessions/${sessionId}`, {
        status: 'active'
      });
      setSession(updated);
      setNotice({
        variant: 'success',
        message: 'Project is now active. Reviewers can see assignments when rubrics are confirmed and marking is on.'
      });
    } catch {
      setNotice({
        variant: 'error',
        message: 'Could not open project for marking.'
      });
    } finally {
      setOpeningSession(false);
    }
  };
  const saveEnrolmentField = async (studentId, fields, localPatch) => {
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.put)(`/sessions/${sessionId}/students/${studentId}`, fields);
      setEnrolled(rows => rows.map(row => row.student?.id === studentId ? {
        ...row,
        ...localPatch
      } : row));
    } catch {
      setNotice({
        variant: 'error',
        message: 'Could not save enrolment details.'
      });
    }
  };
  const saveProjectTitle = async (studentId, projectTitle) => {
    await saveEnrolmentField(studentId, {
      project_title: projectTitle
    }, {
      project_title: projectTitle
    });
  };
  const saveGuideField = async (studentId, field, value) => {
    await saveEnrolmentField(studentId, {
      [field]: value
    }, {
      [field]: value
    });
  };
  const assignPanel = async (studentId, panelId) => {
    const panel = panels.find(p => p.id === panelId);
    await saveEnrolmentField(studentId, {
      panel_id: panelId || null
    }, {
      panel_id: panelId || null,
      panel_name: panel?.name ?? null
    });
  };
  const removeEnrolment = async studentId => {
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.del)(`/sessions/${sessionId}/students/${studentId}`);
      loadAll();
    } catch (err) {
      setNotice({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_4__.parseApiErrorMessage)(err, 'Could not remove student.')
      });
    }
  };
  const closeDeleteAllDialogs = () => {
    setDeleteAllDialogOpen(false);
    setDeleteAllScoresDialogOpen(false);
    setDeleteAllPhrase('');
  };
  const handleDeleteAllStudents = async () => {
    setDeletingAllStudents(true);
    try {
      const payload = enrolledHasScores ? {
        confirm_with_scores: CONFIRM_WITH_SCORES_PHRASE
      } : undefined;
      const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.del)(`/sessions/${sessionId}/students`, payload);
      closeDeleteAllDialogs();
      const removed = result?.removed ?? 0;
      const registryDeleted = result?.registry_deleted ?? 0;
      let message = `Removed ${removed} student${removed === 1 ? '' : 's'} from this project.`;
      if (registryDeleted > 0) {
        message += ` ${registryDeleted} also removed from All Students (not enrolled elsewhere).`;
      }
      setNotice({
        variant: 'success',
        message
      });
      await refreshSessionData({
        showLoading: false
      });
    } catch (err) {
      setNotice({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_4__.parseApiErrorMessage)(err, 'Could not remove all students.')
      });
    } finally {
      setDeletingAllStudents(false);
    }
  };
  const onDeleteAllFirstConfirm = () => {
    if (enrolledHasScores) {
      setDeleteAllDialogOpen(false);
      setDeleteAllScoresDialogOpen(true);
      return;
    }
    handleDeleteAllStudents();
  };
  const phraseMatchesDeleteAllScores = deleteAllPhrase.trim() === CONFIRM_WITH_SCORES_PHRASE;
  const addStudentToProject = async event => {
    event.preventDefault();
    setAddingStudent(true);
    try {
      const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_3__.post)(`/sessions/${sessionId}/students`, {
        reg_no: addStudentForm.reg_no.trim(),
        name: addStudentForm.name.trim(),
        program: addStudentForm.program.trim(),
        batch: addStudentForm.batch.trim()
      });
      if (result?.student) {
        setEnrolled(rows => {
          const exists = rows.some(row => row.student?.id === result.student.student?.id);
          if (exists) {
            return rows.map(row => row.student?.id === result.student.student?.id ? result.student : row);
          }
          return [...rows, result.student];
        });
      }
      setAddStudentForm({
        reg_no: '',
        name: '',
        program: '',
        batch: ''
      });
      setShowAddStudentForm(false);
      setNotice({
        variant: 'success',
        message: 'Student added to this project.'
      });
      await refreshSessionData({
        showLoading: false
      });
    } catch (err) {
      setNotice({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_4__.parseApiErrorMessage)(err, 'Could not add student to this project.')
      });
    } finally {
      setAddingStudent(false);
    }
  };
  const {
    showSkeleton
  } = (0,_shared_hooks_useLoadingPhase__WEBPACK_IMPORTED_MODULE_8__.useLoadingPhase)(loading, session !== null);
  if (!loading && !session) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.PageHeader, {
      title: "Project not found",
      description: "Return to the dashboard and pick another project."
    });
  }
  if (showSkeleton) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.PageHeader, {
        title: "Project setup",
        description: "Enrol students and panels, set up reviewers, define review rounds and rubrics, assign panels per round, then open or pause marking."
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.WizardNav, {
        currentStep: currentStep,
        completedSteps: [],
        blockedSteps: [],
        onStepClick: () => {}
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.ContentLoadingRegion, {
        busy: true,
        variant: "inline",
        label: "Loading project",
        className: "mt-6",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.PageContentSkeleton, {
          rows: 4
        })
      })]
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.PageHeader, {
      title: session.title || 'Project setup',
      description: "Enrol students and panels, set up reviewers, define review rounds and rubrics, assign panels per round, then open or pause marking.",
      actions: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.Button, {
        variant: "secondary",
        onClick: () => navigate('/'),
        children: "Back to dashboard"
      })
    }), notice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("div", {
      className: "mt-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.Notice, {
        variant: notice.variant,
        onDismiss: () => setNotice(null),
        children: notice.message
      })
    }) : null, session.status === 'draft' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
      className: "mb-6 mt-4 flex flex-col gap-3 rounded-md border border-border bg-surface-raised p-4 sm:flex-row sm:items-center sm:justify-between",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("p", {
          className: "text-sm font-medium text-text",
          children: "Draft project"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("p", {
          className: "mt-1 text-sm text-text-muted",
          children: "Reviewers will not see assignments until you open this project for marking. Confirm rubrics on Reviews & rubrics, complete panel assignments, then start each round on Open reviews."
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.Button, {
        variant: "primary",
        disabled: openingSession,
        onClick: openSessionForMarking,
        children: openingSession ? 'Opening…' : 'Open for marking'
      })]
    }) : null, session.status === 'closed' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("div", {
      className: "mt-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.Notice, {
        variant: "warning",
        children: "This project is closed. Reviewers cannot submit new marks."
      })
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.WizardNav, {
      currentStep: currentStep,
      completedSteps: completedSteps,
      blockedSteps: blockedSteps,
      onStepClick: goToStep
    }), currentStep === 'students' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("section", {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("h2", {
        className: "text-lg font-semibold text-text",
        children: "Add students"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("p", {
        className: "mt-1 text-sm text-text-muted",
        children: ["Import or add students to this project. New registration numbers are saved to the", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(react_router_dom__WEBPACK_IMPORTED_MODULE_1__.Link, {
          to: "/registry",
          className: "font-medium text-primary hover:underline",
          children: "student directory"
        }), ' ', "automatically. Custom fields can be managed there."]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
        className: "mt-4 flex flex-wrap gap-2",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.Button, {
          variant: "primary",
          onClick: () => {
            setShowAddStudentForm(value => !value);
            setShowStudentImport(false);
          },
          children: showAddStudentForm ? 'Hide form' : 'Add student'
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.Button, {
          variant: "secondary",
          onClick: () => {
            setShowStudentImport(value => !value);
            setShowAddStudentForm(false);
          },
          children: showStudentImport ? 'Hide import' : 'Import Students'
        })]
      }), showAddStudentForm ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("form", {
        className: "mt-4 max-w-xl space-y-3 rounded-md border border-border bg-surface-raised p-4",
        onSubmit: addStudentToProject,
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("label", {
            htmlFor: "wizard-student-reg_no",
            className: "block text-sm font-medium text-text",
            children: "Registration number"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("input", {
            id: "wizard-student-reg_no",
            "data-testid": "pr-wizard-student-reg-no",
            type: "text",
            required: true,
            value: addStudentForm.reg_no,
            onChange: e => setAddStudentForm(form => ({
              ...form,
              reg_no: e.target.value
            })),
            className: "mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("label", {
            htmlFor: "wizard-student-name",
            className: "block text-sm font-medium text-text",
            children: "Name"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("input", {
            id: "wizard-student-name",
            "data-testid": "pr-wizard-student-name",
            type: "text",
            required: true,
            value: addStudentForm.name,
            onChange: e => setAddStudentForm(form => ({
              ...form,
              name: e.target.value
            })),
            className: "mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
          className: "grid gap-3 sm:grid-cols-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("label", {
              htmlFor: "wizard-student-program",
              className: "block text-sm font-medium text-text",
              children: "Program"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("input", {
              id: "wizard-student-program",
              "data-testid": "pr-wizard-student-program",
              type: "text",
              value: addStudentForm.program,
              onChange: e => setAddStudentForm(form => ({
                ...form,
                program: e.target.value
              })),
              className: "mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("label", {
              htmlFor: "wizard-student-batch",
              className: "block text-sm font-medium text-text",
              children: "Batch"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("input", {
              id: "wizard-student-batch",
              "data-testid": "pr-wizard-student-batch",
              type: "text",
              value: addStudentForm.batch,
              onChange: e => setAddStudentForm(form => ({
                ...form,
                batch: e.target.value
              })),
              className: "mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
            })]
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.Button, {
          type: "submit",
          variant: "primary",
          disabled: addingStudent,
          children: addingStudent ? 'Adding…' : 'Add to project'
        })]
      }) : null, showStudentImport ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_components_CsvImportMapper__WEBPACK_IMPORTED_MODULE_9__.CsvImportMapper, {
        importType: "session-enrol",
        sessionId: sessionId,
        onImportSuccess: handleEnrolImportSuccess,
        onComplete: handleEnrolImportComplete
      }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
        className: "mt-6",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
          className: "flex flex-wrap items-center justify-between gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("h3", {
            className: "text-base font-semibold text-text",
            children: ["Students Added to this Project (", enrolled.length, ")"]
          }), enrolled.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.Button, {
            type: "button",
            variant: "destructive",
            size: "sm",
            "data-testid": "pr-wizard-delete-all-students",
            onClick: () => setDeleteAllDialogOpen(true),
            children: "Delete all students"
          }) : null]
        }), enrolled.length === 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("p", {
          className: "mt-2 text-sm text-warning",
          children: "Add at least one student before continuing to Panels."
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_5__.TableScrollWrapper, {
          className: "mt-2",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("table", {
            className: "min-w-full divide-y divide-border text-sm",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("thead", {
              className: "bg-surface-raised text-left text-text-muted",
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("tr", {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("th", {
                  className: "px-3 py-2 font-medium",
                  children: "Reg no"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("th", {
                  className: "px-3 py-2 font-medium",
                  children: "Name"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("th", {
                  className: "px-3 py-2 font-medium",
                  children: "Program"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("th", {
                  className: "px-3 py-2 font-medium",
                  children: "Batch"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("th", {
                  className: "px-3 py-2 font-medium",
                  children: "Guide emp. ID"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("th", {
                  className: "px-3 py-2 font-medium",
                  children: "Guide name"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("th", {
                  className: "px-3 py-2 font-medium",
                  children: "Panel"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("th", {
                  className: "px-3 py-2 font-medium",
                  children: "Project title"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("th", {
                  className: "px-3 py-2 font-medium text-right",
                  children: "Actions"
                })]
              })
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("tbody", {
              className: "divide-y divide-border bg-surface",
              children: enrolled.map(row => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("tr", {
                className: `group ${_shared_tableStyles__WEBPACK_IMPORTED_MODULE_6__.TABLE_BODY_ROW_SOFT}`,
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("td", {
                  className: "whitespace-nowrap px-3 py-2 text-text",
                  children: row.student?.reg_no
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("td", {
                  className: "px-3 py-2 text-text",
                  children: row.student?.name
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("td", {
                  className: "px-3 py-2 text-text",
                  children: row.student?.program || '—'
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("td", {
                  className: "px-3 py-2 text-text",
                  children: row.student?.batch || '—'
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("td", {
                  className: "px-3 py-2",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("input", {
                    type: "text",
                    className: "w-full min-w-[6rem] rounded-md border border-border bg-surface px-2 py-1 text-sm",
                    value: row.guide_emp_id ?? '',
                    placeholder: "Guide emp. ID",
                    onChange: e => setEnrolled(rows => rows.map(item => item.enrolment_id === row.enrolment_id ? {
                      ...item,
                      guide_emp_id: e.target.value
                    } : item)),
                    onBlur: e => saveGuideField(row.student.id, 'guide_emp_id', e.target.value)
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("td", {
                  className: "px-3 py-2",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("input", {
                    type: "text",
                    className: "w-full min-w-[8rem] rounded-md border border-border bg-surface px-2 py-1 text-sm",
                    value: row.guide_name ?? '',
                    placeholder: "Guide name",
                    onChange: e => setEnrolled(rows => rows.map(item => item.enrolment_id === row.enrolment_id ? {
                      ...item,
                      guide_name: e.target.value
                    } : item)),
                    onBlur: e => saveGuideField(row.student.id, 'guide_name', e.target.value)
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("td", {
                  className: "px-3 py-2",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("select", {
                    value: row.panel_id ?? '',
                    onChange: e => assignPanel(row.student.id, e.target.value ? Number(e.target.value) : null),
                    className: "w-full min-w-[6rem] rounded-md border border-border bg-surface px-2 py-1 text-sm",
                    "aria-label": `Panel for ${row.student?.name}`,
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("option", {
                      value: "",
                      children: "Unassigned"
                    }), panels.map(panel => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("option", {
                      value: panel.id,
                      children: panel.name
                    }, panel.id))]
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("td", {
                  className: "px-3 py-2",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("input", {
                    type: "text",
                    className: "w-full min-w-[12rem] rounded-md border border-border bg-surface px-2 py-1 text-sm",
                    value: row.project_title ?? '',
                    placeholder: "Project title",
                    onChange: e => setEnrolled(rows => rows.map(item => item.enrolment_id === row.enrolment_id ? {
                      ...item,
                      project_title: e.target.value
                    } : item)),
                    onBlur: e => saveProjectTitle(row.student.id, e.target.value)
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("td", {
                  className: "px-3 py-2 text-right",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.Button, {
                    size: "sm",
                    variant: "secondary",
                    disabled: row.has_scores,
                    title: row.has_scores ? 'Cannot remove: this student has scores in one or more review rounds.' : undefined,
                    onClick: () => removeEnrolment(row.student.id),
                    children: "Remove"
                  })
                })]
              }, row.enrolment_id))
            })]
          })
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("div", {
        className: "mt-6 flex justify-end",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.Button, {
          variant: "primary",
          onClick: goNext,
          disabled: enrolledCount === 0,
          children: "Continue to Panels"
        })
      })]
    }) : null, currentStep === 'panels' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_components_PanelsStep__WEBPACK_IMPORTED_MODULE_11__.PanelsStep, {
      sessionId: sessionId,
      panels: panels,
      enrolled: enrolled,
      wizardState: wizardState,
      onReload: loadAll,
      onNotice: setNotice,
      onContinue: goNext,
      blockedTitle: blockedSteps.reviewers
    }) : null, currentStep === 'reviewers' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_components_PanelReviewersStep__WEBPACK_IMPORTED_MODULE_10__.PanelReviewersStep, {
      sessionId: sessionId,
      panels: panels,
      reviewers: reviewers,
      setReviewers: setReviewers,
      onNotice: setNotice,
      onRefreshReviewers: refreshReviewers,
      onReload: loadAll
    }) : null, currentStep === 'reviews' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_components_ReviewsSetupStep__WEBPACK_IMPORTED_MODULE_14__.ReviewsSetupStep, {
      sessionId: sessionId,
      onReload: loadAll,
      onNotice: setNotice,
      canAdvanceToAssignments: !blockedSteps.assignments,
      onContinue: goNext,
      rubricsReloadDependency: reviewsHubReloadTick
    }) : null, currentStep === 'assignments' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_components_ReviewAssignmentsStep__WEBPACK_IMPORTED_MODULE_12__.ReviewAssignmentsStep, {
      sessionId: sessionId,
      panels: panels,
      wizardState: wizardState,
      onReload: loadAll,
      onNotice: setNotice,
      onContinue: goNext
    }) : null, currentStep === 'marking' ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_components_ReviewMarkingStep__WEBPACK_IMPORTED_MODULE_13__.ReviewMarkingStep, {
      sessionId: sessionId,
      sessionStatus: session.status,
      onReload: loadAll,
      onNotice: setNotice,
      isWizardTerminalStep: true
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.ConfirmDialog, {
      open: deleteAllDialogOpen,
      title: "Remove all students from this project?",
      consequences: ['Every student will be unenrolled from this project only.', 'Students not enrolled in any other project will also be removed from All Students.', ...(enrolledHasScores ? ['Some students have entered scores; you will be asked to type Confirm before marking data is permanently deleted.'] : [])],
      confirmLabel: enrolledHasScores ? 'Continue…' : deletingAllStudents ? 'Removing…' : 'Remove all students',
      confirmVariant: "destructive",
      confirmDisabled: deletingAllStudents && !enrolledHasScores,
      onCancel: closeDeleteAllDialogs,
      onConfirm: onDeleteAllFirstConfirm
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_7__.ConfirmDialog, {
      open: deleteAllScoresDialogOpen,
      title: "Remove students and all their scores?",
      consequences: ['Entered marks for students in this project will be permanently deleted.', 'All students will be unenrolled from this project.', 'Students only in this project will be removed from All Students.'],
      confirmLabel: deletingAllStudents ? 'Removing…' : 'Remove all students',
      confirmVariant: "destructive",
      confirmDisabled: deletingAllStudents || !phraseMatchesDeleteAllScores,
      onCancel: closeDeleteAllDialogs,
      onConfirm: handleDeleteAllStudents,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
        className: "space-y-2 text-sm text-text-muted",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("p", {
          children: ["Type ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("strong", {
            className: "text-text",
            children: "Confirm"
          }), " to proceed."]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("input", {
          type: "text",
          className: "w-full rounded-md border border-border bg-surface px-3 py-2 text-text",
          value: deleteAllPhrase,
          onChange: e => setDeleteAllPhrase(e.target.value),
          autoComplete: "off",
          "data-testid": "pr-wizard-delete-all-confirm-input",
          "aria-label": "Type Confirm to remove students with scores"
        })]
      })
    })]
  });
}

/***/ },

/***/ "./src/shared/TableScrollViewport.jsx"
/*!********************************************!*\
  !*** ./src/shared/TableScrollViewport.jsx ***!
  \********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   TableDataViewport: () => (/* binding */ TableDataViewport),
/* harmony export */   TableScrollWrapper: () => (/* binding */ TableScrollWrapper)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _tableStyles__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./tableStyles */ "./src/shared/tableStyles.js");
/* harmony import */ var _useTableRowWindow__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./useTableRowWindow */ "./src/shared/useTableRowWindow.js");
/* harmony import */ var _useMeasuredTableRowHeight__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./useMeasuredTableRowHeight */ "./src/shared/useMeasuredTableRowHeight.js");
/* harmony import */ var _useTableViewportCapacity__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./useTableViewportCapacity */ "./src/shared/useTableViewportCapacity.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






const HEADER_HEIGHT_SINGLE = '3rem';
const HEADER_HEIGHT_DOUBLE = '4.75rem';
function useScrollCue(ref, enabled) {
  const [cueRight, setCueRight] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [cueLeft, setCueLeft] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [scrollable, setScrollable] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const update = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    const el = ref.current;
    if (!el || !enabled) {
      setCueRight(false);
      setCueLeft(false);
      setScrollable(false);
      return;
    }
    const canScrollX = el.scrollWidth > el.clientWidth + 1;
    const canScrollY = el.scrollHeight > el.clientHeight + 1;
    setScrollable(canScrollX || canScrollY);
    setCueLeft(canScrollX && el.scrollLeft > 4);
    setCueRight(canScrollX && el.scrollLeft + el.clientWidth < el.scrollWidth - 4);
  }, [ref, enabled]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const el = ref.current;
    if (!el) {
      return undefined;
    }
    update();
    el.addEventListener('scroll', update, {
      passive: true
    });
    const observer = new ResizeObserver(update);
    observer.observe(el);
    return () => {
      el.removeEventListener('scroll', update);
      observer.disconnect();
    };
  }, [ref, update, enabled]);
  return {
    cueRight,
    cueLeft,
    scrollable
  };
}
function cueClasses(cueRight, cueLeft) {
  return [cueRight ? 'pr-table-scroll--cue-right' : '', cueLeft ? 'pr-table-scroll--cue-left' : ''].filter(Boolean).join(' ');
}
function viewportStyleVars(headerRows, heightRows, rowHeightPx) {
  return {
    '--pr-table-visible-rows': String(heightRows),
    '--pr-table-header-height': headerRows >= 2 ? HEADER_HEIGHT_DOUBLE : HEADER_HEIGHT_SINGLE,
    '--pr-table-row-height': `${rowHeightPx}px`
  };
}
function preserveHorizontalScroll(ref, action) {
  const scrollLeft = ref.current?.scrollLeft ?? 0;
  action();
  requestAnimationFrame(() => {
    if (ref.current) {
      ref.current.scrollLeft = scrollLeft;
    }
  });
}

/**
 * Tall data table: progressive row height (10 default), Add 5 more / Show all.
 * Page scroll stays on `.pr-main`; inner vertical scroll only inside this box.
 */
function TableDataViewport({
  className = '',
  children,
  bodyRowCount = 0,
  headerRows = 1,
  initialRows: initialRowsProp,
  rowIncrement,
  rowHeightVariant = 'auto',
  showControls: showControlsProp,
  ...rest
}) {
  const hostRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const viewportRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const totalRows = Math.max(0, Number(bodyRowCount) || 0);
  const measuredRowHeightPx = (0,_useMeasuredTableRowHeight__WEBPACK_IMPORTED_MODULE_3__.useMeasuredTableRowHeight)(viewportRef, totalRows, rowHeightVariant === 'dense' ? 'dense' : rowHeightVariant === 'comfortable' ? 'comfortable' : 'auto');
  const capacityInitialRows = (0,_useTableViewportCapacity__WEBPACK_IMPORTED_MODULE_4__.useTableViewportCapacity)(hostRef, {
    headerRows,
    totalRows,
    rowHeightPx: measuredRowHeightPx
  });
  const resolvedInitialRows = initialRowsProp ?? capacityInitialRows ?? _useTableRowWindow__WEBPACK_IMPORTED_MODULE_2__.TABLE_VIEWPORT_INITIAL_ROWS;
  const rowWindow = (0,_useTableRowWindow__WEBPACK_IMPORTED_MODULE_2__.useTableRowWindow)(bodyRowCount, {
    initialRows: resolvedInitialRows,
    rowIncrement
  });
  const {
    totalRows: windowTotalRows,
    heightRows,
    showAll,
    showControls: autoShowControls,
    canAddMore,
    canRemoveRows,
    canShowAll,
    canShowFewer,
    cappedVisibleRows,
    initialRows: resolvedInitialRowsForControls,
    rowIncrement: resolvedIncrement,
    addFive,
    removeFive,
    showAllRows,
    resetRows
  } = rowWindow;
  const showControls = showControlsProp !== undefined ? showControlsProp : autoShowControls;
  const {
    cueRight,
    cueLeft,
    scrollable
  } = useScrollCue(viewportRef, true);
  const viewportClasses = [_tableStyles__WEBPACK_IMPORTED_MODULE_1__.TABLE_DATA_VIEWPORT, showAll ? 'pr-table-data-viewport--show-all' : '', cueClasses(cueRight, cueLeft), className].filter(Boolean).join(' ');
  const handleAddFive = () => {
    preserveHorizontalScroll(viewportRef, addFive);
  };
  const handleShowAll = () => {
    preserveHorizontalScroll(viewportRef, showAllRows);
  };
  const handleRemoveFive = () => {
    preserveHorizontalScroll(viewportRef, removeFive);
  };
  const handleReset = () => {
    preserveHorizontalScroll(viewportRef, resetRows);
  };
  const helperText = showControls && windowTotalRows > resolvedInitialRowsForControls ? showAll ? `Showing all ${windowTotalRows} rows` : `Showing ${cappedVisibleRows} of ${windowTotalRows} rows` : null;
  const viewport = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
    ref: viewportRef,
    className: viewportClasses,
    style: viewportStyleVars(headerRows, heightRows, measuredRowHeightPx),
    tabIndex: scrollable ? 0 : undefined,
    ...rest,
    children: children
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
    ref: hostRef,
    className: "pr-table-viewport-host",
    children: [showControls ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
      className: "pr-table-viewport-toolbar",
      children: [helperText ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
        className: "pr-table-viewport-helper",
        "aria-live": "polite",
        children: helperText
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("span", {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
        className: "pr-table-viewport-actions",
        children: [canAddMore ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("button", {
          type: "button",
          className: "pr-table-viewport-toggle",
          onClick: handleAddFive,
          "aria-label": `Add ${resolvedIncrement} more rows to the table view`,
          children: ["Add ", resolvedIncrement, " more"]
        }) : null, canRemoveRows ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("button", {
          type: "button",
          className: "pr-table-viewport-toggle",
          onClick: handleRemoveFive,
          "aria-label": `Remove ${resolvedIncrement} rows from the table view`,
          children: ["Remove ", resolvedIncrement]
        }) : null, canShowAll ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("button", {
          type: "button",
          className: "pr-table-viewport-toggle",
          onClick: handleShowAll,
          "aria-label": "Show all table rows",
          children: "Show all"
        }) : null, canShowFewer ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("button", {
          type: "button",
          className: "pr-table-viewport-toggle",
          onClick: handleReset,
          "aria-label": `Reset table view to ${resolvedInitialRowsForControls} rows`,
          children: "Show fewer"
        }) : null]
      })]
    }) : null, viewport]
  });
}
function TableScrollWrapper({
  className = '',
  children,
  ...rest
}) {
  const ref = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(null);
  const {
    cueRight,
    cueLeft,
    scrollable
  } = useScrollCue(ref, true);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
    ref: ref,
    className: [_tableStyles__WEBPACK_IMPORTED_MODULE_1__.TABLE_SCROLL_WRAPPER, cueClasses(cueRight, cueLeft), className].filter(Boolean).join(' '),
    tabIndex: scrollable ? 0 : undefined,
    ...rest,
    children: children
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

/***/ },

/***/ "./src/shared/getViewportRowCapacity.js"
/*!**********************************************!*\
  !*** ./src/shared/getViewportRowCapacity.js ***!
  \**********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   TABLE_HEADER_HEIGHT_PX: () => (/* binding */ TABLE_HEADER_HEIGHT_PX),
/* harmony export */   getViewportRowCapacity: () => (/* binding */ getViewportRowCapacity)
/* harmony export */ });
/* harmony import */ var _tableStyles__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./tableStyles */ "./src/shared/tableStyles.js");


/** Header budget at 16px root (matches TableScrollViewport rem constants). */
const TABLE_HEADER_HEIGHT_PX = {
  single: 48,
  double: 76
};

/**
 * How many body rows fit in the progressive viewport (pure, testable).
 *
 * @param {{
 *   availablePx: number,
 *   headerRows?: number,
 *   rowHeightPx: number,
 *   minRows?: number,
 *   maxRows?: number,
 *   minFitRows?: number,
 *   totalRows: number,
 *   toolbarPx?: number,
 *   safePaddingPx?: number,
 * }} params
 */
function getViewportRowCapacity({
  availablePx,
  headerRows = 1,
  rowHeightPx,
  minRows = _tableStyles__WEBPACK_IMPORTED_MODULE_0__.TABLE_VIEWPORT_INITIAL_ROWS,
  maxRows = _tableStyles__WEBPACK_IMPORTED_MODULE_0__.TABLE_VIEWPORT_MAX_ROWS,
  minFitRows = _tableStyles__WEBPACK_IMPORTED_MODULE_0__.TABLE_VIEWPORT_MIN_FIT_ROWS,
  totalRows,
  toolbarPx = 0,
  safePaddingPx = 16
}) {
  const total = Math.max(0, Number(totalRows) || 0);
  if (total === 0) {
    return 0;
  }
  if (total <= minRows) {
    return total;
  }
  const headerPx = headerRows >= 2 ? TABLE_HEADER_HEIGHT_PX.double : TABLE_HEADER_HEIGHT_PX.single;
  const bodyBudget = availablePx - headerPx - toolbarPx - safePaddingPx;
  const rowH = Math.max(1, Number(rowHeightPx) || minRows);
  const fitCount = Math.floor(bodyBudget / rowH);
  const capped = Math.min(total, maxRows, Math.max(0, fitCount));
  if (fitCount >= minRows) {
    return Math.max(minRows, capped);
  }
  if (total >= minFitRows) {
    return Math.max(minFitRows, Math.min(capped, total));
  }
  return total;
}

/***/ },

/***/ "./src/shared/hooks/useLoadingPhase.js"
/*!*********************************************!*\
  !*** ./src/shared/hooks/useLoadingPhase.js ***!
  \*********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useLoadingPhase: () => (/* binding */ useLoadingPhase)
/* harmony export */ });
/**
 * Distinguish initial load (skeleton) from refresh (overlay on stale data).
 *
 * @param {boolean} loading  Fetch in progress.
 * @param {boolean} hasData  Prior data is available to show while refreshing.
 */
function useLoadingPhase(loading, hasData) {
  return {
    showSkeleton: loading && !hasData,
    showOverlay: loading && hasData
  };
}

/***/ },

/***/ "./src/shared/markErrors.js"
/*!**********************************!*\
  !*** ./src/shared/markErrors.js ***!
  \**********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   MARK_ERROR_MESSAGES: () => (/* binding */ MARK_ERROR_MESSAGES),
/* harmony export */   fixByLabel: () => (/* binding */ fixByLabel),
/* harmony export */   formatAttendanceConflictLabel: () => (/* binding */ formatAttendanceConflictLabel),
/* harmony export */   mapMarkApiError: () => (/* binding */ mapMarkApiError),
/* harmony export */   unanimousPeerAttendanceGuidance: () => (/* binding */ unanimousPeerAttendanceGuidance)
/* harmony export */ });
/**
 * UX-DR20: map API error codes to user-facing copy and who can fix.
 */
const MARK_ERROR_MESSAGES = {
  rubric_not_confirmed: {
    message: 'Marking is not open yet because the rubric for this review has not been confirmed.',
    fixBy: 'coordinator'
  },
  marking_inactive: {
    message: 'This review round is not open for marking.',
    fixBy: 'coordinator'
  },
  session_closed: {
    message: 'This project is closed. Marks can no longer be saved.',
    fixBy: 'admin'
  },
  session_not_active: {
    message: 'This project is not active yet. Marking is not open.',
    fixBy: 'coordinator'
  },
  not_assigned: {
    message: 'You are not assigned to mark this student for this review.',
    fixBy: 'coordinator'
  },
  invalid_score: {
    message: 'One or more scores are outside the allowed range.',
    fixBy: null
  },
  marks_frozen: {
    message: 'Scores are frozen for this review. You cannot change marks until a coordinator intervenes.',
    fixBy: 'coordinator'
  },
  coordinator_marks_locked: {
    message: 'The coordinator locked marking for this review. No further mark changes are allowed.',
    fixBy: 'coordinator'
  },
  panels_not_all_frozen: {
    message: 'Every participating panel must freeze panel scores before you can freeze this review.',
    fixBy: 'coordinator'
  },
  no_panels_for_review_lock: {
    message: 'Assign students to panels on this review before freezing.',
    fixBy: 'coordinator'
  },
  not_panel_coordinator: {
    message: 'Only the panel coordinator can access this panel report.',
    fixBy: 'coordinator'
  },
  panel_head_requires_account: {
    message: 'Provision or link an account before designating a panel coordinator.',
    fixBy: 'coordinator'
  },
  panel_scores_frozen: {
    message: 'The panel coordinator finalized scores for this panel. Marks cannot be changed.',
    fixBy: 'coordinator'
  },
  panel_freeze_incomplete: {
    message: 'This panel has no students or reviewers assigned, so it cannot be frozen yet.',
    fixBy: null
  },
  panel_freeze_incomplete_marks: {
    message: 'Some reviewers still have students without a score on every criterion.',
    fixBy: null
  },
  panel_freeze_reviewers_not_frozen: {
    message: 'Every reviewer must freeze their personal scores for this review before you can freeze the panel.',
    fixBy: null
  },
  panel_head_already_set: {
    message: 'This panel already has a panel coordinator.',
    fixBy: 'coordinator'
  },
  incomplete_marks: {
    message: 'Some students still need a score on every criterion before you can freeze.',
    fixBy: null
  },
  not_frozen: {
    message: 'Scores are not frozen, so an unfreeze request is not needed.',
    fixBy: null
  },
  unfreeze_request_pending: {
    message: 'An unfreeze request is already pending panel coordinator approval.',
    fixBy: 'coordinator'
  },
  panel_not_frozen: {
    message: 'This panel is not frozen, so a panel unfreeze request is not needed.',
    fixBy: null
  },
  panel_unfreeze_pending: {
    message: 'A panel unfreeze request is already pending project coordinator approval.',
    fixBy: 'coordinator'
  },
  use_panel_head_grant: {
    message: 'Reviewer score unfreeze must be approved by the panel coordinator in the reviewer app.',
    fixBy: 'coordinator'
  },
  unfreeze_reason_required: {
    message: 'Please explain why you need to edit frozen scores.',
    fixBy: null
  },
  unfreeze_reason_too_long: {
    message: 'Reason must be 500 characters or fewer.',
    fixBy: null
  },
  attendance_required: {
    message: 'Select whether the student was present or absent before saving.',
    fixBy: null
  },
  invalid_attendance: {
    message: 'Attendance must be present or absent.',
    fixBy: null
  },
  attendance_conflict: {
    message: 'Attendance must match for all reviewers on this review. Resolve the disagreement before saving.',
    fixBy: null
  }
};
function formatAttendanceConflictLabel(attendanceStatus) {
  return attendanceStatus === 'absent' ? 'Absent' : 'Present';
}

/**
 * When every other panel reviewer shares one status and the current user disagrees.
 */
function unanimousPeerAttendanceGuidance(conflicts, attemptedStatus, currentUserId) {
  if (!Array.isArray(conflicts) || conflicts.length === 0 || !attemptedStatus) {
    return null;
  }
  const others = conflicts.filter(row => Number(row.reviewer_user_id) !== Number(currentUserId));
  if (others.length === 0) {
    return null;
  }
  const otherStatuses = new Set(others.map(row => row.attendance_status));
  if (otherStatuses.size !== 1) {
    return null;
  }
  const peerStatus = [...otherStatuses][0];
  if (peerStatus === attemptedStatus) {
    return null;
  }
  return `All other reviewers recorded ${formatAttendanceConflictLabel(peerStatus)}. Ask the project coordinator to correct attendance if this is wrong.`;
}
function mapMarkApiError(error) {
  const code = error?.code || error?.data?.code || '';
  const mapped = MARK_ERROR_MESSAGES[code];
  if (mapped) {
    const apiMessage = typeof error?.message === 'string' ? error.message : '';
    const useApiMessage = (code === 'invalid_score' || code === 'incomplete_marks' || code === 'attendance_conflict' || code === 'panel_freeze_incomplete' || code === 'panel_freeze_incomplete_marks' || code === 'panel_freeze_reviewers_not_frozen' || code === 'panels_not_all_frozen') && apiMessage !== '';
    const conflicts = Array.isArray(error?.data?.conflicts) ? error.data.conflicts : [];
    return {
      code,
      message: useApiMessage ? apiMessage : mapped.message,
      fixBy: mapped.fixBy,
      ...(code === 'attendance_conflict' && conflicts.length > 0 ? {
        conflicts
      } : {})
    };
  }
  return {
    code: code || 'unknown',
    message: error?.message || 'Something went wrong while saving marks. Please try again.',
    fixBy: null
  };
}
function fixByLabel(fixBy) {
  if (fixBy === 'coordinator') {
    return 'Ask your coordinator to resolve this.';
  }
  if (fixBy === 'admin') {
    return 'Contact a site administrator if you believe this is incorrect.';
  }
  return null;
}

/***/ },

/***/ "./src/shared/reviewerImportRows.js"
/*!******************************************!*\
  !*** ./src/shared/reviewerImportRows.js ***!
  \******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   expandReviewerImportRows: () => (/* binding */ expandReviewerImportRows),
/* harmony export */   findReviewerEmailPanelConflicts: () => (/* binding */ findReviewerEmailPanelConflicts)
/* harmony export */ });
function normalizeKey(key) {
  return String(key).toLowerCase().replace(/\s+/g, '_');
}
function rowValueForSuffix(row, suffix) {
  const target = normalizeKey(suffix);
  for (const [key, value] of Object.entries(row)) {
    if (normalizeKey(key) === target) {
      return String(value ?? '').trim();
    }
  }
  return '';
}
function rowHasWideReviewerSlots(row) {
  for (const [key, value] of Object.entries(row)) {
    const normalized = normalizeKey(key);
    const match = normalized.match(/^reviewer_(\d+)$/);
    if (!match) {
      continue;
    }
    const slot = match[1];
    const name = String(value ?? '').trim();
    const email = rowValueForSuffix(row, `reviewer_${slot}_email`);
    if (name !== '' || email !== '') {
      return true;
    }
  }
  return false;
}
function expandWideRow(row, panelRef, csvRow) {
  const slots = {};
  for (const [key, value] of Object.entries(row)) {
    const normalized = normalizeKey(key);
    const match = normalized.match(/^reviewer_(\d+)$/);
    if (!match) {
      continue;
    }
    const slot = Number(match[1]);
    const name = String(value ?? '').trim();
    const email = rowValueForSuffix(row, `reviewer_${slot}_email`);
    if (name === '' && email === '') {
      continue;
    }
    const weightRaw = rowValueForSuffix(row, `reviewer_${slot}_weight`);
    slots[slot] = {
      panel: panelRef,
      reviewer_name: name,
      email: email.toLowerCase(),
      weight: weightRaw !== '' ? weightRaw : 1,
      _csv_row: csvRow
    };
  }
  return Object.keys(slots).map(Number).sort((a, b) => a - b).map(slot => slots[slot]);
}

/** @param {Record<string, unknown>} row @param {number} sourceIndex */
function csvRowForSource(row, sourceIndex) {
  const fromRow = Number(row._csv_row);
  return fromRow > 0 ? fromRow : sourceIndex + 2;
}

/**
 * Mirror PanelRepository::expand_import_rows for client-side validation.
 *
 * @param {Array<Record<string, string>>} rows
 * @returns {Array<Record<string, string|number>>}
 */
function expandReviewerImportRows(rows) {
  const expanded = [];
  for (let sourceIndex = 0; sourceIndex < rows.length; sourceIndex++) {
    const row = rows[sourceIndex];
    const csvRow = csvRowForSource(row, sourceIndex);
    const panelRef = String(row.panel ?? row.panel_number ?? '').trim();
    if (rowHasWideReviewerSlots(row)) {
      expanded.push(...expandWideRow(row, panelRef, csvRow));
      continue;
    }
    const longName = String(row.reviewer_name ?? row.name ?? '').trim();
    const longEmail = String(row.email ?? '').trim().toLowerCase();
    if (longName !== '' || longEmail !== '') {
      expanded.push({
        panel: panelRef,
        reviewer_name: longName,
        email: longEmail,
        weight: row.weight ?? 1,
        _csv_row: csvRow
      });
      continue;
    }
    if (panelRef !== '') {
      expanded.push({
        ...row,
        _csv_row: csvRow
      });
    }
  }
  return expanded;
}

/**
 * Find reviewers (by email) listed on more than one panel in the same file.
 *
 * @param {Array<Record<string, string>>} rows Mapped import rows (long or wide format).
 * @returns {Array<{ email: string, name: string, panels: string[], rows: number[] }>}
 */
function findReviewerEmailPanelConflicts(rows) {
  const expanded = expandReviewerImportRows(rows);
  const assignments = new Map();
  const conflicts = new Map();
  expanded.forEach(row => {
    const email = String(row.email ?? '').trim().toLowerCase();
    if (email === '') {
      return;
    }
    const panel = String(row.panel ?? row.panel_number ?? '').trim();
    if (panel === '') {
      return;
    }
    const name = String(row.reviewer_name ?? row.name ?? '').trim();
    const line = Number(row._csv_row);
    if (!Number.isFinite(line) || line < 1) {
      return;
    }
    if (!assignments.has(email)) {
      assignments.set(email, {
        panel,
        name,
        line
      });
      return;
    }
    const existing = assignments.get(email);
    if (existing.panel === panel) {
      return;
    }
    if (!conflicts.has(email)) {
      conflicts.set(email, {
        email,
        name: existing.name || name,
        panels: [existing.panel, panel],
        rows: [existing.line, line]
      });
      return;
    }
    const conflict = conflicts.get(email);
    if (!conflict.panels.includes(panel)) {
      conflict.panels.push(panel);
    }
    if (!conflict.rows.includes(line)) {
      conflict.rows.push(line);
    }
    if (!conflict.name && name) {
      conflict.name = name;
    }
  });
  return [...conflicts.values()].map(conflict => ({
    ...conflict,
    rows: [...conflict.rows].sort((a, b) => a - b)
  }));
}

/***/ },

/***/ "./src/shared/reviewerTemplateCsv.js"
/*!*******************************************!*\
  !*** ./src/shared/reviewerTemplateCsv.js ***!
  \*******************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   buildReviewersTemplateCsv: () => (/* binding */ buildReviewersTemplateCsv),
/* harmony export */   downloadCsvText: () => (/* binding */ downloadCsvText)
/* harmony export */ });
function escapeCsvCell(value) {
  const text = String(value ?? '');
  if (text.includes(',') || text.includes('"') || text.includes('\n')) {
    return `"${text.replace(/"/g, '""')}"`;
  }
  return text;
}
function reviewersForPanel(reviewers, panelId) {
  return reviewers.filter(row => Number(row.panel_id) === Number(panelId)).sort((a, b) => String(a.name || a.email).localeCompare(String(b.name || b.email), undefined, {
    numeric: true
  }));
}

/**
 * Build a wide-format CSV: one row per panel, reviewer_N columns prefilled when present.
 *
 * @param {Array<{ id: number, name: string }>} panels
 * @param {Array<{ panel_id: number, name?: string, email?: string, weight?: number }>} reviewers
 * @param {{ minSlots?: number }} options
 */
function buildReviewersTemplateCsv(panels, reviewers, options = {}) {
  const sortedPanels = [...panels].sort((a, b) => String(a.name).localeCompare(String(b.name), undefined, {
    numeric: true
  }));
  const maxOnPanel = sortedPanels.reduce((max, panel) => {
    const count = reviewersForPanel(reviewers, panel.id).length;
    return Math.max(max, count);
  }, 0);
  const slotCount = Math.max(options.minSlots ?? 3, maxOnPanel, 1);
  const headers = ['panel'];
  for (let slot = 1; slot <= slotCount; slot += 1) {
    headers.push(`reviewer_${slot}`, `reviewer_${slot}_email`, `reviewer_${slot}_weight`);
  }
  const lines = [headers.map(escapeCsvCell).join(',')];
  sortedPanels.forEach(panel => {
    const panelReviewers = reviewersForPanel(reviewers, panel.id);
    const cells = [escapeCsvCell(panel.name)];
    for (let slot = 1; slot <= slotCount; slot += 1) {
      const reviewer = panelReviewers[slot - 1];
      cells.push(escapeCsvCell(reviewer?.name ?? ''), escapeCsvCell(reviewer?.email ?? ''), escapeCsvCell(reviewer?.weight != null && reviewer.weight !== '' ? reviewer.weight : ''));
    }
    lines.push(cells.join(','));
  });
  return `${lines.join('\n')}\n`;
}
function downloadCsvText(csvText, filename) {
  const blob = new Blob([csvText], {
    type: 'text/csv;charset=utf-8'
  });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}

/***/ },

/***/ "./src/shared/rubricCriteria.js"
/*!**************************************!*\
  !*** ./src/shared/rubricCriteria.js ***!
  \**************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   buildCriteriaPayload: () => (/* binding */ buildCriteriaPayload),
/* harmony export */   formatMarksSum: () => (/* binding */ formatMarksSum),
/* harmony export */   parseMaxMarks: () => (/* binding */ parseMaxMarks),
/* harmony export */   sumCriteriaMaxMarks: () => (/* binding */ sumCriteriaMaxMarks),
/* harmony export */   validateCriteriaRows: () => (/* binding */ validateCriteriaRows)
/* harmony export */ });
/**
 * Shared rubric criterion validation and payload helpers (coordinator RubricTable).
 */

function parseMaxMarks(value) {
  const parsed = parseFloat(value);
  return Number.isFinite(parsed) ? parsed : 0;
}

/**
 * @param {Array<{ label?: string, max_marks?: string|number }>} rows
 * @returns {string|null} Error message or null when valid.
 */
function validateCriteriaRows(rows) {
  for (const row of rows) {
    const label = (row.label ?? '').trim();
    if (label === '') {
      continue;
    }
    if (parseMaxMarks(row.max_marks) <= 0) {
      return 'Each criterion needs max marks greater than zero.';
    }
  }
  const validCount = rows.filter(row => (row.label ?? '').trim() !== '' && parseMaxMarks(row.max_marks) > 0).length;
  if (validCount === 0) {
    return 'Add at least one criterion with a label and max marks greater than zero before saving.';
  }
  return null;
}

/**
 * @param {Array<{ id?: number, label?: string, max_marks?: string|number }>} rows
 * @returns {Array<{ id?: number, label: string, max_marks: number, sort_order: number }>}
 */
function buildCriteriaPayload(rows) {
  return rows.map((row, index) => {
    const label = (row.label ?? '').trim();
    const max_marks = parseMaxMarks(row.max_marks);
    const payload = {
      label,
      max_marks,
      sort_order: index
    };
    if (row.id != null && !Number.isNaN(row.id)) {
      payload.id = row.id;
    }
    return payload;
  }).filter(row => row.label !== '' && row.max_marks > 0);
}

/**
 * @param {Array<{ label?: string, max_marks?: string|number }>} rows
 * @returns {number|null} Sum of max_marks for valid rows, or null when none.
 */
function sumCriteriaMaxMarks(rows) {
  let sum = 0;
  let hasValid = false;
  for (const row of rows) {
    const label = (row.label ?? '').trim();
    const max_marks = parseMaxMarks(row.max_marks);
    if (label !== '' && max_marks > 0) {
      sum += max_marks;
      hasValid = true;
    }
  }
  return hasValid ? sum : null;
}

/**
 * @param {number} sum
 * @returns {string}
 */
function formatMarksSum(sum) {
  if (sum % 1 === 0) {
    return String(Math.round(sum));
  }
  return String(sum);
}

/***/ },

/***/ "./src/shared/rubricEditable.js"
/*!**************************************!*\
  !*** ./src/shared/rubricEditable.js ***!
  \**************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isCriteriaEditable: () => (/* binding */ isCriteriaEditable)
/* harmony export */ });
/**
 * Mirrors Rest_Reviews::review_is_editable() for UI gating.
 */
function isCriteriaEditable(review) {
  if (!review) {
    return false;
  }
  const status = review.status ?? 'draft';
  const hasMarks = Boolean(review.has_marks);
  if (status === 'confirmed' && hasMarks) {
    return false;
  }
  if (typeof review.criteria_editable === 'boolean') {
    return review.criteria_editable;
  }
  if (status === 'draft' || status === 'unlocked') {
    return true;
  }
  if (status === 'confirmed') {
    return !hasMarks;
  }
  return false;
}

/***/ },

/***/ "./src/shared/useMeasuredTableRowHeight.js"
/*!*************************************************!*\
  !*** ./src/shared/useMeasuredTableRowHeight.js ***!
  \*************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useMeasuredTableRowHeight: () => (/* binding */ useMeasuredTableRowHeight)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _tableStyles__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./tableStyles */ "./src/shared/tableStyles.js");


const ROOT_FONT_PX = 16;
function remToPx(rem) {
  return rem * ROOT_FONT_PX;
}

/**
 * Measure first body row height inside the table viewport (semantic tr or grid cells).
 *
 * @param {import('react').RefObject<HTMLElement|null>} viewportRef
 * @param {number} bodyRowCount
 * @param {'comfortable'|'dense'|'auto'} rowHeightVariant
 */
function useMeasuredTableRowHeight(viewportRef, bodyRowCount, rowHeightVariant = 'auto') {
  const fallbackPx = rowHeightVariant === 'dense' ? remToPx(_tableStyles__WEBPACK_IMPORTED_MODULE_1__.TABLE_ROW_HEIGHT_DENSE_REM) : remToPx(_tableStyles__WEBPACK_IMPORTED_MODULE_1__.TABLE_ROW_HEIGHT_COMFORTABLE_REM);
  const [rowHeightPx, setRowHeightPx] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(fallbackPx);
  const measure = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    const viewport = viewportRef.current;
    if (!viewport || bodyRowCount <= 0) {
      setRowHeightPx(fallbackPx);
      return;
    }
    const tbodyRow = viewport.querySelector('tbody tr');
    if (tbodyRow) {
      const height = tbodyRow.getBoundingClientRect().height;
      if (height > 0) {
        setRowHeightPx(height);
        return;
      }
    }

    // Grid tables: skip header row (`contents` only); body rows use `group contents`.
    const bodyRow = viewport.querySelector('[role="row"].group');
    if (bodyRow) {
      const cells = bodyRow.querySelectorAll('[role="cell"]');
      let max = 0;
      cells.forEach(cell => {
        max = Math.max(max, cell.getBoundingClientRect().height);
      });
      if (max > 0) {
        setRowHeightPx(max);
        return;
      }
    }
    setRowHeightPx(fallbackPx);
  }, [viewportRef, bodyRowCount, fallbackPx]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const runMeasure = () => {
      requestAnimationFrame(measure);
    };
    runMeasure();
    const viewport = viewportRef.current;
    if (!viewport) {
      return undefined;
    }
    const observer = new ResizeObserver(runMeasure);
    observer.observe(viewport);
    const tbodyRow = viewport.querySelector('tbody tr');
    if (tbodyRow) {
      observer.observe(tbodyRow);
    }

    // Grid tables: skip header row (`contents` only); body rows use `group contents`.
    const bodyRow = viewport.querySelector('[role="row"].group');
    if (bodyRow) {
      bodyRow.querySelectorAll('[role="cell"]').forEach(cell => {
        observer.observe(cell);
      });
    }
    return () => observer.disconnect();
  }, [viewportRef, measure, bodyRowCount]);
  return rowHeightPx;
}

/***/ },

/***/ "./src/shared/useTableRowWindow.js"
/*!*****************************************!*\
  !*** ./src/shared/useTableRowWindow.js ***!
  \*****************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   TABLE_VIEWPORT_INITIAL_ROWS: () => (/* reexport safe */ _tableStyles__WEBPACK_IMPORTED_MODULE_1__.TABLE_VIEWPORT_INITIAL_ROWS),
/* harmony export */   TABLE_VIEWPORT_ROW_INCREMENT: () => (/* reexport safe */ _tableStyles__WEBPACK_IMPORTED_MODULE_1__.TABLE_VIEWPORT_ROW_INCREMENT),
/* harmony export */   getTableRowWindowMetrics: () => (/* binding */ getTableRowWindowMetrics),
/* harmony export */   useTableRowWindow: () => (/* binding */ useTableRowWindow)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _tableStyles__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./tableStyles */ "./src/shared/tableStyles.js");




/**
 * Pure row-window metrics (testable without React).
 *
 * @param {{
 *   totalRows: number,
 *   visibleRows: number,
 *   showAll: boolean,
 *   initialRows?: number,
 *   rowIncrement?: number
 * }} state
 */
function getTableRowWindowMetrics({
  totalRows,
  visibleRows,
  showAll,
  initialRows = _tableStyles__WEBPACK_IMPORTED_MODULE_1__.TABLE_VIEWPORT_INITIAL_ROWS,
  rowIncrement = _tableStyles__WEBPACK_IMPORTED_MODULE_1__.TABLE_VIEWPORT_ROW_INCREMENT
}) {
  const cappedVisibleRows = showAll || totalRows <= initialRows ? totalRows : Math.min(visibleRows, totalRows);
  return {
    totalRows,
    heightRows: cappedVisibleRows,
    cappedVisibleRows,
    showAll,
    initialRows,
    rowIncrement,
    showControls: totalRows > initialRows,
    canAddMore: !showAll && totalRows > initialRows && visibleRows < totalRows,
    canRemoveRows: !showAll && visibleRows > initialRows,
    canShowAll: !showAll && totalRows > initialRows,
    canShowFewer: showAll
  };
}

/**
 * Progressive row window for TableDataViewport (height budget, not DOM slicing).
 *
 * @param {number} bodyRowCount Total <tbody> (or grid body) rows in the table.
 * @param {{ initialRows?: number, rowIncrement?: number }} [options]
 */
function useTableRowWindow(bodyRowCount, options = {}) {
  const initialRows = options.initialRows ?? _tableStyles__WEBPACK_IMPORTED_MODULE_1__.TABLE_VIEWPORT_INITIAL_ROWS;
  const rowIncrement = options.rowIncrement ?? _tableStyles__WEBPACK_IMPORTED_MODULE_1__.TABLE_VIEWPORT_ROW_INCREMENT;
  const totalRows = Math.max(0, Number(bodyRowCount) || 0);
  const [visibleRows, setVisibleRows] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(initialRows);
  const [showAll, setShowAll] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    setVisibleRows(initialRows);
    setShowAll(false);
  }, [totalRows, initialRows]);
  const metrics = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => getTableRowWindowMetrics({
    totalRows,
    visibleRows,
    showAll,
    initialRows,
    rowIncrement
  }), [totalRows, visibleRows, showAll, initialRows, rowIncrement]);
  const {
    heightRows,
    cappedVisibleRows,
    showControls,
    canAddMore,
    canRemoveRows,
    canShowAll,
    canShowFewer
  } = metrics;
  const addFive = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setShowAll(false);
    setVisibleRows(current => Math.min(current + rowIncrement, totalRows));
  }, [totalRows, rowIncrement]);
  const removeFive = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setShowAll(false);
    setVisibleRows(current => Math.max(current - rowIncrement, initialRows));
  }, [initialRows, rowIncrement]);
  const showAllRows = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setShowAll(true);
  }, []);
  const resetRows = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    setShowAll(false);
    setVisibleRows(initialRows);
  }, [initialRows]);
  return {
    ...metrics,
    addFive,
    removeFive,
    showAllRows,
    resetRows
  };
}

/***/ },

/***/ "./src/shared/useTableViewportCapacity.js"
/*!************************************************!*\
  !*** ./src/shared/useTableViewportCapacity.js ***!
  \************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getAvailableHeightBelowAnchor: () => (/* binding */ getAvailableHeightBelowAnchor),
/* harmony export */   useTableViewportCapacity: () => (/* binding */ useTableViewportCapacity)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _getViewportRowCapacity__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./getViewportRowCapacity */ "./src/shared/getViewportRowCapacity.js");
/* harmony import */ var _tableStyles__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./tableStyles */ "./src/shared/tableStyles.js");



const SAFE_PADDING_PX = 16;
function getMainElement() {
  return document.querySelector('.pr-main');
}
function getDataViewportElement(host) {
  if (!host) {
    return null;
  }
  if (host.classList.contains('pr-table-data-viewport')) {
    return host;
  }
  return host.querySelector('.pr-table-data-viewport');
}

/**
 * Remaining vertical space inside `.pr-main` below the table box (scroll-aware).
 *
 * @param {HTMLElement} main
 * @param {HTMLElement} anchor
 */
function getAvailableHeightBelowAnchor(main, anchor) {
  const anchorTopInMain = anchor.getBoundingClientRect().top - main.getBoundingClientRect().top + main.scrollTop;
  const mainStyle = window.getComputedStyle(main);
  const paddingBottom = parseFloat(mainStyle.paddingBottom) || 0;
  return main.clientHeight - anchorTopInMain - paddingBottom - SAFE_PADDING_PX;
}

/**
 * Viewport-aware initial row budget from `.pr-main` space below the table box.
 *
 * @param {import('react').RefObject<HTMLElement|null>} hostRef
 * @param {{
 *   headerRows?: number,
 *   totalRows?: number,
 *   rowHeightPx?: number|null,
 *   minRows?: number,
 *   maxRows?: number,
 *   enabled?: boolean,
 * }} options
 */
function useTableViewportCapacity(hostRef, options = {}) {
  const {
    headerRows = 1,
    totalRows = 0,
    rowHeightPx = null,
    minRows = _tableStyles__WEBPACK_IMPORTED_MODULE_2__.TABLE_VIEWPORT_INITIAL_ROWS,
    maxRows = _tableStyles__WEBPACK_IMPORTED_MODULE_2__.TABLE_VIEWPORT_MAX_ROWS,
    minFitRows = _tableStyles__WEBPACK_IMPORTED_MODULE_2__.TABLE_VIEWPORT_MIN_FIT_ROWS,
    enabled = true
  } = options;
  const [suggestedInitialRows, setSuggestedInitialRows] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(() => totalRows > 0 ? Math.min(totalRows, minRows) : 0);
  const measure = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    if (!enabled) {
      return;
    }
    const host = hostRef.current;
    const main = getMainElement();
    if (!host || !main) {
      setSuggestedInitialRows(totalRows > 0 ? Math.min(totalRows, minRows) : 0);
      return;
    }
    const dataViewport = getDataViewportElement(host);
    const anchor = dataViewport || host;
    const availablePx = getAvailableHeightBelowAnchor(main, anchor);
    const fallbackRowPx = 48;
    const rowH = rowHeightPx && rowHeightPx > 0 ? rowHeightPx : fallbackRowPx;
    const suggested = (0,_getViewportRowCapacity__WEBPACK_IMPORTED_MODULE_1__.getViewportRowCapacity)({
      availablePx,
      headerRows,
      rowHeightPx: rowH,
      minRows,
      maxRows,
      minFitRows,
      totalRows,
      toolbarPx: 0,
      safePaddingPx: 0
    });
    setSuggestedInitialRows(suggested);
  }, [hostRef, headerRows, totalRows, rowHeightPx, minRows, maxRows, minFitRows, enabled]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const runMeasure = () => {
      requestAnimationFrame(measure);
    };
    runMeasure();
    const host = hostRef.current;
    const main = getMainElement();
    const observer = new ResizeObserver(runMeasure);
    if (host) {
      observer.observe(host);
    }
    if (main) {
      observer.observe(main);
    }
    main?.addEventListener('scroll', runMeasure, {
      passive: true
    });
    window.addEventListener('resize', runMeasure, {
      passive: true
    });
    return () => {
      observer.disconnect();
      main?.removeEventListener('scroll', runMeasure);
      window.removeEventListener('resize', runMeasure);
    };
  }, [hostRef, measure]);
  return suggestedInitialRows;
}

/***/ }

}]);
//# sourceMappingURL=src_coordinator_pages_SessionWizard_jsx.js.map?ver=d8f8212b8d66618dcf28