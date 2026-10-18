import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Reverb speaks the Pusher protocol, so laravel-echo's 'reverb' broadcaster
// is really just its Pusher connector pointed at our own server. Only
// initialize it when VITE_REVERB_APP_KEY is set at build time (production has
// no websocket server, BROADCAST_CONNECTION=null and the key is unset -- see
// .env.example) so the app never opens a websocket to nothing. Without a key
// window.Echo stays undefined; every consumer must check for it.
if (import.meta.env.VITE_REVERB_APP_KEY) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            withCredentials: true,
        },
    });
}
