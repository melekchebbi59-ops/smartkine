<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 1 — Modèle de données complet (diagramme de classes) :
 * héritage Utilisateur → Gérant / Kiné / Patient, demandes de RDV, dossiers médicaux,
 * exercices, plans de rééducation, séances, factures, paiements, messages, notifications.
 * Les utilisateurs déjà inscrits (table user) deviennent des patients validés.
 */
final class Version20261125100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'SmartKiné : schéma complet du diagramme de classes (12 tables + notifications)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE `user` ADD active TINYINT DEFAULT 1 NOT NULL, ADD created_at DATETIME DEFAULT NULL, ADD type VARCHAR(20) DEFAULT 'patient' NOT NULL");
        $this->addSql("UPDATE `user` SET created_at = NOW() WHERE created_at IS NULL");
        $this->addSql("ALTER TABLE `user` MODIFY created_at DATETIME NOT NULL, MODIFY type VARCHAR(20) NOT NULL");
        $this->addSql("CREATE TABLE demande_rdv (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, telephone VARCHAR(20) NOT NULL, email VARCHAR(180) NOT NULL, date_souhaitee DATETIME NOT NULL, motif LONGTEXT DEFAULT NULL, statut VARCHAR(20) NOT NULL, date_rdv_confirmee DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, gerant_id INT DEFAULT NULL, INDEX IDX_68FB061DA500A924 (gerant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE dossier_medical (id INT AUTO_INCREMENT NOT NULL, description_etat LONGTEXT DEFAULT NULL, tension_arterielle VARCHAR(20) DEFAULT NULL, poids NUMERIC(5, 1) DEFAULT NULL, glycemie NUMERIC(4, 2) DEFAULT NULL, autres_maladies LONGTEXT DEFAULT NULL, updated_at DATETIME NOT NULL, patient_id INT NOT NULL, UNIQUE INDEX UNIQ_3581EE626B899279 (patient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE facture (id INT AUTO_INCREMENT NOT NULL, numero VARCHAR(30) NOT NULL, date_emission DATETIME NOT NULL, montant_total NUMERIC(10, 2) NOT NULL, reste_apayer NUMERIC(10, 2) NOT NULL, patient_id INT NOT NULL, UNIQUE INDEX UNIQ_FE866410F55AE19E (numero), INDEX IDX_FE8664106B899279 (patient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE patient (date_naissance DATE DEFAULT NULL, adresse VARCHAR(255) DEFAULT NULL, qualification VARCHAR(120) DEFAULT NULL, medecin_traitant VARCHAR(150) DEFAULT NULL, type_traitement VARCHAR(150) DEFAULT NULL, statut_inscription VARCHAR(20) NOT NULL, kine_id INT DEFAULT NULL, id INT NOT NULL, INDEX IDX_1ADAD7EB88DC4BCC (kine_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE notification (id INT AUTO_INCREMENT NOT NULL, texte VARCHAR(255) NOT NULL, lue TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, destinataire_id INT NOT NULL, INDEX IDX_BF5476CAA4F84F6E (destinataire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE plan_reeducation (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, objectifs LONGTEXT DEFAULT NULL, date_creation DATETIME NOT NULL, patient_id INT NOT NULL, kine_id INT DEFAULT NULL, INDEX IDX_33BD07FD6B899279 (patient_id), INDEX IDX_33BD07FD88DC4BCC (kine_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE plan_exercice (plan_reeducation_id INT NOT NULL, exercice_id INT NOT NULL, INDEX IDX_EA7DF03721CC7C76 (plan_reeducation_id), INDEX IDX_EA7DF03789D40298 (exercice_id), PRIMARY KEY (plan_reeducation_id, exercice_id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, contenu LONGTEXT NOT NULL, date_envoi DATETIME NOT NULL, lu TINYINT DEFAULT 0 NOT NULL, expediteur_id INT NOT NULL, destinataire_id INT NOT NULL, INDEX IDX_B6BD307F10335F61 (expediteur_id), INDEX IDX_B6BD307FA4F84F6E (destinataire_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE exercice (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(150) NOT NULL, duree INT NOT NULL, cout NUMERIC(10, 2) NOT NULL, objectif LONGTEXT DEFAULT NULL, image VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE kine (specialite VARCHAR(120) DEFAULT NULL, id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE seance (id INT AUTO_INCREMENT NOT NULL, date_heure DATETIME NOT NULL, duree INT DEFAULT 45 NOT NULL, objectif LONGTEXT DEFAULT NULL, statut VARCHAR(30) NOT NULL, date_proposee DATETIME DEFAULT NULL, motif_demande LONGTEXT DEFAULT NULL, patient_id INT NOT NULL, kine_id INT DEFAULT NULL, plan_id INT DEFAULT NULL, facture_id INT DEFAULT NULL, INDEX IDX_DF7DFD0E6B899279 (patient_id), INDEX IDX_DF7DFD0E88DC4BCC (kine_id), INDEX IDX_DF7DFD0EE899029B (plan_id), INDEX IDX_DF7DFD0E7F2DEE08 (facture_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE seance_exercice (seance_id INT NOT NULL, exercice_id INT NOT NULL, INDEX IDX_8A34735E3797A94 (seance_id), INDEX IDX_8A3473589D40298 (exercice_id), PRIMARY KEY (seance_id, exercice_id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE paiement (id INT AUTO_INCREMENT NOT NULL, montant NUMERIC(10, 2) NOT NULL, date_paiement DATETIME NOT NULL, mode VARCHAR(20) NOT NULL, facture_id INT NOT NULL, INDEX IDX_B1DC7A1E7F2DEE08 (facture_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("CREATE TABLE gerant (id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4");
        $this->addSql("INSERT INTO patient (id, statut_inscription) SELECT id, 'valide' FROM `user` WHERE type = 'patient'");
        $this->addSql("ALTER TABLE demande_rdv ADD CONSTRAINT FK_68FB061DA500A924 FOREIGN KEY (gerant_id) REFERENCES gerant (id) ON DELETE SET NULL");
        $this->addSql("ALTER TABLE dossier_medical ADD CONSTRAINT FK_3581EE626B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE facture ADD CONSTRAINT FK_FE8664106B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE patient ADD CONSTRAINT FK_1ADAD7EBBF396750 FOREIGN KEY (id) REFERENCES `user` (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE patient ADD CONSTRAINT FK_1ADAD7EB88DC4BCC FOREIGN KEY (kine_id) REFERENCES kine (id) ON DELETE SET NULL");
        $this->addSql("ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAA4F84F6E FOREIGN KEY (destinataire_id) REFERENCES `user` (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE plan_reeducation ADD CONSTRAINT FK_33BD07FD6B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE plan_reeducation ADD CONSTRAINT FK_33BD07FD88DC4BCC FOREIGN KEY (kine_id) REFERENCES kine (id) ON DELETE SET NULL");
        $this->addSql("ALTER TABLE plan_exercice ADD CONSTRAINT FK_EA7DF03721CC7C76 FOREIGN KEY (plan_reeducation_id) REFERENCES plan_reeducation (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE plan_exercice ADD CONSTRAINT FK_EA7DF03789D40298 FOREIGN KEY (exercice_id) REFERENCES exercice (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE message ADD CONSTRAINT FK_B6BD307F10335F61 FOREIGN KEY (expediteur_id) REFERENCES `user` (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE message ADD CONSTRAINT FK_B6BD307FA4F84F6E FOREIGN KEY (destinataire_id) REFERENCES `user` (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE kine ADD CONSTRAINT FK_4CC38C4FBF396750 FOREIGN KEY (id) REFERENCES `user` (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0E6B899279 FOREIGN KEY (patient_id) REFERENCES patient (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0E88DC4BCC FOREIGN KEY (kine_id) REFERENCES kine (id) ON DELETE SET NULL");
        $this->addSql("ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0EE899029B FOREIGN KEY (plan_id) REFERENCES plan_reeducation (id) ON DELETE SET NULL");
        $this->addSql("ALTER TABLE seance ADD CONSTRAINT FK_DF7DFD0E7F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id) ON DELETE SET NULL");
        $this->addSql("ALTER TABLE seance_exercice ADD CONSTRAINT FK_8A34735E3797A94 FOREIGN KEY (seance_id) REFERENCES seance (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE seance_exercice ADD CONSTRAINT FK_8A3473589D40298 FOREIGN KEY (exercice_id) REFERENCES exercice (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE paiement ADD CONSTRAINT FK_B1DC7A1E7F2DEE08 FOREIGN KEY (facture_id) REFERENCES facture (id) ON DELETE CASCADE");
        $this->addSql("ALTER TABLE gerant ADD CONSTRAINT FK_D1D45C70BF396750 FOREIGN KEY (id) REFERENCES `user` (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE demande_rdv DROP FOREIGN KEY FK_68FB061DA500A924");
        $this->addSql("ALTER TABLE dossier_medical DROP FOREIGN KEY FK_3581EE626B899279");
        $this->addSql("ALTER TABLE facture DROP FOREIGN KEY FK_FE8664106B899279");
        $this->addSql("ALTER TABLE patient DROP FOREIGN KEY FK_1ADAD7EBBF396750");
        $this->addSql("ALTER TABLE patient DROP FOREIGN KEY FK_1ADAD7EB88DC4BCC");
        $this->addSql("ALTER TABLE notification DROP FOREIGN KEY FK_BF5476CAA4F84F6E");
        $this->addSql("ALTER TABLE plan_reeducation DROP FOREIGN KEY FK_33BD07FD6B899279");
        $this->addSql("ALTER TABLE plan_reeducation DROP FOREIGN KEY FK_33BD07FD88DC4BCC");
        $this->addSql("ALTER TABLE plan_exercice DROP FOREIGN KEY FK_EA7DF03721CC7C76");
        $this->addSql("ALTER TABLE plan_exercice DROP FOREIGN KEY FK_EA7DF03789D40298");
        $this->addSql("ALTER TABLE message DROP FOREIGN KEY FK_B6BD307F10335F61");
        $this->addSql("ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FA4F84F6E");
        $this->addSql("ALTER TABLE kine DROP FOREIGN KEY FK_4CC38C4FBF396750");
        $this->addSql("ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0E6B899279");
        $this->addSql("ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0E88DC4BCC");
        $this->addSql("ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0EE899029B");
        $this->addSql("ALTER TABLE seance DROP FOREIGN KEY FK_DF7DFD0E7F2DEE08");
        $this->addSql("ALTER TABLE seance_exercice DROP FOREIGN KEY FK_8A34735E3797A94");
        $this->addSql("ALTER TABLE seance_exercice DROP FOREIGN KEY FK_8A3473589D40298");
        $this->addSql("ALTER TABLE paiement DROP FOREIGN KEY FK_B1DC7A1E7F2DEE08");
        $this->addSql("ALTER TABLE gerant DROP FOREIGN KEY FK_D1D45C70BF396750");
        $this->addSql("DROP TABLE demande_rdv");
        $this->addSql("DROP TABLE dossier_medical");
        $this->addSql("DROP TABLE facture");
        $this->addSql("DROP TABLE patient");
        $this->addSql("DROP TABLE notification");
        $this->addSql("DROP TABLE plan_reeducation");
        $this->addSql("DROP TABLE plan_exercice");
        $this->addSql("DROP TABLE message");
        $this->addSql("DROP TABLE exercice");
        $this->addSql("DROP TABLE kine");
        $this->addSql("DROP TABLE seance");
        $this->addSql("DROP TABLE seance_exercice");
        $this->addSql("DROP TABLE paiement");
        $this->addSql("DROP TABLE gerant");
        $this->addSql("ALTER TABLE `user` DROP active, DROP created_at, DROP type");
    }
}
