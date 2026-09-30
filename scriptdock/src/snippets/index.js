/**
 * Mounts the Snippets screen.
 */
import { createRoot } from '@wordpress/element';
import { ErrorBoundary, ToastProvider } from '../components';
import App from './app';

const root = document.getElementById( 'sd-snippets' );
if ( root && window.sdSnippets ) {
	createRoot( root ).render(
		<ErrorBoundary label="Snippets">
			<ToastProvider>
				<App boot={ window.sdSnippets } />
			</ToastProvider>
		</ErrorBoundary>
	);
}
