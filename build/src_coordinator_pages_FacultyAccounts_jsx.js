"use strict";
(globalThis["webpackChunkproject_reviews"] = globalThis["webpackChunkproject_reviews"] || []).push([["src_coordinator_pages_FacultyAccounts_jsx"],{

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

/***/ "./src/coordinator/pages/FacultyAccounts.jsx"
/*!***************************************************!*\
  !*** ./src/coordinator/pages/FacultyAccounts.jsx ***!
  \***************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   FacultyAccounts: () => (/* binding */ FacultyAccounts)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/apiErrors */ "./src/shared/apiErrors.js");
/* harmony import */ var _shared_hooks_useDebouncedValue__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/hooks/useDebouncedValue */ "./src/shared/hooks/useDebouncedValue.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var _shared_hooks_useLoadingPhase__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../shared/hooks/useLoadingPhase */ "./src/shared/hooks/useLoadingPhase.js");
/* harmony import */ var _components_CsvImportMapper__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../components/CsvImportMapper */ "./src/coordinator/components/CsvImportMapper.jsx");
/* harmony import */ var _shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../shared/TableScrollViewport */ "./src/shared/TableScrollViewport.jsx");
/* harmony import */ var _shared_tableStyles__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../../shared/tableStyles */ "./src/shared/tableStyles.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);










const DEFAULT_PER_PAGE = 20;
function AddFacultyForm({
  onSuccess,
  onError
}) {
  const [busy, setBusy] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [fields, setFields] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({
    name: '',
    email: '',
    emp_id: '',
    designation: '',
    gender: ''
  });
  const [fieldErrors, setFieldErrors] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)({});
  const [expanded, setExpanded] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const set = key => e => setFields(prev => ({
    ...prev,
    [key]: e.target.value
  }));
  const handleSubmit = async e => {
    e.preventDefault();
    setFieldErrors({});
    setBusy(true);
    try {
      const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)('/faculty-accounts', fields);
      setFields({
        name: '',
        email: '',
        emp_id: '',
        designation: '',
        gender: ''
      });
      setExpanded(false);
      onSuccess(result);
    } catch (err) {
      // Field-level errors come back in err.data.fields
      const fields_errs = err?.data?.fields ?? {};
      if (Object.keys(fields_errs).length > 0) {
        setFieldErrors(fields_errs);
      } else {
        onError((0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Could not add faculty account.'));
      }
    } finally {
      setBusy(false);
    }
  };
  if (!expanded) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
      variant: "primary",
      onClick: () => setExpanded(true),
      children: "Add faculty"
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("form", {
    onSubmit: handleSubmit,
    className: "mt-4 rounded-lg border border-border bg-surface-raised p-4",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("h3", {
      className: "mb-3 text-sm font-semibold text-text",
      children: "Add a reviewer"
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      className: "grid grid-cols-1 gap-3 sm:grid-cols-2",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("label", {
          htmlFor: "add-faculty-name",
          className: "block text-xs font-medium text-text-muted",
          children: ["Name ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
            className: "text-error",
            children: "*"
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
          id: "add-faculty-name",
          type: "text",
          required: true,
          className: "mt-1 w-full rounded border border-border px-2 py-1.5 text-sm",
          value: fields.name,
          onChange: set('name')
        }), fieldErrors.name && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
          className: "mt-1 text-xs text-error",
          children: fieldErrors.name
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("label", {
          htmlFor: "add-faculty-email",
          className: "block text-xs font-medium text-text-muted",
          children: ["Email ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
            className: "text-error",
            children: "*"
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
          id: "add-faculty-email",
          type: "email",
          required: true,
          className: "mt-1 w-full rounded border border-border px-2 py-1.5 text-sm",
          value: fields.email,
          onChange: set('email')
        }), fieldErrors.email && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
          className: "mt-1 text-xs text-error",
          children: fieldErrors.email
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
          htmlFor: "add-faculty-empid",
          className: "block text-xs font-medium text-text-muted",
          children: "Employee ID"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
          id: "add-faculty-empid",
          type: "text",
          className: "mt-1 w-full rounded border border-border px-2 py-1.5 text-sm",
          value: fields.emp_id,
          onChange: set('emp_id')
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
          htmlFor: "add-faculty-designation",
          className: "block text-xs font-medium text-text-muted",
          children: "Designation"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
          id: "add-faculty-designation",
          type: "text",
          className: "mt-1 w-full rounded border border-border px-2 py-1.5 text-sm",
          value: fields.designation,
          onChange: set('designation')
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
          htmlFor: "add-faculty-gender",
          className: "block text-xs font-medium text-text-muted",
          children: "Gender"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
          id: "add-faculty-gender",
          type: "text",
          className: "mt-1 w-full rounded border border-border px-2 py-1.5 text-sm",
          value: fields.gender,
          onChange: set('gender')
        })]
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      className: "mt-4 flex gap-2",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
        type: "submit",
        variant: "primary",
        disabled: busy,
        children: busy ? 'Adding…' : 'Add reviewer'
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
        type: "button",
        variant: "secondary",
        onClick: () => {
          setExpanded(false);
          setFieldErrors({});
        },
        children: "Cancel"
      })]
    })]
  });
}
function FacultyAccounts() {
  const canAssign = window.prAppData?.canAssignReviewers !== false;
  const templateUrl = window.prAppData?.facultyAccountsTemplateUrl ?? '';
  const [accounts, setAccounts] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [directoryImport, setDirectoryImport] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [search, setSearch] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [showImport, setShowImport] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [importNotice, setImportNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const [syncBusy, setSyncBusy] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [page, setPage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(1);
  const [totalPages, setTotalPages] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(1);
  const [total, setTotal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(0);
  const [selected, setSelected] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(new Set());
  const [deleteBusy, setDeleteBusy] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [confirmDelete, setConfirmDelete] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const debouncedSearch = (0,_shared_hooks_useDebouncedValue__WEBPACK_IMPORTED_MODULE_3__.useDebouncedValue)(search, 300);

  // Reset to page 1 when search changes.
  const prevSearch = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(debouncedSearch);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (prevSearch.current !== debouncedSearch) {
      prevSearch.current = debouncedSearch;
      setPage(1);
    }
  }, [debouncedSearch]);
  const loadAccounts = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams({
        page: String(page),
        per_page: String(DEFAULT_PER_PAGE)
      });
      if (debouncedSearch) {
        params.set('search', debouncedSearch);
      }
      const data = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.get)(`/faculty-accounts?${params.toString()}`);
      setAccounts(data.accounts ?? []);
      setDirectoryImport(data.directory_import ?? null);
      setTotalPages(data.total_pages ?? 1);
      setTotal(data.total ?? 0);
      setSelected(new Set());
    } catch {
      setAccounts([]);
      setDirectoryImport(null);
    } finally {
      setLoading(false);
    }
  }, [debouncedSearch, page]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (!canAssign) {
      return;
    }
    loadAccounts();
  }, [canAssign, loadAccounts]);
  const handleSyncDirectory = async () => {
    setSyncBusy(true);
    setImportNotice(null);
    try {
      const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.post)('/faculty-accounts/sync-directory', {});
      setImportNotice({
        variant: (result.failed ?? 0) > 0 ? 'warning' : 'success',
        message: `Directory sync: ${result.created ?? 0} created, ${result.updated ?? 0} updated, ${result.skipped ?? 0} skipped, ${result.failed ?? 0} failed.`
      });
      setPage(1);
      loadAccounts();
    } catch (err) {
      setImportNotice({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Could not sync from faculty directory.')
      });
    } finally {
      setSyncBusy(false);
    }
  };
  const handleBulkDelete = async () => {
    if (selected.size === 0) {
      return;
    }
    setDeleteBusy(true);
    setConfirmDelete(false);
    try {
      const result = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_1__.del)('/faculty-accounts', {
        ids: [...selected]
      });
      const msg = result.deleted > 0 ? `${result.deleted} reviewer${result.deleted !== 1 ? 's' : ''} deleted.` : 'No reviewers deleted.';
      const errMsg = result.failed > 0 ? ` ${result.failed} could not be deleted (assigned to panels).` : '';
      setImportNotice({
        variant: result.failed > 0 ? 'warning' : 'success',
        message: msg + errMsg
      });
      setPage(1);
      loadAccounts();
    } catch (err) {
      setImportNotice({
        variant: 'error',
        message: (0,_shared_apiErrors__WEBPACK_IMPORTED_MODULE_2__.parseApiErrorMessage)(err, 'Could not delete reviewers.')
      });
    } finally {
      setDeleteBusy(false);
    }
  };
  const handleToggleSelect = userId => {
    setSelected(prev => {
      const next = new Set(prev);
      if (next.has(userId)) {
        next.delete(userId);
      } else {
        next.add(userId);
      }
      return next;
    });
  };
  const handleSelectAll = e => {
    if (e.target.checked) {
      setSelected(new Set((accounts ?? []).map(a => a.user_id)));
    } else {
      setSelected(new Set());
    }
  };
  if (!canAssign) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Notice, {
      variant: "warning",
      children: "You do not have permission to manage faculty accounts."
    });
  }
  const {
    showSkeleton,
    showOverlay
  } = (0,_shared_hooks_useLoadingPhase__WEBPACK_IMPORTED_MODULE_5__.useLoadingPhase)(loading, accounts !== null);
  const bridgeEnabled = Boolean(directoryImport?.import_available ?? window.prAppData?.facultyBridgeEnabled);
  const hasAccounts = (accounts?.length ?? 0) > 0;
  const showEmpty = !hasAccounts && !debouncedSearch && !showImport;
  const allSelected = hasAccounts && (accounts ?? []).every(a => selected.has(a.user_id));
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.PageHeader, {
      title: "Faculty accounts",
      description: "This is the reviewer pool \u2014 people who can be assigned to review panels. Adding someone here does not notify them. Login credentials are emailed separately when a review opens.",
      actions: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: "flex flex-wrap gap-2",
        children: [bridgeEnabled ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
          variant: "secondary",
          disabled: syncBusy,
          onClick: handleSyncDirectory,
          children: syncBusy ? 'Syncing…' : 'Import from faculty directory'
        }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
          variant: "secondary",
          onClick: () => setShowImport(value => !value),
          children: showImport ? 'Hide import' : 'Import CSV'
        })]
      })
    }), importNotice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
      className: "mt-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Notice, {
        variant: importNotice.variant,
        onDismiss: () => setImportNotice(null),
        children: importNotice.message
      })
    }) : null, directoryImport && !directoryImport.import_available && directoryImport.setting_enabled && !directoryImport.table_available ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
      className: "mt-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Notice, {
        variant: "warning",
        children: ["Faculty directory bridge is enabled in settings, but the faculty table was not found. Expected", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("code", {
          className: "text-xs",
          children: "wp_faculty"
        }), " (or your site prefix + ", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("code", {
          className: "text-xs",
          children: "faculty"
        }), "). CSV import still works."]
      })
    }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
      className: "mt-4",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(AddFacultyForm, {
        onSuccess: () => {
          setImportNotice({
            variant: 'success',
            message: 'Reviewer added to the pool.'
          });
          setPage(1);
          loadAccounts();
        },
        onError: msg => setImportNotice({
          variant: 'error',
          message: msg
        })
      })
    }), showImport ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_components_CsvImportMapper__WEBPACK_IMPORTED_MODULE_6__.CsvImportMapper, {
      importType: "faculty-accounts",
      onImportSuccess: ({
        variant,
        message
      }) => {
        if (message) {
          setImportNotice({
            variant: variant ?? 'success',
            message
          });
        }
      },
      onComplete: loadAccounts,
      onDownloadTemplate: templateUrl ? () => {
        window.location.href = templateUrl;
      } : null
    }) : null, showSkeleton ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.ContentLoadingRegion, {
      busy: true,
      variant: "inline",
      label: "Loading faculty accounts",
      className: "mt-6",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.TableSkeleton, {
        columns: 6
      })
    }) : showEmpty ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.EmptyState, {
      title: "No reviewers in the pool yet",
      description: "Add reviewers one at a time, import a CSV, or sync from the faculty directory.",
      action: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: "flex flex-wrap justify-center gap-2",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
          variant: "primary",
          onClick: () => setShowImport(true),
          children: "Import CSV"
        }), bridgeEnabled ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
          variant: "secondary",
          disabled: syncBusy,
          onClick: handleSyncDirectory,
          children: "Import from directory"
        }) : null]
      })
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
        className: "mt-6 flex flex-wrap items-end justify-between gap-3",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("label", {
            className: "block text-sm font-medium text-text",
            htmlFor: "faculty-search",
            children: "Search accounts"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
            id: "faculty-search",
            type: "search",
            className: "mt-1 w-full max-w-md rounded-md border border-border px-3 py-2 text-sm",
            value: search,
            onChange: event => setSearch(event.target.value),
            placeholder: "Name, email, or employee ID"
          })]
        }), selected.size > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: "flex items-center gap-2",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("span", {
            className: "text-sm text-text-muted",
            children: [selected.size, " selected"]
          }), confirmDelete ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("span", {
              className: "text-sm text-error",
              children: ["Delete ", selected.size, " reviewer", selected.size !== 1 ? 's' : '', "?"]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
              variant: "danger",
              disabled: deleteBusy,
              onClick: handleBulkDelete,
              children: deleteBusy ? 'Deleting…' : 'Confirm delete'
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
              variant: "secondary",
              onClick: () => setConfirmDelete(false),
              children: "Cancel"
            })]
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
            variant: "secondary",
            onClick: () => setConfirmDelete(true),
            children: "Delete selected"
          })]
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.ContentLoadingRegion, {
        busy: showOverlay,
        variant: "overlay",
        label: "Loading faculty accounts",
        className: "mt-6",
        children: !hasAccounts ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
          className: "text-sm text-text-muted",
          children: "No accounts match your search."
        }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_TableScrollViewport__WEBPACK_IMPORTED_MODULE_7__.TableDataViewport, {
            className: "mt-2 bg-surface-raised shadow-card",
            bodyRowCount: accounts.length,
            rowIncrement: _shared_tableStyles__WEBPACK_IMPORTED_MODULE_8__.TABLE_VIEWPORT_ROW_INCREMENT,
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("table", {
              className: "w-max min-w-full text-left text-sm",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("thead", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("tr", {
                  className: "border-b border-border bg-surface text-text-muted",
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
                    className: "px-4 py-3",
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
                      type: "checkbox",
                      "aria-label": "Select all",
                      checked: allSelected,
                      onChange: handleSelectAll
                    })
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
                    className: `${(0,_shared_tableStyles__WEBPACK_IMPORTED_MODULE_8__.regNoStickyClass)({
                      header: true
                    })} px-4 py-3 font-medium`,
                    style: (0,_shared_tableStyles__WEBPACK_IMPORTED_MODULE_8__.regNoStickyStyle)(),
                    children: "Name"
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
                    className: "px-4 py-3 font-medium",
                    children: "Email"
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
                    className: "px-4 py-3 font-medium",
                    children: "Emp. ID"
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
                    className: "px-4 py-3 font-medium",
                    children: "WP user"
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("th", {
                    className: "px-4 py-3 font-medium",
                    children: "Created"
                  })]
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("tbody", {
                children: accounts.map(account => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("tr", {
                  className: `group ${_shared_tableStyles__WEBPACK_IMPORTED_MODULE_8__.TABLE_BODY_ROW_SOFT}`,
                  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
                    className: "px-4 py-3",
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("input", {
                      type: "checkbox",
                      "aria-label": `Select ${account.display_name || account.email}`,
                      checked: selected.has(account.user_id),
                      onChange: () => handleToggleSelect(account.user_id)
                    })
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
                    className: `${(0,_shared_tableStyles__WEBPACK_IMPORTED_MODULE_8__.regNoStickyClass)()} px-4 py-3 font-medium text-text group-hover:bg-surface-raised`,
                    style: (0,_shared_tableStyles__WEBPACK_IMPORTED_MODULE_8__.regNoStickyStyle)(),
                    children: account.display_name || '—'
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
                    className: "px-4 py-3 text-text",
                    children: account.email || '—'
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
                    className: "px-4 py-3 text-text-muted",
                    children: account.emp_id || '—'
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
                    className: "px-4 py-3 text-text-muted",
                    children: account.linked ? `#${account.user_id}` : '—'
                  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("td", {
                    className: "px-4 py-3 text-text-muted",
                    children: account.created_at ? account.created_at.slice(0, 10) : '—'
                  })]
                }, account.user_id))
              })]
            })
          }), totalPages > 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
            className: "mt-4 flex items-center justify-between text-sm text-text-muted",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("span", {
              children: [total, " reviewer", total !== 1 ? 's' : '', " total"]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
              className: "flex items-center gap-2",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
                variant: "secondary",
                disabled: page <= 1,
                onClick: () => setPage(p => Math.max(1, p - 1)),
                children: "Previous"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("span", {
                children: ["Page ", page, " of ", totalPages]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_4__.Button, {
                variant: "secondary",
                disabled: page >= totalPages,
                onClick: () => setPage(p => Math.min(totalPages, p + 1)),
                children: "Next"
              })]
            })]
          })]
        })
      })]
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

/***/ "./src/shared/hooks/useDebouncedValue.js"
/*!***********************************************!*\
  !*** ./src/shared/hooks/useDebouncedValue.js ***!
  \***********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useDebouncedValue: () => (/* binding */ useDebouncedValue)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);

function useDebouncedValue(value, delayMs = 300) {
  const [debounced, setDebounced] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(value);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const timer = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(timer);
  }, [value, delayMs]);
  return debounced;
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
//# sourceMappingURL=src_coordinator_pages_FacultyAccounts_jsx.js.map?ver=5a8838f96097058e3d0f