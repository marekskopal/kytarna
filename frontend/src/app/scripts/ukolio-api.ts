// Single source of truth for the `ukolio` script host API: the TypeScript
// declaration fed to Monaco for autocomplete, and the structured reference data
// rendered in the editor's API panel. Mirrors backend/src/Service/Script/Host/*.

export interface ApiEntry {
    /** Signature shown in the panel row, e.g. `tasks.list(filters?)`. */
    signature: string;
    /** Human description of what the call does. */
    description: string;
    /** Return type label rendered as a badge. */
    returns: string;
    /** Code inserted into the editor when "Insert snippet" is pressed. */
    snippet: string;
}

export interface ApiGroup {
    name: string;
    entries: ApiEntry[];
}

export const UKOLIO_API_GROUPS: readonly ApiGroup[] = [
    {
        name: 'tasks',
        entries: [
            {
                signature: 'ukolio.tasks.list(filters?)',
                description: 'List tasks in the workspace. Filters: limit, offset, statusIds, onlyActive, search, includeArchived.',
                returns: 'Task[]',
                snippet: 'const tasks = ukolio.tasks.list({ onlyActive: true, limit: 50 });',
            },
            {
                signature: 'ukolio.tasks.get(idOrCode)',
                description: 'Fetch a single task by numeric id or its code (e.g. "PRJ-12").',
                returns: 'Task | null',
                snippet: 'const task = ukolio.tasks.get("PRJ-12");',
            },
            {
                signature: 'ukolio.tasks.create(input)',
                description: 'Create a task. Requires projectId and name; optional statusName, priorityId, description.',
                returns: 'Task',
                snippet: 'const task = ukolio.tasks.create({ projectId: 1, name: "New task" });',
            },
            {
                signature: 'ukolio.tasks.move(id, statusName)',
                description: 'Move a task to another status by status name within its project workflow.',
                returns: 'Task',
                snippet: 'ukolio.tasks.move(task.id, "In Progress");',
            },
            {
                signature: 'ukolio.tasks.update(id, input)',
                description: 'Update a task; omitted fields keep their value. Accepts name, description, priorityId/Name, statusId/Name, dueDate.',
                returns: 'Task',
                snippet: 'ukolio.tasks.update(task.id, { description: "Updated by script" });',
            },
            {
                signature: 'ukolio.tasks.delete(id)',
                description: 'Delete a task. Subtasks are orphaned (kept as top-level), not deleted.',
                returns: '{ id, deleted }',
                snippet: 'ukolio.tasks.delete(task.id);',
            },
            {
                signature: 'ukolio.tasks.setTags(id, tagIds)',
                description: 'Replace the full tag set on a task with the given workspace tag ids.',
                returns: 'Task',
                snippet: 'ukolio.tasks.setTags(task.id, [1, 2]);',
            },
            {
                signature: 'ukolio.tasks.addComment(id, body)',
                description: 'Append a markdown comment to a task.',
                returns: '{ id, body }',
                snippet: 'ukolio.tasks.addComment(task.id, "Automated note");',
            },
        ],
    },
    {
        name: 'projects',
        entries: [
            {
                signature: 'ukolio.projects.list()',
                description: 'List every project in the workspace.',
                returns: 'Project[]',
                snippet: 'const projects = ukolio.projects.list();',
            },
            {
                signature: 'ukolio.workflow(projectId)',
                description: 'Get a project workflow with its ordered statuses.',
                returns: '{ statuses }',
                snippet: 'const { statuses } = ukolio.workflow(1);',
            },
        ],
    },
    {
        name: 'vars',
        entries: [
            {
                signature: 'ukolio.vars.get(key)',
                description: 'Read a workspace variable. Secrets are decrypted transparently and redacted from logs.',
                returns: 'string | null',
                snippet: 'const webhook = ukolio.vars.get("SLACK_WEBHOOK_URL");',
            },
            {
                signature: 'ukolio.vars.set(key, value, opts?)',
                description: 'Create or update a workspace variable. Pass { secret: true } to encrypt at rest.',
                returns: 'void',
                snippet: 'ukolio.vars.set("LAST_RUN", new Date().toISOString());',
            },
        ],
    },
    {
        name: 'runtime',
        entries: [
            {
                signature: 'ukolio.log(...args)',
                description: 'Write a line to the run log. Multiple arguments are joined by a space.',
                returns: 'void',
                snippet: 'ukolio.log("Processed", tasks.length, "tasks");',
            },
            {
                signature: 'ukolio.fetch(url, opts?)',
                description: 'HTTP request from the sandbox (max 20 per run). Returns status, headers and text.',
                returns: '{ status, headers, text }',
                snippet: 'const res = ukolio.fetch("https://example.com", { method: "GET" });',
            },
            {
                signature: 'ukolio.context',
                description: 'Run context: triggerType, the event payload (Event triggers) and scheduledAt (Scheduled).',
                returns: '{ triggerType, event, scheduledAt }',
                snippet: 'const trigger = ukolio.context.triggerType;',
            },
        ],
    },
];

/** TypeScript declaration loaded into Monaco so `ukolio.*` gets autocomplete + hovers. */
export const UKOLIO_DTS = `
interface UkolioTask {
  id: number;
  code: string;
  name: string;
  description: string | null;
  statusId: number;
  statusName: string;
  priorityId: number | null;
  createdAt: string;
  updatedAt: string;
}

interface UkolioProject {
  id: number;
  name: string;
  description: string | null;
}

interface UkolioStatus { id: number; name: string; type: string; position: number; }

interface UkolioTaskCreateInput {
  projectId: number;
  name: string;
  statusName?: string;
  priorityId?: number;
  description?: string;
}

interface UkolioTaskFilters {
  limit?: number;
  offset?: number;
  statusIds?: number[];
  onlyActive?: boolean;
  search?: string;
  includeArchived?: boolean;
}

interface UkolioHttpResponse { status: number; headers: Record<string, string>; text: string; }

interface UkolioFetchOptions {
  method?: string;
  headers?: Record<string, string>;
  body?: string;
}

interface UkolioTaskUpdateInput {
  name?: string;
  description?: string;
  statusName?: string;
  statusId?: number;
  priorityId?: number;
  priorityName?: string;
  dueDate?: string;
}

interface UkolioTasksApi {
  list(filters?: UkolioTaskFilters): UkolioTask[];
  get(idOrCode: number | string): UkolioTask | null;
  create(input: UkolioTaskCreateInput): UkolioTask;
  move(id: number, statusName: string): UkolioTask;
  update(id: number | string, input: UkolioTaskUpdateInput): UkolioTask;
  delete(id: number | string): { id: number; deleted: true };
  setTags(id: number | string, tagIds: number[]): UkolioTask;
  addComment(id: number, body: string): { id: number; body: string };
}

interface UkolioProjectsApi { list(): UkolioProject[]; }

interface UkolioVarsApi {
  get(key: string): string | null;
  set(key: string, value: string, opts?: { secret?: boolean }): void;
}

interface UkolioContext {
  triggerType: 'Manual' | 'Scheduled' | 'Event';
  event: Record<string, unknown> | null;
  scheduledAt: string | null;
}

interface Ukolio {
  tasks: UkolioTasksApi;
  projects: UkolioProjectsApi;
  vars: UkolioVarsApi;
  workflow(projectId: number): { statuses: UkolioStatus[] };
  log(...args: unknown[]): void;
  fetch(url: string, opts?: UkolioFetchOptions): UkolioHttpResponse;
  context: UkolioContext;
}

declare const ukolio: Ukolio;
`;
