# Referencia de conexión y despliegue — Hostinger (SevenRockRadio)

Documento de referencia con datos **no secretos** para despliegues y auditorías en producción.
Nunca incluir claves privadas, contraseñas ni tokens en este archivo ni en el repositorio Git.

## Datos de conexión SSH (no secretos)

- **Usuario SSH:** `u531780502`
- **Host:** `77.37.95.252` (`us-phx-web1541.main-hosting.eu`)
- **Puerto SSH:** `65002`
- **Aplicación Laravel:** `/home/u531780502/domains/sevenrockradio.com/sevenrockradio`
- **Directorio público (`public_html`):** `/home/u531780502/domains/sevenrockradio.com/public_html`
- **Build público:** `/home/u531780502/domains/sevenrockradio.com/public_html/build`
- **URL producción:** `https://sevenrockradio.com`

## Clave SSH local (fuera del repositorio)

- Ruta principal en Windows: `C:\Users\JOSE FONT\.ssh\id_hostinger_audit`
- Ejemplo de comprobación segura (sin desactivar verificación de host):
  ```powershell
  ssh -i "C:\Users\JOSE FONT\.ssh\id_hostinger_audit" -p 65002 -o IdentitiesOnly=yes -o BatchMode=yes u531780502@77.37.95.252 "hostname"
  ```

## Procedimiento estándar de despliegue seguro

1. Comparar `HEAD` de producción con el SHA candidato y verificar que el SHA de producción es ancestro directo del candidato.
2. Ejecutar respaldo previo:
   ```bash
   php scripts/backup_pre_deploy.php \
     --app-path=/home/u531780502/domains/sevenrockradio.com/sevenrockradio \
     --public-html-path=/home/u531780502/domains/sevenrockradio.com/public_html
   ```
3. Adquirir bloqueo de protección de diseño:
   ```bash
   php artisan design:protect --lock --public-path=/home/u531780502/domains/sevenrockradio.com/public_html/build
   ```
4. Hacer `git fetch` y `git checkout --detach <SHA_EXACTO>`.
5. Sincronizar `public/build` hacia `public_html/build`.
6. Aplicar únicamente migraciones aditivas pendientes (`php artisan migrate --force`) y limpiar cachés (`php artisan optimize:clear`).
7. Validar build sincronizado, actualizar respaldo verificado y liberar bloqueo:
   ```bash
   php artisan design:protect --validate-deploy --public-path=/home/u531780502/domains/sevenrockradio.com/public_html/build
   php artisan design:protect --backup --public-path=/home/u531780502/domains/sevenrockradio.com/public_html/build
   php artisan design:protect --unlock --public-path=/home/u531780502/domains/sevenrockradio.com/public_html/build
   php artisan design:protect --check --public-path=/home/u531780502/domains/sevenrockradio.com/public_html/build
   ```
