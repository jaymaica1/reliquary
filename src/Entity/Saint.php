<?php

namespace App\Entity;

use App\Enum\CanonicalStatus;
use App\Repository\SaintRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SaintRepository::class)]
class Saint implements ImageOwnerInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: 'string', enumType: CanonicalStatus::class, length: 255, nullable: true)]
    private ?CanonicalStatus $canonical_status = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $canonization_date = null;

    #[ORM\Column(type: "date", nullable: true)]
    private ?\DateTimeInterface $feast_date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $canonizing_pope = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image_link = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $is_incomplete = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $featured = false;

    /**
     * @var Collection<int, Relic>
     */
    #[ORM\OneToMany(targetEntity: Relic::class, mappedBy: 'saint')]
    private Collection $relics;

    /**
     * @var Collection<int, SaintTranslation>
     */
    #[ORM\OneToMany(mappedBy: 'saint', targetEntity: SaintTranslation::class, cascade: ['persist', 'remove'])]
    private Collection $translations;
    
    /**
     * @var Collection<int, SaintImage>
     */
    #[ORM\OneToMany(mappedBy: 'saint', targetEntity: SaintImage::class, cascade: ['persist', 'remove'])]
    private Collection $images;

    public function __construct()
    {
        $this->relics = new ArrayCollection();
        $this->translations = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Relic>
     */
    public function getRelics(): Collection
    {
        return $this->relics;
    }

    public function addRelic(Relic $relic): static
    {
        if (!$this->relics->contains($relic)) {
            $this->relics->add($relic);
            $relic->setSaint($this);
        }

        return $this;
    }

    public function removeRelic(Relic $relic): static
    {
        if ($this->relics->removeElement($relic)) {
            // set the owning side to null (unless already changed)
            if ($relic->getSaint() === $this) {
                $relic->setSaint(null);
            }
        }

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getCanonicalStatus(): ?CanonicalStatus
    {
        return $this->canonical_status;
    }

    public function setCanonicalStatus(?CanonicalStatus $canonical_status): static
    {
        $this->canonical_status = $canonical_status;

        return $this;
    }

    public function setCanonicalStatusFromString(?string $canonical_status): static
    {
        $this->canonical_status = $canonical_status ? CanonicalStatus::fromString($canonical_status) : null;

        return $this;
    }

    public function getCanonizationDate(): ?\DateTimeInterface
    {
        return $this->canonization_date;
    }

    public function setCanonizationDate(?\DateTimeInterface $canonization_date): static
    {
        $this->canonization_date = $canonization_date;

        return $this;
    }

    public function getCanonizingPope(): ?string
    {
        return $this->canonizing_pope;
    }

    public function setCanonizingPope(?string $canonizing_pope): static
    {
        $this->canonizing_pope = $canonizing_pope;

        return $this;
    }

    public function getSaintPhrase(): ?string
    {
        return $this->getTranslation('en')?->getSaintPhrase();
    }

    public function setSaintPhrase(?string $saint_phrase): static
    {
        $translation = $this->getTranslation('en');
        if (!$translation) {
            $translation = new SaintTranslation();
            $translation->setLocale('en');
            $this->addTranslation($translation);
        }
        $translation->setSaintPhrase($saint_phrase);

        return $this;
    }

    public function getAbstract(): ?string
    {
        return $this->getTranslation('en')?->getAbstract();
    }

    public function setAbstract(?string $abstract): static
    {
        $translation = $this->getTranslation('en');
        if (!$translation) {
            $translation = new SaintTranslation();
            $translation->setLocale('en');
            $this->addTranslation($translation);
        }
        $translation->setAbstract($abstract);

        return $this;
    }

    public function getBiography(): ?string
    {
        return $this->getTranslation('en')?->getBiography();
    }

    public function setBiography(?string $biography): static
    {
        $translation = $this->getTranslation('en');
        if (!$translation) {
            $translation = new SaintTranslation();
            $translation->setLocale('en');
            $this->addTranslation($translation);
        }
        $translation->setBiography($biography);

        return $this;
    }

    public function getImageLink(): ?string
    {
        return $this->image_link;
    }

    public function setImageLink(?string $image_link): static
    {
        $this->image_link = $image_link;

        return $this;
    }

    public function getFeastDate(): ?\DateTimeInterface
    {
        return $this->feast_date;
    }

    public function setFeastDate(?\DateTimeInterface $feast_date): static
    {
        $this->feast_date = $feast_date;

        return $this;
    }

    public function isIncomplete(): bool
    {
        return $this->is_incomplete;
    }

    public function setIsIncomplete(bool $is_incomplete): static
    {
        $this->is_incomplete = $is_incomplete;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->featured;
    }

    public function setFeatured(bool $featured): static
    {
        $this->featured = $featured;

        return $this;
    }

    /**
     * @return Collection<int, SaintTranslation>
     */
    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(SaintTranslation $translation): static
    {
        if (!$this->translations->contains($translation)) {
            $this->translations->add($translation);
            $translation->setSaint($this);
        }

        return $this;
    }

    public function removeTranslation(SaintTranslation $translation): static
    {
        if ($this->translations->removeElement($translation)) {
            // set the owning side to null (unless already changed)
            if ($translation->getSaint() === $this) {
                $translation->setSaint(null);
            }
        }

        return $this;
    }

    public function getTranslation(string $locale): ?SaintTranslation
    {
        foreach ($this->translations as $translation) {
            if ($translation->getLocale() === $locale) {
                return $translation;
            }
        }
        
        return null;
    }

    public function getTranslatedName(string $locale): ?string
    {
        $translation = $this->getTranslation($locale);
        
        return $translation?->getName() ?? $this->name;
    }
    
    public function getTranslatedPhrase(string $locale): ?string
    {
        $translation = $this->getTranslation($locale);
        
        return $translation?->getSaintPhrase() ?? $this->saint_phrase;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
    
    /**
     * @return Collection<int, SaintImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }
    
    public function addImage(AbstractImage $image): self
    {
        if (!$this->images->contains($image) && $image instanceof SaintImage) {
            $this->images->add($image);
            $image->setSaint($this);
        }
        
        return $this;
    }
    
    public function removeImage(AbstractImage $image): self
    {
        if ($this->images->removeElement($image) && $image instanceof SaintImage) {
            // set the owning side to null (unless already changed)
            if ($image->getSaint() === $this) {
                // This would throw an error since setSaint requires a non-null Saint
                // We can't set it to null, so we just remove the element
            }
        }
        
        return $this;
    }
}
