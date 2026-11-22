<?php
function empleados_fecha_valida(string $date): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();

    return $parsedDate !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsedDate->format('Y-m-d') === $date;
}

function empleados_salario_valido(string $salary): bool
{
    return $salary === '' || preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $salary) === 1;
}
