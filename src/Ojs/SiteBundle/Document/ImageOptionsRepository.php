<?php

namespace Ojs\SiteBundle\Document;

use Doctrine\ODM\MongoDB\DocumentRepository;

/**
 * ImageOptionsRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ImageOptionsRepository extends DocumentRepository
{
    public function init($data, $entity, $type)
    {
        $option = $this->findOneBy(
            [
                'image_type' => $type,
                'object' => get_class($entity),
                'object_id' => call_user_func([$entity, 'getId']),
            ]
        );
        if (!$option) {
            $option = new ImageOptions();
            $option
                ->setImageType($type)
                ->setObject(get_class($entity))
                ->setObjectId(call_user_func([$entity, 'getId']));
        }
        $option
            ->setHeight($data['height'])
            ->setWidth($data['width'])
            ->setX($data['x'])
            ->setY($data['y']);

        return $option;
    }
}
