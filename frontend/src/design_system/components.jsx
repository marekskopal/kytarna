// ============================================================
// Ukolio Design System — Components
// Buttons, forms, badges, alerts, lists, tables, task cards
// ============================================================

// Small icon helpers (just inline SVG)
const Icon = {
  Search:    (p) => <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" {...p}><circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5L14 14"/></svg>,
  Plus:      (p) => <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" {...p}><path d="M8 3v10M3 8h10"/></svg>,
  Check:     (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M3.5 8.5l3 3 6-7"/></svg>,
  X:         (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" {...p}><path d="M4 4l8 8M12 4l-8 8"/></svg>,
  Arrow:     (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M3 8h10M9 4l4 4-4 4"/></svg>,
  Down:      (p) => <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M4 6l4 4 4-4"/></svg>,
  Calendar:  (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" {...p}><rect x="2.5" y="3.5" width="11" height="10" rx="1.5"/><path d="M2.5 6.5h11M5.5 2v3M10.5 2v3"/></svg>,
  Filter:    (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M2 3h12l-4.5 6v4l-3 2v-6L2 3z"/></svg>,
  Sparkle:   (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M8 2v3M8 11v3M2 8h3M11 8h3M3.8 3.8l2 2M10.2 10.2l2 2M3.8 12.2l2-2M10.2 5.8l2-2"/></svg>,
  Dot:       (p) => <svg width="6" height="6" viewBox="0 0 6 6" {...p}><circle cx="3" cy="3" r="3" fill="currentColor"/></svg>,
  Trash:     (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" {...p}><path d="M3 4.5h10M6 4.5V3a1 1 0 011-1h2a1 1 0 011 1v1.5M4.5 4.5l.7 8a1 1 0 001 .9h3.6a1 1 0 001-.9l.7-8"/></svg>,
  More:      (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" {...p}><circle cx="3" cy="8" r="1.3"/><circle cx="8" cy="8" r="1.3"/><circle cx="13" cy="8" r="1.3"/></svg>,
  User:      (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" {...p}><circle cx="8" cy="6" r="2.5"/><path d="M3 13.5c.8-2.4 2.7-3.5 5-3.5s4.2 1.1 5 3.5"/></svg>,
  Flag:      (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M3.5 14V2.5M3.5 2.5h8l-1.5 3 1.5 3h-8"/></svg>,
};
window.Icon = Icon;

// ============================================================
// Buttons
// ============================================================
function ButtonsBoard() {
  const row = (label, btns) => (
    <div style={{ display: 'grid', gridTemplateColumns: '110px 1fr', gap: 16, alignItems: 'center', paddingBottom: 12, borderBottom: '1px solid #f4f4f5' }}>
      <span className="uk-overline">{label}</span>
      <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>{btns}</div>
    </div>
  );

  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%' }}>
      <div className="uk-section-title">Buttons</div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        {row('Primary', <>
          <button className="uk-btn uk-btn--primary uk-btn--xs">Save</button>
          <button className="uk-btn uk-btn--primary uk-btn--sm">Save</button>
          <button className="uk-btn uk-btn--primary">Save changes</button>
          <button className="uk-btn uk-btn--primary uk-btn--lg">Save changes</button>
          <button className="uk-btn uk-btn--primary"><Icon.Plus/>New task</button>
          <button className="uk-btn uk-btn--primary" disabled>Saving…</button>
        </>)}

        {row('Secondary', <>
          <button className="uk-btn uk-btn--secondary uk-btn--xs">Cancel</button>
          <button className="uk-btn uk-btn--secondary uk-btn--sm">Cancel</button>
          <button className="uk-btn uk-btn--secondary">Cancel</button>
          <button className="uk-btn uk-btn--secondary"><Icon.Filter/>Filter</button>
          <button className="uk-btn uk-btn--secondary"><Icon.Calendar/>May 18, 2026<Icon.Down/></button>
        </>)}

        {row('Ghost', <>
          <button className="uk-btn uk-btn--ghost uk-btn--sm">Dismiss</button>
          <button className="uk-btn uk-btn--ghost">View activity</button>
          <button className="uk-btn uk-btn--ghost"><Icon.More/></button>
        </>)}

        {row('Danger', <>
          <button className="uk-btn uk-btn--danger uk-btn--sm">Delete</button>
          <button className="uk-btn uk-btn--danger">Delete task</button>
          <button className="uk-btn uk-btn--danger-ghost"><Icon.Trash/>Delete</button>
        </>)}

        {row('Icon-only', <>
          <button className="uk-btn uk-btn--secondary uk-btn--icon uk-btn--xs"><Icon.Plus/></button>
          <button className="uk-btn uk-btn--secondary uk-btn--icon uk-btn--sm"><Icon.Plus/></button>
          <button className="uk-btn uk-btn--secondary uk-btn--icon"><Icon.Plus/></button>
          <button className="uk-btn uk-btn--ghost uk-btn--icon"><Icon.More/></button>
        </>)}

        {row('Group', <>
          <div style={{ display: 'inline-flex', border: '1px solid #d4d4d8', borderRadius: 5, overflow: 'hidden', background: '#fff' }}>
            {['Board','List','Timeline'].map((l,i) => (
              <button key={l} className="uk-btn uk-btn--ghost uk-btn--sm" style={{
                borderRadius: 0, borderRight: i < 2 ? '1px solid #d4d4d8' : 'none',
                background: i === 0 ? '#f4f4f5' : 'transparent',
                color: i === 0 ? '#18181b' : '#52525b',
              }}>{l}</button>
            ))}
          </div>
          <div style={{ display: 'inline-flex', gap: 0 }}>
            <button className="uk-btn uk-btn--secondary uk-btn--sm" style={{ borderRadius: '5px 0 0 5px' }}>Approve</button>
            <button className="uk-btn uk-btn--secondary uk-btn--sm uk-btn--icon" style={{ borderRadius: '0 5px 5px 0', borderLeft: 'none' }}><Icon.Down/></button>
          </div>
        </>)}
      </div>

      <div style={{ marginTop: 16, padding: 12, background: '#f4f4f5', border: '1px solid #e7e7ea', borderRadius: 7, fontSize: 11, color: '#52525b', lineHeight: 1.6 }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 8 }}>
          <div><strong style={{ color: '#18181b' }}>xs</strong><br/>22px h, 11px</div>
          <div><strong style={{ color: '#18181b' }}>sm</strong><br/>26px h, 12px</div>
          <div><strong style={{ color: '#18181b' }}>md</strong><br/>30px h, 13px <em>· default</em></div>
          <div><strong style={{ color: '#18181b' }}>lg</strong><br/>36px h, 14px</div>
          <div><strong style={{ color: '#18181b' }}>xl</strong><br/>44px h, 15px</div>
        </div>
      </div>
    </div>
  );
}

// ============================================================
// Form fields
// ============================================================
function FormsBoard() {
  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%' }}>
      <div className="uk-section-title">Form fields</div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', columnGap: 28, rowGap: 16 }}>
        <div className="uk-field">
          <label className="uk-label">Email</label>
          <input className="uk-input" defaultValue="marek@ukolio.com" />
          <span className="uk-hint">Used for invitations and notifications.</span>
        </div>

        <div className="uk-field">
          <label className="uk-label">Password</label>
          <input className="uk-input" type="password" defaultValue="••••••••••" />
        </div>

        <div className="uk-field">
          <label className="uk-label">Project name</label>
          <div className="uk-input-group">
            <span className="uk-input-prefix uk-mono">mskopal/</span>
            <input className="uk-input" defaultValue="backend-rewrite" />
          </div>
        </div>

        <div className="uk-field">
          <label className="uk-label">Due date</label>
          <div className="uk-input-group">
            <Icon.Calendar style={{ color: '#52525b' }} />
            <input className="uk-input" defaultValue="2026-05-22" />
          </div>
        </div>

        <div className="uk-field">
          <label className="uk-label">Workflow status</label>
          <select className="uk-select" defaultValue="doing">
            <option value="todo">To Do</option>
            <option value="doing">In Progress</option>
            <option value="review">In Review</option>
            <option value="done">Done</option>
          </select>
        </div>

        <div className="uk-field">
          <label className="uk-label">Priority</label>
          <select className="uk-select" defaultValue="high">
            <option>Low</option><option>Medium</option><option>High</option><option>Urgent</option>
          </select>
        </div>

        <div className="uk-field" style={{ gridColumn: '1 / -1' }}>
          <label className="uk-label">Description</label>
          <textarea className="uk-textarea" rows={3} defaultValue="Migrate session storage from filesystem to Redis. Preserve current TTL semantics, add metrics."/>
          <span className="uk-hint">Supports markdown. <kbd className="uk-kbd">⌘</kbd> <kbd className="uk-kbd">B</kbd> for bold.</span>
        </div>

        <div className="uk-field">
          <label className="uk-label">Email</label>
          <input className="uk-input uk-input--err" defaultValue="not-an-email" />
          <span className="uk-hint" style={{ color: '#b42318' }}>Enter a valid email address.</span>
        </div>

        <div className="uk-field">
          <label className="uk-label">API token</label>
          <input className="uk-input" disabled defaultValue="sk_live_•••••••••••• (rotate)" />
          <span className="uk-hint">Disabled · contact your workspace owner</span>
        </div>
      </div>

      <hr className="uk-hr" style={{ margin: '20px 0' }} />

      <div className="uk-overline" style={{ marginBottom: 10 }}>Selection</div>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 18, alignItems: 'center' }}>
        <label className="uk-check">
          <input type="checkbox" defaultChecked />
          <span className="uk-check-box"></span>
          Send email notifications
        </label>
        <label className="uk-check">
          <input type="checkbox" />
          <span className="uk-check-box"></span>
          Subscribe to events
        </label>
        <label className="uk-check">
          <input type="radio" name="r1" defaultChecked />
          <span className="uk-radio-box"></span>
          Workspace owner
        </label>
        <label className="uk-check">
          <input type="radio" name="r1" />
          <span className="uk-radio-box"></span>
          Admin
        </label>
        <label className="uk-check">
          <input type="radio" name="r1" />
          <span className="uk-radio-box"></span>
          Member
        </label>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13 }}>
          <span className="uk-toggle uk-toggle--on" />
          <span>Auto-archive done</span>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 13, color: '#52525b' }}>
          <span className="uk-toggle" />
          <span>Block on overdue</span>
        </div>
      </div>
    </div>
  );
}

// ============================================================
// Badges + chips + alerts
// ============================================================
function BadgesBoard() {
  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%' }}>
      <div className="uk-section-title">Badges · Status · Alerts</div>

      <div className="uk-overline" style={{ marginBottom: 10 }}>Badge variants</div>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, marginBottom: 18 }}>
        <span className="uk-badge">12</span>
        <span className="uk-badge uk-badge--outline">draft</span>
        <span className="uk-badge uk-badge--accent">indigo</span>
        <span className="uk-badge uk-badge--success"><Icon.Check/>passed</span>
        <span className="uk-badge uk-badge--warn">staging</span>
        <span className="uk-badge uk-badge--danger">overdue</span>
        <span className="uk-badge uk-badge--info">v2.4.0</span>
        <span className="uk-badge uk-badge--ai"><Icon.Sparkle/>agent</span>
        <span className="uk-badge uk-badge--solid">private</span>
      </div>

      <div className="uk-overline" style={{ marginBottom: 10 }}>Workflow status</div>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 14, marginBottom: 18, fontSize: 13 }}>
        {[
          ['Todo',    '#94a3a8'],
          ['Doing',   '#c98a14'],
          ['Review',  '#4a8fd6'],
          ['Done',    '#16794a'],
          ['Blocked', '#b42318'],
        ].map(([n,c]) => (
          <span key={n} style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
            <span style={{ width: 8, height: 8, borderRadius: '50%', background: c, flexShrink: 0 }} />
            {n}
          </span>
        ))}
      </div>

      <div className="uk-overline" style={{ marginBottom: 10 }}>Priority</div>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8, marginBottom: 18 }}>
        <span className="uk-badge" style={{ background: 'var(--uk-prio-low-bg)', color: 'var(--uk-prio-low-fg)' }}>Low</span>
        <span className="uk-badge" style={{ background: 'var(--uk-prio-med-bg)', color: 'var(--uk-prio-med-fg)' }}>Medium</span>
        <span className="uk-badge" style={{ background: 'var(--uk-prio-high-bg)', color: 'var(--uk-prio-high-fg)' }}>High</span>
        <span className="uk-badge" style={{ background: 'var(--uk-prio-urgent-bg)', color: 'var(--uk-prio-urgent-fg)' }}>Urgent</span>
      </div>

      <div className="uk-overline" style={{ marginBottom: 10 }}>Avatars</div>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 22 }}>
        <span className="uk-avatar" style={{ background: '#fbe5d6', color: '#a35c00' }}>MS</span>
        <span className="uk-avatar" style={{ background: '#dbeaff', color: '#1e58b6' }}>JK</span>
        <span className="uk-avatar" style={{ background: '#dcefe2', color: '#16794a' }}>EP</span>
        <span className="uk-avatar uk-avatar--ai"><Icon.Sparkle/></span>
        <div style={{ display: 'flex' }}>
          {['MS','JK','EP','+2'].map((n,i) => (
            <span key={i} className="uk-avatar" style={{ marginLeft: i ? -6 : 0, background: i === 3 ? '#f4f4f5' : ['#fbe5d6','#dbeaff','#dcefe2'][i], color: i === 3 ? '#52525b' : ['#a35c00','#1e58b6','#16794a'][i] }}>{n}</span>
          ))}
        </div>
      </div>

      <div className="uk-overline" style={{ marginBottom: 10 }}>Alerts</div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginBottom: 18 }}>
        <div className="uk-alert uk-alert--info">
          <div style={{ paddingTop: 1 }}><Icon.Dot style={{ color: '#1e58b6' }}/></div>
          <div>
            <div className="uk-alert-title">Migration pending</div>
            <div className="uk-alert-body">Run <code className="uk-mono" style={{ background: '#fff', padding: '0 4px', borderRadius: 3 }}>make migrate</code> to apply 3 new migrations.</div>
          </div>
        </div>
        <div className="uk-alert uk-alert--success">
          <div style={{ paddingTop: 1 }}><Icon.Check style={{ color: '#16794a' }}/></div>
          <div>
            <div className="uk-alert-title">Invitation sent</div>
            <div className="uk-alert-body">jakub@ukolio.com will get an email shortly.</div>
          </div>
        </div>
        <div className="uk-alert uk-alert--warn">
          <div style={{ paddingTop: 1 }}><Icon.Flag style={{ color: '#a35c00' }}/></div>
          <div>
            <div className="uk-alert-title">3 tasks overdue</div>
            <div className="uk-alert-body">Reschedule or close in the workspace grid.</div>
          </div>
        </div>
        <div className="uk-alert uk-alert--danger">
          <div style={{ paddingTop: 1 }}><Icon.X style={{ color: '#b42318' }}/></div>
          <div>
            <div className="uk-alert-title">Auth token expired</div>
            <div className="uk-alert-body">Refresh your session from the workspace switcher.</div>
          </div>
        </div>
        <div className="uk-alert uk-alert--ai">
          <div style={{ paddingTop: 1 }}><Icon.Sparkle style={{ color: '#6f4ed3' }}/></div>
          <div>
            <div className="uk-alert-title">Agent activity · last 5 min</div>
            <div className="uk-alert-body">Claude (claude-sonnet-4-5) created 2 tasks, moved 4 to Review.</div>
          </div>
        </div>
      </div>

      <div className="uk-overline" style={{ marginBottom: 10 }}>Toast</div>
      <div className="uk-toast">
        <span className="uk-toast-dot" />
        <span>Task <strong style={{ fontWeight: 600 }}>UKO-318</strong> moved to In Progress</span>
        <button className="uk-btn uk-btn--ghost uk-btn--xs" style={{ marginLeft: 'auto', color: '#a1a1aa' }}>Undo</button>
        <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" style={{ color: '#a1a1aa' }}><Icon.X /></button>
      </div>
    </div>
  );
}

// ============================================================
// Lists + rows + table
// ============================================================
function ListsBoard() {
  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%' }}>
      <div className="uk-section-title">Lists · Rows · Tables</div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 28 }}>
        {/* Simple nav list */}
        <div>
          <div className="uk-overline" style={{ marginBottom: 10 }}>Sidebar list</div>
          <div className="uk-card" style={{ overflow: 'hidden' }}>
            <div className="uk-list">
              <div className="uk-row uk-row--interactive uk-row--selected">
                <span className="uk-dot" style={{ background: '#5e6ad2' }}/>
                <span style={{ fontWeight: 500 }}>Backend rewrite</span>
                <span className="uk-badge" style={{ marginLeft: 'auto' }}>24</span>
              </div>
              <div className="uk-row uk-row--interactive">
                <span className="uk-dot" style={{ background: '#16794a' }}/>
                <span>Frontend polish</span>
                <span className="uk-badge" style={{ marginLeft: 'auto' }}>8</span>
              </div>
              <div className="uk-row uk-row--interactive">
                <span className="uk-dot" style={{ background: '#a35c00' }}/>
                <span>MCP onboarding</span>
                <span className="uk-badge" style={{ marginLeft: 'auto' }}>12</span>
              </div>
              <div className="uk-row uk-row--interactive">
                <span className="uk-dot" style={{ background: '#94a3a8' }}/>
                <span>Documentation</span>
                <span className="uk-badge" style={{ marginLeft: 'auto' }}>3</span>
              </div>
            </div>
          </div>
        </div>

        {/* Member list */}
        <div>
          <div className="uk-overline" style={{ marginBottom: 10 }}>Member list</div>
          <div className="uk-card" style={{ overflow: 'hidden' }}>
            <div className="uk-list">
              {[
                ['MS','Marek Skopal','marek@ukolio.com','Owner','#fbe5d6','#a35c00'],
                ['JK','Jakub Kostka','jakub@ukolio.com','Admin','#dbeaff','#1e58b6'],
                ['EP','Eva Pokorna','eva@ukolio.com','Member','#dcefe2','#16794a'],
              ].map(([initials, name, email, role, bg, fg]) => (
                <div key={email} className="uk-row uk-row--interactive">
                  <span className="uk-avatar" style={{ background: bg, color: fg }}>{initials}</span>
                  <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                    <span style={{ fontWeight: 500 }}>{name}</span>
                    <span className="uk-caption" style={{ fontSize: 11 }}>{email}</span>
                  </div>
                  <span className="uk-badge uk-badge--outline" style={{ marginLeft: 'auto' }}>{role}</span>
                </div>
              ))}
            </div>
          </div>
        </div>
      </div>

      <div className="uk-overline" style={{ marginTop: 24, marginBottom: 10 }}>Data table</div>
      <div className="uk-card" style={{ overflow: 'hidden' }}>
        <table className="uk-table">
          <thead>
            <tr>
              <th style={{ width: 28 }}>
                <label className="uk-check" style={{ marginLeft: 4 }}>
                  <input type="checkbox" /><span className="uk-check-box"/>
                </label>
              </th>
              <th style={{ width: 72 }}>ID</th>
              <th>Task</th>
              <th>Status</th>
              <th>Priority</th>
              <th>Assignee</th>
              <th>Due</th>
            </tr>
          </thead>
          <tbody>
            {[
              ['UKO-318','Migrate sessions to Redis',     ['Doing','#c98a14'], 'High',   ['MS','#fbe5d6','#a35c00'],'May 22', false],
              ['UKO-317','Document MCP OAuth + PKCE',     ['Review','#4a8fd6'],'Medium', ['JK','#dbeaff','#1e58b6'],'May 20', false],
              ['UKO-316','Fix kanban drag jitter on FF',  ['Todo','#94a3a8'],  'Low',    ['EP','#dcefe2','#16794a'],'—',     false],
              ['UKO-315','Audit log retention policy',    ['Doing','#c98a14'], 'Urgent', ['MS','#fbe5d6','#a35c00'],'May 18', true],
              ['UKO-314','Workspace transfer atomicity',  ['Done','#16794a'],  'Medium', ['JK','#dbeaff','#1e58b6'],'May 14', false],
            ].map(([id,name,[status,sc],prio,[a,abg,afg],due,overdue]) => (
              <tr key={id}>
                <td>
                  <label className="uk-check" style={{ marginLeft: 4 }}>
                    <input type="checkbox" /><span className="uk-check-box"/>
                  </label>
                </td>
                <td className="uk-mono" style={{ color: '#8a8a92' }}>{id}</td>
                <td style={{ fontWeight: 500 }}>{name}</td>
                <td>
                  <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
                    <span style={{ width: 8, height: 8, borderRadius: '50%', background: sc }}/>
                    {status}
                  </span>
                </td>
                <td>
                  <span className="uk-badge" style={{
                    background: prio === 'Urgent' ? 'var(--uk-prio-urgent-bg)' :
                                prio === 'High'   ? 'var(--uk-prio-high-bg)'   :
                                prio === 'Medium' ? 'var(--uk-prio-med-bg)'    : 'var(--uk-prio-low-bg)',
                    color:      prio === 'Urgent' ? 'var(--uk-prio-urgent-fg)' :
                                prio === 'High'   ? 'var(--uk-prio-high-fg)'   :
                                prio === 'Medium' ? 'var(--uk-prio-med-fg)'    : 'var(--uk-prio-low-fg)',
                  }}>{prio}</span>
                </td>
                <td>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <span className="uk-avatar" style={{ background: abg, color: afg, width: 20, height: 20, fontSize: 9 }}>{a}</span>
                  </div>
                </td>
                <td style={{ color: overdue ? '#b42318' : '#52525b', fontWeight: overdue ? 500 : 400, fontSize: 12 }}>{due}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ============================================================
// Task cards (kanban)
// ============================================================
function TaskCardsBoard() {
  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%' }}>
      <div className="uk-section-title">Task cards</div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12, marginBottom: 16 }}>
        {/* Default */}
        <div>
          <div className="uk-caption" style={{ marginBottom: 6 }}>Default</div>
          <div className="uk-task">
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span className="uk-task-id">UKO-318</span>
              <span style={{ marginLeft: 'auto' }}>
                <span className="uk-badge" style={{ background: 'var(--uk-prio-high-bg)', color: 'var(--uk-prio-high-fg)' }}>High</span>
              </span>
            </div>
            <div className="uk-task-title">Migrate sessions to Redis</div>
            <div className="uk-task-meta">
              <Icon.Calendar /><span>May 22</span>
              <span className="uk-divider"/>
              <span className="uk-avatar" style={{ width: 18, height: 18, fontSize: 9, background: '#fbe5d6', color: '#a35c00' }}>MS</span>
            </div>
          </div>
        </div>

        {/* Agent-created */}
        <div>
          <div className="uk-caption" style={{ marginBottom: 6 }}>Agent · created via MCP</div>
          <div className="uk-task" style={{ borderLeft: '2px solid #6f4ed3' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span className="uk-task-id">UKO-321</span>
              <span className="uk-badge uk-badge--ai" style={{ marginLeft: 0 }}><Icon.Sparkle/>agent</span>
              <span style={{ marginLeft: 'auto' }}>
                <span className="uk-badge" style={{ background: 'var(--uk-prio-med-bg)', color: 'var(--uk-prio-med-fg)' }}>Medium</span>
              </span>
            </div>
            <div className="uk-task-title">Add metrics for cache hit ratio</div>
            <div className="uk-task-meta">
              <Icon.Calendar/><span>May 23</span>
              <span className="uk-divider"/>
              <span className="uk-avatar uk-avatar--ai" style={{ width: 18, height: 18, fontSize: 9 }}><Icon.Sparkle/></span>
            </div>
          </div>
        </div>

        {/* Overdue */}
        <div>
          <div className="uk-caption" style={{ marginBottom: 6 }}>Overdue</div>
          <div className="uk-task">
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span className="uk-task-id">UKO-315</span>
              <span style={{ marginLeft: 'auto' }}>
                <span className="uk-badge" style={{ background: 'var(--uk-prio-urgent-bg)', color: 'var(--uk-prio-urgent-fg)' }}>Urgent</span>
              </span>
            </div>
            <div className="uk-task-title">Audit log retention policy</div>
            <div className="uk-task-meta">
              <Icon.Calendar style={{ color: '#b42318' }}/>
              <span style={{ color: '#b42318', fontWeight: 500 }}>May 18 · overdue</span>
              <span className="uk-divider"/>
              <span className="uk-avatar" style={{ width: 18, height: 18, fontSize: 9, background: '#fbe5d6', color: '#a35c00' }}>MS</span>
            </div>
          </div>
        </div>

        {/* With description */}
        <div>
          <div className="uk-caption" style={{ marginBottom: 6 }}>With description</div>
          <div className="uk-task">
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span className="uk-task-id">UKO-317</span>
              <span style={{ marginLeft: 'auto' }}>
                <span className="uk-badge" style={{ background: 'var(--uk-prio-med-bg)', color: 'var(--uk-prio-med-fg)' }}>Medium</span>
              </span>
            </div>
            <div className="uk-task-title">Document MCP OAuth + PKCE flow</div>
            <div style={{ fontSize: 12, color: '#52525b', lineHeight: 1.45, display: '-webkit-box', WebkitLineClamp: 2, WebkitBoxOrient: 'vertical', overflow: 'hidden' }}>
              Map the discovery endpoints, token TTLs and storage strategy. Reference RFC 9728 for resource metadata.
            </div>
            <div className="uk-task-meta">
              <Icon.Calendar/><span>May 20</span>
              <span className="uk-divider"/>
              <span className="uk-avatar" style={{ width: 18, height: 18, fontSize: 9, background: '#dbeaff', color: '#1e58b6' }}>JK</span>
            </div>
          </div>
        </div>

        {/* Hover */}
        <div>
          <div className="uk-caption" style={{ marginBottom: 6 }}>Hover</div>
          <div className="uk-task" style={{ borderColor: '#d4d4d8' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span className="uk-task-id">UKO-316</span>
              <span style={{ marginLeft: 'auto' }}>
                <span className="uk-badge" style={{ background: 'var(--uk-prio-low-bg)', color: 'var(--uk-prio-low-fg)' }}>Low</span>
              </span>
            </div>
            <div className="uk-task-title">Fix kanban drag jitter in Firefox</div>
            <div className="uk-task-meta">
              <Icon.Calendar/><span>—</span>
              <span className="uk-divider"/>
              <span className="uk-avatar" style={{ width: 18, height: 18, fontSize: 9, background: '#dcefe2', color: '#16794a' }}>EP</span>
            </div>
          </div>
        </div>

        {/* Dragging */}
        <div>
          <div className="uk-caption" style={{ marginBottom: 6 }}>Dragging</div>
          <div className="uk-task" style={{ boxShadow: '0 16px 40px -8px rgba(24,24,27,0.18), 0 4px 8px rgba(24,24,27,0.08)', transform: 'rotate(-1.2deg)', borderColor: '#d4d4d8' }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
              <span className="uk-task-id">UKO-318</span>
              <span style={{ marginLeft: 'auto' }}>
                <span className="uk-badge" style={{ background: 'var(--uk-prio-high-bg)', color: 'var(--uk-prio-high-fg)' }}>High</span>
              </span>
            </div>
            <div className="uk-task-title">Migrate sessions to Redis</div>
            <div className="uk-task-meta">
              <Icon.Calendar/><span>May 22</span>
            </div>
          </div>
        </div>
      </div>

      <div style={{ padding: 12, background: '#f4f4f5', border: '1px solid #e7e7ea', borderRadius: 7, fontSize: 11, color: '#52525b', lineHeight: 1.6 }}>
        <strong style={{ color: '#18181b' }}>Anatomy.</strong> ID (mono) · priority chip · title (medium, 13px, max 2 lines) · meta row (due date, assignee). Agent-created tasks add a 2px indigo-violet left bar and an <em>agent</em> badge to keep MCP activity legible at a glance.
      </div>
    </div>
  );
}

Object.assign(window, { ButtonsBoard, FormsBoard, BadgesBoard, ListsBoard, TaskCardsBoard });
