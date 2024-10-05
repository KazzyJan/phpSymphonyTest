<?php

namespace App\Repository;

use App\Entity\NewUrl;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class NewUrlRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NewUrl::class);
    }
    public function saveUrl(string $url, \DateTimeImmutable $createdAt)
    {
        $urlEntity = new NewUrl();
        $urlEntity->setUrl($url);
        $urlEntity->setCreatedDate($createdAt);

        $this->_em->persist($urlEntity);
        $this->_em->flush();
    }

    public function countUniqueUrlsBetween(\DateTime $startDate, \DateTime $endDate): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.url)')
            ->where('u.createdDate BETWEEN :start AND :end')
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countUniqueUrlsByDomain(string $domain): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.domain)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
