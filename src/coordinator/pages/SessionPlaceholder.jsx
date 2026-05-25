import { useParams } from 'react-router-dom';
import { PageHeader } from '../../shared/components';

export function SessionPlaceholder( { step } ) {
	const { id } = useParams();
	const titles = {
		wizard: 'Project setup',
		progress: 'Marking progress',
	};

	return (
		<PageHeader
			title={ titles[ step ] ?? 'Project' }
			description={ `Project #${ id } — full ${ step } UI ships in a later epic.` }
		/>
	);
}
