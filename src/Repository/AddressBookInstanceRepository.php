<?php

namespace App\Repository;

use App\Entity\AddressBookInstance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method AddressBookInstance|null find($id, $lockMode = null, $lockVersion = null)
 * @method AddressBookInstance|null findOneBy(array $criteria, array $orderBy = null)
 * @method AddressBookInstance[]    findAll()
 * @method AddressBookInstance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AddressBookInstanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AddressBookInstance::class);
    }

    /**
     * Shared (non-owner) instances for any of the given principal URIs.
     *
     * @param string[] $principalUris
     *
     * @return AddressBookInstance[]
     */
    public function findSharedByPrincipalUris(array $principalUris): array
    {
        if ([] === $principalUris) {
            return [];
        }

        return $this->createQueryBuilder('i')
            ->andWhere('i.principalUri IN (:uris)')
            ->andWhere('i.access NOT IN (:ownerAccess)')
            ->setParameter('uris', $principalUris)
            ->setParameter('ownerAccess', AddressBookInstance::getOwnerAccesses())
            ->getQuery()
            ->getResult();
    }
}
