<?php

namespace Qd\SchemaBundle\Controller;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class UserApiController
{
    private const REQUIRED_ROLE = 'ROLE_QD_EDIT';

    public function __construct(
        private EntityManagerInterface $em,
        private Security $security,
        private LoggerInterface $logger
    ) {
    }

    public function get(Request $request): JsonResponse
    {
        $currentUser = $this->security->getUser();

        if (!$currentUser) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        $userClass = get_class($currentUser);

        try {
            $metadata = $this->em->getClassMetadata($userClass);
            $tableName = $metadata->getTableName();
        } catch (\Exception $e) {
            $this->logger->error('Could not determine user table', [
                'exception' => $e->getMessage(),
                'userClass' => $userClass
            ]);
            return new JsonResponse(['error' => 'Could not determine user table'], 500);
        }

        $connection = $this->em->getConnection();
        $platform = $connection->getDatabasePlatform();

        $qb = $connection->createQueryBuilder();
        $qb->select('u.id', 'u.email')
            ->from($tableName, 'u');

        try {
            $columns = $connection->createSchemaManager()->listTableColumns($tableName);
            if (isset($columns['username'])) {
                $qb->addSelect('u.username');
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not list table columns for username detection', [
                'exception' => $e->getMessage(),
                'tableName' => $tableName
            ]);
        }

        if ($platform instanceof PostgreSQLPlatform) {
            $qb->andWhere("u.roles::text LIKE :role");
        } elseif ($platform instanceof SQLitePlatform) {
            $qb->andWhere("u.roles LIKE :role");
        } else {
            $qb->andWhere("u.roles LIKE :role");
        }

        $qb->setParameter('role', '%"' . self::REQUIRED_ROLE . '"%', ParameterType::STRING);

        try {
            $users = $qb->executeQuery()->fetchAllAssociative();

            $cleanUsers = array_map(function ($user) {
                $result = [
                    'id' => $user['id'],
                ];

                if (isset($user['email'])) {
                    $result['email'] = $user['email'];
                }

                if (isset($user['username'])) {
                    $result['username'] = $user['username'];
                }

                return $result;
            }, $users);

            return new JsonResponse(['users' => $cleanUsers]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Database query failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
