# NEMA

## Stories-Administration einrichten

1. `database/schema.sql` mit einem eingeschränkten Anwendungsbenutzer importieren. Zur Laufzeit benötigt dieser nur `SELECT`, `INSERT`, `UPDATE` und `DELETE` auf den vier Tabellen; Schema- oder globale Rechte sind nicht erforderlich.
2. Wie die vorhandenen APIs wird `../config.php` außerhalb des Webroots geladen. Die Datei muss `$pdo` als PDO-Verbindung bereitstellen. Zugangsdaten werden nicht im Repository gespeichert.
3. Den ersten Admin ausschließlich per CLI erstellen:
   `NEMA_ADMIN_USERNAME='admin' NEMA_ADMIN_PASSWORD='ein-langes-zufaelliges-passwort' php bin/create-admin.php`
4. Apache muss `.htaccess` erlauben (`AllowOverride FileInfo Options AuthConfig`). Bei einem TLS-Terminierungsproxy dessen feste IP über `NEMA_TRUSTED_PROXIES` (kommagetrennt) setzen; niemals ein ganzes unkontrolliertes Netz eintragen.
5. Der Webserver-Prozess benötigt Schreibrechte ausschließlich auf `uploads/stories/`. PHP-Ausführung ist dort über `.htaccess` deaktiviert; für nginx ist die äquivalente Deny-/No-script-Regel in der Serverkonfiguration zu setzen.

Der Admin ist unter `/admin/`, die öffentliche Übersicht unter `/stories/` erreichbar. In Produktion sollten PHP-Fehler in ein nicht öffentliches Systemlog geschrieben, TLS/HSTS am Webserver verwaltet und Audit-/Login-Datensätze nach der betrieblichen Aufbewahrungsrichtlinie regelmäßig bereinigt werden.
