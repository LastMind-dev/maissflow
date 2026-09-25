<?php

namespace App\Services;

class FlowInputValidator
{
    public const RULES = ['any', 'text', 'cpf', 'email', 'phone'];

    private const SKIP_TOKENS = ['0', '-', 'pular', 'nao', 'não', 'n/a', 'skip'];

    public static function isSkipToken(string $answer): bool
    {
        return in_array(mb_strtolower(trim($answer)), self::SKIP_TOKENS, true);
    }

    public static function validate(array $data, string $answer): bool
    {
        $maxLength = (int) ($data['maxLength'] ?? 5000);
        if ($answer === '' || mb_strlen($answer) > max(1, $maxLength)) {
            return false;
        }

        return match ((string) ($data['validation'] ?? 'any')) {
            'cpf' => self::isValidCpf($answer),
            'email' => filter_var($answer, FILTER_VALIDATE_EMAIL) !== false,
            'phone' => self::isValidPhone($answer),
            default => true,
        };
    }

    public static function isValidPhone(string $phone): bool
    {
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) >= 8 && strlen($digits) <= 15;
    }

    public static function isValidCpf(string $cpf): bool
    {
        $digits = preg_replace('/\D/', '', $cpf);

        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        foreach ([9, 10] as $position) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) {
                $sum += (int) $digits[$i] * ($position + 1 - $i);
            }
            $check = ($sum * 10) % 11 % 10;
            if ((int) $digits[$position] !== $check) {
                return false;
            }
        }

        return true;
    }
}
