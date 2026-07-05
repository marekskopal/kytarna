<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Iterator;
use Kytario\Model\Entity\SavedView;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;

interface SavedViewProviderInterface
{
	/** @return Iterator<SavedView> */
	public function getViews(Workspace $workspace, User $user): Iterator;

	public function getViewForUser(int $viewId, User $user): ?SavedView;

	public function createView(User $user, Workspace $workspace, string $name, string $filterConfig): SavedView;

	public function updateView(SavedView $view, string $name, string $filterConfig): SavedView;

	public function deleteView(SavedView $view): void;
}
