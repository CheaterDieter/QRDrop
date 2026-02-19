# Upload-Limits Fehlerbehebung

Wenn du große Dateien hochladen möchtest, müssen PHP-Limits erhöht werden.

## Schritt 1: Automatische Methoden ✓

QRdrop versucht die Limits automatisch zu erhöhen über:

1. **`.user.ini`** (Funktioniert bei PHP-FPM und FastCGI)
   - Diese Datei wird automatisch von PHP gelesen
   - Keine Serverneustart nötig
   - ⭐ Empfohlen

2. **`.htaccess`** (Funktioniert nur bei Apache mit mod_php)
   - Nur wenn AllowOverride aktiviert ist
   - Nicht bei Nginx oder PHP-FPM

## Schritt 2: Manuell via Server-Admin

Wenn die automatischen Methoden nicht funktionieren, kontaktiere deinen Server-Admin und bitte ihn, folgende Einstellungen in der `php.ini` oder FPM-Konfiguration anzupassen:

```ini
upload_max_filesize = 500M
post_max_size = 500M  
memory_limit = 512M
max_execution_time = 600
max_input_time = 600
```

## Schritt 3: Test

Nach Änderungen:
1. Browser-Cache leeren (Ctrl+Shift+Delete)
2. Neue Datei hochladen versuchen
3. Bei Fehler: Fehlermeldung zeigt aktuelles Limit

## Fehlermeldung Interpretation

```
Datei zu groß! Aktuelles Limit: 40M (upload_max_filesize: 40M, post_max_size: 40M)
```

Bedeutet: Die Limits sind immer noch auf 40 MB. Gehe zu Schritt 2.

## Bei PHP-FPM Hosting

Falls du auf einem Hosting mit PHP-FPM bist:
1. Lade `.user.ini` hoch
2. Warte 5 Minuten (Cache)
3. Test erneut

Wenn das nicht funktioniert, bitte deinen Hoster um Limit-Erhöhung via Control Panel (cPanel, Plesk, etc.)
