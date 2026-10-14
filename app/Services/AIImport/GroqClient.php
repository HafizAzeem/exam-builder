<?php

namespace App\Services\AIImport;

use App\Models\AISetting;
use Illuminate\Support\Facades\Http;

class GroqClient
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, mixed>  $extra
     * @return array{content:string, usage:?array<string, mixed>}
     */
    public function chat(array $messages, ?string $model = null, array $extra = [], int $timeout = 180): array
    {
        $settings = AISetting::current();
        $settings->applyProviderConfig();

        $apiKey = $settings->resolvedGroqApiKey();
        if (! filled($apiKey)) {
            throw new \RuntimeException('Groq is not configured. Set the Groq API key in AI Settings.');
        }

        $url = rtrim((string) config('ai.providers.groq.url', 'https://api.groq.com/openai/v1'), '/');
        $model = $model ?: $settings->resolvedGroqModel();

        $payload = array_merge([
            'model' => $model,
            'temperature' => (float) $settings->temperature,
            'max_tokens' => max(256, (int) $settings->max_tokens),
            'messages' => $messages,
        ], $extra);

        $response = $this->postChat($url, (string) $apiKey, $payload, $timeout);

        if ($response->status() === 400 && $this->shouldFallbackJsonObject($payload, $response->body())) {
            $payload['response_format'] = ['type' => 'json_object'];
            $response = $this->postChat($url, (string) $apiKey, $payload, $timeout);
        }

        if ($response->status() === 429) {
            throw new \RuntimeException('Groq rate limit exceeded.');
        }

        if (! $response->successful()) {
            throw new \RuntimeException(
                'Groq request failed (HTTP '.$response->status().'): '.mb_substr($response->body(), 0, 500)
            );
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            $toolCalls = data_get($response->json(), 'choices.0.message.tool_calls');
            if (is_array($toolCalls) && $toolCalls !== []) {
                $content = json_encode($toolCalls);
            }
        }

        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('Groq returned an empty response.');
        }

        return [
            'content' => $content,
            'usage' => is_array(data_get($response->json(), 'usage'))
                ? data_get($response->json(), 'usage')
                : null,
            'raw' => $response->json(),
        ];
    }

    public function prompt(string $prompt, ?string $model = null, array $extra = [], int $timeout = 180): array
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $model, $extra, $timeout);
    }

    public function decodeJson(string $text): array
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/', '', $text) ?? $text;
        }

        if (preg_match('/(\[[\s\S]*\]|\{[\s\S]*\})/', $text, $m)) {
            $text = $m[1];
        }

        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new \RuntimeException('Invalid JSON response from Groq: '.json_last_error_msg());
        }

        if (! isset($decoded['questions']) && array_is_list($decoded)) {
            return ['questions' => $decoded];
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postChat(string $url, string $apiKey, array $payload, int $timeout): \Illuminate\Http\Client\Response
    {
        $response = Http::timeout($timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($apiKey)
            ->post($url.'/chat/completions', $payload);

        if ($response->status() === 429) {
            $wait = (int) $response->header('Retry-After');
            $wait = min(45, max(8, $wait > 0 ? $wait : 15));
            sleep($wait);
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->asJson()
                ->withToken($apiKey)
                ->post($url.'/chat/completions', $payload);
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function shouldFallbackJsonObject(array $payload, string $body): bool
    {
        $format = $payload['response_format']['type'] ?? null;
        if ($format !== 'json_schema') {
            return false;
        }

        return (bool) preg_match('/json_schema|response_format|unrecognized|invalid schema/i', $body);
    }
}
