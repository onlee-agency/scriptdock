/**
 * Mounts the Header & Footer screen.
 */
import { createRoot } from '@wordpress/element';
import { ErrorBoundary, ToastProvider } from '../components';
import App from './app';

const root = document.getElementById( 'sd-global' );
if ( root && window.sdGlobal ) {
	createRoot( root ).render(
		<ErrorBoundary label="Header & Footer">
			<ToastProvider>
				<App boot={ window.sdGlobal } />
			</ToastProvider>
		</ErrorBoundary>
	);
}
