<?php

namespace App\Service\Ai;

use App\Exception\Ai\AiResponseTruncatedException;

interface AiChatProviderInterface
{
    public function getName(): string;

    public function supports(string $providerName): bool;

    /**
     * @param array<array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return string
     */
    public function chat(array $messages, array $options = []): string;
}
