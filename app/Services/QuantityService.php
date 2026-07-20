<?php

declare(strict_types=1);

namespace App\Services;

final class QuantityService
{
    public function isValid(string $quantity, bool $allowsFraction): bool
    {
        if (! preg_match('/^\d{1,11}(?:\.\d{1,3})?$/', $quantity)) {
            return false;
        }

        if ($allowsFraction) {
            return true;
        }

        $decimalPart = explode('.', $quantity, 2)[1] ?? '';

        return trim($decimalPart, '0') === '';
    }
}
