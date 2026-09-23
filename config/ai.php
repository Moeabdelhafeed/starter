<?php

/*
|--------------------------------------------------------------------------
| AI assistant
|--------------------------------------------------------------------------
|
| The admin assistant's model backend. Read via config() — never env() — so
| it survives `php artisan config:cache` (see tests/Feature/ConfigCacheTest.php).
|
| `driver` picks an implementation of App\Services\Ai\AgentDriver. Ollama runs
| the model on your own machine: nothing leaves the server and there is no API
| key or per-token cost, which is why it is the default. It does need a host
| that can run the daemon and hold the weights in RAM — roughly 5–6 GB for a 7B
| model at q4 — so it cannot run on the shared hosting the Deploy panel targets.
| A hosted provider is the answer there; the driver interface exists so adding
| one is a new class, not a rewrite.
|
*/

return [

    'driver' => env('AI_DRIVER', 'ollama'),

    'ollama' => [
        // Ollama's OpenAI-compatible endpoint. Include the /v1.
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434/v1'),

        /*
         * Must be a model post-trained for tool calling — qwen3, llama3.x,
         * mistral. One without tool support answers in prose instead of calling
         * anything, which reads as the assistant inventing numbers.
         *
         * Measured on this project's own questions (email lookup, list pages,
         * count admins, explain a flag), on a 16 GB Mac:
         *
         *   qwen3:8b   4/4   ~19s per answer   5.2 GB
         *   llama3.2   3/4    ~6s per answer   2.0 GB
         *
         * 8B is the default because an assistant that is wrong a quarter of the
         * time is worse than a slow one — it also picks the better tool (it
         * reached for list_records where the 3B reached for search). Swap to
         * llama3.2 if the latency matters more than the last question.
         */
        'model' => env('OLLAMA_MODEL', 'qwen3:8b'),

        // Local generation on CPU is slow; this is a whole turn, not a token.
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),

        /*
         * Near-deterministic on purpose. Ollama's own default is 0.8, tuned for
         * chat; this workload is picking one tool from a list and reading a
         * value back unchanged, where creativity is the failure mode rather
         * than the point.
         */
        'temperature' => (float) env('OLLAMA_TEMPERATURE', 0),
    ],

    /*
     * How many times the assistant may call tools before it has to answer.
     *
     * Every hop is another round trip to the model, and a small local model
     * that has lost the thread will otherwise call the same tool forever. The
     * loop stops and answers with what it has.
     */
    'max_tool_hops' => (int) env('AI_MAX_TOOL_HOPS', 5),

    // Cap on one question. Long pastes are the expensive, low-value case.
    'max_prompt_chars' => (int) env('AI_MAX_PROMPT_CHARS', 2000),

];
