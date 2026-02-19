# QRdrop: PDF- and Picture-Upload & QRCode Sharing

Schlankes, auf PHP, JS und SQLite-basierendes System zum Hochladen von PDF-, ZIP- und Bilddateien. Nach dem Upload wird ein kurzer Link + QRCode erzeugt. Hochgeladene Dateien laufen nach voreingesteller Zeit automatisch ab und werden vom CronJob gelöscht.
Dateinamen und Inhalte werden auf dem Server verschlüsselt gespeichert. Der Schlüssel zur Entschlüsselung ist Teil der URL, die nach dem Upload anzeigt wird. Er wird nicht auf dem Server gespeichert. Dateien können somit nur über die korrekte URL abgerufen und wieder entschlüsselt werden. Für den Serverbetreiber sind Dateiname und -inhalt nicht sichtbar.

## ToDo

- Cronjob auf cron.php
- impressum.html anlegen
