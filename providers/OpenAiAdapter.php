<?php

namespace App\Providers;

use Generator;

class OpenAiAdapter implements LlmAdapterInterface
{
    private string $lineBuffer = '';

    public function streamChat(array $params): Generator
    {
        $apiKey = $params['api_key'] ?? '';
        $model = $params['model'] ?? 'gpt-4o';
        $temperature = $params['temperature'] ?? 0.7;
        $maxTokens = $params['max_tokens'] ?? 2048;
        $messages = $params['messages'] ?? [];

        $postData = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => true,
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
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
            yield ['type' => 'error', 'content' => 'OpenAI API Error (HTTP ' . $httpCode . ')'];
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
            
            if (str_starts_with($line, 'data: ')) {
                $data = substr($line, 6);
                if ($data === '[DONE]') {
                    $events[] = ['type' => 'finish'];
                    continue;
                }
                
                $json = json_decode($data, true);
                if (is_array($json)) {
                    $text = $json['choices'][0]['delta']['content'] ?? '';
                    if ($text !== '') {
                        $events[] = ['type' => 'text_delta', 'content' => $text];
                    }
                    
                    $toolCalls = $json['choices'][0]['delta']['tool_calls'] ?? null;
                    if ($toolCalls !== null) {
                        $events[] = ['type' => 'tool_call', 'content' => $toolCalls];
                    }
                }
            }
        }
        
        return $events;
    }
}
