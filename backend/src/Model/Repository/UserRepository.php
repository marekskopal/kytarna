<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\User;

/** @extends AbstractRepository<User> */
final class UserRepository extends AbstractRepository
{
	public function findUserById(int $userId): ?User
	{
		return $this->findOne(['id' => $userId]);
	}

	public function findUserByEmail(string $email): ?User
	{
		return $this->findOne(['email' => $email]);
	}

	public function findUserByGoogleId(string $googleId): ?User
	{
		return $this->findOne(['google_id' => $googleId]);
	}

	/** @return Iterator<User> */
	public function findAllUsers(): Iterator
	{
		return $this->select()->orderBy('id', 'ASC')->fetchAll();
	}

	public function countSystemAdmins(): int
	{
		$count = 0;
		foreach ($this->findAllUsers() as $user) {
			if ($user->systemRole === SystemRoleEnum::SystemAdmin) {
				$count++;
			}
		}
		return $count;
	}
}
