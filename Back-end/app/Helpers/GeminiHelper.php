<?php

namespace App\Helpers;

use GuzzleHttp\Client;

class GeminiHelper
{
    private const MAX_CONTEXT_CHARS = 22000;

    /**
     * Get the AI response from the Gemini API
     *
     * @param string $query
     * @return string
     */
    public static function getNlpResponse($query, $context = '')
    {
        $client = new Client();

        try {
            $geminiApiKey = config('services.gemini.api_key');  // Retrieve the API key
            $safeContext = mb_substr((string) $context, 0, self::MAX_CONTEXT_CHARS);
            $prompt = "Context:\n{$safeContext}\n\nQuestion: {$query}\n\nInstructions:\n- Answer only from the context.\n- Use this exact output format:\n  1) \"Answer:\" heading\n  2) 3-6 bullet points maximum\n  3) Every bullet MUST end with at least one citation tag from context, e.g. [SOURCE: file.pdf p.12] or [SOURCE: deck.pptx slide 3]\n  4) Then add a final line starting with \"References used:\" and list unique citation tags\n- If context is insufficient, output exactly:\n  Answer:\n  - Insufficient context provided.\n  References used: none";
            $payload = [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'maxOutputTokens' => 512,
                ],
            ];

            // Try models that are currently available for this key.
            $models = [
                'gemini-2.5-flash',
                'gemini-2.0-flash',
                'gemini-flash-latest',
                'gemini-2.0-flash-lite',
            ];

            $lastError = null;
            foreach ($models as $model) {
                $geminiApiUrl = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";

                try {
                    $response = $client->post($geminiApiUrl . '?key=' . $geminiApiKey, [
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'json' => $payload,
                        'connect_timeout' => 10,
                        'timeout' => 25,
                    ]);

                    $body = json_decode($response->getBody(), true);
                    $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
                    if (!empty($text)) {
                        return $text;
                    }
                    $blockedReason = $body['promptFeedback']['blockReason'] ?? null;
                    if ($blockedReason) {
                        $lastError = "Gemini blocked the prompt ({$blockedReason}) on model {$model}.";
                    } else {
                        $lastError = "No text in Gemini response for model {$model}.";
                    }
                } catch (\Exception $modelError) {
                    $lastError = "Model {$model} failed: " . $modelError->getMessage();
                    continue;
                }
            }

            return $lastError
                ? 'Error fetching response: ' . $lastError
                : 'No response received from Gemini models.';
        } catch (\Exception $e) {
            return 'Error fetching response: ' . $e->getMessage();
        }
    }
}
