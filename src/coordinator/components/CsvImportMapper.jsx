import { useCallback, useEffect, useMemo, useRef, useState } from '@wordpress/element';
import { post } from '../../shared/api';
import { parseApiErrorMessage } from '../../shared/apiErrors';
import { Button, Notice } from '../../shared/components';
import { findReviewerEmailPanelConflicts } from '../../shared/reviewerImportRows';
import { TableScrollWrapper } from '../../shared/TableScrollViewport';
import { TABLE_BODY_ROW_SOFT } from '../../shared/tableStyles';

const STORAGE_PREFIX = 'pr_csv_mapping_';
const STUDENT_REQUIRED = [
	{ key: 'reg_no', label: 'Registration number' },
	{ key: 'name', label: 'Name' },
];
const STUDENT_OPTIONAL = [
	{ key: 'program', label: 'Program' },
	{ key: 'batch', label: 'Batch' },
];

const IMPORT_TYPE_CONFIG = {
	students: {
		title: 'Import students from CSV',
		description: 'Map columns, preview the first three rows, then import.',
		required: STUDENT_REQUIRED,
		optional: STUDENT_OPTIONAL,
		submitLabel: 'Import students',
		supportsDuplicates: true,
	},
	'session-enrol': {
		title: 'Add Student from CSV',
		description:
			'Map registration numbers and panel columns. Include name for new students; program and batch are optional.',
		required: [
			{ key: 'reg_no', label: 'Registration number' },
			{ key: 'panel', label: 'Panel' },
		],
		optional: [
			{ key: 'name', label: 'Name' },
			{ key: 'program', label: 'Program' },
			{ key: 'batch', label: 'Batch' },
			{ key: 'project_title', label: 'Project title' },
			{ key: 'guide_emp_id', label: 'Guide employee ID' },
			{ key: 'guide_name', label: 'Guide name' },
		],
		submitLabel: 'Import students',
		supportsDuplicates: false,
	},
	'panel-reviewers': {
		title: 'Import panel reviewers from CSV',
		description:
			'Use the template (one row per panel with reviewer_1, reviewer_1_email, …) or long format: panel, reviewer name, and email per row.',
		required: [ { key: 'panel', label: 'Panel name or number' } ],
		optional: [
			{ key: 'reviewer_name', label: 'Reviewer name (long format)' },
			{ key: 'email', label: 'Email (long format)' },
			{ key: 'weight', label: 'Weight (long format)' },
			{
				key: 'panel_coordinator',
				label: 'Panel coordinator (1, yes, true)',
			},
		],
		submitLabel: 'Import reviewers',
		supportsDuplicates: false,
		supportsReplaceChoice: true,
		wideFormat: true,
	},
};

function parseCsv( text ) {
	const rawLines = text.split( /\r?\n/ );
	let headerLineIndex = -1;

	for ( let i = 0; i < rawLines.length; i++ ) {
		if ( rawLines[ i ].trim() !== '' ) {
			headerLineIndex = i;
			break;
		}
	}

	if ( headerLineIndex < 0 ) {
		return { headers: [], rows: [] };
	}

	const headers = rawLines[ headerLineIndex ]
		.split( ',' )
		.map( ( h ) => h.trim() );
	const rows = [];

	for ( let lineIndex = headerLineIndex + 1; lineIndex < rawLines.length; lineIndex++ ) {
		const line = rawLines[ lineIndex ].trim();
		if ( line === '' ) {
			continue;
		}
		const values = line.split( ',' ).map( ( v ) => v.trim() );
		const row = { _csv_row: lineIndex + 1 };
		headers.forEach( ( header, index ) => {
			row[ header ] = values[ index ] ?? '';
		} );
		rows.push( row );
	}

	return { headers, rows };
}

function formatCsvRowRefs( rowNumbers ) {
	return rowNumbers.map( ( row ) => `Row ${ row }` ).join( ', ' );
}

function loadMapping( importType ) {
	try {
		const raw = localStorage.getItem( `${ STORAGE_PREFIX }${ importType }` );
		return raw ? JSON.parse( raw ) : {};
	} catch {
		return {};
	}
}

function saveMapping( importType, mapping ) {
	localStorage.setItem(
		`${ STORAGE_PREFIX }${ importType }`,
		JSON.stringify( mapping )
	);
}

function applyMapping( rows, mapping, customFieldKeys ) {
	return rows.map( ( row ) => {
		const mapped = {};
		Object.entries( mapping ).forEach( ( [ target, source ] ) => {
			if ( source && row[ source ] !== undefined ) {
				mapped[ target ] = row[ source ];
			}
		} );
		customFieldKeys.forEach( ( key ) => {
			const source = mapping[ key ];
			if ( source && row[ source ] !== undefined ) {
				mapped[ key ] = row[ source ];
			}
		} );
		return mapped;
	} );
}

function hasWideReviewerColumns( headers ) {
	return headers.some( ( header ) =>
		/^reviewer_\d+$/i.test( header.trim().replace( /\s+/g, '_' ) )
	);
}

function findDuplicateRegNos( rows ) {
	const seen = new Set();
	const duplicates = new Set();
	rows.forEach( ( row ) => {
		const reg = ( row.reg_no ?? '' ).trim();
		if ( ! reg ) {
			return;
		}
		if ( seen.has( reg ) ) {
			duplicates.add( reg );
		}
		seen.add( reg );
	} );
	return [ ...duplicates ];
}

function findDuplicateFieldValues( rows, field ) {
	const seen = new Set();
	const duplicates = new Set();
	rows.forEach( ( row ) => {
		const value = ( row[ field ] ?? '' ).trim();
		if ( ! value ) {
			return;
		}
		const key = field === 'email' ? value.toLowerCase() : value;
		if ( seen.has( key ) ) {
			duplicates.add( value );
		}
		seen.add( key );
	} );
	return [ ...duplicates ];
}

export function CsvImportMapper( {
	importType = 'students',
	sessionId = null,
	customFields = [],
	existingReviewerCount = 0,
	onComplete,
	onImportSuccess = null,
	onDownloadTemplate = null,
	templateDownloadLabel = 'Download template CSV',
} ) {
	const typeConfig =
		IMPORT_TYPE_CONFIG[ importType ] ?? IMPORT_TYPE_CONFIG.students;
	const [ csvText, setCsvText ] = useState( '' );
	const [ mapping, setMapping ] = useState( () => loadMapping( importType ) );
	const [ duplicatePolicy, setDuplicatePolicy ] = useState( 'skip' );
	const [ showDuplicateChoice, setShowDuplicateChoice ] = useState( false );
	const [ importMode, setImportMode ] = useState( 'append' );
	const [ showReplaceChoice, setShowReplaceChoice ] = useState( false );
	const [ showReviewerConflictChoice, setShowReviewerConflictChoice ] =
		useState( false );
	const [ reviewerConflictsAcknowledged, setReviewerConflictsAcknowledged ] =
		useState( false );
	const [ pendingRows, setPendingRows ] = useState( [] );
	const [ importing, setImporting ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const fileInputRef = useRef( null );

	const resetImportForm = () => {
		setCsvText( '' );
		setPendingRows( [] );
		setShowDuplicateChoice( false );
		setShowReplaceChoice( false );
		setShowReviewerConflictChoice( false );
		setReviewerConflictsAcknowledged( false );
		if ( fileInputRef.current ) {
			fileInputRef.current.value = '';
		}
	};

	const { headers, rows } = useMemo( () => parseCsv( csvText ), [ csvText ] );
	const previewRows = rows.slice( 0, 3 );
	const wideReviewerFormat =
		importType === 'panel-reviewers' && hasWideReviewerColumns( headers );

	const targets = useMemo( () => {
		const base = [
			...typeConfig.required,
			...typeConfig.optional,
		];
		if ( importType !== 'students' ) {
			return base;
		}
		return [
			...base,
			...customFields.map( ( field ) => ( {
				key: field.field_key,
				label: field.label || field.field_key,
			} ) ),
		];
	}, [ customFields, importType, typeConfig ] );

	const customFieldKeys = useMemo(
		() => customFields.map( ( field ) => field.field_key ),
		[ customFields ]
	);

	useEffect( () => {
		if ( headers.length === 0 ) {
			return;
		}
		setMapping( ( current ) => {
			const next = { ...current };
			let changed = false;
			targets.forEach( ( target ) => {
				if ( next[ target.key ] ) {
					return;
				}
				if (
					importType === 'panel-reviewers' &&
					hasWideReviewerColumns( headers ) &&
					target.key !== 'panel'
				) {
					return;
				}
				const match = headers.find(
					( header ) =>
						header.toLowerCase().replace( /\s+/g, '_' ) ===
						target.key.toLowerCase()
				);
				if ( match ) {
					next[ target.key ] = match;
					changed = true;
				}
			} );
			return changed ? next : current;
		} );
	}, [ headers, targets, importType ] );

	const handleMappingChange = ( targetKey, sourceColumn ) => {
		const next = { ...mapping, [ targetKey ]: sourceColumn };
		setMapping( next );
		saveMapping( importType, next );
	};

	const handleFileChange = ( event ) => {
		const file = event.target.files?.[ 0 ];
		if ( ! file ) {
			return;
		}
		const reader = new FileReader();
		reader.onload = () => {
			setCsvText( String( reader.result ?? '' ) );
			setNotice( null );
			setShowDuplicateChoice( false );
			setShowReplaceChoice( false );
			setShowReviewerConflictChoice( false );
			setReviewerConflictsAcknowledged( false );
		};
		reader.readAsText( file );
	};

	const runImport = async ( mappedRows, policy ) => {
		setImporting( true );
		setNotice( null );
		try {
			let result;
			if ( importType === 'session-enrol' ) {
				if ( ! sessionId ) {
					throw new Error( 'Project id is required.' );
				}
				result = await post( `/sessions/${ sessionId }/enrol`, {
					rows: mappedRows,
				} );
			} else if ( importType === 'panel-reviewers' ) {
				if ( ! sessionId ) {
					throw new Error( 'Project id is required.' );
				}
				result = await post( `/sessions/${ sessionId }/reviewers/import`, {
					rows: mappedRows,
					import_mode: policy,
				} );
			} else {
				result = await post( '/students/import', {
					rows: mappedRows,
					duplicate_policy: policy,
				} );
			}

			let message;
			if ( importType === 'session-enrol' ) {
				message = `Enrolment import: ${ result.enrolled ?? 0 } added, ${ result.updated ?? 0 } updated, ${ result.failed ?? 0 } failed.`;
			} else if ( importType === 'panel-reviewers' ) {
				const cleared = result.cleared ?? 0;
				const clearedNote =
					cleared > 0 ? `, ${ cleared } removed` : '';
				message = `Reviewer import: ${ result.imported ?? 0 } added, ${ result.updated ?? 0 } updated${ clearedNote }, ${ result.failed ?? 0 } failed.`;
			} else {
				const imported = result.imported ?? 0;
				const updated = result.updated ?? 0;
				const skipped = result.skipped ?? 0;
				const failed = result.failed ?? 0;
				message = `Import complete: ${ imported } added, ${ updated } updated, ${ skipped } skipped, ${ failed } failed.`;
			}
			const failed = result.failed ?? 0;
			const variant = failed > 0 ? 'warning' : 'success';
			const successPayload = {
				variant,
				message,
				errorCsv: result.error_csv || '',
			};
			if ( onImportSuccess ) {
				onImportSuccess( successPayload );
			} else {
				setNotice( successPayload );
			}
			resetImportForm();
			onComplete?.( successPayload );
		} catch ( err ) {
			setNotice( {
				variant: 'error',
				message: parseApiErrorMessage(
					err,
					'Import failed. Check your file and try again.'
				),
			} );
		} finally {
			setImporting( false );
			setShowDuplicateChoice( false );
			setShowReplaceChoice( false );
			setShowReviewerConflictChoice( false );
		}
	};

	const mapRowsForImport = useCallback( () => {
		if ( wideReviewerFormat ) {
			return rows.map( ( row, index ) => {
				const panelColumn = mapping.panel;
				const panelValue =
					panelColumn && row[ panelColumn ] !== undefined
						? row[ panelColumn ]
						: row.panel ?? '';
				return {
					...row,
					panel: panelValue,
					_csv_row: row._csv_row ?? index + 2,
				};
			} );
		}

		return rows.map( ( row, index ) => ( {
			...row,
			...applyMapping( [ row ], mapping, customFieldKeys )[ 0 ],
			_csv_row: row._csv_row ?? index + 2,
		} ) );
	}, [ wideReviewerFormat, rows, mapping, customFieldKeys ] );

	const reviewerConflicts = useMemo( () => {
		if ( importType !== 'panel-reviewers' || rows.length === 0 ) {
			return [];
		}
		return findReviewerEmailPanelConflicts( mapRowsForImport() );
	}, [ importType, rows.length, mapRowsForImport ] );

	const proceedAfterReviewerConflictCheck = ( mappedRows ) => {
		let duplicates = [];
		if ( typeConfig.supportsDuplicates ) {
			duplicates = findDuplicateRegNos( mappedRows );
		}
		if ( duplicates.length > 0 && ! showDuplicateChoice ) {
			setPendingRows( mappedRows );
			setShowDuplicateChoice( true );
			return;
		}
		if (
			typeConfig.supportsReplaceChoice &&
			existingReviewerCount > 0 &&
			! showReplaceChoice
		) {
			setPendingRows( mappedRows );
			setShowReplaceChoice( true );
			return;
		}
		const reviewerPolicy =
			importType === 'panel-reviewers' ? importMode : duplicatePolicy;
		runImport( mappedRows, reviewerPolicy );
	};

	const handleSubmit = () => {
		const mappedRows = mapRowsForImport();
		if (
			importType === 'panel-reviewers' &&
			reviewerConflicts.length > 0 &&
			! showReviewerConflictChoice
		) {
			setPendingRows( mappedRows );
			setShowReviewerConflictChoice( true );
			return;
		}
		proceedAfterReviewerConflictCheck( mappedRows );
	};

	const handleConfirmReviewerConflicts = () => {
		setShowReviewerConflictChoice( false );
		setReviewerConflictsAcknowledged( true );
		proceedAfterReviewerConflictCheck( pendingRows );
	};

	const handleConfirmDuplicates = () => {
		if (
			importType === 'panel-reviewers' &&
			findReviewerEmailPanelConflicts( pendingRows ).length > 0 &&
			! showReviewerConflictChoice
		) {
			setShowReviewerConflictChoice( true );
			return;
		}
		if (
			typeConfig.supportsReplaceChoice &&
			existingReviewerCount > 0 &&
			! showReplaceChoice
		) {
			setShowReplaceChoice( true );
			return;
		}
		const reviewerPolicy =
			importType === 'panel-reviewers' ? importMode : duplicatePolicy;
		runImport( pendingRows, reviewerPolicy );
	};

	const handleConfirmReplaceChoice = () => {
		if (
			! reviewerConflictsAcknowledged &&
			findReviewerEmailPanelConflicts( pendingRows ).length > 0
		) {
			setShowReviewerConflictChoice( true );
			return;
		}
		runImport( pendingRows, importMode );
	};

	const downloadErrorCsv = () => {
		if ( ! notice?.errorCsv ) {
			return;
		}
		const blob = new Blob( [ notice.errorCsv ], {
			type: 'text/csv;charset=utf-8',
		} );
		const url = URL.createObjectURL( blob );
		const link = document.createElement( 'a' );
		link.href = url;
		link.download = 'import-errors.csv';
		link.click();
		URL.revokeObjectURL( url );
	};

	return (
		<div className="mt-6 rounded-md border border-border bg-surface-raised p-4 shadow-card">
			<h3 className="text-lg font-semibold text-text">{ typeConfig.title }</h3>
			<p className="mt-1 text-sm text-text-muted">{ typeConfig.description }</p>

			<div className="mt-4">
				<label
					className="block text-sm font-medium text-text"
					htmlFor={ `csv-file-${ importType }` }
				>
					CSV file
				</label>
				<input
					ref={ fileInputRef }
					id={ `csv-file-${ importType }` }
					type="file"
					accept=".csv,text/csv"
					onChange={ handleFileChange }
					className="mt-1 block w-full text-sm text-text"
				/>
				{ importType === 'students' &&
				window.prAppData?.studentImportTemplateUrl ? (
					<p className="mt-2 text-sm text-text-muted">
						<a
							href={ window.prAppData.studentImportTemplateUrl }
							download="students-import-template.csv"
							className="font-medium text-primary hover:underline"
						>
							Download template CSV
						</a>
						<span>
							{ ' ' }
							— sample reg. nos. like 25MDT1001 (academic year + programme)
						</span>
					</p>
				) : null }
				{ importType === 'session-enrol' &&
				window.prAppData?.sessionEnrolTemplateUrl ? (
					<p className="mt-2 text-sm text-text-muted">
						<a
							href={ window.prAppData.sessionEnrolTemplateUrl }
							download="session-enrol-template.csv"
							className="font-medium text-primary hover:underline"
						>
							Download enrol template CSV
						</a>
						<span> — reg. no, panel, and optional project title</span>
					</p>
				) : null }
				{ importType === 'panel-reviewers' ? (
					<p className="mt-2 text-sm text-text-muted">
						{ onDownloadTemplate ? (
							<button
								type="button"
								onClick={ onDownloadTemplate }
								className="font-medium text-primary hover:underline"
							>
								{ templateDownloadLabel }
							</button>
						) : window.prAppData?.reviewerImportTemplateUrl ? (
							<a
								href={ window.prAppData.reviewerImportTemplateUrl }
								download="reviewers-import-template.csv"
								className="font-medium text-primary hover:underline"
							>
								{ templateDownloadLabel }
							</a>
						) : null }
						<span>
							{ ' ' }
							— one row per panel; existing reviewers are prefilled when
							available.
						</span>
					</p>
				) : null }
			</div>

			{ headers.length > 0 ? (
				<>
					<div className="mt-4 grid gap-3 sm:grid-cols-2">
						{ targets.map( ( target ) => (
							<div key={ target.key }>
								<label
									className="block text-sm font-medium text-text"
									htmlFor={ `map-${ target.key }` }
								>
									{ target.label }
									{ typeConfig.required.some(
										( item ) => item.key === target.key
									) ? (
										<span className="text-danger"> *</span>
									) : null }
								</label>
								<select
									id={ `map-${ target.key }` }
									value={ mapping[ target.key ] ?? '' }
									onChange={ ( e ) =>
										handleMappingChange(
											target.key,
											e.target.value
										)
									}
									className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
								>
									<option value="">— Select column —</option>
									{ headers.map( ( header ) => (
										<option key={ header } value={ header }>
											{ header }
										</option>
									) ) }
								</select>
							</div>
						) ) }
					</div>

					{ previewRows.length > 0 ? (
						<div className="mt-4">
							<p className="mb-2 text-sm font-medium text-text">
								Preview (first 3 rows)
							</p>
							<TableScrollWrapper>
							<table className="min-w-full text-left text-sm">
								<thead>
									<tr className="border-b border-border text-text-muted">
										{ targets.map( ( target ) => (
											<th key={ target.key } className="px-2 py-1">
												{ target.label }
											</th>
										) ) }
									</tr>
								</thead>
								<tbody>
									{ previewRows.map( ( row, index ) => {
										const mapped = applyMapping(
											[ row ],
											mapping,
											customFieldKeys
										)[ 0 ];
										return (
											<tr
												key={ index }
												className={ TABLE_BODY_ROW_SOFT }
											>
												{ targets.map( ( target ) => (
													<td
														key={ target.key }
														className="px-2 py-1 text-text"
													>
														{ mapped[ target.key ] ?? '—' }
													</td>
												) ) }
											</tr>
										);
									} ) }
								</tbody>
							</table>
							</TableScrollWrapper>
						</div>
					) : null }

					{ reviewerConflicts.length > 0 &&
					! showReviewerConflictChoice ? (
						<div className="mt-4">
							<Notice variant="warning">
								<p className="font-medium">
									Repeated reviewer emails across panels
								</p>
								<p className="mt-1">
									Each reviewer can belong to only one panel per project.
									Fix the file or continue — only the first panel per email
									will import; later rows will fail.
								</p>
								<ul className="mt-2 list-inside list-disc text-sm">
									{ reviewerConflicts.map( ( conflict ) => (
										<li key={ conflict.email }>
											{ conflict.name
												? `${ conflict.name } (${ conflict.email })`
												: conflict.email }
											: { ' ' }
											{ conflict.panels.join( ', ' ) }
											<span className="text-text-muted">
												{ ' ' }
												({ formatCsvRowRefs( conflict.rows ) })
											</span>
										</li>
									) ) }
								</ul>
							</Notice>
						</div>
					) : null }

					{ showReviewerConflictChoice ? (
						<div className="mt-4 rounded-md border border-warning/40 bg-warning/10 p-4">
							<p className="text-sm font-medium text-text">
								Repeated reviewer emails across panels
							</p>
							<p className="mt-1 text-sm text-text">
								The same email appears on more than one panel in this file.
								Each reviewer can belong to only one panel per project. If you
								continue, only the first assignment per email is imported;
								later rows are reported as failed.
							</p>
							<ul className="mt-3 list-inside list-disc text-sm text-text">
								{ reviewerConflicts.map( ( conflict ) => (
									<li key={ conflict.email }>
										{ conflict.name
											? `${ conflict.name } (${ conflict.email })`
											: conflict.email }
										: { ' ' }
										{ conflict.panels.join( ', ' )}
										<span className="text-text-muted">
											{ ' ' }
											({ formatCsvRowRefs( conflict.rows ) })
										</span>
									</li>
								) ) }
							</ul>
							<div className="mt-3 flex gap-2">
								<Button
									variant="primary"
									loading={ importing }
									onClick={ handleConfirmReviewerConflicts }
								>
									Continue import anyway
								</Button>
								<Button
									variant="secondary"
									onClick={ () => setShowReviewerConflictChoice( false ) }
								>
									Cancel
								</Button>
							</div>
						</div>
					) : showReplaceChoice ? (
						<div className="mt-4 rounded-md border border-warning/40 bg-warning/10 p-4">
							<p className="text-sm text-text">
								This project already has { existingReviewerCount } reviewer
								{ existingReviewerCount === 1 ? '' : 's' }. Choose whether to
								replace the full roster or add and update from this file only.
							</p>
							<fieldset className="mt-3 space-y-2">
								<label className="flex items-center gap-2 text-sm">
									<input
										type="radio"
										name="reviewer-import-mode"
										value="append"
										checked={ importMode === 'append' }
										onChange={ () => setImportMode( 'append' ) }
									/>
									Keep existing reviewers and append or update from CSV
								</label>
								<label className="flex items-center gap-2 text-sm">
									<input
										type="radio"
										name="reviewer-import-mode"
										value="replace"
										checked={ importMode === 'replace' }
										onChange={ () => setImportMode( 'replace' ) }
									/>
									Clear all existing reviewers and import only this file
								</label>
							</fieldset>
							<div className="mt-3 flex gap-2">
								<Button
									variant="primary"
									loading={ importing }
									onClick={ handleConfirmReplaceChoice }
								>
									Continue import
								</Button>
								<Button
									variant="secondary"
									onClick={ () => setShowReplaceChoice( false ) }
								>
									Cancel
								</Button>
							</div>
						</div>
					) : showDuplicateChoice ? (
						<div className="mt-4 rounded-md border border-warning/40 bg-warning/10 p-4">
							<p className="text-sm text-text">
								Duplicate registration numbers were found in this file.
								Choose how to handle rows that already exist in the registry.
							</p>
							<fieldset className="mt-3 space-y-2">
								<label className="flex items-center gap-2 text-sm">
									<input
										type="radio"
										name="duplicate-policy"
										value="skip"
										checked={ duplicatePolicy === 'skip' }
										onChange={ () => setDuplicatePolicy( 'skip' ) }
									/>
									Skip existing students
								</label>
								<label className="flex items-center gap-2 text-sm">
									<input
										type="radio"
										name="duplicate-policy"
										value="update"
										checked={ duplicatePolicy === 'update' }
										onChange={ () =>
											setDuplicatePolicy( 'update' )
										}
									/>
									Update existing students
								</label>
							</fieldset>
							<div className="mt-3 flex gap-2">
								<Button
									variant="primary"
									loading={ importing }
									onClick={ handleConfirmDuplicates }
								>
									Continue import
								</Button>
								<Button
									variant="secondary"
									onClick={ () => setShowDuplicateChoice( false ) }
								>
									Cancel
								</Button>
							</div>
						</div>
					) : (
						<div className="mt-4">
							<Button
								variant="primary"
								loading={ importing }
								onClick={ handleSubmit }
								disabled={
									rows.length === 0 ||
									( importType === 'panel-reviewers'
										? ! mapping.panel &&
										  ! headers.some( ( header ) =>
												/^reviewer_\d+$/i.test(
													header
														.trim()
														.replace( /\s+/g, '_' )
												)
										  )
										: typeConfig.required.some(
												( item ) => ! mapping[ item.key ]
										  ) )
								}
							>
								{ typeConfig.submitLabel }
							</Button>
						</div>
					) }
				</>
			) : null }

			{ notice ? (
				<div className="mt-4 space-y-2">
					<Notice variant={ notice.variant } onDismiss={ () => setNotice( null ) }>
						{ notice.message }
					</Notice>
					{ notice.errorCsv ? (
						<Button variant="secondary" size="sm" onClick={ downloadErrorCsv }>
							Download error CSV
						</Button>
					) : null }
				</div>
			) : null }
		</div>
	);
}
