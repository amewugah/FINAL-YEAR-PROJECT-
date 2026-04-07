<?php

namespace App\Helpers;

use GuzzleHttp\Client;

class CohereHelper
{
    /**
     * Get the AI response from Cohere API
     *
     * @param string $query
     * @param string $context
     * @return string
     */
    protected $cohereApiUrl = 'https://api.cohere.ai/v2/chat';

    public static function getNlpResponse($query, $context)
    {
        $client = new Client();

        try {
            $cohereApiKey = config('services.cohere.api_key');  // Retrieve the API key
            $cohereApiUrl = 'https://api.cohere.ai/v2/chat';  // Set the API URL

            // Modify the prompt for bulleted content
            $prompt = "Context: $context\n\nSome points are bulleted without much explanation. Expand on the following point: \"$query\" with detailed information.\n\nAnswer:";

            $response = $client->post($cohereApiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $cohereApiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'model' => 'command-r-plus',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'max_tokens' => 300,
                    'temperature' => 0.7,
                ],
            ]);

            $body = json_decode($response->getBody(), true);

            return $body['text']
                ?? $body['message']['content'][0]['text']
                ?? $body['output'][0]['content'][0]['text']
                ?? 'No relevant information found.';
        } catch (\Exception $e) {
            return 'Error fetching response: ' . $e->getMessage();
        }
    }
}
