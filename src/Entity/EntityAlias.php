<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entity alias for Natural Language queries.
 *
 * Allows users to define alternative names for entities (e.g., "client" -> "User")
 * to improve NL to SQL query understanding and reduce token usage.
 */
#[ORM\Entity(repositoryClass: 'Qd\SchemaBundle\Repository\EntityAliasRepository')]
#[ORM\Table(name: 'qd_entity_alias')]
#[UniqueEntity(fields: ['alias'], message: 'This alias is already used by another entity.')]
#[ORM\HasLifecycleCallbacks]
class EntityAlias
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * The fully qualified class name of the entity.
     */
    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private string $entityFqcn;

    /**
     * The alias name (must be unique across all entities).
     */
    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_\-àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]+$/u',
        message: 'Alias can only contain letters, numbers, hyphens, and underscores.'
    )]
    private string $alias;

    /**
     * Optional language code (e.g., 'en', 'fr', 'es').
     */
    #[ORM\Column(type: 'string', length: 5, nullable: true)]
    #[Assert\Length(max: 5)]
    private ?string $language = null;

    /**
     * Optional description explaining this alias.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityFqcn(): string
    {
        return $this->entityFqcn;
    }

    public function setEntityFqcn(string $entityFqcn): self
    {
        $this->entityFqcn = $entityFqcn;
        return $this;
    }

    public function getAlias(): string
    {
        return $this->alias;
    }

    public function setAlias(string $alias): self
    {
        $this->alias = mb_strtolower(trim($alias));
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $this->language = $language ? mb_strtolower(trim($language)) : null;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'entityFqcn' => $this->entityFqcn,
            'alias' => $this->alias,
            'language' => $this->language,
            'description' => $this->description,
            'createdAt' => $this->createdAt->format('c'),
            'updatedAt' => $this->updatedAt->format('c'),
        ];
    }
}
