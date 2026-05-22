<?php

namespace App\Controller;

use App\Entity\User;
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
        // Sécurité : On vérifie que seul l'admin peut entrer ici
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // 1. L'admin voit ABSOLUMENT TOUT
        $allDemandes = $demandeRepository->findBy([], ['id' => 'DESC']);
        $allBons = $bonSortieRepository->findBy([], ['id' => 'DESC']);
        $allUsers = $userRepository->findAll();

        // 2. Gestion du formulaire de création d'utilisateur
        $newUser = new User();
        $form = $this->createForm(UserType::class, $newUser);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hachage sécurisé du mot de passe entré
            $hashedPassword = $hasher->hashPassword($newUser, $newUser->getPassword());
            $newUser->setPassword($hashedPassword);

            $em->persist($newUser);
            $em->flush();

            $this->addFlash('success', '👤 Nouvel identifiant créé avec succès pour le département !');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->render('admin/index.html.twig', [
            'demandes' => $allDemandes,
            'bons' => $allBons,
            'users' => $allUsers,
            'form' => $form->createView(),
        ]);
    }
}