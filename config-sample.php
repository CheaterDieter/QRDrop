<?php
/**
 * Anpassung der Dateigrößenlimits und weiterer Einstellungen
 */

// Maximale Dateigröße pro Upload (in Bytes)
// Standard: 50 MB (50 * 1024 * 1024)
// Beispiel für 500 MB: 500 * 1024 * 1024
// Hinweis: upload.php versucht, PHP-Limits automatisch anzupassen, aber es ist wichtig, dass diese Werte mit den PHP-Einstellungen übereinstimmen.
define('MAX_FILE_SIZE', 50 * 1024 * 1024);

// Maximales Speicherlimit für das uploads/ Verzeichnis (in Bytes)
// Standard: 5 GB
define('MAX_STORAGE_SIZE', 5 * 1024 * 1024 * 1024);

// Canonical-Host-Weiterleitung (leerer String deaktiviert)
// Mögliche Werte: '', 'www', 'non-www'
define('CANONICAL_HOST', '');

// HTTPS-Umleitung erzwingen
// true = immer auf https umleiten (sofern verfuegbar)
define('FORCE_HTTPS', false);
?>
