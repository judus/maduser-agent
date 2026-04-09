<?php

declare(strict_types=1);

namespace Maduser\Agent\Workflow\Contracts;

use Maduser\Argon\Workflows\WorkflowRunner;

interface WorkflowProviderInterface
{
    public function workflowRunner(): WorkflowRunner;
}
