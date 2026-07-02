<?php

namespace App\Providers;

use Generator;

class GoogleAdapter implements LlmAdapterInterface
{
    private string $currentJson = '';
    private int $braceCount = 0;
    private bool $inString = false;
    private bool $escaped = false;

    public function streamChat(array $params): Generator
    {
        $apiKey = $params['api_key'] ?? '';
        $model = $params['model'] ?? 'gemini-1.5-flash';
        $temperature = $params['temperature'] ?? 0.7;
        $maxTokens = $params['max_tokens'] ?? 2048;
        $messages = $params['messages'] ?? [];

        $systemPrompt = '';
        $contents = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            if ($role === 'system') {
                $systemPrompt .= ($systemPrompt === '' ? '' : "\n") . ($msg['content'] ?? '');
            } else {
                $geminiRole = $role === 'assistant' ? 'model' : 'user';
                $contents[] = [
                    'role' => $geminiRole,
                    'parts' => [
                        ['text' => $msg['content'] ?? '']
                    ]
                ];
            }
        }

        $postData = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxTokens,
            ]
        ];

        if ($systemPrompt !== '') {
            $postData['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':streamGenerateContent?key=' . $apiKey;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        $headers = [
            'Content-Type: application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $buffer = [];
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use (&$buffer) {
            $buffer[] = $data;
            return strlen($data);
        });

        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch);

        $active = null;
        do {
            $status = curl_multi_exec($mh, $active);

            while (!empty($buffer)) {
                $chunk = array_shift($buffer);
                foreach ($this->parseChunk($chunk) as $event) {
                    yield $event;
                }
            }

            if ($active) {
                curl_multi_select($mh, 0.05);
            }
        } while ($active && $status === CURLM_OK);

        while (!empty($buffer)) {
            $chunk = array_shift($buffer);
            foreach ($this->parseChunk($chunk) as $event) {
                yield $event;
            }
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode >= 400) {
            yield ['type' => 'error', 'content' => 'Google Gemini API Error (HTTP ' . $httpCode . ')'];
        }

        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        curl_multi_close($mh);
    }

    private function parseChunk(string $chunk): array
    {
        $events = [];
        $length = strlen($chunk);

        for ($i = 0; $i < $length; $i++) {
            $char = $chunk[$i];

            if ($this->braceCount > 0) {
                $this->currentJson .= $char;

                if ($this->inString) {
                    if ($this->escaped) {
                        $this->escaped = false;
                    } elseif ($char === '\\') {
                        $this->escaped = true;
                    } elseif ($char === '"') {
                        $this->inString = false;
                    }
                } else {
                    if ($char === '"') {
                        $this->inString = true;
                    } elseif ($char === '{') {
                        $this->braceCount++;
                    } elseif ($char === '}') {
                        $this->braceCount--;
                        if ($this->braceCount === 0) {
                            $json = json_decode($this->currentJson, true);
                            if (is_array($json)) {
                                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                                if ($text !== '') {
                                    $events[] = ['type' => 'text_delta', 'content' => $text];
                                }

                                $finishReason = $json['candidates'][0]['finishReason'] ?? null;
                                if ($finishReason !== null) {
                                    $events[] = ['type' => 'finish'];
                                }
                            }
                            $this->currentJson = '';
                            $this->inString = false;
                            $this->escaped = false;
                        }
                    }
                }
            } else {
                if ($char === '{') {
                    $this->braceCount = 1;
                    $this->currentJson = '{';
                    $this->inString = false;
                    $this->escaped = false;
                }
            }
        }

        return $events;
    }
}
