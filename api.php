<?php
session_start();
header('Content-Type: application/json');

// ========== CREDENZIALI QUI ==========
define('USERNAME', 'dosamigos');
define('PASSWORD', 'seiuninetto');
// =====================================

$dataFile = __DIR__ . '/data.json';

function isAuth() {
    return isset($_SESSION['auth']) && $_SESSION['auth'] === true;
}

function respond($data) {
    echo json_encode($data);
    exit;
}

// GET Requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    if (isset($_GET['action']) && $_GET['action'] === 'check_auth') {
        respond(['authenticated' => isAuth()]);
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        session_destroy();
        respond(['success' => true]);
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'load') {
        if (!isAuth()) {
            http_response_code(401);
            respond(['success' => false, 'error' => 'Non autenticato']);
        }

        if (file_exists($dataFile)) {
            $data = json_decode(file_get_contents($dataFile), true);

            // Migrazione automatica da vecchio formato (array) a nuovo formato (oggetto)
            if (isset($data[0])) {
                // Vecchio formato: array di lavori
                $data = [
                    'lavori' => $data,
                    'movimentiBancari' => [],
                    'contoBancario' => ['saldoAttuale' => 0, 'storico' => []],
                    'speseFuture' => []
                ];
            }

            respond(['success' => true, 'data' => $data]);
        } else {
            respond(['success' => true, 'data' => [
                'lavori' => [],
                'movimentiBancari' => [],
                'contoBancario' => ['saldoAttuale' => 0, 'storico' => []],
                'speseFuture' => []
            ]]);
        }
    }
}

// POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'login') {
        $user = isset($input['username']) ? $input['username'] : '';
        $pass = isset($input['password']) ? $input['password'] : '';
        
        if ($user === USERNAME && $pass === PASSWORD) {
            $_SESSION['auth'] = true;
            respond(['success' => true]);
        } else {
            http_response_code(401);
            respond(['success' => false, 'error' => 'Credenziali errate']);
        }
    }
    
    if (isset($input['action']) && $input['action'] === 'save') {
        if (!isAuth()) {
            http_response_code(401);
            respond(['success' => false, 'error' => 'Non autenticato']);
        }
        
        $data = $input['data'];

        if (file_exists($dataFile)) {
            $backupDir = __DIR__ . '/backups';
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0755, true);
            }

            // Sistema di rotazione backup (max 100 file)
            $backupFiles = glob($backupDir . '/backup_*.json');
            if (count($backupFiles) >= 100) {
                // Ordina per data di modifica (più vecchi prima)
                usort($backupFiles, function($a, $b) {
                    return filemtime($a) - filemtime($b);
                });

                // Elimina i più vecchi per mantenere spazio per il nuovo (99 + 1 nuovo = 100)
                $filesToDelete = count($backupFiles) - 99;
                for ($i = 0; $i < $filesToDelete; $i++) {
                    @unlink($backupFiles[$i]);
                }
            }

            @copy($dataFile, $backupDir . '/backup_' . date('Ymd_His') . '.json');
        }
        
        $result = file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
        
        if ($result !== false) {
            respond(['success' => true]);
        } else {
            http_response_code(500);
            respond(['success' => false, 'error' => 'Errore salvataggio']);
        }
    }
}

http_response_code(400);
respond(['success' => false, 'error' => 'Richiesta non valida']);
?>