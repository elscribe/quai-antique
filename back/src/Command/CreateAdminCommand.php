<?php

namespace App\Command;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Security\PasswordPolicy;
use App\Util\UuidGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-admin', description: 'Créer un compte administrateur sans exposer son mot de passe en argument.')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UtilisateurRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('email', null, InputOption::VALUE_REQUIRED, 'Adresse e-mail')
            ->addOption('prenom', null, InputOption::VALUE_REQUIRED, 'Prénom')
            ->addOption('nom', null, InputOption::VALUE_REQUIRED, 'Nom');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error('Cette commande exige une saisie interactive du mot de passe.');
            return Command::FAILURE;
        }
        $email = trim((string) ($input->getOption('email') ?? $io->ask('Adresse e-mail')));
        $prenom = trim((string) ($input->getOption('prenom') ?? $io->ask('Prénom')));
        $nom = trim((string) ($input->getOption('nom') ?? $io->ask('Nom')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255
            || $prenom === '' || mb_strlen($prenom) > 80 || $nom === '' || mb_strlen($nom) > 80) {
            $io->error('Adresse e-mail, prénom ou nom invalide.');
            return Command::FAILURE;
        }
        if ($this->users->findOneBy(['email' => $email]) !== null) {
            $io->error('Cette adresse existe déjà ; aucun rôle existant n’a été modifié.');
            return Command::FAILURE;
        }
        $password = $io->askHidden('Mot de passe (8 à 128 caractères, majuscule, minuscule, chiffre et caractère spécial)');
        if (!is_string($password) || !PasswordPolicy::isValid($password)) {
            $io->error('Mot de passe non conforme.');
            return Command::FAILURE;
        }
        $confirmation = $io->askHidden('Confirmer le mot de passe');
        if (!is_string($confirmation) || !hash_equals($password, $confirmation)) {
            $io->error('Les mots de passe ne correspondent pas.');
            return Command::FAILURE;
        }
        $user = (new Utilisateur())->setUuid(UuidGenerator::generate())->setEmail($email)
            ->setPrenom($prenom)->setNom($nom)->setRoles(['ROLE_ADMIN']);
        $user->setMotDePasseHash($this->hasher->hashPassword($user, $password));
        $this->em->persist($user);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $io->error('Cette adresse existe déjà.');
            return Command::FAILURE;
        }
        $io->success('Compte administrateur créé.');
        return Command::SUCCESS;
    }
}
