/**
 * Mounts the Overview screen.
 */
import { createRoot } from '@wordpress/element';
import { ErrorBoundary, ToastProvider } from '../components';
import App from './app';

const root = document.getElementById( 'sd-overview' );
if ( root && window.sdOverview ) {
	createRoot( root ).render(
		<ErrorBoundary label="Overview">
			<ToastProvider>
				<App boot={ window.sdOverview } />
			</ToastProvider>
		</ErrorBoundary>
	);
}
