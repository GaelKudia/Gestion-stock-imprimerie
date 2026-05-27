<?php

namespace App\Controller;

use App\Entity\Demande;
use App\Entity\BonSortie;
use App\Entity\Article;
use App\Entity\Visite; 

use App\Form\DemandeType;
use App\Form\BonSortieType;
use App\Form\VisiteType;
use App\Form\StockType;
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
        
        // A. FORMULAIRE DU STOCK (MAGASINIER)
        $formStockView = null;
        if ($userRole === 'ROLE_STOCK') {
            $formStock = $this->createForm(StockType::class);
            $formStock->handleRequest($request);

            if ($formStock->isSubmitted() && $formStock->isValid()) {
                $data = $formStock->getData();
                
                $seuilAlerte = (int)$request->request->get('seuil_alerte', 5);
                $nomArticle = $data['article'];
                $quantiteAjoutee = (int)$data['quantite'];

                $articleRepository = $em->getRepository(Article::class);
                $article = $articleRepository->findOneBy(['nom' => $nomArticle]);

                if (!$article) {
                    $article = new Article();
                    $article->setNom($nomArticle);
                    $article->setQuantite($quantiteAjoutee);
                } else {
                    $article->setQuantite($article->getQuantite() + $quantiteAjoutee);
                }

                $article->setSeuilAlerte($seuilAlerte);

                $em->persist($article);
                $em->flush();
                
                $this->addFlash('success', '📥 L\'article "' . $nomArticle . '" a bien été enregistré ! Stock total : ' . $article->getQuantite());
                return $this->redirectToRoute('app_dashboard');
            }
            $formStockView = $formStock->createView();
        }

        // B. FORMULAIRE DE DEMANDE DE MATÉRIEL (SÉCURISÉ SELON LE STOCK)
        $demande = new Demande();
        $formDemande = $this->createForm(DemandeType::class, $demande);
        $formDemande->handleRequest($request);

        if ($formDemande->isSubmitted() && $formDemande->isValid()) {
            $article = $em->getRepository(Article::class)->findOneBy(['nom' => $demande->getMateriel()]);
            $quantiteDemandee = $demande->getQuantite() ?? 1;

            if (!$article || $article->getQuantite() < $quantiteDemandee) {
                $stockActuel = $article ? $article->getQuantite() : 0;
                $this->addFlash('danger', '🛑 Demande impossible. Le matériel "' . $demande->getMateriel() . '" n\'est pas disponible ou insuffisant en stock (Disponible : ' . $stockActuel . ').');
                return $this->redirectToRoute('app_dashboard');
            }

            $demande->setDepartement($userDept); 
            $demande->setStatut('En attente'); 
            $em->persist($demande);
            $em->flush();
            
            $this->addFlash('success', '📦 Votre demande de matériel a été envoyée ! En attente de validation financière.');
            return $this->redirectToRoute('app_dashboard');
        }

        // C. FORMULAIRE DE BON DE SORTIE
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

        // D. FORMULAIRE DE RESERVATION DE VISITE
        $formVisiteView = null;
        if ($userRole !== 'ROLE_GUERITE' && class_exists('App\Form\VisiteType')) {
            $visite = class_exists('App\Entity\Visite') ? new Visite() : new \stdClass(); 
            if ($visite instanceof Visite) {
                $formVisite = $this->createForm(VisiteType::class, $visite);
                $formVisite->handleRequest($request);
                
                if ($formVisite->isSubmitted() && $formVisite->isValid()) {
                    $visite->setStatut('RESERVE'); 
                    $em->persist($visite);
                    $em->flush();
                    
                    $this->addFlash('success', '🗓️ La visite a été réservée avec succès !');
                    return $this->redirectToRoute('app_dashboard');
                }
                $formVisiteView = $formVisite->createView();
            }
        }   

        // =========================================================================
        // 🔍 3. RÉCUPÉRATION DES DONNÉES (Nettoyée et sécurisée)
        // =========================================================================
        $demandes = [];
        $bonsSortie = [];
        $visites = [];

        if ($userRole === 'ROLE_ACCUEIL' || $userRole === 'ROLE_RECEPTION') {
            if (class_exists('App\Entity\Visite')) {
                $visites = $em->getRepository(Visite::class)->findBy(['statut' => 'A_L_ACCUEIL'], ['id' => 'DESC']);
            }
        } else {
            if ($userRole === 'ROLE_STOCK' || $userRole === 'ROLE_COMPTA' || $userRole === 'ROLE_COORDON' || $userRole === 'ROLE_ADMIN') {
                $demandes = $demandeRepository->findBy([], ['id' => 'DESC']);
            } else {
                $demandes = $demandeRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
            }

            if ($userRole === 'ROLE_COORDON' || $userRole === 'ROLE_ADMIN') {
                $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Créé'], ['id' => 'DESC']);
            } elseif ($userRole === 'ROLE_GUERITE') {
                $bonsSortie = $bonSortieRepository->findBy(['statut' => 'Validé Coordon'], ['id' => 'DESC']);
                if (class_exists('App\Entity\Visite')) {
                    $visites = $em->getRepository(Visite::class)->findBy(['statut' => 'RESERVE'], ['id' => 'DESC']);
                }
            } else {
                $bonsSortie = $bonSortieRepository->findBy(['departement' => $userDept], ['id' => 'DESC']);
            }
        }

        // =========================================================================
        // 📊 4. LOGIQUE DES COMPTEURS (Garantit l'existence de $stats)
        // =========================================================================
        $stats = [
            'demandes_attente' => count($demandeRepository->findBy(['statut' => 'En attente'])),
            'bons_a_valider' => count($bonSortieRepository->findBy(['statut' => 'Créé'])),
            'bons_guerite' => count($bonSortieRepository->findBy(['statut' => 'Validé Coordon']))
        ];

        // =========================================================================
        // 🚀 5. RENDU UNIQUE DES TEMPLATES
        // =========================================================================
        if ($userRole === 'ROLE_ADMIN') {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        if ($userRole === 'ROLE_GUERITE') {
            return $this->render('dashboard/guerite.html.twig', [
                'bonsSortie' => $bonsSortie,
                'visites' => $visites,
                'userRole' => $userRole,
                'stats' => $stats
            ]);
        }

        // Le bloc Accueil est maintenant parfaitement placé ici, après la création de $stats !
        if ($userRole === 'ROLE_ACCUEIL' || $userRole === 'ROLE_RECEPTION') {
            return $this->render('dashboard/accueil.html.twig', [
                'visites' => $visites,
                'userRole' => $userRole,
                'stats' => $stats,
                'formVisite' => $formVisiteView, 
                'formBon' => $formBon->createView() 
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

        if ($userRole === 'ROLE_STOCK') {
            $articles = $em->getRepository(Article::class)->findAll();

            return $this->render('dashboard/stock.html.twig', [
                'demandes' => $demandes,
                'userRole' => $userRole,
                'stats' => $stats,
                'formStock' => $formStockView,
                'articles' => $articles 
            ]);
        }

        return $this->render('dashboard/membre.html.twig', [
            'demandes' => $demandes,
            'bonsSortie' => $bonsSortie,
            'userRole' => $userRole,
            'stats' => $stats,
            'formDemande' => $formDemande->createView(), 
            'formBon' => $formBon->createView(),
            'formVisite' => $formVisiteView 
        ]);
    } 

    // =========================================================================
    // 6. CIRCUITS DE VALIDATION LOGISTIQUES CORRIGÉS
    // =========================================================================

    #[Route('/dashboard/compta/valider/{id}', name: 'app_demande_compta_valider', methods: ['POST'])]
    public function validerCompta(Demande $demande, EntityManagerInterface $em): Response
    {
        $article = $em->getRepository(Article::class)->findOneBy(['nom' => $demande->getMateriel()]);
        $quantiteDemandee = $demande->getQuantite() ?? 1;

        if (!$article || $article->getQuantite() < $quantiteDemandee) {
            $stockActuel = $article ? $article->getQuantite() : 0;
            $this->addFlash('danger', '🛑 Impossible de valider le budget. Le matériel "' . $demande->getMateriel() . '" n\'est pas assez en stock au dépôt. La demande reste en attente.');
            return $this->redirectToRoute('app_dashboard');
        }

        $demande->setStatut('Validé Compta');
        $em->flush();

        $this->addFlash('success', '💰 Budget accordé avec succès ! Le dépôt a maintenant l\'autorisation de livrer le matériel.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/livrer/{id}', name: 'app_demande_livrer', methods: ['POST'])]
    public function livrer(Demande $demande, EntityManagerInterface $em): Response
    {
        if ($demande->getStatut() !== 'Validé Compta') {
            $this->addFlash('danger', '❌ Action interdite. Cette demande n\'a pas encore reçu la validation budgétaire de la comptabilité.');
            return $this->redirectToRoute('app_dashboard');
        }

        $article = $em->getRepository(Article::class)->findOneBy(['nom' => $demande->getMateriel()]);
        
        if ($article) {
            $quantiteDemandee = $demande->getQuantite() ?? 1; 
            $nouvelleQuantite = $article->getQuantite() - $quantiteDemandee; 

            if ($nouvelleQuantite < 0) {
                $this->addFlash('danger', '❌ Erreur de dernière minute : Le stock est devenu insuffisant.');
                return $this->redirectToRoute('app_dashboard');
            }
            
            $article->setQuantite($nouvelleQuantite);
            $demande->setStatut('Livré par le Stock');
            $em->flush();
            
            $this->addFlash('success', '🚚 Le matériel a été marqué comme livré et déduit du stock.');
        } else {
            $this->addFlash('danger', '❌ Article introuvable en base de données.');
        }

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
    public function validerEntreeGuerite(Visite $visite, EntityManagerInterface $em): Response
    {
        $visite->setStatut('A_L_ACCUEIL');
        $em->flush();
        $this->addFlash('success', '🪪 Entrée du visiteur validée ! Transmis à l\'accueil.');
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard/visite/cloturer/{id}', name: 'app_visite_cloturer', methods: ['POST'])]
    public function cloturerVisite(Visite $visite, EntityManagerInterface $em): Response
    {
        $visite->setStatut('TERMINE');
        $em->flush();
        $this->addFlash('success', '✅ Visite clôturée. Le départ du visiteur a été enregistré.');
        return $this->redirectToRoute('app_dashboard');
    }
}