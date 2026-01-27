<?php

namespace App\Entity;

use App\Enum\RelicDegree;
use App\Enum\RelicStatus;
use App\Repository\RelicRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RelicRepository::class)]
class Relic implements ImageOwnerInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'relics')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'relic.form.saint_error')]
    private ?Saint $saint = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private ?string $location = null;

    #[ORM\Column(length: 1024, nullable: true)]
    #[Assert\NotBlank(message: 'relic.form.address_error')]
    private ?string $address = null;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    private ?float $longitude = null;

    #[ORM\ManyToOne(inversedBy: 'relics')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $creator = null;

    #[ORM\Column(length: 255, nullable: false, enumType: RelicDegree::class,  options: ['default' => RelicDegree::UNKNOWN])]
    private RelicDegree $degree = RelicDegree::UNKNOWN;

    #[ORM\Column(length: 255, nullable: false, enumType: RelicStatus::class, options: ['default' => RelicStatus::PENDING])]
    private RelicStatus $status = RelicStatus::PENDING;

    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(length: 1024, nullable: false)]
    #[Assert\NotBlank]
    private ?string $provenance = null;

    /**
     * @var Collection<int, RelicImage>
     */
    #[ORM\OneToMany(targetEntity: RelicImage::class, mappedBy: 'relic', cascade: ['persist', 'remove'])]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSaint(): ?Saint
    {
        return $this->saint;
    }

    public function setSaint(?Saint $saint): static
    {
        $this->saint = $saint;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getDegree(): RelicDegree
    {
        return $this->degree;
    }

    public function setDegree(RelicDegree $degree): static
    {
        $this->degree = $degree;

        return $this;
    }

    public function getStatus(): RelicStatus
    {
        return $this->status;
    }

    public function setStatus(RelicStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getProvenance(): ?string
    {
        return $this->provenance;
    }

    public function setProvenance(?string $provenance): static
    {
        $this->provenance = $provenance;

        return $this;
    }
    
    /**
     * Get the marking for the workflow
     * This method is used by the workflow component
     */
    public function getMarking(): string
    {
        return $this->status->value;
    }
    
    /**
     * Set the marking from the workflow
     * This method is used by the workflow component
     */
    public function setMarking(string $marking, array $context = []): void
    {
        $this->status = RelicStatus::from($marking);
    }

    /**
     * @return Collection<int, RelicImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(AbstractImage $image): self
    {
        if ($image instanceof RelicImage) {
            if (!$this->images->contains($image)) {
                $this->images->add($image);
                $image->setRelic($this);
            }
        }

        return $this;
    }

    public function removeImage(AbstractImage $image): self
    {
        if ($image instanceof RelicImage) {
            if ($this->images->removeElement($image)) {
                // set the owning side to null (unless already changed)
                if ($image->getRelic() === $this) {
                    // Can't set to null as it's non-nullable
                }
            }
        }

        return $this;
    }
}
