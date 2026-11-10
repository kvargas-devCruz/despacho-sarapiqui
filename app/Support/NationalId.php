<?php

namespace App\Support;

/**
 * Cédula guardada de forma segura.
 *
 * La cédula se guarda cifrada (nadie la puede leer directo de la base de datos),
 * pero cifrada no se puede buscar ni comparar. Por eso, al lado se guarda una
 * "huella" (HMAC-SHA256 de solo los dígitos): sirve para buscar una cédula exacta
 * y para impedir duplicados, sin poder volver a la cédula original.
 */
class NationalId
{
    /** Solo los dígitos: "1-1452-0871" y "114520871" son la misma cédula */
    public static function normalize(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value);
    }

    /** Huella para búsquedas exactas y para la regla de cédula única */
    public static function hash(?string $value): ?string
    {
        $digits = self::normalize($value);

        if ($digits === '') {
            return null;
        }

        return hash_hmac('sha256', $digits, self::key());
    }

    private static function key(): string
    {
        $key = config('operations.security.blind_index_key') ?: config('app.key');

        return str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7)) : $key;
    }
}
