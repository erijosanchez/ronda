<?php

declare(strict_types=1);

use Ronda\Notifications\Domain\Audience;
use Ronda\Notifications\Domain\NotificationTopic;

return [

    /*
    |--------------------------------------------------------------------------
    | Canales por tema
    |--------------------------------------------------------------------------
    | `database` es la campana de la aplicacion; `mail`, el correo. WhatsApp
    | (sec. 5.2) todavia no existe: cuando exista se anade aqui, y el resto del
    | modulo no se entera.
    |
    | Lo que pasa en la pantalla donde ya estas (llego un envio a tu bandeja) no
    | necesita correo; lo que exige salir a hacer algo, si.
    */

    'channels' => [
        NotificationTopic::ObligationDueSoon->value => ['database', 'mail'],
        NotificationTopic::ObligationMissed->value => ['database', 'mail'],
        NotificationTopic::SubmissionAwaitingReview->value => ['database'],
        NotificationTopic::ReviewOverdue->value => ['database', 'mail'],
        NotificationTopic::SubmissionApproved->value => ['database'],
        NotificationTopic::SubmissionRejected->value => ['database', 'mail'],
        // Una exportacion tarda minutos: quien la pidio ya se fue a otra cosa.
        NotificationTopic::ExportReady->value => ['database', 'mail'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recordatorio anticipado
    |--------------------------------------------------------------------------
    | Cuantos minutos antes del vencimiento se avisa de una entrega que sigue
    | pendiente (sec. 9.3: «te quedan 2 horas»). Solo si la ventana ya abrio:
    | recordar algo que todavia no se puede hacer es ruido.
    */

    'due_soon_minutes' => (int) env('NOTIFY_DUE_SOON_MINUTES', 120),

    /*
    |--------------------------------------------------------------------------
    | Escaleras de escalamiento
    |--------------------------------------------------------------------------
    | Minutos desde el hecho y a quien avisar. El nivel de cada peldano es su
    | posicion aqui, y se guarda en `sla_events`: reordenar la lista mueve
    | avisos ya mandados, asi que se anaden al final.
    |
    | Las audiencias son capacidades sobre la sede, no roles (ver Audience).
    */

    'ladders' => [

        // Desde que la obligacion quedo incumplida.
        NotificationTopic::ObligationMissed->value => [
            ['after_minutes' => 0, 'audience' => Audience::Site->value],
            ['after_minutes' => 120, 'audience' => Audience::Reviewers->value],
            ['after_minutes' => 1440, 'audience' => Audience::Administrators->value],
        ],

        // Desde que el envio entro en la bandeja esperando decision.
        NotificationTopic::ReviewOverdue->value => [
            ['after_minutes' => 1440, 'audience' => Audience::Reviewers->value],
            ['after_minutes' => 2880, 'audience' => Audience::Administrators->value],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Limite por repaso
    |--------------------------------------------------------------------------
    | Cuantas cosas revisa como mucho cada pasada del job por tema. Evita que un
    | cliente con un parque grande y el job parado dos dias dispare miles de
    | correos de golpe; lo que quede se avisa en la siguiente pasada.
    */

    'batch_limit' => (int) env('NOTIFY_BATCH_LIMIT', 500),

];
