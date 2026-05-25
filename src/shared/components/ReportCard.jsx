import { useState } from '@wordpress/element';
import { getBlob } from '../api';
import { Button, Card } from './index';

const OFFLINE_SCORING_SHEET_KEY = 'offline_scoring_sheet';

function defaultFormats( report ) {
	if ( Array.isArray( report.formats ) && report.formats.length > 0 ) {
		return report.formats;
	}
	if ( report.key === OFFLINE_SCORING_SHEET_KEY ) {
		return [ 'pdf' ];
	}
	return [ 'xlsx', 'csv' ];
}

function buildDownloadUrl( report, sessionId, reviewId, apiRoot, format ) {
	if ( report.scope === 'review' ) {
		if ( report.key === 'panel_roster' ) {
			return `${ apiRoot }sessions/${ sessionId }/reviews/${ reviewId }/panel-roster/download?format=${ format }`;
		}
		if ( report.key === 'marks_matrix' ) {
			return `${ apiRoot }sessions/${ sessionId }/reviews/${ reviewId }/marks-grid/download?format=${ format }&layout=rubric&sort_key=reg_no&sort_dir=asc`;
		}
		if ( report.key === 'scores_matrix' ) {
			return `${ apiRoot }sessions/${ sessionId }/reviews/${ reviewId }/scores-matrix/download?format=${ format }&sort_key=reg_no&sort_dir=asc`;
		}
		if ( report.key === OFFLINE_SCORING_SHEET_KEY && format === 'pdf' ) {
			return `${ apiRoot }sessions/${ sessionId }/reviews/${ reviewId }/offline-scoring-sheet/pdf`;
		}
		return null;
	}

	if ( report.scope === 'session' && report.key === 'consolidated_student_scores' ) {
		return `${ apiRoot }sessions/${ sessionId }/consolidated-student-scores/download?format=${ format }`;
	}

	return `${ apiRoot }sessions/${ sessionId }/reports/${ report.key }/download?format=${ format }`;
}

function buildBlobPath( report, sessionId, reviewId, format ) {
	if ( report.scope === 'review' ) {
		if ( report.key === 'panel_roster' ) {
			return `/sessions/${ sessionId }/reviews/${ reviewId }/panel-roster/download?format=${ format }`;
		}
		if ( report.key === 'marks_matrix' ) {
			return `/sessions/${ sessionId }/reviews/${ reviewId }/marks-grid/download?format=${ format }&layout=rubric&sort_key=reg_no&sort_dir=asc`;
		}
		if ( report.key === 'scores_matrix' ) {
			return `/sessions/${ sessionId }/reviews/${ reviewId }/scores-matrix/download?format=${ format }&sort_key=reg_no&sort_dir=asc`;
		}
		if ( report.key === OFFLINE_SCORING_SHEET_KEY && format === 'pdf' ) {
			return `/sessions/${ sessionId }/reviews/${ reviewId }/offline-scoring-sheet/pdf`;
		}
		return null;
	}

	if ( report.scope === 'session' && report.key === 'consolidated_student_scores' ) {
		return `/sessions/${ sessionId }/consolidated-student-scores/download?format=${ format }`;
	}

	return `/sessions/${ sessionId }/reports/${ report.key }/download?format=${ format }`;
}

export function ReportCard( {
	report,
	sessionId,
	apiRoot,
	reviewId = '',
	reviews = [],
	onReviewIdChange,
} ) {
	const [ downloading, setDownloading ] = useState( null );
	const [ error, setError ] = useState( '' );
	const formats = defaultFormats( report );
	const needsReview = report.scope === 'review';
	const reviewRequired = needsReview && ! reviewId;

	const download = async ( format ) => {
		const key = `${ report.key }-${ format }`;
		setDownloading( key );
		setError( '' );
		try {
			if ( format === 'pdf' ) {
				const blobPath = buildBlobPath(
					report,
					sessionId,
					reviewId,
					format
				);
				if ( ! blobPath ) {
					throw new Error( 'unsupported_report' );
				}
				const response = await getBlob( blobPath );
				const blob = await response.blob();
				const disposition =
					response.headers.get( 'Content-Disposition' ) || '';
				const match = disposition.match( /filename="([^"]+)"/ );
				const filename = match
					? match[ 1 ]
					: `${ report.key }.pdf`;
				const link = document.createElement( 'a' );
				link.href = URL.createObjectURL( blob );
				link.download = filename;
				link.click();
				URL.revokeObjectURL( link.href );
				return;
			}

			const url = buildDownloadUrl(
				report,
				sessionId,
				reviewId,
				apiRoot,
				format
			);
			if ( ! url ) {
				throw new Error( 'unsupported_report' );
			}
			const response = await fetch( url, {
				credentials: 'same-origin',
				headers: {
					'X-WP-Nonce': window.prAppData?.nonce || '',
				},
			} );
			if ( ! response.ok ) {
				let message = 'Download failed. Please try again.';
				const contentType = response.headers.get( 'content-type' ) || '';
				if ( contentType.includes( 'application/json' ) ) {
					const data = await response.json();
					message = data?.message || message;
				}
				throw new Error( message );
			}
			const blob = await response.blob();
			const disposition =
				response.headers.get( 'Content-Disposition' ) || '';
			const match = disposition.match( /filename="([^"]+)"/ );
			const filename = match
				? match[ 1 ]
				: `${ report.key }.${ format === 'csv' ? 'csv' : 'xlsx' }`;
			const link = document.createElement( 'a' );
			link.href = URL.createObjectURL( blob );
			link.download = filename;
			link.click();
			URL.revokeObjectURL( link.href );
		} catch ( err ) {
			setError(
				err?.message || 'Download failed. Please try again.'
			);
		} finally {
			setDownloading( null );
		}
	};

	const busy = downloading !== null;

	return (
		<Card className="flex flex-col gap-4">
			<div>
				<h3 className="text-base font-semibold text-text">
					{ report.label }
				</h3>
				<p className="mt-1 text-sm text-text-muted">
					{ report.description }
				</p>
			</div>
			{ needsReview && reviews.length > 0 && onReviewIdChange ? (
				<label className="block text-sm">
					<span className="mb-1 block font-medium text-text">
						Review round
					</span>
					<select
						className="w-full rounded-md border border-border bg-surface px-3 py-2 text-sm text-text"
						value={ reviewId }
						onChange={ ( e ) => onReviewIdChange( e.target.value ) }
						disabled={ busy }
					>
						{ reviews.map( ( review ) => (
							<option key={ review.id } value={ review.id }>
								{ review.label || `Review ${ review.id }` }
								{ review.status &&
								review.status !== 'confirmed'
									? ` (${ review.status })`
									: '' }
							</option>
						) ) }
					</select>
				</label>
			) : null }
			{ reviewRequired ? (
				<p className="text-sm text-text-muted">
					Select a review round to download.
				</p>
			) : null }
			{ error ? (
				<p className="text-sm text-danger" role="alert">
					{ error }
				</p>
			) : null }
			<div className="flex flex-wrap gap-2">
				{ formats.includes( 'pdf' ) ? (
					<Button
						variant="secondary"
						disabled={ busy || reviewRequired }
						onClick={ () => download( 'pdf' ) }
					>
						{ downloading === `${ report.key }-pdf`
							? 'Downloading…'
							: 'Download PDF' }
					</Button>
				) : null }
				{ formats.includes( 'xlsx' ) ? (
					<Button
						variant="secondary"
						disabled={ busy || reviewRequired }
						onClick={ () => download( 'xlsx' ) }
					>
						{ downloading === `${ report.key }-xlsx`
							? 'Downloading…'
							: 'Download Excel' }
					</Button>
				) : null }
				{ formats.includes( 'csv' ) ? (
					<Button
						variant="secondary"
						disabled={ busy || reviewRequired }
						onClick={ () => download( 'csv' ) }
					>
						{ downloading === `${ report.key }-csv`
							? 'Downloading…'
							: 'Download CSV' }
					</Button>
				) : null }
			</div>
		</Card>
	);
}
