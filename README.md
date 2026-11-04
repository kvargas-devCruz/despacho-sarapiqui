# Despacho Sarapiquí

Demo de un sistema de despacho y rastreo de ambulancias en tiempo real, hecho con **Laravel 13 + Filament 5**.

![Mapa en vivo](docs/screenshots/mapa.png)

**Incluye:** mapa en vivo de las unidades, gestión de emergencias y traslados, estados de cada unidad y un simulador que las mueve por las rutas reales de Sarapiquí.

## Cómo ejecutarlo

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan make:filament-user
php artisan serve
```

Simulador (en otra terminal): `php artisan fleet:simulate --auto`

*Datos de unidades y pacientes ficticios.*