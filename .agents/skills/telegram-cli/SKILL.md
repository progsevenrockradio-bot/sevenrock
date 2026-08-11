---
name: telegram-cli
description: Permite interactuar con la API de Telegram usando el bot del usuario.
---

# Instrucciones

Utiliza esta habilidad para recibir mensajes o enviar respuestas a través del Bot de Telegram configurado en el proyecto.

## Uso Básico

Para obtener los últimos mensajes del bot:
```powershell
php .agents/skills/telegram-cli/scripts/bot.php getUpdates
```

Para enviar un mensaje:
```powershell
php .agents/skills/telegram-cli/scripts/bot.php sendMessage <chat_id> "Tu mensaje aquí"
```

> **Nota:** La habilidad usa automáticamente la clave `TELEGRAM_BOT_TOKEN` de tu `.env`.
