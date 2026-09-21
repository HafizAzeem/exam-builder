import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export function createEcho(key, cluster) {
    if (window.Echo || !key) {
        return window.Echo ?? null;
    }

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster: cluster || 'ap2',
        forceTLS: true,
        authEndpoint: '/broadcasting/auth',
    });

    return window.Echo;
}

export function getEcho() {
    return window.Echo ?? null;
}
