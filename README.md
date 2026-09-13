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

Der Job-Finder unter `/sites/job-finder.php` kommuniziert ausschließlich über die PHP-Proxys in `/api/` mit n8n. Vor dem produktiven Einsatz müssen diese Werte in der privaten Serverumgebung (nicht im Webroot und nicht im Repository) gesetzt werden:

- `N8N_JOB_FINDER_UPLOAD_URL`: vollständige HTTPS-URL des Upload-Webhooks
- `N8N_JOB_FINDER_SEARCH_URL`: vollständige HTTPS-URL des Such-Webhooks
- `N8N_JOB_FINDER_WEBHOOK_SECRET`: gemeinsames Bearer-Secret für die authentifizierte Server-zu-Server-Kommunikation

PHP benötigt die Erweiterungen cURL, Fileinfo, JSON und mbstring. Die Webhooks müssen die in der UI erwarteten JSON-Strukturen liefern; ohne die drei Konfigurationswerte zeigt die Oberfläche bewusst nur eine neutrale Fehlermeldung.
