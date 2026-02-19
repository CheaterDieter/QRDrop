<?php
/**
 * QRdrop Konfiguration
 * Hier können Sie die Haupteinstellungen anpassen
 */

// Maximale Dateigröße pro Upload (in Bytes)
// Standard: 50 MB (50 * 1024 * 1024)
// Beispiel für 500 MB: 500 * 1024 * 1024
// Hinweis: upload.php passt PHP-Limits automatisch an
define('MAX_FILE_SIZE', 50 * 1024 * 1024);

// Maximales Speicherlimit für das uploads/ Verzeichnis (in Bytes)
// Standard: 5 GB
define('MAX_STORAGE_SIZE', 5 * 1024 * 1024 * 1024);
?>
