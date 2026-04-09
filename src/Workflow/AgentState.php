<?php

declare(strict_types=1);

namespace Maduser\Agent\Workflow;

enum AgentState: string
{
    case Bootstrapping = 'bootstrapping';
    case Thinking = 'thinking';
    case Processing = 'processing';
    case Storing = 'storing';
    case Summarizing = 'summarizing';
    case Done = 'done';
}
