<?php

namespace MartenaSoft\ImageBundle\Repository;

use Doctrine\ORM\QueryBuilder;
use MartenaSoft\CommonLibrary\Dictionary\ImageDictionary;
use MartenaSoft\ImageBundle\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use MartenaSoft\PageBundle\Entity\Page;
use MartenaSoft\UserBundle\Entity\User;


class ImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    public function getQueryBuilder(string $type, int $activeSiteId, string $parentUuid, string $alias = 'i'): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($alias);

        if ($type === ImageDictionary::TYPE_PAGE) {
            $queryBuilder
                ->innerJoin(
                    Page::class,
                    'page',
                    'WITH',
                    $alias . '.parentUuid = page.uuid')
                ->andWhere("{$alias}.siteId = :siteId")
                ->andWhere("{$alias}.parentUuid = :parentUuid")
                ->setParameter("parentUuid", $parentUuid)
                ->setParameter("siteId", $activeSiteId)
            ;
        }

        if ($type === ImageDictionary::TYPE_USER) {
            $queryBuilder
                ->innerJoin(
                    User::class,
                    'user',
                    'WITH',
                    $alias . '.parentUuid = user.uuid')
                ->andWhere("{$alias}.siteId = :siteId")
                ->andWhere("{$alias}.parentUuid = :parentUuid")
                ->setParameter("parentUuid", $parentUuid)
                ->setParameter("siteId", $activeSiteId)
            ;
        }

        $queryBuilder->addOrderBy("{$alias}.isMain", "DESC");
        $queryBuilder->addOrderBy("{$alias}.createdAt", "DESC");

        return $queryBuilder;
    }

    public function delete(Image $image): void
    {
        $this->getEntityManager()->remove($image);
        $this->getEntityManager()->flush();
    }

    public function save(Image $image, bool $isFlush = true): void
    {
        $this->getEntityManager()->persist($image);
        if ($isFlush) {
            $this->getEntityManager()->flush();
        }
    }
}
