<?php

namespace App\Twig;

use App\Entity\Saint;
use App\Service\SaintDisplayTitleResolver;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SaintExtension extends AbstractExtension
{
    public function __construct(
        private readonly SaintDisplayTitleResolver $titleResolver,
        private readonly RequestStack $requestStack,
    ) {
    }
    
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_saint_name', [$this, 'formatSaintName']),
        ];
    }
    
    /**
     * Formats a saint's name with the appropriate title prefix
     * and uses translated name if available
     */
    public function formatSaintName(Saint $saint, ?string $locale = null): string
    {
        $canonicalStatus = $saint->getCanonicalStatus();
        $locale = $locale ?? $this->requestStack->getCurrentRequest()?->getLocale() ?? 'en';
        $name = $saint->getTranslatedName($locale);
        
        if ($canonicalStatus === null) {
            return $name ?? '';
        }

        $title = $this->titleResolver->resolveTitlePrefix($saint, $locale);

        return sprintf('%s %s', $title, $name);
    }
}