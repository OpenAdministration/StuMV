<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @return void
     */
    #[\Override]
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('ldap:sync-roles')->dailyAt('00:01');
        $schedule->command('app:mark-inactive-accounts')->dailyAt('03:00')->withoutOverlapping()->onOneServer();
        $schedule->command('app:delete-marked-accounts')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    #[\Override]
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
