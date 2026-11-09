<?php

namespace App\Filament\Resources\Dispatches\Pages\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Si al guardar la unidad resulta ocupada (por ejemplo, otro despachador la
 * asignó un segundo antes), se muestra el aviso debajo del campo "Unidad"
 * en lugar de un error de servidor.
 */
trait ReportsAssignmentErrors
{
    protected function withAssignmentErrors(Closure $save): Model
    {
        try {
            return $save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'data.ambulance_id' => 'La unidad se acaba de asignar a otro servicio. Elija otra.',
            ]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())->mapWithKeys(fn (array $messages, string $key) => ["data.{$key}" => $messages])->all()
            );
        }
    }
}
