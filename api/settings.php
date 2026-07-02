<?php

use App\Core\Request;
use App\Core\Response;
use App\Core\Auth;
use App\Core\Crypto;
use App\Models\Setting;

global $router;

$router->get('/api/settings', function() {
    $request = new Request();
    $operator = Auth::handle($request);
    
    if ($operator['role'] !== 'admin') {
        Response::error('Acesso negado', 403);
    }
    
    $settingModel = new Setting();
    $records = $settingModel->all();
    
    $result = [];
    $encryptionKey = getenv('ENCRYPTION_KEY') ?: '';
    
    foreach ($records as $rec) {
        $key = $rec['setting_key'];
        $val = $rec['setting_value'];
        $decrypted = Crypto::decrypt($val, $encryptionKey);
        $result[$key] = $decrypted !== null ? $decrypted : $val;
    }
    
    Response::json($result);
});

$router->post('/api/settings', function() {
    $request = new Request();
    $operator = Auth::handle($request);
    
    if ($operator['role'] !== 'admin') {
        Response::error('Acesso negado', 403);
    }
    
    $body = $request->getBody();
    if (!isset($body['settings']) || !is_array($body['settings'])) {
        Response::error('O objeto settings é obrigatório', 400);
    }
    
    $settingModel = new Setting();
    $encryptionKey = getenv('ENCRYPTION_KEY') ?: '';
    
    try {
        foreach ($body['settings'] as $key => $val) {
            $encrypted = Crypto::encrypt($val, $encryptionKey);
            $records = $settingModel->where(['setting_key' => $key]);
            
            if (!empty($records)) {
                $settingModel->update($records[0]['id'], [
                    'setting_value' => $encrypted
                ]);
            } else {
                $settingModel->create([
                    'setting_key' => $key,
                    'setting_value' => $encrypted
                ]);
            }
        }
        
        Response::json(['success' => true]);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});
