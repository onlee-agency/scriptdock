/**
 * Mounts the Settings screen.
 */
import { createRoot } from '@wordpress/element';
import { ErrorBoundary, ToastProvider } from '../components';
import App from './app';

const root = document.getElementById( 'sd-settings' );
if ( root && window.sdSettings ) {
	createRoot( root ).render(
		<ErrorBoundary label="Settings">
			<ToastProvider>
				<App boot={ window.sdSettings } />
			</ToastProvider>
		</ErrorBoundary>
	);
}
