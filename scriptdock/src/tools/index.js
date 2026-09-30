/**
 * Mounts the Import & Export screen.
 */
import { createRoot } from '@wordpress/element';
import { ErrorBoundary, ToastProvider } from '../components';
import App from './app';

const root = document.getElementById( 'sd-tools' );
if ( root && window.sdTools ) {
	createRoot( root ).render(
		<ErrorBoundary label="Import & Export">
			<ToastProvider>
				<App boot={ window.sdTools } />
			</ToastProvider>
		</ErrorBoundary>
	);
}
