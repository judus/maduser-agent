<?php

declare(strict_types=1);

namespace Maduser\Agent;

use JsonException;
use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Processing\Contracts\ResponseProcessorInterface;
use Maduser\Agent\Tooling\EmptyToolRegistry;
use Maduser\Agent\Tooling\ToolInterface;
use Maduser\Agent\Tooling\Contracts\ToolRegistryInterface;
use Maduser\Agent\Tooling\ToolExecutionPipeline;
use Maduser\Agent\Tooling\ToolRegistry;
use Maduser\Agent\Workflow\AgentWorkflowContext;
use Maduser\Agent\Workflow\DefaultWorkflowRunnerFactory;
use Maduser\Agent\LLM\LLMClient;
use Maduser\Argon\Workflows\WorkflowRunner;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

final readonly class AgentRunner
{
    private ToolExecutionPipeline $toolPipeline;

    private WorkflowRunner $workflowRunner;

    public function __construct(
        private LLMClient $llm,
        private AgentContext $context,
        ToolExecutionPipeline|ToolRegistryInterface|null $tools = null,
        ?WorkflowRunner $workflowRunner = null,
        private string $workflowId = 'default',
        private ?ContainerInterface $container = null,
        ?ResponseProcessorInterface $responseProcessor = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->toolPipeline = match (true) {
            $tools instanceof ToolExecutionPipeline => $tools,
            $tools instanceof ToolRegistryInterface => new ToolExecutionPipeline($tools),
            default => new ToolExecutionPipeline(new EmptyToolRegistry()),
        };
        $this->workflowRunner = $workflowRunner ?? DefaultWorkflowRunnerFactory::create(
            llm: $this->llm,
            toolPipeline: $this->toolPipeline,
            responseProcessor: $responseProcessor,
            logger: $logger,
        );
    }

    public function context(): AgentContext
    {
        return $this->context;
    }

    public function withContext(AgentContext $context): self
    {
        return new self(
            llm: $this->llm,
            context: $context,
            tools: $this->toolPipeline,
            workflowRunner: $this->workflowRunner,
            workflowId: $this->workflowId,
            container: $this->container,
        );
    }

    public function withWorkflow(string $workflowId): self
    {
        return new self(
            llm: $this->llm,
            context: $this->context,
            tools: $this->toolPipeline,
            workflowRunner: $this->workflowRunner,
            workflowId: $workflowId,
            container: $this->container,
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
            context: $this->context,
            tools: $registry,
            workflowRunner: null,
            workflowId: $this->workflowId,
            container: $this->container,
        );
    }

    /**
     * @throws JsonException
     */
    public function query(string $input): AgentResponse
    {
        return $this->run($input);
    }

    public function respond(): AgentResponse
    {
        return $this->run('');
    }

    /**
     * @throws JsonException
     */
    private function run(string $input): AgentResponse
    {
        $context = new AgentWorkflowContext(
            agentContext: $this->context,
            input: $input,
            workflowId: $this->workflowId,
        );

        $result = $this->workflowRunner->run($context, $this->workflowId);

        if (!$result instanceof AgentWorkflowContext) {
            throw new RuntimeException('Workflow returned an unexpected context type.');
        }

        $response = $result->getFinalResponse()
            ?? throw new RuntimeException('Agent completed but returned no response.');

        return new AgentResponse(
            text: $response->content,
            trace: $result->trace,
            response: $response,
            artifacts: $result->artifacts,
        );
    }
}
