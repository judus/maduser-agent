<?php

declare(strict_types=1);

namespace Maduser\Agent\Workflow\Handlers;

use Maduser\Agent\Processing\Contracts\ResponseProcessorInterface;
use Maduser\Agent\Workflow\AgentWorkflowContext;
use Maduser\Argon\Workflows\Contracts\ContextInterface;
use Maduser\Argon\Workflows\Contracts\StateHandlerInterface;
use Maduser\Argon\Workflows\HandlerResult;
use Override;

final readonly class ProcessingHandler implements StateHandlerInterface
{
    public function __construct(
        private ResponseProcessorInterface $processor,
    ) {
    }

    #[Override]
    public function handle(ContextInterface $context): HandlerResult
    {
        if (!$context instanceof AgentWorkflowContext) {
            return new HandlerResult($context);
        }

        if ($context->response === null) {
            return new HandlerResult($context);
        }

        $state = $context->conversationState
            ?? $context->agentContext->conversationStateRepository->load($context->agentContext->id);

        $processed = $this->processor->process(
            response: $context->response,
            conversationState: $state,
            context: $context->agentContext,
        );

        return new HandlerResult(
            $context
                ->withConversationState($processed->conversationState)
                ->withProcessedResponse($processed)
                ->withTraceEntry([
                    'role' => 'assistant',
                    'content' => $processed->assistantMessage,
                ]),
        );
    }
}
