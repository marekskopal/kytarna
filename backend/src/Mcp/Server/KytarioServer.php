<?php

declare(strict_types=1);

namespace Kytario\Mcp\Server;

use Mcp\Server;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final readonly class KytarioServer
{
	public function __construct(private ContainerInterface $container, private LoggerInterface $logger)
	{
	}

	public function build(?SessionStoreInterface $sessionStore = null): Server
	{
		$builder = Server::builder()
			->setContainer($this->container)
			->setLogger($this->logger)
			->setDiscovery(
				basePath: dirname(__DIR__, 2),
				scanDirs: ['Mcp/Tool'],
			)
			->setServerInfo(name: 'kytario', version: '1.0.0', description: 'Kytario MCP server — guitar courses and lectures')
			->setInstructions(
				'This server manages the authenticated user\'s guitar learning content. '
				. 'A workspace contains courses (e.g. "Fingerstyle Basics"), and each course contains lectures '
				. '(songs, exercises, techniques) that move through a per-course practice workflow. '
				. 'Typical flow when adding lectures from an external source: '
				. '1) call find_course_by_name to check if the target course exists; '
				. '2) if it does not, call create_course (a default "To Learn → Learning → Mastered" workflow is created automatically); '
				. '3) call create_lecture for each item (defaults to the Start status, e.g. "To Learn"). '
				. 'Typical flow when practising a lecture: '
				. '1) call find_lecture_by_name to locate the lecture; '
				. '2) call move_lecture with statusName="Learning" to start practising it; '
				. '3) once it is mastered, call move_lecture with statusName="Mastered". '
				. 'Use list_statuses to discover the column names for a given course — workflows are per-course and customizable. '
				. 'Lectures also support tags, file attachments, watchers, and an audit log (list_events). '
				. 'Each lecture can hold tabs, practice progress, and reference links: '
				. 'tabs store notation as alphaTex (create_tab/update_tab validate the alphaTex via the tab-service and '
				. 'return the errors if it is invalid; import_gp_file converts an uploaded Guitar Pro file to alphaTex); '
				. 'progress entries log practice sessions (create_progress_entry) and get_practice_summary aggregates '
				. 'totals, per-week counts, and the BPM trend for a lecture or course; '
				. 'add_lecture_link attaches YouTube or other reference links. '
				. 'Guitar metadata (tuning, capo, targetTempoBpm, difficulty) lives directly on the lecture — '
				. 'list_lectures can filter by tuning (e.g. "Drop D").',
			);

		if ($sessionStore !== null) {
			$builder->setSession($sessionStore);
		}

		return $builder->build();
	}
}
