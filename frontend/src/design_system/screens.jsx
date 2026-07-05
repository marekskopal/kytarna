// ============================================================
// Kytario — Application screens applying the new design system
// ============================================================

// Shared topbar shell
function TopBar({ active = 'tasks' }) {
  const NavLink = ({ id, label }) => (
    <a href="#" style={{
      padding: '6px 10px',
      borderRadius: 5,
      fontSize: 13,
      color: active === id ? '#18181b' : '#52525b',
      fontWeight: active === id ? 500 : 400,
      background: active === id ? '#f4f4f5' : 'transparent',
      textDecoration: 'none'
    }}>{label}</a>
  );

  return (
    <header style={{
      height: 48,
      borderBottom: '1px solid #e7e7ea',
      background: '#fff',
      display: 'flex',
      alignItems: 'center',
      padding: '0 16px',
      gap: 14
    }}>
      <Mark size={22} />
      <span style={{ fontWeight: 600, letterSpacing: '-0.018em', fontSize: 14 }}>kytario</span>

      <span style={{ width: 1, height: 18, background: '#e7e7ea', margin: '0 4px' }} />

      <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ padding: '0 8px', gap: 5 }}>
        <span style={{ width: 14, height: 14, borderRadius: 3, background: '#5e6ad2', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', color: '#fff', fontSize: 10, fontWeight: 600 }}>M</span>
        <span style={{ color: '#18181b', fontWeight: 500 }}>mskopal</span>
        <Icon.Down />
      </button>

      <nav style={{ display: 'flex', alignItems: 'center', gap: 2, marginLeft: 8 }}>
        <NavLink id="projects" label="Projects"/>
        <NavLink id="tasks" label="Tasks"/>
        <NavLink id="agents" label="Agents"/>
        <NavLink id="workspaces" label="Workspaces"/>
      </nav>

      <div style={{ flex: 1 }} />

      <div style={{
        display: 'flex', alignItems: 'center', gap: 6,
        height: 26, padding: '0 8px',
        border: '1px solid #e7e7ea', borderRadius: 5,
        background: '#fafafa',
        color: '#8a8a92', fontSize: 12, minWidth: 220
      }}>
        <Icon.Search />
        <span style={{ flex: 1 }}>Search or jump to…</span>
        <span className="uk-kbd">⌘K</span>
      </div>

      <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm"><Icon.Sparkle/></button>
      <span className="uk-avatar" style={{ background: '#fbe5d6', color: '#a35c00', width: 24, height: 24, fontSize: 10 }}>MS</span>
    </header>
  );
}
window.TopBar = TopBar;

// ============================================================
// 1. Login
// ============================================================
function LoginScreen() {
  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', display: 'flex' }}>
      {/* Left: form */}
      <div style={{ flex: '0 0 56%', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 32 }}>
        <div style={{ width: '100%', maxWidth: 320 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 32 }}>
            <Mark size={28}/>
            <span style={{ fontWeight: 600, letterSpacing: '-0.02em', fontSize: 18 }}>kytario</span>
          </div>

          <h1 className="uk-h2" style={{ marginBottom: 4 }}>Sign in</h1>
          <p className="uk-caption" style={{ marginBottom: 20 }}>to your workspace</p>

          <form style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div className="uk-field">
              <label className="uk-label">Email</label>
              <input className="uk-input" defaultValue="marek@kytario.com"/>
            </div>
            <div className="uk-field">
              <label className="uk-label" style={{ display: 'flex', alignItems: 'center' }}>
                Password
                <a href="#" style={{ marginLeft: 'auto', fontSize: 11, color: '#52525b' }}>Forgot?</a>
              </label>
              <input className="uk-input" type="password" defaultValue="••••••••••"/>
            </div>

            <button className="uk-btn uk-btn--primary uk-btn--lg" type="button" style={{ marginTop: 4, width: '100%' }}>
              Continue
            </button>

            <div style={{ display: 'flex', alignItems: 'center', gap: 8, margin: '6px 0', color: '#b4b4ba', fontSize: 11 }}>
              <span style={{ flex: 1, height: 1, background: '#e7e7ea' }}/>
              OR
              <span style={{ flex: 1, height: 1, background: '#e7e7ea' }}/>
            </div>

            <button className="uk-btn uk-btn--secondary uk-btn--lg" type="button" style={{ width: '100%' }}>
              Continue with SSO
            </button>
          </form>

          <p className="uk-caption" style={{ marginTop: 22, textAlign: 'center' }}>
            New here? <a href="#" style={{ color: '#5e6ad2', fontWeight: 500 }}>Sign up</a>
          </p>
        </div>
      </div>

      {/* Right: aside */}
      <div style={{ flex: 1, background: '#18181b', color: '#f4f4f5', padding: 40, display: 'flex', flexDirection: 'column', justifyContent: 'space-between' }}>
        <div>
          <div className="uk-overline" style={{ color: '#8a8a92', marginBottom: 16 }}>MCP-native</div>
          <h2 style={{ fontSize: 32, fontWeight: 600, letterSpacing: '-0.022em', lineHeight: 1.15, color: '#fafafa', marginBottom: 16 }}>
            A task manager designed for agents to drive, humans to oversee.
          </h2>
          <p style={{ fontSize: 14, color: '#b4b4ba', lineHeight: 1.6, maxWidth: 360 }}>
            Connect Claude, Cursor, or any MCP client over OAuth 2.1 + PKCE. Every move shows up in the audit log.
          </p>
        </div>

        <div style={{
          background: '#23232a',
          border: '1px solid #2e2e36',
          borderRadius: 7,
          padding: 14,
          fontFamily: 'JetBrains Mono, monospace',
          fontSize: 11.5,
          lineHeight: 1.7,
          color: '#d4d4d8',
          maxWidth: 380
        }}>
          <div style={{ color: '#8a8a92', marginBottom: 4 }}># claude.json</div>
          <div><span style={{ color: '#8a8a92' }}>"kytario"</span>: {'{'}</div>
          <div>&nbsp;&nbsp;<span style={{ color: '#8a8a92' }}>"url"</span>: <span style={{ color: '#a3b5e8' }}>"https://app.kytario.com/api/mcp"</span>,</div>
          <div>&nbsp;&nbsp;<span style={{ color: '#8a8a92' }}>"transport"</span>: <span style={{ color: '#a3b5e8' }}>"http"</span></div>
          <div>{'}'}</div>
        </div>
      </div>
    </div>
  );
}

// ============================================================
// 2. Projects list
// ============================================================
function ProjectsScreen() {
  const projects = [
    ['Backend rewrite',  'FrankenPHP + Redis migration',       24, 7, 'May 30', '#5e6ad2', ['MS','JK']],
    ['Frontend polish',  'Visual rebuild on the new system',    8, 2, 'Jun 14', '#16794a', ['EP','MS']],
    ['MCP onboarding',   'Public docs + Claude Desktop sample', 12, 5, 'Jun 02', '#a35c00', ['JK']],
    ['Documentation',    'Architecture decisions, ADRs',         3, 0, '—',      '#94a3a8', ['EP']],
    ['v1.0 launch',      'Marketing site, billing',              6, 1, 'Aug 01', '#4a8fd6', ['MS','JK','EP']],
  ];

  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', display: 'flex', flexDirection: 'column' }}>
      <TopBar active="projects"/>

      <div style={{ flex: 1, padding: 24, overflow: 'auto' }}>
        <div style={{ display: 'flex', alignItems: 'center', marginBottom: 18 }}>
          <div>
            <h1 className="uk-h2">Projects</h1>
            <p className="uk-caption">5 projects · workspace <span className="uk-mono">mskopal</span></p>
          </div>
          <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8 }}>
            <div className="uk-input-group" style={{ width: 220, height: 28 }}>
              <Icon.Search style={{ color: '#8a8a92' }}/>
              <input className="uk-input" placeholder="Filter projects"/>
            </div>
            <button className="uk-btn uk-btn--secondary uk-btn--sm"><Icon.Filter/>Filter</button>
            <button className="uk-btn uk-btn--primary uk-btn--sm"><Icon.Plus/>New project</button>
          </div>
        </div>

        {/* Agent activity strip */}
        <div className="uk-alert uk-alert--ai" style={{ marginBottom: 16 }}>
          <div style={{ paddingTop: 1 }}><Icon.Sparkle style={{ color: '#6f4ed3' }}/></div>
          <div style={{ flex: 1 }}>
            <div className="uk-alert-title">Agents active in this workspace</div>
            <div className="uk-alert-body">
              Claude (claude-sonnet-4-5) opened <span className="uk-mono" style={{ background: '#fff', padding: '0 4px', borderRadius: 3 }}>UKO-321</span> · Cursor created 2 tasks in Backend rewrite · 4 minutes ago
            </div>
          </div>
          <button className="uk-btn uk-btn--ghost uk-btn--sm">View activity</button>
        </div>

        <div className="uk-card" style={{ overflow: 'hidden' }}>
          <table className="uk-table">
            <thead>
              <tr>
                <th>Project</th>
                <th style={{ width: 110 }}>Open</th>
                <th style={{ width: 110 }}>Done · 7d</th>
                <th>Members</th>
                <th style={{ width: 120 }}>Target</th>
                <th style={{ width: 36 }}></th>
              </tr>
            </thead>
            <tbody>
              {projects.map(([name, desc, open, done, target, color, members]) => (
                <tr key={name} style={{ cursor: 'pointer' }}>
                  <td>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                      <span style={{ width: 8, height: 8, borderRadius: '50%', background: color, flexShrink: 0 }}/>
                      <div>
                        <div style={{ fontWeight: 500, fontSize: 13 }}>{name}</div>
                        <div style={{ fontSize: 12, color: '#8a8a92' }}>{desc}</div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span style={{ display: 'inline-flex', alignItems: 'baseline', gap: 4 }}>
                      <span style={{ fontWeight: 500 }}>{open}</span>
                      <span style={{ fontSize: 11, color: '#8a8a92' }}>open</span>
                    </span>
                  </td>
                  <td>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                      <span style={{ fontWeight: 500, color: '#16794a' }}>+{done}</span>
                      <span style={{ width: 40, height: 4, background: '#e7e7ea', borderRadius: 2, overflow: 'hidden' }}>
                        <span style={{ display: 'block', width: `${Math.min(done * 12, 40)}px`, height: '100%', background: '#16794a' }}/>
                      </span>
                    </span>
                  </td>
                  <td>
                    <div style={{ display: 'flex' }}>
                      {members.map((m,i) => (
                        <span key={i} className="uk-avatar" style={{
                          marginLeft: i ? -6 : 0,
                          width: 20, height: 20, fontSize: 9,
                          background: ['#fbe5d6','#dbeaff','#dcefe2'][i % 3],
                          color:      ['#a35c00','#1e58b6','#16794a'][i % 3],
                        }}>{m}</span>
                      ))}
                    </div>
                  </td>
                  <td style={{ color: '#52525b', fontSize: 12 }}>{target}</td>
                  <td><button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs"><Icon.More/></button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

// ============================================================
// 3. Kanban board
// ============================================================
function KanbanScreen() {
  const cols = [
    { name: 'To Do',       color: '#94a3a8', tasks: [
      { id: 'UKO-316', title: 'Fix kanban drag jitter in Firefox', prio: ['Low','low'],   due: '—', assign: ['EP','#dcefe2','#16794a'], agent: false, tags: [['frontend','#5e6ad2']] },
      { id: 'UKO-323', title: 'Add bulk assign action to tasks grid', prio: ['Medium','med'], due: 'May 28', assign: ['JK','#dbeaff','#1e58b6'], agent: false, tags: [['frontend','#5e6ad2'],['quick-win','#16794a']] },
      { id: 'UKO-325', title: 'Workspace-scoped API tokens for MCP', prio: ['High','high'], due: 'May 30', assign: ['MS','#fbe5d6','#a35c00'], agent: true, tags: [['mcp','#6f4ed3'],['security','#7e22ce']], files: 2 },
    ]},
    { name: 'In Progress', color: '#c98a14', tasks: [
      { id: 'UKO-318', title: 'Migrate sessions to Redis', prio: ['High','high'], due: 'May 22', assign: ['MS','#fbe5d6','#a35c00'], agent: false, desc: 'Preserve TTL semantics, add cache-hit metrics.', tags: [['backend','#0f766e'],['mcp','#6f4ed3']], files: 4, blockedBy: 1 },
      { id: 'UKO-315', title: 'Audit log retention policy', prio: ['Urgent','urgent'], due: 'May 18', assign: ['MS','#fbe5d6','#a35c00'], agent: false, overdue: true, tags: [['security','#7e22ce'],['backend','#0f766e']], blocks: 1 },
      { id: 'UKO-321', title: 'Add metrics for cache hit ratio', prio: ['Medium','med'], due: 'May 23', assign: ['AI','ai','ai'], agent: true, tags: [['backend','#0f766e']], dependsOn: 1, files: 1 },
    ]},
    { name: 'In Review',   color: '#4a8fd6', tasks: [
      { id: 'UKO-317', title: 'Document MCP OAuth + PKCE flow', prio: ['Medium','med'], due: 'May 20', assign: ['JK','#dbeaff','#1e58b6'], agent: false, tags: [['mcp','#6f4ed3'],['docs','#475569']], files: 2 },
      { id: 'UKO-312', title: 'Repository pattern for events', prio: ['Low','low'], due: '—', assign: ['JK','#dbeaff','#1e58b6'], agent: false, tags: [['backend','#0f766e'],['tech-debt','#475569']] },
    ]},
    { name: 'Done',        color: '#16794a', tasks: [
      { id: 'UKO-314', title: 'Workspace transfer atomicity', prio: ['Medium','med'], due: 'May 14', assign: ['JK','#dbeaff','#1e58b6'], agent: false, done: true, tags: [['backend','#0f766e']] },
      { id: 'UKO-311', title: 'Ship i18n switcher (EN/CS)', prio: ['Low','low'], due: 'May 12', assign: ['EP','#dcefe2','#16794a'], agent: false, done: true, tags: [['frontend','#5e6ad2']] },
    ]},
  ];

  const prioStyle = (k) => ({
    low:    { background: 'var(--uk-prio-low-bg)',    color: 'var(--uk-prio-low-fg)' },
    med:    { background: 'var(--uk-prio-med-bg)',    color: 'var(--uk-prio-med-fg)' },
    high:   { background: 'var(--uk-prio-high-bg)',   color: 'var(--uk-prio-high-fg)' },
    urgent: { background: 'var(--uk-prio-urgent-bg)', color: 'var(--uk-prio-urgent-fg)' },
  })[k];

  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', display: 'flex', flexDirection: 'column' }}>
      <TopBar active="projects"/>

      {/* Project bar */}
      <div style={{ padding: '14px 24px 12px', borderBottom: '1px solid #e7e7ea', background: '#fff' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 4, fontSize: 12, color: '#8a8a92' }}>
          <a href="#" style={{ color: '#8a8a92' }}>Projects</a>
          <Icon.Arrow style={{ width: 9, height: 9 }}/>
          <span style={{ color: '#18181b', fontWeight: 500 }}>Backend rewrite</span>
        </div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <h1 className="uk-h2">Backend rewrite</h1>
          <span className="uk-badge uk-badge--outline">v0.4</span>
          <span className="uk-badge uk-badge--ai"><Icon.Sparkle/>3 agents active</span>
          <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8 }}>
            <div style={{ display: 'inline-flex', border: '1px solid #d4d4d8', borderRadius: 5, overflow: 'hidden' }}>
              {['Board','List','Workflow','Events'].map((l,i) => (
                <button key={l} className="uk-btn uk-btn--ghost uk-btn--sm" style={{
                  borderRadius: 0,
                  borderRight: i < 3 ? '1px solid #d4d4d8' : 'none',
                  background: i === 0 ? '#f4f4f5' : 'transparent',
                  color: i === 0 ? '#18181b' : '#52525b',
                  height: 26
                }}>{l}</button>
              ))}
            </div>
            <button className="uk-btn uk-btn--secondary uk-btn--sm"><Icon.Filter/>Filter</button>
            <button className="uk-btn uk-btn--primary uk-btn--sm"><Icon.Plus/>New task</button>
          </div>
        </div>
      </div>

      {/* Board */}
      <div style={{ flex: 1, overflow: 'auto', padding: '14px 24px 24px' }}>
        <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
          {cols.map(col => (
            <div key={col.name} style={{ flex: '0 0 280px', display: 'flex', flexDirection: 'column', gap: 8 }}>
              <header style={{ display: 'flex', alignItems: 'center', gap: 8, padding: '2px 4px' }}>
                <span style={{ width: 8, height: 8, borderRadius: '50%', background: col.color }}/>
                <h3 style={{ fontSize: 13, fontWeight: 600 }}>{col.name}</h3>
                <span style={{ fontSize: 11, color: '#8a8a92' }}>{col.tasks.length}</span>
                <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" style={{ marginLeft: 'auto' }}><Icon.Plus/></button>
              </header>

              {col.tasks.map(t => (
                <div key={t.id} className="uk-task" style={{
                  borderLeft: t.agent ? '2px solid #6f4ed3' : undefined,
                  opacity: t.done ? 0.75 : 1
                }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <span className="uk-task-id">{t.id}</span>
                    {t.agent && <span className="uk-badge uk-badge--ai"><Icon.Sparkle/>agent</span>}
                    <span style={{ marginLeft: 'auto' }}>
                      <span className="uk-badge" style={prioStyle(t.prio[1])}>{t.prio[0]}</span>
                    </span>
                  </div>
                  <div className="uk-task-title" style={{ textDecoration: t.done ? 'line-through' : 'none', color: t.done ? '#8a8a92' : undefined }}>{t.title}</div>
                  {t.desc && <div style={{ fontSize: 12, color: '#52525b', lineHeight: 1.45 }}>{t.desc}</div>}
                  {t.tags && t.tags.length > 0 && (
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                      {t.tags.map(([name, c]) => <TagChip key={name} size="xs" color={c} name={name}/>)}
                    </div>
                  )}
                  <div className="uk-task-meta">
                    <Icon.Calendar style={t.overdue ? { color: '#b42318' } : undefined}/>
                    <span style={t.overdue ? { color: '#b42318', fontWeight: 500 } : undefined}>
                      {t.due}{t.overdue ? ' · overdue' : ''}
                    </span>
                    {(t.files || t.blockedBy || t.blocks || t.dependsOn) && <span className="uk-divider"/>}
                    {t.files > 0 && (
                      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 2 }} title={`${t.files} file${t.files > 1 ? 's' : ''}`}>
                        <FIcon.Paperclip/>{t.files}
                      </span>
                    )}
                    {t.dependsOn > 0 && (
                      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 2, color: '#a35c00' }} title="Depends on other tasks">
                        <FIcon.Block/>{t.dependsOn}
                      </span>
                    )}
                    {t.blocks > 0 && (
                      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 2, color: '#b42318' }} title="Blocks other tasks">
                        <FIcon.Block/>{t.blocks}
                      </span>
                    )}
                    {t.blockedBy > 0 && (
                      <span style={{ display: 'inline-flex', alignItems: 'center', gap: 2, color: '#52525b' }} title="Has a parent">
                        <FIcon.Tree/>{t.blockedBy}
                      </span>
                    )}
                    <span style={{ marginLeft: 'auto' }}>
                      {t.assign[2] === 'ai' ? (
                        <span className="uk-avatar uk-avatar--ai" style={{ width: 18, height: 18, fontSize: 9 }}><Icon.Sparkle/></span>
                      ) : (
                        <span className="uk-avatar" style={{ width: 18, height: 18, fontSize: 9, background: t.assign[1], color: t.assign[2] }}>{t.assign[0]}</span>
                      )}
                    </span>
                  </div>
                </div>
              ))}

              <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ justifyContent: 'flex-start', color: '#8a8a92', padding: '6px 4px' }}>
                <Icon.Plus/>Add task
              </button>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

// ============================================================
// 4. Task detail drawer
// ============================================================
function DrawerScreen() {
  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', position: 'relative' }}>
      {/* Faux board behind */}
      <div style={{ height: '100%', overflow: 'hidden', filter: 'blur(0.5px)' }}>
        <TopBar active="projects"/>
        <div style={{ padding: 18, display: 'flex', gap: 12 }}>
          {[1,2,3].map(i => (
            <div key={i} style={{ width: 260, height: 380, background: '#fff', borderRadius: 7, border: '1px solid #e7e7ea' }}/>
          ))}
        </div>
      </div>

      {/* Backdrop */}
      <div style={{ position: 'absolute', inset: 0, background: 'rgba(24,24,27,0.32)' }}/>

      {/* Drawer */}
      <aside style={{
        position: 'absolute',
        top: 0, right: 0, bottom: 0,
        width: 560,
        background: '#fff',
        borderLeft: '1px solid #e7e7ea',
        boxShadow: '-16px 0 40px -8px rgba(24,24,27,0.14)',
        display: 'flex', flexDirection: 'column'
      }}>
        {/* Header */}
        <div style={{ padding: '12px 20px', borderBottom: '1px solid #e7e7ea', display: 'flex', alignItems: 'center', gap: 8 }}>
          <span className="uk-mono" style={{ color: '#8a8a92' }}>UKO-318</span>
          <span style={{ width: 1, height: 14, background: '#e7e7ea' }}/>
          <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ padding: '0 6px' }}>
            <span style={{ width: 8, height: 8, borderRadius: '50%', background: '#c98a14' }}/>
            In Progress
            <Icon.Down/>
          </button>
          <div style={{ marginLeft: 'auto', display: 'flex', gap: 4 }}>
            <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm"><Icon.More/></button>
            <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm"><Icon.X/></button>
          </div>
        </div>

        {/* Body */}
        <div style={{ flex: 1, overflow: 'auto', padding: 20 }}>
          <input
            defaultValue="Migrate sessions to Redis"
            style={{
              width: '100%', border: 'none', outline: 'none',
              fontSize: 20, fontWeight: 600, letterSpacing: '-0.012em',
              color: '#18181b', padding: 0, marginBottom: 12,
              fontFamily: 'inherit'
            }}
          />

          {/* Meta row */}
          <div style={{ display: 'grid', gridTemplateColumns: '90px 1fr', rowGap: 8, columnGap: 12, fontSize: 12, marginBottom: 20 }}>
            <div style={{ color: '#8a8a92', alignSelf: 'center' }}>Assignee</div>
            <div>
              <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ padding: '0 6px' }}>
                <span className="uk-avatar" style={{ width: 18, height: 18, fontSize: 9, background: '#fbe5d6', color: '#a35c00' }}>MS</span>
                Marek Skopal
              </button>
            </div>

            <div style={{ color: '#8a8a92', alignSelf: 'center' }}>Priority</div>
            <div>
              <span className="uk-badge" style={{ background: 'var(--uk-prio-high-bg)', color: 'var(--uk-prio-high-fg)' }}>High</span>
            </div>

            <div style={{ color: '#8a8a92', alignSelf: 'center' }}>Due date</div>
            <div>
              <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ padding: '0 6px' }}>
                <Icon.Calendar/>May 22, 2026
              </button>
            </div>

            <div style={{ color: '#8a8a92', alignSelf: 'center' }}>Workflow</div>
            <div>
              <span className="uk-badge uk-badge--outline">Backend rewrite</span>
            </div>

            <div style={{ color: '#8a8a92', alignSelf: 'center' }}>Version</div>
            <div>
              <span className="uk-badge uk-badge--info">v2.4.0</span>
            </div>
          </div>

          {/* Tags */}
          <div style={{ display: 'flex', alignItems: 'center', marginBottom: 8 }}>
            <span className="uk-overline">Tags</span>
            <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ marginLeft: 'auto', padding: '0 6px' }}>
              <Icon.Plus/>Add tag
            </button>
          </div>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5, marginBottom: 20 }}>
            <TagChip color="#0f766e" name="backend" removable/>
            <TagChip color="#6f4ed3" name="mcp" removable/>
            <TagChip color="#7e22ce" name="security" removable/>
          </div>

          {/* Files */}
          <div style={{ display: 'flex', alignItems: 'center', marginBottom: 8 }}>
            <span className="uk-overline">Files</span>
            <span className="uk-badge" style={{ marginLeft: 6 }}>4</span>
            <label style={{ marginLeft: 'auto', display: 'inline-flex', alignItems: 'center', gap: 4, padding: '0 8px', height: 22, borderRadius: 4, color: '#52525b', fontSize: 12, cursor: 'pointer' }}>
              <FIcon.Upload/>Upload
            </label>
          </div>
          <ul style={{ listStyle: 'none', padding: 0, margin: '0 0 20px', display: 'flex', flexDirection: 'column', gap: 5 }}>
            {[
              { name: 'redis-migration-plan.md',     size: '12.4 KB', who: 'Marek \u00b7 2d',  ext: 'md',  agent: false },
              { name: 'cache-hit-ratio-grafana.png', size: '418 KB',  who: 'Claude \u00b7 4h', ext: 'png', agent: true },
              { name: 'session-ttl-benchmark.csv',   size: '8.1 KB',  who: 'Cursor \u00b7 4h', ext: 'csv', agent: true },
              { name: 'mcp_session_dir.yaml',        size: '1.2 KB',  who: 'Marek \u00b7 6h',  ext: 'yaml',agent: false },
            ].map(f => (
              <li key={f.name} style={{
                display: 'grid', gridTemplateColumns: '28px 1fr auto auto auto', gap: 9, alignItems: 'center',
                padding: '6px 8px', border: '1px solid #e7e7ea', borderRadius: 5, background: '#fff', fontSize: 12.5
              }}>
                <FileTypeIcon ext={f.ext} size={24}/>
                <div style={{ minWidth: 0 }}>
                  <div style={{ fontWeight: 500, color: '#18181b', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{f.name}</div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 5, fontSize: 11, color: '#8a8a92' }}>
                    {f.agent && <span className="uk-badge uk-badge--ai" style={{ height: 13, padding: '0 4px', fontSize: 10 }}><Icon.Sparkle/>agent</span>}
                    <span>{f.who}</span>
                  </div>
                </div>
                <span style={{ fontSize: 11, color: '#8a8a92' }}>{f.size}</span>
                <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" title="Download"><FIcon.Download/></button>
                <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" style={{ color: '#b42318' }} title="Delete"><Icon.X/></button>
              </li>
            ))}
          </ul>

          {/* Related tasks */}
          <div style={{ display: 'flex', alignItems: 'center', marginBottom: 8 }}>
            <span className="uk-overline">Related tasks</span>
            <span className="uk-badge" style={{ marginLeft: 6 }}>5</span>
            <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ marginLeft: 'auto', padding: '0 6px' }}>
              <Icon.Plus/>Add relation
            </button>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginBottom: 20 }}>
            {[
              ['Parent task',   'in',  [['300','Backend rewrite epic',   'Backend rewrite', '#c98a14']]],
              ['Depends on',    'out', [['305','Add Redis health endpoint',   'Backend rewrite', '#16794a'], ['315','Audit log retention policy','Backend rewrite','#c98a14', true]]],
              ['Blocks',        'in',  [['321','Add metrics for cache hit ratio',  'Backend rewrite', '#c98a14']]],
              ['Related',       'out', [['317','Document MCP OAuth + PKCE flow',   'MCP onboarding',  '#4a8fd6']]],
            ].map(([groupLabel, dir, rows]) => (
              <div key={groupLabel}>
                <div style={{ fontSize: 11, color: '#8a8a92', textTransform: 'uppercase', letterSpacing: '0.04em', marginBottom: 5, display: 'flex', alignItems: 'center', gap: 6 }}>
                  {groupLabel}
                  <span style={{ color: '#b4b4ba' }}>{rows.length}</span>
                </div>
                <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                  {rows.map(([id, name, proj, c, overdue]) => (
                    <li key={id} style={{
                      display: 'grid', gridTemplateColumns: 'auto 56px 1fr auto auto', alignItems: 'center', gap: 8,
                      padding: '6px 8px', border: '1px solid #e7e7ea', borderRadius: 5, background: '#fff', cursor: 'pointer', fontSize: 12.5
                    }}>
                      <span style={{ width: 8, height: 8, borderRadius: '50%', background: c }}/>
                      <span className="uk-mono" style={{ color: '#8a8a92', fontSize: 11 }}>UKO-{id}</span>
                      <span style={{ color: '#18181b', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{name}</span>
                      {overdue
                        ? <span className="uk-badge uk-badge--danger" style={{ height: 15, padding: '0 5px', fontSize: 10 }}>overdue</span>
                        : <span className="uk-caption" style={{ fontSize: 11 }}>{proj}</span>}
                      <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" style={{ color: '#b42318' }}><Icon.X/></button>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>

          {/* Description */}
          <div style={{ display: 'flex', alignItems: 'center', marginBottom: 8 }}>
            <span className="uk-overline">Description</span>
            <div style={{ marginLeft: 'auto', display: 'inline-flex', border: '1px solid #e7e7ea', borderRadius: 4, overflow: 'hidden' }}>
              <button className="uk-btn uk-btn--ghost uk-btn--xs" style={{ borderRadius: 0, background: '#f4f4f5', color: '#18181b' }}>Edit</button>
              <button className="uk-btn uk-btn--ghost uk-btn--xs" style={{ borderRadius: 0 }}>Preview</button>
            </div>
          </div>
          <div style={{
            padding: 12, border: '1px solid #e7e7ea', borderRadius: 6,
            fontSize: 13, color: '#52525b', lineHeight: 1.6
          }}>
            Migrate session storage from local filesystem to Redis. Preserve TTL semantics (1h access tokens, 30d refresh), add cache-hit metrics, and update <span className="uk-mono" style={{ background: '#f4f4f5', padding: '0 4px', borderRadius: 3 }}>MCP_SESSION_DIR</span> docs.
            <br/><br/>
            <strong style={{ color: '#18181b', fontWeight: 600 }}>Acceptance</strong>
            <ul style={{ margin: '6px 0 0', paddingLeft: 18 }}>
              <li>All MCP integration tests pass against Redis backend</li>
              <li>Migration runbook published in <code className="uk-mono">docs/ops/</code></li>
              <li>Grafana dashboard wired up</li>
            </ul>
          </div>

          {/* Activity */}
          <div style={{ display: 'flex', alignItems: 'center', marginTop: 24, marginBottom: 10 }}>
            <span className="uk-overline">Activity</span>
            <span className="uk-badge" style={{ marginLeft: 8 }}>5</span>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 14, fontSize: 12 }}>
            {[
              { who: 'AI', ai: true, action: 'created task via MCP', when: '2 days ago', client: 'Claude (claude-sonnet-4-5)' },
              { who: 'MS', bg: '#fbe5d6', fg: '#a35c00', action: 'assigned to Marek Skopal', when: '2 days ago' },
              { who: 'AI', ai: true, action: 'moved to In Progress', when: '6 hours ago', client: 'Cursor' },
              { who: 'MS', bg: '#fbe5d6', fg: '#a35c00', action: 'edited description', when: '4 hours ago' },
              { who: 'MS', bg: '#fbe5d6', fg: '#a35c00', comment: 'Sketched the migration plan in #ops. Doing this Friday.', when: '12 minutes ago' },
            ].map((e, i) => (
              <div key={i} style={{ display: 'flex', gap: 10 }}>
                {e.ai
                  ? <span className="uk-avatar uk-avatar--ai" style={{ width: 22, height: 22, fontSize: 10 }}><Icon.Sparkle/></span>
                  : <span className="uk-avatar" style={{ width: 22, height: 22, fontSize: 10, background: e.bg, color: e.fg }}>{e.who}</span>}
                <div style={{ flex: 1, lineHeight: 1.5 }}>
                  {e.comment ? (
                    <div style={{ background: '#f4f4f5', borderRadius: 6, padding: '8px 10px', color: '#18181b', fontSize: 13 }}>
                      {e.comment}
                    </div>
                  ) : (
                    <div>
                      <span style={{ color: '#18181b', fontWeight: 500 }}>
                        {e.ai ? (e.client || 'Agent') : (e.who === 'MS' ? 'Marek' : e.who)}
                      </span>{' '}
                      <span style={{ color: '#52525b' }}>{e.action}</span>
                    </div>
                  )}
                  <div style={{ color: '#8a8a92', fontSize: 11, marginTop: 2 }}>{e.when}{e.ai && !e.client ? '' : e.ai ? '' : ''}</div>
                </div>
              </div>
            ))}
          </div>

          <div style={{ marginTop: 14 }}>
            <div className="uk-input-group">
              <input className="uk-input" placeholder="Comment, mention with @"/>
              <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs"><Icon.Arrow/></button>
            </div>
          </div>
        </div>

        {/* Footer */}
        <div style={{ padding: '12px 20px', borderTop: '1px solid #e7e7ea', display: 'flex', alignItems: 'center', gap: 8 }}>
          <button className="uk-btn uk-btn--danger-ghost uk-btn--sm"><Icon.Trash/>Delete</button>
          <div style={{ flex: 1 }}/>
          <button className="uk-btn uk-btn--secondary uk-btn--sm">Cancel</button>
          <button className="uk-btn uk-btn--primary uk-btn--sm">Save changes</button>
        </div>
      </aside>
    </div>
  );
}

// ============================================================
// 5. Workspace tasks grid (with filters)
// ============================================================
function TasksGridScreen() {
  const rows = [
    ['UKO-321','Add metrics for cache hit ratio',     'Backend rewrite', ['Doing','#c98a14'],   'Medium', 'AI', 'May 23', false, true,  [['backend','#0f766e']], 1, 0],
    ['UKO-320','Refactor `Workspace::transfer`',      'Backend rewrite', ['Review','#4a8fd6'],  'Medium', 'JK', 'May 21', false, false, [['backend','#0f766e'],['tech-debt','#475569']], 0, 0],
    ['UKO-318','Migrate sessions to Redis',           'Backend rewrite', ['Doing','#c98a14'],   'High',   'MS', 'May 22', false, false, [['backend','#0f766e'],['mcp','#6f4ed3']], 4, 2],
    ['UKO-317','Document MCP OAuth + PKCE flow',      'MCP onboarding',  ['Review','#4a8fd6'],  'Medium', 'JK', 'May 20', false, false, [['mcp','#6f4ed3'],['docs','#475569']], 2, 1],
    ['UKO-316','Fix kanban drag jitter in Firefox',   'Frontend polish', ['Todo','#94a3a8'],    'Low',    'EP', '—',      false, false, [['frontend','#5e6ad2']], 0, 0],
    ['UKO-315','Audit log retention policy',          'Backend rewrite', ['Doing','#c98a14'],   'Urgent', 'MS', 'May 18', true,  false, [['security','#7e22ce'],['backend','#0f766e']], 0, 3],
    ['UKO-314','Workspace transfer atomicity',        'Backend rewrite', ['Done','#16794a'],    'Medium', 'JK', 'May 14', false, false, [['backend','#0f766e']], 1, 0],
    ['UKO-313','SSO via Google for invitation flow',  'v1.0 launch',     ['Todo','#94a3a8'],    'Low',    'EP', 'Jun 02', false, false, [['security','#7e22ce']], 0, 0],
    ['UKO-312','Repository pattern for events',       'Backend rewrite', ['Review','#4a8fd6'],  'Low',    'JK', '—',      false, false, [['backend','#0f766e'],['tech-debt','#475569']], 0, 0],
    ['UKO-311','Ship i18n switcher (EN/CS)',          'Frontend polish', ['Done','#16794a'],    'Low',    'EP', 'May 12', false, false, [['frontend','#5e6ad2']], 0, 0],
    ['UKO-310','Set up Mailpit in compose',           'Documentation',   ['Done','#16794a'],    'Low',    'MS', 'May 10', false, false, [], 0, 0],
  ];

  const prioStyle = (p) => ({
    Low:    { background: 'var(--uk-prio-low-bg)',    color: 'var(--uk-prio-low-fg)' },
    Medium: { background: 'var(--uk-prio-med-bg)',    color: 'var(--uk-prio-med-fg)' },
    High:   { background: 'var(--uk-prio-high-bg)',   color: 'var(--uk-prio-high-fg)' },
    Urgent: { background: 'var(--uk-prio-urgent-bg)', color: 'var(--uk-prio-urgent-fg)' },
  })[p];

  const avatar = (a) => {
    if (a === 'AI') return <span className="uk-avatar uk-avatar--ai" style={{ width: 20, height: 20, fontSize: 9 }}><Icon.Sparkle/></span>;
    const map = { MS: ['#fbe5d6','#a35c00'], JK: ['#dbeaff','#1e58b6'], EP: ['#dcefe2','#16794a'] };
    const [bg,fg] = map[a];
    return <span className="uk-avatar" style={{ width: 20, height: 20, fontSize: 9, background: bg, color: fg }}>{a}</span>;
  };

  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', display: 'flex', flexDirection: 'column' }}>
      <TopBar active="tasks"/>

      <div style={{ flex: 1, padding: 24, overflow: 'auto', display: 'flex', flexDirection: 'column' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: 16 }}>
          <div>
            <h1 className="uk-h2">Tasks</h1>
            <p className="uk-caption">All tasks across mskopal · {rows.length} of 128</p>
          </div>
          <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 6 }}>
            <button className="uk-btn uk-btn--secondary uk-btn--sm">Export CSV</button>
            <button className="uk-btn uk-btn--primary uk-btn--sm"><Icon.Plus/>New task</button>
          </div>
        </div>

        {/* Filter bar */}
        <div style={{
          display: 'flex', alignItems: 'center', gap: 8, padding: 8,
          background: '#fff', border: '1px solid #e7e7ea', borderRadius: 7, marginBottom: 10
        }}>
          <div className="uk-input-group" style={{ width: 240, height: 28 }}>
            <Icon.Search style={{ color: '#8a8a92' }}/>
            <input className="uk-input" placeholder="Search tasks"/>
          </div>
          <span style={{ width: 1, height: 18, background: '#e7e7ea' }}/>
          <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ border: '1px dashed #d4d4d8' }}>
            <Icon.Plus/>Status<Icon.Down/>
          </button>
          <span className="uk-badge uk-badge--outline" style={{ height: 22 }}>
            Status: Doing, Review
            <button style={{ border: 'none', background: 'none', padding: 0, marginLeft: 2, cursor: 'pointer', color: '#8a8a92' }}><Icon.X/></button>
          </span>
          <span className="uk-badge uk-badge--outline" style={{ height: 22 }}>
            Priority: High, Urgent
            <button style={{ border: 'none', background: 'none', padding: 0, marginLeft: 2, cursor: 'pointer', color: '#8a8a92' }}><Icon.X/></button>
          </span>
          <span className="uk-badge uk-badge--outline" style={{ height: 22, display: 'inline-flex', alignItems: 'center', gap: 5 }}>
            <FIcon.Tag style={{ color: '#8a8a92' }}/>
            Tags:
            <TagChip size="xs" color="#0f766e" name="backend"/>
            <TagChip size="xs" color="#6f4ed3" name="mcp"/>
            <button style={{ border: 'none', background: 'none', padding: 0, marginLeft: 2, cursor: 'pointer', color: '#8a8a92' }}><Icon.X/></button>
          </span>
          <span className="uk-badge uk-badge--ai" style={{ height: 22 }}>
            <Icon.Sparkle/>Created by agent
            <button style={{ border: 'none', background: 'none', padding: 0, marginLeft: 2, cursor: 'pointer', color: '#6f4ed3' }}><Icon.X/></button>
          </span>
          <div style={{ flex: 1 }}/>
          <span className="uk-caption">Group by:</span>
          <button className="uk-btn uk-btn--ghost uk-btn--sm">Project<Icon.Down/></button>
        </div>

        <div className="uk-card" style={{ overflow: 'hidden', flex: 1 }}>
          <table className="uk-table">
            <thead>
              <tr>
                <th style={{ width: 28 }}>
                  <label className="uk-check" style={{ marginLeft: 4 }}>
                    <input type="checkbox" /><span className="uk-check-box"/>
                  </label>
                </th>
                <th style={{ width: 76 }}>ID</th>
                <th>Task</th>
                <th>Tags</th>
                <th>Project</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Assignee</th>
                <th>Due</th>
                <th style={{ width: 60 }}>Files</th>
                <th style={{ width: 32 }}></th>
              </tr>
            </thead>
            <tbody>
              {rows.map(([id,name,proj,[status,sc],prio,assign,due,overdue,agent,tags,files,relCount]) => (
                <tr key={id} style={{ cursor: 'pointer' }}>
                  <td>
                    <label className="uk-check" style={{ marginLeft: 4 }}>
                      <input type="checkbox" /><span className="uk-check-box"/>
                    </label>
                  </td>
                  <td className="uk-mono" style={{ color: '#8a8a92' }}>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
                      {agent && <Icon.Sparkle style={{ color: '#6f4ed3', width: 11, height: 11 }}/>}
                      {id}
                    </span>
                  </td>
                  <td style={{ fontWeight: 500, color: '#18181b' }}>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
                      {name}
                      {relCount > 0 && (
                        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 2, color: '#8a8a92', fontSize: 11, fontWeight: 400 }} title={`${relCount} relation${relCount > 1 ? 's' : ''}`}>
                          <FIcon.Link/>{relCount}
                        </span>
                      )}
                    </span>
                  </td>
                  <td>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                      {tags.map(([n, c]) => <TagChip key={n} size="xs" color={c} name={n}/>)}
                    </div>
                  </td>
                  <td style={{ color: '#52525b', fontSize: 12 }}>{proj}</td>
                  <td>
                    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 6, fontSize: 12 }}>
                      <span style={{ width: 8, height: 8, borderRadius: '50%', background: sc }}/>
                      {status}
                    </span>
                  </td>
                  <td><span className="uk-badge" style={prioStyle(prio)}>{prio}</span></td>
                  <td>{avatar(assign)}</td>
                  <td style={{ color: overdue ? '#b42318' : '#52525b', fontSize: 12, fontWeight: overdue ? 500 : 400 }}>{due}</td>
                  <td style={{ color: '#8a8a92', fontSize: 12 }}>
                    {files > 0
                      ? <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3 }}><FIcon.Paperclip/>{files}</span>
                      : <span style={{ color: '#b4b4ba' }}>—</span>}
                  </td>
                  <td><button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs"><Icon.More/></button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        <div style={{ display: 'flex', alignItems: 'center', padding: '10px 4px', fontSize: 12, color: '#52525b' }}>
          <span>11 of 128</span>
          <div style={{ marginLeft: 'auto', display: 'flex', gap: 4 }}>
            <button className="uk-btn uk-btn--secondary uk-btn--sm" disabled>Previous</button>
            <button className="uk-btn uk-btn--secondary uk-btn--sm">Next</button>
          </div>
        </div>
      </div>
    </div>
  );
}

// ============================================================
// 6. Events / activity log
// ============================================================
function EventsScreen() {
  const events = [
    { when: '13:42', actor: 'AI',  action: 'created task',  target: 'UKO-321 · Add metrics for cache hit ratio', proj: 'Backend rewrite', client: 'Claude (claude-sonnet-4-5)' },
    { when: '13:42', actor: 'AI',  action: 'moved task',    target: 'UKO-318 → In Progress',                     proj: 'Backend rewrite', client: 'Cursor' },
    { when: '13:31', actor: 'MS',  action: 'commented on',  target: 'UKO-318',                                   proj: 'Backend rewrite',
      comment: 'Sketched the migration plan in #ops. Doing this Friday.' },
    { when: '12:14', actor: 'JK',  action: 'opened review', target: 'UKO-317 · Document MCP OAuth + PKCE',       proj: 'MCP onboarding' },
    { when: '11:50', actor: 'AI',  action: 'updated description', target: 'UKO-316',                              proj: 'Frontend polish', client: 'Claude (claude-sonnet-4-5)' },
    { when: '11:02', actor: 'EP',  action: 'closed task',   target: 'UKO-310 · Set up Mailpit in compose',       proj: 'Documentation' },
    { when: '10:18', actor: 'MS',  action: 'invited member',target: 'eva@kytario.com (Member)',                    proj: '—' },
    { when: '09:44', actor: 'AI',  action: 'created task',  target: 'UKO-319 · Cache key collision in tests',    proj: 'Backend rewrite', client: 'Claude Desktop' },
    { when: '09:01', actor: 'JK',  action: 'changed status',target: 'UKO-312 → In Review',                       proj: 'Backend rewrite' },
  ];

  const avatar = (a) => {
    if (a === 'AI') return <span className="uk-avatar uk-avatar--ai" style={{ width: 22, height: 22, fontSize: 10 }}><Icon.Sparkle/></span>;
    const map = { MS: ['#fbe5d6','#a35c00'], JK: ['#dbeaff','#1e58b6'], EP: ['#dcefe2','#16794a'] };
    const [bg,fg] = map[a] || ['#f4f4f5','#52525b'];
    return <span className="uk-avatar" style={{ width: 22, height: 22, fontSize: 10, background: bg, color: fg }}>{a}</span>;
  };

  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', display: 'flex', flexDirection: 'column' }}>
      <TopBar active="agents"/>

      <div style={{ flex: 1, padding: 24, overflow: 'auto' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: 18 }}>
          <div>
            <h1 className="uk-h2">Agent activity</h1>
            <p className="uk-caption">Live audit log · Today, May 17 · 2026</p>
          </div>
          <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 8 }}>
            <button className="uk-btn uk-btn--secondary uk-btn--sm">Export</button>
            <button className="uk-btn uk-btn--secondary uk-btn--sm"><Icon.Calendar/>Today<Icon.Down/></button>
          </div>
        </div>

        {/* KPI strip */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 10, marginBottom: 16 }}>
          {[
            ['Events · 24h',  '142', '+18 vs avg', '#16794a'],
            ['Active agents', '3',   'Claude · Cursor · Claude Desktop', '#6f4ed3'],
            ['Tasks created', '11',  '7 by agents · 4 by humans', '#1e58b6'],
            ['Tasks closed',  '6',   '+3 vs yesterday', '#16794a'],
          ].map(([label, val, sub, c]) => (
            <div key={label} className="uk-card" style={{ padding: 14 }}>
              <div className="uk-overline" style={{ marginBottom: 6 }}>{label}</div>
              <div style={{ fontSize: 22, fontWeight: 600, letterSpacing: '-0.02em', color: '#18181b' }}>{val}</div>
              <div style={{ fontSize: 11, color: c, marginTop: 2 }}>{sub}</div>
            </div>
          ))}
        </div>

        {/* Filter chips */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
          <span className="uk-caption" style={{ marginRight: 4 }}>Show:</span>
          {['All','Humans','Agents','Comments','Status changes'].map((f,i) => (
            <button key={f} className="uk-btn uk-btn--ghost uk-btn--sm" style={{
              height: 24, padding: '0 10px', borderRadius: 999,
              background: i === 0 ? '#18181b' : '#f4f4f5',
              color: i === 0 ? '#fff' : '#52525b'
            }}>{f}</button>
          ))}
        </div>

        <div className="uk-card" style={{ overflow: 'hidden' }}>
          <div className="uk-list">
            {events.map((e, i) => (
              <div key={i} className="uk-row" style={{ alignItems: 'flex-start', padding: '10px 14px', minHeight: 0 }}>
                <span className="uk-mono" style={{ color: '#8a8a92', width: 44, paddingTop: 3, flexShrink: 0 }}>{e.when}</span>
                <div style={{ paddingTop: 0 }}>{avatar(e.actor)}</div>
                <div style={{ flex: 1, lineHeight: 1.5, fontSize: 13 }}>
                  <div>
                    <span style={{ color: '#18181b', fontWeight: 500 }}>
                      {e.actor === 'AI' ? (e.client || 'Agent') :
                       e.actor === 'MS' ? 'Marek Skopal' :
                       e.actor === 'JK' ? 'Jakub Kostka' :
                       e.actor === 'EP' ? 'Eva Pokorna' : e.actor}
                    </span>
                    {' '}
                    <span style={{ color: '#52525b' }}>{e.action}</span>
                    {' '}
                    <span className="uk-mono" style={{ color: '#18181b', background: '#f4f4f5', padding: '0 5px', borderRadius: 3 }}>{e.target}</span>
                  </div>
                  {e.comment && (
                    <div style={{ background: '#f4f4f5', borderRadius: 6, padding: '8px 10px', marginTop: 6, color: '#18181b', fontSize: 13 }}>
                      {e.comment}
                    </div>
                  )}
                  <div style={{ display: 'flex', gap: 8, marginTop: 3, fontSize: 11, color: '#8a8a92' }}>
                    <span>{e.proj}</span>
                    {e.actor === 'AI' && <><span>·</span><span className="uk-badge uk-badge--ai" style={{ height: 16 }}><Icon.Sparkle/>via MCP</span></>}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

// ============================================================
// 7. Workspace management
// ============================================================
function WorkspaceScreen() {
  const members = [
    ['MS','Marek Skopal','marek@kytario.com','Owner','active 1m ago','#fbe5d6','#a35c00'],
    ['JK','Jakub Kostka','jakub@kytario.com','Admin','active 14m ago','#dbeaff','#1e58b6'],
    ['EP','Eva Pokorna','eva@kytario.com','Member','active yesterday','#dcefe2','#16794a'],
    ['TN','Tomas Novak','tomas@kytario.com','Member','invited 3d ago, pending','#f4f4f5','#52525b'],
  ];
  const tokens = [
    ['Claude Desktop',    'claude-sonnet-4-5', 'created May 12', 'last used 2m ago'],
    ['Cursor (work)',     'mixed',             'created May 03', 'last used 14m ago'],
    ['n8n workflow',      'gpt-4o',            'created Apr 22', 'last used 3d ago'],
  ];

  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', display: 'flex', flexDirection: 'column' }}>
      <TopBar active="workspaces"/>

      <div style={{ flex: 1, padding: 24, overflow: 'auto', maxWidth: 1080, margin: '0 auto', width: '100%' }}>
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: 18 }}>
          <div>
            <h1 className="uk-h2">mskopal</h1>
            <p className="uk-caption">Workspace · 4 members · 5 projects · 128 tasks</p>
          </div>
        </div>

        {/* Tabs */}
        <div style={{ display: 'flex', gap: 0, borderBottom: '1px solid #e7e7ea', marginBottom: 20 }}>
          {['General','Members','MCP & agents','Custom fields','Tags','Billing'].map((t,i) => (
            <button key={t} className="uk-btn uk-btn--ghost uk-btn--sm" style={{
              height: 34, borderRadius: 0,
              borderBottom: i === 1 ? '2px solid #18181b' : '2px solid transparent',
              color: i === 1 ? '#18181b' : '#52525b',
              fontWeight: i === 1 ? 500 : 400, marginBottom: -1
            }}>{t}</button>
          ))}
        </div>

        {/* Two columns: members + tokens */}
        <div style={{ display: 'grid', gridTemplateColumns: '1.4fr 1fr', gap: 24 }}>
          {/* Members card */}
          <div className="uk-card" style={{ overflow: 'hidden' }}>
            <div style={{ padding: '12px 14px', borderBottom: '1px solid #e7e7ea', display: 'flex', alignItems: 'center' }}>
              <div>
                <div style={{ fontSize: 14, fontWeight: 600 }}>Members</div>
                <div className="uk-caption" style={{ fontSize: 11 }}>Manage roles and invitations</div>
              </div>
              <div style={{ marginLeft: 'auto', display: 'flex', gap: 6 }}>
                <div className="uk-input-group" style={{ height: 26, width: 180 }}>
                  <Icon.Search style={{ color: '#8a8a92' }}/>
                  <input className="uk-input" placeholder="Filter"/>
                </div>
                <button className="uk-btn uk-btn--primary uk-btn--sm"><Icon.Plus/>Invite</button>
              </div>
            </div>

            <div className="uk-list">
              {members.map(([i,name,email,role,last,bg,fg]) => (
                <div key={email} className="uk-row" style={{ padding: '10px 14px' }}>
                  <span className="uk-avatar" style={{ background: bg, color: fg, width: 26, height: 26, fontSize: 11 }}>{i}</span>
                  <div style={{ display: 'flex', flexDirection: 'column', minWidth: 0 }}>
                    <span style={{ fontWeight: 500 }}>{name}</span>
                    <span style={{ fontSize: 11, color: '#8a8a92' }}>{email} · {last}</span>
                  </div>
                  <div style={{ marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 6 }}>
                    <button className="uk-btn uk-btn--secondary uk-btn--sm" style={{ paddingRight: 8 }}>
                      {role}<Icon.Down/>
                    </button>
                    <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm"><Icon.More/></button>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* MCP tokens */}
          <div className="uk-card" style={{ overflow: 'hidden', alignSelf: 'flex-start' }}>
            <div style={{ padding: '12px 14px', borderBottom: '1px solid #e7e7ea', display: 'flex', alignItems: 'center' }}>
              <div>
                <div style={{ fontSize: 14, fontWeight: 600 }}>MCP clients</div>
                <div className="uk-caption" style={{ fontSize: 11 }}>OAuth 2.1 + PKCE</div>
              </div>
              <button className="uk-btn uk-btn--secondary uk-btn--sm" style={{ marginLeft: 'auto' }}>
                <Icon.Plus/>Register
              </button>
            </div>

            <div className="uk-list">
              {tokens.map(([client, model, created, used]) => (
                <div key={client} className="uk-row" style={{ alignItems: 'flex-start', padding: '12px 14px', minHeight: 0 }}>
                  <span className="uk-avatar uk-avatar--ai" style={{ width: 26, height: 26, fontSize: 12, marginTop: 1 }}><Icon.Sparkle/></span>
                  <div style={{ flex: 1, lineHeight: 1.45 }}>
                    <div style={{ fontWeight: 500 }}>{client}</div>
                    <div style={{ fontSize: 11, color: '#8a8a92' }}>{model} · {created} · {used}</div>
                  </div>
                  <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm"><Icon.More/></button>
                </div>
              ))}
            </div>

            <div style={{ padding: 12, borderTop: '1px solid #e7e7ea' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontSize: 11, color: '#52525b' }}>
                <Icon.Sparkle style={{ color: '#6f4ed3' }}/>
                3 active · 4,218 calls this month
              </div>
            </div>
          </div>

          {/* General settings card spanning both */}
          <div className="uk-card" style={{ gridColumn: '1 / -1', padding: 18 }}>
            <div style={{ display: 'grid', gridTemplateColumns: '220px 1fr 240px', gap: 24, alignItems: 'flex-start' }}>
              <div>
                <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 4 }}>Ownership</div>
                <div className="uk-caption" style={{ fontSize: 11 }}>Transfer ownership to another member. The current Owner is downgraded to Admin atomically.</div>
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                <div className="uk-field">
                  <label className="uk-label">Transfer to</label>
                  <select className="uk-select" defaultValue="jk">
                    <option value="jk">Jakub Kostka — jakub@kytario.com</option>
                    <option value="ep">Eva Pokorna — eva@kytario.com</option>
                  </select>
                </div>
                <label className="uk-check">
                  <input type="checkbox"/><span className="uk-check-box"/>
                  I understand this action is irreversible.
                </label>
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                <button className="uk-btn uk-btn--secondary">Cancel</button>
                <button className="uk-btn uk-btn--danger">Transfer ownership</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

Object.assign(window, {
  LoginScreen, ProjectsScreen, KanbanScreen, DrawerScreen,
  TasksGridScreen, EventsScreen, WorkspaceScreen
});
