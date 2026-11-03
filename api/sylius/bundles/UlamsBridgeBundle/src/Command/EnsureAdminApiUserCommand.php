<?php

declare(strict_types=1);

namespace Ulams\Bridge\Command;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * Creates (or updates the password of) the one admin API user the LMS signs in with. Credentials come from the
 * environment, never from arguments, so they do not land in a shell history or a process list.
 */
#[AsCommand(name: 'ulams:admin-api-user:ensure', description: 'Create or update the Sylius admin API user the LMS uses (ULAMS_ADMIN_API_EMAIL, ULAMS_ADMIN_API_PASSWORD)')]
final class EnsureAdminApiUserCommand extends Command
{
    /**
     * @param RepositoryInterface<AdminUserInterface> $users
     * @param FactoryInterface<AdminUserInterface> $factory
     */
    public function __construct(
        #[Autowire(service: 'sylius.repository.admin_user')]
        private readonly RepositoryInterface $users,
        #[Autowire(service: 'sylius.factory.admin_user')]
        private readonly FactoryInterface $factory,
        private readonly EntityManagerInterface $em,
        private readonly PasswordHasherFactoryInterface $hashers,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) getenv('ULAMS_ADMIN_API_EMAIL');
        $password = (string) getenv('ULAMS_ADMIN_API_PASSWORD');
        if ($email === '' || strlen($password) < 12) {
            $output->writeln('<error>Set ULAMS_ADMIN_API_EMAIL and ULAMS_ADMIN_API_PASSWORD (at least 12 characters).</error>');

            return Command::FAILURE;
        }

        /** @var AdminUserInterface|null $user */
        $user = $this->users->findOneBy(['email' => $email]);
        $created = $user === null;
        if ($created) {
            /** @var AdminUserInterface $user */
            $user = $this->factory->createNew();
            $user->setEmail($email);
            $user->setUsername($email);
            $user->setLocaleCode('en_US');
            $user->setFirstName('Ulams');
            $user->setLastName('LMS');
        }
        $user->setEnabled(true);
        $user->addRole('ROLE_API_ACCESS');
        $user->setPlainPassword($password);
        $user->setPassword($this->hashers->getPasswordHasher($user)->hash($password));
        if ($created) {
            $this->em->persist($user);
        }
        $this->em->flush();
        $output->writeln($created ? 'Admin API user created.' : 'Admin API user updated.');

        return Command::SUCCESS;
    }
}
