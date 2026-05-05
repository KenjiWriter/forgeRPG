/**
 * Bootstrap Axios defaults for Laravel Sanctum / session-based auth.
 *
 * `withCredentials` — send cookies (session) cross-origin.
 * `withXSRFToken`   — auto-attach the XSRF-TOKEN cookie value as the
 *                     X-XSRF-TOKEN header, satisfying Laravel's CSRF middleware.
 *
 * Both must be true for Inertia + Axios + Laravel session auth to work.
 */
import axios from 'axios';

window.axios = axios;
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

declare global {
    interface Window {
        axios: typeof axios;
    }
}
