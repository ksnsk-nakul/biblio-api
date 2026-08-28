<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin wrapper around OpenAI's REST API using Laravel's built-in Http
 * (Guzzle-backed) client — no OpenAI SDK dependency.
 */
class OpenAiClient
{
    protected const EMBEDDINGS_URL = 'https://api.openai.com/v1/embeddings';

    protected const CHAT_COMPLETIONS_URL = 'https://api.openai.com/v1/chat/completions';

    protected const EMBEDDING_MODEL = 'text-embedding-3-small';

    protected const CHAT_MODEL = 'gpt-4o-mini';

    protected string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? (string) config('services.openai.key');
    }

    /**
     * @param  string[]  $texts
     * @return array<int, array<int, float>> embeddings in the same order as $texts
     */
    public function embed(array $texts): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post(self::EMBEDDINGS_URL, [
                'model' => self::EMBEDDING_MODEL,
                'input' => array_values($texts),
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI embeddings request failed: '.$response->body());
        }

        $data = $response->json('data', []);

        // OpenAI returns results with an `index` matching input order; sort
        // defensively rather than assuming array order is preserved.
        usort($data, fn ($a, $b) => $a['index'] <=> $b['index']);

        return array_map(fn ($item) => $item['embedding'], $data);
    }

    /**
     * Streams a chat completion, invoking $onDelta with each text fragment
     * as it arrives from OpenAI's SSE stream.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  callable(string): void  $onDelta
     */
    public function streamChat(array $messages, callable $onDelta): void
    {
        $response = Http::withToken($this->apiKey)
            ->withOptions(['stream' => true])
            ->timeout(120)
            ->post(self::CHAT_COMPLETIONS_URL, [
                'model' => self::CHAT_MODEL,
                'messages' => $messages,
                'stream' => true,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI chat completions request failed: '.$response->body());
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(1024);

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = trim(substr($buffer, 0, $newlinePos));
                $buffer = substr($buffer, $newlinePos + 1);

                if ($line === '' || ! str_starts_with($line, 'data:')) {
                    continue;
                }

                $payload = trim(substr($line, 5));

                if ($payload === '[DONE]') {
                    return;
                }

                $decoded = json_decode($payload, true);
                $delta = $decoded['choices'][0]['delta']['content'] ?? null;

                if ($delta !== null && $delta !== '') {
                    $onDelta($delta);
                }
            }
        }
    }
}
