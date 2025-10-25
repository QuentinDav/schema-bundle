<?php

namespace Qd\SchemaBundle\Controller;

use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class CommentApiController
{
    public function __construct(private EntityManagerInterface $em, private Security $security
    )
    {
    }

    public function get(Request $request): JsonResponse
    {
        $entityFqcn = $request->query->get('entityFqcn');
        if (empty($entityFqcn)) {
            return new JsonResponse(["message" => "Missing entityFqcn"], 400);
        }

        $rows = $this->em->getConnection()->executeQuery("select * from qd_schema_comment where entity_fqcn = :entityFqcn", ['entityFqcn' => $entityFqcn])->fetchAllAssociative();

        $comments = array_map(function ($row) {
            return [
                'id' => $row['id'],
                'entityFqcn' => $row['entity_fqcn'],
                'targetType' => 'table',
                'fieldName' => null,
                'content' => $row['body'],
                'author' => $row['author'] ?? 'Unknown',
                'createdAt' => $row['created_at'],
                'isSystem' => $row['is_system'],
            ];
        }, $rows);

        return new JsonResponse(['comments' => $comments]);
    }

    public function post(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $entityFqcn = $payload['entity_fqcn'] ?? null;
        $body = $payload['body'] ?? null;

        if (empty($entityFqcn)) {
            return new JsonResponse(["message" => "Missing entityFqcn"], 400);
        }

        if (empty($body)) {
            return new JsonResponse(["message" => "Missing body"], 400);
        }

        $user = $this->security->getUser()?->getEmail() ?? "Unknown";

        $createdAt = date('Y-m-d H:i:s');
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->em->getConnection()->executeStatement(
            "INSERT INTO qd_schema_comment (entity_fqcn, body, is_system, created_at, updated_at, author)
     VALUES (:entityFqcn, :body, :isSystem, :createdAt, :updatedAt, :author)",
            [
                'entityFqcn' => (string) $entityFqcn,
                'body'       => (string) $body,
                'isSystem'   => 0,
                'createdAt'  => $now,
                'updatedAt'  => $now,
                'author'     => (string) $user,
            ],
            [
                'entityFqcn' => ParameterType::STRING,
                'body'       => ParameterType::STRING,
                'isSystem'   => ParameterType::INTEGER,
                'createdAt'  => ParameterType::STRING,
                'updatedAt'  => ParameterType::STRING,
                'author'     => ParameterType::STRING,
            ]
        );

        $lastInsertId = $this->em->getConnection()->lastInsertId();

        $newComment = [
            'id' => (int)$lastInsertId,
            'entityFqcn' => $entityFqcn,
            'targetType' => 'table',
            'fieldName' => null,
            'content' => $body,
            'author' => $user,
            'createdAt' => $createdAt,
        ];

        return new JsonResponse($newComment);
    }

    public function delete(int $id): JsonResponse
    {
        $this->em->getConnection()->executeQuery("delete from qd_schema_comment where id = :id", ['id' => $id]);

        return new JsonResponse([]);
    }
}
