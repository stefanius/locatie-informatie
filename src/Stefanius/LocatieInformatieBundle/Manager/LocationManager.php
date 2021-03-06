<?php

namespace Stefanius\LocatieInformatieBundle\Manager;

use Doctrine\Common\Persistence\ObjectManager;
use Stefanius\LocatieInformatieBundle\Entity\City;
use Stefanius\LocatieInformatieBundle\Entity\Location;
use Stefanius\LocatieInformatieBundle\Entity\Municipality;
use Stefanius\LocatieInformatieBundle\Entity\Street;
use Stefanius\LocatieInformatieBundle\Entity\ZipCode;
use Stefanius\SimpleCmsBundle\Manager\AbstractObjectManager;
use Stefanius\Slugifier\Manipulators\SlugManipulator;

abstract class LocationManager extends AbstractObjectManager
{
    /**
     * @var SlugManipulator
     */
    protected $slugifier;

    /**
     * @param ObjectManager $om
     * @param SlugManipulator $slugifier
     */
    function __construct(ObjectManager $om, SlugManipulator $slugifier)
    {
        $this->om = $om;
        $this->slugifier = $slugifier;
    }

    /**
     * @return \Doctrine\Common\Persistence\ObjectRepository
     */
    public function getRepository()
    {
        return $this->om->getRepository($this->repoName);
    }

    /**
     * @param Location $entity
     * @return string
     */
    protected function createLocationCode($entity)
    {
        if ($entity instanceof City) {
            if ($entity->getMunicipality() === null) {
                var_dump(' EMPTY GEMETTEN    -    ' . $entity->getTitle());
            }
            return 'CITY:' . $entity->getMunicipality()->getProvince()->getProvinceCode() . ':' . strtoupper(str_replace('-', '', $entity->getSlug()));
        } else if ($entity instanceof Municipality) {
            return 'MUNI:' . $entity->getProvince()->getProvinceCode() . ':' . strtoupper(str_replace('-', '', $entity->getSlug()));
        } else if ($entity instanceof ZipCode) {
            return 'ZIP:' . $entity->getPnum() . $entity->getPchar();
        } else if ($entity instanceof Street) {
            if ($entity->getCity() !== null) {
                $code = str_replace('-', '', $this->slugifier->manipulate((substr($entity->getCity()->getTitle(), 0, 5)))) . str_replace('-', '', $this->slugifier->manipulate(substr($entity->getSlug(), 0, 5))) ;
            } else {
                $code = str_replace('-', '', $this->slugifier->manipulate(substr($entity->getSlug(), 0, 5)));
            }

            return 'STRT:' . $code . md5($code);
        }


        return null;
    }

    /**
     * @param Location $entity
     */
    public function persist($entity)
    {
        if (empty($entity->getTitleLng()) || strlen($entity->getTitleLng()) < strlen($entity->getTitle()) ) {
            $entity->setTitleLng($entity->getTitle());
        }

        //if (empty($entity->getLocationCode())) {
            $code = $this->createLocationCode($entity);
            $entity->setLocationCode($code);
        //}

        if ($entity->getCreated() === null) {
            $entity->setCreated(new \DateTime());
        }

        $entity->setModified(new \DateTime());

        parent::persist($entity);
    }

    public function getListByFirstLetter($letter)
    {
        if ($letter === 'apostrof') {
            $letter = "'";
        }

        $repo = $this->om->getRepository($this->repoName);

        $query = $repo->createQueryBuilder('c')
            ->where('c.title LIKE :title')
            ->setParameter('title', $letter . '%')
            ->getQuery();

        return $query->getResult();
    }

    public function findByCbsCode($cbsCode)
    {
        $repo = $this->om->getRepository($this->repoName);

        $query = $repo->createQueryBuilder('c')
            ->where('c.cbsCode LIKE :cbs_code')
            ->setParameter('cbs_code', $cbsCode)
            ->getQuery();

        return $query->getOneOrNullResult();
    }

    public function findOneByTitle($title)
    {
        $entity = $this->om->getRepository($this->repoName)->findOneByTitle($title);

        return $entity;
    }

    public function findOneBySlug($slug)
    {
        $entity = $this->om->getRepository($this->repoName)->findOneBySlug($slug);

        return $entity;
    }
}