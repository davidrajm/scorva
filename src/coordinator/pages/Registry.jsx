import { useCallback, useEffect, useState } from '@wordpress/element';
import { del, get } from '../../shared/api';
import { useDebouncedValue } from '../../shared/hooks/useDebouncedValue';
import {
	Button,
	ContentLoadingRegion,
	EmptyState,
	Notice,
	PageHeader,
	TableSkeleton,
} from '../../shared/components';
import { useLoadingPhase } from '../../shared/hooks/useLoadingPhase';
import { CsvImportMapper } from '../components/CsvImportMapper';
import { StudentForm } from '../components/StudentForm';
import { TableDataViewport } from '../../shared/TableScrollViewport';
import {
	TABLE_BODY_ROW_SOFT,
	TABLE_VIEWPORT_ROW_INCREMENT,
	regNoStickyClass,
	regNoStickyStyle,
} from '../../shared/tableStyles';

export function Registry() {
	const [students, setStudents] = useState(null);
	const [customFields, setCustomFields] = useState([]);
	const [search, setSearch] = useState('');
	const [loading, setLoading] = useState(true);
	const [showForm, setShowForm] = useState(false);
	const [showImport, setShowImport] = useState(false);
	const [editingStudent, setEditingStudent] = useState(null);
	const [importNotice, setImportNotice] = useState(null);

	const debouncedSearch = useDebouncedValue(search, 300);

	const loadRegistry = useCallback(async () => {
		setLoading(true);
		try {
			const query = debouncedSearch
				? `?search=${encodeURIComponent(debouncedSearch)}`
				: '';
			const [studentData, schemaData] = await Promise.all([
				get(`/students${query}`),
				get('/students/field-schema'),
			]);
			setStudents(studentData.students ?? []);
			setCustomFields(schemaData.fields ?? []);
		} catch {
			setStudents([]);
			setCustomFields([]);
		} finally {
			setLoading(false);
		}
	}, [debouncedSearch]);

	useEffect(() => {
		loadRegistry();
	}, [loadRegistry]);

	const handleDelete = async (student) => {
		if (
			!window.confirm(
				`Delete ${student.name || student.reg_no} from the registry?`
			)
		) {
			return;
		}
		try {
			await del(`/students/${student.id}`);
			loadRegistry();
		} catch {
			// eslint-disable-next-line no-alert
			window.alert('Could not delete student.');
		}
	};

	const handleSaved = () => {
		setShowForm(false);
		setEditingStudent(null);
		loadRegistry();
	};

	const handleStudentImportSuccess = ({ variant, message }) => {
		if (message) {
			setImportNotice({ variant: variant ?? 'success', message });
		}
	};

	const columns = ( () => {
		const base = [
			{ key: 'reg_no', label: 'Reg. no.' },
			{ key: 'name', label: 'Name' },
			{ key: 'program', label: 'Program' },
			{ key: 'batch', label: 'Batch' },
			...customFields.map( ( field ) => ( {
				key: field.field_key,
				label: field.label || field.field_key,
				isMeta: true,
			} ) ),
		];
		const regIndex = base.findIndex( ( col ) => col.key === 'reg_no' );
		if ( regIndex > 0 ) {
			const [ regCol ] = base.splice( regIndex, 1 );
			base.unshift( regCol );
		}

		return base;
	} )();

	const { showSkeleton, showOverlay } = useLoadingPhase(
		loading,
		students !== null
	);

	const hasStudents = (students?.length ?? 0) > 0;
	const showEmpty = !hasStudents && !debouncedSearch && !showForm && !showImport;

	return (
		<>
			<PageHeader
				title="Student directory"
				description="Cross-project student identity and custom fields. You can also add students directly in each project's setup wizard."
				actions={
					<div className="flex flex-wrap gap-2">
						<Button
							variant="secondary"
							onClick={() => {
								setShowImport((value) => !value);
								setShowForm(false);
								setEditingStudent(null);
							}}
						>
							{showImport ? 'Hide import' : 'Import CSV'}
						</Button>
						<Button
							variant="primary"
							onClick={() => {
								setShowForm(true);
								setEditingStudent(null);
								setShowImport(false);
							}}
						>
							Add student
						</Button>
					</div>
				}
			/>

			{importNotice ? (
				<div className="mt-4">
					<Notice
						variant={importNotice.variant}
						onDismiss={() => setImportNotice(null)}
					>
						{importNotice.message}
					</Notice>
				</div>
			) : null}

			{showImport ? (
				<CsvImportMapper
					customFields={customFields}
					onImportSuccess={handleStudentImportSuccess}
					onComplete={loadRegistry}
				/>
			) : null}

			{showForm || editingStudent ? (
				<div className="mt-6">
					<StudentForm
						student={editingStudent}
						customFields={customFields}
						onSaved={handleSaved}
						onCancel={() => {
							setShowForm(false);
							setEditingStudent(null);
						}}
					/>
				</div>
			) : null}

			{showSkeleton ? (
				<ContentLoadingRegion
					busy
					variant="inline"
					label="Loading registry"
					className="mt-6"
				>
					<TableSkeleton columns={ Math.max( columns.length + 1, 4 ) } />
				</ContentLoadingRegion>
			) : showEmpty ? (
				<EmptyState
					title="No students in the registry"
					description="Import a CSV file or add your first student to get started."
					action={
						<div className="flex flex-wrap justify-center gap-2">
							<Button
								variant="primary"
								onClick={() => setShowImport(true)}
							>
								Import CSV
							</Button>
							<Button variant="secondary" onClick={() => setShowForm(true)}>
								Add first student
							</Button>
						</div>
					}
				/>
			) : (
				<>
					<div className="mt-6">
						<label
							className="block text-sm font-medium text-text"
							htmlFor="registry-search"
						>
							Search students
						</label>
						<input
							id="registry-search"
							type="search"
							value={search}
							onChange={(e) => setSearch(e.target.value)}
							placeholder="Search by reg. no., name, program, batch"
							className="mt-1 w-full max-w-md rounded-md border border-border bg-surface px-3 py-2 text-sm"
						/>
					</div>

					<ContentLoadingRegion
						busy={ showOverlay }
						variant="overlay"
						label="Loading registry"
						className="mt-6"
					>
					{!hasStudents ? (
						<p className="text-sm text-text-muted">
							No students match your search.
						</p>
					) : (
						<TableDataViewport
							className="mt-6 bg-surface-raised shadow-card"
							bodyRowCount={ students.length }
							rowIncrement={ TABLE_VIEWPORT_ROW_INCREMENT }
						>
							<table className="w-max min-w-full text-left text-sm">
								<thead>
									<tr className="border-b border-border bg-surface text-text-muted">
										{columns.map((column) => (
											<th
												key={column.key}
												className={
													column.key === 'reg_no'
														? `${regNoStickyClass({ header: true })} px-4 py-3 font-medium`
														: 'px-4 py-3 font-medium'
												}
												style={
													column.key === 'reg_no'
														? regNoStickyStyle()
														: undefined
												}
											>
												{column.label}
											</th>
										))}
										<th className="px-4 py-3 font-medium">Actions</th>
									</tr>
								</thead>
								<tbody>
									{students.map((student) => (
										<tr
											key={student.id}
											className={`group ${TABLE_BODY_ROW_SOFT}`}
										>
											{columns.map((column) => (
												<td
													key={column.key}
													className={
														column.key === 'reg_no'
															? `${regNoStickyClass()} px-4 py-3 text-text group-hover:bg-surface-raised`
															: 'px-4 py-3 text-text'
													}
													style={
														column.key === 'reg_no'
															? regNoStickyStyle()
															: undefined
													}
												>
													{column.isMeta
														? student.meta?.[column.key] ?? '—'
														: student[column.key] ?? '—'}
												</td>
											))}
											<td className="px-4 py-3">
												<div className="flex gap-2">
													<Button
														variant="ghost"
														size="sm"
														onClick={() => {
															setEditingStudent(student);
															setShowForm(false);
															setShowImport(false);
														}}
													>
														Edit
													</Button>
													<Button
														variant="ghost"
														size="sm"
														onClick={() => handleDelete(student)}
													>
														Delete
													</Button>
												</div>
											</td>
										</tr>
									))}
								</tbody>
							</table>
						</TableDataViewport>
					)}
					</ContentLoadingRegion>
				</>
			)}
		</>
	);
}
