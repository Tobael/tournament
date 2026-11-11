let messages;
export function locale() {
    return globalThis.document?.documentElement?.lang === "en" ? "en" : "de";
}
export function t(source, replacements = {}) {
    if (!messages) {
        try {
            const payload = JSON.parse(
                globalThis.document?.getElementById("app-translations")
                    ?.textContent || "{}",
            );
            messages = payload.messages
                ? JSON.parse(
                      new TextDecoder().decode(
                          Uint8Array.from(atob(payload.messages), (c) =>
                              c.charCodeAt(0),
                          ),
                      ),
                  )
                : {};
        } catch {
            messages = {};
        }
    }
    let value = messages[source] ?? source;
    for (const [key, replacement] of Object.entries(replacements))
        value = value.replaceAll(`:${key}`, String(replacement));
    return value;
}
