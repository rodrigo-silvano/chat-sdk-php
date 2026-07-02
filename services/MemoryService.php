<?php

namespace App\Services;

use App\Models\Memory;

class MemoryService
{
    public function getMemory(string $sessionId, string $key): ?array
    {
        $memoryModel = new Memory();
        $records = $memoryModel->where([
            'session_id' => $sessionId,
            'memory_key' => $key
        ]);
        return !empty($records) ? $records[0] : null;
    }

    public function setMemory(string $sessionId, string $key, string $value): string
    {
        $memoryModel = new Memory();
        $records = $memoryModel->where([
            'session_id' => $sessionId,
            'memory_key' => $key
        ]);

        if (!empty($records)) {
            $id = $records[0]['id'];
            $memoryModel->update($id, [
                'memory_value' => $value
            ]);
            return $id;
        }

        return $memoryModel->create([
            'session_id' => $sessionId,
            'memory_key' => $key,
            'memory_value' => $value
        ]);
    }

    public function injectMemory(string $systemPrompt, string $sessionId): string
    {
        $memoryModel = new Memory();
        $records = $memoryModel->where([
            'session_id' => $sessionId
        ]);

        if (empty($records)) {
            return $systemPrompt;
        }

        $memText = "\n\nUser Profile & Context (Session Memories):\n";
        foreach ($records as $rec) {
            $memText .= "- " . $rec['memory_key'] . ": " . $rec['memory_value'] . "\n";
        }

        return $systemPrompt . $memText;
    }
}
