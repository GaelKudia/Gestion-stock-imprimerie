<?php

namespace App\Controller;

<<<<<<< HEAD
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(): Response
    {
        // 1. On identifie le rôle de l'utilisateur connecté
        if ($this->isGranted('ROLE_DEPOT')) {
            
            // Ici, plus tard, on récupérera via un Repository 
            // les flux en attente de signature pour l'affichage
            $demandesEnAttenteValidation = [
                ['id' => 1, 'departement' => 'Atelier Offset', 'materiel' => 'Bobine Papier 80g', 'date' => '17/05/2026'],
                ['id' => 2, 'departement' => 'Façonnage', 'materiel' => 'Encre Cyan Cartouche X', 'date' => '17/05/2026'],
            ];

            // On redirige vers la vue spécifique de l'Agent de Dépôt
            return $this->render('dashboard/depot.html.twig', [
                'demandes_attente' => $demandesEnAttenteValidation
            ]);
        }

        // 2. Échappatoire temporaire pour les autres rôles (on s'en occupera juste après)
        return $this->render('dashboard/index.html.twig', [
            'message' => 'Bienvenue sur le tableau de bord général (en attente de configuration).'
=======
use App\Entity\Demande;
use App\Entity\Visite;
use App\Form\DemandeType;
use App\Form\VisiteType;
use App\Repository\DemandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(Request $request, EntityManagerInterface $em, DemandeRepository $demandeRepository): Response
    {
        // --- 1. FORMULAIRE DE DEMANDE DE MATÉRIEL ---
        $nouvelleDemande = new Demande();
        $formDemande = $this->createForm(DemandeType::class, $nouvelleDemande);
        $formDemande->handleRequest($request);

        if ($formDemande->isSubmitted() && $formDemande->isValid()) {
            $nouvelleDemande->setStatut('En attente de préparation');
            $nouvelleDemande->setDateDemande(new \DateTimeImmutable());

            $em->persist($nouvelleDemande);
            $em->flush();

            $this->addFlash('success', 'Votre demande de matériel a bien été envoyée !');
            return $this->redirectToRoute('app_dashboard');
        }

        // --- 2. FORMULAIRE DE PRISE DE RDV (NOUVEAU) ---
        $nouvelleVisite = new Visite();
        $formVisite = $this->createForm(VisiteType::class, $nouvelleVisite);
        $formVisite->handleRequest($request);

        if ($formVisite->isSubmitted() && $formVisite->isValid()) {
            // Par défaut, le visiteur est "Attendu" à l'accueil
            $nouvelleVisite->setStatut('Attendu');

            $em->persist($nouvelleVisite);
            $em->flush();

            $this->addFlash('success', 'Le rendez-vous a bien été enregistré pour l’accueil !');
            return $this->redirectToRoute('app_dashboard');
        }

        // --- 3. RÉCUPÉRATION DES DEMANDES ---
        $mesDemandesDepartement = $demandeRepository->findBy([], ['id' => 'DESC']);

        return $this->render('dashboard/membre.html.twig', [
            'demandes' => $mesDemandesDepartement,
            'form' => $formDemande->createView(),
            'formVisite' => $formVisite->createView() // On envoie le formulaire de RDV à Twig
>>>>>>> origin/feature-module-visites
        ]);
    }
}