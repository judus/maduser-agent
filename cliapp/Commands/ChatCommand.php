<?php

declare(strict_types=1);

namespace Maduser\AgentCli\Commands;

use Maduser\AgentCli\AppServices;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use function fgets;
use function is_string;
use function strlen;
use function substr;
use function trim;

#[AsCommand(
    name: 'agent:chat',
    description: 'Start a long-running interactive Agent chat session.',
)]
final class ChatCommand extends Command
{
    public function __construct(
        private readonly AppServices $services,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
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
            name: 'session',
            shortcut: null,
            mode: InputOption::VALUE_REQUIRED,
            description: 'Session id to use.',
            default: 'chat-session',
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
        $workflowId = $this->requireStringOption($input, 'workflow');
        $sessionId = $this->requireStringOption($input, 'session');
        $systemPrompt = $input->getOption('system');
        $toolOption = $input->getOption('tools');
        $normalizedSystemPrompt = is_string($systemPrompt) && $systemPrompt !== '' ? $systemPrompt : null;
        $tools = $this->services->toolsFromOption(is_string($toolOption) ? $toolOption : null);

        $chat = $this->services
            ->agent()
            ->withContext($this->services->agentContext(
                sessionId: $sessionId,
                systemPrompt: $normalizedSystemPrompt,
            ))
            ->withWorkflow($workflowId)
            ->withTools($tools);

        $output->writeln('<info>Interactive Agent chat started. Type /exit to quit.</info>');

        while (true) {
            $output->write('> ');
            $line = fgets(STDIN);

            if ($line === false) {
                $output->writeln('');

                return self::SUCCESS;
            }

            $prompt = trim($line);

            if ($prompt === '') {
                continue;
            }

            if ($prompt === '/exit' || $prompt === '/quit') {
                $output->writeln('<info>Bye.</info>');

                return self::SUCCESS;
            }

            try {
                $response = $chat->ask($prompt);
            } catch (Throwable $e) {
                $output->writeln('<error>' . $e->getMessage() . '</error>');
                continue;
            }

            $output->writeln('');
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

            $output->writeln('');
        }
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
