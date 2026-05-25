import { useEffect, useState } from '@wordpress/element';
import { useParams } from 'react-router-dom';
import { get } from '../../shared/api';
import {
	ContentLoadingRegion,
	EmptyState,
	PageHeader,
	TableSkeleton,
} from '../../shared/components';
import { TableScrollWrapper } from '../../shared/TableScrollViewport';
import { TABLE_ROW_HOVER } from '../../shared/tableStyles';

function formatAuditAction( action ) {
	if ( action === 'attendance_correction' ) {
		return 'Attendance correction';
	}
	if ( action === 'review_marks_locked' ) {
		return 'Review marks frozen';
	}
	if ( action === 'review_marks_unlocked' ) {
		return 'Review marks unlocked';
	}
	if ( action === 'mark_override' ) {
		return 'Mark override';
	}

	return action;
}

export function AuditLog() {
	const { id } = useParams();
	const [ data, setData ] = useState( { items: [], total: 0 } );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		get( `sessions/${ id }/audit?per_page=50` )
			.then( setData )
			.finally( () => setLoading( false ) );
	}, [ id ] );

	return (
		<>
			<PageHeader title="Audit log" description="Governance actions for this project." />
			{ loading ? (
				<ContentLoadingRegion
					busy
					variant="inline"
					label="Loading audit log"
					className="mt-4"
				>
					<TableSkeleton rows={ 8 } columns={ 4 } />
				</ContentLoadingRegion>
			) : null }
			{ ! loading && data.items.length === 0 && (
				<EmptyState
					title="No audit entries"
					description="Overrides and project events will appear here."
				/>
			) }
			{ data.items.length > 0 && (
				<TableScrollWrapper>
					<table className="min-w-full text-left text-sm">
						<thead className="bg-surface-raised text-text-muted">
							<tr>
								<th className="px-4 py-2">Time</th>
								<th className="px-4 py-2">Actor</th>
								<th className="px-4 py-2">Action</th>
								<th className="px-4 py-2">Entity</th>
								<th className="px-4 py-2">Details</th>
							</tr>
						</thead>
						<tbody>
							{ data.items.map( ( row ) => (
								<tr
									key={ row.id }
									className={ `border-t border-border ${ TABLE_ROW_HOVER }` }
								>
									<td className="px-4 py-2">{ row.created_at }</td>
									<td className="px-4 py-2">{ row.actor_name }</td>
									<td className="px-4 py-2">{ formatAuditAction( row.action ) }</td>
									<td className="px-4 py-2">
										{ row.entity } #{ row.entity_id }
									</td>
									<td className="px-4 py-2 max-w-xs truncate">
										{ row.new_value }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</TableScrollWrapper>
			) }
		</>
	);
}
