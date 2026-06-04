# Radio Player Structure

Documento de referencia para entender la estructura real del reproductor de audio.

Este componente no es una sola caja. Tiene dos maquetas principales:

1. Dock superior
2. Panel expandido

Ademas, cada una cambia segun el estado:

- `dockMinimized = true` -> estado compacto
- `dockMinimized = false` -> estado expandido
- `panelOpen = true` -> panel expandido visible
- `x-show` / `x-cloak` -> nodos que entran o salen del flujo
- media queries -> cambios de layout en desktop y mobile

## Archivo principal

- Blade: [`resources/views/components/radio/player.blade.php`](../resources/views/components/radio/player.blade.php)
- CSS: [`resources/css/app.css`](../resources/css/app.css)

## Vista general

```text
RADIO PLAYER
|
|-- DOCK (barra superior flotante)
|   |
|   |-- Bloque 1: portada / cover
|   |   |-- pill LIVE / PLAYBACK
|   |   |-- imagen de portada
|   |   |-- acciones Share / Popup
|   |
|   |-- Bloque 2: metadatos
|   |   |-- linea roja
|   |   |-- titulo
|   |   |-- artista / grupo
|   |   |-- programa / proximo programa
|   |   |-- contador
|   |
|   |-- Bloque 3: acciones centrales
|   |   |-- details
|   |   |-- play / pause
|   |   |-- minimize / expand
|   |   |-- favorite
|   |
|   |-- Bloque 4: lado derecho
|       |-- mute / volumen
|       |-- redes sociales
|
|-- PANEL EXPANDIDO
    |
    |-- izquierda: portada + copys + controles
    |-- derecha: tabs + popup + contenido de pestañas
```

## Orden real del dock

```text
.radio-player-dock
|
|-- .rbcloud_nowplaying.radio-player-dock-cover-wrap
|   |-- badge LIVE / PLAYBACK
|   |-- .radio-player-dock-trigger
|   |   |-- imagen cover
|   |-- .radio-player-dock-share-popout-desktop
|   |   |-- Share
|   |   |-- Popup
|   |-- .radio-player-mobile-share-popout
|
|-- .radio-player-meta-column
|   |-- .radio-player-dock-copy
|   |   |-- .radio-player-dock-line
|   |   |-- .radio-player-dock-meta
|   |   |   |-- titulo
|   |   |   |-- artista
|   |   |   |-- programa
|   |   |   |-- proximo programa
|   |   |-- .radio-player-dock-timer
|
|-- .radio-player-actions.radio-player-dock-actions
|   |-- details
|   |-- play / pause
|   |-- minimize / expand
|   |-- favorite
|
|-- .radio-player-dock-side
    |-- mute / volumen
    |-- redes sociales
```

## Por que a veces "mover algo" no cambia nada

Porque el nodo puede estar:

- dentro de otro contenedor que ya define su posicion
- oculto por `x-show`
- reordenado por `flex` o `grid`
- afectado por una media query distinta en desktop o mobile
- duplicado en otra maqueta del mismo componente

En otras palabras: mover un elemento en Blade no cambia la composicion si el padre sigue mandando el layout.

## Reglas de edicion

Si se quiere tocar el reproductor sin romperlo:

1. Identificar primero el estado visible
2. Encontrar el contenedor padre que manda la geometria
3. Mover solo el bloque correcto
4. Ajustar el CSS del padre, no solo del hijo
5. Probar en:
   - dock compacto
   - dock expandido
   - panel expandido
   - mobile

## Cambios que deben hacerse en un caso como este

### Para textos debajo de la linea roja

La estructura debe quedar asi:

```text
[caratula]   [linea roja interna]
             [titulo]
             [grupo]
             [contador]
```

Eso exige que el bloque de metadatos viva en una columna separada a la derecha de la caratula:

```text
flex-direction: column;
```

En el panel expandido actual, esa linea se trata como un divisor visual interno del bloque de copys, no como una guia externa.

### Para Share + Popup en la misma fila

La estructura debe quedar asi:

```text
[Share] [Popup]
```

Eso exige:

```text
display: flex;
flex-direction: row;
gap: 10px;
```

## CSS clave

Las clases mas relevantes para este layout son:

- `.radio-player-dock`
- `.radio-player-dock-cover-wrap`
- `.radio-player-meta-column`
- `.radio-player-dock-copy`
- `.radio-player-dock-line`
- `.radio-player-dock-meta`
- `.radio-player-dock-share-popout-desktop`
- `.radio-player-dock-side`
- `.radio-player-actions`
- `.radio-player-panel--dock .player-expanded-container`

## Nota practica

Si una edicion parece no tener efecto, normalmente el problema no esta en el nodo que moviste, sino en:

- el padre que lo posiciona
- la otra maqueta del mismo componente
- una regla responsive que lo sobreescribe

## Resumen mental rapido

```text
HTML define el orden
CSS define la geometria
Alpine define la visibilidad
Responsive define variantes
```

Cuando haya que editar el reproductor, revisar estas cuatro capas antes de asumir que el cambio "no funciona".
