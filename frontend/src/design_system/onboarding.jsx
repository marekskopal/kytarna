// ============================================================
// Ukolio — Onboarding flow
//   Step 1 · Create first project (name + workflow columns)
//   Step 2 · Invite teammates
//   Step 3 · You're set (with MCP hand-off)
// ============================================================

function OnboardingShell({ step = 1, children }) {
  const steps = [
    { n: 1, label: 'Project & workflow', hint: 'Where work lives' },
    { n: 2, label: 'Invite teammates',   hint: 'Bring your team in' },
    { n: 3, label: 'Connect agents',     hint: 'Optional · MCP' },
  ];

  return (
    <div className="uk" style={{ height: '100%', background: '#fafafa', display: 'flex', flexDirection: 'column' }}>
      {/* Minimal chrome */}
      <header style={{
        height: 52, padding: '0 24px',
        borderBottom: '1px solid #e7e7ea', background: '#fff',
        display: 'flex', alignItems: 'center', gap: 12
      }}>
        <Mark size={22} />
        <span style={{ fontWeight: 600, letterSpacing: '-0.018em', fontSize: 14 }}>ukolio</span>
        <span style={{ width: 1, height: 16, background: '#e7e7ea', margin: '0 4px' }} />
        <span className="uk-caption" style={{ fontSize: 12 }}>
          Set up workspace <span className="uk-mono" style={{ color: '#18181b' }}>mskopal</span>
        </span>
        <div style={{ flex: 1 }} />
        <span className="uk-caption" style={{ fontSize: 12 }}>
          Signed in as <span style={{ color: '#18181b', fontWeight: 500 }}>marek@ukolio.com</span>
        </span>
        <button className="uk-btn uk-btn--ghost uk-btn--sm">Sign out</button>
      </header>

      <div style={{ flex: 1, display: 'grid', gridTemplateColumns: '280px 1fr', minHeight: 0 }}>
        {/* Left: progress */}
        <aside style={{
          borderRight: '1px solid #e7e7ea', background: '#fff',
          padding: '28px 22px', display: 'flex', flexDirection: 'column'
        }}>
          <div className="uk-overline" style={{ marginBottom: 14 }}>Getting started</div>
          <ol style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
            {steps.map(s => {
              const state = s.n < step ? 'done' : s.n === step ? 'active' : 'pending';
              return (
                <li key={s.n} style={{
                  display: 'grid', gridTemplateColumns: '22px 1fr', gap: 10, alignItems: 'flex-start',
                  padding: '8px 8px', borderRadius: 6,
                  background: state === 'active' ? '#f4f4f5' : 'transparent'
                }}>
                  <span style={{
                    width: 22, height: 22, borderRadius: '50%',
                    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                    background: state === 'done' ? '#16794a' : state === 'active' ? '#18181b' : '#fff',
                    border: state === 'pending' ? '1px solid #d4d4d8' : 'none',
                    color: state === 'pending' ? '#8a8a92' : '#fff',
                    fontSize: 11, fontWeight: 600, marginTop: 1
                  }}>
                    {state === 'done' ? <Icon.Check style={{ color: '#fff' }}/> : s.n}
                  </span>
                  <div style={{ lineHeight: 1.35 }}>
                    <div style={{ fontSize: 13, fontWeight: state === 'active' ? 600 : 500, color: state === 'pending' ? '#8a8a92' : '#18181b' }}>
                      {s.label}
                    </div>
                    <div style={{ fontSize: 11, color: '#8a8a92' }}>{s.hint}</div>
                  </div>
                </li>
              );
            })}
          </ol>

          <div style={{ flex: 1 }}/>

          <div style={{
            marginTop: 16, padding: 12,
            border: '1px solid #e7e7ea', borderRadius: 7, background: '#fafafa'
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 4 }}>
              <Icon.Sparkle style={{ color: '#6f4ed3' }}/>
              <span style={{ fontSize: 12, fontWeight: 600 }}>Why workflows?</span>
            </div>
            <p className="uk-caption" style={{ fontSize: 11, lineHeight: 1.5 }}>
              Each project owns one workflow — the ordered statuses a task moves through. You can rename or add columns later.
            </p>
          </div>
        </aside>

        {/* Center: step content */}
        <main style={{ overflow: 'auto', padding: '48px 64px', display: 'flex', justifyContent: 'center' }}>
          <div style={{ width: '100%', maxWidth: 720 }}>
            {children}
          </div>
        </main>
      </div>

      {/* Footer */}
      <footer style={{
        height: 60, padding: '0 24px',
        borderTop: '1px solid #e7e7ea', background: '#fff',
        display: 'flex', alignItems: 'center', gap: 8
      }}>
        <span className="uk-caption" style={{ fontSize: 12 }}>
          Step <span style={{ color: '#18181b', fontWeight: 500 }}>{step}</span> of 3
        </span>
        <div style={{ flex: 1, display: 'flex', justifyContent: 'center', gap: 4 }}>
          {[1,2,3].map(n => (
            <span key={n} style={{
              width: n === step ? 22 : 6, height: 6, borderRadius: 3,
              background: n <= step ? '#18181b' : '#e7e7ea',
              transition: 'all 200ms ease'
            }}/>
          ))}
        </div>
        <button className="uk-btn uk-btn--ghost uk-btn--sm">Skip for now</button>
        <button className="uk-btn uk-btn--secondary uk-btn--sm" disabled={step === 1}>Back</button>
        <button className="uk-btn uk-btn--primary uk-btn--sm">
          {step === 3 ? 'Open workspace' : 'Continue'}
          {step < 3 && <Icon.Arrow/>}
        </button>
      </footer>
    </div>
  );
}

// ============================================================
// Step 1 — Project + workflow
// ============================================================
function OnboardingStep1() {
  const projectColors = ['#5e6ad2','#16794a','#a35c00','#4a8fd6','#b42318','#6f4ed3','#0f766e','#94a3a8'];
  const selectedColor = '#5e6ad2';

  const workflows = [
    {
      id: 'kanban',
      name: 'Kanban',
      sub: 'Classic 4-stage flow',
      active: true,
      cols: [
        ['To Do',       '#94a3a8'],
        ['In Progress', '#c98a14'],
        ['In Review',   '#4a8fd6'],
        ['Done',        '#16794a'],
      ],
    },
    {
      id: 'eng',
      name: 'Engineering',
      sub: 'Backlog → Spec → Build → Ship',
      cols: [
        ['Backlog',     '#94a3a8'],
        ['Spec',        '#6f4ed3'],
        ['Building',    '#c98a14'],
        ['Review',      '#4a8fd6'],
        ['Shipped',     '#16794a'],
      ],
    },
    {
      id: 'simple',
      name: 'Simple',
      sub: 'Just open/closed',
      cols: [
        ['Open',  '#94a3a8'],
        ['Done',  '#16794a'],
      ],
    },
    {
      id: 'custom',
      name: 'Start blank',
      sub: 'Define your own columns',
      empty: true,
    },
  ];

  return (
    <OnboardingShell step={1}>
      <div>
        <div className="uk-overline" style={{ marginBottom: 8 }}>Step 01</div>
        <h1 style={{ fontSize: 28, fontWeight: 600, letterSpacing: '-0.022em', lineHeight: 1.15, marginBottom: 6 }}>
          Create your first project
        </h1>
        <p style={{ fontSize: 14, color: '#52525b', lineHeight: 1.55, marginBottom: 28 }}>
          A project groups related tasks. Pick a name, then choose the workflow your team will use to move work to done.
        </p>

        {/* Project name */}
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 220px', gap: 12, marginBottom: 22 }}>
          <div className="uk-field">
            <label className="uk-label">Project name</label>
            <div className="uk-input-group" style={{ height: 34 }}>
              <span style={{ width: 12, height: 12, borderRadius: 3, background: selectedColor, flexShrink: 0 }}/>
              <input className="uk-input" defaultValue="Backend rewrite" style={{ fontSize: 14, fontWeight: 500 }}/>
              <span className="uk-mono" style={{ color: '#8a8a92', fontSize: 11 }}>BR-</span>
            </div>
            <span className="uk-hint">Used as the task ID prefix · e.g. <span className="uk-mono" style={{ background: '#f4f4f5', padding: '0 4px', borderRadius: 3 }}>BR-101</span></span>
          </div>
          <div className="uk-field">
            <label className="uk-label">Color</label>
            <div style={{ display: 'flex', gap: 5, alignItems: 'center', height: 34 }}>
              {projectColors.map(c => (
                <button key={c} style={{
                  width: 22, height: 22, borderRadius: 5,
                  background: c, border: 'none', padding: 0, cursor: 'pointer',
                  outline: c === selectedColor ? '2px solid #18181b' : 'none',
                  outlineOffset: 2
                }}/>
              ))}
            </div>
          </div>
        </div>

        <div className="uk-field" style={{ marginBottom: 28 }}>
          <label className="uk-label">Description <span className="uk-hint" style={{ marginLeft: 4 }}>optional</span></label>
          <textarea className="uk-textarea" rows={2} defaultValue="FrankenPHP + Redis migration. First milestone before v1.0."/>
        </div>

        {/* Workflow */}
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: 12 }}>
          <div>
            <div style={{ fontSize: 14, fontWeight: 600, letterSpacing: '-0.005em' }}>Workflow</div>
            <div className="uk-caption" style={{ fontSize: 12 }}>The statuses tasks move through. Pick a template — you can edit it after.</div>
          </div>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 10, marginBottom: 24 }}>
          {workflows.map(w => (
            <div key={w.id} style={{
              padding: 12,
              border: w.active ? '1px solid #5e6ad2' : '1px solid #e7e7ea',
              borderRadius: 7,
              background: w.active ? '#fff' : '#fff',
              boxShadow: w.active ? '0 0 0 3px rgba(94,106,210,0.15)' : 'none',
              cursor: 'pointer',
              display: 'flex', flexDirection: 'column', gap: 8
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                <span style={{
                  width: 14, height: 14, borderRadius: '50%',
                  border: w.active ? 'none' : '1px solid #d4d4d8',
                  background: w.active ? '#5e6ad2' : '#fff',
                  display: 'inline-flex', alignItems: 'center', justifyContent: 'center'
                }}>
                  {w.active && <span style={{ width: 6, height: 6, borderRadius: '50%', background: '#fff' }}/>}
                </span>
                <span style={{ fontSize: 13, fontWeight: 600 }}>{w.name}</span>
                <span className="uk-caption" style={{ fontSize: 11, marginLeft: 4 }}>{w.sub}</span>
              </div>
              {w.empty ? (
                <div style={{
                  height: 28, border: '1px dashed #d4d4d8', borderRadius: 5,
                  display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                  color: '#8a8a92', fontSize: 11
                }}>
                  <Icon.Plus/> Add columns
                </div>
              ) : (
                <div style={{ display: 'flex', gap: 3, flexWrap: 'wrap' }}>
                  {w.cols.map(([name, c], i) => (
                    <React.Fragment key={name}>
                      <span style={{
                        display: 'inline-flex', alignItems: 'center', gap: 4,
                        padding: '2px 7px', height: 20, borderRadius: 4,
                        background: '#f4f4f5', fontSize: 11, color: '#18181b'
                      }}>
                        <span style={{ width: 6, height: 6, borderRadius: '50%', background: c }}/>
                        {name}
                      </span>
                      {i < w.cols.length - 1 && (
                        <span style={{ color: '#b4b4ba', fontSize: 10, alignSelf: 'center' }}>›</span>
                      )}
                    </React.Fragment>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>

        {/* Edit workflow inline */}
        <div style={{
          padding: 14, border: '1px solid #e7e7ea', borderRadius: 8, background: '#fff'
        }}>
          <div style={{ display: 'flex', alignItems: 'center', marginBottom: 10 }}>
            <div style={{ fontSize: 13, fontWeight: 600 }}>Customise columns</div>
            <span className="uk-badge" style={{ marginLeft: 6 }}>Kanban</span>
            <span className="uk-caption" style={{ marginLeft: 'auto', fontSize: 11 }}>Drag · ⇅ to reorder</span>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            {[
              ['To Do',       '#94a3a8', false],
              ['In Progress', '#c98a14', false],
              ['In Review',   '#4a8fd6', false],
              ['Done',        '#16794a', true],
            ].map(([name, c, done]) => (
              <div key={name} style={{
                display: 'grid', gridTemplateColumns: '14px 14px 1fr 96px 28px', gap: 8,
                alignItems: 'center',
                padding: '6px 8px',
                border: '1px solid #e7e7ea',
                borderRadius: 5, background: '#fafafa'
              }}>
                <span style={{ color: '#b4b4ba', cursor: 'grab', fontSize: 13, lineHeight: 1, userSelect: 'none' }}>⋮⋮</span>
                <span style={{ width: 10, height: 10, borderRadius: '50%', background: c }}/>
                <input className="uk-input" defaultValue={name} style={{
                  border: 'none', background: 'transparent', height: 22, padding: 0, fontSize: 13, fontWeight: 500
                }}/>
                <span className="uk-badge" style={{ background: '#f4f4f5', color: '#52525b' }}>
                  {done ? 'completed' : 'in flight'}
                </span>
                <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" style={{ color: '#b4b4ba' }}><Icon.X/></button>
              </div>
            ))}
          </div>
          <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ marginTop: 8, padding: '0 6px', color: '#5e6ad2' }}>
            <Icon.Plus/> Add column
          </button>
        </div>
      </div>
    </OnboardingShell>
  );
}

// Live preview — mini kanban
function LivePreviewProject() {
  return (
    <div>
      <div className="uk-overline" style={{ marginBottom: 12 }}>Preview</div>
      <div style={{
        border: '1px solid #e7e7ea', borderRadius: 8, background: '#fff',
        padding: 12
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 12 }}>
          <span style={{ width: 8, height: 8, borderRadius: '50%', background: '#5e6ad2' }}/>
          <span style={{ fontSize: 13, fontWeight: 600 }}>Backend rewrite</span>
          <span className="uk-badge uk-badge--outline" style={{ marginLeft: 'auto' }}>new</span>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 8 }}>
          {[
            ['To Do', '#94a3a8', 0],
            ['In Progress', '#c98a14', 0],
            ['In Review', '#4a8fd6', 0],
            ['Done', '#16794a', 0],
          ].map(([name, c]) => (
            <div key={name} style={{
              padding: 8, background: '#fafafa',
              border: '1px solid #e7e7ea', borderRadius: 6,
              minHeight: 110, display: 'flex', flexDirection: 'column', gap: 6
            }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 5 }}>
                <span style={{ width: 6, height: 6, borderRadius: '50%', background: c }}/>
                <span style={{ fontSize: 11, fontWeight: 600 }}>{name}</span>
                <span style={{ fontSize: 10, color: '#b4b4ba', marginLeft: 'auto' }}>0</span>
              </div>
              <div style={{
                flex: 1, border: '1px dashed #e0e0e3', borderRadius: 4,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                color: '#b4b4ba', fontSize: 10
              }}>
                empty
              </div>
            </div>
          ))}
        </div>
      </div>

      <div style={{ marginTop: 16, padding: 12, border: '1px dashed #d4d4d8', borderRadius: 7, background: '#fff' }}>
        <div className="uk-overline" style={{ marginBottom: 6 }}>What gets created</div>
        <ul style={{ margin: 0, padding: 0, listStyle: 'none', display: 'flex', flexDirection: 'column', gap: 5, fontSize: 12, color: '#52525b' }}>
          <li style={{ display: 'flex', gap: 6 }}><Icon.Check style={{ color: '#16794a', marginTop: 2 }}/> Project <span className="uk-mono" style={{ background: '#f4f4f5', padding: '0 4px', borderRadius: 3 }}>BR</span> with 4 columns</li>
          <li style={{ display: 'flex', gap: 6 }}><Icon.Check style={{ color: '#16794a', marginTop: 2 }}/> Default labels: <span className="uk-mono">bug</span>, <span className="uk-mono">feature</span>, <span className="uk-mono">chore</span></li>
          <li style={{ display: 'flex', gap: 6 }}><Icon.Check style={{ color: '#16794a', marginTop: 2 }}/> Sample task <span className="uk-mono" style={{ background: '#f4f4f5', padding: '0 4px', borderRadius: 3 }}>BR-1</span></li>
        </ul>
      </div>
    </div>
  );
}

// ============================================================
// Step 2 — Invite teammates
// ============================================================
function OnboardingStep2() {
  const invitees = [
    ['jakub@ukolio.com', 'Admin',  '#dbeaff', '#1e58b6', 'JK'],
    ['eva@ukolio.com',   'Member', '#dcefe2', '#16794a', 'EP'],
    ['tomas@ukolio.com', 'Member', '#fbe5d6', '#a35c00', 'TN'],
    ['','',              '','',     ''],
  ];

  return (
    <OnboardingShell step={2}>
      <div>
        <div className="uk-overline" style={{ marginBottom: 8 }}>Step 02</div>
        <h1 style={{ fontSize: 28, fontWeight: 600, letterSpacing: '-0.022em', lineHeight: 1.15, marginBottom: 6 }}>
          Invite your team
        </h1>
        <p style={{ fontSize: 14, color: '#52525b', lineHeight: 1.55, marginBottom: 28 }}>
          They'll get an email with a one-click link into <span className="uk-mono" style={{ background: '#f4f4f5', padding: '0 4px', borderRadius: 3 }}>mskopal</span>. Add as many as you like — you can manage roles any time.
        </p>

        {/* Invite rows */}
        <div className="uk-overline" style={{ marginBottom: 8 }}>By email</div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginBottom: 12 }}>
          {invitees.map(([email, role, bg, fg, init], i) => (
            <div key={i} style={{
              display: 'grid', gridTemplateColumns: '28px 1fr 140px 28px', gap: 8, alignItems: 'center'
            }}>
              {email ? (
                <span className="uk-avatar" style={{ background: bg, color: fg, width: 26, height: 26, fontSize: 11 }}>{init}</span>
              ) : (
                <span style={{
                  width: 26, height: 26, borderRadius: '50%',
                  border: '1px dashed #d4d4d8', background: '#fafafa',
                  display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                  color: '#b4b4ba'
                }}><Icon.Plus/></span>
              )}
              <input className="uk-input" defaultValue={email} placeholder="name@company.com" style={{ height: 32, fontSize: 13 }}/>
              <select className="uk-select" defaultValue={role || 'Member'} style={{ height: 32 }}>
                <option>Owner</option>
                <option>Admin</option>
                <option>Member</option>
                <option>Viewer</option>
              </select>
              <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--sm" style={{ color: '#b4b4ba' }}>
                <Icon.X/>
              </button>
            </div>
          ))}
        </div>

        <div style={{ display: 'flex', gap: 8, marginBottom: 28 }}>
          <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ padding: '0 6px', color: '#5e6ad2' }}>
            <Icon.Plus/> Add another
          </button>
          <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ padding: '0 6px', color: '#52525b' }}>
            Paste a list
          </button>
        </div>

        {/* Bulk / domain */}
        <div className="uk-overline" style={{ marginBottom: 8 }}>Or share a link</div>
        <div style={{
          display: 'grid', gridTemplateColumns: '1fr 110px', gap: 6, marginBottom: 16
        }}>
          <div className="uk-input-group" style={{ height: 34 }}>
            <FIcon.Link style={{ color: '#8a8a92' }}/>
            <input
              className="uk-input"
              defaultValue="https://app.ukolio.com/join/mskopal?token=k9_4f2b…"
              readOnly
              style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: 12 }}
            />
            <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs"><FIcon.Copy/></button>
          </div>
          <button className="uk-btn uk-btn--secondary uk-btn--sm">
            Regenerate
          </button>
        </div>

        <div style={{
          padding: 14, border: '1px solid #e7e7ea', borderRadius: 8, background: '#fff',
          display: 'flex', flexDirection: 'column', gap: 10
        }}>
          <div style={{ fontSize: 13, fontWeight: 600 }}>Join rules</div>

          <label style={{ display: 'flex', alignItems: 'flex-start', gap: 10, cursor: 'pointer' }}>
            <span className="uk-toggle uk-toggle--on" style={{ marginTop: 2 }}/>
            <div style={{ lineHeight: 1.4 }}>
              <div style={{ fontSize: 13, color: '#18181b' }}>Anyone with <span className="uk-mono" style={{ background: '#f4f4f5', padding: '0 4px', borderRadius: 3 }}>@ukolio.com</span> can join</div>
              <div className="uk-caption" style={{ fontSize: 11 }}>They'll be added as Member automatically when they sign up with that domain.</div>
            </div>
          </label>

          <label style={{ display: 'flex', alignItems: 'flex-start', gap: 10, cursor: 'pointer' }}>
            <span className="uk-toggle" style={{ marginTop: 2 }}/>
            <div style={{ lineHeight: 1.4 }}>
              <div style={{ fontSize: 13, color: '#18181b' }}>Require Admin approval for new joins</div>
              <div className="uk-caption" style={{ fontSize: 11 }}>You'll get notified before they get access to projects.</div>
            </div>
          </label>
        </div>
      </div>
    </OnboardingShell>
  );
}

function LivePreviewTeam({ invitees }) {
  return (
    <div>
      <div className="uk-overline" style={{ marginBottom: 12 }}>Preview · workspace</div>
      <div style={{ border: '1px solid #e7e7ea', borderRadius: 8, background: '#fff', overflow: 'hidden' }}>
        <div style={{ padding: '10px 12px', borderBottom: '1px solid #e7e7ea', display: 'flex', alignItems: 'center', gap: 8 }}>
          <span style={{
            width: 22, height: 22, borderRadius: 5,
            background: '#18181b', color: '#fff',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            fontSize: 11, fontWeight: 600
          }}>M</span>
          <div style={{ lineHeight: 1.2 }}>
            <div style={{ fontSize: 12, fontWeight: 600 }}>mskopal</div>
            <div style={{ fontSize: 10, color: '#8a8a92' }}>{invitees.length + 1} member{invitees.length === 0 ? '' : 's'}</div>
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column' }}>
          <div style={{
            padding: '8px 12px', display: 'flex', alignItems: 'center', gap: 8, borderBottom: '1px solid #e7e7ea'
          }}>
            <span className="uk-avatar" style={{ background: '#fbe5d6', color: '#a35c00', width: 22, height: 22, fontSize: 10 }}>MS</span>
            <div style={{ lineHeight: 1.2 }}>
              <div style={{ fontSize: 12, fontWeight: 500 }}>Marek Skopal</div>
              <div style={{ fontSize: 10, color: '#8a8a92' }}>you · Owner</div>
            </div>
          </div>
          {invitees.map(([email, role, bg, fg, init]) => (
            <div key={email} style={{
              padding: '8px 12px', display: 'flex', alignItems: 'center', gap: 8, borderBottom: '1px solid #e7e7ea'
            }}>
              <span className="uk-avatar" style={{ background: bg, color: fg, width: 22, height: 22, fontSize: 10 }}>{init}</span>
              <div style={{ lineHeight: 1.2, minWidth: 0, flex: 1 }}>
                <div style={{ fontSize: 12, fontWeight: 500, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{email.split('@')[0]}</div>
                <div style={{ fontSize: 10, color: '#8a8a92' }}>{email}</div>
              </div>
              <span className="uk-badge" style={{ background: '#fbf2dd', color: '#a35c00' }}>
                pending · {role}
              </span>
            </div>
          ))}
        </div>
      </div>

      <div className="uk-alert" style={{ marginTop: 16, background: '#fff', alignItems: 'flex-start' }}>
        <Icon.Sparkle style={{ color: '#6f4ed3', marginTop: 2 }}/>
        <div>
          <div className="uk-alert-title">Tip · roles you can pick later</div>
          <div className="uk-alert-body" style={{ marginTop: 4, lineHeight: 1.5 }}>
            <b style={{ color: '#18181b', fontWeight: 600 }}>Admin</b> manages projects & billing · <b style={{ color: '#18181b', fontWeight: 600 }}>Member</b> can edit any task · <b style={{ color: '#18181b', fontWeight: 600 }}>Viewer</b> is read-only.
          </div>
        </div>
      </div>
    </div>
  );
}

// ============================================================
// Step 3 — You're set (success + agents)
// ============================================================
function OnboardingStep3() {
  return (
    <OnboardingShell step={3}>
      <div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 14 }}>
          <span style={{
            width: 28, height: 28, borderRadius: '50%', background: '#16794a',
            display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
            color: '#fff'
          }}>
            <Icon.Check style={{ width: 16, height: 16 }}/>
          </span>
          <span className="uk-overline" style={{ color: '#16794a' }}>Workspace ready</span>
        </div>
        <h1 style={{ fontSize: 28, fontWeight: 600, letterSpacing: '-0.022em', lineHeight: 1.15, marginBottom: 6 }}>
          You're set, Marek.
        </h1>
        <p style={{ fontSize: 14, color: '#52525b', lineHeight: 1.55, marginBottom: 28 }}>
          Your workspace, first project, and team are good to go. One optional step left: connect an MCP-compatible agent so Claude or Cursor can move tasks for you.
        </p>

        {/* Summary cards */}
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10, marginBottom: 28 }}>
          {[
            ['Workspace', 'mskopal', '4 seats'],
            ['First project', 'Backend rewrite', '4-column kanban'],
            ['Invites sent',  '3 pending', '@ukolio.com auto-join'],
          ].map(([label, val, sub]) => (
            <div key={label} className="uk-card" style={{ padding: 12 }}>
              <div className="uk-overline" style={{ fontSize: 10, marginBottom: 4 }}>{label}</div>
              <div style={{ fontSize: 15, fontWeight: 600, letterSpacing: '-0.01em', marginBottom: 2 }}>{val}</div>
              <div style={{ fontSize: 11, color: '#8a8a92' }}>{sub}</div>
            </div>
          ))}
        </div>

        {/* MCP step (optional) */}
        <div style={{ display: 'flex', alignItems: 'flex-end', marginBottom: 10 }}>
          <div>
            <div style={{ fontSize: 14, fontWeight: 600, letterSpacing: '-0.005em' }}>Connect an agent <span style={{ color: '#8a8a92', fontWeight: 400, fontSize: 12 }}>· optional</span></div>
            <div className="uk-caption" style={{ fontSize: 12 }}>Drop this snippet into your MCP client to give it scoped access to <span className="uk-mono">mskopal</span>.</div>
          </div>
        </div>

        <div style={{
          border: '1px solid #e7e7ea', borderRadius: 8,
          background: '#18181b', color: '#d4d4d8',
          padding: 14,
          fontFamily: 'JetBrains Mono, monospace', fontSize: 12, lineHeight: 1.65,
          position: 'relative', marginBottom: 16
        }}>
          <div style={{ position: 'absolute', top: 8, right: 8 }}>
            <button className="uk-btn uk-btn--ghost uk-btn--xs" style={{
              color: '#a1a1a8', background: '#23232a', border: '1px solid #2e2e36'
            }}>
              <FIcon.Copy/> Copy
            </button>
          </div>
          <div style={{ color: '#71717a', marginBottom: 4 }}># ~/.config/claude/claude.json</div>
          <div><span style={{ color: '#71717a' }}>"ukolio"</span>: {'{'}</div>
          <div>  <span style={{ color: '#71717a' }}>"url"</span>: <span style={{ color: '#a3b5e8' }}>"https://app.ukolio.com/api/mcp"</span>,</div>
          <div>  <span style={{ color: '#71717a' }}>"transport"</span>: <span style={{ color: '#a3b5e8' }}>"http"</span>,</div>
          <div>  <span style={{ color: '#71717a' }}>"workspace"</span>: <span style={{ color: '#a3b5e8' }}>"mskopal"</span></div>
          <div>{'}'}</div>
        </div>

        <div style={{ display: 'flex', gap: 8, marginBottom: 24 }}>
          <button className="uk-btn uk-btn--secondary uk-btn--sm">
            <Icon.Sparkle style={{ color: '#6f4ed3' }}/>
            Open in Claude Desktop
          </button>
          <button className="uk-btn uk-btn--secondary uk-btn--sm">Open in Cursor</button>
          <button className="uk-btn uk-btn--ghost uk-btn--sm">View docs</button>
        </div>

        <div className="uk-alert uk-alert--ai">
          <Icon.Sparkle style={{ color: '#6f4ed3', marginTop: 2 }}/>
          <div>
            <div className="uk-alert-title">No agent? No problem.</div>
            <div className="uk-alert-body" style={{ marginTop: 2 }}>
              You can skip this — invite agents from <span className="uk-mono">Settings › MCP & agents</span> whenever you're ready.
            </div>
          </div>
        </div>
      </div>
    </OnboardingShell>
  );
}

function LivePreviewDone() {
  return (
    <div>
      <div className="uk-overline" style={{ marginBottom: 12 }}>Up next</div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        {[
          { icon: '📋', t: 'Create your first task', sub: 'Use ⌘N anywhere in the app.', kbd: '⌘N' },
          { icon: '🏷', t: 'Add a few tags',        sub: 'Set up the chip library.',     kbd: null },
          { icon: '🔗', t: 'Link related tasks',     sub: 'Use parent / depends-on relations.', kbd: null },
          { icon: '🔔', t: 'Tune notifications',     sub: 'Slack, email, or in-app only.', kbd: null },
        ].map(c => (
          <div key={c.t} style={{
            display: 'grid', gridTemplateColumns: '28px 1fr auto', gap: 10,
            padding: '10px 12px', background: '#fff',
            border: '1px solid #e7e7ea', borderRadius: 7, alignItems: 'center'
          }}>
            <span style={{
              width: 28, height: 28, borderRadius: 5, background: '#f4f4f5',
              display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 14
            }}>{c.icon}</span>
            <div style={{ lineHeight: 1.35 }}>
              <div style={{ fontSize: 12.5, fontWeight: 500 }}>{c.t}</div>
              <div style={{ fontSize: 11, color: '#8a8a92' }}>{c.sub}</div>
            </div>
            {c.kbd ? <span className="uk-kbd">{c.kbd}</span> : <Icon.Arrow style={{ color: '#b4b4ba' }}/>}
          </div>
        ))}
      </div>

      <div style={{
        marginTop: 16, padding: 14, borderRadius: 8,
        background: 'linear-gradient(180deg, #eef0fb 0%, #fafafa 100%)',
        border: '1px solid #d8def2'
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 6 }}>
          <Icon.Sparkle style={{ color: '#5e6ad2' }}/>
          <span style={{ fontSize: 12, fontWeight: 600 }}>Welcome to Ukolio</span>
        </div>
        <p className="uk-caption" style={{ fontSize: 11, lineHeight: 1.55 }}>
          A 14-day free trial is active. No card required. We'll send a recap to <span className="uk-mono">marek@ukolio.com</span>.
        </p>
      </div>
    </div>
  );
}

Object.assign(window, {
  OnboardingStep1, OnboardingStep2, OnboardingStep3
});
