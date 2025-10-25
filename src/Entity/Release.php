<?php

namespace Qd\SchemaBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qd_schema_release')]
#[ORM\Index(columns: ['created_at'], name: 'idx_qd_release_created')]
#[ORM\UniqueConstraint(name: 'UNIQ_RELEASE_NAME', columns: ['name'])]
class Release
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $name;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'created_by', type: 'string', length: 180, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\OneToMany(mappedBy: 'release', targetEntity: Snapshot::class, cascade: ['persist'])]
    private Collection $snapshots;

    public function __construct(string $name, ?string $description = null, ?string $createdBy = null)
    {
        $this->name = $name;
        $this->description = $description;
        $this->createdAt = new \DateTimeImmutable();
        $this->createdBy = $createdBy;
        $this->snapshots = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function getSnapshots(): Collection
    {
        return $this->snapshots;
    }

    public function addSnapshot(Snapshot $snapshot): self
    {
        if (!$this->snapshots->contains($snapshot)) {
            $this->snapshots->add($snapshot);
            $snapshot->setRelease($this);
        }
        return $this;
    }

    public function removeSnapshot(Snapshot $snapshot): self
    {
        if ($this->snapshots->removeElement($snapshot)) {
            if ($snapshot->getRelease() === $this) {
                $snapshot->setRelease(null);
            }
        }
        return $this;
    }

    /**
     * Get summary statistics for this release
     */
    public function getSummary(): array
    {
        $total = $this->snapshots->count();
        $changed = 0;
        $added = 0;

        foreach ($this->snapshots as $snapshot) {
            if ($snapshot->getDiffJson() === null) {
                $added++;
            } elseif (!$this->isDiffEmpty($snapshot->getDiffJson())) {
                $changed++;
            }
        }

        return [
            'total_entities' => $total,
            'changed_entities' => $changed,
            'added_entities' => $added,
        ];
    }

    private function isDiffEmpty(array $diff): bool
    {
        foreach ($diff as $v) {
            if (!empty($v)) {
                return false;
            }
        }
        return true;
    }
}
