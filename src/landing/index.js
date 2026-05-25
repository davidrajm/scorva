/**
 * Landing entry — mounts on #pr-root (pr_app=landing).
 */
import { createRoot } from '@wordpress/element';
import { LandingApp } from './App';
import '../shared/styles.css';

const root = document.getElementById( 'pr-root' );

if ( root ) {
	createRoot( root ).render( <LandingApp /> );
}
