<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Respuestas mínimas del guion de recepción para crear una emergencia desde
 * el formulario: motivo, preguntas de sí / no y punto confirmado en el mapa.
 *
 * @return array<string, mixed>
 */
function guionCompleto(array $overrides = []): array
{
    return array_merge([
        'caller_phone' => '8700-0000',
        'chief_complaint' => 'illness',
        'triage' => [
            'conscious' => 1,
            'breathing' => 1,
            'severe_bleeding' => 0,
            'multiple_patients' => 0,
            'scene_danger' => 0,
        ],
        'pickup_latitude' => 10.4470,
        'pickup_longitude' => -84.0780,
        'location_confirmed' => true,
    ], $overrides);
}
