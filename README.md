# NEMA

## Stories-Administration einrichten

1. `database/schema.sql` mit einem eingeschränkten Anwendungsbenutzer importieren. Zur Laufzeit benötigt dieser nur `SELECT`, `INSERT`, `UPDATE` und `DELETE` auf den vier Tabellen; Schema- oder globale Rechte sind nicht erforderlich.
2. Wie die vorhandenen APIs wird `../config.php` außerhalb des Webroots geladen. Die Datei muss `$pdo` als PDO-Verbindung bereitstellen. Zugangsdaten werden nicht im Repository gespeichert.
3. Den ersten Admin ausschließlich per CLI erstellen:
   `NEMA_ADMIN_USERNAME='admin' NEMA_ADMIN_PASSWORD='ein-langes-zufaelliges-passwort' php bin/create-admin.php`
4. Apache muss `.htaccess` erlauben (`AllowOverride FileInfo Options AuthConfig`). Bei einem TLS-Terminierungsproxy dessen feste IP über `NEMA_TRUSTED_PROXIES` (kommagetrennt) setzen; niemals ein ganzes unkontrolliertes Netz eintragen.
5. Der Webserver-Prozess benötigt Schreibrechte ausschließlich auf `uploads/stories/`. PHP-Ausführung ist dort über `.htaccess` deaktiviert; für nginx ist die äquivalente Deny-/No-script-Regel in der Serverkonfiguration zu setzen.

Der Admin ist unter `/admin/`, die öffentliche Übersicht unter `/stories/` erreichbar. In Produktion sollten PHP-Fehler in ein nicht öffentliches Systemlog geschrieben, TLS/HSTS am Webserver verwaltet und Audit-/Login-Datensätze nach der betrieblichen Aufbewahrungsrichtlinie regelmäßig bereinigt werden.

## Job-Finder mit n8n verbinden

Der Browser kommuniziert ausschließlich mit den PHP-Proxys unter `/api/`. Lege auf dem Server **außerhalb des Repository-/Webroot-Verzeichnisses** die Datei `../job-finder.local.php` an (bei diesem Deployment also neben dem Verzeichnis `article-finder`):

```php
<?php
return [
    'N8N_JOB_FINDER_UPLOAD_URL' => 'https://appwbs.app.n8n.cloud/webhook/job-finder/cv-upload',
    'N8N_JOB_FINDER_SEARCH_URL' => 'https://appwbs.app.n8n.cloud/webhook/job-finder/search',
    'N8N_JOB_FINDER_SECRET' => 'HIER_DAS_NEUE_GEHEIME_SECRET_EINTRAGEN',
];
```

Alternativ können dieselben drei Namen als Server-Umgebungsvariablen gesetzt werden; sie haben Vorrang. Die lokale Datei und insbesondere ihr Secret dürfen nicht committed, ausgeliefert oder unter dem Webroot abgelegt werden. PHP benötigt cURL, Fileinfo, JSON und mbstring.
