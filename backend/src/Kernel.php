<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Heure locale du centre (sinon PHP/XAMPP utilise souvent UTC : décalage d'1 h
        // sur les contrôles « date dans le passé » et les séances du jour).
        date_default_timezone_set($_SERVER['APP_TIMEZONE'] ?? $_ENV['APP_TIMEZONE'] ?? 'Africa/Tunis');

        parent::boot();
    }
}
