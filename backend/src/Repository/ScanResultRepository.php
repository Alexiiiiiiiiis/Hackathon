<?php
namespace App\Repository;

use App\Entity\ScanResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ScanResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScanResult::class);
    }

    public function findWithVulnerabilities(int $id): ?ScanResult
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.vulnerabilities', 'v')
            ->addSelect('v')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}