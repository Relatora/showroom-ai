import { ref } from 'vue';
import { errorMessage, HttpError, xsrfToken } from '@/lib/http';

/** Event name => the JSON payload that event carries. */
type EventMap = Record<string, unknown>;

type Handlers<Events extends EventMap> = {
    [Name in keyof Events]?: (data: Events[Name]) => void;
};

/**
 * Reads a server-sent event stream from a POST request.
 *
 * The browser's EventSource only does GET and can't send a body or headers, and the assistant
 * needs to POST a question (plus the CSRF token). So this reads the response body with fetch
 * and parses the SSE format by hand. It's a small format: blocks separated by a blank line,
 * each with an "event:" line and one or more "data:" lines.
 *
 *   const { streaming, post, cancel } = useEventStream<{ delta: { text: string } }>();
 *   await post('/assistant/ask', { question }, { delta: (d) => (answer.value += d.text) });
 */
export function useEventStream<Events extends EventMap>() {
    const streaming = ref(false);
    let controller: AbortController | null = null;

    async function post(
        url: string,
        body: unknown,
        handlers: Handlers<Events>,
    ): Promise<void> {
        cancel();
        controller = new AbortController();
        streaming.value = true;

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: JSON.stringify(body),
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'text/event-stream',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
            });

            if (!response.ok || !response.body) {
                throw new HttpError(
                    await errorMessage(response),
                    response.status,
                );
            }

            const reader = response.body
                .pipeThrough(new TextDecoderStream())
                .getReader();
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();

                if (done) {
                    break;
                }

                buffer += value;

                // A network chunk can end halfway through an event, so only handle complete
                // blocks and keep the remainder for the next read.
                let boundary: number;

                while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                    dispatch(buffer.slice(0, boundary), handlers);
                    buffer = buffer.slice(boundary + 2);
                }
            }
        } catch (error) {
            // Stopping on purpose isn't an error.
            if ((error as Error).name !== 'AbortError') {
                throw error;
            }
        } finally {
            streaming.value = false;
            controller = null;
        }
    }

    function cancel(): void {
        controller?.abort();
    }

    return { streaming, post, cancel };
}

function dispatch<Events extends EventMap>(
    block: string,
    handlers: Handlers<Events>,
): void {
    let event = 'message';
    const data: string[] = [];

    for (const line of block.split('\n')) {
        if (line.startsWith('event:')) {
            event = line.slice(6).trim();
        } else if (line.startsWith('data:')) {
            data.push(line.slice(5).trimStart());
        }
    }

    if (data.length > 0) {
        handlers[event as keyof Events]?.(JSON.parse(data.join('\n')));
    }
}
