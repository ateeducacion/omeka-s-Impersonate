<?php

declare(strict_types=1);

namespace Doctrine\ORM;

interface EntityManagerInterface
{
    /**
     * @param string $className
     * @param mixed $id
     * @return mixed
     */
    public function find($className, $id);

    /**
     * @param string $className
     * @return object
     */
    public function getRepository($className);
}
