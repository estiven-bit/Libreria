<?php

use GeoIp2\Database\Reader as GeoIpReader;

/**
 * Resuelve una IP a país (código ISO + nombre) usando MaxMind GeoLite2-Country.
 *
 * La base de datos GeoLite2-Country.mmdb vive en storage/geoip/ y se actualiza
 * manualmente (gratis con licencia MaxMind, descarga mensual recomendada).
 *
 * Si la BD no existe o el lookup falla (IP privada/local, IP inválida, etc.)
 * devolvemos null sin error — el caller decide qué hacer.
 */
class GeoLocationService
{
    private static ?GeoIpReader $reader = null;
    private static bool $tried = false;

    private static function reader(): ?GeoIpReader
    {
        if (self::$tried) return self::$reader;
        self::$tried = true;
        $path = __DIR__ . '/../storage/geoip/GeoLite2-Country.mmdb';
        if (!is_file($path)) {
            error_log('[GeoLocationService] DB GeoLite2-Country.mmdb no encontrada en ' . $path);
            return null;
        }
        try {
            self::$reader = new GeoIpReader($path);
        } catch (\Throwable $e) {
            error_log('[GeoLocationService] No se pudo abrir la BD GeoIP: ' . $e->getMessage());
            self::$reader = null;
        }
        return self::$reader;
    }

    /**
     * @return array{code: string, name: string}|null
     */
    public static function lookup(string $ip): ?array
    {
        // IPs locales/privadas no resuelven a país. En dev devolvemos un valor
        // sintético para que las pruebas no tengan que mockear.
        if ($ip === '' || $ip === '::1' || $ip === '127.0.0.1' || str_starts_with($ip, '192.168.')
            || str_starts_with($ip, '10.') || str_starts_with($ip, '172.16.')) {
            return ['code' => 'LO', 'name' => 'Local'];
        }
        $reader = self::reader();
        if (!$reader) return null;
        try {
            $record = $reader->country($ip);
            $code = (string)($record->country->isoCode ?? '');
            $name = (string)($record->country->name ?? '');
            if ($code === '') return null;
            return ['code' => $code, 'name' => $name];
        } catch (\Throwable $e) {
            // IP no encontrada o inválida — sin ruido en log.
            return null;
        }
    }
}
