<?php

namespace Qd\SchemaBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Qd\SchemaBundle\Entity\Release;
use Qd\SchemaBundle\Service\VersioningService;

class VersioningServiceTest extends TestCase
{
    private function createVersioningService(?Release $lastRelease = null): VersioningService
    {
        $query = $this->createMock(Query::class);
        $query->method('getOneOrNullResult')
            ->willReturn($lastRelease);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('orderBy')
            ->willReturnSelf();
        $queryBuilder->method('setMaxResults')
            ->willReturnSelf();
        $queryBuilder->method('getQuery')
            ->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->willReturn($repository);

        return new VersioningService($em);
    }

    public function testGetNextVersionReturnsV100ForFirstRelease(): void
    {
        $service = $this->createVersioningService(null);
        $version = $service->getNextVersion('minor');

        $this->assertEquals('v1.0.0', $version);
    }

    public function testGetNextVersionIncrementsMinor(): void
    {
        $lastRelease = new Release('v1.2.3', null, 'user');
        $service = $this->createVersioningService($lastRelease);

        $version = $service->getNextVersion('minor');

        $this->assertEquals('v1.3.0', $version);
    }

    public function testGetNextVersionIncrementsMajor(): void
    {
        $lastRelease = new Release('v1.2.3', null, 'user');
        $service = $this->createVersioningService($lastRelease);

        $version = $service->getNextVersion('major');

        $this->assertEquals('v2.0.0', $version);
    }

    public function testGetNextVersionIncrementsPatch(): void
    {
        $lastRelease = new Release('v1.2.3', null, 'user');
        $service = $this->createVersioningService($lastRelease);

        $version = $service->getNextVersion('patch');

        $this->assertEquals('v1.2.4', $version);
    }

    public function testIsValidVersionAcceptsValidVersions(): void
    {
        $service = $this->createVersioningService();

        $this->assertTrue($service->isValidVersion('v1.0.0'));
        $this->assertTrue($service->isValidVersion('1.0.0'));
        $this->assertTrue($service->isValidVersion('v10.20.30'));
        $this->assertTrue($service->isValidVersion('v0.1.0'));
    }

    public function testIsValidVersionRejectsInvalidVersions(): void
    {
        $service = $this->createVersioningService();

        $this->assertFalse($service->isValidVersion('v1.0'));
        $this->assertFalse($service->isValidVersion('invalid'));
        $this->assertFalse($service->isValidVersion(''));
    }

    public function testDetectVersionTypeReturnsMajorForLargeChanges(): void
    {
        $service = $this->createVersioningService();

        $summary = [
            'total_entities' => 100,
            'changed_entities' => 25,
            'added_entities' => 0,
        ];

        $type = $service->detectVersionType($summary);

        $this->assertEquals('major', $type);
    }

    public function testDetectVersionTypeReturnsMinorForNewEntities(): void
    {
        $service = $this->createVersioningService();

        $summary = [
            'total_entities' => 10,
            'changed_entities' => 1,
            'added_entities' => 2,
        ];

        $type = $service->detectVersionType($summary);

        $this->assertEquals('minor', $type);
    }

    public function testDetectVersionTypeReturnsPatchForSmallChanges(): void
    {
        $service = $this->createVersioningService();

        $summary = [
            'total_entities' => 10,
            'changed_entities' => 1,
            'added_entities' => 0,
        ];

        $type = $service->detectVersionType($summary);

        $this->assertEquals('patch', $type);
    }
}
