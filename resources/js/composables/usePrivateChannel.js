import { onMounted, onUnmounted } from 'vue';
import { getEcho } from '@/echo';

export function usePrivateChannel(channelName, event, handler) {
    onMounted(() => {
        const echo = getEcho();
        if (!echo || !channelName) {
            return;
        }

        echo.private(channelName).listen(event, handler);
    });

    onUnmounted(() => {
        const echo = getEcho();
        if (echo && channelName) {
            echo.leave(channelName);
        }
    });
}

export function hasRealtime() {
    return Boolean(getEcho());
}
