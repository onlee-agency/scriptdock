/**
 * Mounts the first-run setup.
 */
import { createRoot } from '@wordpress/element';
import { ErrorBoundary, ToastProvider } from '../components';
import App from './app';

const root = document.getElementById( 'sd-onboarding' );
if ( root && window.sdOnboarding ) {
	createRoot( root ).render(
		<ErrorBoundary label="Setup">
			<ToastProvider>
				<App boot={ window.sdOnboarding } />
			</ToastProvider>
		</ErrorBoundary>
	);
}
