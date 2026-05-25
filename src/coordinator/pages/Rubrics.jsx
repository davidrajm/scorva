import { Navigate, useParams } from 'react-router-dom';

/** Bookmarks for `#/session/:id/rubrics` or `/reviews` → wizard Reviews & rubrics step. */
export function SessionReviewsWizardRedirect() {
	const { id } = useParams();
	return (
		<Navigate
			to={ `/session/${ id }/wizard?step=reviews` }
			replace
		/>
	);
}
