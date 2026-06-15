import { useCallback, useEffect, useState } from '@wordpress/element';
import { useParams } from 'react-router-dom';
import { get, post, put } from '../../shared/api';
import {
	Button,
	ConfirmDialog,
	ContentLoadingRegion,
	PageContentSkeleton,
	PageHeader,
	StatusChip,
	useToast,
} from '../../shared/components';
import { PanelReportSettingsPreview } from '../components/PanelReportSettingsPreview';

const DEFAULT_TEXT_BLOCKS = [
	{ type: 'text', value: '', style: 'title', label: '' },
	{ type: 'text', value: '', style: 'subtitle', label: '' },
];

function defaultSettings() {
	return {
		letterhead: {
			blocks: [
				{ type: 'image', attachment_id: 0, width_in: 4, align: 'center' },
				...DEFAULT_TEXT_BLOCKS.map( ( block ) => ( { ...block } ) ),
			],
		},
		report: {
			title: 'Review Report',
			program_name: '',
			semester: '',
			show_review_number: true,
			show_panel_name: true,
			show_reviewers_list: true,
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
			show_reviewer_legend: true,
		},
		footer: {
			show_generated_datetime: true,
		},
		signatures: {
			show_panel_coordinator_line: true,
			hod: {
				enabled: true,
				label: 'Head of the Department',
				name: '',
			},
		},
	};
}

function mergeSettings( loaded ) {
	const base = defaultSettings();
	if ( ! loaded || typeof loaded !== 'object' ) {
		return base;
	}
	return {
		...base,
		...loaded,
		letterhead: {
			...base.letterhead,
			...( loaded.letterhead || {} ),
			blocks: loaded.letterhead?.blocks?.length
				? loaded.letterhead.blocks
				: base.letterhead.blocks,
		},
		report: { ...base.report, ...( loaded.report || {} ) },
		table: { ...base.table, ...( loaded.table || {} ) },
		footer: { ...base.footer, ...( loaded.footer || {} ) },
		signatures: {
			...base.signatures,
			...( loaded.signatures || {} ),
			hod: { ...base.signatures.hod, ...( loaded.signatures?.hod || {} ) },
		},
	};
}

async function uploadLogo( file ) {
	const root = ( window.prAppData?.root || '/wp-json' ).replace( /\/$/, '' );
	const form = new FormData();
	form.append( 'file', file );
	const response = await fetch( `${ root }/wp/v2/media`, {
		method: 'POST',
		headers: {
			'X-WP-Nonce': window.prAppData?.nonce || '',
		},
		body: form,
	} );
	if ( ! response.ok ) {
		throw new Error( 'Logo upload failed.' );
	}
	const data = await response.json();
	return {
		id: data.id,
		url: data.source_url || data.guid?.rendered || '',
	};
}

export function PanelReportSettings() {
	const { id } = useParams();
	const sessionId = parseInt( id, 10 );
	const [ settings, setSettings ] = useState( defaultSettings );
	const [ settingsFrozen, setSettingsFrozen ] = useState( false );
	const [ logoPreview, setLogoPreview ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ freezing, setFreezing ] = useState( false );
	const [ freezeOpen, setFreezeOpen ] = useState( false );
	const [ unfreezeOpen, setUnfreezeOpen ] = useState( false );
	const toast = useToast();
	const frozen = settingsFrozen || settings?.settings_frozen;

	const load = useCallback( async () => {
		if ( ! sessionId ) {
			return;
		}
		setLoading( true );
		try {
			const data = await get(
				`sessions/${ sessionId }/panel-report-settings`
			);
			const merged = mergeSettings( data?.panel_report_pdf );
			setSettings( merged );
			setSettingsFrozen(
				Boolean( data?.settings_frozen || merged?.settings_frozen )
			);
			const logoBlock = merged.letterhead?.blocks?.find(
				( b ) => b.type === 'image'
			);
			if ( logoBlock?.attachment_id ) {
				try {
					const root = ( window.prAppData?.root || '/wp-json' ).replace(
						/\/$/,
						''
					);
					const media = await fetch(
						`${ root }/wp/v2/media/${ logoBlock.attachment_id }`,
						{
							headers: {
								'X-WP-Nonce': window.prAppData?.nonce || '',
							},
						}
					);
					if ( media.ok ) {
						const json = await media.json();
						setLogoPreview( json.source_url || '' );
					}
				} catch {
					setLogoPreview( '' );
				}
			} else {
				setLogoPreview( '' );
			}
		} catch {
			toast( { variant: 'error', message: 'Could not load panel report settings.' } );
		} finally {
			setLoading( false );
		}
	}, [ sessionId ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const update = ( path, value ) => {
		setSettings( ( prev ) => {
			const next = { ...prev };
			const keys = path.split( '.' );
			let cursor = next;
			for ( let i = 0; i < keys.length - 1; i += 1 ) {
				cursor[ keys[ i ] ] = { ...cursor[ keys[ i ] ] };
				cursor = cursor[ keys[ i ] ];
			}
			cursor[ keys[ keys.length - 1 ] ] = value;
			return next;
		} );
	};

	const updateLetterheadText = ( index, field, value ) => {
		setSettings( ( prev ) => {
			const blocks = [ ...( prev.letterhead?.blocks || [] ) ];
			let target = 0;
			let seen = -1;
			for ( let i = 0; i < blocks.length; i += 1 ) {
				if ( blocks[ i ].type === 'text' ) {
					seen += 1;
					if ( seen === index ) {
						target = i;
						break;
					}
				}
			}
			if ( seen < index ) {
				target = blocks.length;
				blocks.push( {
					type: 'text',
					value: '',
					style: 'body',
					label: '',
				} );
			}
			blocks[ target ] = { ...blocks[ target ], [ field ]: value };
			return {
				...prev,
				letterhead: { ...prev.letterhead, blocks },
			};
		} );
	};

	const logoBlock =
		( settings.letterhead?.blocks || [] ).find( ( b ) => b.type === 'image' ) ||
		{ attachment_id: 0, width_in: 4 };

	const handleLogoChange = async ( event ) => {
		const file = event.target.files?.[ 0 ];
		if ( ! file ) {
			return;
		}
		try {
			const uploaded = await uploadLogo( file );
			setLogoPreview( uploaded.url );
			setSettings( ( prev ) => {
				const blocks = [ ...( prev.letterhead?.blocks || [] ) ];
				const imageIndex = blocks.findIndex( ( b ) => b.type === 'image' );
				const imageBlock = {
					type: 'image',
					attachment_id: uploaded.id,
					width_in: logoBlock.width_in || 4,
					align: 'center',
				};
				if ( imageIndex >= 0 ) {
					blocks[ imageIndex ] = imageBlock;
				} else {
					blocks.unshift( imageBlock );
				}
				return { ...prev, letterhead: { blocks } };
			} );
		} catch {
			toast( { variant: 'error', message: 'Could not upload logo.' } );
		}
		event.target.value = '';
	};

	const handleLogoWidthChange = ( event ) => {
		const width = parseFloat( event.target.value ) || 4;
		setSettings( ( prev ) => {
			const blocks = [ ...( prev.letterhead?.blocks || [] ) ];
			const idx = blocks.findIndex( ( b ) => b.type === 'image' );
			if ( idx >= 0 ) {
				blocks[ idx ] = { ...blocks[ idx ], width_in: width };
			}
			return { ...prev, letterhead: { blocks } };
		} );
	};

	const trimReportMeta = ( s ) => ( {
		...s,
		report: {
			...s.report,
			program_name: ( s.report?.program_name || '' ).trim(),
			semester: ( s.report?.semester || '' ).trim(),
		},
	} );

	const handleSave = async () => {
		if ( frozen ) {
			return;
		}
		setSaving( true );
		const payload = trimReportMeta( settings );
		setSettings( payload );
		try {
			await put( `sessions/${ sessionId }/panel-report-settings`, {
				panel_report_pdf: payload,
			} );
			toast( { variant: 'success', message: 'Panel report settings saved.' } );
		} catch {
			toast( { variant: 'error', message: 'Could not save settings.' } );
		} finally {
			setSaving( false );
		}
	};

	const handleFreezeSettings = async () => {
		setFreezing( true );
		const payload = trimReportMeta( settings );
		setSettings( payload );
		try {
			const data = await post(
				`sessions/${ sessionId }/panel-report-settings/freeze`,
				{ panel_report_pdf: payload }
			);
			setSettingsFrozen( true );
			if ( data?.panel_report_pdf ) {
				setSettings( mergeSettings( data.panel_report_pdf ) );
			}
			setFreezeOpen( false );
			toast( {
				variant: 'success',
				message:
					'Panel report settings saved and frozen. Panel coordinators can download the PDF.',
			} );
		} catch {
			toast( {
				variant: 'error',
				message: 'Could not save and freeze settings.',
			} );
		} finally {
			setFreezing( false );
		}
	};

	const handleUnfreezeSettings = async () => {
		setFreezing( true );
		try {
			const data = await post(
				`sessions/${ sessionId }/panel-report-settings/unfreeze`,
				{}
			);
			setSettingsFrozen( false );
			if ( data?.panel_report_pdf ) {
				setSettings( mergeSettings( data.panel_report_pdf ) );
			}
			setUnfreezeOpen( false );
			toast( {
				variant: 'success',
				message: 'Panel report settings unlocked for editing.',
			} );
		} catch {
			toast( { variant: 'error', message: 'Could not unfreeze settings.' } );
		} finally {
			setFreezing( false );
		}
	};

	return (
		<div className="min-w-0 max-w-full">
			<PageHeader
				title="Panel Report"
				description="Configure the Review Report PDF for this project. Edit the document preview below; freeze settings when ready so panel coordinators can download the official PDF."
			/>

			{ loading ? (
				<ContentLoadingRegion
					busy
					variant="inline"
					label="Loading panel report settings"
					className="mt-6"
				>
					<PageContentSkeleton rows={ 5 } />
				</ContentLoadingRegion>
			) : (
				<>
			<section className="mb-6 rounded-lg border border-border bg-surface p-4">
				<div className="flex flex-wrap items-center justify-between gap-3">
					<div>
						<h2 className="text-sm font-semibold text-text">Settings lock</h2>
						<p className="mt-1 text-sm text-text-muted">
							{ frozen
								? 'Settings are frozen. Panel coordinators can download the Review Report PDF.'
								: 'While unlocked, you can edit the template. Freeze when ready to allow PDF downloads.' }
						</p>
					</div>
					<div className="flex flex-wrap items-center gap-2">
						{ frozen ? (
							<StatusChip variant="confirmed" label="Settings frozen" icon="lock" />
						) : null }
						{ frozen ? (
							<Button
								type="button"
								variant="secondary"
								icon="unlock"
								disabled={ freezing }
								onClick={ () => setUnfreezeOpen( true ) }
							>
								Unfreeze settings
							</Button>
						) : (
							<Button
								type="button"
								variant="primary"
								icon="lock"
								disabled={ freezing || saving }
								onClick={ () => setFreezeOpen( true ) }
							>
								Freeze settings
							</Button>
						) }
					</div>
				</div>
			</section>

			<fieldset disabled={ frozen } className="min-w-0 max-w-full disabled:opacity-75">
				<PanelReportSettingsPreview
					settings={ settings }
					logoPreview={ logoPreview }
					logoBlock={ logoBlock }
					disabled={ frozen }
					onUpdate={ update }
					onLetterheadText={ updateLetterheadText }
					onLogoChange={ handleLogoChange }
					onLogoWidthChange={ handleLogoWidthChange }
				/>
			</fieldset>

			<div className="mt-6 flex justify-end">
				<Button onClick={ handleSave } disabled={ saving || frozen }>
					{ saving ? 'Saving…' : 'Save settings' }
				</Button>
			</div>

			<ConfirmDialog
				open={ freezeOpen }
				title="Freeze panel report settings?"
				confirmLabel={ freezing ? 'Freezing…' : 'Freeze settings' }
				confirmDisabled={ freezing }
				onConfirm={ handleFreezeSettings }
				onCancel={ () => setFreezeOpen( false ) }
			>
				<p className="text-sm text-text-muted">
					Your current template changes will be saved, then settings will be frozen.
					After freezing, these settings cannot be edited until you unfreeze them.
					Panel coordinators can download the Review Report PDF only while settings
					are frozen.
				</p>
			</ConfirmDialog>

			<ConfirmDialog
				open={ unfreezeOpen }
				title="Unfreeze panel report settings?"
				confirmLabel={ freezing ? 'Unfreezing…' : 'Unfreeze settings' }
				confirmDisabled={ freezing }
				onConfirm={ handleUnfreezeSettings }
				onCancel={ () => setUnfreezeOpen( false ) }
			>
				<p className="text-sm text-text-muted">
					Unfreezing allows you to edit the template again. PDF download will be
					disabled for panel coordinators until you freeze settings again.
				</p>
			</ConfirmDialog>
				</>
			) }
		</div>
	);
}
