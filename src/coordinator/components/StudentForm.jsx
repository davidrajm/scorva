import { useEffect, useState } from '@wordpress/element';
import { get, post, put } from '../../shared/api';
import { Button } from '../../shared/components';

const SCALAR_FIELDS = [
	{ key: 'reg_no', label: 'Registration number', required: true },
	{ key: 'name', label: 'Name', required: true },
	{ key: 'batch', label: 'Batch', required: false },
];

export function StudentForm( {
	student,
	customFields = [],
	onSaved,
	onCancel,
} ) {
	const isEdit = Boolean( student?.id );
	const [ form, setForm ] = useState( {
		reg_no: '',
		name: '',
		program: '',
		batch: '',
		meta: {},
	} );
	const [ saving, setSaving ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ programs, setPrograms ] = useState( [] );

	useEffect( () => {
		get( '/programs' )
			.then( ( data ) => setPrograms( data?.programs ?? [] ) )
			.catch( () => setPrograms( [] ) );
	}, [] );

	useEffect( () => {
		if ( ! student ) {
			setForm( {
				reg_no: '',
				name: '',
				program: '',
				batch: '',
				meta: {},
			} );
			return;
		}
		setForm( {
			reg_no: student.reg_no ?? '',
			name: student.name ?? '',
			program: student.program ?? '',
			batch: student.batch ?? '',
			meta: { ...( student.meta ?? {} ) },
		} );
	}, [ student ] );

	const setField = ( key, value ) => {
		setForm( ( current ) => ( { ...current, [ key ]: value } ) );
	};

	const setMeta = ( key, value ) => {
		setForm( ( current ) => ( {
			...current,
			meta: { ...current.meta, [ key ]: value },
		} ) );
	};

	const handleSubmit = async ( event ) => {
		event.preventDefault();
		setSaving( true );
		setError( '' );

		const programValue = form.program.trim();

		// Validate program against catalog (case-insensitive) before submitting.
		if ( programValue !== '' ) {
			const lowerValue = programValue.toLowerCase();
			const match = programs.find(
				( p ) => p.name.toLowerCase() === lowerValue
			);
			if ( ! match ) {
				setError(
					`Program "${ programValue }" is not in the catalog. Add it to the program catalog first.`
				);
				setSaving( false );
				return;
			}
		}

		try {
			const payload = {
				reg_no: form.reg_no.trim(),
				name: form.name.trim(),
				program: programValue,
				batch: form.batch.trim(),
				meta: form.meta,
			};
			if ( isEdit ) {
				await put( `/students/${ student.id }`, payload );
			} else {
				await post( '/students', payload );
			}
			onSaved?.();
		} catch ( err ) {
			const message =
				err?.data?.message ||
				err?.message ||
				'Could not save student. Check required fields and try again.';
			setError( message );
		} finally {
			setSaving( false );
		}
	};

	return (
		<form
			onSubmit={ handleSubmit }
			className="rounded-md border border-border bg-surface-raised p-4 shadow-card"
		>
			<h3 className="text-lg font-semibold text-text">
				{ isEdit ? 'Edit student' : 'Add student' }
			</h3>
			<div className="mt-4 grid gap-4 sm:grid-cols-2">
				{ SCALAR_FIELDS.map( ( field ) => (
					<div key={ field.key }>
						<label
							className="block text-sm font-medium text-text"
							htmlFor={ `student-${ field.key }` }
						>
							{ field.label }
							{ field.required ? (
								<span className="text-danger"> *</span>
							) : null }
						</label>
						<input
							id={ `student-${ field.key }` }
							type="text"
							value={ form[ field.key ] }
							onChange={ ( e ) => setField( field.key, e.target.value ) }
							required={ field.required }
							aria-required={ field.required ? 'true' : undefined }
							className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
						/>
					</div>
				) ) }
				<div>
					<label
						className="block text-sm font-medium text-text"
						htmlFor="student-program"
					>
						Program
					</label>
					<input
						id="student-program"
						type="text"
						list="student-program-list"
						value={ form.program }
						onChange={ ( e ) => setField( 'program', e.target.value ) }
						autoComplete="off"
						className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
					/>
					<datalist id="student-program-list">
						{ programs.map( ( p ) => (
							<option key={ p.id } value={ p.name } />
						) ) }
					</datalist>
					{ form.program.trim() !== '' &&
						! programs.some(
							( p ) =>
								p.name.toLowerCase() ===
								form.program.trim().toLowerCase()
						) && (
							<p className="mt-1 text-xs text-warning">
								{ `"${ form.program.trim() }" is not in the catalog. Add it to the program catalog first.` }
							</p>
						) }
				</div>
				{ customFields.map( ( field ) => (
					<div key={ field.field_key }>
						<label
							className="block text-sm font-medium text-text"
							htmlFor={ `student-meta-${ field.field_key }` }
						>
							{ field.label || field.field_key }
						</label>
						<input
							id={ `student-meta-${ field.field_key }` }
							type="text"
							value={ form.meta[ field.field_key ] ?? '' }
							onChange={ ( e ) =>
								setMeta( field.field_key, e.target.value )
							}
							className="mt-1 w-full rounded-md border border-border bg-surface px-3 py-2 text-sm"
						/>
					</div>
				) ) }
			</div>
			{ error ? (
				<p className="mt-3 text-sm text-danger" role="alert">
					{ error }
				</p>
			) : null }
			<div className="mt-4 flex gap-2">
				<Button type="submit" variant="primary" loading={ saving }>
					{ isEdit ? 'Save changes' : 'Add student' }
				</Button>
				{ onCancel ? (
					<Button type="button" variant="secondary" onClick={ onCancel }>
						Cancel
					</Button>
				) : null }
			</div>
		</form>
	);
}
