# Radio Modal Band Structure

Documento de referencia para entender la estructura de la ventana modal de banda del reproductor.

Esta modal no es una sola caja plana. Tiene varias capas:

1. Overlay exterior
2. Contenedor principal
3. Estado de carga
4. Contenido real
5. Pestañas internas
6. Cache visual de carátula por pestaña

## Archivo principal

- Blade: [`resources/views/components/radio/player.blade.php`](../resources/views/components/radio/player.blade.php)
- CSS: [`resources/css/app.css`](../resources/css/app.css)

## Vista general

```text
radio-modal-overlay--band
└── radio-modal-container--band
    ├── botón cerrar
    └── radio-modal-stage--band
        ├── estado loading: radio-modal-skeleton
        └── estado normal: radio-modal-content--band
            ├── header superior
            │   ├── imagen / carátula
            │   └── copy superior
            │       ├── título
            │       ├── artista
            │       ├── resumen breve / intro
            │       ├── label de fundación
            │       └── tags opcionales
            ├── tabs
            │   ├── Biografía
            │   └── Letras
            └── body
                ├── panel bio
                │   ├── card "Biografía"
                │   │   └── `biographyExpanded`
                │   ├── links opcionales
                │   └── card "Integrantes"
                └── panel lyrics
                    └── card "Letra"
                        └── `track.lyrics`

La carátula de la modal no usa un único `src` mutable. Tiene dos capas:

- una capa para la vista `bio`, con la imagen externa de la banda
- una capa para la vista `lyrics`, con la carátula del reproductor

La pestaña de la modal usa `bandWindowTab` y no `activeTab`, para no arrastrar el estado de las pestañas del reproductor de fondo.

La apertura del modal hace un pre-cargado de ambas imágenes antes de soltar el contenido, y el bloque de pestañas trabaja con una altura fija para evitar micro saltos al alternar entre `Biografía` y `Letra`.

La pestaña `Biografía` ahora muestra una etiqueta de procedencia:

- `Biografía real`
- `Resumen editorial`
- `Texto de respaldo`
```

## Campos que se reflejan

Estos son los datos que alimentan la modal:

- `bandPanel.title`
- `track.title`
- `bandPanel.artist`
- `track.artist`
- `bandPanel.info`
- `track.band_info`
- `bandPanel.biography`
- `track.band_biography`
- `bandPanel.foundedLabel`
- `track.band_founded_label`
- `bandPanel.country`
- `track.band_country`
- `bandPanel.genre`
- `track.band_genre`
- `bandPanel.membersCount`
- `track.band_members_count`
- `bandPanel.status`
- `track.band_status`
- `bandPanel.logo`
- `track.band_logo`
- `track.band_members`
- `resumenBio`
- `biographyExpanded`
- `track.lyrics`

## Organización real

### 1. Overlay

`radio-modal-overlay--band` controla:

- la capa oscura del fondo
- el cierre al hacer click fuera
- el cierre con `Escape`

### 2. Contenedor

`radio-modal-container--band` controla:

- el ancho máximo
- el borde
- el radio
- el fondo
- la sombra
- el padding

### 3. Stage

`radio-modal-stage--band` contiene dos estados:

- `bandInfoLoading` -> skeleton de carga
- `!bandInfoLoading` -> contenido real

### 4. Header superior

El header superior agrupa:

- carátula / imagen
- título
- artista
- etiqueta de fundación
- tags

Ese bloque está pensado para ser un resumen visual rápido de la banda.

El texto superior breve sale de `editorial_summary` y, si no existe, cae a `biography` o al `band_info` ya resuelto. La biografía larga se pinta aparte dentro del panel `bio`.

### 5. Tabs

Las pestañas son:

- `Biografía`
- `Letras`

El estado activo se controla con `activeTab`.

### 6. Body

El body tiene dos paneles alternables:

- `activeTab === 'bio'`
- `activeTab === 'lyrics'`

## Qué se puede mover

Sí se puede mover sin romper la lógica:

- el orden visual de la carátula y el copy superior
- la mini biografía dentro del panel `bio`
- la posición de `Integrantes` dentro del panel `bio`
- el espaciado entre cards
- la separación entre tabs y contenido
- el orden visual de los bloques internos del body

## Qué no conviene mover sin tocar lógica

No conviene mover directamente sin revisar la lógica:

- `x-show="activeTab === 'bio'"`
- `x-show="activeTab === 'lyrics'"`
- `bandInfoLoading`
- `resumenBio`
- `track.lyrics`
- `track.band_members`

Esos elementos no son solo visuales. También gobiernan qué se ve y cuándo se ve.

## Cómo está pensada la parte de biografía

La pestaña `Biografía` contiene una card principal con:

- título de sección
- texto de `resumenBio`
- links opcionales

Después puede aparecer otra card con:

- lista de integrantes

Si la biografía te queda muy corta, ese contenido puede moverse dentro de la misma card o dividirse en dos bloques más claros, pero siempre dentro del panel `bio`.

## Cómo está pensada la parte de letra

La pestaña `Letras` contiene una card única con:

- título de sección
- texto de `track.lyrics`

Si quieres separar visualmente `Biografía` y `Letra`, el ajuste correcto está en:

- el layout de `radio-modal-body`
- el espaciado de `radio-modal-card`
- el padding / gap de `radio-modal-scroll`

## Regla práctica

Si algo parece "amontonado", normalmente el problema no está en el dato, sino en:

- el contenedor padre
- el `gap`
- el `padding`
- el `overflow`
- el estado activo de la pestaña

## Resumen mental rápido

```text
HTML define el orden
CSS define la separación
Alpine define la visibilidad
Los datos definen el contenido
```

## Nota de uso

Cuando haya que editar esta modal:

1. Identifica si estás en `bio` o `lyrics`
2. Revisa qué dato alimenta cada bloque
3. Ajusta el layout del `body`
4. Ajusta el CSS de cards y tabs
5. No muevas el `x-show` sin entender su impacto
