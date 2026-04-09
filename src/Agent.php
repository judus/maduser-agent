<?php

declare(strict_types=1);

namespace Maduser\Agent;

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\LLM\LLMClient;
use Maduser\Agent\Processing\Contracts\ResponseProcessorInterface;
use Maduser\Agent\Tooling\Contracts\ToolRegistryInterface;
use Maduser\Agent\Tooling\EmptyToolRegistry;
use Maduser\Agent\Tooling\ToolExecutionPipeline;
use Maduser\Agent\Tooling\ToolInterface;
use Maduser\Agent\Tooling\ToolRegistry;
use Maduser\Agent\Workflow\Contracts\WorkflowProviderInterface;
use Maduser\Agent\Workflow\DefaultWorkflowProvider;
use Maduser\Argon\Workflows\WorkflowRunner;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final readonly class Agent
{
    public function __construct(
        private LLMClient $llm,
        private WorkflowProviderInterface $workflowProvider,
        private ?ContainerInterface $container = null,
        private ?AgentContext $context = null,
        private ToolExecutionPipeline|ToolRegistryInterface|null $tools = null,
        private string $workflowId = 'default',
        private ?ResponseProcessorInterface $responseProcessor = null,
    ) {
    }

    public static function packageName(): string
    {
        return 'maduser/agent';
    }

    public static function createDefault(
        LLMClient $llm,
        ToolExecutionPipeline|ToolRegistryInterface|null $tools = null,
        ?ContainerInterface $container = null,
        ?ResponseProcessorInterface $responseProcessor = null,
        ?LoggerInterface $logger = null,
    ): self {
        $toolPipeline = match (true) {
            $tools instanceof ToolExecutionPipeline => $tools,
            $tools instanceof ToolRegistryInterface => new ToolExecutionPipeline($tools),
            default => new ToolExecutionPipeline(new EmptyToolRegistry()),
        };

        return new self(
            llm: $llm,
            workflowProvider: new DefaultWorkflowProvider($llm, $toolPipeline, $responseProcessor, $logger),
            container: $container,
            responseProcessor: $responseProcessor,
        );
    }

    public function workflowRunner(): WorkflowRunner
    {
        return $this->workflowProvider->workflowRunner();
    }

    public function withContext(AgentContext $context): self
    {
        return new self(
            llm: $this->llm,
            workflowProvider: $this->workflowProvider,
            container: $this->container,
            context: $context,
            tools: $this->tools,
            workflowId: $this->workflowId,
            responseProcessor: $this->responseProcessor,
        );
    }

    public function withWorkflow(string $workflowId): self
    {
        return new self(
            llm: $this->llm,
            workflowProvider: $this->workflowProvider,
            container: $this->container,
            context: $this->context,
            tools: $this->tools,
            workflowId: $workflowId,
            responseProcessor: $this->responseProcessor,
        );
    }

    /**
     * @param list<ToolInterface|class-string<ToolInterface>> $tools
     */
    public function withTools(array $tools): self
    {
        $registry = (new ToolRegistry(container: $this->container))
            ->addTools($tools);

        return new self(
            llm: $this->llm,
            workflowProvider: $this->workflowProvider,
            container: $this->container,
            context: $this->context,
            tools: $tools === [] ? null : $registry,
            workflowId: $this->workflowId,
            responseProcessor: $this->responseProcessor,
        );
    }

    public function agent(
        ?LLMClient $llm = null,
        ?AgentContext $context = null,
        ToolExecutionPipeline|ToolRegistryInterface|null $tools = null,
        ?string $workflowId = null,
    ): AgentRunner {
        $resolvedLlm = $llm ?? $this->llm;
        $resolvedContext = $context ?? $this->context;
        $resolvedTools = $tools ?? $this->tools;
        $resolvedWorkflowId = $workflowId ?? $this->workflowId;

        if (!$resolvedContext instanceof AgentContext) {
            throw new \RuntimeException('AgentContext is required before running Agent.');
        }

        return new AgentRunner(
            llm: $resolvedLlm,
            context: $resolvedContext,
            tools: $resolvedTools,
            workflowRunner: $resolvedTools === null ? $this->workflowProvider->workflowRunner() : null,
            workflowId: $resolvedWorkflowId,
            container: $this->container,
            responseProcessor: $this->responseProcessor,
        );
    }

    public function ask(string $input): AgentResponse
    {
        return $this->agent()->query($input);
    }

    public function respond(): AgentResponse
    {
        return $this->agent()->respond();
    }
}
