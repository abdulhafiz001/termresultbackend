<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('termresult:tenants-migrate {--school_id=} {--subdomain=}', function () {
    $this->warn('Deprecated: Single-database tenancy no longer uses per-school databases.');
    if ($this->option('school_id') || $this->option('subdomain')) {
        $this->warn('The --school_id and --subdomain options are ignored in single-db mode.');
    }

    $this->info('Running normal migrations...');
    Artisan::call('migrate', [
        '--force' => true,
    ]);

    $out = trim(Artisan::output());
    if ($out) {
        $this->line($out);
    }

    $this->info('Done.');
    return 0;
})->purpose('Run tenant migrations for all (or filtered) active schools');
