<?php

namespace App\Twig;

use App\Entity\Saint;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class SaintExtension extends AbstractExtension
{
    private TranslatorInterface $translator;
    private RequestStack $requestStack;
    
    public function __construct(TranslatorInterface $translator, RequestStack $requestStack)
    {
        $this->translator = $translator;
        $this->requestStack = $requestStack;
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
        
        $titleKey = $canonicalStatus->getTitleTransKey();
        $title = $this->translator->trans($titleKey, [], 'saint', $locale);
        
        return sprintf('%s %s', $title, $name);
    }
}