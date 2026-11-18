/**
 * Reads a Server-Sent Events stream with `fetch`, which unlike
 * `EventSource` can send the bearer token the hub requires.
 *
 * Calls `onData` with the data of every event until the stream ends or
 * the signal aborts. Rejects when the connection cannot be opened.
 */
export async function readEventStream(
    url: string,
    token: string,
    signal: AbortSignal,
    onData: (data: string) => void,
): Promise<void> {
    const response = await fetch(url, {
        headers: { Accept: 'text/event-stream', Authorization: `Bearer ${token}` },
        signal,
    });

    if (!response.ok || response.body === null) {
        throw new Error(`Event stream answered ${response.status}.`);
    }

    const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
    let buffer = '';

    for (;;) {
        const { value, done } = await reader.read();

        if (done) {
            return;
        }

        buffer += value;
        const events = buffer.split('\n\n');
        buffer = events.pop() ?? '';

        for (const event of events) {
            const data = event
                .split('\n')
                .filter((line) => line.startsWith('data:'))
                .map((line) => line.slice(5).trimStart())
                .join('\n');

            if (data !== '') {
                onData(data);
            }
        }
    }
}
