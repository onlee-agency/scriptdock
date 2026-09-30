/**
 * Mounts the snippet editor.
 */
import { createRoot } from '@wordpress/element';
import { ErrorBoundary, ToastProvider } from '../components';
import App from './app';

const root = document.getElementById( 'sd-editor' );
if ( root && window.sdEditor ) {
	createRoot( root ).render(
		<ErrorBoundary label="Editor">
			<ToastProvider>
				<App />
			</ToastProvider>
		</ErrorBoundary>
	);
}
