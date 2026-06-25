<?php

if (!defined('SHEET_ID_DATABASE')) {
    define(
        'SHEET_ID_DATABASE',
        env('APP_ENV') === 'dev'
            ? '1GAQ2ZkHoORsGnQp6xrwY_7iCPAga29g1l0tZKIYUJs4'
            : '1FaOF5K_bb3uxNPSbFel7qOuckAhcaKoZIDKHRxGJrxY'
    );
}

// Google Calendar configuration
if (!defined('GOOGLE_CALENDAR_ID')) {
    define('GOOGLE_CALENDAR_ID', env('GOOGLE_CALENDAR_ID', 'primary'));
}

// Daily agents import spreadsheet configuration
if (!defined('AGENTS_SHEET_SPREADSHEET_ID')) {
    define(
        'AGENTS_SHEET_SPREADSHEET_ID',
        env('AGENTS_SHEET_SPREADSHEET_ID', '1YRMk0yL5p5URS4d7XnIuvl2yNgZk0kha98xfxJBvSCI')
    );
}

if (!defined('AGENTS_SHEET_GID')) {
    define('AGENTS_SHEET_GID', (int) env('AGENTS_SHEET_GID', 1061456955));
}

if (!defined('AGENTS_SYNC_DAILY_AT')) {
    define('AGENTS_SYNC_DAILY_AT', env('AGENTS_SYNC_DAILY_AT', '20:00'));
}

if (!defined('AGENTS_SYNC_TIMEZONE')) {
    define('AGENTS_SYNC_TIMEZONE', env('AGENTS_SYNC_TIMEZONE', 'Europe/Amsterdam'));
}
