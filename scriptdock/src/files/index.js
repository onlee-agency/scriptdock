/**
 * Mounts the Site Files screen.
 */
import { createRoot } from '@wordpress/element';
import { ErrorBoundary, ToastProvider } from '../components';
import App from './app';

const root = document.getElementById( 'sd-files' );
if ( root && window.sdFiles ) {
	createRoot( root ).render(
		<ErrorBoundary label="Site Files">
			<ToastProvider>
				<App boot={ window.sdFiles } />
			</ToastProvider>
		</ErrorBoundary>
	);
}
