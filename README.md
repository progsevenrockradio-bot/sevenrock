# SevenRockRadio

Laravel app for Seven Rock Radio site migration (Blade + Tailwind + Alpine), including:

- Public pages (home, events, discography, videos, gallery, blog, shop, contact)
- Admin panel for editable content and theme settings
- Integrated radio player with live metadata and details modal


## Configuración de certificados SSL en HTTP

En el sistema se ha dejado de desactivar la verificación SSL (erify => false) para las peticiones HTTP que descargan imágenes de fuentes externas, ya que hacerlo permitía ataques man-in-the-middle y validaciones inseguras.
En su lugar, hemos incluido un almacén de certificados manual (storage/app/cacert.pem) que se encarga de validar los certificados para todas las peticiones a través de la clase centralizada App\Support\HttpBrowser. Todo el sistema debe utilizar este cliente y este certificado para no romper la seguridad global.
