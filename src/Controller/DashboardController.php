<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\BonSortie;
use App\Form\DemandeType;
use App\Form\BonSortieType;
use App\Form\VisiteType;
use App\Repository\DemandeRepository;
use App\Repository\BonSortieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
  #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        Request $request, 
        DemandeRepository $demandeRepository, 
        BonSortieRepository $bonSortieRepository,
        EntityManagerInterface $em
    ): Response {
        
        // 1. UTILISATEUR CONNECTÉ
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->redirectToRoute('app_login');
        }

        $userRole = $currentUser->getRoles()[0]; 
        $userName = $currentUser->getUserIdentifier(); 
        $userDept = 'Impression'; 

        // =========================================================================
        // 📦 2. TRAITEMENT DES FORMULAIRES
        // =========================================================================
        
        $demande = new Demande();
        $formDemande = $this->createForm(DemandeType::class, $demande);
        $formDemande->handleRequest($request);
        if ($formDemande->isSubmitted() && $formDemande->isValid()) {
            $demande->setDepartement($userDept); 
            $demande->setStatut('En attente'); 
            $em->persist($demande);
            $em->flush();
            $this->addFlash('success', '📦 Votre demande de matériel a bien été envoyée !');
            return $this->redirectToRoute('app_dashboard');
        }

        $bonSortie = new BonSortie();
        $formBon = $this->createForm(BonSortieType::class, $bonSortie);
        $formBon->handleRequest($request);
        if ($formBon->isSubmitted() && $formBon->isValid()) {
            $bonSortie->setNomEmploye($userName);
            $bonSortie->setDepartement($userDept);
            $bonSortie->setDateCreation(new \DateTime());
            $bonSortie->setStatut('Créé'); 
            $em->persist($bonSortie);
            $em->flush();
            $this->addFlash('success', '🚀 Le bon de sortie a été transmis avec succès !');
            return $this->redirectToRoute('app_dashboard');
        }

        // FORMULAIRE DE VISITE SÉCURISÉ (Pas de crash si la classe n'a pas d'entité)
        // FORMULAIRE DE VISITE SÉCURISÉ (ENREGISTREMENT RÉEL)
        $formVisiteView = null;
        if ($userRole !== 'ROLE_GUERITE' && class_exists('App\Form\VisiteType')) {
            // 1. On instancie la vraie entité Visite maintenant qu'elle existe !
            $visite = new \App\Entity\Visite(); 
            $formVisite = $this->createForm(VisiteType::class, $visite);
            $formVisite->handleRequest($request);
            
            if ($formVisite->isSubmitted() && $formVisite->isValid()) {
                // 2. On lui donne de force le statut attendu par la guérite
                $visite->setStatut('RESERVE'); 
                
                // 3. On sauvegarde en Base de Données
                $em->persist($visite);
                $em->flush();
                
                $this->addFlash('success', '🗓️ La visite a été réservée avec succès !');
                return $this->redirectToRoute('app_dashboard');
            }
            $formVisiteView = $formVisite->createView();
        }   

        // =========================================================================
        // 🔍 3. RÉCUPÉRATION DES DONNÉES (CORRIGÉE POUR L'ADMIN)
        // =========================================================================
        // =========================================================================
        // 🔍 3. RÉCUPÉRATION DES DONNÉES SÉCURISÉE
        // =========================================================================
        $demandes = [];
        $bonsSortie = [];
        $visites = [];

        // L'accueil ne doit charger AUCUNE demande de matériel ni aucun bon de sortie
        // L'accueil/réception ne charge aucune demande matérielle, uniquement les visites validées
        if ($userRole === 'ROLE_ACCUEIL' || $userRole === 'ROLE_RECEPTION') {
            $visites = $em->getRepository('App\Entity\Visite')->findBy(['statut' => 'A_L_ACCUEIL'], ['id' => 'DESC']);
        } else {
            // Logique pour les autres rôles (Stock, Compta, Coordon, Admin, Membres)
            if ($userRole === 'ROLE_STOCK' || $userRole === 'ROLE_COMPTA' || $userRole === 'ROLE_COORDON' || $userRole === 'ROLE_ADMIN') {
                $demandes = $demandeRepository->findBy([], ['id' => 'DESC']);
            } else {
                $demandes = $demandeRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
            }

            if ($userRole === 'ROLE_COORDON' || $userRole === 'ROLE_ADMIN') {
                $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Créé'], ['id' => 'DESC']);
            } elseif ($userRole === 'ROLE_GUERITE') {
                $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Validé Coordon'], ['id' => 'DESC']);
                $visites = $em->getRepository('App\Entity\Visite')->findBy(['statut' => 'RESERVE'], ['id' => 'DESC']);
            } else {
                $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
            }
        }
        // =========================================================================
        // 📊 4. LOGIQUE DES COMPTEURS
        // =========================================================================
        $stats = [
            'demandes_attente' => count($demandeRepository->findBy(['statut' => 'En attente'])),
            'bons_a_valider' => count($bonSortieRepository->findBy(['statut' => 'Créé'])),
            'bons_guerite' => count($bonSortieRepository->findBy(['statut' => 'Validé Coordon']))
        ];
        // =========================================================================

        // =========================================================================
        // 🚀 5. RENDU SELON LE RÔLE
        // =========================================================================
        // =========================================================================
      // =========================================================================
        // 🚀 5. RENDU SELON LE RÔLE
        // =========================================================================
        
        if ($userRole === 'ROLE_ADMIN') {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($userRole === 'ROLE_GUERITE') {
            return $this->render('dashboard/guerite.html.twig', [
                'bonsSortie' => $bonsSortie,
                'visites' => $visites, // On envoie les visites RESERVES à la guérite
                'userRole' => $userRole,
                'stats' => $stats
            ]);
        }

        if ($userRole === 'ROLE_ACCUEIL' || $userRole === 'ROLE_RECEPTION') {
            return $this->render('dashboard/accueil.html.twig', [
                'visites' => $visites,
                'userRole' => $userRole,
                'stats' => $stats
            ]);
        }

        if ($userRole === 'ROLE_COMPTA') {
            return $this->render('dashboard/compta.html.twig', [
                'demandes' => $demandes,
                'userRole' => $userRole,
                'stats' => $stats,
                'formVisite' => $formVisiteView
            ]);
        }

        // Pour ROLE_USER1, ROLE_USER2, ROLE_STOCK et ROLE_COORDON
        return $this->render('dashboard/membre.html.twig', [
            'demandes' => $demandes,
            'bonsSortie' => $bonsSortie,
            'userRole' => $userRole,
            'stats' => $stats,
            'formDemande' => $formDemande, // Plus de .view() ici pour éviter les conflits si null
            'formBon' => $formBon,
            'formVisite' => $formVisiteView 
        ]);
    } // Fin de la fonction index// <-- CETTE ACCOLADE MANQUAIT ICI POUR FERMER PROPREMENT LA MÉTHODE index() !

    // =========================================================================
    // 6. CIRCUITS DE VALIDATION LOGISTIQUES
    // =========================================================================

    #[Route('/dashboard/livrer/{id}', name: 'app_demande_livrer', methods: ['POST'])]
    public function livrer(Demande $demande, EntityManagerInterface $em): Response
    {
        $demande->setStatut('Livré par le Stock');
        $em->flush();
        $this->addFlash('success', '🚚 Le matériel a été marqué comme livré.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/recevoir/{id}', name: 'app_demande_recevoir', methods: ['POST'])]
    public function recevoir(Demande $demande, EntityManagerInterface $em): Response
    {
        $demande->setStatut('Reçu / Clôturé');
        $em->flush();
        $this->addFlash('success', '✅ Confirmation de réception validée.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/compta/valider/{id}', name: 'app_demande_compta_valider', methods: ['POST'])]
    public function validerCompta(Demande $demande, EntityManagerInterface $em): Response
    {
        $demande->setStatut('Validé Compta');
        $em->flush();
        $this->addFlash('success', '💰 Budget accordé avec succès !');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/bon/valider/{id}', name: 'app_bon_valider', methods: ['POST'])]
    public function validerBon(BonSortie $bonSortie, EntityManagerInterface $em): Response
    {
        $bonSortie->setStatut('Validé Coordon');
        $em->flush();
        $this->addFlash('success', '✍️ Bon de sortie signé avec succès !');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/bon/autoriser/{id}', name: 'app_bon_autoriser', methods: ['POST'])]
    public function autoriserSortie(BonSortie $bonSortie, EntityManagerInterface $em): Response
    {
        $bonSortie->setStatut('Sortie Validée');
        $em->flush();
        $this->addFlash('success', '🚪 Sortie physique autorisée.');
        return $this->redirectToRoute('app_dashboard');
    }
    #[Route('/dashboard/visite/valider/{id}', name: 'app_visite_valider_guerite', methods: ['POST'])]
    public function validerEntreeGuerite(\App\Entity\Visite $visite, EntityManagerInterface $em): Response
    {
        // La guérite valide le badge physique et envoie le visiteur à l'accueil
        $visite->setStatut('A_L_ACCUEIL');
        $em->flush();

        $this->addFlash('success', '🪪 Entrée du visiteur validée ! Transmis à l\'accueil.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/visite/cloturer/{id}', name: 'app_visite_cloturer', methods: ['POST'])]
    public function cloturerVisite(\App\Entity\Visite $visite, EntityManagerInterface $em): Response
    {
        $visite->setStatut('TERMINE');
        $em->flush();

        $this->addFlash('success', '✅ Visite clôturée. Le départ du visiteur a été enregistré.');
        return $this->redirectToRoute('app_dashboard');
    }
} // <-- Tout dernier crochet qui ferme la classe DashboardController
