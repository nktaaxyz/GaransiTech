<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

class GeminiClaimSummarizer
{
    public function isConfigured(): bool
    {
        return filled(config('services.gemini.api_key'));
    }

    public function summarize(string $transcript): string
    {
        $apiKey = config('services.gemini.api_key');
        if (! filled($apiKey)) {
            throw new \LogicException('Gemini is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['x-goog-api-key' => $apiKey])
                ->connectTimeout(10)
                ->timeout(30)
                ->post(
                    'https://generativelanguage.googleapis.com/v1beta/models/'
                        .rawurlencode((string) config('services.gemini.model'))
                        .':generateContent',
                    [
                        'contents' => [[
                            'role' => 'user',
                            'parts' => [[
                                'text' => "Ubah transkrip pelanggan berikut menjadi daftar poin singkat dalam bahasa Indonesia untuk deskripsi kerusakan produk. Pertahankan hanya gejala dan fakta yang disebutkan; jangan menambah dugaan atau diagnosis. Keluarkan hanya poin-poinnya.\n\nTranskrip:\n".$transcript,
                            ]],
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.2,
                        ],
                    ],
                )
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Gemini claim summarization request failed.', [
                'exception' => $exception::class,
                'status' => $exception instanceof RequestException
                    ? $exception->response->status()
                    : null,
            ]);

            throw new ClaimSummaryUnavailableException('Gemini could not summarize the claim transcript.', previous: $exception);
        }

        $summary = collect($response->json('candidates.0.content.parts', []))
            ->pluck('text')
            ->filter(fn ($text) => is_string($text))
            ->implode("\n");

        if (trim($summary) === '') {
            Log::warning('Gemini returned an empty claim summary.');
            throw new ClaimSummaryUnavailableException('Gemini returned an empty claim summary.');
        }

        return trim($summary);
    }
}
