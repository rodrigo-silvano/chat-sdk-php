<?php

namespace App\Providers;

use Generator;

interface LlmAdapterInterface
{
    public function streamChat(array $params): Generator;
}
