// ============================================================
// Ukolio — Mobile marketing site
// Landing · Pricing · Contact · Text/Legal — adapted for ~402px
// Rendered inside IOSDevice frames. Reuses tokens & language
// from landing.jsx / marketing-pages.jsx
// ============================================================

// =============================================================
//  Shared mobile chrome
// =============================================================
function MobileTopBar({ menuOpen = false, onToggle = () => {} }) {
  return (
    <header style={{
      position: 'sticky', top: 0, zIndex: 4,
      height: 52,
      display: 'flex', alignItems: 'center', justifyContent: 'space-between',
      padding: '0 18px',
      background: 'rgba(250,250,250,0.92)',
      backdropFilter: 'saturate(140%) blur(8px)',
      WebkitBackdropFilter: 'saturate(140%) blur(8px)',
      borderBottom: '1px solid var(--uk-border)',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        <Mark size={22} />
        <span style={{ fontWeight: 600, letterSpacing: '-0.02em', fontSize: 15 }}>ukolio</span>
      </div>
      <button
        aria-label="Menu"
        onClick={onToggle}
        style={{
          width: 36, height: 36, borderRadius: 8,
          border: '1px solid var(--uk-border)',
          background: 'var(--uk-surface)',
          display: 'grid', placeItems: 'center', cursor: 'pointer',
        }}>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="var(--uk-fg)" strokeWidth="1.8" strokeLinecap="round">
          {menuOpen
            ? <><path d="M6 6l12 12"/><path d="M18 6L6 18"/></>
            : <><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></>}
        </svg>
      </button>
    </header>
  );
}

function MobileMenu({ open }) {
  if (!open) return null;
  const items = ['Features', 'For agents', 'Built right', 'Docs', 'Pricing'];
  return (
    <div style={{
      position: 'absolute', top: 52, left: 0, right: 0, zIndex: 3,
      background: 'var(--uk-surface)',
      borderBottom: '1px solid var(--uk-border)',
      padding: '6px 0 14px',
      boxShadow: 'var(--uk-shadow-md)',
    }}>
      {items.map(it => (
        <a key={it} href="#" style={{
          display: 'block', padding: '12px 20px',
          fontSize: 15, color: 'var(--uk-fg)', textDecoration: 'none',
          fontWeight: 500, letterSpacing: '-0.005em',
          borderBottom: '1px solid var(--uk-border)',
        }}>{it}</a>
      ))}
      <div style={{ display: 'flex', gap: 8, padding: '14px 20px 4px' }}>
        <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ flex: 1 }}>Sign in</button>
        <button className="uk-btn uk-btn--primary uk-btn--sm" style={{ flex: 1 }}>Get started</button>
      </div>
    </div>
  );
}

function MobileFooter() {
  return (
    <footer style={{
      borderTop: '1px solid var(--uk-border)',
      padding: '24px 20px 32px',
      background: 'var(--uk-surface)',
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 14 }}>
        <Mark size={20} />
        <span style={{ fontWeight: 600, color: 'var(--uk-fg)', letterSpacing: '-0.015em', fontSize: 14 }}>ukolio</span>
      </div>
      <div style={{
        display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '10px 16px',
        fontSize: 13, color: 'var(--uk-fg-muted)',
        marginBottom: 18,
      }}>
        {['Open app', 'Features', 'Pricing', 'Security', 'Docs', 'Contact'].map(l => (
          <a key={l} href="#" style={{ color: 'inherit', textDecoration: 'none' }}>{l}</a>
        ))}
      </div>
      <div style={{ fontSize: 11.5, color: 'var(--uk-fg-subtle)' }}>
        © 2026 Ukolio s.r.o.
      </div>
    </footer>
  );
}

// page wrapper — gives every mobile page the same chrome + bg
function MobilePage({ children }) {
  const [open, setOpen] = React.useState(false);
  return (
    <div className="uk" style={{
      width: '100%', minHeight: '100%',
      background: 'var(--uk-bg)',
      position: 'relative',
    }}>
      <MobileTopBar menuOpen={open} onToggle={() => setOpen(!open)} />
      <MobileMenu open={open} />
      <main>{children}</main>
      <MobileFooter />
    </div>
  );
}

// =============================================================
//  1. MOBILE LANDING
// =============================================================
function MobileLandingScreen() {
  return (
    <MobilePage>
      <MLHero />
      <MLPillars />
      <MLBoardPreview />
      <MLFeatures />
      <MLTrust />
      <MLCTA />
    </MobilePage>
  );
}

function MLHero() {
  return (
    <section style={{ padding: '36px 20px 12px' }}>
      <span style={{
        display: 'inline-flex', alignItems: 'center', gap: 6,
        padding: '3px 10px 3px 5px',
        background: 'var(--uk-ai-soft)', color: 'var(--uk-ai)',
        border: '1px solid var(--uk-ai-border)',
        borderRadius: 999, fontSize: 11, fontWeight: 500,
      }}>
        <span style={{
          width: 14, height: 14, borderRadius: 999,
          background: 'var(--uk-ai)', color: '#fff',
          display: 'grid', placeItems: 'center', fontSize: 8,
        }}>★</span>
        MCP-native · Open source
      </span>

      <h1 style={{
        marginTop: 18,
        fontSize: 36, lineHeight: 1.08, letterSpacing: '-0.028em',
        fontWeight: 600, color: 'var(--uk-fg)',
        textWrap: 'pretty',
      }}>
        The Kanban your{' '}
        <em style={{
          fontStyle: 'normal',
          background: 'linear-gradient(120deg, var(--uk-accent), var(--uk-ai))',
          WebkitBackgroundClip: 'text', backgroundClip: 'text',
          color: 'transparent',
        }}>agents</em>{' '}
        can drive.
      </h1>

      <p style={{
        marginTop: 14,
        fontSize: 15, lineHeight: 1.55, color: 'var(--uk-fg-muted)',
      }}>
        A multi-tenant task manager built around the Model Context Protocol.
        Claude, Cursor, ChatGPT — any MCP client — plans, creates, moves, and
        closes tasks alongside your team.
      </p>

      <div style={{ marginTop: 22, display: 'flex', flexDirection: 'column', gap: 8 }}>
        <button className="uk-btn uk-btn--primary uk-btn--lg" style={{ width: '100%' }}>
          Start a free workspace
        </button>
        <button className="uk-btn uk-btn--secondary uk-btn--lg" style={{ width: '100%' }}>
          See how agents use it
        </button>
      </div>

      <div style={{
        marginTop: 20, display: 'flex', gap: 14, flexWrap: 'wrap',
        fontSize: 11.5, color: 'var(--uk-fg-subtle)',
      }}>
        {['Self-hostable', 'MIT', 'Multi-tenant'].map(t => (
          <span key={t} style={{ display: 'inline-flex', alignItems: 'center', gap: 5 }}>
            <span style={{ width: 5, height: 5, borderRadius: 999, background: 'var(--uk-success)' }} />
            {t}
          </span>
        ))}
      </div>
    </section>
  );
}

function MLBoardPreview() {
  const Card = ({ id, title, badge }) => (
    <div className="uk-task" style={{ marginBottom: 8, padding: 12 }}>
      <div className="uk-task-id" style={{ fontSize: 10.5 }}>{id}</div>
      <div className="uk-task-title" style={{ fontSize: 13.5, lineHeight: 1.35 }}>{title}</div>
      {badge && (
        <div style={{ marginTop: 6 }}>
          <span className={`uk-badge ${badge.cls}`} style={{ fontSize: 10 }}>{badge.label}</span>
        </div>
      )}
    </div>
  );

  const cols = [
    { dot: 'var(--uk-status-todo)', label: 'To do', count: 3, cards: [
      { id: 'BE-217', title: 'Rate-limit refresh-token endpoint', badge: { cls: 'uk-badge--danger', label: 'High' } },
      { id: 'BE-219', title: 'Backfill workspace.owner_id for legacy rows' },
      { id: 'BE-220', title: 'Add OpenTelemetry tracing to MCP transport', badge: { cls: 'uk-badge--ai', label: 'via Cursor' } },
    ]},
    { dot: 'var(--uk-status-doing)', label: 'In progress', count: 2, cards: [
      { id: 'BE-214', title: 'Migrate file attachments to S3-compatible storage', badge: { cls: 'uk-badge--solid', label: 'Urgent' } },
      { id: 'BE-216', title: 'Custom-field validation for Version type' },
    ]},
    { dot: 'var(--uk-status-done)', label: 'Done', count: 4, cards: [
      { id: 'BE-210', title: 'OAuth 2.1 dynamic client registration', badge: { cls: 'uk-badge--ai', label: 'closed by ChatGPT' } },
      { id: 'BE-211', title: 'Workspace-scoped tag catalog' },
    ]},
  ];

  return (
    <section style={{ padding: '8px 20px 16px' }}>
      <div style={{
        background: 'var(--uk-surface)',
        border: '1px solid var(--uk-border)',
        borderRadius: 12,
        boxShadow: 'var(--uk-shadow-md)',
        overflow: 'hidden',
      }}>
        {/* board header */}
        <div style={{
          display: 'flex', alignItems: 'center', justifyContent: 'space-between',
          padding: '12px 14px',
          borderBottom: '1px solid var(--uk-border)',
          fontSize: 12, color: 'var(--uk-fg-muted)',
        }}>
          <div><strong style={{ color: 'var(--uk-fg)' }}>Acme · Backend</strong>&nbsp;·&nbsp;Sprint 14</div>
          <span className="uk-badge uk-badge--ai" style={{ fontSize: 10 }}>3 by agents</span>
        </div>

        {/* scrollable column area */}
        <div style={{ position: 'relative' }}>
          <div style={{
            display: 'flex', gap: 12,
            padding: 14,
            overflowX: 'auto',
            scrollSnapType: 'x mandatory',
            WebkitOverflowScrolling: 'touch',
            scrollbarWidth: 'none',
          }}>
            <style>{`section .kanban-scroll::-webkit-scrollbar { display: none; }`}</style>
            {cols.map((col, i) => (
              <div key={col.label} style={{
                /* first column near-full width; subsequent columns peek */
                flex: i === 0 ? '0 0 calc(100% - 56px)' : '0 0 calc(100% - 56px)',
                scrollSnapAlign: 'start',
                display: 'flex', flexDirection: 'column',
              }}>
                <div style={{
                  display: 'flex', alignItems: 'center', justifyContent: 'space-between',
                  marginBottom: 10,
                }}>
                  <div style={{
                    display: 'flex', alignItems: 'center', gap: 8,
                    fontSize: 10.5, fontWeight: 600, textTransform: 'uppercase',
                    letterSpacing: '0.06em', color: 'var(--uk-fg-subtle)',
                  }}>
                    <span style={{ width: 7, height: 7, borderRadius: 999, background: col.dot }} />
                    {col.label}
                  </div>
                  <span style={{
                    fontSize: 10.5, color: 'var(--uk-fg-subtle)',
                    fontFamily: 'var(--uk-font-mono)',
                  }}>{col.count}</span>
                </div>
                {col.cards.map(c => <Card key={c.id} {...c} />)}
              </div>
            ))}
          </div>

          {/* right-edge fade — signals scrollability */}
          <div style={{
            position: 'absolute', top: 0, right: 0, bottom: 0,
            width: 64, pointerEvents: 'none',
            background: 'linear-gradient(to left, var(--uk-surface) 12%, rgba(255,255,255,0) 100%)',
          }} />

          {/* tiny right-edge chevron hint */}
          <div style={{
            position: 'absolute', top: '50%', right: 8,
            transform: 'translateY(-50%)',
            width: 26, height: 26, borderRadius: 999,
            background: 'var(--uk-surface)',
            border: '1px solid var(--uk-border)',
            boxShadow: '0 2px 6px rgba(0,0,0,0.06)',
            display: 'grid', placeItems: 'center',
            pointerEvents: 'none',
          }}>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                 stroke="var(--uk-fg-muted)" strokeWidth="2"
                 strokeLinecap="round" strokeLinejoin="round">
              <polyline points="9 6 15 12 9 18"/>
            </svg>
          </div>
        </div>

        {/* pagination dots */}
        <div style={{
          display: 'flex', justifyContent: 'center', gap: 6,
          padding: '4px 0 14px',
        }}>
          {cols.map((_, i) => (
            <span key={i} style={{
              width: i === 0 ? 18 : 6, height: 6, borderRadius: 999,
              background: i === 0 ? 'var(--uk-fg)' : 'var(--uk-border-strong, var(--uk-border))',
              transition: 'width 160ms',
            }}/>
          ))}
        </div>
      </div>
    </section>
  );
}

function MLPillars() {
  const items = [
    {
      icon: <LIcon.Layers />,
      title: 'MCP-native',
      body: 'Streamable HTTP, Redis sessions, every domain operation exposed as a typed MCP tool.',
    },
    {
      icon: <LIcon.Lock />,
      title: 'OAuth 2.1 + PKCE',
      body: 'No shared keys. Agents register dynamically, request consent, get revocable tokens.',
    },
    {
      icon: <LIcon.Clock />,
      title: 'Who did what',
      body: 'Every event tagged Human or Agent, with the MCP client preserved in the audit log.',
    },
  ];
  return (
    <section style={{ padding: '32px 20px', borderTop: '1px solid var(--uk-border)' }}>
      <MSectionHead eyebrow="Why Ukolio" title="Made for humans and agents to share." />
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        {items.map(it => (
          <div key={it.title} className="uk-card" style={{ padding: 16 }}>
            <div style={{
              display: 'flex', alignItems: 'center', gap: 10, marginBottom: 8,
            }}>
              <span style={{
                width: 28, height: 28, borderRadius: 7,
                background: 'var(--uk-accent-soft)', color: 'var(--uk-accent)',
                display: 'grid', placeItems: 'center',
              }}>{it.icon}</span>
              <h3 style={{ fontSize: 14.5, fontWeight: 600, letterSpacing: '-0.012em' }}>{it.title}</h3>
            </div>
            <p style={{ fontSize: 13, lineHeight: 1.55, color: 'var(--uk-fg-muted)' }}>{it.body}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

function MLFeatures() {
  const items = [
    { icon: <LIcon.Kanban/>, title: 'Kanban',         body: 'Per-project workflows, drag-drop columns, stable PROJECT-N task IDs.' },
    { icon: <LIcon.Grid/>,   title: 'Workspace grid', body: 'Search, multi-status filter, sortable columns across every project.' },
    { icon: <LIcon.Tag/>,    title: 'Tags & relations', body: 'Workspace tags. Parent / DependsOn / Related between tasks.' },
    { icon: <LIcon.Check2/>, title: 'Custom fields',  body: 'Text, Textarea, Select, semver Version. Reachable from MCP.' },
    { icon: <LIcon.Upload/>, title: 'Attachments',    body: 'S3-compatible storage. Agents fetch by ID, not by scraping.' },
    { icon: <LIcon.Doc/>,    title: 'Event log',      body: 'Append-only typed event stream — per project, workspace, task.' },
  ];
  return (
    <section style={{ padding: '32px 20px', borderTop: '1px solid var(--uk-border)' }}>
      <MSectionHead
        eyebrow="Everything you'd expect"
        title="A complete task manager. Just one your tools can use."
      />
      <div style={{
        border: '1px solid var(--uk-border)', borderRadius: 10,
        background: 'var(--uk-surface)', overflow: 'hidden',
      }}>
        {items.map((it, i) => (
          <div key={it.title} style={{
            padding: '14px 16px',
            borderTop: i === 0 ? 'none' : '1px solid var(--uk-border)',
          }}>
            <h3 style={{
              fontSize: 13.5, fontWeight: 600, marginBottom: 4,
              display: 'flex', alignItems: 'center', gap: 8,
              letterSpacing: '-0.008em',
            }}>
              <span style={{ color: 'var(--uk-fg-subtle)', display: 'inline-flex' }}>{it.icon}</span>
              {it.title}
            </h3>
            <p style={{ fontSize: 12.5, lineHeight: 1.55, color: 'var(--uk-fg-muted)' }}>{it.body}</p>
          </div>
        ))}
      </div>
    </section>
  );
}

function MLTrust() {
  const items = [
    'OAuth 2.1 + PKCE, S256 only',
    'RFC 9728 discovery for MCP clients',
    'Strict CSP, per-request nonces',
    'One-click export, one-click delete',
    'PHPStan max · zero-warning lint',
    'MIT licensed · self-hostable',
  ];
  return (
    <section style={{ padding: '32px 20px', borderTop: '1px solid var(--uk-border)' }}>
      <MSectionHead eyebrow="Built right" title="The boring details, done seriously." />
      <ul style={{
        listStyle: 'none', padding: 0, margin: 0,
        display: 'flex', flexDirection: 'column', gap: 10,
      }}>
        {items.map(it => (
          <li key={it} style={{
            display: 'flex', gap: 10, alignItems: 'flex-start',
            fontSize: 13.5, lineHeight: 1.45,
          }}>
            <span style={{
              flexShrink: 0, marginTop: 1,
              width: 18, height: 18, borderRadius: 999,
              background: 'var(--uk-success-soft)', color: 'var(--uk-success)',
              display: 'grid', placeItems: 'center',
            }}>
              <LIcon.Check2 />
            </span>
            {it}
          </li>
        ))}
      </ul>
    </section>
  );
}

function MLCTA() {
  return (
    <section style={{ padding: '24px 20px 36px' }}>
      <div style={{
        background: 'var(--uk-fg)', color: '#fff',
        borderRadius: 12, padding: '28px 22px',
        textAlign: 'center',
        backgroundImage: 'radial-gradient(circle at 20% 0%, rgba(94,106,210,0.20), transparent 60%), radial-gradient(circle at 80% 100%, rgba(111,78,211,0.18), transparent 60%)',
      }}>
        <h2 style={{
          fontSize: 22, lineHeight: 1.2, letterSpacing: '-0.02em',
          fontWeight: 600, color: '#fff',
        }}>
          Stop describing tasks. Let your agent manage them.
        </h2>
        <p style={{
          margin: '10px auto 0', maxWidth: 320,
          color: '#a1a1aa', fontSize: 13.5, lineHeight: 1.5,
        }}>
          Free to try. Free to self-host. One minute to connect your first MCP client.
        </p>
        <div style={{ marginTop: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <button className="uk-btn uk-btn--lg" style={{
            background: '#fff', color: 'var(--uk-fg)', borderColor: '#fff', width: '100%',
          }}>Create a workspace</button>
          <button className="uk-btn uk-btn--lg" style={{
            background: 'transparent', color: '#fff', borderColor: '#3f3f46', width: '100%',
          }}>Sign in</button>
        </div>
      </div>
    </section>
  );
}

function MSectionHead({ eyebrow, title }) {
  return (
    <div style={{ marginBottom: 18 }}>
      <div style={{
        fontSize: 10.5, fontWeight: 600, letterSpacing: '0.1em',
        textTransform: 'uppercase', color: 'var(--uk-accent)',
        marginBottom: 8,
      }}>{eyebrow}</div>
      <h2 style={{
        fontSize: 22, lineHeight: 1.2, letterSpacing: '-0.02em',
        fontWeight: 600, color: 'var(--uk-fg)', textWrap: 'pretty',
      }}>{title}</h2>
    </div>
  );
}

// =============================================================
//  2. MOBILE PRICING
// =============================================================
function MobilePricingScreen() {
  const [annual, setAnnual] = React.useState(true);

  return (
    <MobilePage>
      <section style={{ padding: '32px 20px 12px' }}>
        <div style={{
          fontSize: 10.5, fontWeight: 600, letterSpacing: '0.1em',
          textTransform: 'uppercase', color: 'var(--uk-accent)', marginBottom: 8,
        }}>Pricing</div>
        <h1 style={{
          fontSize: 32, lineHeight: 1.1, letterSpacing: '-0.028em',
          fontWeight: 600,
        }}>Simple pricing.</h1>
        <p style={{
          marginTop: 12, fontSize: 14.5, lineHeight: 1.55, color: 'var(--uk-fg-muted)',
        }}>
          Pay per active member. Cancel anytime. Self-host the open-source edition free.
        </p>

        {/* segmented toggle */}
        <div style={{
          marginTop: 20,
          display: 'inline-flex', padding: 4,
          background: 'var(--uk-surface)',
          border: '1px solid var(--uk-border)',
          borderRadius: 999,
        }}>
          {[
            { id: 'monthly', label: 'Monthly' },
            { id: 'annual',  label: 'Annual'  },
          ].map(opt => {
            const active = (opt.id === 'annual') === annual;
            return (
              <button key={opt.id}
                      onClick={() => setAnnual(opt.id === 'annual')}
                      style={{
                        border: 'none', cursor: 'pointer',
                        height: 30, padding: '0 14px', borderRadius: 999,
                        fontSize: 12.5, fontWeight: 500,
                        background: active ? 'var(--uk-fg)' : 'transparent',
                        color: active ? '#fff' : 'var(--uk-fg-muted)',
                        display: 'inline-flex', alignItems: 'center', gap: 6,
                      }}>
                {opt.label}
                {opt.id === 'annual' && (
                  <span style={{
                    fontSize: 9.5, fontWeight: 600,
                    padding: '1px 5px', borderRadius: 999,
                    background: active ? 'rgba(255,255,255,0.16)' : 'var(--uk-success-soft)',
                    color: active ? '#fff' : 'var(--uk-success)',
                  }}>−20%</span>
                )}
              </button>
            );
          })}
        </div>
      </section>

      {/* plan cards stacked */}
      <section style={{ padding: '16px 20px 24px' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <MPlanCard
            name="Free"
            blurb="For individuals and small teams trying things out."
            price={0}
            suffix="forever"
            cta="Start free"
            ctaClass="uk-btn--secondary"
            features={[
              '1 workspace, up to 3 members',
              'Up to 5 projects, unlimited tasks',
              'Connect 1 MCP client',
              'Community support',
            ]}
          />
          <MPlanCard
            highlighted
            badge="Most teams"
            name="Pro"
            blurb="For teams who run work through agents."
            price={annual ? 9 : 12}
            suffix={annual ? 'per member · billed annually' : 'per member · billed monthly'}
            cta="Start 14-day trial"
            ctaClass="uk-btn--primary"
            features={[
              'Unlimited workspaces & members',
              'Unlimited MCP clients per user',
              'Custom fields, tags, relations',
              '1 GB / file · 1 yr activity log',
              'SSO (SAML / OIDC)',
              'Priority support · 99.9% SLA',
            ]}
          />
        </div>
      </section>

      {/* Community block */}
      <section style={{ padding: '20px', borderTop: '1px solid var(--uk-border)' }}>
        <div style={{
          background: 'var(--uk-fg)', color: 'var(--uk-bg)',
          borderRadius: 12, padding: 22,
        }}>
          <div style={{
            fontSize: 10.5, fontWeight: 600, letterSpacing: '0.1em',
            textTransform: 'uppercase', opacity: 0.6, marginBottom: 10,
          }}>Community · MIT</div>
          <h2 style={{
            fontSize: 22, lineHeight: 1.2, letterSpacing: '-0.02em',
            fontWeight: 600, marginBottom: 10,
          }}>Self-host it. Free.</h2>
          <p style={{
            fontSize: 13.5, lineHeight: 1.55, opacity: 0.78,
          }}>
            The full source — server, web client, MCP bridge — published under MIT.
            Run it on your own hardware.
          </p>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 16 }}>
            <a href="https://github.com/ukolio/ukolio"
               style={{
                 display: 'inline-flex', alignItems: 'center', justifyContent: 'center', gap: 8,
                 background: 'var(--uk-bg)', color: 'var(--uk-fg)',
                 padding: '11px 16px', borderRadius: 8,
                 fontSize: 13.5, fontWeight: 500, textDecoration: 'none',
               }}>
              <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden>
                <path d="M8 0C3.58 0 0 3.58 0 8a8 8 0 005.47 7.59c.4.07.55-.17.55-.38
                         0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13
                         -.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87
                         2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95
                         0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82
                         a7.5 7.5 0 014 0c1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12
                         .51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48
                         0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8
                         c0-4.42-3.58-8-8-8z"/>
              </svg>
              View on GitHub
            </a>
          </div>

          <div style={{
            marginTop: 14, display: 'flex', gap: 16, flexWrap: 'wrap',
            fontSize: 11, opacity: 0.65,
          }}>
            <span><b style={{ fontWeight: 600 }}>4.2k</b> stars</span>
            <span><b style={{ fontWeight: 600 }}>312</b> forks</span>
            <span style={{ fontFamily: 'var(--uk-font-mono)' }}>MIT</span>
          </div>

          <div style={{
            marginTop: 16, padding: '10px 12px', borderRadius: 8,
            background: 'rgba(255,255,255,0.05)',
            border: '1px solid rgba(255,255,255,0.08)',
            fontFamily: 'var(--uk-font-mono)',
            fontSize: 11, lineHeight: 1.55, opacity: 0.7,
          }}>
            {'// '}Provided <b style={{ fontWeight: 600 }}>“as is”</b>, without
            warranty. No support, no SLA.
          </div>
        </div>
      </section>

      {/* FAQ */}
      <section style={{ padding: '32px 20px', borderTop: '1px solid var(--uk-border)' }}>
        <MSectionHead eyebrow="FAQ" title="Things people ask." />
        <MFaq items={[
          { q: 'Can I self-host?',
            a: 'Yes. MIT licensed. docker compose up gives you the hosted experience locally.' },
          { q: 'How is a "member" counted?',
            a: 'Anyone added to one of your workspaces who accepted the invitation.' },
          { q: 'Do agents count as members?',
            a: 'No. Agents authenticate as a user — included in that member\'s seat.' },
          { q: 'What if I cancel?',
            a: 'You keep 90 days of read-only access plus a full export. Then deletion.' },
        ]}/>
      </section>
    </MobilePage>
  );
}

function MPlanCard({ name, blurb, price, suffix, cta, ctaClass, features, highlighted, badge }) {
  return (
    <div className="uk-card" style={{
      padding: 20,
      borderColor: highlighted ? 'var(--uk-fg)' : 'var(--uk-border)',
      borderWidth: highlighted ? 1.5 : 1,
      boxShadow: highlighted ? 'var(--uk-shadow-md)' : 'none',
      position: 'relative',
    }}>
      {badge && (
        <span style={{
          position: 'absolute', top: -10, left: 20,
          background: 'var(--uk-fg)', color: '#fff',
          fontSize: 10.5, fontWeight: 500,
          padding: '3px 9px', borderRadius: 999,
        }}>{badge}</span>
      )}
      <h3 style={{ fontSize: 17, fontWeight: 600, letterSpacing: '-0.012em' }}>{name}</h3>
      <p style={{ marginTop: 4, fontSize: 12.5, color: 'var(--uk-fg-muted)', lineHeight: 1.5 }}>{blurb}</p>

      <div style={{ marginTop: 14, display: 'flex', alignItems: 'flex-end', gap: 4 }}>
        <span style={{ fontSize: 36, fontWeight: 600, letterSpacing: '-0.028em', lineHeight: 1 }}>${price}</span>
        <span style={{ fontSize: 12, color: 'var(--uk-fg-subtle)', marginBottom: 4 }}>
          {price === 0 ? '' : '/ mo'}
        </span>
      </div>
      <div style={{ fontSize: 11.5, color: 'var(--uk-fg-subtle)', marginTop: 2 }}>{suffix}</div>

      <button className={`uk-btn ${ctaClass} uk-btn--lg`}
              style={{ marginTop: 16, width: '100%' }}>{cta}</button>

      <hr className="uk-hr" style={{ margin: '16px 0 12px' }} />

      <ul style={{
        listStyle: 'none', padding: 0, margin: 0,
        display: 'flex', flexDirection: 'column', gap: 8,
      }}>
        {features.map(f => (
          <li key={f} style={{
            display: 'flex', gap: 9, alignItems: 'flex-start',
            fontSize: 12.5, lineHeight: 1.45, color: 'var(--uk-fg)',
          }}>
            <span style={{
              flexShrink: 0, marginTop: 3,
              width: 13, height: 13, borderRadius: 999,
              background: 'var(--uk-success-soft)', color: 'var(--uk-success)',
              display: 'grid', placeItems: 'center',
            }}>
              <svg width="8" height="8" viewBox="0 0 16 16" fill="none"
                   stroke="currentColor" strokeWidth="2.6"
                   strokeLinecap="round" strokeLinejoin="round">
                <path d="M3 8.5l3 3 6-7"/>
              </svg>
            </span>
            {f}
          </li>
        ))}
      </ul>
    </div>
  );
}

function MFaq({ items }) {
  const [open, setOpen] = React.useState(0);
  return (
    <div style={{
      border: '1px solid var(--uk-border)', borderRadius: 10,
      background: 'var(--uk-surface)', overflow: 'hidden',
    }}>
      {items.map((it, i) => {
        const isOpen = i === open;
        return (
          <div key={it.q} style={{
            borderTop: i === 0 ? 'none' : '1px solid var(--uk-border)',
          }}>
            <button
              onClick={() => setOpen(isOpen ? -1 : i)}
              style={{
                width: '100%', textAlign: 'left',
                padding: '14px 16px',
                background: 'transparent', border: 'none', cursor: 'pointer',
                display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10,
              }}>
              <span style={{ fontSize: 13.5, fontWeight: 600, color: 'var(--uk-fg)', letterSpacing: '-0.008em' }}>{it.q}</span>
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                   stroke="var(--uk-fg-subtle)" strokeWidth="2"
                   strokeLinecap="round" strokeLinejoin="round"
                   style={{ transform: isOpen ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform 120ms' }}>
                <polyline points="6 9 12 15 18 9"/>
              </svg>
            </button>
            {isOpen && (
              <div style={{
                padding: '0 16px 14px',
                fontSize: 13, lineHeight: 1.55, color: 'var(--uk-fg-muted)',
              }}>{it.a}</div>
            )}
          </div>
        );
      })}
    </div>
  );
}

// =============================================================
//  3. MOBILE CONTACT
// =============================================================
function MobileContactScreen() {
  return (
    <MobilePage>
      <section style={{ padding: '48px 20px 32px', textAlign: 'center' }}>
        <div style={{
          fontSize: 10.5, fontWeight: 600, letterSpacing: '0.1em',
          textTransform: 'uppercase', color: 'var(--uk-accent)', marginBottom: 10,
        }}>Contact</div>

        <h1 style={{
          fontSize: 36, lineHeight: 1.1, letterSpacing: '-0.028em',
          fontWeight: 600,
        }}>Say hello.</h1>

        <p style={{
          marginTop: 14, fontSize: 14.5, lineHeight: 1.55, color: 'var(--uk-fg-muted)',
        }}>
          Questions about pricing, self-hosting, security? Drop us a line — a human writes back.
        </p>

        <div style={{
          marginTop: 28,
          background: 'var(--uk-surface)',
          border: '1px solid var(--uk-border)',
          borderRadius: 14,
          padding: '24px 20px',
          boxShadow: 'var(--uk-shadow-md)',
          display: 'flex', flexDirection: 'column', alignItems: 'center',
        }}>
          <div style={{
            width: 40, height: 40, borderRadius: 10,
            background: 'var(--uk-accent-soft)', color: 'var(--uk-accent)',
            display: 'grid', placeItems: 'center', marginBottom: 14,
          }}>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" strokeWidth="1.7"
                 strokeLinecap="round" strokeLinejoin="round">
              <rect x="2" y="4" width="20" height="16" rx="2"/>
              <path d="m2 7 10 7 10-7"/>
            </svg>
          </div>

          <div style={{
            fontSize: 10.5, fontWeight: 600, letterSpacing: '0.1em',
            textTransform: 'uppercase', color: 'var(--uk-fg-subtle)',
            marginBottom: 6,
          }}>Email us</div>

          <a href="mailto:info@ukolio.com" style={{
            fontSize: 19, fontWeight: 600, letterSpacing: '-0.015em',
            color: 'var(--uk-fg)', textDecoration: 'none',
            fontFamily: 'var(--uk-font-mono)',
          }}>info@ukolio.com</a>

          <a href="mailto:info@ukolio.com"
             className="uk-btn uk-btn--primary"
             style={{ marginTop: 18, width: '100%' }}>
            Compose email
          </a>

          <div style={{
            marginTop: 18, paddingTop: 14, width: '100%',
            borderTop: '1px solid var(--uk-border)',
            display: 'flex', alignItems: 'center', gap: 8, justifyContent: 'center',
            fontSize: 11.5, color: 'var(--uk-fg-subtle)',
          }}>
            <span style={{ width: 5, height: 5, borderRadius: 999, background: 'var(--uk-success)' }}/>
            Reply within one business day · EN / CS
          </div>
        </div>

        <p style={{ marginTop: 24, fontSize: 12, color: 'var(--uk-fg-subtle)' }}>
          Security disclosures: <a href="mailto:security@ukolio.com" style={{ color: 'var(--uk-accent)' }}>security@ukolio.com</a>
        </p>
      </section>
    </MobilePage>
  );
}

// =============================================================
//  4. MOBILE TEXT / LEGAL
// =============================================================
function MobileTextPageScreen() {
  const [tocOpen, setTocOpen] = React.useState(false);

  const sections = [
    { id: 'intro',     label: 'Introduction' },
    { id: 'accounts',  label: 'Accounts & access' },
    { id: 'agents',    label: 'Agents & MCP clients' },
    { id: 'data',      label: 'Your data' },
    { id: 'billing',   label: 'Billing' },
    { id: 'liability', label: 'Liability' },
    { id: 'contact',   label: 'Contact' },
  ];

  const para = {
    marginBottom: 14, color: 'var(--uk-fg)',
    fontSize: 14.5, lineHeight: 1.65,
  };
  const h2 = {
    fontSize: 19, fontWeight: 600, letterSpacing: '-0.015em',
    lineHeight: 1.25, marginTop: 28, marginBottom: 10,
    color: 'var(--uk-fg)',
  };
  const h3 = {
    fontSize: 15, fontWeight: 600, letterSpacing: '-0.01em',
    lineHeight: 1.3, marginTop: 18, marginBottom: 8,
    color: 'var(--uk-fg)',
  };
  const ul = {
    margin: '0 0 14px', paddingLeft: 18,
    display: 'flex', flexDirection: 'column', gap: 6,
    fontSize: 14, lineHeight: 1.55, color: 'var(--uk-fg)',
  };
  const link = {
    color: 'var(--uk-accent)', textDecoration: 'underline',
    textUnderlineOffset: 2,
  };

  return (
    <MobilePage>
      <section style={{ padding: '32px 20px 12px' }}>
        <div style={{
          fontSize: 10.5, fontWeight: 600, letterSpacing: '0.1em',
          textTransform: 'uppercase', color: 'var(--uk-accent)', marginBottom: 8,
        }}>Legal</div>
        <h1 style={{
          fontSize: 30, lineHeight: 1.1, letterSpacing: '-0.025em',
          fontWeight: 600,
        }}>Terms of Service</h1>

        <div style={{
          display: 'flex', gap: 10, alignItems: 'center',
          fontSize: 12, color: 'var(--uk-fg-subtle)',
          paddingBottom: 16, marginTop: 12,
          borderBottom: '1px solid var(--uk-border)',
        }}>
          <span>Updated <strong style={{ color: 'var(--uk-fg)', fontWeight: 500 }}>May 14, 2026</strong></span>
          <span style={{ width: 3, height: 3, borderRadius: 999, background: 'var(--uk-fg-faint)' }}/>
          <span>v2.3</span>
        </div>

        {/* Collapsible TOC */}
        <div style={{
          marginTop: 16,
          border: '1px solid var(--uk-border)',
          borderRadius: 8,
          background: 'var(--uk-surface)',
          overflow: 'hidden',
        }}>
          <button
            onClick={() => setTocOpen(!tocOpen)}
            style={{
              width: '100%', textAlign: 'left',
              padding: '12px 14px',
              background: 'transparent', border: 'none', cursor: 'pointer',
              display: 'flex', alignItems: 'center', justifyContent: 'space-between',
            }}>
            <span style={{
              fontSize: 10.5, fontWeight: 600, letterSpacing: '0.08em',
              textTransform: 'uppercase', color: 'var(--uk-fg-subtle)',
            }}>On this page</span>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="var(--uk-fg-subtle)" strokeWidth="2"
                 strokeLinecap="round" strokeLinejoin="round"
                 style={{ transform: tocOpen ? 'rotate(180deg)' : 'rotate(0deg)', transition: 'transform 120ms' }}>
              <polyline points="6 9 12 15 18 9"/>
            </svg>
          </button>
          {tocOpen && (
            <nav style={{
              borderTop: '1px solid var(--uk-border)',
              display: 'flex', flexDirection: 'column',
            }}>
              {sections.map(s => (
                <a key={s.id} href={`#${s.id}`} style={{
                  padding: '11px 14px',
                  fontSize: 13.5, color: 'var(--uk-fg)',
                  textDecoration: 'none',
                  borderTop: '1px solid var(--uk-border)',
                }}>{s.label}</a>
              ))}
            </nav>
          )}
        </div>
      </section>

      <article style={{ padding: '8px 20px 24px' }}>
        <p style={para}>
          These Terms govern your use of Ukolio, a task-management product operated by
          Ukolio s.r.o. ("we", "us"). By creating an account or connecting an MCP
          client to your workspace, you agree to be bound by them.
        </p>

        <div style={{
          margin: '16px 0 18px',
          padding: '12px 14px',
          background: 'var(--uk-surface-2)',
          borderLeft: '3px solid var(--uk-accent)',
          borderRadius: '0 7px 7px 0',
          fontSize: 13, lineHeight: 1.55,
          color: 'var(--uk-fg-muted)',
        }}>
          This is a template for long-form prose. The same styles cover Terms, Privacy,
          DPA, Acceptable Use, and Cookie pages.
        </div>

        <h2 id="accounts" style={h2}>1 · Accounts &amp; access</h2>
        <p style={para}>
          You need an account to use Ukolio. You're responsible for keeping your
          credentials secure and for everything that happens under your account —
          including actions taken by agents you've authorised.
        </p>
        <h3 style={h3}>Workspaces and roles</h3>
        <ul style={ul}>
          <li><strong>Owner</strong> — full administrative control, including billing.</li>
          <li><strong>Admin</strong> — manage members, projects, tags, workflows.</li>
          <li><strong>Member</strong> — create and edit tasks within projects.</li>
        </ul>

        <h2 id="agents" style={h2}>2 · Agents &amp; MCP clients</h2>
        <p style={para}>
          You may connect one or more MCP-conforming clients to your account. Actions
          taken via an MCP client are attributed to you in the activity log, with the
          client name preserved.
        </p>

        <h2 id="data" style={h2}>3 · Your data</h2>
        <p style={para}>
          You retain ownership of everything you put into Ukolio. We process this data
          only to provide the service. We do <strong>not</strong> train machine-learning
          models on your content.
        </p>

        <h2 id="billing" style={h2}>4 · Billing</h2>
        <p style={para}>
          The Free plan is free. Paid plans are billed in advance — monthly or annually,
          per active member.
        </p>

        <h2 id="liability" style={h2}>5 · Liability</h2>
        <p style={para}>
          Ukolio is provided "as is". To the maximum extent permitted by law, our total
          liability is limited to the fees you paid us in the 12 months preceding the
          event giving rise to the claim.
        </p>

        <h2 id="contact" style={h2}>6 · Contact</h2>
        <p style={para}>
          Questions? Email <a href="mailto:info@ukolio.com" style={link}>info@ukolio.com</a>.
          For data-protection, use <a href="mailto:dpo@ukolio.com" style={link}>dpo@ukolio.com</a>.
        </p>

        <div style={{
          marginTop: 28, paddingTop: 18,
          borderTop: '1px solid var(--uk-border)',
          fontSize: 12, color: 'var(--uk-fg-subtle)',
          lineHeight: 1.6,
        }}>
          Ukolio s.r.o.<br/>
          Karlovo náměstí 10, 120 00 Praha 2, Czech Republic<br/>
          IČO 12345678
        </div>
      </article>
    </MobilePage>
  );
}

// =============================================================
//  Bare mobile wrapper — content only, no device chrome
// =============================================================
function MobileFrame({ children }) {
  return (
    <div style={{
      width: 402, margin: '0 auto',
      background: 'var(--uk-bg)',
      borderLeft: '1px solid var(--uk-border)',
      borderRight: '1px solid var(--uk-border)',
      minHeight: '100%',
      overflow: 'hidden',
    }}>
      {children}
    </div>
  );
}

function MobileLandingArtboard() { return <MobileFrame><MobileLandingScreen/></MobileFrame>; }
function MobilePricingArtboard() { return <MobileFrame><MobilePricingScreen/></MobileFrame>; }
function MobileContactArtboard() { return <MobileFrame><MobileContactScreen/></MobileFrame>; }
function MobileTextArtboard()    { return <MobileFrame><MobileTextPageScreen/></MobileFrame>; }

window.MobileLandingArtboard = MobileLandingArtboard;
window.MobilePricingArtboard = MobilePricingArtboard;
window.MobileContactArtboard = MobileContactArtboard;
window.MobileTextArtboard    = MobileTextArtboard;
