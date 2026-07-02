<?php

namespace App\Providers;

use App\Core\LLMRouter as CoreLLMRouter;

class LlmRouter
{
    public function route(array $agent, array $messages): \Generator
    {
        try {
            $systemPrompt = '';
            $formattedMessages = [];
            foreach ($messages as $msg) {
                if ($msg['role'] === 'system') {
                    $systemPrompt = $msg['content'];
                } else {
                    $formattedMessages[] = $msg;
                }
            }

            $response = CoreLLMRouter::call(
                $agent['provider'],
                $agent['model'],
                $systemPrompt,
                $formattedMessages,
                (float)($agent['temperature'] ?? 0.7),
                (int)($agent['max_tokens'] ?? 2048)
            );

            yield ['type' => 'text_delta', 'content' => $response];
            yield ['type' => 'finish'];
        } catch (\Exception $e) {
            yield ['type' => 'error', 'content' => $e->getMessage()];
        }
    }
}
