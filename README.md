# QRdrop: PDF- and Picture-Upload & QRCode Sharing

Schlankes, auf PHP, JS und SQLite-basierendes System zum Hochladen von PDF- und Bilddateien. Nach dem Upload wird ein kurzer Link + QRCode erzeugt. Hochgeladene Dateien laufen nach voreingesteller Zeit automatisch ab und werden vom CronJob gelöscht.
Dateinamen und Inhalte werden auf dem Server verschlüsselt gespeichert. Der Schlüssel zur Entschlüsselung ist Teil der URL, die nach dem Upload anzeigt wird. Er wird nicht auf dem Server gespeichert. Dateien können somit nur über die korrekte URL abgerufen und wieder entschlüsselt werden. Für den Serverbetreiber sind Dateiname und -inhalt nicht sichtbar.

## Projektstruktur

```
QRdrop/
 index.php              # Web-Entrypoint (UI + Download-Routing via /?ID)
 app.js                 # ClientJS (Upload, QR-Rendering, UI-Logik)
 app.css                # Stylesheet
 upload.php             # Upload API 
 cron.php               # Cleanup-Skript (löscht abgelaufene Einträge + Dateien) -> per Cronjob regelmäßig aufrufen
 qrcode.min.js          # QR-Code Generator Library qrcode.js von davidshimjs
 uploads/               # Verzeichnis für hochgeladene, verschlüsselte Dateien
 db/                    # Verzeichnis für SQLite-Datenbank
 terms.html             # Nutzungsbedingungen (wird per Modal geladen)
 about.html             # Über QRdrop (wird per Modal geladen)
 impressum.html         # Impressum und Datenschutzerklärung (wird per Modal geladen, nicht synchronisiert)
 impressum-sample.html  # Template für Impressum und Datenschutzerklärung
 about-sample.html      # Template für Über QRdrop
 .gitignore             # Git-Ignoriere-Regeln
 README.md
```

