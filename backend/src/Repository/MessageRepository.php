<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /** @return Message[] tous les messages envoyés ou reçus par l'utilisateur (récents d'abord) */
    public function deUtilisateur(User $u): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.expediteur = :u OR m.destinataire = :u')
            ->setParameter('u', $u)
            ->orderBy('m.dateEnvoi', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->getQuery()->getResult();
    }

    /** @return Message[] fil de discussion entre deux utilisateurs */
    public function conversation(User $a, User $b): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('(m.expediteur = :a AND m.destinataire = :b) OR (m.expediteur = :b AND m.destinataire = :a)')
            ->setParameter('a', $a)
            ->setParameter('b', $b)
            ->orderBy('m.dateEnvoi', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()->getResult();
    }
}
