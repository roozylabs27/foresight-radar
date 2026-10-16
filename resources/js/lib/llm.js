// Display names for the provider ids stored with AI-generated data
// (signals.extraction_provider, scenarios.provider). Unknown ids are title-cased.
const PROVIDER_LABELS = {
    claude: "Claude",
    openrouter: "OpenRouter",
    opencode: "OpenCode Zen",
    "opencode-zen": "OpenCode Zen",
    ollama: "Ollama",
    "lm-studio": "LM Studio",
    groq: "Groq",
    deepseek: "DeepSeek",
    gemini: "Gemini",
    openai: "OpenAI",
    heuristic: "Aturan",
    template: "Kerangka tanpa AI",
};

export function providerLabel(provider) {
    if (!provider) return "AI";
    return (
        PROVIDER_LABELS[provider] ??
        String(provider)
            .split(/[-_\s]+/)
            .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
            .join(" ")
    );
}

// LLM work runs in a queued job; its status is "queued", "processing", "completed" or "failed".
export const isPending = (status) => status === "queued" || status === "processing";

const wait = (ms, signal) =>
    new Promise((resolve) => {
        const timer = setTimeout(resolve, ms);
        signal?.addEventListener("abort", () => {
            clearTimeout(timer);
            resolve();
        });
    });

/**
 * Call `load()` every `interval` ms until `done(result)` is true. Resolves to
 * that result, or to null when `signal` aborts or `timeout` ms pass first.
 * A failed request rejects.
 */
export async function pollUntil(load, done, { interval = 2500, timeout = 6 * 60 * 1000, signal } = {}) {
    const deadline = Date.now() + timeout;
    while (!signal?.aborted) {
        const result = await load();
        if (signal?.aborted) return null;
        if (done(result)) return result;
        if (Date.now() + interval > deadline) return null;
        await wait(interval, signal);
    }
    return null;
}
