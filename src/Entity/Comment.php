<?php
namespace Qd\SchemaBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'qd_schema_comment')]
#[ORM\Index(columns: ['entity_fqcn', 'created_at'], name: 'idx_qd_comment_entity_created')]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(name: 'entity_fqcn', type: 'string', length: 255)]
    private string $entityFqcn;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'string', length: 180, nullable: true)]
    private ?string $author = null;

    #[ORM\Column(name: 'is_system', type: 'boolean', options: ['default' => false])]
    private bool $isSystem = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    public function __construct(string $entityFqcn, string $body, ?string $author = null, bool $isSystem = false)
    {
        $this->entityFqcn = $entityFqcn;
        $this->body = $body;
        $this->author = $author;
        $this->isSystem = $isSystem;
        $this->createdAt = new \DateTimeImmutable('now');
    }

    // getters/setters au besoin…
}
