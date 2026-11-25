<?php

namespace App\Command;

use App\Entity\DemandeRdv;
use App\Entity\DossierMedical;
use App\Entity\Exercice;
use App\Entity\Facture;
use App\Entity\Gerant;
use App\Entity\Kine;
use App\Entity\Message;
use App\Entity\Paiement;
use App\Entity\Patient;
use App\Entity\PlanReeducation;
use App\Entity\Seance;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Remplit la base avec des données de démonstration (pour la recette / la soutenance).
 * php bin/console app:demo
 * Mots de passe : Gerant123 / Kine12345 / Patient123
 */
#[AsCommand(name: 'app:demo', description: 'Charge des données de démonstration SmartKiné')]
class DemoDataCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->users->findOneBy(['email' => 'gerant@smartkine.tn'])) {
            $io->warning('Les données de démo existent déjà (gerant@smartkine.tn).');
            return Command::SUCCESS;
        }

        $gerant = $this->user(new Gerant(), 'gerant@smartkine.tn', 'Gerant123', 'Kamel', 'Brigui', '73 000 001');
        $k1 = $this->user((new Kine())->setSpecialite('Kinésithérapie du sport'), 'melek@smartkine.tn', 'Kine12345', 'Melek', 'Chabbi', '98 111 222');
        $k2 = $this->user((new Kine())->setSpecialite('Rééducation neurologique'), 'fadwa@smartkine.tn', 'Kine12345', 'Fadwa', 'Ezzar', '98 333 444');

        $exos = [];
        foreach ([
            ['Renforcement du quadriceps', 15, '20', 'Contractions isométriques genou tendu, 3 x 10.'],
            ['Proprioception sur plateau', 10, '15', "Équilibre unipodal sur plateau instable, 3 x 30 s."],
            ['Étirement des ischio-jambiers', 10, '10', 'Étirement passif, 3 x 30 s de chaque côté.'],
            ['Mobilisation de la cheville', 10, '15', 'Flexion / extension active, 2 x 15.'],
            ['Gainage abdominal', 10, '10', 'Planche ventrale 3 x 30 s.'],
            ['Massage décontracturant', 20, '30', 'Massage manuel de la zone douloureuse.'],
            ['Électrothérapie antalgique', 15, '25', 'TENS sur la zone douloureuse.'],
            ['Marche avec obstacles', 15, '15', "Parcours d'équilibre et coordination."],
        ] as [$nom, $duree, $cout, $obj]) {
            $e = (new Exercice())->setNom($nom)->setDuree($duree)->setCout($cout)->setObjectif($obj);
            $this->em->persist($e);
            $exos[] = $e;
        }

        $patients = [];
        foreach ([
            ['sami@test.tn', 'Sami', 'Ben Ali', 'Entorse de la cheville', $k1],
            ['ines@test.tn', 'Ines', 'Trabelsi', 'Lombalgie chronique', $k1],
            ['youssef@test.tn', 'Youssef', 'Gharbi', 'Rééducation post-AVC', $k2],
            ['amira@test.tn', 'Amira', 'Mansour', 'Ligamentoplastie du genou', null],
        ] as $i => [$email, $prenom, $nom, $patho, $kine]) {
            $p = (new Patient())->setStatutInscription(Patient::STATUT_VALIDE)->setTypeTraitement($patho)->setKine($kine)
                ->setDateNaissance(new \DateTimeImmutable((1980 + $i * 6) . '-0' . ($i + 3) . '-15'))
                ->setAdresse('Sousse')->setMedecinTraitant('Dr. Haddad');
            $this->user($p, $email, 'Patient123', $prenom, $nom, '22 00' . $i . ' 00' . $i);
            $patients[] = $p;

            $d = (new DemandeRdv())->setNom($nom)->setPrenom($prenom)->setEmail($email)->setTelephone('22 000 000')
                ->setDateSouhaitee(new \DateTimeImmutable('-20 days 10:00'))->setMotif($patho)
                ->setStatut(DemandeRdv::STATUT_ACCEPTEE)->setDateRdvConfirmee(new \DateTimeImmutable('-20 days 10:00'))->setGerant($gerant);
            $this->em->persist($d);
        }

        // Une demande et une inscription en attente, pour la démo du gérant
        $this->em->persist((new DemandeRdv())->setNom('Jaziri')->setPrenom('Rania')->setEmail('rania@test.tn')->setTelephone('55 123 456')
            ->setDateSouhaitee(new \DateTimeImmutable('+2 days 09:30'))->setMotif('Tendinite de l\'épaule'));
        $this->user((new Patient())->setTypeTraitement('Cervicalgie'), 'hedi@test.tn', 'Patient123', 'Hedi', 'Saidi', '50 765 432');

        // Dossiers, plans, séances
        $seancesPassees = [];
        $lundi = new \DateTimeImmutable('monday this week');
        foreach ([$patients[0], $patients[1], $patients[2]] as $i => $p) {
            $dossier = (new DossierMedical())->setDescriptionEtat('Douleur ' . ($i + 4) . '/10 à la mobilisation.')
                ->setTensionArterielle('12/8')->setPoids((string) (65 + $i * 7))->setGlycemie('0.95')->setAutresMaladies($i === 2 ? 'Hypertension' : null);
            $p->setDossierMedical($dossier);
            $this->em->persist($dossier);

            $plan = (new PlanReeducation())->setTitre('Plan — ' . $p->getTypeTraitement())->setObjectifs('Réduire la douleur et retrouver la mobilité.')
                ->setPatient($p)->setKine($p->getKine())->replaceExercices(array_slice($exos, $i * 2, 3));
            $this->em->persist($plan);

            for ($j = -3; $j < 5; $j++) {
                $date = $lundi->modify(($j * 2 + $i) . ' days')->setTime(9 + $i * 2, 0);
                $s = (new Seance())->setPatient($p)->setKine($p->getKine())->setPlan($plan)->setDateHeure($date)
                    ->setObjectif('Séance ' . ($j + 4))->replaceExercices($plan->getExercices()->toArray())
                    ->setStatut($date < new \DateTimeImmutable() ? Seance::STATUT_EFFECTUEE : Seance::STATUT_PLANIFIEE);
                $this->em->persist($s);
                if ($j < 0) {
                    $seancesPassees[$i][] = $s;
                }
            }
        }

        // Une demande de report en attente
        $report = (new Seance())->setPatient($patients[0])->setKine($k1)->setDateHeure(new \DateTimeImmutable('+3 days 16:00'))
            ->setObjectif('Renforcement')->setStatut(Seance::STATUT_REPORT_DEMANDE)
            ->setDateProposee(new \DateTimeImmutable('+4 days 17:00'))->setMotifDemande('Examen à la fac');
        $this->em->persist($report);

        // Factures + paiements partiels
        foreach ($seancesPassees as $i => $liste) {
            $f = (new Facture())->setPatient($patients[$i])->setNumero(sprintf('FAC-%s-%04d', date('Y'), $i + 1));
            $total = 0;
            foreach ($liste as $s) {
                $f->addSeance($s);
                $total += (float) $s->getPrix();
            }
            $f->setMontantTotal(number_format($total, 2, '.', ''));
            if ($i < 2) {
                $p = (new Paiement())->setMontant(number_format($total / ($i + 1), 2, '.', ''))->setMode('especes')
                    ->setDatePaiement(new \DateTimeImmutable('-' . ($i * 9 + 1) . ' days'));
                $f->addPaiement($p);
                $this->em->persist($p);
            }
            $f->recalculerReste();
            $this->em->persist($f);
        }

        // Messages
        $this->em->persist((new Message())->setExpediteur($patients[0])->setDestinataire($k1)->setContenu('Bonjour, dois-je refaire les exercices à la maison ?'));
        $this->em->persist((new Message())->setExpediteur($k1)->setDestinataire($patients[0])->setContenu('Oui, 2 fois par jour, sans forcer sur la douleur.'));

        $this->em->flush();

        $io->success('Données de démo chargées.');
        $io->table(['Rôle', 'E-mail', 'Mot de passe'], [
            ['Gérant', 'gerant@smartkine.tn', 'Gerant123'],
            ['Kiné', 'melek@smartkine.tn', 'Kine12345'],
            ['Kiné', 'fadwa@smartkine.tn', 'Kine12345'],
            ['Patient', 'sami@test.tn', 'Patient123'],
        ]);

        return Command::SUCCESS;
    }

    private function user($u, string $email, string $pwd, string $prenom, string $nom, string $tel)
    {
        $u->setEmail($email)->setFirstName($prenom)->setLastName($nom)->setPhone($tel);
        $u->setPassword($this->hasher->hashPassword($u, $pwd));
        $this->em->persist($u);
        return $u;
    }
}
