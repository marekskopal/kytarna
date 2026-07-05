// ============================================================
// Ukolio Design System — 07 · Scripts (automation)
// Self-contained: merges its own icons over the DS Icon set,
// reuses window.TopBar / window.Mark, exports only *Board comps.
// ============================================================
(function () {
const { useState, useRef, useEffect } = React;

// ---- icons: DS set + script-specific extras (no global clobber) ----
const SIcon = Object.assign({}, window.Icon, {
  Right:    (p) => <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M6 4l4 4-4 4"/></svg>,
  Back:     (p) => <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M13 8H3M7 4L3 8l4 4"/></svg>,
  Play:     (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor" {...p}><path d="M4 3.2v9.6a.6.6 0 0 0 .92.5l7.2-4.8a.6.6 0 0 0 0-1l-7.2-4.8A.6.6 0 0 0 4 3.2z"/></svg>,
  Code:     (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M5.5 5L2.5 8l3 3M10.5 5l3 3-3 3M9 3l-2 10"/></svg>,
  Clock:    (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><circle cx="8" cy="8" r="5.5"/><path d="M8 5v3l2 1.4"/></svg>,
  Bolt:     (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M9 1.5L3.5 9H8l-1 5.5L12.5 7H8z"/></svg>,
  Hand:     (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.3" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M5 7V3.6a1 1 0 0 1 2 0V7m0 0V2.8a1 1 0 0 1 2 0V7m0 0V3.6a1 1 0 0 1 2 0V8.5c0 2.6-1.4 4.5-4 4.5-1.6 0-2.6-.6-3.4-1.8L4 9.4a1 1 0 0 1 1.6-1.2L6 8.6"/></svg>,
  Terminal: (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><rect x="2" y="3" width="12" height="10" rx="1.5"/><path d="M4.8 6.5L6.8 8l-2 1.5M8.5 9.8h2.5"/></svg>,
  Book:     (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M8 4C7 3 5.5 2.7 3.5 2.7A1 1 0 0 0 2.5 3.7v8a1 1 0 0 0 1 1c2 0 3.5.3 4.5 1.3M8 4c1-1 2.5-1.3 4.5-1.3a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1c-2 0-3.5.3-4.5 1.3M8 4v10"/></svg>,
  Key:      (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><circle cx="6" cy="6" r="3"/><path d="M8.2 8.2L13 13M11 11l1.4-1.4M9.6 9.6L11 11"/></svg>,
  Lock:     (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><rect x="3.5" y="7" width="9" height="6.5" rx="1.3"/><path d="M5.5 7V5.2a2.5 2.5 0 0 1 5 0V7"/></svg>,
  Warn:     (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M8 2.5L14.5 13.5h-13z"/><path d="M8 6.5v3.2M8 11.6v.2"/></svg>,
  PanelR:   (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><rect x="2.5" y="3" width="11" height="10" rx="1.5"/><path d="M10 3v10"/></svg>,
  Insert:   (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M8 4v8M4 8h8"/></svg>,
});

// ---- highlighter ------------------------------------------
const KW = new Set('const let var function return if else for of in while do break continue await async new try catch finally throw typeof instanceof delete void class extends this switch case default export import from'.split(' '));
const LIT = new Set('true false null undefined NaN Infinity'.split(' '));
const BLT = new Set('Date Math JSON Object Array String Number Boolean Error Promise console parseInt parseFloat isNaN'.split(' '));
const MEM = new Set('tasks projects vars workflow log fetch context list get create move addComment set status headers text triggerType event scheduledAt'.split(' '));
const esc = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
function hlLine(code) {
  if (code === '') return '&nbsp;';
  const re = /(\/\/.*$)|(`(?:\\.|[^`\\])*`|'(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*")|(\b\d[\d_]*(?:\.\d+)?\b)|([A-Za-z_$][\w$]*)/g;
  let out = '', last = 0, m;
  while ((m = re.exec(code)) !== null) {
    out += esc(code.slice(last, m.index)); last = re.lastIndex;
    if (m[1]) out += `<span class="tk-com">${esc(m[1])}</span>`;
    else if (m[2]) out += `<span class="tk-str">${esc(m[2])}</span>`;
    else if (m[3]) out += `<span class="tk-num">${esc(m[3])}</span>`;
    else {
      const w = m[4], dot = /\.\s*$/.test(code.slice(0, m.index));
      let c = null;
      if (w === 'ukolio') c = 'tk-host';
      else if (!dot && KW.has(w)) c = 'tk-kw';
      else if (!dot && LIT.has(w)) c = 'tk-lit';
      else if (!dot && BLT.has(w)) c = 'tk-builtin';
      else if (dot && MEM.has(w)) c = 'tk-method';
      out += c ? `<span class="${c}">${esc(w)}</span>` : esc(w);
    }
  }
  out += esc(code.slice(last));
  return out;
}

const CAPS = { timeMs: 5000, memMb: 64, http: 20, taskApi: 200 };
const API_REF = [
  { group: 'tasks', items: [
    { sig: 'ukolio.tasks.list(filters?)', ret: 'Task[]', desc: 'Tasks in the workspace. filters: { search, statusIds, onlyActive, limit, offset }.', snip: 'const tasks = ukolio.tasks.list({ onlyActive: true });' },
    { sig: 'ukolio.tasks.get(id)', ret: 'Task | null', desc: 'Look up by task code (MP-3) or numeric id.', snip: 'const task = ukolio.tasks.get("MP-12");' },
    { sig: 'ukolio.tasks.create(input)', ret: 'Task', desc: '{ projectId, name, description?, priorityName?, statusName?, dueDate? }.', snip: 'ukolio.tasks.create({ projectId: 1, name: "Follow up" });' },
    { sig: 'ukolio.tasks.move(id, statusName)', ret: 'Task', desc: 'Move a task to a status by name.', snip: 'ukolio.tasks.move("MP-12", "Done");' },
    { sig: 'ukolio.tasks.addComment(id, body)', ret: '{ id, body }', desc: 'Add a Markdown comment (tagged Agent).', snip: 'ukolio.tasks.addComment("MP-12", "Triaged.");' },
  ]},
  { group: 'projects', items: [
    { sig: 'ukolio.projects.list()', ret: 'Project[]', desc: 'All projects in the workspace.', snip: 'const projects = ukolio.projects.list();' },
    { sig: 'ukolio.workflow(projectId)', ret: '{ statuses }', desc: 'Workflow + ordered statuses for a project.', snip: 'const wf = ukolio.workflow(1);' },
  ]},
  { group: 'vars', items: [
    { sig: 'ukolio.vars.get(key)', ret: 'string | null', desc: 'Read a workspace variable. Secrets decrypt on read.', snip: 'const url = ukolio.vars.get("SLACK_WEBHOOK_URL");' },
    { sig: 'ukolio.vars.set(key, value, opts?)', ret: 'void', desc: 'Write a variable. opts { secret: true } encrypts at rest.', snip: 'ukolio.vars.set("LAST_RUN", today);' },
  ]},
  { group: 'runtime', items: [
    { sig: 'ukolio.log(...args)', ret: 'void', desc: 'Append a line to the run log shown below.', snip: 'ukolio.log("done", count);' },
    { sig: 'ukolio.fetch(url, opts?)', ret: '{ status, headers, text }', desc: 'http(s) only · 10s timeout · 5 MB cap · 20 calls/run.', snip: 'const res = ukolio.fetch(url, { method: "POST", body });' },
    { sig: 'ukolio.context', ret: '{ triggerType, event, scheduledAt }', desc: 'Why this run fired; event payload for Event triggers.', snip: 'if (ukolio.context.triggerType === "Event") { }' },
  ]},
];

const DIGEST_SRC =
`// Weekly stale-task digest → Slack
// Trigger: Scheduled · 0 9 * * 1  (every Monday 09:00)

const webhook = ukolio.vars.get("SLACK_WEBHOOK_URL");
const open = ukolio.tasks.list({ onlyActive: true, limit: 200 });

const cutoff = Date.now() - 14 * 24 * 60 * 60 * 1000;
const stale = open.filter((t) => new Date(t.createdAt).getTime() < cutoff);

ukolio.log(\`Scanned \${open.length} open tasks · \${stale.length} stale\`);

if (stale.length > 0 && webhook) {
  const lines = stale.map((t) => \`• \${t.code} — \${t.name}\`).join("\\n");
  const res = ukolio.fetch(webhook, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ text: \`*\${stale.length} stale tasks*\\n\${lines}\` }),
  });
  ukolio.log(\`Slack webhook → \${res.status}\`);
} else {
  ukolio.log("Nothing to report.");
}
`;

const SCRIPTS_D = [
  { id: 7, name: 'Weekly stale-task digest', active: true,  trigger: { type: 'Scheduled', cron: '0 9 * * 1', events: [] }, lastRunAt: '2h ago',     lastStatus: 'Success', runCount: 41 },
  { id: 4, name: 'Auto-triage agent tasks',  active: true,  trigger: { type: 'Event', cron: '0 9 * * 1', events: ['TaskCreated'] }, lastRunAt: '11m ago', lastStatus: 'Success', runCount: 318 },
  { id: 9, name: 'Notify Slack on urgent',   active: true,  trigger: { type: 'Event', cron: '0 9 * * 1', events: ['TaskCreated', 'TaskMoved'] }, lastRunAt: '1h ago', lastStatus: 'Success', runCount: 87 },
  { id: 5, name: 'Sync urgent → Jira',       active: true,  trigger: { type: 'Manual', cron: '0 9 * * 1', events: [] }, lastRunAt: 'yesterday', lastStatus: 'Error',   runCount: 12 },
  { id: 3, name: 'Close stale review tasks', active: false, trigger: { type: 'Scheduled', cron: '0 2 * * *', events: [] }, lastRunAt: '6d ago',  lastStatus: 'Timeout', runCount: 6 },
];
const RUNS_D = [
  { id: 5012, trigger: 'Manual',    status: 'Success', started: 'Just now',      duration: 184,  http: 1, taskApi: 1, logs: 'Scanned 47 open tasks · 3 stale\nSlack webhook → 200', error: null },
  { id: 5009, trigger: 'Scheduled', status: 'Success', started: 'Today, 09:00',  duration: 203,  http: 1, taskApi: 1, logs: 'Scanned 44 open tasks · 2 stale\nSlack webhook → 200', error: null },
  { id: 4981, trigger: 'Scheduled', status: 'Success', started: 'Mon, 09:00',    duration: 176,  http: 1, taskApi: 1, logs: 'Scanned 51 open tasks · 5 stale\nSlack webhook → 200', error: null },
  { id: 4955, trigger: 'Manual',    status: 'Error',   started: 'Jun 16, 14:22', duration: 88,   http: 0, taskApi: 1, logs: 'Scanned 49 open tasks · 4 stale', error: 'ReferenceError: webook is not defined (script.js:14)' },
  { id: 4930, trigger: 'Scheduled', status: 'Timeout', started: 'Jun 09, 09:00', duration: 5000, http: 6, taskApi: 142, logs: 'Scanned 200 open tasks · 61 stale', error: 'Script exceeded the time limit (5000 ms).' },
];
const VARS_D = [
  { id: 1, key: 'SLACK_WEBHOOK_URL', value: 'https://hooks.slack.com/services/T0/B0/xY', secret: true },
  { id: 2, key: 'DIGEST_CHANNEL',    value: '#ops',                                       secret: false },
  { id: 3, key: 'JIRA_BASE_URL',     value: 'https://acme.atlassian.net',                 secret: false },
  { id: 4, key: 'JIRA_TOKEN',        value: 'atlassian-pat-9f3c1a77b22e',                 secret: true },
  { id: 5, key: 'STALE_DAYS',        value: '14',                                         secret: false },
];

const EVENT_TYPES = ['TaskCreated', 'TaskUpdated', 'TaskMoved', 'TaskDeleted', 'TaskCommentAdded'];
const CRON_PRESETS = [['0 9 * * 1', 'Mon 09:00'], ['0 9 * * *', 'Daily 09:00'], ['0 * * * *', 'Hourly'], ['*/15 * * * *', 'Every 15 min'], ['0 2 1 * *', 'Monthly']];
const cronHuman = (c) => ({ '0 9 * * 1': 'Every Monday at 09:00', '0 9 * * *': 'Every day at 09:00', '0 2 * * *': 'Every day at 02:00', '0 * * * *': 'Every hour, on the hour', '*/15 * * * *': 'Every 15 minutes', '0 2 1 * *': 'On the 1st of each month at 02:00' }[(c || '').trim()] || 'Custom schedule');
const parseErr = (e) => { const m = /script\.js:(\d+)/.exec(e || ''); return m ? Number(m[1]) : null; };
const SC = { Success: { fg: 'var(--uk-success)', soft: 'var(--uk-success-soft)', bd: 'var(--uk-success-border)' }, Error: { fg: 'var(--uk-danger)', soft: 'var(--uk-danger-soft)', bd: 'var(--uk-danger-border)' }, Timeout: { fg: 'var(--uk-warn)', soft: 'var(--uk-warn-soft)', bd: 'var(--uk-warn-border)' }, Running: { fg: 'var(--uk-info)', soft: 'var(--uk-info-soft)', bd: 'var(--uk-info-border)' } };
const runToResult = (r) => ({ status: r.status, duration: r.duration, http: r.http, taskApi: r.taskApi, logs: r.logs ? r.logs.split('\n') : [], error: r.error, errorLine: parseErr(r.error) });

// ---- small pieces -----------------------------------------
function Pill({ status, sm }) {
  const c = SC[status] || SC.Running;
  return <span className="uk-badge" style={{ background: c.soft, color: c.fg, border: `1px solid ${c.bd}`, height: sm ? 18 : 20, paddingLeft: 6, paddingRight: 8, gap: 5 }}>
    <span className={status === 'Running' ? 'ce-pulse' : ''} style={{ width: 6, height: 6, borderRadius: '50%', background: c.fg }}/>{status}
  </span>;
}
function TBadge({ trigger }) {
  const t = trigger.type, I = t === 'Scheduled' ? SIcon.Clock : t === 'Event' ? SIcon.Bolt : SIcon.Hand;
  const label = t === 'Scheduled' ? <>Scheduled <span className="uk-mono" style={{ fontSize: 11, opacity: .85 }}>{trigger.cron}</span></> : t === 'Event' ? <>Event <span style={{ opacity: .7 }}>· {trigger.events.length}</span></> : 'Manual';
  return <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12.5, color: 'var(--uk-fg-muted)', whiteSpace: 'nowrap' }}><I style={{ color: 'var(--uk-fg-subtle)', flexShrink: 0 }}/>{label}</span>;
}
function CapChip({ label, used, max, unit, warn }) {
  const pct = Math.min(100, (used / max) * 100), col = warn || pct > 80 ? 'var(--uk-warn)' : 'var(--uk-fg-muted)';
  return <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
    <span style={{ fontSize: 11, color: 'var(--uk-fg-subtle)' }}>{label}</span>
    <span className="uk-mono" style={{ fontSize: 11, color: col }}>{used}{unit || `/${max}`}</span>
    <span style={{ width: 34, height: 3, borderRadius: 2, background: 'var(--uk-surface-3)', overflow: 'hidden' }}><span style={{ display: 'block', width: `${pct}%`, height: '100%', background: col }}/></span>
  </div>;
}

function CodeEditor({ value, onChange, errorLine, dark }) {
  const ta = useRef(null), hl = useRef(null), gut = useRef(null);
  const lines = value.split('\n');
  const sync = () => { const t = ta.current; if (!t) return; if (hl.current) { hl.current.scrollTop = t.scrollTop; hl.current.scrollLeft = t.scrollLeft; } if (gut.current) gut.current.scrollTop = t.scrollTop; };
  useEffect(sync, [value]);
  const onKey = (e) => { if (e.key === 'Tab') { e.preventDefault(); const t = e.target, s = t.selectionStart, en = t.selectionEnd; onChange(value.slice(0, s) + '  ' + value.slice(en)); requestAnimationFrame(() => { t.selectionStart = t.selectionEnd = s + 2; }); } };
  return <div className={'ce' + (dark ? ' ce-dark' : '')}>
    <div className="ce-gutter" ref={gut}>{lines.map((_, i) => <div key={i} className={'ce-ln' + (errorLine === i + 1 ? ' ce-ln-err' : '')}>{i + 1}</div>)}</div>
    <div className="ce-main">
      <div className="ce-hl" ref={hl} aria-hidden="true">{lines.map((ln, i) => <div key={i} className={'ce-row' + (errorLine === i + 1 ? ' ce-row-err' : '')} dangerouslySetInnerHTML={{ __html: hlLine(ln) }}/>)}</div>
      <textarea ref={ta} className="ce-ta" value={value} spellCheck="false" wrap="off" autoCapitalize="off" autoCorrect="off" onChange={(e) => onChange(e.target.value)} onScroll={sync} onKeyDown={onKey}/>
    </div>
  </div>;
}

function ApiPanel({ onInsert }) {
  const [open, setOpen] = useState('ukolio.tasks.list(filters?)');
  return <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
    <div style={{ flex: 1, overflow: 'auto', padding: '6px 0' }}>
      {API_REF.map((g) => <div key={g.group} style={{ padding: '6px 0' }}>
        <div className="uk-overline" style={{ padding: '4px 14px' }}>{g.group}</div>
        {g.items.map((it) => { const o = open === it.sig; return <div key={it.sig} style={{ borderTop: '1px solid var(--uk-border)' }}>
          <button className="api-row" onClick={() => setOpen(o ? null : it.sig)}>
            {o ? <SIcon.Down style={{ color: 'var(--uk-fg-subtle)', flexShrink: 0 }}/> : <SIcon.Right style={{ color: 'var(--uk-fg-subtle)', flexShrink: 0 }}/>}
            <code className="uk-mono api-sig" dangerouslySetInnerHTML={{ __html: hlLine(it.sig) }}/>
          </button>
          {o && <div style={{ padding: '0 14px 12px 32px' }}>
            <div style={{ fontSize: 12, color: 'var(--uk-fg-muted)', lineHeight: 1.5, marginBottom: 8 }}>{it.desc}</div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}><span className="uk-badge" style={{ background: 'var(--uk-accent-soft)', color: 'var(--uk-accent)' }}>returns</span><code className="uk-mono" style={{ fontSize: 11.5, color: 'var(--uk-fg-muted)' }}>{it.ret}</code></div>
            <button className="uk-btn uk-btn--secondary uk-btn--xs" onClick={() => onInsert(it.snip)}><SIcon.Insert/>Insert snippet</button>
          </div>}
        </div>; })}
      </div>)}
    </div>
    <div style={{ padding: '10px 14px', borderTop: '1px solid var(--uk-border)', display: 'flex', gap: 8, background: 'var(--uk-surface-2)' }}>
      <SIcon.Bolt style={{ color: 'var(--uk-fg-subtle)', marginTop: 1, flexShrink: 0 }}/>
      <div style={{ fontSize: 11, color: 'var(--uk-fg-subtle)', lineHeight: 1.5 }}>Per run: <strong style={{ color: 'var(--uk-fg-muted)' }}>{CAPS.timeMs / 1000}s CPU</strong> · {CAPS.memMb} MB · {CAPS.http} fetch · {CAPS.taskApi} task-API calls. No filesystem.</div>
    </div>
  </div>;
}

function TriggerPanel({ trigger, onChange }) {
  const set = (p) => onChange({ ...trigger, ...p });
  const Seg = ({ type, icon: I, label }) => <button onClick={() => set({ type })} className="uk-btn uk-btn--sm" style={{ flex: 1, borderRadius: 0, height: 30, background: trigger.type === type ? 'var(--uk-surface)' : 'transparent', color: trigger.type === type ? 'var(--uk-fg)' : 'var(--uk-fg-muted)', boxShadow: trigger.type === type ? 'var(--uk-shadow-xs)' : 'none', fontWeight: trigger.type === type ? 500 : 400, gap: 5 }}><I/>{label}</button>;
  const tog = (ev) => set({ events: trigger.events.includes(ev) ? trigger.events.filter((e) => e !== ev) : [...trigger.events, ev] });
  return <div style={{ display: 'flex', flexDirection: 'column', height: '100%' }}>
    <div style={{ flex: 1, overflow: 'auto', padding: 14, display: 'flex', flexDirection: 'column', gap: 14 }}>
      <div style={{ display: 'flex', border: '1px solid var(--uk-border-strong)', borderRadius: 6, overflow: 'hidden', background: 'var(--uk-surface-2)' }}>
        <Seg type="Manual" icon={SIcon.Hand} label="Manual"/><span style={{ width: 1, background: 'var(--uk-border)' }}/>
        <Seg type="Scheduled" icon={SIcon.Clock} label="Schedule"/><span style={{ width: 1, background: 'var(--uk-border)' }}/>
        <Seg type="Event" icon={SIcon.Bolt} label="Event"/>
      </div>
      {trigger.type === 'Manual' && <div style={{ fontSize: 12.5, color: 'var(--uk-fg-muted)', lineHeight: 1.55, padding: '4px 2px' }}>Runs only when you press <strong style={{ color: 'var(--uk-fg)' }}>Run</strong> here, from the list, or via the API. No automatic schedule.</div>}
      {trigger.type === 'Scheduled' && <div className="uk-field">
        <label className="uk-label">Cron expression</label>
        <input className="uk-input uk-mono" value={trigger.cron} onChange={(e) => set({ cron: e.target.value })} style={{ fontSize: 12.5 }}/>
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5, fontSize: 11.5, color: 'var(--uk-accent)', marginTop: 2 }}><SIcon.Clock style={{ width: 12, height: 12 }}/>{cronHuman(trigger.cron)} · UTC</span>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5, marginTop: 8 }}>{CRON_PRESETS.map(([c, l]) => <button key={c} className="uk-btn uk-btn--xs" onClick={() => set({ cron: c })} style={{ border: '1px solid var(--uk-border)', background: trigger.cron === c ? 'var(--uk-accent-soft)' : 'var(--uk-surface)', color: trigger.cron === c ? 'var(--uk-accent)' : 'var(--uk-fg-muted)' }}>{l}</button>)}</div>
      </div>}
      {trigger.type === 'Event' && <div>
        <div className="uk-label" style={{ marginBottom: 8 }}>Run when… <span style={{ color: 'var(--uk-fg-subtle)', fontWeight: 400 }}>({trigger.events.length} selected)</span></div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>{EVENT_TYPES.map((ev) => { const on = trigger.events.includes(ev); return <label key={ev} className="uk-row uk-row--interactive" style={{ borderRadius: 6, padding: '7px 8px', gap: 9, minHeight: 0, background: on ? 'var(--uk-accent-soft)' : undefined }}><span className="uk-check"><input type="checkbox" checked={on} onChange={() => tog(ev)}/><span className="uk-check-box"/></span><code className="uk-mono" style={{ fontSize: 12, color: on ? 'var(--uk-accent)' : 'var(--uk-fg)' }}>{ev}</code></label>; })}</div>
        <div style={{ display: 'flex', gap: 7, marginTop: 10, fontSize: 11, color: 'var(--uk-fg-subtle)', lineHeight: 1.5 }}><SIcon.Sparkle style={{ marginTop: 1, flexShrink: 0 }}/>The event payload is available as <code className="uk-mono" style={{ background: 'var(--uk-surface-2)', padding: '0 3px', borderRadius: 3 }}>ukolio.context.event</code>.</div>
      </div>}
    </div>
  </div>;
}

function RightPanel({ tab, setTab, trigger, onTrigger, onInsert, onClose }) {
  const T = ({ id, icon: I, label }) => <button onClick={() => setTab(id)} className="uk-btn uk-btn--ghost uk-btn--sm" style={{ height: 30, borderRadius: 5, gap: 5, padding: '0 9px', background: tab === id ? 'var(--uk-surface-2)' : 'transparent', color: tab === id ? 'var(--uk-fg)' : 'var(--uk-fg-muted)', fontWeight: tab === id ? 500 : 400 }}><I/>{label}</button>;
  return <div style={{ width: 320, flexShrink: 0, borderLeft: '1px solid var(--uk-border)', background: 'var(--uk-surface)', display: 'flex', flexDirection: 'column', minHeight: 0 }}>
    <div style={{ height: 42, flexShrink: 0, borderBottom: '1px solid var(--uk-border)', display: 'flex', alignItems: 'center', gap: 4, padding: '0 8px 0 10px' }}>
      <T id="api" icon={SIcon.Book} label="API"/><T id="trigger" icon={SIcon.Bolt} label="Trigger"/>
      <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm" style={{ marginLeft: 'auto' }} onClick={onClose}><SIcon.PanelR/></button>
    </div>
    <div style={{ flex: 1, minHeight: 0 }}>{tab === 'api' ? <ApiPanel onInsert={onInsert}/> : <TriggerPanel trigger={trigger} onChange={onTrigger}/>}</div>
  </div>;
}

function Output({ running, result }) {
  if (running) return <div style={{ padding: '12px 16px', display: 'flex', alignItems: 'center', gap: 9, fontSize: 12.5, color: 'var(--uk-fg-muted)' }}><span className="ce-pulse" style={{ width: 7, height: 7, borderRadius: '50%', background: 'var(--uk-info)' }}/>Running in sandbox…</div>;
  if (!result) return <div style={{ padding: 16, fontSize: 12.5, color: 'var(--uk-fg-subtle)' }}>Press <strong style={{ color: 'var(--uk-fg-muted)' }}>Run</strong> to execute and capture output.</div>;
  return <div style={{ padding: '10px 0' }}>
    <div style={{ display: 'flex', alignItems: 'center', gap: 9, padding: '0 16px 8px' }}><Pill status={result.status}/><span style={{ fontSize: 12, color: 'var(--uk-fg-subtle)' }}>finished in <span className="uk-mono">{result.duration} ms</span></span></div>
    <pre className="run-log">{result.logs.map((l, i) => <div key={i} className="run-log-line"><span className="run-log-gut">{i + 1}</span><span>{l}</span></div>)}{result.error && <div className="run-log-line run-log-err"><span className="run-log-gut">!</span><span>{result.error}</span></div>}</pre>
  </div>;
}
function Problems({ result }) {
  if (!result || (result.status !== 'Error' && result.status !== 'Timeout')) return <div style={{ padding: 16, display: 'flex', alignItems: 'center', gap: 8, fontSize: 12.5, color: 'var(--uk-fg-subtle)' }}><SIcon.Check style={{ color: 'var(--uk-success)' }}/>No problems — last run completed cleanly.</div>;
  const c = SC[result.status];
  return <div style={{ padding: 12 }}><div style={{ display: 'flex', gap: 10, padding: 12, border: `1px solid ${c.bd}`, background: c.soft, borderRadius: 7 }}><SIcon.Warn style={{ color: c.fg, marginTop: 1, flexShrink: 0 }}/><div style={{ flex: 1 }}><div style={{ fontSize: 12.5, fontWeight: 600, color: 'var(--uk-fg)' }}>{result.status === 'Timeout' ? 'Execution timed out' : 'Uncaught error'}</div><div className="uk-mono" style={{ fontSize: 12, color: c.fg, marginTop: 3, lineHeight: 1.5 }}>{result.error}</div>{result.errorLine && <button className="uk-btn uk-btn--secondary uk-btn--xs" style={{ marginTop: 8 }}><SIcon.Code/>Go to line {result.errorLine}</button>}</div></div></div>;
}
function RunHistory({ runs, onSelect, selectedId }) {
  return <table className="uk-table" style={{ fontSize: 12.5 }}><thead><tr><th style={{ width: 120 }}>Status</th><th style={{ width: 110 }}>Trigger</th><th>Started</th><th style={{ width: 90 }}>Duration</th><th style={{ width: 130 }}>Calls</th><th style={{ width: 28 }}></th></tr></thead><tbody>{runs.map((r) => <tr key={r.id} style={{ cursor: 'pointer', background: selectedId === r.id ? 'var(--uk-accent-soft)' : undefined }} onClick={() => onSelect(r)}><td><Pill status={r.status} sm/></td><td style={{ color: 'var(--uk-fg-muted)' }}>{r.trigger}</td><td style={{ color: 'var(--uk-fg-muted)' }}>{r.started}</td><td className="uk-mono" style={{ color: r.status === 'Timeout' ? 'var(--uk-warn)' : 'var(--uk-fg-muted)', fontSize: 11.5 }}>{r.duration} ms</td><td className="uk-mono" style={{ fontSize: 11, color: 'var(--uk-fg-subtle)' }}>{r.http} fetch · {r.taskApi} api</td><td><SIcon.Right style={{ color: 'var(--uk-fg-faint)' }}/></td></tr>)}</tbody></table>;
}

function WsTabs() {
  const tabs = ['General', 'Members', 'MCP & agents', 'Custom fields', 'Tags', 'Scripts', 'Billing'];
  return <div style={{ display: 'flex', borderBottom: '1px solid var(--uk-border)' }}>{tabs.map((t) => <button key={t} className="uk-btn uk-btn--ghost uk-btn--sm" style={{ height: 36, borderRadius: 0, padding: '0 12px', borderBottom: t === 'Scripts' ? '2px solid var(--uk-fg)' : '2px solid transparent', color: t === 'Scripts' ? 'var(--uk-fg)' : 'var(--uk-fg-muted)', fontWeight: t === 'Scripts' ? 500 : 400, marginBottom: -1 }}>{t}</button>)}</div>;
}

// ============================================================
// BOARDS
// ============================================================
function ScriptsListBoard() {
  const [scripts, setScripts] = useState(SCRIPTS_D);
  const toggle = (id) => setScripts((p) => p.map((x) => x.id === id ? { ...x, active: !x.active } : x));
  return <div className="uk" style={{ height: '100%', background: 'var(--uk-bg)', display: 'flex', flexDirection: 'column' }}>
    <window.TopBar active="workspaces"/>
    <div style={{ flex: 1, overflow: 'auto' }}><div style={{ maxWidth: 1040, margin: '0 auto', width: '100%', padding: '24px 24px 40px' }}>
      <div style={{ marginBottom: 18 }}><h1 className="uk-h2">mskopal</h1><p className="uk-caption">Workspace · 4 members · 5 projects · 128 tasks</p></div>
      <WsTabs/>
      <div style={{ display: 'flex', alignItems: 'flex-start', margin: '20px 0 14px' }}>
        <div><h2 className="uk-h3" style={{ marginBottom: 3 }}>Scripts</h2><p className="uk-caption" style={{ maxWidth: 520 }}>Automate your workspace with sandboxed JavaScript — on a schedule, on events, or on demand.</p></div>
        <div style={{ marginLeft: 'auto', display: 'flex', gap: 8 }}><button className="uk-btn uk-btn--secondary uk-btn--sm"><SIcon.Key/>Variables</button><button className="uk-btn uk-btn--primary uk-btn--sm"><SIcon.Plus/>New script</button></div>
      </div>
      <div className="uk-alert" style={{ marginBottom: 16, alignItems: 'flex-start', background: 'var(--uk-surface-2)', borderColor: 'var(--uk-border)' }}>
        <SIcon.Code style={{ color: 'var(--uk-accent)', marginTop: 1 }}/>
        <div style={{ fontSize: 12.5, color: 'var(--uk-fg-muted)', lineHeight: 1.55 }}>Scripts run as <strong style={{ color: 'var(--uk-fg)' }}>you</strong> inside a hardened V8 sandbox — {CAPS.timeMs / 1000}s CPU, {CAPS.memMb} MB, no filesystem. Calls to the <code className="uk-mono" style={{ background: 'var(--uk-surface)', padding: '0 4px', borderRadius: 3 }}>ukolio</code> API are scoped to this workspace. Admins only.</div>
      </div>
      <div className="uk-card" style={{ overflow: 'hidden' }}><table className="uk-table">
        <thead><tr><th>Script</th><th style={{ width: 200 }}>Trigger</th><th style={{ width: 90 }}>Active</th><th style={{ width: 150 }}>Last run</th><th style={{ width: 70 }}>Runs</th><th style={{ width: 110 }}></th></tr></thead>
        <tbody>{scripts.map((s) => <tr key={s.id} style={{ cursor: 'pointer' }}>
          <td><div style={{ display: 'flex', alignItems: 'center', gap: 9 }}><span style={{ width: 26, height: 26, borderRadius: 6, background: 'var(--uk-surface-2)', border: '1px solid var(--uk-border)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', color: 'var(--uk-fg-subtle)', flexShrink: 0 }}><SIcon.Code/></span><span style={{ fontWeight: 500, color: 'var(--uk-fg)' }}>{s.name}</span></div></td>
          <td><TBadge trigger={s.trigger}/></td>
          <td onClick={(e) => { e.stopPropagation(); toggle(s.id); }}><span className={'uk-toggle' + (s.active ? ' uk-toggle--on' : '')}/></td>
          <td><div style={{ display: 'flex', alignItems: 'center', gap: 7 }}><Pill status={s.lastStatus} sm/><span style={{ fontSize: 11.5, color: 'var(--uk-fg-subtle)' }}>{s.lastRunAt}</span></div></td>
          <td className="uk-mono" style={{ color: 'var(--uk-fg-muted)', fontSize: 12 }}>{s.runCount}</td>
          <td><div style={{ display: 'flex', gap: 4, justifyContent: 'flex-end' }}><button className="uk-btn uk-btn--ghost uk-btn--xs"><SIcon.Play/></button><button className="uk-btn uk-btn--ghost uk-btn--xs">Edit</button><button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs"><SIcon.More/></button></div></td>
        </tr>)}</tbody>
      </table></div>
    </div></div>
  </div>;
}

function ScriptEditorBoard({ dark = false }) {
  const [name, setName] = useState('Weekly stale-task digest');
  const [active, setActive] = useState(true);
  const [source, setSource] = useState(DIGEST_SRC);
  const [trigger, setTrigger] = useState({ type: 'Scheduled', cron: '0 9 * * 1', events: [] });
  const [dirty, setDirty] = useState(false);
  const [showPanel, setShowPanel] = useState(true);
  const [rightTab, setRightTab] = useState('api');
  const [bottomTab, setBottomTab] = useState('output');
  const [running, setRunning] = useState(false);
  const [runs, setRuns] = useState(RUNS_D);
  const [result, setResult] = useState(runToResult(RUNS_D[0]));
  const [sel, setSel] = useState(RUNS_D[0].id);
  const timer = useRef(null);
  useEffect(() => () => clearTimeout(timer.current), []);
  const edit = (fn) => { fn(); setDirty(true); };
  const errorLine = result && result.status === 'Error' ? result.errorLine : null;
  const problems = result && (result.status === 'Error' || result.status === 'Timeout') ? 1 : 0;
  const run = () => {
    if (running || !source.trim()) return;
    setRunning(true); setBottomTab('output'); setResult(null); setSel(null); clearTimeout(timer.current);
    timer.current = setTimeout(() => { const r = { status: 'Success', duration: 191, http: 1, taskApi: 1, logs: ['Scanned 47 open tasks · 3 stale', 'Slack webhook → 200'], error: null, errorLine: null }; setResult(r); setRunning(false); setRuns((p) => [{ id: Date.now(), trigger: 'Manual', status: 'Success', started: 'Just now', duration: 191, http: 1, taskApi: 1, logs: r.logs.join('\n'), error: null }, ...p]); }, 850);
  };
  const insert = (s) => edit(() => setSource((v) => v.replace(/\n*$/, '') + '\n' + s + '\n'));
  const selRun = (r) => { setResult(runToResult(r)); setSel(r.id); setBottomTab('output'); };
  const bt = [['output', 'Output'], ['problems', `Problems${problems ? ` · ${problems}` : ''}`], ['runs', 'Run history']];
  return <div className={'uk' + (dark ? ' uk-dark' : '')} style={{ height: '100%', background: 'var(--uk-bg)', display: 'flex', flexDirection: 'column' }}>
    <window.TopBar active="workspaces"/>
    <div style={{ flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column' }}>
      <div style={{ flexShrink: 0, borderBottom: '1px solid var(--uk-border)', background: 'var(--uk-surface)', display: 'flex', alignItems: 'center', gap: 10, padding: '0 14px', height: 52 }}>
        <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm"><SIcon.Back/></button>
        <span className="uk-caption" style={{ color: 'var(--uk-fg-subtle)' }}>Scripts</span><SIcon.Right style={{ color: 'var(--uk-fg-faint)' }}/>
        <input value={name} onChange={(e) => edit(() => setName(e.target.value))} style={{ border: 'none', outline: 'none', background: 'transparent', fontSize: 15, fontWeight: 600, letterSpacing: '-0.01em', color: 'var(--uk-fg)', fontFamily: 'inherit', minWidth: 120, width: Math.max(120, name.length * 8.5) }}/>
        {dirty && <span className="uk-badge uk-badge--warn" style={{ height: 18 }}>Unsaved</span>}
        <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10 }}>
          <button onClick={() => { setRightTab('trigger'); setShowPanel(true); }} className="uk-btn uk-btn--ghost uk-btn--sm" style={{ gap: 7 }}><TBadge trigger={trigger}/></button>
          <label className="uk-check" style={{ gap: 7, fontSize: 12.5, color: 'var(--uk-fg-muted)' }} onClick={(e) => { e.preventDefault(); edit(() => setActive((a) => !a)); }}><span className={'uk-toggle' + (active ? ' uk-toggle--on' : '')}/>{active ? 'Active' : 'Inactive'}</label>
          <span style={{ width: 1, height: 20, background: 'var(--uk-border)' }}/>
          {!showPanel && <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm" onClick={() => setShowPanel(true)}><SIcon.PanelR/></button>}
          <button className="uk-btn uk-btn--secondary uk-btn--sm" onClick={run} disabled={running}><SIcon.Play/>{running ? 'Running…' : 'Run'}</button>
          <button className="uk-btn uk-btn--primary uk-btn--sm" onClick={() => setDirty(false)}>Save changes</button>
        </div>
      </div>
      <div style={{ flex: 1, minHeight: 0, display: 'flex' }}>
        <div style={{ flex: 1, minWidth: 0, display: 'flex', flexDirection: 'column' }}>
          <div style={{ flexShrink: 0, height: 34, borderBottom: '1px solid var(--uk-border)', background: 'var(--uk-bg)', display: 'flex', alignItems: 'stretch' }}>
            <div style={{ display: 'inline-flex', alignItems: 'center', gap: 7, padding: '0 14px', borderRight: '1px solid var(--uk-border)', background: 'var(--uk-surface)', fontSize: 12.5, color: 'var(--uk-fg)', fontWeight: 500 }}><SIcon.Code style={{ color: 'var(--uk-accent)' }}/><span className="uk-mono" style={{ fontSize: 12 }}>script.js</span></div>
            <div style={{ flex: 1 }}/>
            <div style={{ display: 'inline-flex', alignItems: 'center', gap: 10, padding: '0 14px', fontSize: 11, color: 'var(--uk-fg-subtle)', whiteSpace: 'nowrap' }}><span>JavaScript</span><span className="uk-mono">{source.split('\n').length} lines</span></div>
          </div>
          <div style={{ flex: 1, minHeight: 0 }}><CodeEditor value={source} onChange={(v) => edit(() => setSource(v))} errorLine={errorLine} dark={dark}/></div>
          <div style={{ flexShrink: 0, height: 216, borderTop: '1px solid var(--uk-border)', background: 'var(--uk-surface)', display: 'flex', flexDirection: 'column' }}>
            <div style={{ flexShrink: 0, height: 36, borderBottom: '1px solid var(--uk-border)', display: 'flex', alignItems: 'center', gap: 2, padding: '0 8px' }}>
              <SIcon.Terminal style={{ color: 'var(--uk-fg-subtle)', margin: '0 6px 0 4px' }}/>
              {bt.map(([id, label]) => <button key={id} onClick={() => setBottomTab(id)} className="uk-btn uk-btn--ghost uk-btn--sm" style={{ height: 26, borderRadius: 5, padding: '0 9px', background: bottomTab === id ? 'var(--uk-surface-2)' : 'transparent', color: bottomTab === id ? 'var(--uk-fg)' : 'var(--uk-fg-muted)', fontWeight: bottomTab === id ? 500 : 400 }}>{id === 'problems' && problems > 0 && <SIcon.Warn style={{ color: 'var(--uk-danger)', marginRight: 4 }}/>}{label}</button>)}
              <div style={{ flex: 1 }}/>
              {result && !running && bottomTab === 'output' && <div style={{ display: 'flex', alignItems: 'center', gap: 12, paddingRight: 6 }}><CapChip label="time" used={result.duration} max={CAPS.timeMs} unit=" ms" warn={result.status === 'Timeout'}/><CapChip label="fetch" used={result.http} max={CAPS.http}/><CapChip label="api" used={result.taskApi} max={CAPS.taskApi}/></div>}
            </div>
            <div style={{ flex: 1, minHeight: 0, overflow: 'auto' }}>{bottomTab === 'output' && <Output running={running} result={result}/>}{bottomTab === 'problems' && <Problems result={result}/>}{bottomTab === 'runs' && <RunHistory runs={runs} onSelect={selRun} selectedId={sel}/>}</div>
          </div>
        </div>
        {showPanel && <RightPanel tab={rightTab} setTab={setRightTab} trigger={trigger} onTrigger={(tg) => edit(() => setTrigger(tg))} onInsert={insert} onClose={() => setShowPanel(false)}/>}
      </div>
    </div>
  </div>;
}

function VariablesBoard() {
  const [vars, setVars] = useState(VARS_D);
  const [key, setKey] = useState(''); const [val, setVal] = useState(''); const [secret, setSecret] = useState(false);
  const add = () => { if (!key.trim() || !val.trim()) return; setVars((p) => [...p, { id: Date.now(), key: key.trim(), value: val.trim(), secret }]); setKey(''); setVal(''); setSecret(false); };
  return <div className="uk" style={{ height: '100%', background: 'var(--uk-bg)', display: 'flex', flexDirection: 'column' }}>
    <window.TopBar active="workspaces"/>
    <div style={{ flex: 1, overflow: 'auto' }}><div style={{ maxWidth: 880, margin: '0 auto', width: '100%', padding: '24px 24px 40px' }}>
      <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ padding: '0 8px', marginBottom: 12, color: 'var(--uk-fg-muted)' }}><SIcon.Back/>Back to scripts</button>
      <h1 className="uk-h2" style={{ marginBottom: 3 }}>Script variables</h1>
      <p className="uk-caption" style={{ maxWidth: 560, marginBottom: 18 }}>Workspace-scoped key/value store, read from scripts via <code className="uk-mono" style={{ background: 'var(--uk-surface-2)', padding: '0 4px', borderRadius: 3 }}>ukolio.vars.get(key)</code>. Secrets are encrypted at rest (AES-256-GCM) and redacted from run logs.</p>
      <div className="uk-card" style={{ padding: 14, marginBottom: 16 }}><div style={{ display: 'flex', gap: 10, alignItems: 'flex-end', flexWrap: 'wrap' }}>
        <div className="uk-field" style={{ flex: '1 1 200px' }}><label className="uk-label">Key</label><input className="uk-input uk-mono" placeholder="SLACK_WEBHOOK_URL" value={key} onChange={(e) => setKey(e.target.value)} style={{ fontSize: 12.5 }}/></div>
        <div className="uk-field" style={{ flex: '2 1 280px' }}><label className="uk-label">Value</label><input className="uk-input" type={secret ? 'password' : 'text'} placeholder="value" value={val} onChange={(e) => setVal(e.target.value)}/></div>
        <label className="uk-check" style={{ height: 30, gap: 7 }}><input type="checkbox" checked={secret} onChange={(e) => setSecret(e.target.checked)}/><span className="uk-check-box"/><span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}><SIcon.Lock style={{ color: 'var(--uk-fg-subtle)' }}/>Secret</span></label>
        <button className="uk-btn uk-btn--primary" onClick={add} style={{ height: 30 }}><SIcon.Plus/>Add</button>
      </div></div>
      <div className="uk-card" style={{ overflow: 'hidden' }}><table className="uk-table">
        <thead><tr><th style={{ width: 260 }}>Key</th><th>Value</th><th style={{ width: 90 }}>Type</th><th style={{ width: 90 }}></th></tr></thead>
        <tbody>{vars.map((v) => <tr key={v.id}>
          <td className="uk-mono" style={{ color: 'var(--uk-fg)', fontWeight: 500, fontSize: 12.5 }}>{v.key}</td>
          <td>{v.secret ? <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, color: 'var(--uk-fg-subtle)' }}><SIcon.Lock/><span className="uk-mono" style={{ letterSpacing: 1 }}>••••••••••</span></span> : <span className="uk-mono" style={{ fontSize: 12, color: 'var(--uk-fg-muted)' }}>{v.value}</span>}</td>
          <td>{v.secret ? <span className="uk-badge" style={{ background: 'var(--uk-ai-soft)', color: 'var(--uk-ai)' }}><SIcon.Lock/>Secret</span> : <span className="uk-badge uk-badge--outline">Plain</span>}</td>
          <td><div style={{ display: 'flex', gap: 4, justifyContent: 'flex-end' }}><button className="uk-btn uk-btn--ghost uk-btn--xs">Edit</button><button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" style={{ color: 'var(--uk-danger)' }} onClick={() => setVars((p) => p.filter((x) => x.id !== v.id))}><SIcon.Trash/></button></div></td>
        </tr>)}</tbody>
      </table></div>
    </div></div>
  </div>;
}

Object.assign(window, { ScriptsListBoard, ScriptEditorBoard, VariablesBoard });
})();
