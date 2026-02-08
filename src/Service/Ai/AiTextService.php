<?php

namespace App\Service\Ai;

use App\Service\ConfigurationService;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class AiTextService
{
    public function __construct(
        #[TaggedIterator('app.ai_chat_provider')]
        private readonly iterable $providers,
        private readonly ConfigurationService $configurationService
    ) {
    }

    public function chat(array $messages, array $options = []): string
    {
        $providerName = $this->configurationService->get('ai_chat_provider', 'openai');
        
        foreach ($this->providers as $provider) {
            if ($provider->supports($providerName)) {
                return $provider->chat($messages, $options);
            }
        }

        throw new \RuntimeException(sprintf('AI chat provider "%s" not found', $providerName));
    }
}
