<?php

namespace App\Core;

use Exception;

class LLMRouter
{
    public static function call(
        string $provider,
        string $model,
        string $systemPrompt,
        array $messages,
        float $temperature = 0.7,
        int $maxTokens = 2048
    ): string {
        $apiKey = self::getApiKey($provider);
        if (!$apiKey) {
            throw new Exception("API key for provider {$provider} not found");
        }

        if ($provider === 'openai') {
            return self::callOpenAI($model, $systemPrompt, $messages, $temperature, $maxTokens, $apiKey);
        }
        if ($provider === 'anthropic') {
            return self::callAnthropic($model, $systemPrompt, $messages, $temperature, $maxTokens, $apiKey);
        }
        if ($provider === 'google') {
            return self::callGoogle($model, $systemPrompt, $messages, $temperature, $maxTokens, $apiKey);
        }

        throw new Exception("Unsupported provider: {$provider}");
    }

    private static function getApiKey(string $provider): ?string
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute(['key' => $provider . '_api_key']);
        $row = $stmt->fetch();
        
        $encryptionKey = getenv('ENCRYPTION_KEY') ?: '';
        $apiKey = null;
        if ($row && $row['setting_value']) {
            $apiKey = Crypto::decrypt($row['setting_value'], $encryptionKey);
        }
        if (!$apiKey) {
            $apiKey = getenv(strtoupper($provider) . '_API_KEY');
        }
        return $apiKey ?: null;
    }

    private static function callOpenAI(
        string $model,
        string $systemPrompt,
        array $messages,
        float $temperature,
        int $maxTokens,
        string $apiKey
    ): string {
        $formatted = [];
        foreach ($messages as $msg) {
            $role = $msg['role'];
            if ($role === 'operator') {
                $role = 'user';
            } elseif ($role !== 'system' && $role !== 'assistant') {
                $role = 'user';
            }
            $formatted[] = [
                'role' => $role,
                'content' => $msg['content']
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => array_merge([['role' => 'system', 'content' => $systemPrompt]], $formatted),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("OpenAI API curl error: " . $error);
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['choices'][0]['message']['content'])) {
            throw new Exception("OpenAI API response error: " . ($data['error']['message'] ?? $response));
        }

        return $data['choices'][0]['message']['content'];
    }

    private static function callAnthropic(
        string $model,
        string $systemPrompt,
        array $messages,
        float $temperature,
        int $maxTokens,
        string $apiKey
    ): string {
        $formatted = [];
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                continue;
            }
            $role = $msg['role'] === 'assistant' ? 'assistant' : 'user';
            $formatted[] = [
                'role' => $role,
                'content' => $msg['content']
            ];
        }

        $payload = [
            'model' => $model,
            'messages' => $formatted,
            'system' => $systemPrompt,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Anthropic API curl error: " . $error);
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['content'][0]['text'])) {
            throw new Exception("Anthropic API response error: " . ($data['error']['message'] ?? $response));
        }

        return $data['content'][0]['text'];
    }

    private static function callGoogle(
        string $model,
        string $systemPrompt,
        array $messages,
        float $temperature,
        int $maxTokens,
        string $apiKey
    ): string {
        $contents = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]]
            ];
        }

        $payload = [
            'contents' => $contents,
            'systemInstruction' => [
                'parts' => [['text' => $systemPrompt]]
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens
            ]
        ];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Google Gemini API curl error: " . $error);
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new Exception("Google Gemini API response error: " . ($data['error']['message'] ?? $response));
        }

        return $data['candidates'][0]['content']['parts'][0]['text'];
    }
}
