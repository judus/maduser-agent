<?php

declare(strict_types=1);

namespace Maduser\Agent\Workflow\Handlers;

use JsonException;
use Maduser\Agent\Tooling\ToolExecutionPipeline;
use Maduser\Agent\Workflow\AgentWorkflowContext;
use Maduser\Agent\LLM\LLMClient;
use Maduser\Argon\Workflows\Contracts\ContextInterface;
use Maduser\Argon\Workflows\Contracts\StateHandlerInterface;
use Maduser\Argon\Workflows\HandlerResult;
use Override;

final readonly class ThinkingHandler implements StateHandlerInterface
{
    public function __construct(
        private LLMClient $llm,
        private ToolExecutionPipeline $toolPipeline,
    ) {
    }

    /**
     * @throws JsonException
     */
    #[Override]
    public function handle(ContextInterface $context): HandlerResult
    {
        if (!$context instanceof AgentWorkflowContext) {
            return new HandlerResult($context);
        }

        $state = $context->conversationState
            ?? $context->agentContext->conversationStateRepository->load($context->agentContext->id);
        $registry = $this->toolPipeline->getToolRegistry();
        $currentContext = $context;

        while (true) {
            $response = $this->llm->ask(
                history: $state->getRecentMessages($context->agentContext->maxMessages ?? 20),
                tools: $registry->listTools($context->agentContext->tools),
                model: $context->agentContext->model,
                systemPrompt: $this->buildSystemPrompt(
                    basePrompt: $context->agentContext->systemPrompt,
                    summary: $state->summary,
                ),
                options: $context->agentContext->options,
                vendorHints: $context->agentContext->vendorHints,
            );

            if ($response->toolCalls === []) {
                return new HandlerResult(
                    $currentContext
                        ->withResponse($response)
                );
            }

            $state = $state->addAssistantMessage($response->content, $response->toolCalls);

            foreach ($response->toolCalls as $call) {
                $currentContext = $currentContext->withTraceEntry($call->toArray());
                $functionResult = $this->toolPipeline
                    ->withContext($context->agentContext)
                    ->execute($call);

                $state = $state->addToolResult($functionResult);
                $currentContext = $currentContext->withTraceEntry($functionResult->toArray());

                if ($functionResult->artifact !== null) {
                    $currentContext = $currentContext->withArtifact([
                        'tool' => $call->name,
                        'call_id' => $call->id,
                        ...$functionResult->artifact,
                    ]);
                }
            }

            $currentContext = $currentContext->withConversationState($state);
        }
    }

    private function buildSystemPrompt(?string $basePrompt, string $summary): ?string
    {
        if ($summary === '') {
            return $basePrompt;
        }

        $summaryPrompt = "Conversation summary:\n" . $summary;

        if ($basePrompt === null || $basePrompt === '') {
            return $summaryPrompt;
        }

        return $basePrompt . "\n\n" . $summaryPrompt;
    }
}
