<?php
define('APP_VERSION', '1.1.0'); // App-Version

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} else {
    require_once __DIR__ . '/config-sample.php';
}

$hostHeader = $_SERVER['HTTP_HOST'] ?? '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parts = explode(':', $hostHeader, 2);
$hostName = $parts[0];
$hostPort = isset($parts[1]) ? ':' . $parts[1] : '';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

if ($hostName !== '') {
    $targetHost = $hostName;
    $targetScheme = $isHttps ? 'https' : 'http';

    if (defined('FORCE_HTTPS') && FORCE_HTTPS) {
        $targetScheme = 'https';
    }

    if (defined('CANONICAL_HOST') && CANONICAL_HOST !== '') {
        $isIp = filter_var($hostName, FILTER_VALIDATE_IP);
        $isLocalhost = strtolower($hostName) === 'localhost';
        if (!$isIp && !$isLocalhost) {
            $lowerHost = strtolower($hostName);
            if (CANONICAL_HOST === 'www' && strpos($lowerHost, 'www.') !== 0) {
                $targetHost = 'www.' . $hostName;
            } elseif (CANONICAL_HOST === 'non-www' && strpos($lowerHost, 'www.') === 0) {
                $targetHost = substr($hostName, 4);
            }
        }
    }

    if ($targetHost !== $hostName || $targetScheme !== ($isHttps ? 'https' : 'http')) {
        header('Location: ' . $targetScheme . '://' . $targetHost . $hostPort . $requestUri, true, 308);
        exit;
    }
}

ob_start();

define('DB_FILE', __DIR__ . '/db/qrdrop.db');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

ini_set('display_errors', '0');
error_reporting(0);

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

try {
    $query = isset($_SERVER['QUERY_STRING']) ? trim($_SERVER['QUERY_STRING']) : '';
    if ($query !== '') {
        // Parse ID-PASSWORD format from query string
        $queryParts = explode('-', $query, 2);
        $id = preg_replace('/[^a-zA-Z0-9]/', '', $queryParts[0]);
        $password = isset($queryParts[1]) ? preg_replace('/[^a-zA-Z0-9]/', '', $queryParts[1]) : '';

        if ($id === '') {
            // fall through to UI
        } else {
            $db = new PDO('sqlite:' . DB_FILE);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare("SELECT filename, file_path, expiry_time FROM files WHERE id = ?");
            $stmt->execute([$id]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$file) {
                http_response_code(404);
                ob_end_clean();
                die('Nicht gefunden');
            }

            if (time() > $file['expiry_time']) {
                http_response_code(410);
                ob_end_clean();
                die('Abgelaufen');
            }

            // Verify password is provided
            if (empty($password)) {
                http_response_code(401);
                ob_end_clean();
                die('Passwort erforderlich');
            }

            $stored = $file['file_path'];
            $fullPath = realpath(rtrim(UPLOAD_DIR, '\\/') . DIRECTORY_SEPARATOR . $stored);
            $uploadReal = realpath(rtrim(UPLOAD_DIR, '\\/'));
            if ($fullPath === false || strpos($fullPath, $uploadReal) !== 0) {
                http_response_code(404);
                ob_end_clean();
                die('Datei fehlt');
            }

            // Read encrypted data from file
            $encryptedData = @file_get_contents($fullPath);
            if ($encryptedData === false || strlen($encryptedData) < 16) {
                http_response_code(500);
                ob_end_clean();
                die('Fehler beim Lesen der Datei');
            }

            // Create 32-byte encryption key from password using SHA256
            $encryptionKey = hash('sha256', $password, true);

            // Decrypt filename
            $encryptedNameB64 = $file['filename'];
            $encryptedNameData = base64_decode($encryptedNameB64, true);
            if ($encryptedNameData === false || strlen($encryptedNameData) < 16) {
                http_response_code(500);
                ob_end_clean();
                die('Fehler beim Decodieren des Dateinamens');
            }
            $nameIv = substr($encryptedNameData, 0, 16);
            $nameCiphertext = substr($encryptedNameData, 16);
            $decryptedName = openssl_decrypt($nameCiphertext, 'AES-256-CBC', $encryptionKey, OPENSSL_RAW_DATA, $nameIv);
            if ($decryptedName === false) {
                http_response_code(500);
                ob_end_clean();
                die('Fehler beim Entschlüsseln des Dateinamens');
            }

            // Extract IV (first 16 bytes) and ciphertext from file data
            $iv = substr($encryptedData, 0, 16);
            $ciphertext = substr($encryptedData, 16);

            // Decrypt file content
            $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $encryptionKey, OPENSSL_RAW_DATA, $iv);
            if ($decrypted === false) {
                http_response_code(500);
                ob_end_clean();
                die('Fehler beim Entschlüsseln');
            }

            ob_end_clean();
            // detect file extension and set appropriate Content-Type
            $ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
            $isImage = false;
            switch ($ext) {
                case 'pdf':
                    $ctype = 'application/pdf';
                    break;
                case 'jpg':
                case 'jpeg':
                    $ctype = 'image/jpeg';
                    $isImage = true;
                    break;
                case 'png':
                    $ctype = 'image/png';
                    $isImage = true;
                    break;
                case 'zip':
                    $ctype = 'application/zip';
                    break;
                default:
                    $ctype = 'application/octet-stream';
            }

            header('Content-Type: ' . $ctype);
            // Show images inline in browser, download everything else
            if ($isImage) {
                header('Content-Disposition: inline; filename="' . basename($decryptedName) . '"');
            } else {
                header('Content-Disposition: attachment; filename="' . basename($decryptedName) . '"');
            }
            header('Content-Length: ' . strlen($decrypted));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            echo $decrypted;
            exit;
        }
    }
} catch (Throwable $e) {
    http_response_code(500);
    ob_end_clean();
    die('Fehler');
}

// Generate soft background pattern based on current second (green-blue-purple-violet spectrum)
$currentSecond = (int)date('s'); // 0-59

// Map second to hue range: green (120°) to violet (300°) = 180° span
$hue1 = 120 + ($currentSecond * 180 / 59); // Maps 0-59 to 120-300
$hue2 = $hue1 + 25; // Close hues for harmony
$hue3 = $hue1 - 15; // Slight variation

// Generate darker colors with medium saturation and lower lightness
$color1 = "hsl($hue1, 35%, 45%)";
$color2 = "hsl($hue2, 40%, 42%)";
$color3 = "hsl($hue3, 32%, 48%)";
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QRdrop - Dateien hochladen & teilen</title>
    <link rel="stylesheet" href="app.css" />
</head>
<body style="--bg-color1: <?php echo $color1; ?>; --bg-color2: <?php echo $color2; ?>; --bg-color3: <?php echo $color3; ?>">
    <!-- UI is provided by app.js and index markup below -->
    <div class="container">
        <p class="subtitle">PDF, Bild (JPG/PNG) oder ZIP-Datei hochladen und QR-Code zum Teilen generieren</p>

        <div class="error" id="error"></div>
        <div class="success" id="success"></div>
        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>Datei wird hochgeladen...</p>
        </div>

        <div id="uploadForm">
            <div class="upload-area" id="uploadArea">
                <div class="upload-icon">📤</div>
                <div class="upload-text">PDF, Bild (.jpg, .jpeg, .png) oder ZIP-Datei hier ablegen</div>
                <div class="upload-subtext">oder zum Auswählen klicken</div>
                <div class="upload-limit">max. <?php echo round(MAX_FILE_SIZE / 1024 / 1024); ?> MB</div>
                <input type="file" id="fileInput" accept=".pdf,.jpg,.jpeg,.png,.zip" />
            </div>

            <div class="file-info" id="fileInfo">
                <div class="file-name" id="fileName"></div>
                <div class="file-size" id="fileSize"></div>
            </div>

            <div class="retention-time-section">
                <label class="retention-label">⏱️ Aufbewahrungszeit:</label>
                <div class="retention-slider">
                    <input type="radio" id="retention10" name="retentionTime" value="10" checked />
                    <label for="retention10" class="retention-option">10 min</label>
                    
                    <input type="radio" id="retention60" name="retentionTime" value="60" />
                    <label for="retention60" class="retention-option">1 h</label>
                    
                    <input type="radio" id="retention720" name="retentionTime" value="720" />
                    <label for="retention720" class="retention-option">12 h</label>
                </div>
            </div>

            <div class="terms-note">Mit dem Upload akzeptierst du die <a href="#" id="termsLink">Nutzungsbedingungen</a>.</div>
            <button class="upload-button" id="uploadButton" disabled>Hochladen</button>
        </div>

        <div class="result" id="result">
            <div class="qr-code" id="qrCode"></div>
            <div class="link-container">
                <label class="link-label">Download-Link:</label>
                <div class="download-link">
                    <input type="text" id="downloadLink" readonly />
                    <button class="copy-button" id="copyButton">Kopieren</button>
                </div>
            </div>
            <div class="expires-info">
                ⏱️ Diese Datei verfällt in <span id="expiresTime">10 Minuten</span>
            </div>
            <button class="upload-button" onclick="location.reload()" style="margin-top: 20px;">Weitere Datei hochladen</button>
        </div>
    </div>

    <!-- Terms Modal -->
    <div id="termsModal" class="modal" aria-hidden="true">
        <div class="modal-overlay" id="termsOverlay"></div>
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="termsTitle">
            <button class="modal-close" id="termsClose" aria-label="Schließen">×</button>
            <h2 id="termsTitle">Nutzungsbedingungen</h2>
            <div id="termsBody">Inhalt wird geladen...</div>
        </div>
    </div>

    <!-- Impressum Modal -->
    <div id="impressumModal" class="modal" aria-hidden="true">
        <div class="modal-overlay" id="impressumOverlay"></div>
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="impressumTitle">
            <button class="modal-close" id="impressumClose" aria-label="Schließen">×</button>
            <div id="impressumBody">Inhalt wird geladen...</div>
        </div>
    </div>

    <!-- About Modal -->
    <div id="aboutModal" class="modal" aria-hidden="true">
        <div class="modal-overlay" id="aboutOverlay"></div>
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="aboutTitle">
            <button class="modal-close" id="aboutClose" aria-label="Schließen">×</button>
            <div id="aboutBody">Inhalt wird geladen...</div>
        </div>
    </div>

    <div class="version-info">v<?php echo APP_VERSION; ?> | <a href="#" id="aboutLink">About</a> | <a href="#" id="impressumLink">Impressum und Datenschutz</a></div>
    <script>
        // Maximale Dateigröße (in Bytes) von config.php
        const MAX_FILE_SIZE = <?php echo MAX_FILE_SIZE; ?>;
    </script>
    <script src="qrcode.min.js"></script>
    <script src="app.js?v=<?php echo filemtime(__DIR__ . '/app.js'); ?>"></script>
</body>
</html>

