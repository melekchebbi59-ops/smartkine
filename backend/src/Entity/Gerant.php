<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'gerant')]
class Gerant extends User
{
    public function getMainRole(): string
    {
        return 'ROLE_GERANT';
    }

    public function getType(): string
    {
        return 'gerant';
    }
}
