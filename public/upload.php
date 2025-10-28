<?php
require __DIR__ . '/../config/App.php';
require __DIR__ . '/../src/Utils.php';
require __DIR__ . '/../src/FlashMessage.php';

header('X-Handler: upload'); // Debug-Hilfe

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        throw new RuntimeException('Method not allowed');
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Keine Datei oder Upload-Fehler.');
    }

    $folder = trim($_POST['folder'] ?? '');
    // sanitize: nur Buchstaben, Zahlen, -, _, /
    if ($folder !== '' && !preg_match('~^[a-zA-Z0-9/_-]+$~', $folder)) {
        throw new RuntimeException('Ungültiger Ordnername.');
    }

    $origName = $_FILES['file']['name'];
    $tmpPath  = $_FILES['file']['tmp_name'];
    $size     = (int)$_FILES['file']['size'];

    if ($size > UPLOAD_MAX_BYTES) {
        throw new RuntimeException('Datei zu groß.');
    }

    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (!in_array($ext, UPLOAD_ALLOWED_EXT, true)) {
        throw new RuntimeException('Dateityp nicht erlaubt: ' . $ext);
    }

    $safeName = uniqid('', true) . '-' . preg_replace('~[^a-zA-Z0-9._-]~', '_', $origName);
    $targetDir = rtrim(UPLOAD_PATH . ($folder ? DIRECTORY_SEPARATOR . $folder : ''), DIRECTORY_SEPARATOR);

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true)) {
        throw new RuntimeException('Zielordner kann nicht erstellt werden.');
    }

    $dest = $targetDir . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($tmpPath, $dest)) {
        throw new RuntimeException('Konnte Datei nicht speichern.');
    }

    // Erfolgreich: Flash-Message setzen und zur Index-Seite umleiten
    $uploadedFile = ($folder ? $folder . '/' : '') . $safeName;
    FlashMessage::set('Upload erfolgreich: ' . $uploadedFile);
    
    header('Location: ' . BASE_URL . '/public/index.php', true, 303);
    exit;
} catch (Throwable $e) {
    http_response_code(http_response_code() >= 400 ? http_response_code() : 400);
    
    // Fehler als Flash-Message speichern und zurück zum Formular
    FlashMessage::set('Upload-Fehler: ' . $e->getMessage(), 'error');
    header('Location: ' . BASE_URL . '/public/index.php', true, 303);
    exit;
}