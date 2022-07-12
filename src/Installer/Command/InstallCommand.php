<?php

declare(strict_types=1);

namespace App\Installer\Command;

use App\Enums\SupportedLocales;
use App\Environment;
use App\Installer\EnvFiles\AbstractEnvFile;
use App\Installer\EnvFiles\AmbientCastEnvFile;
use App\Installer\EnvFiles\EnvFile;
use App\Radio\Configuration;
use App\Utilities\Strings;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'install'
)]
final class InstallCommand extends Command
{
    public const DEFAULT_BASE_DIRECTORY = '/installer';

    public function __construct(
        private readonly Environment $environment
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('base-dir', InputArgument::OPTIONAL)
            ->addOption('update', null, InputOption::VALUE_NONE)
            ->addOption('defaults', null, InputOption::VALUE_NONE)
            ->addOption('http-port', null, InputOption::VALUE_OPTIONAL)
            ->addOption('https-port', null, InputOption::VALUE_OPTIONAL)
            ->addOption('release-channel', null, InputOption::VALUE_OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $baseDir = $input->getArgument('base-dir') ?? self::DEFAULT_BASE_DIRECTORY;
        $update = (bool)$input->getOption('update');
        $defaults = (bool)$input->getOption('defaults');
        $httpPort = $input->getOption('http-port');
        $httpsPort = $input->getOption('https-port');
        $releaseChannel = $input->getOption('release-channel');

        $devMode = ($baseDir !== self::DEFAULT_BASE_DIRECTORY);

        // Initialize all the environment variables.
        $envPath = EnvFile::buildPathFromBase($baseDir);
        $ambientcastEnvPath = AmbientCastEnvFile::buildPathFromBase($baseDir);

        // Fail early if permissions aren't present.
        if (!is_writable($envPath)) {
            $io->error(
                'Permissions error: cannot write to work directory. Exiting installer and using defaults instead.'
            );
            return 1;
        }

        $isNewInstall = !$update;

        try {
            $env = EnvFile::fromEnvFile($envPath);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            $env = new EnvFile($envPath);
        }

        try {
            $ambientcastEnv = AmbientCastEnvFile::fromEnvFile($ambientcastEnvPath);
        } catch (InvalidArgumentException $e) {
            $io->error($e->getMessage());
            $ambientcastEnv = new AmbientCastEnvFile($envPath);
        }

        // Initialize locale for translated installer/updater.
        if (!$defaults && ($isNewInstall || empty($ambientcastEnv[Environment::LANG]))) {
            $langOptions = [];
            foreach (SupportedLocales::cases() as $supportedLocale) {
                $langOptions[$supportedLocale->getLocaleWithoutEncoding()] = $supportedLocale->getLocalName();
            }

            $ambientcastEnv[Environment::LANG] = $io->choice(
                'Select Language',
                $langOptions,
                SupportedLocales::default()->getLocaleWithoutEncoding()
            );
        }

        $locale = SupportedLocales::getValidLocale($ambientcastEnv[Environment::LANG] ?? null);
        $locale->register($this->environment);

        $envConfig = EnvFile::getConfiguration($this->environment);
        $env->setFromDefaults($this->environment);

        $ambientcastEnvConfig = AmbientCastEnvFile::getConfiguration($this->environment);
        $ambientcastEnv->setFromDefaults($this->environment);

        // Apply values passed via flags
        if (null !== $releaseChannel) {
            $env['AMBIENTCAST_VERSION'] = $releaseChannel;
        }
        if (null !== $httpPort) {
            $env['AMBIENTCAST_HTTP_PORT'] = $httpPort;
        }
        if (null !== $httpsPort) {
            $env['AMBIENTCAST_HTTPS_PORT'] = $httpsPort;
        }

        // Migrate legacy config values.
        if (isset($ambientcastEnv['PREFER_RELEASE_BUILDS'])) {
            $env['AMBIENTCAST_VERSION'] = ('true' === $ambientcastEnv['PREFER_RELEASE_BUILDS'])
                ? 'stable'
                : 'latest';

            unset($ambientcastEnv['PREFER_RELEASE_BUILDS']);
        }

        unset($ambientcastEnv['ENABLE_ADVANCED_FEATURES']);

        // Randomize the MariaDB root password for new installs.
        if ($isNewInstall) {
            if ($devMode) {
                if (empty($ambientcastEnv['MYSQL_ROOT_PASSWORD'])) {
                    $ambientcastEnv['MYSQL_ROOT_PASSWORD'] = 'ambient4c457_root';
                }
            } else {
                if (
                    empty($ambientcastEnv[Environment::DB_PASSWORD])
                    || 'ambient4c457' === $ambientcastEnv[Environment::DB_PASSWORD]
                ) {
                    $ambientcastEnv[Environment::DB_PASSWORD] = Strings::generatePassword(12);
                }

                if (empty($ambientcastEnv['MYSQL_ROOT_PASSWORD'])) {
                    $ambientcastEnv['MYSQL_ROOT_PASSWORD'] = Strings::generatePassword(20);
                }
            }
        }

        if (!empty($ambientcastEnv['MYSQL_ROOT_PASSWORD'])) {
            unset($ambientcastEnv['MYSQL_RANDOM_ROOT_PASSWORD']);
        } else {
            $ambientcastEnv['MYSQL_RANDOM_ROOT_PASSWORD'] = 'yes';
        }

        // Special fixes for transitioning to standalone installations.
        if ($this->environment->isDocker()) {
            if ('mariadb' === $ambientcastEnv['MYSQL_HOST']) {
                unset($ambientcastEnv['MYSQL_HOST']);
            }
            if ('redis' === $ambientcastEnv['REDIS_HOST']) {
                unset($ambientcastEnv['REDIS_HOST']);
            }
        }

        // Display header messages
        if ($isNewInstall) {
            $io->title(
                __('AmbientCast Installer')
            );
            $io->block(
                __('Welcome to AmbientCast! Complete the initial server setup by answering a few questions.')
            );

            $customize = !$defaults;
        } else {
            $io->title(
                __('AmbientCast Updater')
            );

            if ($defaults) {
                $customize = false;
            } else {
                $customize = $io->confirm(
                    __('Change installation settings?'),
                    false
                );
            }
        }

        if ($customize) {
            // Port customization
            $io->writeln(
                __('AmbientCast is currently configured to listen on the following ports:'),
            );
            $io->listing(
                [
                    sprintf(__('HTTP Port: %d'), $env['AMBIENTCAST_HTTP_PORT']),
                    sprintf(__('HTTPS Port: %d'), $env['AMBIENTCAST_HTTPS_PORT']),
                    sprintf(__('SFTP Port: %d'), $env['AMBIENTCAST_SFTP_PORT']),
                    sprintf(__('Radio Ports: %s'), $env['AMBIENTCAST_STATION_PORTS']),
                ],
            );

            $customizePorts = $io->confirm(
                __('Customize ports used for AmbientCast?'),
                false
            );

            if ($customizePorts) {
                $simplePorts = [
                    'AMBIENTCAST_HTTP_PORT',
                    'AMBIENTCAST_HTTPS_PORT',
                    'AMBIENTCAST_SFTP_PORT',
                ];

                foreach ($simplePorts as $port) {
                    $env[$port] = (int)$io->ask(
                        $envConfig[$port]['name'] . ' - ' . $envConfig[$port]['description'],
                        (string)$env[$port]
                    );
                }

                $ambientcastEnv[Environment::AUTO_ASSIGN_PORT_MIN] = (int)$io->ask(
                    $ambientcastEnvConfig[Environment::AUTO_ASSIGN_PORT_MIN]['name'],
                    (string)$ambientcastEnv[Environment::AUTO_ASSIGN_PORT_MIN]
                );

                $ambientcastEnv[Environment::AUTO_ASSIGN_PORT_MAX] = (int)$io->ask(
                    $ambientcastEnvConfig[Environment::AUTO_ASSIGN_PORT_MAX]['name'],
                    (string)$ambientcastEnv[Environment::AUTO_ASSIGN_PORT_MAX]
                );

                $stationPorts = Configuration::enumerateDefaultPorts(
                    rangeMin: $ambientcastEnv[Environment::AUTO_ASSIGN_PORT_MIN],
                    rangeMax: $ambientcastEnv[Environment::AUTO_ASSIGN_PORT_MAX]
                );
                $env['AMBIENTCAST_STATION_PORTS'] = implode(',', $stationPorts);
            }

            $ambientcastEnv['COMPOSER_PLUGIN_MODE'] = $io->confirm(
                $ambientcastEnvConfig['COMPOSER_PLUGIN_MODE']['name'],
                $ambientcastEnv->getAsBool('COMPOSER_PLUGIN_MODE', false)
            );
        }

        $io->writeln(
            __('Writing configuration files...')
        );

        $envStr = $env->writeToFile($this->environment);
        $ambientcastEnvStr = $ambientcastEnv->writeToFile($this->environment);

        if ($io->isVerbose()) {
            $io->section($env->getBasename());
            $io->block($envStr);

            $io->section($ambientcastEnv->getBasename());
            $io->block($ambientcastEnvStr);
        }

        $dockerComposePath = ($devMode)
            ? $baseDir . '/docker-compose.yml'
            : $baseDir . '/docker-compose.new.yml';
        $dockerComposeStr = $this->updateDockerCompose($dockerComposePath, $env, $ambientcastEnv);

        if ($io->isVerbose()) {
            $io->section(basename($dockerComposePath));
            $io->block($dockerComposeStr);
        }

        $io->success(
            __('Server configuration complete!')
        );
        return 0;
    }

    protected function updateDockerCompose(
        string $dockerComposePath,
        AbstractEnvFile $env,
        AbstractEnvFile $ambientcastEnv
    ): string {
        // Attempt to parse Docker Compose YAML file
        $sampleFile = $this->environment->getBaseDirectory() . '/docker-compose.sample.yml';
        $yaml = Yaml::parseFile($sampleFile);

        // Parse port listing and convert into YAML format.
        $ports = $env['AMBIENTCAST_STATION_PORTS'] ?? '';

        $envConfig = $env::getConfiguration($this->environment);
        $defaultPorts = $envConfig['AMBIENTCAST_STATION_PORTS']['default'];

        if (!empty($ports) && 0 !== strcmp($ports, $defaultPorts)) {
            $yamlPorts = [];
            $nginxRadioPorts = [];
            $nginxWebDjPorts = [];

            foreach (explode(',', $ports) as $port) {
                $port = (int)$port;
                if ($port <= 0) {
                    continue;
                }

                $yamlPorts[] = $port . ':' . $port;

                if (0 === $port % 10) {
                    $nginxRadioPorts[] = $port;
                } elseif (5 === $port % 10) {
                    $nginxWebDjPorts[] = $port;
                }
            }

            if (!empty($yamlPorts)) {
                $existingPorts = [];
                foreach ($yaml['services']['web']['ports'] as $port) {
                    if (str_starts_with($port, '$')) {
                        $existingPorts[] = $port;
                    }
                }

                $yaml['services']['web']['ports'] = array_merge($existingPorts, $yamlPorts);
            }
            if (!empty($nginxRadioPorts)) {
                $yaml['services']['web']['environment']['NGINX_RADIO_PORTS'] = '(' . implode(
                    '|',
                    $nginxRadioPorts
                ) . ')';
            }
            if (!empty($nginxWebDjPorts)) {
                $yaml['services']['web']['environment']['NGINX_WEBDJ_PORTS'] = '(' . implode(
                    '|',
                    $nginxWebDjPorts
                ) . ')';
            }
        }

        // Add plugin mode if it's selected.
        if ($ambientcastEnv->getAsBool('COMPOSER_PLUGIN_MODE', false)) {
            $yaml['services']['web']['volumes'][] = 'www_vendor:/var/ambient/www/vendor';
            $yaml['volumes']['www_vendor'] = [];
        }

        // Remove privileged-mode settings if not enabled.
        if (!$env->getAsBool('AMBIENTCAST_COMPOSE_PRIVILEGED', true)) {
            foreach ($yaml['services'] as &$service) {
                unset(
                    $service['ulimits'],
                    $service['sysctls']
                );
            }
            unset($service);
        }

        $yamlRaw = Yaml::dump($yaml, PHP_INT_MAX);
        file_put_contents($dockerComposePath, $yamlRaw);

        return $yamlRaw;
    }
}
