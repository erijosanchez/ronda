<?php

declare(strict_types=1);

return [

    'ARQUEO-CAJA' => [
        'name' => 'Cash count',
        'description' => 'Daily cash reconciliation: system balance, counted cash, declared difference, photo of the count and signature.',
        'cadence' => 'Daily',
    ],

    'DEPOSITO' => [
        'name' => 'Bank deposit',
        'description' => "Deposit of the day's takings: amount, bank, operation number and the bank voucher as evidence.",
        'cadence' => 'Daily',
    ],

    'APERTURA-CIERRE' => [
        'name' => 'Opening and closing',
        'description' => 'One template for both windows of the day. Closing also asks about the alarm and the till. With a photo of the site to check the location.',
        'cadence' => 'Daily, two windows',
    ],

    'INCIDENCIA' => [
        'name' => 'Incident report',
        'description' => 'Submitted when something happens: type, severity, what occurred, photos and actions taken. Severity drives attention.',
        'cadence' => 'Per event',
    ],

    'LIMPIEZA' => [
        'name' => 'Cleaning and image checklist',
        'description' => 'Area by area review (front, service, restrooms and storage) with status and photo each, plus a score that feeds the site ranking.',
        'cadence' => 'Weekly',
    ],

];
