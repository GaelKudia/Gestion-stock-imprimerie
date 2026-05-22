<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\BonSortie;
use App\Form\DemandeType;
use App\Form\BonSortieType;
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
        
        // 1. RÉCUPÉRATION DE L'UTILISATEUR CONNECTÉ (FINI LA SIMULATION)
        $currentUser = $this->getUser();
        
        // Sécurité : Si personne n'est connecté en session, redirection forcée au login
        if (!$currentUser) {
            return $this->redirectToRoute('app_login');
        }

        // Extraction dynamique du rôle et de l'identifiant réel
        $userRole = $currentUser->getRoles()[0]; 
        $userName = $currentUser->getUserIdentifier(); 
        
        // Département par défaut (Modifiable selon tes besoins futurs)
        $userDept = 'Impression'; 

        $demandes = [];
        $bonsSortie = [];

        // 2. RÉCUPÉRATION DES DONNÉES SELON LES RÔLES
        if ($userRole === 'ROLE_STOCK' || $userRole === 'ROLE_COMPTA' || $userRole === 'ROLE_COORDON') {
            $demandes = $demandeRepository->findBy([], ['id' => 'DESC']);
        } else {
            $demandes = $demandeRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
        }

        if ($userRole === 'ROLE_COORDON') {
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Créé'], ['id' => 'DESC']);
        } elseif ($userRole === 'ROLE_GUERITE') {
            $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Validé Coordon'], ['id' => 'DESC']);
        } else {
            $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
        }

        // =========================================================================
        // 📊 3. LOGIQUE DU CADRE DE BLOCS EN COULEUR (STATISTIQUES)
        // =========================================================================
        $countDemandesAttente = count($demandeRepository->findBy(['statut' => 'En attente']));
        $countBonsAValider = count($bonSortieRepository->findBy(['statut' => 'Créé']));
        $countBonsGuerite = count($bonSortieRepository->findBy(['statut' => 'Validé Coordon']));

        // =========================================================================
        // 📦 4. TRAITEMENT DU FORMULAIRE A : DEMANDE DE MATÉRIEL
        // =========================================================================
        $demande = new Demande();
        $formDemande = $this->createForm(DemandeType::class, $demande);
        $formDemande->handleRequest($request);

        if ($formDemande->isSubmitted() && $formDemande->isValid()) {
            $demande->setDepartement($userDept); 
            $demande->setStatut('En attente'); 
            
            $em->persist($demande);
            $em->flush();
            
            $this->addFlash('success', '📦 Votre demande de matériel a bien été envoyée au Gérant de Stock !');
            return $this->redirectToRoute('app_dashboard');
        }

        // =========================================================================
        // 🚀 5. TRAITEMENT DU FORMULAIRE B : BON DE SÉCURITÉ / SORTIE
        // =========================================================================
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
            
            $this->addFlash('success', '🚀 Le bon de sortie a été transmis au Coordonnateur avec succès !');
            return $this->redirectToRoute('app_dashboard');
        }

        // =========================================================================
        // 6. AIGUILLAGE ET ENVOI DES VARIABLES À TWIG SELON LE RÔLE
        // =========================================================================
        // =========================================================================
        // 6. AIGUILLAGE ET ENVOI DES VARIABLES À TWIG SELON LE RÔLE
        // =========================================================================
        
        // Cas ADMIN : Redirection automatique vers son espace de contrôle
        if ($userRole === 'ROLE_ADMIN') {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Cas A : La Guérite... (le reste de ton code ne bouge pas)
        // Cas A : La Guérite
        if ($userRole === 'ROLE_GUERITE') {
            return $this->render('dashboard/guerite.html.twig', [
                'bonsSortie' => $bonsSortie,
                'userRole' => $userRole,
                'stats' => [
                    'demandes_attente' => $countDemandesAttente,
                    'bons_a_valider' => $countBonsAValider,
                    'bons_guerite' => $countBonsGuerite
                ]
            ]);
        }

        // Cas B : La Comptabilité (Redirige vers son propre template)
        if ($userRole === 'ROLE_COMPTA') {
            return $this->render('dashboard/compta.html.twig', [
                'demandes' => $demandes,
                'userRole' => $userRole,
                'stats' => [
                    'demandes_attente' => $countDemandesAttente,
                    'bons_a_valider' => $countBonsAValider,
                    'bons_guerite' => $countBonsGuerite
                ]
            ]);
        }

        // Cas C : Coordonnateur, Stock, Utilisateur standard
        return $this->render('dashboard/membre.html.twig', [
            'demandes' => $demandes,
            'bonsSortie' => $bonsSortie,
            'formDemande' => $formDemande->createView(),
            'formBon' => $formBon->createView(),
            'userRole' => $userRole,
            'stats' => [
                'demandes_attente' => $countDemandesAttente,
                'bons_a_valider' => $countBonsAValider,
                'bons_guerite' => $countBonsGuerite
            ]
        ]);
    }

    // =========================================================================
    // 7. LES ROUTES DE VALIDATION (CIRCUITS LOGISTIQUES ET SÉCURITÉ)
    // =========================================================================

    #[Route('/dashboard/livrer/{id}', name: 'app_demande_livrer', methods: ['POST'])]
    public function livrer(Demande $demande, EntityManagerInterface $em): Response
    {
        $demande->setStatut('Livré par le Stock');
        $em->flush();

        $this->addFlash('success', '🚚 Le matériel a été marqué comme livré. En attente de la confirmation.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/recevoir/{id}', name: 'app_demande_recevoir', methods: ['POST'])]
    public function recevoir(Demande $demande, EntityManagerInterface $em): Response
    {
        $demande->setStatut('Reçu / Clôturé');
        $em->flush();

        $this->addFlash('success', '✅ Confirmation de réception validée avec succès.');
        return $this->redirectToRoute('app_dashboard');
    }

    // NOUVELLE ROUTE : Validation financière par la Compta
    #[Route('/dashboard/compta/valider/{id}', name: 'app_demande_compta_valider', methods: ['POST'])]
    public function validerCompta(Demande $demande, EntityManagerInterface $em): Response
    {
        $demande->setStatut('Validé Compta');
        $em->flush();

        $this->addFlash('success', '💰 Budget accordé avec succès ! Le gérant de stock peut désormais livrer.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/bon/valider/{id}', name: 'app_bon_valider', methods: ['POST'])]
    public function validerBon(BonSortie $bonSortie, EntityManagerInterface $em): Response
    {
        $bonSortie->setStatut('Validé Coordon');
        $em->flush();

        $this->addFlash('success', '✍️ Bon de sortie signé avec succès ! Transmis à la Guérite.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/bon/autoriser/{id}', name: 'app_bon_autoriser', methods: ['POST'])]
    public function autoriserSortie(BonSortie $bonSortie, EntityManagerInterface $em): Response
    {
        $bonSortie->setStatut('Sortie Validée');
        $em->flush();

        $this->addFlash('success', '🚪 Sortie physique autorisée au niveau de la guérite.');
        return $this->redirectToRoute('app_dashboard');
    }
}