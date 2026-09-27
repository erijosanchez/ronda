<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Ronda\Api\Domain\ApiScope;
use Ronda\Api\Presentation\Http\Controllers\V1\ObligationController;
use Ronda\Api\Presentation\Http\Controllers\V1\SiteController;
use Ronda\Api\Presentation\Http\Controllers\V1\SubmissionController;
use Ronda\Api\Presentation\Http\Controllers\V1\TemplateController;

/*
| API publica v1. RONDA-PLAN-MAESTRO.md sec. 13.1
|
| Se incluye desde routes/tenant.php, en un grupo APARTE del de la aplicacion:
| aqui no hay sesion, ni cookies, ni CSRF. Se entra con un token de Sanctum y
| nada mas.
|
| Cada ruta declara su alcance con `scope:<alcance>`. No es decorativo: hay un
| test que recorre la tabla de rutas y falla si alguna de la API no lo declara.
| Asi una ruta nueva no puede nacer abierta por olvido, que es justo lo que
| pasaba en el sistema anterior.
|
| Las rutas de la API van en INGLES, al reves que las de la interfaz: son parte
| del contrato publico y quien integra no tiene por que saber espanol.
*/

Route::middleware('scope:'.ApiScope::SitesRead->value)->group(function (): void {
    Route::get('/sites', [SiteController::class, 'index'])->name('api.v1.sites.index');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('api.v1.sites.show');
});

Route::middleware('scope:'.ApiScope::TemplatesRead->value)->group(function (): void {
    Route::get('/templates', [TemplateController::class, 'index'])->name('api.v1.templates.index');
    Route::get('/templates/{template}', [TemplateController::class, 'show'])->name('api.v1.templates.show');
});

Route::middleware('scope:'.ApiScope::ObligationsRead->value)->group(function (): void {
    Route::get('/obligations', [ObligationController::class, 'index'])->name('api.v1.obligations.index');
});

Route::middleware('scope:'.ApiScope::SubmissionsRead->value)->group(function (): void {
    Route::get('/submissions', [SubmissionController::class, 'index'])->name('api.v1.submissions.index');
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show'])->name('api.v1.submissions.show');
});

// La unica puerta de escritura de la v1, y con su propio alcance: un token de
// lectura no puede entregar reportes por mucho que su duena si pueda.
Route::middleware('scope:'.ApiScope::SubmissionsWrite->value)->group(function (): void {
    Route::post('/submissions', [SubmissionController::class, 'store'])->name('api.v1.submissions.store');
});
