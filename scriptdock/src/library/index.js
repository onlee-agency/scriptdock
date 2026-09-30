/**
 * Mounts the Library screen.
 */
import { createRoot } from '@wordpress/element';
import { ErrorBoundary, ToastProvider } from '../components';
import App from './app';

const root = document.getElementById( 'sd-library' );
if ( root && window.sdLibrary ) {
	createRoot( root ).render(
		<ErrorBoundary label="Library">
			<ToastProvider>
				<App boot={ window.sdLibrary } />
			</ToastProvider>
		</ErrorBoundary>
	);
}
