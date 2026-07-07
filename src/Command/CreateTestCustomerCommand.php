<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-test-customer', description: 'Create a test customer for mobile app testing')]
class CreateTestCustomerCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = 'customer@vapeshop.ph';
        $password = 'Customer123456';

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setFirstName('Juan');
            $user->setLastName('Dela Cruz');
            $user->setPhone('+63 9123456789');
            $user->setAgeVerificationStatus(User::AGE_STATUS_VERIFIED);
            $user->setIsEmailVerified(true);
            $user->setIsActive(true);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setRoles([User::ROLE_CUSTOMER]);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $output->writeln(sprintf('✅ Test customer created: %s', $email));
            $output->writeln(sprintf('   Password: %s', $password));
            return Command::SUCCESS;
        }

        $output->writeln(sprintf('✅ Test customer already exists: %s', $email));
        return Command::SUCCESS;
    }
}
