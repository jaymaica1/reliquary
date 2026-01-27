<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['username'], message: 'There is already an account with this username')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface, ImageOwnerInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $username = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $geolocationTimestamp = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $allowGeolocationStorage = true;

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    /**
     * @var Collection<int, Relic>
     */
    #[ORM\OneToMany(targetEntity: Relic::class, mappedBy: 'creator')]
    private Collection $relics;

    /**
     * @var Collection<int, UserImage>
     */
    #[ORM\OneToMany(targetEntity: UserImage::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private Collection $images;

    public function __construct()
    {
        $this->relics = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    public function isAdmin(): bool
    {
        return method_exists($this, 'getRoles') && in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

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
            $relic->setCreator($this);
        }

        return $this;
    }

    public function removeRelic(Relic $relic): static
    {
        if ($this->relics->removeElement($relic)) {
            // set the owning side to null (unless already changed)
            if ($relic->getCreator() === $this) {
                $relic->setCreator(null);
            }
        }

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

    public function getGeolocationTimestamp(): ?\DateTimeInterface
    {
        return $this->geolocationTimestamp;
    }

    public function setGeolocationTimestamp(?\DateTimeInterface $geolocationTimestamp): static
    {
        $this->geolocationTimestamp = $geolocationTimestamp;

        return $this;
    }

    /**
     * Set the user's geolocation data
     *
     * @param float $latitude
     * @param float $longitude
     * @return $this
     */
    public function setGeolocation(float $latitude, float $longitude): static
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->geolocationTimestamp = new \DateTime();

        return $this;
    }

    /**
     * @return Collection<int, UserImage>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(AbstractImage $image): self
    {
        if ($image instanceof UserImage) {
            if (!$this->images->contains($image)) {
                $this->images->add($image);
                $image->setUser($this);
            }
        }

        return $this;
    }

    public function removeImage(AbstractImage $image): self
    {
        if ($image instanceof UserImage) {
            if ($this->images->removeElement($image)) {
                // set the owning side to null (unless already changed)
                if ($image->getUser() === $this) {
                    // Can't set to null as it's non-nullable
                }
            }
        }

        return $this;
    }

    public function getProfileImage(): ?UserImage
    {
        return $this->images->isEmpty() ? null : $this->images->first();
    }

    public function isAllowGeolocationStorage(): bool
    {
        return $this->allowGeolocationStorage;
    }

    public function setAllowGeolocationStorage(bool $allowGeolocationStorage): static
    {
        $this->allowGeolocationStorage = $allowGeolocationStorage;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
