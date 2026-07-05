// ============================================================
// Ukolio — Date-based task views: Calendar + Timeline (Gantt)
// Closes the Trello gap: visualize Task.dueDate (+ startDate)
// so humans can see what's coming. Frontend-only build.
// ============================================================

// ---------- Shared task dataset (workspace-wide) ----------
// dates are ISO; `start` drives Timeline bars, `due` drives Calendar.
const TV_TODAY = '2026-05-17';

const TV_PROJECTS = {
  'Backend rewrite': '#5e6ad2',
  'MCP onboarding':  '#a35c00',
  'Frontend polish': '#16794a',
  'v1.0 launch':     '#4a8fd6',
  'Documentation':   '#94a3a8',
};

const TV_PEOPLE = {
  MS: ['#fbe5d6', '#a35c00'],
  JK: ['#dbeaff', '#1e58b6'],
  EP: ['#dcefe2', '#16794a'],
};

const TV_TASKS = [
  { id: 'UKO-313', title: 'SSO via Google for invitation flow', proj: 'v1.0 launch',     prio: 'Low',    who: 'EP', status: 'Todo',   start: '2026-05-28', due: '2026-06-02' },
  { id: 'UKO-324', title: 'Public docs polish pass',            proj: 'MCP onboarding',  prio: 'Medium', who: 'AI', status: 'Todo',   start: '2026-06-01', due: '2026-06-05', agent: true },
  { id: 'UKO-310', title: 'Set up Mailpit in compose',          proj: 'Documentation',   prio: 'Low',    who: 'MS', status: 'Done',   start: '2026-05-07', due: '2026-05-10', done: true },
  { id: 'UKO-311', title: 'Ship i18n switcher (EN/CS)',         proj: 'Frontend polish', prio: 'Low',    who: 'EP', status: 'Done',   start: '2026-05-08', due: '2026-05-12', done: true },
  { id: 'UKO-314', title: 'Workspace transfer atomicity',       proj: 'Backend rewrite', prio: 'Medium', who: 'JK', status: 'Done',   start: '2026-05-10', due: '2026-05-14', done: true },
  { id: 'UKO-315', title: 'Audit log retention policy',         proj: 'Backend rewrite', prio: 'Urgent', who: 'MS', status: 'Doing',  start: '2026-05-08', due: '2026-05-18' },
  { id: 'UKO-317', title: 'Document MCP OAuth + PKCE flow',      proj: 'MCP onboarding',  prio: 'Medium', who: 'JK', status: 'Review', start: '2026-05-14', due: '2026-05-20' },
  { id: 'UKO-320', title: 'Refactor Workspace::transfer',       proj: 'Backend rewrite', prio: 'Medium', who: 'JK', status: 'Review', start: '2026-05-15', due: '2026-05-21' },
  { id: 'UKO-318', title: 'Migrate sessions to Redis',          proj: 'Backend rewrite', prio: 'High',   who: 'MS', status: 'Doing',  start: '2026-05-12', due: '2026-05-22' },
  { id: 'UKO-321', title: 'Add metrics for cache hit ratio',    proj: 'Backend rewrite', prio: 'Medium', who: 'AI', status: 'Doing',  start: '2026-05-19', due: '2026-05-23', agent: true },
  { id: 'UKO-312', title: 'Repository pattern for events',      proj: 'Backend rewrite', prio: 'Low',    who: 'JK', status: 'Review', start: '2026-05-20', due: '2026-05-27' },
  { id: 'UKO-323', title: 'Add bulk assign action to grid',     proj: 'Frontend polish', prio: 'Medium', who: 'JK', status: 'Todo',   start: '2026-05-24', due: '2026-05-28' },
  { id: 'UKO-316', title: 'Fix kanban drag jitter in Firefox',  proj: 'Frontend polish', prio: 'Low',    who: 'EP', status: 'Todo',   start: '2026-05-26', due: '2026-05-29' },
  { id: 'UKO-325', title: 'Workspace-scoped API tokens',        proj: 'MCP onboarding',  prio: 'High',   who: 'MS', status: 'Todo',   start: '2026-05-25', due: '2026-05-30', agent: true },
];

// Project milestones (target dates) — drawn as diamonds on Timeline
const TV_MILESTONES = [
  { proj: 'Backend rewrite', label: 'v0.4 cut',     date: '2026-05-23' },
  { proj: 'MCP onboarding',  label: 'Docs live',    date: '2026-06-02' },
  { proj: 'v1.0 launch',     label: 'Launch',       date: '2026-06-05' },
];

// ---------- date helpers (UTC, no tz drift) ----------
const tvDate   = (iso) => new Date(iso + 'T00:00:00Z');
const tvDays   = (a, b) => Math.round((tvDate(b) - tvDate(a)) / 86400000);
const tvKey    = (d) => d.toISOString().slice(0, 10);
const tvAddDay = (iso, n) => { const d = tvDate(iso); d.setUTCDate(d.getUTCDate() + n); return tvKey(d); };
const TV_MONTHS = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
const TV_WK     = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
const tvFmt     = (iso) => { const d = tvDate(iso); return `${TV_MONTHS[d.getUTCMonth()]} ${d.getUTCDate()}`; };

const tvPrioStyle = (p) => ({
  Low:    { background: 'var(--uk-prio-low-bg)',    color: 'var(--uk-prio-low-fg)' },
  Medium: { background: 'var(--uk-prio-med-bg)',    color: 'var(--uk-prio-med-fg)' },
  High:   { background: 'var(--uk-prio-high-bg)',   color: 'var(--uk-prio-high-fg)' },
  Urgent: { background: 'var(--uk-prio-urgent-bg)', color: 'var(--uk-prio-urgent-fg)' },
})[p];

function TvAvatar({ who, size = 16 }) {
  if (who === 'AI') return <span className="uk-avatar uk-avatar--ai" style={{ width: size, height: size, fontSize: size * 0.5 }}><Icon.Sparkle/></span>;
  const [bg, fg] = TV_PEOPLE[who] || ['#f4f4f5', '#52525b'];
  return <span className="uk-avatar" style={{ width: size, height: size, fontSize: size * 0.45, background: bg, color: fg }}>{who}</span>;
}

// ---------- Shared header: breadcrumb + view switcher + scope ----------
function TvViewControls({ active }) {
  const Views = [
    ['List',     <TvIcon.List/>],
    ['Calendar', <Icon.Calendar style={{ width: 12, height: 12 }}/>],
    ['Timeline', <TvIcon.Timeline/>],
  ];
  return (
    <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8 }}>
      {/* scope */}
      <button className="uk-btn uk-btn--secondary uk-btn--sm" style={{ paddingRight: 8 }}>
        <span style={{ width: 8, height: 8, borderRadius: 2, background: 'linear-gradient(135deg,#5e6ad2 50%,#a35c00 50%)' }}/>
        All projects<Icon.Down/>
      </button>
      {/* view switcher */}
      <div style={{ display: 'inline-flex', border: '1px solid #d4d4d8', borderRadius: 5, overflow: 'hidden' }}>
        {Views.map(([l, ic], i) => (
          <button key={l} className="uk-btn uk-btn--ghost uk-btn--sm" style={{
            borderRadius: 0,
            borderRight: i < Views.length - 1 ? '1px solid #d4d4d8' : 'none',
            background: l === active ? '#f4f4f5' : 'transparent',
            color: l === active ? '#18181b' : '#52525b',
            fontWeight: l === active ? 500 : 400,
            height: 26,
          }}>
            {ic}{l}
          </button>
        ))}
      </div>
      <button className="uk-btn uk-btn--secondary uk-btn--sm"><Icon.Filter/>Filter</button>
      <button className="uk-btn uk-btn--primary uk-btn--sm"><Icon.Plus/>New task</button>
    </div>
  );
}

function TvHeader({ active, count, dateNav }) {
  return (
    <div style={{ background: '#fff', borderBottom: '1px solid #e7e7ea' }}>
      {/* title */}
      <div style={{ padding: '14px 24px 10px' }}>
        <h1 className="uk-h2">Schedule</h1>
        <p className="uk-caption" style={{ marginTop: 1 }}>
          {count} scheduled tasks · workspace <span className="uk-mono">mskopal</span>
        </p>
      </div>

      {/* merged toolbar: date nav (view-specific) + shared view controls */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '0 24px 12px', flexWrap: 'wrap' }}>
        {dateNav}
        <TvViewControls active={active}/>
      </div>
    </div>
  );
}

const TvIcon = {
  Timeline: (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" {...p}><path d="M2 4h8M2 8h11M2 12h6"/></svg>,
  List:     (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" {...p}><path d="M5.5 4h8.5M5.5 8h8.5M5.5 12h8.5"/><circle cx="2.4" cy="4" r="0.9" fill="currentColor" stroke="none"/><circle cx="2.4" cy="8" r="0.9" fill="currentColor" stroke="none"/><circle cx="2.4" cy="12" r="0.9" fill="currentColor" stroke="none"/></svg>,
  Left:     (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M10 4l-4 4 4 4"/></svg>,
  Right:    (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M6 4l4 4-4 4"/></svg>,
  Grip:     (p) => <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor" {...p}><circle cx="6" cy="4" r="1.1"/><circle cx="10" cy="4" r="1.1"/><circle cx="6" cy="8" r="1.1"/><circle cx="10" cy="8" r="1.1"/><circle cx="6" cy="12" r="1.1"/><circle cx="10" cy="12" r="1.1"/></svg>,
};

// ============================================================
// CALENDAR VIEW — tasks placed by dueDate; drag to reschedule
// ============================================================
function CalendarScreen() {
  // Month: May 2026. Grid starts Sunday.
  const monthStart = '2026-05-01';
  const first = tvDate(monthStart);
  const gridStart = tvAddDay(monthStart, -first.getUTCDay()); // back to Sunday
  const cells = Array.from({ length: 42 }, (_, i) => tvAddDay(gridStart, i));

  // bucket tasks by due date
  const byDay = {};
  TV_TASKS.forEach(t => { (byDay[t.due] = byDay[t.due] || []).push(t); });

  // demo: one task mid-drag from May 22 -> May 26 (drop target)
  const dragId = 'UKO-318';
  const dropDay = '2026-05-26';

  const Chip = ({ t, ghost }) => {
    const overdue = !t.done && tvDays(t.due, TV_TODAY) > 0;
    return (
      <div style={{
        display: 'flex', alignItems: 'center', gap: 5,
        padding: '2px 5px 2px 4px', borderRadius: 4,
        background: ghost ? '#fff' : '#fff',
        border: '1px solid #e7e7ea',
        borderLeft: `2px solid ${TV_PROJECTS[t.proj]}`,
        fontSize: 11, lineHeight: 1.3, cursor: 'grab',
        boxShadow: ghost ? '0 8px 20px -6px rgba(24,24,27,0.25)' : 'none',
        opacity: t.done ? 0.6 : 1,
        transform: ghost ? 'rotate(-1.5deg)' : 'none',
      }}>
        {t.agent
          ? <span className="uk-avatar uk-avatar--ai" style={{ width: 13, height: 13, fontSize: 7, border: 'none' }}><Icon.Sparkle/></span>
          : <span style={{ width: 5, height: 5, borderRadius: '50%', background: overdue ? '#b42318' : TV_PROJECTS[t.proj], flexShrink: 0 }}/>}
        <span style={{
          flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap',
          color: t.done ? '#8a8a92' : '#18181b', textDecoration: t.done ? 'line-through' : 'none', fontWeight: 500,
        }}>{t.title}</span>
        {overdue && <span style={{ width: 4, height: 4, borderRadius: '50%', background: '#b42318', flexShrink: 0 }}/>}
      </div>
    );
  };

  const dateNav = (
    <React.Fragment>
      <h2 style={{ fontSize: 15, fontWeight: 600, letterSpacing: '-0.01em', marginRight: 2 }}>May 2026</h2>
      <div style={{ display: 'inline-flex', border: '1px solid #d4d4d8', borderRadius: 5, overflow: 'hidden' }}>
        <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm" style={{ borderRadius: 0, borderRight: '1px solid #d4d4d8' }}><TvIcon.Left/></button>
        <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm" style={{ borderRadius: 0 }}><TvIcon.Right/></button>
      </div>
      <button className="uk-btn uk-btn--secondary uk-btn--sm">Today</button>
      <div style={{ display: 'inline-flex', border: '1px solid #d4d4d8', borderRadius: 5, overflow: 'hidden' }}>
        {['Month','Week'].map((l, i) => (
          <button key={l} className="uk-btn uk-btn--ghost uk-btn--sm" style={{ borderRadius: 0, borderRight: i === 0 ? '1px solid #d4d4d8' : 'none', height: 26, background: i === 0 ? '#f4f4f5' : 'transparent', color: i === 0 ? '#18181b' : '#52525b' }}>{l}</button>
        ))}
      </div>
    </React.Fragment>
  );

  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', display: 'flex', flexDirection: 'column' }}>
      <TopBar active="tasks"/>
      <TvHeader active="Calendar" count={TV_TASKS.length} dateNav={dateNav}/>

      {/* weekday header */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', borderBottom: '1px solid #e7e7ea', background: '#fafafa' }}>
        {TV_WK.map(d => (
          <div key={d} style={{ padding: '6px 10px', fontSize: 11, fontWeight: 600, color: '#8a8a92', textTransform: 'uppercase', letterSpacing: '0.06em', textAlign: 'right' }}>{d}</div>
        ))}
      </div>

      {/* grid */}
      <div style={{ flex: 1, display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gridTemplateRows: 'repeat(6,1fr)', background: '#e7e7ea', gap: 1, overflow: 'hidden' }}>
        {cells.map((iso, i) => {
          const d = tvDate(iso);
          const inMonth = d.getUTCMonth() === 4;
          const isToday = iso === TV_TODAY;
          const isWeekend = d.getUTCDay() === 0 || d.getUTCDay() === 6;
          const tasks = (byDay[iso] || []).filter(t => t.id !== dragId || iso !== '2026-05-22');
          const isDrop = iso === dropDay;
          const shown = tasks.slice(0, 3);
          const extra = tasks.length - shown.length;
          return (
            <div key={iso} style={{
              background: inMonth ? (isWeekend ? '#fcfcfd' : '#fff') : '#fafafa',
              padding: '5px 6px', display: 'flex', flexDirection: 'column', gap: 3, minHeight: 0, overflow: 'hidden',
              position: 'relative',
            }}>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'flex-end', gap: 4, marginBottom: 1 }}>
                {i < 7 && <span style={{ marginRight: 'auto', fontSize: 9, color: '#b4b4ba', textTransform: 'uppercase', letterSpacing: '0.04em' }}>{!inMonth ? TV_MONTHS[d.getUTCMonth()] : ''}</span>}
                <span style={{
                  fontSize: 11, fontWeight: isToday ? 600 : 500,
                  color: isToday ? '#fff' : inMonth ? '#18181b' : '#b4b4ba',
                  background: isToday ? '#5e6ad2' : 'transparent',
                  width: 18, height: 18, borderRadius: '50%',
                  display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                }}>{d.getUTCDate()}</span>
              </div>

              {isDrop && (
                <div style={{ border: '1.5px dashed #5e6ad2', borderRadius: 4, height: 20, background: 'rgba(94,106,210,0.06)' }}/>
              )}
              {shown.map(t => <Chip key={t.id} t={t}/>)}
              {isDrop && (() => {
                const dt = TV_TASKS.find(t => t.id === dragId);
                return <div style={{ position: 'absolute', left: 6, right: 6, top: 26, zIndex: 5 }}><Chip t={dt} ghost/></div>;
              })()}
              {extra > 0 && (
                <button className="uk-btn uk-btn--ghost uk-btn--xs" style={{ justifyContent: 'flex-start', height: 16, padding: '0 4px', color: '#8a8a92', fontSize: 10 }}>+{extra} more</button>
              )}
            </div>
          );
        })}
      </div>

      {/* footer hint */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '7px 24px', borderTop: '1px solid #e7e7ea', background: '#fff', fontSize: 11, color: '#8a8a92' }}>
        <TvIcon.Grip style={{ color: '#b4b4ba' }}/>
        Drag a task to another day to reschedule — updates <span className="uk-mono" style={{ background: '#f4f4f5', padding: '0 4px', borderRadius: 3 }}>dueDate</span> via the move endpoint.
        <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 12 }}>
          {Object.entries(TV_PROJECTS).map(([name, c]) => (
            <span key={name} style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
              <span style={{ width: 7, height: 7, borderRadius: 2, background: c }}/>{name}
            </span>
          ))}
          <span style={{ width: 1, height: 14, background: '#e7e7ea' }}/>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
            <span style={{ width: 5, height: 5, borderRadius: '50%', background: '#b42318' }}/> overdue
          </span>
        </div>
      </div>
    </div>
  );
}

// ============================================================
// TIMELINE VIEW (Gantt) — bars from startDate → dueDate
// ============================================================
function TimelineScreen() {
  const rangeStart = '2026-05-04'; // Monday
  const N = 35;                     // 5 weeks
  const dayPct = 100 / N;
  const xOf = (iso) => tvDays(rangeStart, iso) * dayPct;

  // group tasks by project, ordered by start
  const order = Object.keys(TV_PROJECTS);
  const groups = order.map(proj => ({
    proj, color: TV_PROJECTS[proj],
    tasks: TV_TASKS.filter(t => t.proj === proj).sort((a, b) => tvDays(b.start, a.start) * -1),
  })).filter(g => g.tasks.length);

  // week column headers (every Monday)
  const weeks = Array.from({ length: 5 }, (_, w) => tvAddDay(rangeStart, w * 7));

  const LABEL_W = 248;

  const Bar = ({ t }) => {
    const left = xOf(t.start);
    const width = (tvDays(t.start, t.due) + 1) * dayPct;
    const overdue = !t.done && tvDays(t.due, TV_TODAY) > 0;
    const c = TV_PROJECTS[t.proj];
    const wide = width > 14;
    return (
      <div style={{
        position: 'absolute', left: `${left}%`, width: `${width}%`, top: 6, height: 22,
        background: t.done ? '#f1f1f3' : `color-mix(in srgb, ${c} 16%, #fff)`,
        border: `1px solid ${t.done ? '#d4d4d8' : `color-mix(in srgb, ${c} 45%, #fff)`}`,
        borderRadius: 5, display: 'flex', alignItems: 'center', gap: 5, padding: '0 4px 0 0',
        cursor: 'grab', overflow: 'hidden',
        outline: overdue ? '1.5px solid #b42318' : 'none', outlineOffset: -1,
      }}>
        <span style={{ width: 4, alignSelf: 'stretch', background: t.done ? '#b4b4ba' : c, borderRadius: '5px 0 0 5px', flexShrink: 0 }}/>
        {wide && <span className="uk-mono" style={{ fontSize: 9.5, color: t.done ? '#8a8a92' : 'color-mix(in srgb, ' + c + ' 60%, #18181b)', flexShrink: 0 }}>{t.id.replace('UKO-', '#')}</span>}
        {wide && <span style={{ flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: 11, fontWeight: 500, color: t.done ? '#8a8a92' : '#18181b', textDecoration: t.done ? 'line-through' : 'none' }}>{t.title}</span>}
        <span style={{ marginLeft: wide ? 0 : 'auto', flexShrink: 0 }}><TvAvatar who={t.who} size={15}/></span>
      </div>
    );
  };

  let rowIdx = 0;
  const ROW_H = 34;

  const dateNav = (
    <React.Fragment>
      <h2 style={{ fontSize: 15, fontWeight: 600, letterSpacing: '-0.01em', marginRight: 2 }}>May 4 – Jun 7</h2>
      <div style={{ display: 'inline-flex', border: '1px solid #d4d4d8', borderRadius: 5, overflow: 'hidden' }}>
        <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm" style={{ borderRadius: 0, borderRight: '1px solid #d4d4d8' }}><TvIcon.Left/></button>
        <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm" style={{ borderRadius: 0 }}><TvIcon.Right/></button>
      </div>
      <button className="uk-btn uk-btn--secondary uk-btn--sm">Today</button>
      <div style={{ display: 'inline-flex', border: '1px solid #d4d4d8', borderRadius: 5, overflow: 'hidden' }}>
        {['Day','Week','Month'].map((l, i) => (
          <button key={l} className="uk-btn uk-btn--ghost uk-btn--sm" style={{ borderRadius: 0, borderRight: i < 2 ? '1px solid #d4d4d8' : 'none', height: 26, background: i === 1 ? '#f4f4f5' : 'transparent', color: i === 1 ? '#18181b' : '#52525b' }}>{l}</button>
        ))}
      </div>
    </React.Fragment>
  );

  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', display: 'flex', flexDirection: 'column' }}>
      <TopBar active="tasks"/>
      <TvHeader active="Timeline" count={TV_TASKS.length} dateNav={dateNav}/>

      {/* axis header */}
      <div style={{ display: 'flex', borderBottom: '1px solid #e7e7ea', background: '#fafafa' }}>
        <div style={{ width: LABEL_W, flexShrink: 0, padding: '7px 14px', fontSize: 11, fontWeight: 600, color: '#8a8a92', textTransform: 'uppercase', letterSpacing: '0.06em', borderRight: '1px solid #e7e7ea' }}>Task</div>
        <div style={{ flex: 1, position: 'relative', display: 'flex' }}>
          {weeks.map((wk, i) => (
            <div key={wk} style={{ width: `${dayPct * 7}%`, padding: '7px 8px', fontSize: 11, fontWeight: 600, color: '#52525b', borderRight: i < 4 ? '1px solid #e7e7ea' : 'none' }}>
              {tvFmt(wk)}
            </div>
          ))}
        </div>
      </div>

      {/* body */}
      <div style={{ flex: 1, overflow: 'auto', position: 'relative' }}>
        <div style={{ display: 'flex', minHeight: '100%' }}>
          {/* left labels */}
          <div style={{ width: LABEL_W, flexShrink: 0, borderRight: '1px solid #e7e7ea', background: '#fff' }}>
            {groups.map(g => (
              <div key={g.proj}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 7, padding: '6px 14px', background: '#fafafa', borderBottom: '1px solid #e7e7ea', height: 30 }}>
                  <span style={{ width: 8, height: 8, borderRadius: 2, background: g.color }}/>
                  <span style={{ fontSize: 12, fontWeight: 600, color: '#18181b' }}>{g.proj}</span>
                  <span style={{ fontSize: 11, color: '#b4b4ba', marginLeft: 'auto' }}>{g.tasks.length}</span>
                </div>
                {g.tasks.map(t => (
                  <div key={t.id} style={{ display: 'flex', alignItems: 'center', gap: 7, padding: '0 14px', height: ROW_H, borderBottom: '1px solid #f1f1f3' }}>
                    <TvIcon.Grip style={{ color: '#d4d4d8', flexShrink: 0 }}/>
                    <span className="uk-mono" style={{ fontSize: 10, color: '#8a8a92', flexShrink: 0 }}>{t.id}</span>
                    <span style={{ flex: 1, minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', fontSize: 12, color: t.done ? '#8a8a92' : '#18181b', textDecoration: t.done ? 'line-through' : 'none' }}>{t.title}</span>
                    <span className="uk-badge" style={{ ...tvPrioStyle(t.prio), height: 15, padding: '0 4px', fontSize: 9.5, flexShrink: 0 }}>{t.prio[0]}</span>
                  </div>
                ))}
              </div>
            ))}
          </div>

          {/* right track */}
          <div style={{ flex: 1, position: 'relative' }}>
            {/* day/week gridlines */}
            <div style={{ position: 'absolute', inset: 0, display: 'flex', pointerEvents: 'none' }}>
              {Array.from({ length: N }, (_, i) => {
                const iso = tvAddDay(rangeStart, i);
                const dow = tvDate(iso).getUTCDay();
                const weekend = dow === 0 || dow === 6;
                const monday = dow === 1;
                return <div key={i} style={{ width: `${dayPct}%`, background: weekend ? '#fafafb' : 'transparent', borderRight: monday ? '1px solid #e7e7ea' : '1px solid #f4f4f5' }}/>;
              })}
            </div>
            {/* today line */}
            <div style={{ position: 'absolute', top: 0, bottom: 0, left: `${xOf(TV_TODAY) + dayPct / 2}%`, width: 0, borderLeft: '2px solid #5e6ad2', zIndex: 3, pointerEvents: 'none' }}>
              <span style={{ position: 'absolute', top: 4, left: -18, fontSize: 9, fontWeight: 700, color: '#fff', background: '#5e6ad2', padding: '1px 5px', borderRadius: 3, whiteSpace: 'nowrap' }}>TODAY</span>
            </div>

            {/* rows + bars */}
            <div style={{ position: 'relative', zIndex: 2 }}>
              {groups.map(g => (
                <div key={g.proj}>
                  {/* group spacer matches label header height */}
                  <div style={{ height: 30, borderBottom: '1px solid #e7e7ea', position: 'relative' }}>
                    {TV_MILESTONES.filter(m => m.proj === g.proj).map(m => (
                      <div key={m.label} style={{ position: 'absolute', left: `${xOf(m.date) + dayPct / 2}%`, top: '50%', transform: 'translate(-50%,-50%)', display: 'flex', alignItems: 'center', gap: 4 }}>
                        <span style={{ width: 9, height: 9, background: g.color, transform: 'rotate(45deg)', borderRadius: 1 }}/>
                        <span style={{ fontSize: 10, fontWeight: 600, color: g.color, whiteSpace: 'nowrap' }}>{m.label}</span>
                      </div>
                    ))}
                  </div>
                  {g.tasks.map(t => (
                    <div key={t.id} style={{ height: ROW_H, borderBottom: '1px solid #f1f1f3', position: 'relative' }}>
                      <Bar t={t}/>
                    </div>
                  ))}
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* footer hint */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 14, padding: '7px 24px', borderTop: '1px solid #e7e7ea', background: '#fff', fontSize: 11, color: '#8a8a92' }}>
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}><span style={{ width: 9, height: 9, background: '#5e6ad2', transform: 'rotate(45deg)', borderRadius: 1 }}/> milestone</span>
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}><span style={{ width: 16, height: 10, borderRadius: 3, outline: '1.5px solid #b42318', outlineOffset: -1, background: '#fff' }}/> overdue</span>
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}><TvIcon.Grip style={{ color: '#b4b4ba' }}/> drag bar to move · drag edge to change start / due date</span>
      </div>
    </div>
  );
}

// ============================================================
// LIST VIEW — same dataset + same shared toolbar as Calendar/Timeline
// ============================================================
function TaskListScreen() {
  const statusColor = { Todo: '#94a3a8', Doing: '#c98a14', Review: '#4a8fd6', Done: '#16794a' };
  const rows = [...TV_TASKS].sort((a, b) => tvDays(a.due, b.due));

  const dateNav = (
    <React.Fragment>
      <div className="uk-input-group" style={{ width: 220, height: 28 }}>
        <Icon.Search style={{ color: '#8a8a92' }}/>
        <input className="uk-input" placeholder="Search tasks"/>
      </div>
      <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ border: '1px dashed #d4d4d8' }}>
        <Icon.Plus/>Status<Icon.Down/>
      </button>
      <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ border: '1px dashed #d4d4d8' }}>
        <Icon.Plus/>Priority<Icon.Down/>
      </button>
      <span style={{ width: 1, height: 18, background: '#e7e7ea' }}/>
      <button className="uk-btn uk-btn--ghost uk-btn--sm">Sort: Due date<Icon.Down/></button>
    </React.Fragment>
  );

  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', display: 'flex', flexDirection: 'column' }}>
      <TopBar active="tasks"/>
      <TvHeader active="List" count={TV_TASKS.length} dateNav={dateNav}/>

      <div style={{ flex: 1, overflow: 'auto', padding: '14px 24px 24px' }}>
        <div className="uk-card" style={{ overflow: 'hidden' }}>
          <table className="uk-table">
            <thead>
              <tr>
                <th style={{ width: 28 }}>
                  <label className="uk-check" style={{ marginLeft: 4 }}><input type="checkbox"/><span className="uk-check-box"/></label>
                </th>
                <th style={{ width: 84 }}>ID</th>
                <th>Task</th>
                <th>Project</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Assignee</th>
                <th style={{ width: 84 }}>Start</th>
                <th style={{ width: 120 }}>Due</th>
              </tr>
            </thead>
            <tbody>
              {rows.map(t => {
                const overdue = !t.done && tvDays(t.due, TV_TODAY) > 0;
                return (
                  <tr key={t.id} style={{ cursor: 'pointer' }}>
                    <td>
                      <label className="uk-check" style={{ marginLeft: 4 }}><input type="checkbox"/><span className="uk-check-box"/></label>
                    </td>
                    <td className="uk-mono" style={{ color: '#8a8a92' }}>
                      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                        {t.agent && <Icon.Sparkle style={{ color: '#6f4ed3', width: 11, height: 11 }}/>}
                        {t.id}
                      </span>
                    </td>
                    <td style={{ fontWeight: 500, color: t.done ? '#8a8a92' : '#18181b', textDecoration: t.done ? 'line-through' : 'none' }}>{t.title}</td>
                    <td style={{ fontSize: 12 }}>
                      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                        <span style={{ width: 8, height: 8, borderRadius: 2, background: TV_PROJECTS[t.proj] }}/>{t.proj}
                      </span>
                    </td>
                    <td>
                      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
                        <span style={{ width: 8, height: 8, borderRadius: '50%', background: statusColor[t.status] }}/>{t.status}
                      </span>
                    </td>
                    <td><span className="uk-badge" style={tvPrioStyle(t.prio)}>{t.prio}</span></td>
                    <td><TvAvatar who={t.who} size={20}/></td>
                    <td style={{ color: '#52525b', fontSize: 12 }}>{tvFmt(t.start)}</td>
                    <td style={{ color: overdue ? '#b42318' : '#52525b', fontSize: 12, fontWeight: overdue ? 500 : 400 }}>
                      {tvFmt(t.due)}{overdue ? ' · overdue' : ''}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </div>

      {/* footer hint — mirrors the other two views */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '7px 24px', borderTop: '1px solid #e7e7ea', background: '#fff', fontSize: 11, color: '#8a8a92' }}>
        {TV_TASKS.length} scheduled tasks · sorted by due date
        <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 12 }}>
          {Object.entries(TV_PROJECTS).map(([name, c]) => (
            <span key={name} style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
              <span style={{ width: 7, height: 7, borderRadius: 2, background: c }}/>{name}
            </span>
          ))}
        </div>
      </div>
    </div>
  );
}

Object.assign(window, { CalendarScreen, TimelineScreen, TaskListScreen });
