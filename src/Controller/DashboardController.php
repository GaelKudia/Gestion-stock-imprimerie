<?php

namespace App\Controller;

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
        ]);
    }
}