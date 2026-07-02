<?php

namespace App\Services;

use App\Models\Operator;
use App\Core\JWT;
use App\Core\TOTP;
use Exception;

class AuthService
{
    public function register(array $data): array
    {
        $operatorModel = new Operator();
        $existing = $operatorModel->where(['email' => $data['email']]);
        if (!empty($existing)) {
            throw new Exception('Email já registado');
        }

        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $newId = $operatorModel->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password_hash' => $passwordHash,
            'role' => 'operator',
            'totp_enabled' => 0,
            'is_online' => 0
        ]);

        $operator = $operatorModel->find($newId);
        if (!$operator) {
            throw new Exception('Erro ao criar operador');
        }

        unset($operator['password_hash']);
        unset($operator['totp_secret']);

        return $operator;
    }

    public function login(array $data): array
    {
        $operatorModel = new Operator();
        $records = $operatorModel->where(['email' => $data['email']]);
        if (empty($records)) {
            throw new Exception('E-mail ou palavra-passe inválidos');
        }

        $operatorRecord = $records[0];
        if (!password_verify($data['password'], $operatorRecord['password_hash'])) {
            throw new Exception('E-mail ou palavra-passe inválidos');
        }

        $operator = [
            'id' => $operatorRecord['id'],
            'name' => $operatorRecord['name'],
            'email' => $operatorRecord['email'],
            'role' => $operatorRecord['role'],
            'totp_enabled' => (bool)$operatorRecord['totp_enabled'],
            'is_online' => (bool)$operatorRecord['is_online'],
            'created_at' => $operatorRecord['created_at'],
            'updated_at' => $operatorRecord['updated_at']
        ];

        $secret = getenv('JWT_SECRET') ?: 'default-chat-sdk-jwt-secret';
        $token = JWT::encode([
            'id' => $operator['id'],
            'email' => $operator['email'],
            'role' => $operator['role'],
            'is2FaVerified' => !$operator['totp_enabled']
        ], $secret);

        return [
            'operator' => $operator,
            'requires2Fa' => (bool)$operatorRecord['totp_enabled'],
            'token' => $token
        ];
    }

    public function setup2Fa(string $operatorId): array
    {
        $operatorModel = new Operator();
        $operator = $operatorModel->find($operatorId);
        if (!$operator) {
            throw new Exception('Operador não encontrado');
        }

        $totpSecret = TOTP::generateSecret();
        $operatorModel->update($operatorId, [
            'totp_secret' => $totpSecret
        ]);

        $qrCode = TOTP::getQrCodeUrl($operator['email'], $totpSecret);

        return [
            'secret' => $totpSecret,
            'qrCode' => $qrCode
        ];
    }

    public function verify2Fa(string $operatorId, string $token): array
    {
        $operatorModel = new Operator();
        $operator = $operatorModel->find($operatorId);
        if (!$operator || !$operator['totp_secret']) {
            throw new Exception('2FA não configurado para este operador');
        }

        if (!TOTP::verify($operator['totp_secret'], $token)) {
            throw new Exception('Código inválido');
        }

        $operatorModel->update($operatorId, [
            'totp_enabled' => 1
        ]);

        $secret = getenv('JWT_SECRET') ?: 'default-chat-sdk-jwt-secret';
        $jwtToken = JWT::encode([
            'id' => $operator['id'],
            'email' => $operator['email'],
            'role' => $operator['role'],
            'is2FaVerified' => true
        ], $secret);

        return ['token' => $jwtToken];
    }
}
