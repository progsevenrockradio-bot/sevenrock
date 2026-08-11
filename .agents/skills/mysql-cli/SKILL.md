---
name: mysql-cli
description: Permite al agente ejecutar consultas SQL en la base de datos local de Laragon para depurar y leer esquemas.
---

# MySQL Local Skill

Cuando necesites consultar la base de datos local del proyecto, utiliza la herramienta `run_command` para ejecutar el script `query.php` que se encuentra en esta misma carpeta.

**Uso:**
Debes pasar la consulta SQL entre comillas como argumento.

```bash
php .agents/skills/mysql-cli/scripts/query.php "SELECT * FROM users LIMIT 5"
```

El script se conecta automáticamente usando las credenciales del `.env` de Laravel y te devolverá los resultados en formato JSON.
