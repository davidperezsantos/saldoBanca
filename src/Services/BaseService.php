<?php

namespace App\Services;

use Doctrine\ORM\EntityManagerInterface;

abstract class BaseService
{
    public function __construct(
        protected EntityManagerInterface $entityManager
    ) {
    }

    protected function flush(): void
    {
        $this->entityManager->flush();
    }

    protected function persist(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    protected function remove(object $entity): void
    {
        $this->entityManager->remove($entity);
    }

    protected function getRepository(string $entityClass): \Doctrine\Persistence\ObjectRepository
    {
        return $this->entityManager->getRepository($entityClass);
    }
}
