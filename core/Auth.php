<?php

namespace App\Core;

use App\Models\Operator;

class Auth
{
    public static function handle(Request $request): array
    {
        $authHeader = $request->getHeader('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            Response::error('Unauthorized', 401);
        }

        $token = substr($authHeader, 7);
        $secret = getenv('JWT_SECRET') ?: 'default-chat-sdk-jwt-secret';
        $payload = JWT::decode($token, $secret);
        if (!$payload) {
            Response::error('Unauthorized', 401);
        }

        $operatorModel = new Operator();
        $operator = $operatorModel->find($payload['id']);
        if (!$operator) {
            Response::error('Unauthorized', 401);
        }

        return $operator;
    }
}
