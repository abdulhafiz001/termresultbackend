<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('termresult:tenants-migrate {--school_id=} {--subdomain=}', function () {
    $central = 'mysql';

    $q = DB::connection($central)->table('schools')->where('status', 'active');
    if ($this->option('school_id')) {
        $q->where('id', (int) $this->option('school_id'));
    }
    if ($this->option('subdomain')) {
        $q->where('subdomain', (string) $this->option('subdomain'));
    }

    $schools = $q->get(['id', 'name', 'subdomain', 'database_name']);

    if ($schools->isEmpty()) {
        $this->warn('No active schools found for the given filter.');
        return 0;
    }

    $this->info('Migrating tenant databases...');
    foreach ($schools as $s) {
        $this->line(" - [{$s->id}] {$s->subdomain} ({$s->database_name})");

        Config::set('database.connections.tenant.database', $s->database_name);
        DB::purge('tenant');

        // Run tenant migrations against this tenant DB.
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);

        $out = trim(Artisan::output());
        if ($out) {
            $this->line($out);
        }
    }

    $this->info('Done.');
    return 0;
})->purpose('Run tenant migrations for all (or filtered) active schools');
