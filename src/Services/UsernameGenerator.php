<?php

namespace App\Services;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class UsernameGenerator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SluggerInterface $slugger
    ) {}

    public function generate(string $fullName): string
    {
        // 1. Limpiar el nombre completo: quitar tildes y convertir a minúsculas
        $cleanFullName = $this->slugger->slug($fullName, ' ')->lower()->toString();
        $parts = array_filter(explode(' ', $cleanFullName)); // ['david', 'perez', 'santos']

        if (empty($parts)) {
            throw new \InvalidArgumentException('El nombre no contiene partes válidas.');
        }

        $attempts = $this->buildUsernameAttempts($parts);

        foreach ($attempts as $username) {
            if (!$this->usernameExists($username)) {
                return $username;
            }
        }

        // Si todas las combinaciones existen, usar la versión completa + sufijo numérico
        $base = $this->buildBaseUsername($parts);
        return $this->appendNumericSuffix($base);
    }

    private function buildUsernameAttempts(array $parts): array
    {
        $attempts = [];
        $first = $parts[0] ?? '';

        // 1. Solo nombre
        $attempts[] = $first;

        if (count($parts) >= 2) {
            $secondInitial = substr($parts[1], 0, 1);
            $second = $parts[1] ?? '';

            // 2. nombre.inicial
            $attempts[] = "$first.$secondInitial";

            // 3. nombre.apellido
            $attempts[] = "$first.$second";

            if (count($parts) >= 3) {
                $thirdInitial = substr($parts[2], 0, 1);
                $third = $parts[2] ?? '';

                // 4. nombreapellido.inicial
                $attempts[] = $first . $second . ".$thirdInitial";

                // 5. nombreapellido.apellido
                $attempts[] = $first . $second . ".$third";
            }
        }

        // 6. nombreapellido... (todo junto)
        $attempts[] = $this->buildBaseUsername($parts);

        return $attempts;
    }

    private function buildBaseUsername(array $parts): string
    {
        return implode('', $parts);
    }

    private function appendNumericSuffix(string $base): string
    {
        $counter = 1;
        $username = $base;

        while ($this->usernameExists($username)) {
            $username = $base . $counter;
            $counter++;
        }

        return $username;
    }

    private function usernameExists(string $username): bool
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['username' => $username]) !== null;
    }
}
