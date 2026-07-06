<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider\Exception;

use RuntimeException;

/**
 * Thrown when a user tries to create/own a second workspace. A user is the Teacher of at most one
 * workspace; they may join any number of others as a Student.
 */
final class WorkspaceOwnershipException extends RuntimeException
{
	public function __construct()
	{
		parent::__construct('You already own a workspace. A teacher can own only one workspace.');
	}
}
