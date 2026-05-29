<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Article;
use App\Form\UserType;
use App\Repository\DemandeRepository;
use App\Repository\BonSortieRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_dashboard')]
    public function index(
        Request $request,
        DemandeRepository $demandeRepository,
        BonSortieRepository $bonSortieRepository,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        
        // Récupérations des données d'origine pour l'admin
        $allDemandes = $demandeRepository->findBy([], ['id' => 'DESC']);
        $allBons = $bonSortieRepository->findBy([], ['id' => 'DESC']);
        $allUsers = $userRepository->findAll();
        $articles = $em->getRepository(Article::class)->findAll();

        // Logique du Rapport Général Historique
        $debutAujourdhui = new \DateTime('today 00:00:00');
        $finAujourdhui = new \DateTime('today 23:59:59');

        $dateDebutParam = $request->query->get('date_debut');
        $dateFinParam = $request->query->get('date_fin');

        $qbGlobal = $demandeRepository->createQueryBuilder('d');

        if ($dateDebutParam && $dateFinParam) {
            $qbGlobal->where('d.createdAt BETWEEN :dateDebut AND :dateFin')
                     ->setParameter('dateDebut', new \DateTime($dateDebutParam . ' 00:00:00'))
                     ->setParameter('dateFin', new \DateTime($dateFinParam . ' 23:59:59'));
        } else {
            $qbGlobal->where('d.createdAt BETWEEN :debutAujourdhui AND :finAujourdhui')
                     ->setParameter('debutAujourdhui', $debutAujourdhui)
                     ->setParameter('finAujourdhui', $finAujourdhui);
        }

        $rapportGlobal = $qbGlobal->orderBy('d.createdAt', 'DESC')
                                  ->getQuery()
                                  ->getResult();

        // Gestion du formulaire de création d'utilisateur
        $newUser = new User();
        $form = $this->createForm(UserType::class, $newUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $hashedPassword = $hasher->hashPassword($newUser, $newUser->getPassword());
            $newUser->setPassword($hashedPassword);

            $chosenRoles = $form->get('roles')->getData();
            if (!empty($chosenRoles)) {
                $newUser->setRoles(is_array($chosenRoles) ? $chosenRoles : [$chosenRoles]);
            } else {
                $newUser->setRoles(['ROLE_USER']);
            }

            $em->persist($newUser);
            $em->flush();

            $this->addFlash('success', 'Nouvel identifiant créé avec succès !');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/index.html.twig', [
            'demandes' => $allDemandes,
            'bons' => $allBons,
            'users' => $allUsers,
            'articles' => $articles,
            'rapportGlobal' => $rapportGlobal,
            'date_debut' => $dateDebutParam,
            'date_fin' => $dateFinParam,
            'form' => $form->createView(),
        ]);
    }
}