<?php

namespace App\Services;

use App\Core\LLMRouter;
use Exception;

class IntentService
{
    public function detectIntent(
        string $provider,
        string $model,
        string $latestMessage,
        array $history = []
    ): string {
        try {
            $systemPrompt = "You are a helper that classifies user intent.\n"
                . "Classify the user's latest message into one of these labels:\n"
                . "- human_handover: User explicitly asks to talk to a human, operator, support agent, or wants human assistance.\n"
                . "- frustration: User is highly frustrated, angry, or complaining strongly.\n"
                . "- general: General questions, greetings, or other normal queries.\n\n"
                . "Respond with exactly one word from this list: human_handover, frustration, general. Do not include any other text or formatting.";

            $recentHistory = array_slice($history, -5);
            $formattedMessages = [];
            foreach ($recentHistory as $msg) {
                $formattedMessages[] = [
                    'role' => $msg['role'],
                    'content' => $msg['content']
                ];
            }
            $formattedMessages[] = [
                'role' => 'user',
                'content' => $latestMessage
            ];

            $classification = LLMRouter::call(
                $provider,
                $model,
                $systemPrompt,
                $formattedMessages,
                0.0,
                10
            );

            $cleanLabel = strtolower(trim($classification));
            if (str_contains($cleanLabel, 'human_handover')) {
                return 'human_handover';
            }
            if (str_contains($cleanLabel, 'frustration')) {
                return 'frustration';
            }
            return 'general';
        } catch (Exception $e) {
            return 'general';
        }
    }
}
