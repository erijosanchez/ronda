<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Api\Presentation\Livewire\ApiTokens;
use Ronda\Api\Presentation\Livewire\Webhooks;

/*
| Rutas del modulo Api DENTRO de la aplicacion del cliente.
|
| Es solo la pantalla que emite y revoca tokens. Los endpoints de la API viven
| en routes.php, en un grupo aparte sin sesion.
|
| Ruta en espanol como el resto de la interfaz (CLAUDE.md); lo que va en ingles
| son los endpoints, que son contrato publico.
*/

Route::get('/integraciones', ApiTokens::class)->name('api-tokens');

// Los avisos salientes (sec. 13.2). Cuelgan de la misma seccion: quien emite
// un token es quien configura un webhook.
Route::get('/integraciones/avisos', Webhooks::class)->name('webhooks');
