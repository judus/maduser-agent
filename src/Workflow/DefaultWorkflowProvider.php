<?php

declare(strict_types=1);

namespace Maduser\Agent\Workflow;

use Maduser\Agent\Processing\Contracts\ResponseProcessorInterface;
use Maduser\Agent\Tooling\ToolExecutionPipeline;
use Maduser\Agent\Workflow\Contracts\WorkflowProviderInterface;
use Maduser\Agent\LLM\LLMClient;
use Maduser\Argon\Workflows\WorkflowRunner;
use Psr\Log\LoggerInterface;

final readonly class DefaultWorkflowProvider implements WorkflowProviderInterface
{
    private WorkflowRunner $workflowRunner;

    public function __construct(
        LLMClient $llm,
        ToolExecutionPipeline $toolPipeline,
        ?ResponseProcessorInterface $responseProcessor = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->workflowRunner = DefaultWorkflowRunnerFactory::create(
            llm: $llm,
            toolPipeline: $toolPipeline,
            responseProcessor: $responseProcessor,
            logger: $logger,
        );
    }

    #[\Override]
    public function workflowRunner(): WorkflowRunner
    {
        return $this->workflowRunner;
    }
}
