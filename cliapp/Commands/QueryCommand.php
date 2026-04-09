<?php

declare(strict_types=1);

namespace Maduser\AgentCli\Commands;

use Maduser\AgentCli\AppServices;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function strlen;
use function substr;
use function is_string;
use function json_encode;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;

#[AsCommand(
    name: 'agent:query',
    description: 'Run one query through the default Agent workflow and print the result.',
)]
final class QueryCommand extends Command
{
    public function __construct(
        private readonly AppServices $services,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        $this->addArgument(
            name: 'prompt',
            mode: InputArgument::REQUIRED,
            description: 'The user prompt to send to Agent.',
        );

        $this->addOption(
            name: 'system',
            shortcut: 's',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Optional system prompt.',
        );

        $this->addOption(
            name: 'workflow',
            shortcut: 'w',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Workflow id to run.',
            default: 'default',
        );

        $this->addOption(
            name: 'json',
            shortcut: 'j',
            mode: InputOption::VALUE_NONE,
            description: 'Print the full AgentResponse as JSON.',
        );

        $this->addOption(
            name: 'tools',
            shortcut: 't',
            mode: InputOption::VALUE_REQUIRED,
            description: 'Comma-separated tool aliases to enable, e.g. time,weather.',
        );
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prompt = $this->requireStringArgument($input, 'prompt');
        $workflowId = $this->requireStringOption($input, 'workflow');
        $systemPrompt = $input->getOption('system');
        $toolOption = $input->getOption('tools');
        $normalizedSystemPrompt = is_string($systemPrompt) && $systemPrompt !== '' ? $systemPrompt : null;
        $tools = $this->services->toolsFromOption(is_string($toolOption) ? $toolOption : null);

        try {
            $response = $this->services
                ->agent()
                ->withContext($this->services->agentContext(systemPrompt: $normalizedSystemPrompt))
                ->withWorkflow($workflowId)
                ->withTools($tools)
                ->ask($prompt);
        } catch (Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }

        if ($input->getOption('json') === true) {
            $output->writeln(json_encode([
                'text' => $response->text,
                'trace' => $response->trace,
                'artifacts' => $response->artifacts,
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $output->writeln($response->text);

        foreach ($response->artifacts as $artifact) {
            $output->writeln('');
            $output->writeln('<comment>Artifact detected:</comment> ' . (string) ($artifact['type'] ?? 'unknown'));

            if (isset($artifact['revised_prompt']) && is_string($artifact['revised_prompt']) && $artifact['revised_prompt'] !== '') {
                $output->writeln('<comment>Revised prompt:</comment> ' . $artifact['revised_prompt']);
            }

            if (isset($artifact['base64']) && is_string($artifact['base64']) && $artifact['base64'] !== '') {
                $output->writeln('<comment>Base64 preview:</comment> ' . substr($artifact['base64'], 0, 80) . '... (' . strlen($artifact['base64']) . ' chars)');
            }
        }

        return self::SUCCESS;
    }

    private function requireStringArgument(InputInterface $input, string $name): string
    {
        $value = $input->getArgument($name);

        if (!is_string($value) || $value === '') {
            throw new \RuntimeException('Missing required argument: ' . $name);
        }

        return $value;
    }

    private function requireStringOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        if (!is_string($value) || $value === '') {
            throw new \RuntimeException('Missing required option: --' . $name);
        }

        return $value;
    }
}
