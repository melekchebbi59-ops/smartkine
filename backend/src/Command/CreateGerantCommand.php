<?php

namespace App\Command;

use App\Entity\Gerant;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée le premier compte gérant (personne ne peut le créer depuis l'interface).
 * Exemple : php bin/console app:create-gerant gerant@smartkine.tn Gerant123 Kamel Brigui
 */
#[AsCommand(name: 'app:create-gerant', description: 'Crée un compte gérant')]
class CreateGerantCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED)
            ->addArgument('password', InputArgument::REQUIRED)
            ->addArgument('firstName', InputArgument::OPTIONAL, '', 'Gérant')
            ->addArgument('lastName', InputArgument::OPTIONAL, '', 'SmartKiné');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = strtolower($input->getArgument('email'));

        if ($this->users->findOneBy(['email' => $email])) {
            $io->error('Cet e-mail existe déjà.');
            return Command::FAILURE;
        }

        $gerant = new Gerant();
        $gerant->setEmail($email)
            ->setFirstName($input->getArgument('firstName'))
            ->setLastName($input->getArgument('lastName'));
        $gerant->setPassword($this->hasher->hashPassword($gerant, $input->getArgument('password')));

        $this->em->persist($gerant);
        $this->em->flush();

        $io->success("Gérant créé : $email");
        return Command::SUCCESS;
    }
}
