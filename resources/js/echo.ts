import { configureEcho } from '@laravel/echo-vue';

/**
 * Configure Laravel Echo to connect to the local Reverb WebSocket server.
 * All connection parameters are populated automatically from VITE_REVERB_* env vars.
 * Use the composables from @laravel/echo-vue in your components:
 *   - useEcho()         → private/presence channels (authenticated user)
 *   - useEchoPublic()   → public channels (node HP updates, etc.)
 *   - useConnectionStatus() → reactive connection state
 */
configureEcho({
    broadcaster: 'reverb',
});
