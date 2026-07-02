<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Operator;
use Exception;

class HandoverService
{
    public function requestHandover(string $conversationId): void
    {
        $conversationModel = new Conversation();
        $conversationModel->update($conversationId, [
            'status' => 'handover_requested'
        ]);

        try {
            $operatorModel = new Operator();
            $ops = $operatorModel->all();
            $mailService = new MailService();
            foreach ($ops as $op) {
                if (!empty($op['email'])) {
                    $mailService->sendMail(
                        $op['email'],
                        'Solicitação de Atendimento Humano',
                        '<h1>Atendimento Humano Solicitado</h1><p>Uma nova conversa (ID: ' . $conversationId . ') solicitou a transição para um operador humano. Por favor, aceda ao painel de administração para responder.</p>'
                    );
                }
            }
        } catch (Exception $e) {
        }
    }

    public function assignOperator(string $conversationId, string $operatorId): void
    {
        $conversationModel = new Conversation();
        $conversationModel->update($conversationId, [
            'status' => 'active_human',
            'assigned_operator_id' => $operatorId
        ]);
    }

    public function unassignOperator(string $conversationId): void
    {
        $conversationModel = new Conversation();
        $conversationModel->update($conversationId, [
            'status' => 'waiting_for_agent',
            'assigned_operator_id' => null
        ]);
    }

    public function resolveConversation(string $conversationId): void
    {
        $conversationModel = new Conversation();
        $conversationModel->update($conversationId, [
            'status' => 'resolved'
        ]);
    }

    public function updateStatus(string $conversationId, string $status): void
    {
        $conversationModel = new Conversation();
        $conversationModel->update($conversationId, [
            'status' => $status
        ]);
    }
}
