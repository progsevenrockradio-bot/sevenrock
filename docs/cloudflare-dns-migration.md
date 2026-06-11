# Guía de Migración de DNS a Cloudflare (Subdominio `media`)

Esta guía detalla los pasos necesarios para migrar la gestión de tus DNS desde Hostinger (servidores actuales `dns-parking.com`) hacia Cloudflare. Esto permitirá activar el subdominio personalizado `media.sevenrockradio.com` como proxy de Backblaze B2 bajo HTTPS con certificado SSL y caché activa.

---

## ⚠️ IMPORTANTE: Riesgo de Caída del Sitio
Al cambiar los Nameservers (servidores de nombres), delegas la autoridad de tus DNS a Cloudflare. **Si no copias los registros actuales antes de hacer el cambio, tu sitio web y tus correos dejarán de funcionar.** Sigue los pasos de preparación minuciosamente.

---

## Paso 1: Recopilar los Registros DNS Actuales en Hostinger
Antes de cambiar nada, ingresa al panel de Hostinger (hPanel) > **Dominio** > **Editor de Zona DNS** y anota o exporta todos tus registros activos. Los más críticos son:

1. **Registro A principal**:
   - Host: `@` (o `sevenrockradio.com`)
   - Apunta a: IP del servidor de Hostinger (ej. `84.32.84.46`).
2. **Registro CNAME para www**:
   - Host: `www`
   - Apunta a: `sevenrockradio.com`.
3. **Registros MX (Correos)**:
   - Apuntan a los servidores de correo de tu proveedor (ej. Hostinger, Google Workspace, etc.).
4. **Registros TXT (SPF/DKIM/DMARC)**:
   - Registros de seguridad de correo (ej. `"v=spf1 include:_spf.mail.hostinger.com ~all"`).

---

## Paso 2: Configurar Cloudflare
1. Inicia sesión en tu cuenta de [Cloudflare](https://dash.cloudflare.com/).
2. Haz clic en **Add a Site** (Añadir un sitio) e ingresa `sevenrockradio.com`.
3. Selecciona el **Plan Gratuito** (Free).
4. Cloudflare escaneará automáticamente tus registros DNS. 
5. **Verificación Manual**: Compara la lista detectada por Cloudflare con la lista que copiaste en el **Paso 1**. Si falta algún registro (especialmente el registro `A` o registros `MX`), agrégalos manualmente en Cloudflare.

---

## Paso 3: Agregar el Registro de Backblaze B2 en Cloudflare
Dentro de la pestaña **DNS > Records** de Cloudflare, agrega el subdominio multimedia:

* **Type (Tipo)**: `CNAME`
* **Name (Nombre)**: `media`
* **Target (Destino)**: `f003.backblazeb2.com`
* **Proxy Status (Estado de proxy)**: **Proxied (Nube Naranja activa 🟠)**.

*Nota: Esto asegura que Cloudflare intercepte las peticiones, le aplique HTTPS (SSL) y guarde los archivos en caché para ahorrar costos de descarga de Backblaze.*

---

## Paso 4: Cambiar los Nameservers en tu Registrador
1. Cloudflare te proporcionará dos Nameservers específicos (ejemplo: `claudia.ns.cloudflare.com` y `mark.ns.cloudflare.com`).
2. Ve al panel del registrador donde compraste tu dominio (ej. Hostinger Dominios).
3. Busca la opción **Nameservers** (Servidores de nombres) o **Cambiar DNS**.
4. Elige la opción de usar **Nameservers personalizados** y pega los dos que te entregó Cloudflare.
5. Guarda los cambios.

*Nota: La propagación del cambio de Nameservers suele tardar entre **2 y 24 horas** a nivel mundial, aunque para la mayoría de los usuarios se ve reflejado en minutos.*

---

## Paso 5: Configurar SSL/TLS Seguro (Obligatorio)
Una vez que el dominio esté activo en Cloudflare:
1. En el menú izquierdo de Cloudflare, ve a **SSL/TLS**.
2. Asegúrate de que el **Modo de Encriptación** esté configurado en **Completo (Full)** o **Completo (Estricto) (Full Strict)**.
3. *¿Por qué?* Backblaze B2 requiere conexiones HTTPS obligatoriamente. Si dejas Cloudflare en modo *Flexible*, intentará conectar con Backblaze por HTTP común (puerto 80), lo que generará un bucle de redirecciones o error de conexión.

---

## Paso 6: Verificación del Estado de Diagnóstico
Una vez completado todo y propagado el DNS, ve a tu panel de diagnóstico en la web:
`https://sevenrockradio.com/diagnose-media?token=sevenrock_audit_2026`

Verás que el recuadro de **Resolución de Nombres (DNS)** para `media.sevenrockradio.com` pasará de ser "Fallo de DNS" a **Resuelve (IP de Cloudflare)**. Automáticamente, la web pasará del modo de fallback al uso del dominio bonito.
