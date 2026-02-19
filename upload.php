<?php
ob_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(0);

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

$uploadDir = __DIR__ . '/uploads';
$dbFile = __DIR__ . '/db/qrdrop.db';

function respond($success, $data = [], $code = 200) {
    ob_end_clean();
    http_response_code($code);
    $payload = array_merge(['success' => $success], is_array($data) ? $data : []);
    echo json_encode($payload);
    exit;
}

function getDirSize($path) {
    $size = 0;
    if (is_dir($path)) {
        $files = @scandir($path);
        if ($files) {
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && $file !== 'index.php' && $file !== '.htaccess') {
                    $fullPath = $path . '/' . $file;
                    if (is_file($fullPath)) {
                        $size += @filesize($fullPath);
                    }
                }
            }
        }
    }
    return $size;
}

try {
    @mkdir($uploadDir, 0777, true);
    @mkdir(__DIR__ . '/db', 0777, true);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(false, ['error' => 'POST required'], 405);
    }

    if (!isset($_FILES['file'])) {
        respond(false, ['error' => 'No file uploaded'], 400);
    }

    // Get retention time from request (default: 10 minutes)
    $retentionTime = 10; // default
    if (isset($_POST['retention_time'])) {
        $rt = intval($_POST['retention_time']);
        if ($rt === 60 || $rt === 720) {
            $retentionTime = $rt;
        }
    }
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $code = (int)$_FILES['file']['error'];
        $msg = 'Upload error code: ' . $code;
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $msg = 'File too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $msg = 'File only partially uploaded';
                break;
            case UPLOAD_ERR_NO_FILE:
                $msg = 'No file sent';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $msg = 'Missing temporary folder on server';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $msg = 'Failed to write file to disk';
                break;
            case UPLOAD_ERR_EXTENSION:
                $msg = 'A PHP extension stopped the file upload';
                break;
        }
        respond(false, ['error' => $msg], 400);
    }

    $file = $_FILES['file'];

    if (!is_uploaded_file($file['tmp_name'])) {
        respond(false, ['error' => 'Temporary upload file not recognized'], 400);
    }
    // read initial bytes to detect file type (PDF, JPEG, PNG, ZIP)
    $magic = @file_get_contents($file['tmp_name'], false, null, 0, 8);
    if ($magic === false) {
        respond(false, ['error' => 'Unable to read upload'], 500);
    }

    $detected = null; // one of: pdf, jpg, png, zip
    if (substr($magic, 0, 4) === "%PDF") {
        $detected = 'pdf';
    } elseif (substr($magic, 0, 3) === "\xFF\xD8\xFF" || substr($magic, 0, 2) === "\xFF\xD8") {
        $detected = 'jpg';
    } elseif ($magic === "\x89PNG\r\n\x1A\n" || substr($magic, 0, 4) === "\x89PNG") {
        $detected = 'png';
    } elseif (substr($magic, 0, 4) === "PK\x03\x04") {
        $detected = 'zip';
    } else {
        respond(false, ['error' => 'Ungültiger Dateityp'], 400);
    }

    // verify MIME type if available and map to expected values
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
        $mimeMap = [
            'pdf' => ['application/pdf'],
            'jpg' => ['image/jpeg', 'image/pjpeg'],
            'png' => ['image/png', 'image/x-png'],
            'zip' => ['application/zip', 'application/x-zip-compressed']
        ];
        if (!isset($mimeMap[$detected]) || !in_array($mime, $mimeMap[$detected], true)) {
            respond(false, ['error' => 'MIME-Typ stimmt nicht mit Datei überein'], 400);
        }
    }

    if ($file['size'] > 52428800) {
        respond(false, ['error' => 'Too large'], 400);
    }

    // Check if upload directory size exceeds 5 GB limit
    $currentDirSize = getDirSize($uploadDir);
    $maxDirSize = 5 * 1024 * 1024 * 1024; // 5 GB in bytes
    if ($currentDirSize >= $maxDirSize) {
        respond(false, ['error' => 'Speicherlimit erreicht. Bitte versuchen Sie es später erneut.'], 507);
    }
    // Check if adding this file would exceed limit
    if (($currentDirSize + $file['size']) > $maxDirSize) {
        respond(false, ['error' => 'Nicht genug Speicherplatz verfügbar.'], 507);
    }

    $origName = $file['name'];
    $origName = preg_replace('/[\x00-\x1F\\\"' . "'\r\n]/u", '_', $origName);
    $origName = trim($origName);
    if (mb_strlen($origName) > 200) $origName = mb_substr($origName, 0, 200);

    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (!$db) {
        respond(false, ['error' => 'Database connection failed', 'db_file' => $dbFile], 500);
    }
    
    $db->exec("CREATE TABLE IF NOT EXISTS files (
        id TEXT PRIMARY KEY,
        filename TEXT NOT NULL,
        file_path TEXT NOT NULL,
        upload_time INTEGER NOT NULL,
        expiry_time INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $stmtM = $db->query("SELECT id, file_path FROM files");
    $rows = $stmtM->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $fp = $r['file_path'];
        if (empty($fp)) continue;
        if (strpos($fp, '/') !== false || strpos($fp, '\\') !== false) {
            $base = basename($fp);
            if ($base !== $fp) {
                $u = $db->prepare("UPDATE files SET file_path = ? WHERE id = ?");
                $u->execute([$base, $r['id']]);
            }
        }
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
    $initialLen = 2; // start with 2 chars and increase if collisions occur
    $maxAttempts = 10000;
    
    // Generate 8-character password for display/sharing
    $password = '';
    for ($i = 0; $i < 8; $i++) {
        $password .= $alphabet[random_int(0, strlen($alphabet)-1)];
    }
    
    // Create 32-byte encryption key from password using SHA256
    $encryptionKey = hash('sha256', $password, true);
    
    // Encrypt filename for storage
    $nameIv = random_bytes(16);
    $encryptedName = openssl_encrypt($origName, 'AES-256-CBC', $encryptionKey, OPENSSL_RAW_DATA, $nameIv);
    if ($encryptedName === false) {
        respond(false, ['error' => 'Filename encryption failed', 'openssl_error' => openssl_error_string()], 500);
    }
    // Store encrypted name as Base64 (IV + ciphertext)
    $encryptedNameB64 = base64_encode($nameIv . $encryptedName);

    $checkStmt = $db->prepare("SELECT COUNT(1) FROM files WHERE id = ?");
    $fileId = null;
    
    while ($fileId === null) {
        $attempts = 0;
        $id = substr(str_shuffle($alphabet), 0, $initialLen);
        
        while (true) {
            $attempts++;
            if ($attempts > $maxAttempts) {
                // Increase length and try again with new base
                $initialLen++;
                break;
            }
            $checkStmt->execute([$id]);
            $count = (int)$checkStmt->fetchColumn();
            if ($count === 0) {
                $fileId = $id;
                break;
            }
            $id .= $alphabet[random_int(0, strlen($alphabet)-1)];
        }
    }

    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0777, true)) {
            respond(false, ['error' => 'Unable to create upload directory'], 500);
        }
    }
    if (!is_writable($uploadDir)) {
        respond(false, ['error' => 'Upload directory not writable: ' . $uploadDir], 500);
    }

    // create a cryptographically secure random suffix (hex) and use it in the stored filename
    try {
        $randomHex = bin2hex(random_bytes(6)); // 12 hex chars
    } catch (Throwable $e) {
        // fallback to openssl if random_bytes unavailable
        if (function_exists('openssl_random_pseudo_bytes')) {
            $randomHex = bin2hex(openssl_random_pseudo_bytes(6));
        } else {
            // last resort: pseudo-random (should be very rare)
            $randomHex = bin2hex(substr(md5(uniqid('', true)), 0, 6));
        }
    }

    // filename is: <fileId>_<randomHex>.<ext> (no timestamp)
    $ext = ($detected === 'jpg') ? 'jpg' : (($detected === 'png') ? 'png' : (($detected === 'zip') ? 'zip' : 'pdf'));
    $storedName = $fileId . '_' . $randomHex . '.' . $ext;
    $filePath = $uploadDir . '/' . $storedName;

    // Read uploaded file content
    $fileContent = @file_get_contents($file['tmp_name']);
    if ($fileContent === false) {
        respond(false, ['error' => 'Failed to read uploaded file', 'tmp_path' => $file['tmp_name']], 500);
    }
    if (empty($fileContent)) {
        respond(false, ['error' => 'Uploaded file is empty'], 400);
    }
    
    // Generate random IV for AES-256-CBC encryption
    $iv = random_bytes(16);
    
    // Encrypt file content
    if (!function_exists('openssl_encrypt')) {
        respond(false, ['error' => 'OpenSSL extension not available'], 500);
    }
    
    $encrypted = openssl_encrypt($fileContent, 'AES-256-CBC', $encryptionKey, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        respond(false, ['error' => 'Encryption failed', 'openssl_error' => openssl_error_string()], 500);
    }
    
    // Store IV + encrypted data (IV at the beginning for decryption)
    $encryptedData = $iv . $encrypted;
    
    if (@file_put_contents($filePath, $encryptedData) === false) {
        respond(false, ['error' => 'Save failed - check permissions', 'path' => $filePath, 'dir_writable' => is_writable($uploadDir)], 500);
    }

    $now = time();
    $expires = $now + ($retentionTime * 60);

    $stmt = $db->prepare("INSERT INTO files (id, filename, file_path, upload_time, expiry_time) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$fileId, $encryptedNameB64, $storedName, $now, $expires]);
    if (!$stmt) {
        respond(false, ['error' => 'Database insert failed', 'details' => implode(' ', $db->errorInfo())], 500);
    }

    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['REQUEST_URI']);
    $path = ($path === '\\' || $path === '/') ? '' : $path;
    $url = $proto . '://' . $host . $path . '/?' . $fileId . '-' . $password;

    respond(true, [
        'fileId' => $fileId,
        'password' => $password,
        'downloadUrl' => $url,
        'expiresAt' => $expires
    ]);

} catch (Exception $e) {
    respond(false, ['error' => 'Internal server error: ' . $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 500);
}
