<?php
/**
 * Diese Datei regelmäßig per Cronjob aufrufen, um abgelaufene Dateien zu löschen und die Datenbank zu bereinigen.
 */

define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('DB_FILE', __DIR__ . '/db/qrdrop.db');

ini_set('display_errors', '0');
error_reporting(0);

function formatBytes($bytes) {
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    if ($bytes == 0) return '0 Bytes';
    $i = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), 2) . ' ' . $sizes[$i];
}

function getDirSize($path) {
    $size = 0;
    if (is_dir($path)) {
        $files = @scandir($path);
        if ($files) {
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && $file !== 'index.php' && $file !== 'index.html' && $file !== '.htaccess') {
                    $fullPath = $path . '/' . $file;
                    if (is_file($fullPath)) {
                        $size += filesize($fullPath);
                    }
                }
            }
        }
    }
    return $size;
}

$summary = [
    'deleted_files' => [],
    'deleted_db' => [],
    'deleted_orphans' => [],
    'deleted_missing_db' => [],
    'errors' => [],
];

try {
    if (!is_dir(UPLOAD_DIR)) {
        @mkdir(UPLOAD_DIR, 0777, true);
    }

    $db = new PDO('sqlite:' . DB_FILE);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $currentTime = time();

    // Delete expired files
    $stmt = $db->prepare("SELECT id, file_path FROM files WHERE expiry_time <= ?");
    $stmt->execute([$currentTime]);
    $expiredFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($expiredFiles as $file) {
        try {
            $fullPath = rtrim(UPLOAD_DIR, '\\/') . DIRECTORY_SEPARATOR . $file['file_path'];
            if (file_exists($fullPath)) {
                if (@unlink($fullPath)) {
                    $summary['deleted_files'][] = $file['file_path'];
                } else {
                    $summary['errors'][] = 'Failed to delete file: ' . $fullPath;
                }
            }

            $deleteStmt = $db->prepare("DELETE FROM files WHERE id = ?");
            $deleteStmt->execute([$file['id']]);
            $summary['deleted_db'][] = $file['id'];
        } catch (Exception $e) {
            $summary['errors'][] = 'Exception for id ' . $file['id'] . ': ' . $e->getMessage();
        }
    }

    // Clean up orphaned files
    $stmt = $db->query("SELECT file_path FROM files");
    $registeredFiles = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

    if (is_dir(UPLOAD_DIR)) {
        $files = @scandir(UPLOAD_DIR);
        if ($files) {
            foreach ($files as $f) {
                if ($f === '.' || $f === '..' || $f === 'index.php' || $f === 'index.html' || $f === '.htaccess') continue;
                if (!in_array($f, $registeredFiles)) {
                    $full = rtrim(UPLOAD_DIR, '\\/') . DIRECTORY_SEPARATOR . $f;
                    if (@unlink($full)) {
                        $summary['deleted_orphans'][] = $f;
                    }
                }
            }
        }
    }

    // Clean up DB entries for missing files
    $stmt = $db->query("SELECT id, file_path FROM files");
    $dbFiles = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    foreach ($dbFiles as $row) {
        $full = rtrim(UPLOAD_DIR, '\\/') . DIRECTORY_SEPARATOR . $row['file_path'];
        if (!file_exists($full)) {
            $deleteStmt = $db->prepare("DELETE FROM files WHERE id = ?");
            $deleteStmt->execute([$row['id']]);
            $summary['deleted_missing_db'][] = $row['id'];
        }
    }

    // Get all files for statistics only
    $stmt = $db->query("SELECT COUNT(*) FROM files");
    $totalFiles = $stmt ? $stmt->fetchColumn() : 0;

    $dirSize = getDirSize(UPLOAD_DIR);

    // Output concise text summary for cron logs
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/plain; charset=utf-8');
    }

    echo "QRdrop Cron Summary\n";
    echo "===================\n";
    echo date('Y-m-d H:i:s') . "\n\n";
    echo "Upload Directory: " . formatBytes($dirSize) . "\n";
    echo "Total Files: " . $totalFiles . "\n\n";
    echo "Cleanup Results:\n";
    echo "- Expired files deleted: " . count($summary['deleted_files']) . "\n";
    echo "- DB entries removed: " . count($summary['deleted_db']) . "\n";
    echo "- Orphaned files deleted: " . count($summary['deleted_orphans']) . "\n";
    echo "- DB entries without file removed: " . count($summary['deleted_missing_db']) . "\n";
    
    if (count($summary['errors']) > 0) {
        echo "\nErrors (" . count($summary['errors']) . "):\n";
        foreach ($summary['errors'] as $error) {
            echo "- " . $error . "\n";
        }
        exit(1);
    }

    exit(0);

} catch (Exception $e) {
    if (php_sapi_name() !== 'cli') {
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo "QRdrop cron failed: " . $e->getMessage() . "\n";
    exit(1);
}