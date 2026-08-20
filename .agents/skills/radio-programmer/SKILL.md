---
name: radio-programmer
description: Herramientas para auditar, limpiar y renombrar la librería musical de RadioBOSS.
---

# Radio Programmer Skill

Esta habilidad automatiza las tareas de un Director Musical para Seven Rock Radio.

## Comandos Disponibles

### 1. Renombrar MP3s usando ID3 Tags
Usa este script para organizar la carpeta local donde se descargan las bandas nuevas antes de subirlas al FTP. Renombrará los archivos a `Artista - Título.mp3`.
```bash
php .agents/skills/radio-programmer/scripts/mp3_renamer.php "C:\Ruta\A\Tus\Descargas"
```

### 2. Auditar Librería Musical (FTP o Local)
Genera un listado recomendando qué canciones mantener y cuáles borrar, basado en reglas de curaduría (peso, calidad, b-sides).
```bash
php .agents/skills/radio-programmer/scripts/library_auditor.php
```

### 3. Limpiar Librería (Ejecutor)
Una vez aprobado el listado generado por el auditor, este script borrará físicamente los archivos marcados para eliminación.
```bash
php .agents/skills/radio-programmer/scripts/library_cleaner.php
```
