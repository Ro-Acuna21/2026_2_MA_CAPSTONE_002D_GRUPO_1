<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida un RUT chileno usando el algoritmo de módulo 11.
 * Acepta el RUT con o sin puntos/guión (ej: "12.345.678-5" o "123456785").
 */
class ValidRut implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '' || ! self::isValid($value)) {
            $fail('El :attribute ingresado no es un RUT chileno válido.');
        }
    }

    public static function isValid(string $rut): bool
    {
        $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));

        if (strlen($clean) < 2) {
            return false;
        }

        $dv = substr($clean, -1);
        $number = substr($clean, 0, -1);

        if ($number === '' || ! ctype_digit($number)) {
            return false;
        }

        return self::computeDv($number) === $dv;
    }

    /**
     * Devuelve el RUT normalizado como "12345678-5".
     */
    public static function normalize(string $rut): string
    {
        $clean = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
        $dv = substr($clean, -1);
        $number = substr($clean, 0, -1);

        return $number.'-'.$dv;
    }

    private static function computeDv(string $number): string
    {
        $sum = 0;
        $multiplier = 2;

        foreach (array_reverse(str_split($number)) as $digit) {
            $sum += ((int) $digit) * $multiplier;
            $multiplier = $multiplier === 7 ? 2 : $multiplier + 1;
        }

        $remainder = 11 - ($sum % 11);

        return match ($remainder) {
            11 => '0',
            10 => 'K',
            default => (string) $remainder,
        };
    }
}
