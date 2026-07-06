// Archive stale Done tasks
// ─────────────────────────
// Archives every task that has sat in a Finish-type status (e.g. "Done") for
// more than MAX_AGE_DAYS, for each project named in TARGET_PROJECTS.
//
// Deploy as a Script (Settings → Scripts) with:
//   • Trigger:       Scheduled
//   • triggerConfig: 0 3 * * *      (daily at 03:00; standard 5-field cron)
//   • active:        true
//
// "Time in status" is derived from the audit log: the most recent `TaskMoved`
// event into the task's current status. Tasks created directly in a Finish
// status (never moved) fall back to their `TaskCreated` time.
//
// The Script sandbox caps task-API calls per run, so a large first-run backlog
// is drained across consecutive daily runs (CALL_BUDGET below). Steady-state
// runs archive only the few tasks that newly cross the 14-day line.

var TARGET_PROJECTS = ['Kytarna', 'FinGather'];
var MAX_AGE_DAYS = 14;
var CALL_BUDGET = 180; // stay safely under the sandbox's 200 task-API calls/run

var cutoff = Date.now() - MAX_AGE_DAYS * 24 * 60 * 60 * 1000;
var calls = 0;
var archived = 0;
var deferred = 0;

var projects = kytarna.projects.list();
calls++;

TARGET_PROJECTS.forEach(function (name) {
    var project = findByName(projects, name);
    if (!project) {
        kytarna.log('Project not found, skipping: ' + name);
        return;
    }

    var statuses = kytarna.workflow(project.id).statuses();
    calls++;
    var finishStatuses = statuses.filter(function (s) { return s.type === 'Finish'; });
    if (finishStatuses.length === 0) {
        kytarna.log('No Finish status in "' + name + '", skipping.');
        return;
    }

    // 1) Collect candidates read-only (stable pagination — no mutation yet).
    var candidates = collectTasks(finishStatuses);

    // 2) Archive those that crossed the age line.
    for (var i = 0; i < candidates.length; i++) {
        var task = candidates[i];

        if (calls >= CALL_BUDGET - 2) { // reserve budget for lookup + archive
            deferred++;
            continue;
        }

        var enteredAt = enteredStatusAt(task.id, task.statusId);
        if (enteredAt === null) {
            kytarna.log('No timestamp for ' + task.code + ', skipping.');
            continue;
        }

        if (enteredAt <= cutoff) {
            kytarna.tasks.archive(task.id);
            calls++;
            archived++;
            kytarna.log('Archived ' + task.code + ' — ' + task.name + ' (' + name + ' / ' + task.statusName + ')');
        }
    }
});

if (deferred > 0) {
    kytarna.log('Reached the per-run call budget; ' + deferred + ' task(s) deferred to the next daily run.');
}
kytarna.log('Done. Archived ' + archived + ' task(s).');

// Read every active task sitting in any of the given Finish statuses.
function collectTasks(finishStatuses) {
    var pageSize = 200;
    var out = [];

    for (var s = 0; s < finishStatuses.length; s++) {
        var status = finishStatuses[s];
        var offset = 0;

        while (calls < CALL_BUDGET) {
            var page = kytarna.tasks.list({
                statusIds: [status.id],
                includeArchived: false,
                limit: pageSize,
                offset: offset,
            });
            calls++;

            for (var i = 0; i < page.length; i++) {
                out.push({
                    id: page[i].id,
                    code: page[i].code,
                    name: page[i].name,
                    statusId: status.id,
                    statusName: status.name,
                });
            }

            if (page.length < pageSize) {
                break;
            }
            offset += pageSize;
        }
    }

    return out;
}

// Epoch-ms when the task last entered `statusId`, or null if unknown.
function enteredStatusAt(taskId, statusId) {
    var moves = kytarna.events.list({ taskId: taskId, type: 'TaskMoved', limit: 50 });
    calls++;
    for (var i = 0; i < moves.length; i++) { // newest first
        var meta = moves[i].metadata;
        if (meta && meta.toStatusId === statusId) {
            return new Date(moves[i].createdAt).getTime();
        }
    }

    // Never moved into this status via an event → created directly in it.
    var created = kytarna.events.list({ taskId: taskId, type: 'TaskCreated', limit: 1 });
    calls++;
    if (created.length > 0) {
        return new Date(created[0].createdAt).getTime();
    }

    return null;
}

function findByName(list, name) {
    for (var i = 0; i < list.length; i++) {
        if (list[i].name === name) {
            return list[i];
        }
    }
    return null;
}
