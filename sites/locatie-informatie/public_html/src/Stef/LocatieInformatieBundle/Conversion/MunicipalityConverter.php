<?php
namespace Stef\LocatieInformatieBundle\Conversion;

use Doctrine\ORM\QueryBuilder;
use Stef\LocatieInformatieBundle\Entity\Municipality;
use Stef\LocatieInformatieBundle\Entity\Postcode;
use Stef\LocatieInformatieBundle\Manager\MunicipalityManager;
use Stef\LocatieInformatieBundle\Manager\PostcodeManager;
use Stef\SlugManipulation\Manipulators\SlugManipulator;

class MunicipalityConverter extends AbstractConverter
{
    /**
     * @var PostcodeManager
     */
    protected $postcodeManager;

    /**
     * @var MunicipalityManager
     */
    protected $municipalityManager;

    /**
     * @var SlugManipulator
     */
    protected $slugifier;

    function __construct(PostcodeManager $postcodeManager, MunicipalityManager $municipalityManager, SlugManipulator $slugifier)
    {
        $this->postcodeManager = $postcodeManager;
        $this->municipalityManager = $municipalityManager;
        $this->slugifier = $slugifier;
    }

    public function convert()
    {
        /**
         * @var $qbPostcode QueryBuilder
         */
        $qbPostcode = $this->postcodeManager->getRepository()->createQueryBuilder('p');

        $this->doStuff($qbPostcode->select('p')->groupBy('p.municipality')->getQuery()->getResult(), 'municipality');
        $this->doStuff($qbPostcode->select('p')->groupBy('p.municipalityId')->getQuery()->getResult(), 'id');
    }

    protected function doStuff($entities, $type)
    {
        $correction = new Correction($this->municipalityManager);

        /**
         * @var $p Postcode
         */
        foreach ($entities as $p) {
            if ($type === 'id') {
                $entity = $this->municipalityManager->getRepository()->findOneBySourceLocationTypeId($p->getMunicipalityId());
            } else {
                $entity = $this->municipalityManager->getRepository()->findOneByTitle($p->getMunicipality());
            }

            if ($entity != null) {
                continue;
            }

            $slug = $this->slugifier->manipulate('gem-' . $p->getMunicipality());

            $entity = $this->municipalityManager->getRepository()->findOneBySlug($slug);

            if ($entity != null) {
                $slug = $this->slugifier->manipulate('gem-' . $p->getMunicipality() . '-' . $p->getProvinceCode());

                $entity = $this->municipalityManager->getRepository()->findOneBySlug($slug);

                if ($entity != null) {
                    continue;
                }
            }

            /**
             * @var $m Municipality
             */
            $m = $this->copyFields(new Municipality(), $p);
            $m->setTitle($p->getMunicipality());

            $m->setSlug($slug);
            $m->setSourceLocationTypeId($p->getMunicipalityId());
            $m->setProvinceCode($p->getProvinceCode());


            $m = $correction->correct($m, $p);

            $entity = $this->municipalityManager->getRepository()->findOneBySlug($m->getSlug());

            if ($entity == null) {
                $this->municipalityManager->persistAndFlush($m);
            }
        }
    }
}