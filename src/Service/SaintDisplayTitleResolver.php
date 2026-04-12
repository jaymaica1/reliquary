<?php

namespace App\Service;

use App\Entity\Saint;
use App\Enum\CanonicalStatus;
use App\Enum\SaintSex;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves the honorific prefix before a saint name (e.g. Santo/Santa) from DB fields.
 */
class SaintDisplayTitleResolver
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function resolveTitlePrefix(Saint $saint, string $locale): string
    {
        $status = $saint->getCanonicalStatus();
        if ($status === null) {
            return '';
        }

        $localeLower = strtolower(str_replace('-', '_', $locale));
        if (!str_starts_with($localeLower, 'pt')) {
            return $this->translator->trans($status->getTitleTransKey(), [], 'saint', $locale);
        }

        $key = $this->buildPortugueseTitleKey($status, $saint->isGroup(), $saint->getSex());

        return $this->translator->trans($key, [], 'saint', $locale);
    }

    private function buildPortugueseTitleKey(CanonicalStatus $status, bool $isGroup, SaintSex $sex): string
    {
        $sk = match ($status) {
            CanonicalStatus::CANONIZATION => 'canonization',
            CanonicalStatus::BEATIFICATION => 'beatification',
            CanonicalStatus::VENERATION => 'veneration',
            CanonicalStatus::SERVANT_OF_GOD => 'servant_of_god',
        };

        if (!$isGroup) {
            $slot = match ($sex) {
                SaintSex::MALE => 'singular_male',
                SaintSex::FEMALE => 'singular_female',
                SaintSex::UNKNOWN => 'singular_unknown',
            };
        } else {
            $slot = match ($sex) {
                SaintSex::FEMALE => 'plural_female',
                default => 'plural_male',
            };
        }

        return 'saint.title_prefix.'.$sk.'.'.$slot;
    }
}
