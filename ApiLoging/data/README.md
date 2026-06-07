# ApiLoging/data

Datos binarios no versionados.

## GeoLite2-Country.mmdb

Base de datos de geolocalización IP→país usada por `GeoLocationService`.

### Descarga inicial

1. Cuenta gratis en https://www.maxmind.com/en/geolite2/signup
2. Panel → "Download databases" → fila **GeoLite Country** → "Download GZIP".
3. Extraer; copiar `GeoLite2-Country.mmdb` a este directorio.

### Actualización

El archivo se puede refrescar mensualmente; países cambian raramente, así que
no es urgente. Si el archivo no existe o está corrupto, `GeoLocationService`
degrada grácilmente: registra logins con `country_code = NULL` y no dispara
alertas.

### Deploy a Hostinger

Subir el mismo `.mmdb` por FTP a `ApiLoging/data/` en producción. No está
en el repo.
