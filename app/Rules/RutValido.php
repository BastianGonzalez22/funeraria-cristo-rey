<?php

namespace App\Rules;

use App\Support\Rut;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class RutValido implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Rut::valido((string) $value)) {
            $fail('El RUT ingresado no es válido (revise el dígito verificador).');
        }
    }
}
