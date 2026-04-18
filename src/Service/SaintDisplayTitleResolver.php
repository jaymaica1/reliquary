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
    /**
     * Consonant-initial given names that still take "Santo" (eufonia / fixed tradition).
     *
     * @see https://ciberduvidas.iscte-iul.pt/consultorio/perguntas/santo--sao/15752
     */
    /** @var list<string> */
    private const PT_SAO_SANTO_FORCE_SANTO_FIRST_NAMES = [
        'tirso',
        'tomas',
    ];

    /**
     * Vowel-initial given names that take "São" instead of "Santo" (rare; extend if needed).
     *
     * @var list<string>
     */
    private const PT_SAO_SANTO_FORCE_SAO_FIRST_NAMES = [];

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

        $key = $this->buildPortugueseTitleKey($status, $saint, $locale);

        return $this->translator->trans($key, [], 'saint', $locale);
    }

    private function buildPortugueseTitleKey(CanonicalStatus $status, Saint $saint, string $locale): string
    {
        $sk = match ($status) {
            CanonicalStatus::CANONIZATION => 'canonization',
            CanonicalStatus::BEATIFICATION => 'beatification',
            CanonicalStatus::VENERATION => 'veneration',
            CanonicalStatus::SERVANT_OF_GOD => 'servant_of_god',
        };

        if (!$saint->isGroup()) {
            $slot = match ($saint->getSex()) {
                SaintSex::MALE => $this->resolvePortugueseSingularMaleSlot($status, $saint, $locale),
                SaintSex::FEMALE => 'singular_female',
                SaintSex::UNKNOWN => 'singular_unknown',
            };
        } else {
            $slot = match ($saint->getSex()) {
                SaintSex::FEMALE => 'plural_female',
                default => 'plural_male',
            };
        }

        return 'saint.title_prefix.'.$sk.'.'.$slot;
    }

    private function resolvePortugueseSingularMaleSlot(CanonicalStatus $status, Saint $saint, string $locale): string
    {
        if ($status !== CanonicalStatus::CANONIZATION) {
            return 'singular_male';
        }

        $firstWord = $this->firstWordOfDisplayName($saint->getTranslatedName($locale));
        if ($firstWord === null) {
            return 'singular_male';
        }

        $useSanto = $this->shouldUseSantoBeforePortugueseMaleSaintName($firstWord);

        return $useSanto ? 'singular_male_santo' : 'singular_male_sao';
    }

    /**
     * Portuguese: "São" before a consonant-initial given name, "Santo" before a vowel-initial one,
     * plus listed exceptions (e.g. Santo Tirso).
     */
    private function shouldUseSantoBeforePortugueseMaleSaintName(string $firstWord): bool
    {
        $key = $this->normalizePortugueseNameWord($firstWord);
        if ($key === '') {
            return true;
        }

        if (\in_array($key, self::PT_SAO_SANTO_FORCE_SAO_FIRST_NAMES, true)) {
            return false;
        }

        if (\in_array($key, self::PT_SAO_SANTO_FORCE_SANTO_FIRST_NAMES, true)) {
            return true;
        }

        $firstChar = mb_substr($key, 0, 1, 'UTF-8');

        return str_contains('aeiou', $firstChar);
    }

    private function firstWordOfDisplayName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        $trimmed = preg_replace('/^[\s"«»\'`]+/u', '', $trimmed) ?? $trimmed;
        if ($trimmed === '') {
            return null;
        }

        $parts = preg_split('/\s+/u', $trimmed);
        $first = $parts[0] ?? '';

        return $first !== '' ? $first : null;
    }

    private function normalizePortugueseNameWord(string $word): string
    {
        $word = trim($word);
        $word = preg_replace('/^[^\p{L}]+/u', '', $word) ?? $word;
        $word = preg_replace('/[^\p{L}]+$/u', '', $word) ?? $word;
        $lower = mb_strtolower($word, 'UTF-8');

        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($lower, \Normalizer::FORM_D);
            if (\is_string($decomposed)) {
                $lower = preg_replace('/\p{M}/u', '', $decomposed) ?? $lower;
            }
        }

        return $lower;
    }
}
