// Single source of truth for the `kytario` script host API: the TypeScript
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

export const KYTARIO_API_GROUPS: readonly ApiGroup[] = [
    {
        name: 'tasks',
        entries: [
            {
                signature: 'kytario.tasks.list(filters?)',
                description: 'List tasks in the workspace. Filters: limit, offset, statusIds, onlyActive, search, includeArchived.',
                returns: 'Task[]',
                snippet: 'const tasks = kytario.tasks.list({ onlyActive: true, limit: 50 });',
            },
            {
                signature: 'kytario.tasks.get(idOrCode)',
                description: 'Fetch a single task by numeric id or its code (e.g. "PRJ-12").',
                returns: 'Task | null',
                snippet: 'const task = kytario.tasks.get("PRJ-12");',
            },
            {
                signature: 'kytario.tasks.create(input)',
                description: 'Create a task. Requires projectId and name; optional statusName, priorityId, description.',
                returns: 'Task',
                snippet: 'const task = kytario.tasks.create({ projectId: 1, name: "New task" });',
            },
            {
                signature: 'kytario.tasks.move(id, statusName)',
                description: 'Move a task to another status by status name within its project workflow.',
                returns: 'Task',
                snippet: 'kytario.tasks.move(task.id, "In Progress");',
            },
            {
                signature: 'kytario.tasks.update(id, input)',
                description: 'Update a task; omitted fields keep their value. Accepts name, description, priorityId/Name, statusId/Name, dueDate.',
                returns: 'Task',
                snippet: 'kytario.tasks.update(task.id, { description: "Updated by script" });',
            },
            {
                signature: 'kytario.tasks.delete(id)',
                description: 'Delete a task. Subtasks are orphaned (kept as top-level), not deleted.',
                returns: '{ id, deleted }',
                snippet: 'kytario.tasks.delete(task.id);',
            },
            {
                signature: 'kytario.tasks.setTags(id, tagIds)',
                description: 'Replace the full tag set on a task with the given workspace tag ids.',
                returns: 'Task',
                snippet: 'kytario.tasks.setTags(task.id, [1, 2]);',
            },
            {
                signature: 'kytario.tasks.addComment(id, body)',
                description: 'Append a markdown comment to a task.',
                returns: '{ id, body }',
                snippet: 'kytario.tasks.addComment(task.id, "Automated note");',
            },
        ],
    },
    {
        name: 'projects',
        entries: [
            {
                signature: 'kytario.projects.list()',
                description: 'List every project in the workspace.',
                returns: 'Project[]',
                snippet: 'const projects = kytario.projects.list();',
            },
            {
                signature: 'kytario.workflow(projectId)',
                description: 'Get a project workflow with its ordered statuses.',
                returns: '{ statuses }',
                snippet: 'const { statuses } = kytario.workflow(1);',
            },
        ],
    },
    {
        name: 'vars',
        entries: [
            {
                signature: 'kytario.vars.get(key)',
                description: 'Read a workspace variable. Secrets are decrypted transparently and redacted from logs.',
                returns: 'string | null',
                snippet: 'const webhook = kytario.vars.get("SLACK_WEBHOOK_URL");',
            },
            {
                signature: 'kytario.vars.set(key, value, opts?)',
                description: 'Create or update a workspace variable. Pass { secret: true } to encrypt at rest.',
                returns: 'void',
                snippet: 'kytario.vars.set("LAST_RUN", new Date().toISOString());',
            },
        ],
    },
    {
        name: 'runtime',
        entries: [
            {
                signature: 'kytario.log(...args)',
                description: 'Write a line to the run log. Multiple arguments are joined by a space.',
                returns: 'void',
                snippet: 'kytario.log("Processed", tasks.length, "tasks");',
            },
            {
                signature: 'kytario.fetch(url, opts?)',
                description: 'HTTP request from the sandbox (max 20 per run). Returns status, headers and text.',
                returns: '{ status, headers, text }',
                snippet: 'const res = kytario.fetch("https://example.com", { method: "GET" });',
            },
            {
                signature: 'kytario.context',
                description: 'Run context: triggerType, the event payload (Event triggers) and scheduledAt (Scheduled).',
                returns: '{ triggerType, event, scheduledAt }',
                snippet: 'const trigger = kytario.context.triggerType;',
            },
        ],
    },
];

/** TypeScript declaration loaded into Monaco so `kytario.*` gets autocomplete + hovers. */
export const KYTARIO_DTS = `
interface KytarioTask {
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

interface KytarioProject {
  id: number;
  name: string;
  description: string | null;
}

interface KytarioStatus { id: number; name: string; type: string; position: number; }

interface KytarioTaskCreateInput {
  projectId: number;
  name: string;
  statusName?: string;
  priorityId?: number;
  description?: string;
}

interface KytarioTaskFilters {
  limit?: number;
  offset?: number;
  statusIds?: number[];
  onlyActive?: boolean;
  search?: string;
  includeArchived?: boolean;
}

interface KytarioHttpResponse { status: number; headers: Record<string, string>; text: string; }

interface KytarioFetchOptions {
  method?: string;
  headers?: Record<string, string>;
  body?: string;
}

interface KytarioTaskUpdateInput {
  name?: string;
  description?: string;
  statusName?: string;
  statusId?: number;
  priorityId?: number;
  priorityName?: string;
  dueDate?: string;
}

interface KytarioTasksApi {
  list(filters?: KytarioTaskFilters): KytarioTask[];
  get(idOrCode: number | string): KytarioTask | null;
  create(input: KytarioTaskCreateInput): KytarioTask;
  move(id: number, statusName: string): KytarioTask;
  update(id: number | string, input: KytarioTaskUpdateInput): KytarioTask;
  delete(id: number | string): { id: number; deleted: true };
  setTags(id: number | string, tagIds: number[]): KytarioTask;
  addComment(id: number, body: string): { id: number; body: string };
}

interface KytarioProjectsApi { list(): KytarioProject[]; }

interface KytarioVarsApi {
  get(key: string): string | null;
  set(key: string, value: string, opts?: { secret?: boolean }): void;
}

interface KytarioContext {
  triggerType: 'Manual' | 'Scheduled' | 'Event';
  event: Record<string, unknown> | null;
  scheduledAt: string | null;
}

interface Kytario {
  tasks: KytarioTasksApi;
  projects: KytarioProjectsApi;
  vars: KytarioVarsApi;
  workflow(projectId: number): { statuses: KytarioStatus[] };
  log(...args: unknown[]): void;
  fetch(url: string, opts?: KytarioFetchOptions): KytarioHttpResponse;
  context: KytarioContext;
}

declare const kytario: Kytario;
`;
