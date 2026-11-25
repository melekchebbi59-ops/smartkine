export type UserType = 'gerant' | 'kine' | 'patient';

export interface UserLight {
  id: number;
  type: UserType;
  firstName: string;
  lastName: string;
  fullName: string;
}

export interface User extends UserLight {
  email: string;
  phone: string | null;
  active: boolean;
  roles: string[];
  createdAt: string;
  specialite?: string | null;
  nbPatients?: number;
  dateNaissance?: string | null;
  adresse?: string | null;
  qualification?: string | null;
  medecinTraitant?: string | null;
  typeTraitement?: string | null;
  statutInscription?: 'en_attente' | 'valide' | 'refuse';
  kine?: UserLight | null;
}

export interface Centre {
  nom: string;
  adresse: string;
  telephone: string;
  email: string;
  horaires: { jours: string; heures: string }[];
  services: { titre: string; description: string }[];
  latitude: number;
  longitude: number;
}

export interface DemandeRdv {
  id: number;
  nom: string;
  prenom: string;
  telephone: string;
  email: string;
  dateSouhaitee: string;
  motif: string | null;
  statut: 'en_attente' | 'acceptee' | 'refusee';
  dateRdvConfirmee: string | null;
  createdAt: string;
  gerant: UserLight | null;
}

export interface Exercice {
  id: number;
  nom: string;
  duree: number;
  cout: string;
  objectif: string | null;
  image: string | null;
}

export type StatutSeance = 'planifiee' | 'effectuee' | 'annulee' | 'report_demande' | 'annulation_demandee';

export interface Seance {
  id: number;
  dateHeure: string;
  duree: number;
  objectif: string | null;
  statut: StatutSeance;
  dateProposee: string | null;
  motifDemande: string | null;
  patient: UserLight;
  kine: UserLight | null;
  plan: { id: number; titre: string } | null;
  exercices: Exercice[];
  prix: string;
  facturee: boolean;
}

export interface DossierMedical {
  id: number;
  descriptionEtat: string | null;
  tensionArterielle: string | null;
  poids: string | null;
  glycemie: string | null;
  autresMaladies: string | null;
  updatedAt: string;
}

export interface Plan {
  id: number;
  titre: string;
  objectifs: string | null;
  dateCreation: string;
  patient: UserLight;
  kine: UserLight | null;
  exercices: Exercice[];
  seances?: Seance[];
}

export interface Paiement {
  id: number;
  montant: string;
  datePaiement: string;
  mode: string;
}

export interface Facture {
  id: number;
  numero: string;
  dateEmission: string;
  montantTotal: string;
  montantPaye: string;
  resteAPayer: string;
  statut: 'payee' | 'partielle' | 'impayee';
  patient: UserLight;
  seances: { id: number; dateHeure: string; statut: string; prix: string }[];
  paiements: Paiement[];
}

export interface Message {
  id: number;
  contenu: string;
  dateEnvoi: string;
  lu: boolean;
  expediteur: UserLight;
  destinataire: UserLight;
}

export interface Conversation {
  contact: UserLight;
  dernierMessage: Message;
  nonLus: number;
}

export interface Notification {
  id: number;
  texte: string;
  lue: boolean;
  createdAt: string;
}

export interface ApiMessage {
  message: string;
}
