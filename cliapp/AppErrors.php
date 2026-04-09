<?php

declare(strict_types=1);

namespace Maduser\AgentCli;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function fwrite;
use function sprintf;

final readonly class AppErrors
{
    public function __construct(
        private AppServices $services,
    ) {
    }

    public function handleException(Throwable $exception, ?OutputInterface $output = null): int
    {
        $this->services->logger()->error($exception->getMessage(), [
            'component' => 'alix-cli',
            'exception' => $exception,
        ]);

        if ($output instanceof OutputInterface) {
            $output->writeln($exception->getMessage());

            return Command::FAILURE;
        }

        fwrite(STDERR, sprintf("Error: %s
", $exception->getMessage()));

        return Command::FAILURE;
    }
}
