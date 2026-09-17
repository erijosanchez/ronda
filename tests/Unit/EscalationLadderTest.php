<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Ronda\Notifications\Domain\Audience;
use Ronda\Notifications\Domain\Exceptions\InvalidEscalation;
use Ronda\Notifications\Domain\ValueObjects\EscalationLadder;
use Ronda\Notifications\Domain\ValueObjects\EscalationStage;

// La escalera de escalamiento. RONDA-PLAN-MAESTRO.md sec. 9.4
//
// Es pura: solo responde «a estas alturas, que peldanos tocaban». Lo caro aqui
// es avisar de mas (ruido que se acaba ignorando) o saltarse un peldano cuando
// el job estuvo parado.

function escalera(): EscalationLadder
{
    return EscalationLadder::fromConfig([
        ['after_minutes' => 0, 'audience' => 'site'],
        ['after_minutes' => 120, 'audience' => 'reviewers'],
        ['after_minutes' => 1440, 'audience' => 'administrators'],
    ]);
}

function nivelesDe(array $peldanos): array
{
    return array_map(static fn (EscalationStage $stage): int => $stage->level, $peldanos);
}

it('no devuelve nada antes del primer peldano', function (): void {
    $escalera = EscalationLadder::fromConfig([
        ['after_minutes' => 60, 'audience' => 'reviewers'],
    ]);

    $desde = CarbonImmutable::parse('2026-09-17 10:00', 'UTC');

    expect($escalera->stagesDue($desde, $desde->addMinutes(59)))->toBe([])
        ->and(nivelesDe($escalera->stagesDue($desde, $desde->addMinutes(60))))->toBe([0]);
})->group('notifications');

it('sube de peldano conforme pasa el tiempo', function (): void {
    $desde = CarbonImmutable::parse('2026-09-17 10:00', 'UTC');

    expect(nivelesDe(escalera()->stagesDue($desde, $desde)))->toBe([0])
        ->and(nivelesDe(escalera()->stagesDue($desde, $desde->addHours(3))))->toBe([0, 1]);
})->group('notifications');

it('devuelve todos los peldanos vencidos si el repaso no corrio', function (): void {
    // Dos dias despues: salen los tres, cada uno con su nivel. Los ya mandados
    // los filtra `sla_events`, no esto.
    $desde = CarbonImmutable::parse('2026-09-17 10:00', 'UTC');

    expect(nivelesDe(escalera()->stagesDue($desde, $desde->addDays(2))))->toBe([0, 1, 2]);
})->group('notifications');

it('conserva el nivel de la configuracion aunque los tiempos vengan desordenados', function (): void {
    // El nivel es lo que se guarda en `sla_events`: reordenar la lista movería
    // de sitio avisos ya mandados.
    $escalera = EscalationLadder::fromConfig([
        ['after_minutes' => 1440, 'audience' => 'administrators'],
        ['after_minutes' => 0, 'audience' => 'site'],
    ]);

    $desde = CarbonImmutable::parse('2026-09-17 10:00', 'UTC');
    $peldanos = $escalera->stagesDue($desde, $desde->addDays(2));

    expect(nivelesDe($peldanos))->toBe([1, 0])
        ->and($peldanos[0]->audience)->toBe(Audience::Site)
        ->and($peldanos[1]->audience)->toBe(Audience::Administrators);
})->group('notifications');

it('rechaza una escalera mal configurada', function (Closure $build): void {
    expect($build)->toThrow(InvalidEscalation::class);
})->with([
    'audiencia inventada' => fn (): EscalationLadder => EscalationLadder::fromConfig([['after_minutes' => 0, 'audience' => 'gerencia']]),
    'sin audiencia' => fn (): EscalationLadder => EscalationLadder::fromConfig([['after_minutes' => 0]]),
    'retraso negativo' => fn (): EscalationLadder => EscalationLadder::fromConfig([['after_minutes' => -5, 'audience' => 'site']]),
])->group('notifications');

it('la configuracion del proyecto describe escaleras validas', function (string $topic): void {
    /** @var array<int, array{after_minutes?: int|string, audience?: string}> $config */
    $config = config('notifications.ladders.'.$topic);

    expect(EscalationLadder::fromConfig($config)->stages)->not->toBeEmpty();
})->with(['obligation_missed', 'review_overdue'])->group('notifications');
