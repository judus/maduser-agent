<?php

declare(strict_types=1);

namespace Maduser\Agent\Workflow;

use Maduser\Agent\Processing\Contracts\ResponseProcessorInterface;
use Maduser\Agent\Processing\DefaultResponseProcessor;
use Maduser\Agent\Tooling\ToolExecutionPipeline;
use Maduser\Agent\Workflow\Handlers\BootstrapHandler;
use Maduser\Agent\Workflow\Handlers\ProcessingHandler;
use Maduser\Agent\Workflow\Handlers\StoringHandler;
use Maduser\Agent\Workflow\Handlers\SummarizingHandler;
use Maduser\Agent\Workflow\Handlers\ThinkingHandler;
use Maduser\Agent\LLM\LLMClient;
use Maduser\Argon\Workflows\StateHandlerRegistry;
use Maduser\Argon\Workflows\TransitionResolver;
use Maduser\Argon\Workflows\WorkflowDefinition;
use Maduser\Argon\Workflows\WorkflowRegistry;
use Maduser\Argon\Workflows\WorkflowRunner;
use Psr\Log\LoggerInterface;

final class DefaultWorkflowRunnerFactory
{
    public static function create(
        LLMClient $llm,
        ToolExecutionPipeline $toolPipeline,
        ?ResponseProcessorInterface $responseProcessor = null,
        ?LoggerInterface $logger = null,
    ): WorkflowRunner {
        $responseProcessor ??= new DefaultResponseProcessor();

        $handlers = new StateHandlerRegistry();
        $handlers->register(AgentState::Bootstrapping->value, new BootstrapHandler());
        $handlers->register(AgentState::Thinking->value, new ThinkingHandler($llm, $toolPipeline));
        $handlers->register(AgentState::Processing->value, new ProcessingHandler($responseProcessor));
        $handlers->register(AgentState::Storing->value, new StoringHandler());
        $handlers->register(AgentState::Summarizing->value, new SummarizingHandler($llm));

        $workflows = new WorkflowRegistry();
        $workflows->add('default', new WorkflowDefinition(
            staticTransitions: [
                AgentState::Bootstrapping->value => AgentState::Thinking->value,
                AgentState::Thinking->value => AgentState::Processing->value,
                AgentState::Processing->value => AgentState::Storing->value,
                AgentState::Storing->value => AgentState::Summarizing->value,
                AgentState::Summarizing->value => AgentState::Done->value,
            ],
            signalTransitions: [],
        ));

        return new WorkflowRunner(
            registry: $handlers,
            resolver: new TransitionResolver(),
            workflowRegistry: $workflows,
            logger: $logger,
        );
    }
}
