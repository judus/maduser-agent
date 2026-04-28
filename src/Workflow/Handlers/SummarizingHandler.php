<?php

declare(strict_types=1);

namespace Maduser\Agent\Workflow\Handlers;

use Maduser\Agent\Workflow\AgentWorkflowContext;
use Maduser\Agent\LLM\LLMClient;
use Maduser\Agent\LLM\Message\Contracts\MessageInterface;
use Maduser\Argon\Workflows\Contracts\ContextInterface;
use Maduser\Argon\Workflows\Contracts\StateHandlerInterface;
use Maduser\Argon\Workflows\HandlerResult;
use Override;

use function array_filter;
use function array_values;
use function count;
use function implode;
use function is_array;
use function is_string;
use function max;
use function trim;

final class SummarizingHandler implements StateHandlerInterface
{
    public function __construct(
        private readonly LLMClient $llm,
    ) {
    }

    #[Override]
    public function handle(ContextInterface $context): HandlerResult
    {
        if (!$context instanceof AgentWorkflowContext) {
            return new HandlerResult($context);
        }

        $summaryAfter = $context->agentContext->summaryAfter;
        $state = $context->conversationState
            ?? $context->agentContext->conversationStateRepository->load($context->agentContext->id);

        if ($summaryAfter === null || $summaryAfter < 1) {
            return new HandlerResult($context->withConversationState($state));
        }

        $history = $state->history;

        if (count($history) <= $summaryAfter) {
            return new HandlerResult($context->withConversationState($state));
        }

        $retainedCount = max(2, intdiv($summaryAfter, 2));
        $retainedHistory = \Maduser\Agent\Support\HistoryTrimmer::safeTail($history, $retainedCount);
        $overflowCount = count($history) - count($retainedHistory);

        if ($overflowCount <= 0) {
            return new HandlerResult($context);
        }

        $overflow = \array_slice($history, 0, $overflowCount);
        $summary = trim($this->summarizeOverflow(
            previousSummary: $state->summary,
            messages: $overflow,
            model: $context->agentContext->model,
            vendorHints: $context->agentContext->vendorHints,
        ));

        if ($summary === '') {
            return new HandlerResult($context->withConversationState($state));
        }

        $updatedState = $state
            ->withSummary($summary)
            ->withHistory($retainedHistory);

        $context->agentContext->conversationStateRepository->save(
            $context->agentContext->id,
            $updatedState,
        );

        return new HandlerResult($context->withConversationState($updatedState));
    }

    /**
     * @param list<MessageInterface> $messages
     * @param array<string, mixed> $vendorHints
     */
    private function summarizeOverflow(
        ?string $previousSummary,
        array $messages,
        ?string $model,
        array $vendorHints,
    ): string {
        $prompt = implode("\n\n", [
            'Update the internal conversation summary.',
            'Rewrite the summary from scratch using the previous summary and the messages to compress.',
            'Keep it factual, compact, and focused on durable context only.',
            'Preserve stable user preferences, commitments, relationship shifts, accepted or rejected offers, meaningful facts, and unresolved threads.',
            'Drop greetings, filler, transient banter, temporary scene staging, procedural chatter, UI choice labels, and already-resolved details.',
            'Do not duplicate points that are already covered by the previous summary.',
            'Prefer terse bullet points and keep the final summary under 10 bullets.',
            'Do not include stylistic flourish or quote dialogue unless the wording itself matters long-term.',
            '',
            'Previous summary:',
            $previousSummary !== null && $previousSummary !== '' ? $previousSummary : '[none]',
            '',
            'Messages to compress:',
            $this->buildTranscript($messages),
        ]);

        $response = $this->llm->ask(
            $prompt,
            model: $model,
            vendorHints: $vendorHints,
        );

        return $response->content;
    }

    /**
     * @param list<MessageInterface> $messages
     */
    private function buildTranscript(array $messages): string
    {
        $lines = [];

        foreach ($messages as $message) {
            /** @var array<string, mixed> $data */
            $data = $message->toArray();
            /** @var mixed $content */
            $content = $data['content'] ?? '';
            /** @var mixed $images */
            $images = $data['images'] ?? null;

            $normalizedContent = is_string($content) ? trim($content) : '';
            $imageCount = 0;

            if (is_array($images)) {
                $imageCount = count(array_values(array_filter(
                    $images,
                    static fn (mixed $image): bool => is_string($image) && trim($image) !== '',
                )));
            }

            $parts = [];

            if ($normalizedContent !== '') {
                $parts[] = $normalizedContent;
            }

            if ($imageCount > 0) {
                $parts[] = $imageCount === 1
                    ? '[attached 1 image]'
                    : '[attached ' . $imageCount . ' images]';
            }

            if ($parts === []) {
                continue;
            }

            $lines[] = strtoupper($message->getRole()) . ': ' . implode(' ', $parts);
        }

        return implode("\n", $lines);
    }
}
