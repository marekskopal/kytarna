<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpLectureLinkDto;
use Kytarna\Mcp\Dto\McpLectureLinkListDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\LectureLink;
use Kytarna\Service\Provider\LectureProviderInterface;
use Kytarna\Service\Provider\LinkProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class LinkTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private LectureProviderInterface $lectureProvider,
		private LinkProviderInterface $linkProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * Add a reference link to a lecture (e.g. a YouTube lesson or a tab site). The kind is auto-detected
	 * from the URL when omitted (youtube vs other).
	 *
	 * @param int $lectureId Lecture ID
	 * @param string $url The link URL (must start with http:// or https://)
	 * @param string|null $label Optional human-readable label
	 * @param string|null $kind Optional kind: "youtube" or "other"; auto-detected when omitted
	 * @param int|null $timestampSeconds Optional start timestamp in seconds (for video links)
	 */
	#[McpTool(name: 'add_lecture_link', description: 'Add a reference link (YouTube/other) to a lecture.')]
	public function addLectureLink(
		int $lectureId,
		string $url,
		?string $label = null,
		?string $kind = null,
		?int $timestampSeconds = null,
	): McpLectureLinkDto {
		$user = $this->userContext->getUser();
		$lecture = $this->requireLecture($lectureId);

		$link = $this->linkProvider->addLink($user, $lecture, $url, $label, $kind, $timestampSeconds);

		return McpLectureLinkDto::fromEntity($link);
	}

	/**
	 * List the reference links attached to a lecture.
	 *
	 * @param int $lectureId Lecture ID
	 */
	#[McpTool(name: 'list_lecture_links', description: 'List the reference links attached to a lecture.')]
	public function listLectureLinks(int $lectureId): McpLectureLinkListDto
	{
		$lecture = $this->requireLecture($lectureId);
		$links = array_map(
			static fn (LectureLink $link): McpLectureLinkDto => McpLectureLinkDto::fromEntity($link),
			$this->linkProvider->getLinksByLecture($lecture),
		);

		return new McpLectureLinkListDto($links);
	}

	/**
	 * Delete a reference link from a lecture.
	 *
	 * @param int $linkId Link ID
	 */
	#[McpTool(name: 'delete_lecture_link', description: 'Delete a reference link from a lecture.')]
	public function deleteLectureLink(int $linkId): string
	{
		$user = $this->userContext->getUser();
		$link = $this->linkProvider->getLink($linkId);
		if ($link === null || !$this->workspaceProvider->isMember($user, $link->lecture->course->workspace)) {
			throw new RuntimeException(sprintf('Link %d not found.', $linkId));
		}

		$this->linkProvider->deleteLink($user, $link);

		return 'Link deleted.';
	}

	private function requireLecture(int $lectureId): Lecture
	{
		$lecture = $this->lectureProvider->getLecture($lectureId);
		if ($lecture === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $lecture->course->workspace)) {
			throw new RuntimeException(sprintf('Lecture %d not found.', $lectureId));
		}
		return $lecture;
	}
}
