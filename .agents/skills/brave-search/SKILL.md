---
name: brave-search
description: Permite realizar búsquedas en la web utilizando la API de Brave Search.
---

# Instrucciones

Utiliza esta habilidad para buscar información actualizada en internet usando Brave Search, sin requerir configuración MCP externa.

## Uso Básico

```powershell
php .agents/skills/brave-search/scripts/search.php "Noticias de rock 2026"
```

> **Nota:** Requiere que la clave `BRAVE_API_KEY` esté configurada en el archivo `.env`.
