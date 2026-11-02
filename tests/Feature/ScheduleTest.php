<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use VV\PixxioFlysystem\ServiceProvider;

function scheduledCommands(Schedule $schedule): array
{
    return collect($schedule->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'pixxio:sync'))
        ->mapWithKeys(fn ($event) => [Str::after($event->command, "'artisan' ") => $event->expression])
        ->all();
}

it('does not schedule syncs by default', function () {
    expect(scheduledCommands(app(Schedule::class)))->toBe([]);
});

it('schedules configured syncs', function () {
    config(['statamic.flysystem-pixxio.schedule' => ['sync' => '0 3 * * *', 'sync_new' => '*/15 * * * *']]);
    $schedule = new Schedule();

    (fn () => $this->schedule($schedule))->call(app()->getProvider(ServiceProvider::class));

    expect(scheduledCommands($schedule))->toBe([
        'pixxio:sync' => '0 3 * * *',
        'pixxio:sync --new' => '*/15 * * * *',
    ]);
});
