# App-Icons für QRdrop

`icon.svg` ist ein einfaches Platzhalter-Icon (wird von Android/Chrome direkt unterstützt).

Für die beste Darstellung auf iOS/iPadOS solltest du zusätzlich echte PNG-Dateien ablegen
(iOS ignoriert SVG-Icons beim "Zum Home-Bildschirm hinzufügen"):

| Datei | Größe | Verwendung |
|---|---|---|
| `apple-touch-icon.png` | 180×180 | Home-Bildschirm-Icon auf iOS |
| `icon-192.png` | 192×192 | Android/Chrome Manifest |
| `icon-512.png` | 512×512 | Android/Chrome Manifest (Splash Screen) |
| `icon-maskable-512.png` | 512×512 | Android "maskable" Icon (Motiv mit Sicherheitsabstand zum Rand) |

Du kannst z.B. mit https://realfavicongenerator.net/ oder https://maskable.app/editor
aus `icon.svg` passende PNGs erzeugen und hier ablegen. Ohne diese Dateien funktioniert
die Web-App weiterhin, iOS verwendet dann aber nur einen Screenshot als Icon.
