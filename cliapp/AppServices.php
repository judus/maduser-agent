<?php

declare(strict_types=1);

namespace Maduser\AgentCli;

use Maduser\Agent\Agent;
use Maduser\Agent\Conversation\Contracts\ConversationStateRepositoryInterface;
use Maduser\Agent\Conversation\InMemoryConversationStateRepository;
use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Image\ImageGenerationClient;
use Maduser\Agent\LLM\LLMClient;
use Maduser\Agent\Tooling\ToolInterface;
use Maduser\Agent\Tooling\Tools\GenerateImageTool;
use Maduser\Agent\Tooling\Tools\GetTimeTool;
use Maduser\Agent\Tooling\Tools\GetWeatherTool;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

use function array_filter;
use function array_map;
use function explode;
use function is_string;
use function strtolower;
use function trim;

final class AppServices
{
    private ?LoggerInterface $logger = null;

    private ?LLMClient $llmClient = null;

    private ?Agent $agent = null;

    private ?ConversationStateRepositoryInterface $conversationStateRepository = null;

    private ?ImageGenerationClient $imageClient = null;

    public function __construct(
        private readonly AppContext $context,
    ) {
    }

    public function context(): AppContext
    {
        return $this->context;
    }

    public function logger(): LoggerInterface
    {
        return $this->logger ??= new NullLogger();
    }

    public function llmClient(): LLMClient
    {
        if ($this->llmClient instanceof LLMClient) {
            return $this->llmClient;
        }

        return $this->llmClient = LLMClient::openAiResponses(
            apiKey: $this->requireEnv('LLM_API_KEY'),
            defaultModel: $this->context->env('LLM_MODEL', 'gpt-4o'),
            baseUrl: $this->context->env('LLM_BASE_URL', 'https://api.openai.com'),
        );
    }

    public function agent(): Agent
    {
        return $this->agent ??= Agent::createDefault(
            llm: $this->llmClient(),
            logger: $this->logger(),
        );
    }

    public function imageClient(): ImageGenerationClient
    {
        if ($this->imageClient instanceof ImageGenerationClient) {
            return $this->imageClient;
        }

        return $this->imageClient = ImageGenerationClient::openAiImages(
            apiKey: $this->requireEnv('LLM_API_KEY'),
            defaultModel: $this->context->env('IMAGE_MODEL', 'gpt-image-1'),
            baseUrl: $this->context->env('LLM_BASE_URL', 'https://api.openai.com'),
        );
    }

    public function conversationStateRepository(): ConversationStateRepositoryInterface
    {
        return $this->conversationStateRepository ??= new InMemoryConversationStateRepository();
    }

    public function agentContext(
        string $sessionId = 'default-session',
        ?string $systemPrompt = null,
        ?string $model = null,
    ): AgentContext {
        $summaryAfter = $this->context->env('AGENT_SUMMARY_AFTER');

        if (!is_string($summaryAfter) || $summaryAfter === '') {
            $summaryAfter = $this->context->env('ALIX_SUMMARY_AFTER');
        }

        $normalizedSummaryAfter = is_string($summaryAfter) && $summaryAfter !== ''
            ? (int) $summaryAfter
            : 20;

        return new AgentContext(
            conversationStateRepository: $this->conversationStateRepository(),
            id: $sessionId,
            model: $model ?? $this->context->env('LLM_MODEL', 'gpt-4o'),
            systemPrompt: $systemPrompt,
            summaryAfter: $normalizedSummaryAfter,
        );
    }

    /**
     * @return list<ToolInterface>
     */
    public function toolsFromOption(?string $tools): array
    {
        if (!is_string($tools) || trim($tools) === '') {
            return [];
        }

        $aliases = array_values(array_filter(array_map(
            static fn (string $part): string => trim(strtolower($part)),
            explode(',', $tools),
        ), static fn (string $part): bool => $part !== ''));

        return array_values(array_map(
            fn (string $alias): ToolInterface => $this->resolveToolAlias($alias),
            $aliases,
        ));
    }

    private function requireEnv(string $key): string
    {
        $value = $this->context->env($key);

        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Missing required environment variable: ' . $key);
        }

        return $value;
    }

    private function resolveToolAlias(string $alias): ToolInterface
    {
        return match ($alias) {
            'time', 'get_time' => new GetTimeTool(),
            'weather', 'get_weather' => new GetWeatherTool(
                apiKey: $this->context->env('OPENWEATHER_API_KEY'),
                logger: $this->logger(),
            ),
            'image', 'generate_image' => new GenerateImageTool(
                imageClient: $this->imageClient(),
            ),
            default => throw new RuntimeException('Unknown tool alias: ' . $alias),
        };
    }
}
