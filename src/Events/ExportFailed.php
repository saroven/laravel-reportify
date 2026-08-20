<?php

declare(strict_types=1);

namespace Saroven\Reportify\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ExportFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly int|string|null $userId,
        public readonly string $title,
        public readonly string $exportFormat,
        public readonly string $errorMessage,
        public readonly array $payload = []
    ) {}
}
