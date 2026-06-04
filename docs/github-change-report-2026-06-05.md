# Informe de cambios - 2026-06-05

## Alcance

Últimos ajustes aplicados al reproductor de radio, su modal de banda y la versión móvil/popup.

## Resumen técnico

### 1. Modal de banda estabilizada
- La ventana modal ahora usa un snapshot congelado al abrirse.
- `Biografía` y `Letras` ya no dependen del estado vivo del reproductor principal.
- Se redujo el salto visual al alternar pestañas.

### 2. Separación de texto biográfico
- `editorial_summary` se usa como resumen breve.
- `biography` se muestra como bloque ampliado cuando existe.
- Se añadió fallback controlado para evitar pestañas vacías.

### 3. Imagen y contenido precargados
- Las imágenes se precargan antes de mostrar la modal.
- La carátula de banda y la del reproductor se mantienen estables durante la apertura.
- Se fijó la altura del panel de pestañas para minimizar reflows.

### 4. Mejora visual de pestañas
- Las pestañas `Biografía` y `Letras` tienen más separación.
- En escritorio se espacian más.
- En móvil se compactan para no consumir ancho excesivo.

### 5. Versión móvil
- El drawer de banda en móvil usa un fallback más estable.
- Se compactaron alturas, paddings y badges de origen.
- Se reforzó la coherencia visual entre desktop y móvil.
- Se corrigió la visibilidad de los controles del dock en móvil:
  - `Detalles`
  - `Play`
  - `Expandir / Minimizar`
  - `Me gusta`

### 6. Estructura documentada
- Se añadieron documentos permanentes de referencia:
  - `docs/radio-player-structure.md`
  - `docs/radio-modal-band-structure.md`

## Archivos principales tocados

- `app/Http/Controllers/Api/BandInfoController.php`
- `resources/js/player.js`
- `resources/views/components/radio/player.blade.php`
- `resources/css/app.css`
- `resources/views/components/layouts/site.blade.php`
- `public/build/*`

## Validación

- `php -l` sin errores en los archivos revisados.
- `npm run build` ejecutado correctamente.

## Resultado

El reproductor queda más estable, la modal de banda no hace reflows bruscos, la información biográfica se muestra con jerarquía clara y fallback seguro, y los controles del dock móvil vuelven a ser visibles.
