import { TABLE_SCROLL_INNER, TABLE_SCROLL_WRAPPER } from '../../shared/tableStyles';
import { PANEL_REPORT_PREVIEW_FIXTURE } from './panelReportPreviewFixture';
import {
	TABLE_COLUMNS,
	buildPreviewScoreColumns,
	formatReviewerHeader,
	previewScoreRowCells,
} from './panelReportTableConfig';

function RegionCaption( { id, children } ) {
	return (
		<>
			<span className="sr-only" id={ id }>
				{ children }
			</span>
			<p
				className="mb-2 font-sans text-[10px] font-medium uppercase tracking-wide text-text-muted"
				aria-hidden="true"
			>
				{ children }
			</p>
		</>
	);
}

function letterheadClassForIndex( index ) {
	if ( index === 0 ) {
		return 'letterhead-title';
	}
	if ( index === 1 ) {
		return 'letterhead-subtitle';
	}
	return 'letterhead-body';
}

function letterheadLabelForIndex( index, optional = false ) {
	if ( index === 0 ) {
		return optional ? 'Department (optional)' : 'Department';
	}
	if ( index === 1 ) {
		return optional ? 'School (optional)' : 'School';
	}
	return optional ? `Line ${ index + 1 } (optional)` : `Line ${ index + 1 }`;
}

export function PanelReportSettingsPreview( {
	settings,
	logoPreview,
	logoBlock,
	disabled,
	onUpdate,
	onLetterheadText,
	onLogoChange,
	onLogoWidthChange,
} ) {
	const report = settings?.report || {};
	const table = settings?.table || {};
	const footer = settings?.footer || {};
	const signatures = settings?.signatures || {};
	const hod = signatures?.hod || {};
	const fixture = PANEL_REPORT_PREVIEW_FIXTURE;
	const textBlocks = ( settings?.letterhead?.blocks || [] ).filter(
		( b ) => b.type === 'text'
	);
	const columns = buildPreviewScoreColumns( table, fixture.reviewers );
	const widthIn = logoBlock?.width_in ?? 4;

	const program = ( report.program_name || '' ).trim();
	const semester = ( report.semester || '' ).trim();
	const metaRows = [
		{
			cells: [
				{ label: 'Program Name', value: program, editable: 'report.program_name' },
				{ label: 'Semester', value: semester, editable: 'report.semester' },
			],
		},
	];

	const detailCells = [];
	if ( report.show_review_number !== false ) {
		detailCells.push( { label: 'Review Number', value: fixture.review_label } );
	}
	if ( report.show_panel_name !== false ) {
		detailCells.push( { label: 'Panel Name', value: fixture.panel_name } );
	}
	if ( detailCells.length ) {
		metaRows.push( { cells: detailCells } );
	}
	if ( report.show_reviewers_list !== false ) {
		const reviewerNamesLine = fixture.reviewers
			.map( ( reviewer ) => reviewer.name )
			.join( ', ' );
		metaRows.push( {
			fullWidth: true,
			cells: [ { label: 'Reviewers', value: reviewerNamesLine } ],
		} );
	}

	const showLegend = table.show_reviewer_legend !== false;
	const sigPattern = signatures.reviewer_label_pattern || 'Reviewer {n}';
	const legendParts = fixture.reviewers.map( ( reviewer ) => {
		const short = formatReviewerHeader(
			table.reviewer_header_pattern || 'R{n}',
			reviewer.ordinal
		);
		const long = formatReviewerHeader( sigPattern, reviewer.ordinal );
		return `${ short } = ${ long } (${ reviewer.name })`;
	} );

	const signatureLines = fixture.reviewers.map( ( reviewer, index ) => ( {
		label: formatReviewerHeader( sigPattern, reviewer.ordinal ),
		name: reviewer.name,
		key: `rev-${ index }`,
	} ) );

	if ( signatures.show_panel_coordinator_line !== false ) {
		signatureLines.push( {
			label: 'Panel Coordinator',
			name: '',
			key: 'panel-coordinator',
		} );
	}

	const hodCaption =
		( hod.name || '' ).trim() !== ''
			? `${ hod.label || 'Head of the Department' }: ${ hod.name }`
			: hod.label || 'Head of the Department';

	return (
		<div className="min-w-0 w-full max-w-full">
			<div className="mx-auto min-w-0 w-full max-w-[210mm] rounded-md border border-border bg-white p-4 shadow-sm sm:p-6 lg:max-w-3xl">
				<div
					className="pr-panel-report-preview min-w-0"
					aria-labelledby="panel-report-preview-heading"
				>
					<h2 id="panel-report-preview-heading" className="sr-only">
						Review Report layout preview
					</h2>

					<section
						className="pr-preview-region letterhead"
						aria-labelledby="region-letterhead"
					>
						<RegionCaption id="region-letterhead">Letterhead</RegionCaption>
						{ logoPreview ? (
							<img
								src={ logoPreview }
								alt=""
								style={ {
									width: `${ widthIn }in`,
									maxWidth: '100%',
									height: 'auto',
								} }
							/>
						) : (
							<div
								className="mx-auto mb-2 flex h-16 w-40 items-center justify-center border border-dashed border-black/30 bg-[#fafafa] text-[10px] text-black/50"
								aria-hidden="true"
							>
								Logo
							</div>
						) }
						<div className="pr-preview-logo-controls max-w-full">
							<label className="block max-w-full">
								<span className="sr-only">Upload logo</span>
								<input
									type="file"
									accept="image/*"
									className="max-w-full text-xs"
									disabled={ disabled }
									onChange={ onLogoChange }
								/>
							</label>
							<label className="mt-2 inline-flex items-center gap-2">
								Width (inches)
								<input
									type="number"
									min="0.5"
									max="8"
									step="0.1"
									className="w-16 rounded border border-border px-2 py-1"
									value={ widthIn }
									disabled={ disabled }
									onChange={ onLogoWidthChange }
								/>
							</label>
							{ textBlocks.map( ( block, index ) => {
								const hasValue = ( block.value || '' ).trim() !== '';
								if ( hasValue ) {
									return null;
								}
								return (
									<label
										key={ `lh-empty-${ index }` }
										className="mt-2 block text-left"
									>
										{ letterheadLabelForIndex( index, true ) }
										<input
											type="text"
											className="mt-1 w-full max-w-xs rounded border border-border px-2 py-1"
											placeholder={ `Add ${ letterheadLabelForIndex( index ).toLowerCase() }` }
											value={ block.value || '' }
											disabled={ disabled }
											onChange={ ( e ) =>
												onLetterheadText( index, 'value', e.target.value )
											}
										/>
									</label>
								);
							} ) }
						</div>
						{ textBlocks.map( ( block, index ) => {
							const hasValue = ( block.value || '' ).trim() !== '';
							if ( ! hasValue ) {
								return null;
							}
							return (
								<div
									key={ `lh-text-${ index }` }
									className={ letterheadClassForIndex( index ) }
								>
									<label className="sr-only">
										{ letterheadLabelForIndex( index ) }
									</label>
									<input
										type="text"
										className="pr-preview-inline-input text-center"
										value={ block.value || '' }
										disabled={ disabled }
										onChange={ ( e ) =>
											onLetterheadText( index, 'value', e.target.value )
										}
									/>
								</div>
							);
						} ) }
					</section>

					<section
						className="pr-preview-region"
						aria-labelledby="region-report-details"
					>
						<RegionCaption id="region-report-details">Report details</RegionCaption>
						<div className="report-title">
							<label className="sr-only">Report title</label>
							<input
								type="text"
								className="pr-preview-inline-input text-center font-bold"
								value={ report.title || 'Review Report' }
								disabled={ disabled }
								onChange={ ( e ) => onUpdate( 'report.title', e.target.value ) }
							/>
						</div>
						{ metaRows.length > 0 ? (
							<div className={ `${ TABLE_SCROLL_WRAPPER } mb-4` }>
								<table className={ `meta-table ${ TABLE_SCROLL_INNER }` }>
								<tbody>
									{ metaRows.map( ( row, rowIndex ) => (
										<tr key={ `meta-${ rowIndex }` }>
											{ row.fullWidth ? (
												<>
													<th>{ row.cells[ 0 ].label }</th>
													<td colSpan={ 3 }>{ row.cells[ 0 ].value }</td>
												</>
											) : (
												<>
													{ row.cells.map( ( cell, cellIndex ) => (
														<MetaCell
															key={ `${ cell.label }-${ cellIndex }` }
															cell={ cell }
															disabled={ disabled }
															onUpdate={ onUpdate }
														/>
													) ) }
													{ row.cells.length === 1 ? (
														<>
															<th />
															<td />
														</>
													) : null }
												</>
											) }
										</tr>
									) ) }
								</tbody>
								</table>
							</div>
						) : null }
					</section>

					<section
						className="pr-preview-region"
						aria-labelledby="region-scores-table"
					>
						<RegionCaption id="region-scores-table">Scores table</RegionCaption>
						<div
							className={ TABLE_SCROLL_WRAPPER }
							data-testid="panel-report-preview-scroll"
						>
							<table className={ `scores ${ TABLE_SCROLL_INNER }` }>
							<thead>
								<tr>
									{ TABLE_COLUMNS.map( ( column ) => {
										const enabled =
											column.alwaysOn || table[ column.showKey ] !== false;
										if ( ! enabled ) {
											return null;
										}
										return (
											<th
												key={ column.showKey }
												className={
													column.shrink ? 'col-shrink col-wrap' : 'col-wrap'
												}
											>
												<div className="pr-preview-th-control">
													{ ! column.alwaysOn ? (
														<label>
															<input
																type="checkbox"
																checked
																disabled={ disabled }
																onChange={ ( e ) =>
																	onUpdate(
																		`table.${ column.showKey }`,
																		e.target.checked
																	)
																}
															/>
															<span>{ column.name }</span>
														</label>
													) : null }
													<input
														type="text"
														className="pr-preview-inline-input"
														value={
															table[ column.labelKey ] ??
															column.defaultLabel
														}
														disabled={ disabled }
														onChange={ ( e ) =>
															onUpdate(
																`table.${ column.labelKey }`,
																e.target.value
															)
														}
													/>
												</div>
											</th>
										);
									} ) }
									{ fixture.reviewers.map( ( reviewer ) => (
										<th
											key={ `rev-h-${ reviewer.ordinal }` }
											className="col-reviewer col-shrink"
										>
											{ formatReviewerHeader(
												table.reviewer_header_pattern || 'R{n}',
												reviewer.ordinal
											) }
										</th>
									) ) }
									<th className="col-final col-shrink">
										<div className="pr-preview-th-control">
											<input
												type="text"
												className="pr-preview-inline-input"
												value={
													table.final_marks_column_header || 'Final Marks'
												}
												disabled={ disabled }
												onChange={ ( e ) =>
													onUpdate(
														'table.final_marks_column_header',
														e.target.value
													)
												}
											/>
										</div>
									</th>
								</tr>
							</thead>
							<tbody>
								{ fixture.students.map( ( student ) => {
									const cells = previewScoreRowCells( student, columns );
									return (
										<tr key={ student.reg_no }>
											{ cells.map( ( cell, cellIndex ) => {
												const column = columns[ cellIndex ];
												const cls = column.shrink
													? 'col-shrink col-wrap'
													: 'col-wrap';
												const extra = column.className || '';
												return (
													<td
														key={ cellIndex }
														className={ `${ cls } ${ extra }`.trim() }
													>
														{ cell }
													</td>
												);
											} ) }
										</tr>
									);
								} ) }
							</tbody>
						</table>
						</div>
						<label className="mb-3 flex items-center gap-2 font-sans text-xs text-text">
							<input
								type="checkbox"
								checked={ showLegend }
								disabled={ disabled }
								onChange={ ( e ) =>
									onUpdate( 'table.show_reviewer_legend', e.target.checked )
								}
							/>
							Show reviewer legend below table
						</label>
						{ showLegend ? (
							<p className="reviewer-legend">{ legendParts.join( '; ' ) }</p>
						) : null }
						<div className="font-sans text-xs text-text-muted">
							<label className="block">
								Reviewer header pattern (use {'{n}'} for number)
								<input
									type="text"
									className="mt-1 w-full max-w-xs rounded border border-border px-2 py-1 text-sm"
									placeholder="R{n}"
									value={ table.reviewer_header_pattern || 'R{n}' }
									disabled={ disabled }
									onChange={ ( e ) =>
										onUpdate(
											'table.reviewer_header_pattern',
											e.target.value
										)
									}
								/>
							</label>
							<p className="mt-1 max-w-md">
								Short column headers in the scores table. Examples:{' '}
								<code className="text-text">R{'{n}'}</code> → R1, R2;{' '}
								<code className="text-text">Rev {'{n}'}</code> → Rev 1, Rev 2;{' '}
								<code className="text-text">#{ '{n}' }</code> → #1, #2. Pattern
								must include {'{n}'}.
								{ table.reviewer_header_pattern ? (
									<>
										{' '}
										Preview:{' '}
										{ fixture.reviewers
											.map( ( r ) =>
												formatReviewerHeader(
													table.reviewer_header_pattern || 'R{n}',
													r.ordinal
												)
											)
											.join( ', ' ) }
									</>
								) : null }
							</p>
							<label className="mt-2 block">
								Project title field key
								<input
									type="text"
									className="mt-1 w-full max-w-xs rounded border border-border px-2 py-1 text-sm"
									placeholder="project_title"
									value={ table.project_title_field_key || 'project_title' }
									disabled={ disabled }
									onChange={ ( e ) =>
										onUpdate(
											'table.project_title_field_key',
											e.target.value
										)
									}
								/>
							</label>
							<p className="mt-1 max-w-md">
								Registry custom-field key used in the PDF when a student has no
								per-review project title on their assignment. Default{' '}
								<code className="text-text">project_title</code> matches the
								Student Registry field.
							</p>
						</div>
						{ TABLE_COLUMNS.filter(
							( c ) => ! c.alwaysOn && table[ c.showKey ] === false
						).map( ( column ) => (
							<label
								key={ `off-${ column.showKey }` }
								className="mt-2 flex items-center gap-2 font-sans text-xs"
							>
								<input
									type="checkbox"
									checked={ false }
									disabled={ disabled }
									onChange={ ( e ) =>
										onUpdate( `table.${ column.showKey }`, e.target.checked )
									}
								/>
								Show { column.name } column
							</label>
						) ) }
					</section>

					<section
						className="pr-preview-region sig-section"
						aria-labelledby="region-signatures"
					>
						<RegionCaption id="region-signatures">Signatures</RegionCaption>
						<div className="sig-heading">Signatures with date</div>
						<div className="sig-layout">
							<div className="sig-left">
								{ signatureLines.map( ( line ) => (
									<div key={ line.key } className="sig-row">
										<span className="sig-line" aria-hidden="true" />
										<span className="sig-label">{ line.label }</span>
										{ line.name ? (
											<span className="sig-label" style={ { fontWeight: 400 } }>
												{ line.name }
											</span>
										) : null }
									</div>
								) ) }
								<label className="mt-2 flex items-center gap-2 font-sans text-xs">
									<input
										type="checkbox"
										checked={ signatures.show_panel_coordinator_line !== false }
										disabled={ disabled }
										onChange={ ( e ) =>
											onUpdate(
												'signatures.show_panel_coordinator_line',
												e.target.checked
											)
										}
									/>
									Show panel coordinator line when not in roster
								</label>
							</div>
							<div className="sig-right">
								{ hod.enabled !== false ? (
									<div className="sig-row">
										<span className="sig-line" aria-hidden="true" />
										<span className="sig-label">
											<input
												type="text"
												className="pr-preview-inline-input text-right"
												placeholder="HoD label"
												value={ hod.label || '' }
												disabled={ disabled }
												onChange={ ( e ) =>
													onUpdate( 'signatures.hod.label', e.target.value )
												}
											/>
										</span>
										<input
											type="text"
											className="pr-preview-inline-input mt-1 text-right"
											placeholder="HoD name"
											value={ hod.name || '' }
											disabled={ disabled }
											onChange={ ( e ) =>
												onUpdate( 'signatures.hod.name', e.target.value )
											}
										/>
										<span className="sr-only">Preview: { hodCaption }</span>
									</div>
								) : null }
							</div>
						</div>
						<div className="pr-preview-footer-note">
							<label className="flex items-center gap-2">
								<input
									type="checkbox"
									checked={ footer.show_generated_datetime !== false }
									disabled={ disabled }
									onChange={ ( e ) =>
										onUpdate(
											'footer.show_generated_datetime',
											e.target.checked
										)
									}
								/>
								Show generated date &amp; time on each page (bottom left)
							</label>
							{ footer.show_generated_datetime !== false ? (
								<p className="mt-1 italic">
									Report generated: { new Date().toLocaleString() } (sample)
								</p>
							) : null }
						</div>
					</section>
				</div>
			</div>
		</div>
	);
}

function MetaCell( { cell, disabled, onUpdate } ) {
	if ( cell.editable ) {
		return (
			<>
				<th>{ cell.label }</th>
				<td>
					<input
						type="text"
						className="pr-preview-inline-input"
						value={ cell.value }
						placeholder={ cell.label }
						disabled={ disabled }
						onChange={ ( e ) => onUpdate( cell.editable, e.target.value ) }
					/>
				</td>
			</>
		);
	}
	return (
		<>
			<th>{ cell.label }</th>
			<td>{ cell.value }</td>
		</>
	);
}
