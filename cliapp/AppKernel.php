<?php

declare(strict_types=1);

namespace Maduser\AgentCli;

use RuntimeException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Throwable;
use UnexpectedValueException;

use function is_array;
use function is_callable;
use function is_file;

final class AppKernel
{
    private ?Application $application = null;

    private ?AppContext $context = null;

    private ?AppServices $services = null;

    private ?AppErrors $errors = null;

    public function __construct(
        private readonly string $projectRoot,
        private readonly ?string $commandsFile = null,
    ) {
    }

    public function context(): AppContext
    {
        return $this->context ??= AppContext::fromProjectRoot(
            projectRoot: $this->projectRoot,
            configDir: $this->projectRoot . '/config',
        );
    }

    public function services(): AppServices
    {
        return $this->services ??= new AppServices($this->context());
    }

    private function errors(): AppErrors
    {
        return $this->errors ??= new AppErrors($this->services());
    }

    public function run(): int
    {
        try {
            return $this->application()->run();
        } catch (Throwable $e) {
            return $this->errors()->handleException($e);
        }
    }

    public function application(): Application
    {
        if ($this->application instanceof Application) {
            return $this->application;
        }

        $application = new Application(
            name: 'alix CLI',
            version: '0.1.0',
        );
        $application->setAutoExit(false);

        foreach ($this->commands() as $command) {
            $application->addCommand($command);
        }

        return $this->application = $application;
    }

    /**
     * @return list<Command>
     */
    private function commands(): array
    {
        $commandsFile = $this->commandsFile ?? $this->projectRoot . '/cliapp/commands.php';

        if (!is_file($commandsFile)) {
            throw new RuntimeException('Commands file not found: ' . $commandsFile);
        }

        /** @var mixed $factory */
        $factory = require $commandsFile;

        if (!is_callable($factory)) {
            throw new UnexpectedValueException('Invalid commands factory: ' . $commandsFile);
        }

        /** @var mixed $commands */
        $commands = $factory($this->context(), $this->services());

        if (!is_array($commands)) {
            throw new UnexpectedValueException('Invalid commands list returned by: ' . $commandsFile);
        }

        foreach ($commands as $command) {
            if (!$command instanceof Command) {
                throw new UnexpectedValueException('Invalid command instance returned by: ' . $commandsFile);
            }
        }

        /** @var list<Command> $commands */
        return $commands;
    }
}
