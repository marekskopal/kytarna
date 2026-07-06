<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Iterator;
use Kytarna\Model\Entity\SavedView;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;

interface SavedViewProviderInterface
{
	/** @return Iterator<SavedView> */
	public function getViews(Workspace $workspace, User $user): Iterator;

	public function getViewForUser(int $viewId, User $user): ?SavedView;

	public function createView(User $user, Workspace $workspace, string $name, string $filterConfig): SavedView;

	public function updateView(SavedView $view, string $name, string $filterConfig): SavedView;

	public function deleteView(SavedView $view): void;
}
