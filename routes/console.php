<?php

use App\Models\AusenciaChofer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Actualiza el estado de las ausencias de choferes (programada -> vigente -> finalizada).
Artisan::command('funeraria:actualizar-ausencias', function () {
    $n = AusenciaChofer::sincronizarEstados();
    $this->info("Ausencias actualizadas: {$n}");
})->purpose('Sincroniza el estado de vacaciones y permisos de los choferes');

Schedule::command('funeraria:actualizar-ausencias')->dailyAt('00:05');
