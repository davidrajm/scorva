"use strict";
(globalThis["webpackChunkproject_reviews"] = globalThis["webpackChunkproject_reviews"] || []).push([["src_coordinator_pages_PanelReportSettings_jsx"],{

/***/ "./src/coordinator/components/PanelReportSettingsPreview.jsx"
/*!*******************************************************************!*\
  !*** ./src/coordinator/components/PanelReportSettingsPreview.jsx ***!
  \*******************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PanelReportSettingsPreview: () => (/* binding */ PanelReportSettingsPreview)
/* harmony export */ });
/* harmony import */ var _shared_tableStyles__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../shared/tableStyles */ "./src/shared/tableStyles.js");
/* harmony import */ var _panelReportPreviewFixture__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./panelReportPreviewFixture */ "./src/coordinator/components/panelReportPreviewFixture.js");
/* harmony import */ var _panelReportTableConfig__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./panelReportTableConfig */ "./src/coordinator/components/panelReportTableConfig.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);




function RegionCaption({
  id,
  children
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
      className: "sr-only",
      id: id,
      children: children
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
      className: "mb-2 font-sans text-[10px] font-medium uppercase tracking-wide text-text-muted",
      "aria-hidden": "true",
      children: children
    })]
  });
}
function letterheadClassForIndex(index) {
  if (index === 0) {
    return 'letterhead-title';
  }
  if (index === 1) {
    return 'letterhead-subtitle';
  }
  return 'letterhead-body';
}
function letterheadLabelForIndex(index, optional = false) {
  if (index === 0) {
    return optional ? 'Department (optional)' : 'Department';
  }
  if (index === 1) {
    return optional ? 'School (optional)' : 'School';
  }
  return optional ? `Line ${index + 1} (optional)` : `Line ${index + 1}`;
}
function PanelReportSettingsPreview({
  settings,
  logoPreview,
  logoBlock,
  disabled,
  onUpdate,
  onLetterheadText,
  onLogoChange,
  onLogoWidthChange
}) {
  const report = settings?.report || {};
  const table = settings?.table || {};
  const footer = settings?.footer || {};
  const signatures = settings?.signatures || {};
  const hod = signatures?.hod || {};
  const fixture = _panelReportPreviewFixture__WEBPACK_IMPORTED_MODULE_1__.PANEL_REPORT_PREVIEW_FIXTURE;
  const textBlocks = (settings?.letterhead?.blocks || []).filter(b => b.type === 'text');
  const columns = (0,_panelReportTableConfig__WEBPACK_IMPORTED_MODULE_2__.buildPreviewScoreColumns)(table, fixture.reviewers);
  const widthIn = logoBlock?.width_in ?? 4;
  const program = report.program_name || '';
  const semester = report.semester || '';
  const metaRows = [{
    cells: [{
      label: 'Program Name',
      value: program,
      editable: 'report.program_name'
    }, {
      label: 'Semester',
      value: semester,
      editable: 'report.semester'
    }]
  }];
  const detailCells = [];
  if (report.show_review_number !== false) {
    detailCells.push({
      label: 'Review Number',
      value: fixture.review_label
    });
  }
  if (report.show_panel_name !== false) {
    detailCells.push({
      label: 'Panel Name',
      value: fixture.panel_name
    });
  }
  if (detailCells.length) {
    metaRows.push({
      cells: detailCells
    });
  }
  if (report.show_reviewers_list !== false) {
    const reviewerNamesLine = fixture.reviewers.map(reviewer => reviewer.name).join(', ');
    metaRows.push({
      fullWidth: true,
      cells: [{
        label: 'Reviewers',
        value: reviewerNamesLine
      }]
    });
  }
  const showLegend = table.show_reviewer_legend !== false;
  const sigPattern = signatures.reviewer_label_pattern || 'Reviewer {n}';
  const legendParts = fixture.reviewers.map(reviewer => {
    const short = (0,_panelReportTableConfig__WEBPACK_IMPORTED_MODULE_2__.formatReviewerHeader)(table.reviewer_header_pattern || 'R{n}', reviewer.ordinal);
    const long = (0,_panelReportTableConfig__WEBPACK_IMPORTED_MODULE_2__.formatReviewerHeader)(sigPattern, reviewer.ordinal);
    return `${short} = ${long} (${reviewer.name})`;
  });
  const signatureLines = fixture.reviewers.map((reviewer, index) => ({
    label: (0,_panelReportTableConfig__WEBPACK_IMPORTED_MODULE_2__.formatReviewerHeader)(sigPattern, reviewer.ordinal),
    name: reviewer.name,
    key: `rev-${index}`
  }));
  if (signatures.show_panel_coordinator_line !== false) {
    signatureLines.push({
      label: 'Panel Coordinator',
      name: '',
      key: 'panel-coordinator'
    });
  }
  const hodCaption = (hod.name || '').trim() !== '' ? `${hod.label || 'Head of the Department'}: ${hod.name}` : hod.label || 'Head of the Department';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
    className: "min-w-0 w-full max-w-full",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
      className: "mx-auto min-w-0 w-full max-w-[210mm] rounded-md border border-border bg-white p-4 shadow-sm sm:p-6 lg:max-w-3xl",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
        className: "pr-panel-report-preview min-w-0",
        "aria-labelledby": "panel-report-preview-heading",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("h2", {
          id: "panel-report-preview-heading",
          className: "sr-only",
          children: "Review Report layout preview"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("section", {
          className: "pr-preview-region letterhead",
          "aria-labelledby": "region-letterhead",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(RegionCaption, {
            id: "region-letterhead",
            children: "Letterhead"
          }), logoPreview ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("img", {
            src: logoPreview,
            alt: "",
            style: {
              width: `${widthIn}in`,
              maxWidth: '100%',
              maxHeight: '1.5in',
              height: 'auto'
            }
          }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
            className: "mx-auto mb-2 flex h-16 w-40 items-center justify-center border border-dashed border-black/30 bg-[#fafafa] text-[10px] text-black/50",
            "aria-hidden": "true",
            children: "Logo"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
            className: "pr-preview-logo-controls max-w-full",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("label", {
              className: "block max-w-full",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
                className: "sr-only",
                children: "Upload logo"
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                type: "file",
                accept: "image/*",
                className: "max-w-full text-xs",
                disabled: disabled,
                onChange: onLogoChange
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("label", {
              className: "mt-2 inline-flex items-center gap-2",
              children: ["Width (inches)", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                type: "number",
                min: "0.5",
                max: "8",
                step: "0.1",
                className: "w-16 rounded border border-border px-2 py-1",
                value: widthIn,
                disabled: disabled,
                onChange: onLogoWidthChange
              })]
            })]
          }), textBlocks.map((block, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
            className: letterheadClassForIndex(index),
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("label", {
              className: "sr-only",
              children: letterheadLabelForIndex(index)
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
              type: "text",
              className: "pr-preview-inline-input text-center",
              placeholder: letterheadLabelForIndex(index, true),
              value: block.value || '',
              disabled: disabled,
              onChange: e => onLetterheadText(index, 'value', e.target.value)
            })]
          }, `lh-block-${index}`))]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("section", {
          className: "pr-preview-region",
          "aria-labelledby": "region-report-details",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(RegionCaption, {
            id: "region-report-details",
            children: "Report details"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
            className: "report-title",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("label", {
              className: "sr-only",
              children: "Report title"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
              type: "text",
              className: "pr-preview-inline-input text-center font-bold",
              value: report.title || 'Review Report',
              disabled: disabled,
              onChange: e => onUpdate('report.title', e.target.value)
            })]
          }), metaRows.length > 0 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
            className: `${_shared_tableStyles__WEBPACK_IMPORTED_MODULE_0__.TABLE_SCROLL_WRAPPER} mb-4`,
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("table", {
              className: `meta-table ${_shared_tableStyles__WEBPACK_IMPORTED_MODULE_0__.TABLE_SCROLL_INNER}`,
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("tbody", {
                children: metaRows.map((row, rowIndex) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("tr", {
                  children: row.fullWidth ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
                    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("th", {
                      children: row.cells[0].label
                    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("td", {
                      colSpan: 3,
                      children: row.cells[0].value
                    })]
                  }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
                    children: [row.cells.map((cell, cellIndex) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(MetaCell, {
                      cell: cell,
                      disabled: disabled,
                      onUpdate: onUpdate
                    }, `${cell.label}-${cellIndex}`)), row.cells.length === 1 ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
                      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("th", {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("td", {})]
                    }) : null]
                  })
                }, `meta-${rowIndex}`))
              })
            })
          }) : null]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("section", {
          className: "pr-preview-region",
          "aria-labelledby": "region-scores-table",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(RegionCaption, {
            id: "region-scores-table",
            children: "Scores table"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
            className: _shared_tableStyles__WEBPACK_IMPORTED_MODULE_0__.TABLE_SCROLL_WRAPPER,
            "data-testid": "panel-report-preview-scroll",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("table", {
              className: `scores ${_shared_tableStyles__WEBPACK_IMPORTED_MODULE_0__.TABLE_SCROLL_INNER}`,
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("thead", {
                children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("tr", {
                  children: [_panelReportTableConfig__WEBPACK_IMPORTED_MODULE_2__.TABLE_COLUMNS.map(column => {
                    const enabled = column.alwaysOn || table[column.showKey] !== false;
                    if (!enabled) {
                      return null;
                    }
                    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("th", {
                      className: column.shrink ? 'col-shrink col-wrap' : 'col-wrap',
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
                        className: "pr-preview-th-control",
                        children: [!column.alwaysOn ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("label", {
                          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                            type: "checkbox",
                            checked: true,
                            disabled: disabled,
                            onChange: e => onUpdate(`table.${column.showKey}`, e.target.checked)
                          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
                            children: column.name
                          })]
                        }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                          type: "text",
                          className: "pr-preview-inline-input",
                          value: table[column.labelKey] ?? column.defaultLabel,
                          disabled: disabled,
                          onChange: e => onUpdate(`table.${column.labelKey}`, e.target.value)
                        })]
                      })
                    }, column.showKey);
                  }), fixture.reviewers.map(reviewer => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("th", {
                    className: "col-reviewer col-shrink",
                    children: (0,_panelReportTableConfig__WEBPACK_IMPORTED_MODULE_2__.formatReviewerHeader)(table.reviewer_header_pattern || 'R{n}', reviewer.ordinal)
                  }, `rev-h-${reviewer.ordinal}`)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("th", {
                    className: "col-final col-shrink",
                    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
                      className: "pr-preview-th-control",
                      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                        type: "text",
                        className: "pr-preview-inline-input",
                        value: table.final_marks_column_header || 'Final Marks',
                        disabled: disabled,
                        onChange: e => onUpdate('table.final_marks_column_header', e.target.value)
                      })
                    })
                  })]
                })
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("tbody", {
                children: fixture.students.map(student => {
                  const cells = (0,_panelReportTableConfig__WEBPACK_IMPORTED_MODULE_2__.previewScoreRowCells)(student, columns);
                  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("tr", {
                    children: cells.map((cell, cellIndex) => {
                      const column = columns[cellIndex];
                      const cls = column.shrink ? 'col-shrink col-wrap' : 'col-wrap';
                      const extra = column.className || '';
                      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("td", {
                        className: `${cls} ${extra}`.trim(),
                        children: cell
                      }, cellIndex);
                    })
                  }, student.reg_no);
                })
              })]
            })
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("label", {
            className: "mb-3 flex items-center gap-2 font-sans text-xs text-text",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
              type: "checkbox",
              checked: showLegend,
              disabled: disabled,
              onChange: e => onUpdate('table.show_reviewer_legend', e.target.checked)
            }), "Show reviewer legend below table"]
          }), showLegend ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("p", {
            className: "reviewer-legend",
            children: legendParts.join('; ')
          }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
            className: "font-sans text-xs text-text-muted",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("label", {
              className: "block",
              children: ["Reviewer header pattern (use ", '{n}', " for number)", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                type: "text",
                className: "mt-1 w-full max-w-xs rounded border border-border px-2 py-1 text-sm",
                placeholder: "R{n}",
                value: table.reviewer_header_pattern || 'R{n}',
                disabled: disabled,
                onChange: e => onUpdate('table.reviewer_header_pattern', e.target.value)
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("p", {
              className: "mt-1 max-w-md",
              children: ["Short column headers in the scores table. Examples:", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("code", {
                className: "text-text",
                children: ["R", '{n}']
              }), " \u2192 R1, R2;", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("code", {
                className: "text-text",
                children: ["Rev ", '{n}']
              }), " \u2192 Rev 1, Rev 2;", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("code", {
                className: "text-text",
                children: ["#", '{n}']
              }), " \u2192 #1, #2. Pattern must include ", '{n}', ".", table.reviewer_header_pattern ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
                children: [' ', "Preview:", ' ', fixture.reviewers.map(r => (0,_panelReportTableConfig__WEBPACK_IMPORTED_MODULE_2__.formatReviewerHeader)(table.reviewer_header_pattern || 'R{n}', r.ordinal)).join(', ')]
              }) : null]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("label", {
              className: "mt-2 block",
              children: ["Project title field key", /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                type: "text",
                className: "mt-1 w-full max-w-xs rounded border border-border px-2 py-1 text-sm",
                placeholder: "project_title",
                value: table.project_title_field_key || 'project_title',
                disabled: disabled,
                onChange: e => onUpdate('table.project_title_field_key', e.target.value)
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("p", {
              className: "mt-1 max-w-md",
              children: ["Registry custom-field key used in the PDF when a student has no per-review project title on their assignment. Default", ' ', /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("code", {
                className: "text-text",
                children: "project_title"
              }), " matches the Student Registry field."]
            })]
          }), _panelReportTableConfig__WEBPACK_IMPORTED_MODULE_2__.TABLE_COLUMNS.filter(c => !c.alwaysOn && table[c.showKey] === false).map(column => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("label", {
            className: "mt-2 flex items-center gap-2 font-sans text-xs",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
              type: "checkbox",
              checked: false,
              disabled: disabled,
              onChange: e => onUpdate(`table.${column.showKey}`, e.target.checked)
            }), "Show ", column.name, " column"]
          }, `off-${column.showKey}`))]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("section", {
          className: "pr-preview-region sig-section",
          "aria-labelledby": "region-signatures",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(RegionCaption, {
            id: "region-signatures",
            children: "Signatures"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
            className: "sig-heading",
            children: "Signatures with date"
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
            className: "sig-layout",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
              className: "sig-left",
              children: [signatureLines.map(line => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
                className: "sig-row",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
                  className: "sig-line",
                  "aria-hidden": "true"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
                  className: "sig-label",
                  children: line.label
                }), line.name ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
                  className: "sig-label",
                  style: {
                    fontWeight: 400
                  },
                  children: line.name
                }) : null]
              }, line.key)), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("label", {
                className: "mt-2 flex items-center gap-2 font-sans text-xs",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                  type: "checkbox",
                  checked: signatures.show_panel_coordinator_line !== false,
                  disabled: disabled,
                  onChange: e => onUpdate('signatures.show_panel_coordinator_line', e.target.checked)
                }), "Show panel coordinator line when not in roster"]
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
              className: "sig-right",
              children: hod.enabled !== false ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
                className: "sig-row",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
                  className: "sig-line",
                  "aria-hidden": "true"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("span", {
                  className: "sig-label",
                  children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                    type: "text",
                    className: "pr-preview-inline-input text-right",
                    placeholder: "HoD label",
                    value: hod.label || '',
                    disabled: disabled,
                    onChange: e => onUpdate('signatures.hod.label', e.target.value)
                  })
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                  type: "text",
                  className: "pr-preview-inline-input mt-1 text-right",
                  placeholder: "HoD name",
                  value: hod.name || '',
                  disabled: disabled,
                  onChange: e => onUpdate('signatures.hod.name', e.target.value)
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("span", {
                  className: "sr-only",
                  children: ["Preview: ", hodCaption]
                })]
              }) : null
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("div", {
            className: "pr-preview-footer-note",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("label", {
              className: "flex items-center gap-2",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
                type: "checkbox",
                checked: footer.show_generated_datetime !== false,
                disabled: disabled,
                onChange: e => onUpdate('footer.show_generated_datetime', e.target.checked)
              }), "Show generated date & time on each page (bottom left)"]
            }), footer.show_generated_datetime !== false ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)("p", {
              className: "mt-1 italic",
              children: ["Report generated: ", new Date().toLocaleString(), " (sample)"]
            }) : null]
          })]
        })]
      })
    })
  });
}
function MetaCell({
  cell,
  disabled,
  onUpdate
}) {
  if (cell.editable) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("th", {
        children: cell.label
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("td", {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("input", {
          type: "text",
          className: "pr-preview-inline-input",
          value: cell.value,
          placeholder: cell.label,
          disabled: disabled,
          onChange: e => onUpdate(cell.editable, e.target.value)
        })
      })]
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("th", {
      children: cell.label
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("td", {
      children: cell.value
    })]
  });
}

/***/ },

/***/ "./src/coordinator/components/panelReportPreviewFixture.js"
/*!*****************************************************************!*\
  !*** ./src/coordinator/components/panelReportPreviewFixture.js ***!
  \*****************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PANEL_REPORT_PREVIEW_FIXTURE: () => (/* binding */ PANEL_REPORT_PREVIEW_FIXTURE)
/* harmony export */ });
/**
 * Static sample data for Panel Report settings WYSIWYG preview (no REST).
 */

/** Last two digits of the batch / academic-year start (calendar year). */
function academicYearPrefix() {
  return String(new Date().getFullYear()).slice(-2);
}
const REG_PREFIX = academicYearPrefix();
const PANEL_REPORT_PREVIEW_FIXTURE = {
  review_label: 'Review 1',
  panel_name: 'Panel A',
  reviewers: [{
    ordinal: 1,
    name: 'Dr. Sample One'
  }, {
    ordinal: 2,
    name: 'Dr. Sample Two'
  }, {
    ordinal: 3,
    name: 'Dr. Sample Three'
  }],
  students: [{
    sr_no: 1,
    reg_no: `${REG_PREFIX}MDT1001`,
    name: 'Alex Sample',
    attendance_label: 'P',
    project_title: 'Smart Campus IoT Network',
    guide_name: 'Prof. Guide Alpha',
    review_score: 42.5
  }, {
    sr_no: 2,
    reg_no: `${REG_PREFIX}MDT1002`,
    name: 'Jordan Sample',
    attendance_label: 'A',
    project_title: 'ML-Based Energy Forecasting',
    guide_name: 'Prof. Guide Beta',
    review_score: null
  }, {
    sr_no: 3,
    reg_no: `${REG_PREFIX}MDT1003`,
    name: 'Sam Sample',
    attendance_label: 'P',
    project_title: 'Blockchain Supply Chain Audit',
    guide_name: 'Prof. Guide Gamma',
    review_score: 38.0
  }]
};

/***/ },

/***/ "./src/coordinator/components/panelReportTableConfig.js"
/*!**************************************************************!*\
  !*** ./src/coordinator/components/panelReportTableConfig.js ***!
  \**************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   TABLE_COLUMNS: () => (/* binding */ TABLE_COLUMNS),
/* harmony export */   buildPreviewScoreColumns: () => (/* binding */ buildPreviewScoreColumns),
/* harmony export */   formatReviewerHeader: () => (/* binding */ formatReviewerHeader),
/* harmony export */   previewScoreRowCells: () => (/* binding */ previewScoreRowCells)
/* harmony export */ });
/**
 * Panel Report PDF table column config (shared by settings page and WYSIWYG preview).
 */
const TABLE_COLUMNS = [{
  showKey: 'show_sr_no',
  labelKey: 'sr_no_column_header',
  defaultLabel: 'Sr. No.',
  name: 'Serial number',
  alwaysOn: true,
  className: 'col-sr num',
  shrink: true
}, {
  showKey: 'show_reg_no',
  labelKey: 'reg_no_column_header',
  defaultLabel: 'Reg No',
  name: 'Registration number',
  className: 'col-reg num',
  shrink: false
}, {
  showKey: 'show_student_name',
  labelKey: 'student_column_header',
  defaultLabel: 'Student',
  name: 'Student name',
  alwaysOn: true,
  className: 'col-student',
  shrink: false
}, {
  showKey: 'show_attendance',
  labelKey: 'attendance_column_header',
  defaultLabel: 'At',
  name: 'Attendance',
  hint: 'Short header recommended (1–2 letters). Cells show P or A.',
  className: 'col-att num',
  shrink: true
}, {
  showKey: 'show_project_title',
  labelKey: 'project_title_column_header',
  defaultLabel: 'Project title',
  name: 'Project title',
  className: 'col-title',
  shrink: false
}, {
  showKey: 'show_guide_name',
  labelKey: 'guide_column_header',
  defaultLabel: 'Guide',
  name: 'Guide',
  className: 'col-guide',
  shrink: false
}];
function formatReviewerHeader(pattern, ordinal) {
  return (pattern || 'R{n}').replace('{n}', String(ordinal));
}

/**
 * @param {object} tableCfg settings.table
 * @param {Array<{ ordinal: number }>} reviewers
 */
function buildPreviewScoreColumns(tableCfg, reviewers) {
  const columns = [];
  const table = tableCfg || {};
  for (const col of TABLE_COLUMNS) {
    const enabled = col.alwaysOn || table[col.showKey] !== false;
    if (!enabled) {
      continue;
    }
    columns.push({
      ...col,
      header: table[col.labelKey] ?? col.defaultLabel
    });
  }
  const pattern = table.reviewer_header_pattern || 'R{n}';
  for (const reviewer of reviewers) {
    columns.push({
      header: formatReviewerHeader(pattern, reviewer.ordinal),
      className: 'col-reviewer score',
      shrink: true,
      isReviewer: true
    });
  }
  columns.push({
    header: table.final_marks_column_header || 'Final Marks',
    className: 'col-final score',
    shrink: true,
    isFinal: true
  });
  return columns;
}

/**
 * @param {object} student
 * @param {ReturnType<typeof buildPreviewScoreColumns>} columns
 */
function previewScoreRowCells(student, columns) {
  return columns.map(column => {
    if (column.isReviewer) {
      return student.attendance_label === 'A' ? '—' : '36.50';
    }
    if (column.isFinal) {
      return student.review_score == null ? '—' : Number(student.review_score).toFixed(2);
    }
    const cls = column.className || '';
    if (cls.includes('col-sr')) {
      return String(student.sr_no ?? '');
    }
    if (cls.includes('col-reg')) {
      return student.reg_no ?? '';
    }
    if (cls.includes('col-student')) {
      return student.name ?? '';
    }
    if (cls.includes('col-att')) {
      return student.attendance_label ?? 'P';
    }
    if (cls.includes('col-title')) {
      return student.project_title ?? '';
    }
    if (cls.includes('col-guide')) {
      return student.guide_name ?? '';
    }
    return '';
  });
}

/***/ },

/***/ "./src/coordinator/pages/PanelReportSettings.jsx"
/*!*******************************************************!*\
  !*** ./src/coordinator/pages/PanelReportSettings.jsx ***!
  \*******************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PanelReportSettings: () => (/* binding */ PanelReportSettings)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_router_dom__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react-router-dom */ "./node_modules/react-router/dist/index.js");
/* harmony import */ var _shared_api__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../shared/api */ "./src/shared/api.js");
/* harmony import */ var _shared_components__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../shared/components */ "./src/shared/components/index.js");
/* harmony import */ var _components_PanelReportSettingsPreview__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ../components/PanelReportSettingsPreview */ "./src/coordinator/components/PanelReportSettingsPreview.jsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






const DEFAULT_TEXT_BLOCKS = [{
  type: 'text',
  value: '',
  style: 'title',
  label: ''
}, {
  type: 'text',
  value: '',
  style: 'subtitle',
  label: ''
}];
function defaultSettings() {
  return {
    letterhead: {
      blocks: [{
        type: 'image',
        attachment_id: 0,
        width_in: 4,
        align: 'center'
      }, ...DEFAULT_TEXT_BLOCKS.map(block => ({
        ...block
      }))]
    },
    report: {
      title: 'Review Report',
      program_name: '',
      semester: '',
      show_review_number: true,
      show_panel_name: true,
      show_reviewers_list: true
    },
    table: {
      show_sr_no: true,
      sr_no_column_header: 'Sr. No.',
      show_reg_no: true,
      reg_no_column_header: 'Reg No',
      show_student_name: true,
      student_column_header: 'Student',
      show_attendance: true,
      attendance_column_header: 'At',
      show_project_title: true,
      project_title_column_header: 'Project title',
      project_title_field_key: 'project_title',
      show_guide_name: true,
      guide_column_header: 'Guide',
      final_marks_column_header: 'Final Marks',
      reviewer_header_pattern: 'R{n}',
      show_reviewer_legend: true
    },
    footer: {
      show_generated_datetime: true
    },
    signatures: {
      show_panel_coordinator_line: true,
      hod: {
        enabled: true,
        label: 'Head of the Department',
        name: ''
      }
    }
  };
}
function mergeSettings(loaded) {
  const base = defaultSettings();
  if (!loaded || typeof loaded !== 'object') {
    return base;
  }
  return {
    ...base,
    ...loaded,
    letterhead: {
      ...base.letterhead,
      ...(loaded.letterhead || {}),
      blocks: loaded.letterhead?.blocks?.length ? loaded.letterhead.blocks : base.letterhead.blocks
    },
    report: {
      ...base.report,
      ...(loaded.report || {})
    },
    table: {
      ...base.table,
      ...(loaded.table || {})
    },
    footer: {
      ...base.footer,
      ...(loaded.footer || {})
    },
    signatures: {
      ...base.signatures,
      ...(loaded.signatures || {}),
      hod: {
        ...base.signatures.hod,
        ...(loaded.signatures?.hod || {})
      }
    }
  };
}
async function uploadLogo(file) {
  const root = (window.prAppData?.root || '/wp-json').replace(/\/$/, '');
  const form = new FormData();
  form.append('file', file);
  const response = await fetch(`${root}/wp/v2/media`, {
    method: 'POST',
    headers: {
      'X-WP-Nonce': window.prAppData?.nonce || ''
    },
    body: form
  });
  if (!response.ok) {
    throw new Error('Logo upload failed.');
  }
  const data = await response.json();
  return {
    id: data.id,
    url: data.source_url || data.guid?.rendered || ''
  };
}
function PanelReportSettings() {
  const {
    id
  } = (0,react_router_dom__WEBPACK_IMPORTED_MODULE_1__.useParams)();
  const sessionId = parseInt(id, 10);
  const [settings, setSettings] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(defaultSettings);
  const [settingsFrozen, setSettingsFrozen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [logoPreview, setLogoPreview] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)('');
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(true);
  const [saving, setSaving] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [freezing, setFreezing] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [freezeOpen, setFreezeOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [unfreezeOpen, setUnfreezeOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const [notice, setNotice] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(null);
  const frozen = settingsFrozen || settings?.settings_frozen;
  const load = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(async () => {
    if (!sessionId) {
      return;
    }
    setLoading(true);
    try {
      const data = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.get)(`sessions/${sessionId}/panel-report-settings`);
      const merged = mergeSettings(data?.panel_report_pdf);
      setSettings(merged);
      setSettingsFrozen(Boolean(data?.settings_frozen || merged?.settings_frozen));
      const logoBlock = merged.letterhead?.blocks?.find(b => b.type === 'image');
      if (logoBlock?.attachment_id) {
        try {
          const root = (window.prAppData?.root || '/wp-json').replace(/\/$/, '');
          const media = await fetch(`${root}/wp/v2/media/${logoBlock.attachment_id}`, {
            headers: {
              'X-WP-Nonce': window.prAppData?.nonce || ''
            }
          });
          if (media.ok) {
            const json = await media.json();
            setLogoPreview(json.source_url || '');
          }
        } catch {
          setLogoPreview('');
        }
      } else {
        setLogoPreview('');
      }
    } catch {
      setNotice({
        type: 'error',
        message: 'Could not load panel report settings.'
      });
    } finally {
      setLoading(false);
    }
  }, [sessionId]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    load();
  }, [load]);
  const update = (path, value) => {
    setSettings(prev => {
      const next = {
        ...prev
      };
      const keys = path.split('.');
      let cursor = next;
      for (let i = 0; i < keys.length - 1; i += 1) {
        cursor[keys[i]] = {
          ...cursor[keys[i]]
        };
        cursor = cursor[keys[i]];
      }
      cursor[keys[keys.length - 1]] = value;
      return next;
    });
  };
  const updateLetterheadText = (index, field, value) => {
    setSettings(prev => {
      const blocks = [...(prev.letterhead?.blocks || [])];
      let target = 0;
      let seen = -1;
      for (let i = 0; i < blocks.length; i += 1) {
        if (blocks[i].type === 'text') {
          seen += 1;
          if (seen === index) {
            target = i;
            break;
          }
        }
      }
      if (seen < index) {
        target = blocks.length;
        blocks.push({
          type: 'text',
          value: '',
          style: 'body',
          label: ''
        });
      }
      blocks[target] = {
        ...blocks[target],
        [field]: value
      };
      return {
        ...prev,
        letterhead: {
          ...prev.letterhead,
          blocks
        }
      };
    });
  };
  const logoBlock = (settings.letterhead?.blocks || []).find(b => b.type === 'image') || {
    attachment_id: 0,
    width_in: 4
  };
  const handleLogoChange = async event => {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }
    try {
      const uploaded = await uploadLogo(file);
      setLogoPreview(uploaded.url);
      setSettings(prev => {
        const blocks = [...(prev.letterhead?.blocks || [])];
        const imageIndex = blocks.findIndex(b => b.type === 'image');
        const imageBlock = {
          type: 'image',
          attachment_id: uploaded.id,
          width_in: logoBlock.width_in || 4,
          align: 'center'
        };
        if (imageIndex >= 0) {
          blocks[imageIndex] = imageBlock;
        } else {
          blocks.unshift(imageBlock);
        }
        return {
          ...prev,
          letterhead: {
            blocks
          }
        };
      });
    } catch {
      setNotice({
        type: 'error',
        message: 'Could not upload logo.'
      });
    }
    event.target.value = '';
  };
  const handleLogoWidthChange = event => {
    const width = parseFloat(event.target.value) || 4;
    setSettings(prev => {
      const blocks = [...(prev.letterhead?.blocks || [])];
      const idx = blocks.findIndex(b => b.type === 'image');
      if (idx >= 0) {
        blocks[idx] = {
          ...blocks[idx],
          width_in: width
        };
      }
      return {
        ...prev,
        letterhead: {
          blocks
        }
      };
    });
  };
  const trimReportMeta = s => ({
    ...s,
    report: {
      ...s.report,
      program_name: (s.report?.program_name || '').trim(),
      semester: (s.report?.semester || '').trim()
    }
  });
  const handleSave = async () => {
    if (frozen) {
      return;
    }
    setSaving(true);
    setNotice(null);
    const payload = trimReportMeta(settings);
    setSettings(payload);
    try {
      await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.put)(`sessions/${sessionId}/panel-report-settings`, {
        panel_report_pdf: payload
      });
      setNotice({
        type: 'success',
        message: 'Panel report settings saved.'
      });
    } catch {
      setNotice({
        type: 'error',
        message: 'Could not save settings.'
      });
    } finally {
      setSaving(false);
    }
  };
  const handleFreezeSettings = async () => {
    setFreezing(true);
    setNotice(null);
    const payload = trimReportMeta(settings);
    setSettings(payload);
    try {
      const data = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.post)(`sessions/${sessionId}/panel-report-settings/freeze`, {
        panel_report_pdf: payload
      });
      setSettingsFrozen(true);
      if (data?.panel_report_pdf) {
        setSettings(mergeSettings(data.panel_report_pdf));
      }
      setFreezeOpen(false);
      setNotice({
        type: 'success',
        message: 'Panel report settings saved and frozen. Panel coordinators can download the PDF.'
      });
    } catch {
      setNotice({
        type: 'error',
        message: 'Could not save and freeze settings.'
      });
    } finally {
      setFreezing(false);
    }
  };
  const handleUnfreezeSettings = async () => {
    setFreezing(true);
    setNotice(null);
    try {
      const data = await (0,_shared_api__WEBPACK_IMPORTED_MODULE_2__.post)(`sessions/${sessionId}/panel-report-settings/unfreeze`, {});
      setSettingsFrozen(false);
      if (data?.panel_report_pdf) {
        setSettings(mergeSettings(data.panel_report_pdf));
      }
      setUnfreezeOpen(false);
      setNotice({
        type: 'success',
        message: 'Panel report settings unlocked for editing.'
      });
    } catch {
      setNotice({
        type: 'error',
        message: 'Could not unfreeze settings.'
      });
    } finally {
      setFreezing(false);
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
    className: "min-w-0 max-w-full",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.PageHeader, {
      title: "Panel Report",
      description: "Configure the Review Report PDF for this project. Edit the document preview below; freeze settings when ready so panel coordinators can download the official PDF."
    }), loading ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ContentLoadingRegion, {
      busy: true,
      variant: "inline",
      label: "Loading panel report settings",
      className: "mt-6",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.PageContentSkeleton, {
        rows: 5
      })
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.Fragment, {
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("section", {
        className: "mb-6 rounded-lg border border-border bg-surface p-4",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
          className: "flex flex-wrap items-center justify-between gap-3",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("h2", {
              className: "text-sm font-semibold text-text",
              children: "Settings lock"
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
              className: "mt-1 text-sm text-text-muted",
              children: frozen ? 'Settings are frozen. Panel coordinators can download the Review Report PDF.' : 'While unlocked, you can edit the template. Freeze when ready to allow PDF downloads.'
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
            className: "flex flex-wrap items-center gap-2",
            children: [frozen ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.StatusChip, {
              variant: "confirmed",
              label: "Settings frozen",
              icon: "lock"
            }) : null, frozen ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
              type: "button",
              variant: "secondary",
              icon: "unlock",
              disabled: freezing,
              onClick: () => setUnfreezeOpen(true),
              children: "Unfreeze settings"
            }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
              type: "button",
              variant: "primary",
              icon: "lock",
              disabled: freezing || saving,
              onClick: () => setFreezeOpen(true),
              children: "Freeze settings"
            })]
          })]
        })
      }), notice ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Notice, {
        variant: notice.type === 'error' ? 'error' : 'success',
        className: "mb-6",
        children: notice.message
      }) : null, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("fieldset", {
        disabled: frozen,
        className: "min-w-0 max-w-full disabled:opacity-75",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_components_PanelReportSettingsPreview__WEBPACK_IMPORTED_MODULE_4__.PanelReportSettingsPreview, {
          settings: settings,
          logoPreview: logoPreview,
          logoBlock: logoBlock,
          disabled: frozen,
          onUpdate: update,
          onLetterheadText: updateLetterheadText,
          onLogoChange: handleLogoChange,
          onLogoWidthChange: handleLogoWidthChange
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
        className: "mt-6 flex justify-end",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.Button, {
          onClick: handleSave,
          disabled: saving || frozen,
          children: saving ? 'Saving…' : 'Save settings'
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
        open: freezeOpen,
        title: "Freeze panel report settings?",
        confirmLabel: freezing ? 'Freezing…' : 'Freeze settings',
        confirmDisabled: freezing,
        onConfirm: handleFreezeSettings,
        onCancel: () => setFreezeOpen(false),
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          className: "text-sm text-text-muted",
          children: "Your current template changes will be saved, then settings will be frozen. After freezing, these settings cannot be edited until you unfreeze them. Panel coordinators can download the Review Report PDF only while settings are frozen."
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_shared_components__WEBPACK_IMPORTED_MODULE_3__.ConfirmDialog, {
        open: unfreezeOpen,
        title: "Unfreeze panel report settings?",
        confirmLabel: freezing ? 'Unfreezing…' : 'Unfreeze settings',
        confirmDisabled: freezing,
        onConfirm: handleUnfreezeSettings,
        onCancel: () => setUnfreezeOpen(false),
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("p", {
          className: "text-sm text-text-muted",
          children: "Unfreezing allows you to edit the template again. PDF download will be disabled for panel coordinators until you freeze settings again."
        })
      })]
    })]
  });
}

/***/ }

}]);
//# sourceMappingURL=src_coordinator_pages_PanelReportSettings_jsx.js.map?ver=43fdade97c76828a37cb