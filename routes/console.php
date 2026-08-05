<?php

use Illuminate\Support\Facades\Schedule;

// Coda email: processata solo dal cron, ogni minuto.
// Cron di produzione: * * * * * cd /percorso/mailer-server && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('mail:process-queue')
    ->everyMinute()
    ->withoutOverlapping(10);
