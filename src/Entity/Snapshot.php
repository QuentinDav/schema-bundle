<?php
namespace Qd\SchemaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qd_schema_snapshot')]
#[ORM\Index(columns: ['entity_fqcn', 'created_at'], name: 'idx_qd_snapshot_entity_created')]
#[ORM\Index(columns: ['schema_hash'], name: 'idx_qd_snapshot_hash')]
class Snapshot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(name: 'entity_fqcn', type: 'string', length: 255)]
    private string $entityFqcn;

    #[ORM\Column(name: 'schema_json', type: 'json')]
    private array $schemaJson;

    #[ORM\Column(name: 'schema_hash', type: 'string', length: 64)]
    private string $schemaHash;

    #[ORM\Column(name: 'diff_json', type: 'json', nullable: true)]
    private ?array $diffJson = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'created_by', type: 'string', length: 180, nullable: true)]
    private ?string $createdBy = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $tag = null;

    #[ORM\ManyToOne(targetEntity: Release::class, inversedBy: 'snapshots')]
    #[ORM\JoinColumn(name: 'release_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Release $release = null;

    public function __construct(string $entityFqcn, array $schemaJson, string $schemaHash, ?array $diffJson = null, ?string $createdBy = null, ?string $tag = null)
    {
        $this->entityFqcn = $entityFqcn;
        $this->schemaJson = $schemaJson;
        $this->schemaHash = $schemaHash;
        $this->diffJson = $diffJson;
        $this->createdAt = new \DateTimeImmutable('now');
        $this->createdBy = $createdBy;
        $this->tag = $tag;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEntityFqcn(): string
    {
        return $this->entityFqcn;
    }

    public function getSchemaJson(): array
    {
        return $this->schemaJson;
    }

    public function getSchemaHash(): string
    {
        return $this->schemaHash;
    }

    public function getDiffJson(): ?array
    {
        return $this->diffJson;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function getRelease(): ?Release
    {
        return $this->release;
    }

    public function setRelease(?Release $release): self
    {
        $this->release = $release;
        return $this;
    }
}
