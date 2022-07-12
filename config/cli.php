<?php

use App\Console\Command;

return function (App\Event\BuildConsoleCommands $event) {
    $event->addAliases([
        'ambientcast:acme:get-certificate' => Command\Acme\GetCertificateCommand::class,
        'ambientcast:backup' => Command\Backup\BackupCommand::class,
        'ambientcast:restore' => Command\Backup\RestoreCommand::class,
        'ambientcast:debug:optimize-tables' => Command\Debug\OptimizeTablesCommand::class,
        'ambientcast:internal:on-ssl-renewal' => Command\Internal\OnSslRenewal::class,
        'ambientcast:internal:ip' => Command\Internal\GetIpCommand::class,
        'ambientcast:locale:generate' => Command\Locale\GenerateCommand::class,
        'ambientcast:locale:import' => Command\Locale\ImportCommand::class,
        'ambientcast:queue:process' => Command\MessageQueue\ProcessCommand::class,
        'ambientcast:queue:clear' => Command\MessageQueue\ClearCommand::class,
        'ambientcast:settings:list' => Command\Settings\ListCommand::class,
        'ambientcast:settings:set' => Command\Settings\SetCommand::class,
        'ambientcast:station-queues:clear' => Command\ClearQueuesCommand::class,
        'ambientcast:account:list' => Command\Users\ListCommand::class,
        'ambientcast:account:login-token' => Command\Users\LoginTokenCommand::class,
        'ambientcast:account:reset-password' => Command\Users\ResetPasswordCommand::class,
        'ambientcast:account:set-administrator' => Command\Users\SetAdministratorCommand::class,
        'ambientcast:cache:clear' => Command\ClearCacheCommand::class,
        'ambientcast:setup:initialize' => Command\InitializeCommand::class,
        'ambientcast:config:migrate' => Command\MigrateConfigCommand::class,
        'ambientcast:setup:fixtures' => Command\SetupFixturesCommand::class,
        'ambientcast:setup' => Command\SetupCommand::class,
        'ambientcast:radio:restart' => Command\RestartRadioCommand::class,
        'ambientcast:sync:nowplaying' => Command\Sync\NowPlayingCommand::class,
        'ambientcast:sync:nowplaying:station' => Command\Sync\NowPlayingPerStationCommand::class,
        'ambientcast:sync:run' => Command\Sync\RunnerCommand::class,
        'ambientcast:sync:task' => Command\Sync\SingleTaskCommand::class,
        'ambientcast:media:reprocess' => Command\ReprocessMediaCommand::class,
        'ambientcast:api:docs' => Command\GenerateApiDocsCommand::class,
        'locale:generate' => Command\Locale\GenerateCommand::class,
        'locale:import' => Command\Locale\ImportCommand::class,
        'queue:process' => Command\MessageQueue\ProcessCommand::class,
        'queue:clear' => Command\MessageQueue\ClearCommand::class,
        'cache:clear' => Command\ClearCacheCommand::class,
        'acme:cert' => Command\Acme\GetCertificateCommand::class,
    ]);
};
