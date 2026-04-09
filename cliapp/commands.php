<?php

declare(strict_types=1);

use Maduser\AgentCli\AppContext;
use Maduser\AgentCli\AppServices;
use Maduser\AgentCli\Commands\ChatCommand;
use Maduser\AgentCli\Commands\QueryCommand;

return static function (AppContext $context, AppServices $services): array {
    return [
        new ChatCommand($services),
        new QueryCommand($services),
    ];
};
