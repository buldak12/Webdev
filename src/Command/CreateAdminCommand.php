<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Create or reset an admin account')]
class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED);
        $this->addArgument('password', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setFirstName('Admin');
            $user->setLastName('User');
            $user->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
            $user->setIsEmailVerified(true);
            $this->entityManager->persist($user);
        }

        // Always ensure admin role and active status.
        $user->setRoles([User::ROLE_ADMIN]);
        $user->setIsActive(true);
        $user->setIsEmailVerified(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->entityManager->flush();

        $output->writeln(sprintf('Admin user created/updated: %s', $email));

        return Command::SUCCESS;
    }
}
