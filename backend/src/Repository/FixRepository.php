<?php
namespace App\Repository;

use App\Entity\Fix;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FixRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fix::class);
    }

    public function findPendingByScan(int $scanId): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.scanResult = :scanId')
            ->andWhere('f.status = :status')
            ->setParameter('scanId', $scanId)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getResult();
    }
}