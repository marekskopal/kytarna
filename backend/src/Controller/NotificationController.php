<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ukolio\Dto\NotificationDto;
use Ukolio\Dto\NotificationListDto;
use Ukolio\Model\Entity\Notification;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Provider\NotificationProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class NotificationController
{
	private const int DefaultLimit = 30;

	private const int MaxLimit = 100;

	public function __construct(
		private NotificationProviderInterface $notificationProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::Notifications->value)]
	public function actionList(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$query = $request->getQueryParams();

		$limit = is_numeric($query['limit'] ?? null)
			? min(self::MaxLimit, max(1, (int) $query['limit']))
			: self::DefaultLimit;
		$offset = is_numeric($query['offset'] ?? null) ? max(0, (int) $query['offset']) : 0;
		$unreadOnly = ($query['unreadOnly'] ?? null) === '1' || ($query['unreadOnly'] ?? null) === 'true';

		$notifications = array_map(
			static fn (Notification $n): NotificationDto => NotificationDto::fromEntity($n),
			$this->notificationProvider->listForUser($user, $limit, $offset, $unreadOnly),
		);

		return new JsonResponse(new NotificationListDto($notifications, $this->notificationProvider->unreadCount($user)));
	}

	#[RouteGet(Routes::NotificationsUnreadCount->value)]
	public function actionUnreadCount(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);

		return new JsonResponse(['unreadCount' => $this->notificationProvider->unreadCount($user)]);
	}

	#[RoutePost(Routes::NotificationRead->value)]
	public function actionMarkRead(ServerRequestInterface $request, int $notificationId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$notification = $this->notificationProvider->getNotification($notificationId);
		if ($notification === null || $notification->user->id !== $user->id) {
			return new NotFoundResponse('Notification not found.');
		}

		$this->notificationProvider->markRead($notification);

		return new JsonResponse(NotificationDto::fromEntity($notification));
	}

	#[RoutePost(Routes::NotificationsReadAll->value)]
	public function actionMarkAllRead(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$count = $this->notificationProvider->markAllRead($user);

		return new JsonResponse(['marked' => $count]);
	}

	#[RouteDelete(Routes::Notification->value)]
	public function actionDelete(ServerRequestInterface $request, int $notificationId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$notification = $this->notificationProvider->getNotification($notificationId);
		if ($notification === null || $notification->user->id !== $user->id) {
			return new NotFoundResponse('Notification not found.');
		}

		$this->notificationProvider->delete($notification);

		return new OkResponse();
	}
}
