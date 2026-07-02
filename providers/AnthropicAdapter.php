<?php

namespace App\Providers;

use Generator;

class AnthropicAdapter implements LlmAdapterInterface
{
    private string $lineBuffer = '';
    private string $currentEvent = '';

    public function streamChat(array $params): Generator
    {
        $apiKey = $params['api_key'] ?? '';
        $model = $params['model'] ?? 'claude-3-5-sonnet-20241022';
        $temperature = $params['temperature'] ?? 0.7;
        $maxTokens = $params['max_tokens'] ?? 2048;
        $messages = $params['messages'] ?? [];

        $systemPrompt = '';
        $filteredMessages = [];
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $systemPrompt .= ($systemPrompt === '' ? '' : "\n") . ($msg['content'] ?? '');
            } else {
                $role = $msg['role'] ?? 'user';
                if ($role === 'model') {
                    $role = 'assistant';
                }
                $filteredMessages[] = [
                    'role' => $role === 'assistant' ? 'assistant' : 'user',
                    'content' => $msg['content'] ?? '',
                ];
            }
        }

        $postData = [
            'model' => $model,
            'messages' => $filteredMessages,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'stream' => true,
        ];

        if ($systemPrompt !== '') {
            $postData['system'] = $systemPrompt;
        }

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

        $headers = [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey,
            'Anthropic-Version: 2023-06-01',
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
            yield ['type' => 'error', 'content' => 'Anthropic API Error (HTTP ' . $httpCode . ')'];
        }

        curl_multi_remove_handle($mh, $ch);
        curl_multi_close($mh);
        curl_close($ch);
    }

    private function parseChunk(string $chunk): array
    {
        $events = [];
        $this->lineBuffer .= $chunk;

        while (($pos = strpos($this->lineBuffer, "\n")) !== false) {
            $line = substr($this->lineBuffer, 0, $pos);
            $this->lineBuffer = substr($this->lineBuffer, $pos + 1);

            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'event: ')) {
                $this->currentEvent = trim(substr($line, 7));
            } elseif (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                $json = json_decode($data, true);
                if (is_array($json)) {
                    if ($this->currentEvent === 'content_block_delta') {
                        $text = $json['delta']['text'] ?? '';
                        if ($text !== '') {
                            $events[] = ['type' => 'text_delta', 'content' => $text];
                        }
                    } elseif ($this->currentEvent === 'message_stop') {
                        $events[] = ['type' => 'finish'];
                    }
                }
            }
        }

        return $events;
    }
}
