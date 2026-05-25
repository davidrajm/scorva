/**
 * Reviewer SPA entry — mounts on #pr-root (pr_app=reviewer).
 */
import { createRoot } from '@wordpress/element';
import { ReviewerApp } from './App';
import '../shared/styles.css';

const root = document.getElementById( 'pr-root' );

if ( root ) {
	createRoot( root ).render( <ReviewerApp /> );
}
