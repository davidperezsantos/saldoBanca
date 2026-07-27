<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Loggable\Entity\Repository\LogEntryRepository;
use Gedmo\Loggable\LogEntryInterface;

/**
 * Tabla de auditoría de Gedmo Loggable (ext_log_entries) — un registro por cada create/update/
 * delete de una entidad marcada #[Gedmo\Mapping\Annotation\Loggable]. Implementa
 * LogEntryInterface directamente (en vez de extender Gedmo\Loggable\Entity\MappedSuperclass\
 * AbstractLogEntry) porque esa clase mapea `data` como type "array", un tipo de Doctrine DBAL
 * eliminado en la versión 4 que usa este proyecto (Doctrine no permite cambiar el tipo de una
 * columna heredada vía AttributeOverride, solo nombre/longitud/nullable) — aquí se mapea
 * directamente como "json".
 */
#[ORM\Entity(repositoryClass: LogEntryRepository::class)]
#[ORM\Table(name: 'ext_log_entries')]
#[ORM\Index(name: 'log_class_lookup_idx', columns: ['object_class'])]
#[ORM\Index(name: 'log_date_lookup_idx', columns: ['logged_at'])]
#[ORM\Index(name: 'log_user_lookup_idx', columns: ['username'])]
#[ORM\Index(name: 'log_version_lookup_idx', columns: ['object_id', 'object_class', 'version'])]
class LogEntry implements LogEntryInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 8)]
    private ?string $action = null;

    #[ORM\Column(name: 'logged_at', type: 'datetime')]
    private ?\DateTime $loggedAt = null;

    #[ORM\Column(name: 'object_id', length: 64, nullable: true)]
    private ?string $objectId = null;

    #[ORM\Column(name: 'object_class', type: 'string', length: 191)]
    private ?string $objectClass = null;

    #[ORM\Column(type: 'integer')]
    private ?int $version = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = null;

    #[ORM\Column(length: 191, nullable: true)]
    private ?string $username = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getObjectClass(): ?string
    {
        return $this->objectClass;
    }

    public function setObjectClass(string $objectClass): void
    {
        $this->objectClass = $objectClass;
    }

    public function getObjectId(): ?string
    {
        return $this->objectId;
    }

    public function setObjectId(string $objectId): void
    {
        $this->objectId = $objectId;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getLoggedAt(): ?\DateTimeInterface
    {
        return $this->loggedAt;
    }

    public function setLoggedAt(): void
    {
        $this->loggedAt = new \DateTime();
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function setVersion(int $version): void
    {
        $this->version = $version;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }
}
