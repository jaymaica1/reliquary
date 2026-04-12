<?php

namespace App\Service\Ai;

use App\Exception\Ai\AiResponseTruncatedException;
use App\Service\ConfigurationService;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class AiTextService
{
    public function __construct(
        #[TaggedIterator('app.ai_chat_provider')]
        private readonly iterable $providers,
        private readonly ConfigurationService $configurationService,
        private readonly string $defaultChatProvider = 'openai',
    ) {
    }

    /**
     * @param array<array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return string
     * @throws AiResponseTruncatedException
     */
    public function chat(array $messages, array $options = []): string
    {
        $providerName = $this->getActiveProviderName();

        foreach ($this->providers as $provider) {
            if ($provider->supports($providerName)) {
                return $provider->chat($messages, $options);
            }
        }

        throw new \RuntimeException(sprintf('AI chat provider "%s" not found', $providerName));
    }

    public function getActiveProviderName(): string
    {
        $configured = $this->configurationService->get('ai_chat_provider');

        return (is_string($configured) && $configured !== '')
            ? $configured
            : $this->defaultChatProvider;
    }
}
