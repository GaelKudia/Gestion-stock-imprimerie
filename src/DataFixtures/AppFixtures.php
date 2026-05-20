<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    // On injecte le service pour hacher les mots de passe
    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Création de l'utilisateur de test
        $user = new User();
        $user->setEmail('admin@imprimerie.com');
        $user->setRoles(['ROLE_ADMIN']);

        // 2. Hachage sécurisé du mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword(
            $user,
            'admin123'
        );
        // 2. NOUVEAU : Le compte de ton Agent de Dépôt (Magasinier)
        $agentDepot = new User();
        $agentDepot->setEmail('depot@imprimerie.com');
        $agentDepot->setRoles(['ROLE_DEPOT']); // Le rôle requis par notre contrôleur
        $agentDepot->setPassword($this->passwordHasher->hashPassword($agentDepot, 'depot123'));
        $manager->persist($agentDepot);

        $manager->flush();
        $user->setPassword($hashedPassword);

        // 3. On demande à Doctrine de le sauvegarder
        $manager->persist($user);

        // 4. On envoie tout en base de données
        $manager->flush();
    }
}