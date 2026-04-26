<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Create a new user (family or teacher)')]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email      = $io->ask('Email');
        $familyName = $io->ask('Name (Familie oder Lehrerin)');
        $role       = $io->choice('Rolle', ['family', 'teacher'], 'family');
        $password   = $io->askHidden('Passwort');

        $user = new User();
        $user->setEmail($email)
            ->setFamilyName($familyName)
            ->setRole($role)
            ->setPassword($this->hasher->hashPassword($user, $password));

        $this->em->persist($user);
        $this->em->flush();

        $io->success("Benutzer '$email' ($role) wurde angelegt.");

        return Command::SUCCESS;
    }
}
