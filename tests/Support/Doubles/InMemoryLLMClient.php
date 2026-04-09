<?php

declare(strict_types=1);

namespace Tests\Support\Doubles;

use Maduser\Agent\LLM\Contracts\ProviderClientInterface;
use Maduser\Agent\LLM\LLMRequest;
use Maduser\Agent\LLM\LLMResponse;
use Override;

final class InMemoryLLMClient implements ProviderClientInterface
{
    /**
     * @var list<LLMRequest>
     */
    public array $requests = [];

    /**
     * @param list<LLMResponse> $responses
     */
    public function __construct(
        private array $responses,
    ) {
    }

    #[Override]
    public function send(LLMRequest $request): LLMResponse
    {
        $this->requests[] = $request;

        return array_shift($this->responses) ?? new LLMResponse(role: 'assistant', content: '');
    }
}
