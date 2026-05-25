/**
 * Coordinator SPA entry — mounts on #pr-root (pr_app=coordinator).
 */
import { createRoot } from '@wordpress/element';
import { CoordinatorApp } from './App';
import '../shared/styles.css';
import '../../assets/css/panel-report-preview.css';

const root = document.getElementById( 'pr-root' );

if ( root ) {
	createRoot( root ).render( <CoordinatorApp /> );
}
