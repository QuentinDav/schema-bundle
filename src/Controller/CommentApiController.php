<?php

namespace Qd\SchemaBundle\Controller;

use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\Mapping\MappingException;
use Qd\SchemaBundle\Dto\CreateCommentDto;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class CommentApiController
{
    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    )
    {
    }

    public function get(Request $request): JsonResponse
    {
        $entityFqcn = $request->query->get('entityFqcn');
        if (empty($entityFqcn)) {
            return new JsonResponse(["message" => "Missing entityFqcn"], 400);
        }

        $qb = $this->em->getConnection()->createQueryBuilder();
        $qb->select('*')
            ->from('qd_schema_comment')
            ->where('entity_fqcn = :entityFqcn')
            ->setParameter('entityFqcn', $entityFqcn, ParameterType::STRING);

        $rows = $qb->executeQuery()->fetchAllAssociative();

        $comments = array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'entityFqcn' => $row['entity_fqcn'],
                'targetType' => 'table',
                'fieldName' => null,
                'content' => $row['body'],
                'author' => $row['author'] ?? 'Unknown',
                'createdAt' => $row['created_at'],
                'isSystem' => (bool) $row['is_system'],
            ];
        }, $rows);

        return new JsonResponse(['comments' => $comments]);
    }

    public function post(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!is_array($payload)) {
            return new JsonResponse(["message" => "Invalid JSON payload"], 400);
        }

        $dto = new CreateCommentDto(
            entityFqcn: $payload['entity_fqcn'] ?? '',
            body: $payload['body'] ?? ''
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(["message" => "Validation failed", "errors" => $errorMessages], 400);
        }

        try {
            $this->em->getClassMetadata($dto->entityFqcn);
        } catch (MappingException $e) {
            return new JsonResponse([
                "message" => "Invalid entity FQCN. Entity does not exist in Doctrine metadata.",
                "entityFqcn" => $dto->entityFqcn
            ], 400);
        }

        $user = $this->security->getUser()?->getEmail() ?? "Unknown";
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $connection = $this->em->getConnection();

        $qb = $connection->createQueryBuilder();
        $qb->insert('qd_schema_comment')
            ->values([
                'entity_fqcn' => ':entityFqcn',
                'body' => ':body',
                'is_system' => ':isSystem',
                'created_at' => ':createdAt',
                'updated_at' => ':updatedAt',
                'author' => ':author',
            ])
            ->setParameter('entityFqcn', $dto->entityFqcn, ParameterType::STRING)
            ->setParameter('body', $dto->body, ParameterType::STRING)
            ->setParameter('isSystem', 0, ParameterType::INTEGER)
            ->setParameter('createdAt', $now, ParameterType::STRING)
            ->setParameter('updatedAt', $now, ParameterType::STRING)
            ->setParameter('author', $user, ParameterType::STRING);

        $qb->executeStatement();

        $lastInsertId = $connection->lastInsertId();

        $newComment = [
            'id' => (int) $lastInsertId,
            'entityFqcn' => $dto->entityFqcn,
            'targetType' => 'table',
            'fieldName' => null,
            'content' => $dto->body,
            'author' => $user,
            'createdAt' => $now,
            'isSystem' => false,
        ];

        return new JsonResponse($newComment);
    }

    public function delete(int $id): JsonResponse
    {
        $qb = $this->em->getConnection()->createQueryBuilder();
        $comment = $qb->select('*')
            ->from('qd_schema_comment')
            ->where('id = :id')
            ->setParameter('id', $id, ParameterType::INTEGER)
            ->executeQuery()
            ->fetchAssociative();

        if (!$comment) {
            return new JsonResponse(['message' => 'Comment not found'], 404);
        }

        $currentUser = $this->security->getUser()?->getEmail() ?? 'Unknown';
        $isAuthor = $comment['author'] === $currentUser;
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');

        if (!$isAuthor && !$isAdmin) {
            return new JsonResponse(['message' => 'You are not authorized to delete this comment'], 403);
        }

        $deleteQb = $this->em->getConnection()->createQueryBuilder();
        $deleteQb->delete('qd_schema_comment')
            ->where('id = :id')
            ->setParameter('id', $id, ParameterType::INTEGER);

        $deleteQb->executeStatement();

        return new JsonResponse(['success' => true]);
    }
}
