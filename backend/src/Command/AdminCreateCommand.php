<?php

declare(strict_types=1);

namespace Ukolio\Command;

use DateTimeImmutable;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Ukolio\App\ApplicationFactory;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\UserRepository;
use const FILTER_VALIDATE_EMAIL;
use const PASSWORD_BCRYPT;

final class AdminCreateCommand extends AbstractCommand
{
	private const int MinPasswordLength = 12;

	protected function configure(): void
	{
		$this->setName('admin:create')
			->setDescription('Create a SystemAdmin user. Prompts for credentials if --email/--password are not given.')
			->addOption('email', null, InputOption::VALUE_REQUIRED, 'Admin email address')
			->addOption('password', null, InputOption::VALUE_REQUIRED, 'Admin password (read from --password or stdin)')
			->addOption('name', null, InputOption::VALUE_REQUIRED, 'Display name', 'System Administrator');
	}

	protected function process(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$application = ApplicationFactory::create();

		$userRepository = $application->container->get(UserRepository::class);
		assert($userRepository instanceof UserRepository);

		$email = $this->resolveEmail($input, $io);
		if ($email === null) {
			return self::FAILURE;
		}

		if ($userRepository->findUserByEmail($email) !== null) {
			$io->error(sprintf('A user with email %s already exists.', $email));
			return self::FAILURE;
		}

		$password = $this->resolvePassword($input, $io);
		if ($password === null) {
			return self::FAILURE;
		}

		$nameOption = $input->getOption('name');
		$name = is_string($nameOption) && trim($nameOption) !== '' ? $nameOption : 'System Administrator';

		$now = new DateTimeImmutable();
		$user = new User(
			email: $email,
			password: password_hash($password, PASSWORD_BCRYPT),
			name: $name,
			locale: LocaleEnum::En,
			currentWorkspaceId: null,
			systemRole: SystemRoleEnum::SystemAdmin,
			emailVerified: true,
		);
		$user->createdAt = $now;
		$user->updatedAt = $now;

		$userRepository->persist($user);

		$io->success(sprintf('Created SystemAdmin %s.', $email));

		return self::SUCCESS;
	}

	private function resolveEmail(InputInterface $input, SymfonyStyle $io): ?string
	{
		$email = $input->getOption('email');
		if (is_string($email) && $email !== '') {
			$normalized = $this->normalizeEmail($email);
			if ($normalized === null) {
				$io->error('Invalid email address.');
				return null;
			}
			return $normalized;
		}

		if (!$input->isInteractive()) {
			$io->error('Missing --email and stdin is not interactive.');
			return null;
		}

		$question = new Question('Email: ');
		$question->setValidator(function (mixed $value): string {
			$normalized = is_string($value) ? $this->normalizeEmail($value) : null;
			if ($normalized === null) {
				throw new \RuntimeException('Invalid email address.');
			}
			return $normalized;
		});

		$helper = $this->getHelper('question');
		assert($helper instanceof QuestionHelper);
		/** @var string $answer */
		$answer = $helper->ask($input, $io, $question);
		return $answer;
	}

	private function normalizeEmail(string $value): ?string
	{
		$value = mb_strtolower(trim($value));
		if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
			return null;
		}
		return $value;
	}

	private function resolvePassword(InputInterface $input, SymfonyStyle $io): ?string
	{
		$password = $input->getOption('password');
		if (is_string($password) && $password !== '') {
			if (strlen($password) < self::MinPasswordLength) {
				$io->error(sprintf('Password must be at least %d characters.', self::MinPasswordLength));
				return null;
			}
			return $password;
		}

		if (!$input->isInteractive()) {
			$io->error('Missing --password and stdin is not interactive.');
			return null;
		}

		$helper = $this->getHelper('question');
		assert($helper instanceof QuestionHelper);

		$first = new Question('Password (min ' . self::MinPasswordLength . ' chars): ');
		$first->setHidden(true)->setHiddenFallback(false);
		$first->setValidator(function (mixed $value): string {
			if (!is_string($value) || strlen($value) < self::MinPasswordLength) {
				throw new \RuntimeException(sprintf('Password must be at least %d characters.', self::MinPasswordLength));
			}
			return $value;
		});

		/** @var string $entered */
		$entered = $helper->ask($input, $io, $first);

		$confirm = new Question('Confirm password: ');
		$confirm->setHidden(true)->setHiddenFallback(false);
		/** @var string $confirmed */
		$confirmed = $helper->ask($input, $io, $confirm);

		if ($entered !== $confirmed) {
			$io->error('Passwords do not match.');
			return null;
		}

		return $entered;
	}
}
