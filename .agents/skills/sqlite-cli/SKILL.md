---
name: sqlite-cli
description: Permite ejecutar consultas SQL en bases de datos SQLite locales.
---

# Instrucciones

Utiliza esta habilidad para consultar la base de datos SQLite del proyecto sin depender de un servidor MCP externo.

## Uso Básico

```powershell
php .agents/skills/sqlite-cli/scripts/query.php "database/database.sqlite" "SELECT * FROM users LIMIT 5;"
```
