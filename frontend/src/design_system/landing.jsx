// ============================================================
// Ukolio Design System — Landing page (marketing)
// Uses the same tokens & components as the app screens so it
// reads as a coherent surface in the canvas.
// ============================================================

// --- Local marketing-only icons (kept tiny, currentColor stroke) -------
const LIcon = {
  Layers:    (p) => <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M12 2 2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>,
  Lock:      (p) => <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" {...p}><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>,
  Clock:     (p) => <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" {...p}><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>,
  Kanban:    (p) => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...p}><rect x="3" y="3" width="7" height="18"/><rect x="14" y="3" width="7" height="11"/></svg>,
  Grid:      (p) => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" {...p}><path d="M3 12h18M3 6h18M3 18h18"/></svg>,
  Chat:      (p) => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z"/></svg>,
  Check2:    (p) => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" {...p}><polyline points="20 6 9 17 4 12"/></svg>,
  Tag:       (p) => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M20.59 13.41 13.42 20.58a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>,
  Upload:    (p) => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>,
  Users:     (p) => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>,
  Globe:     (p) => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...p}><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>,
  Doc:       (p) => <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>,
};

// =============================================================
//  Marketing TopBar — matches app shell, but with marketing nav
// =============================================================
function LandingTopBar() {
  const link = {
    fontSize: 13, color: 'var(--uk-fg-muted)', padding: '6px 4px',
    textDecoration: 'none', fontWeight: 500, letterSpacing: '-0.005em',
  };
  return (
    <header style={{
      height: 56,
      borderBottom: '1px solid var(--uk-border)',
      background: 'rgba(250,250,250,0.85)',
      backdropFilter: 'saturate(140%) blur(8px)',
      WebkitBackdropFilter: 'saturate(140%) blur(8px)',
      display: 'flex', alignItems: 'center', padding: '0 28px', gap: 24,
      position: 'sticky', top: 0, zIndex: 5,
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
        <Mark size={24} />
        <span style={{ fontWeight: 600, letterSpacing: '-0.02em', fontSize: 15 }}>ukolio</span>
      </div>

      <nav style={{ display: 'flex', alignItems: 'center', gap: 22, marginLeft: 12 }}>
        <a href="#" style={link}>Features</a>
        <a href="#" style={link}>For agents</a>
        <a href="#" style={link}>Built right</a>
        <a href="#" style={link}>Docs</a>
        <a href="#" style={link}>Pricing</a>
      </nav>

      <div style={{ flex: 1 }} />

      <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
        <button className="uk-btn uk-btn--ghost uk-btn--sm">Sign in</button>
        <button className="uk-btn uk-btn--primary uk-btn--sm" style={{ paddingRight: 12 }}>
          Get started <Icon.Arrow />
        </button>
      </div>
    </header>
  );
}

// =============================================================
//  Section head — reused
// =============================================================
function SectionHead({ eyebrow, title, lead, align = 'left', maxWidth = 720 }) {
  return (
    <div style={{
      maxWidth, marginBottom: 40,
      textAlign: align,
      marginLeft: align === 'center' ? 'auto' : 0,
      marginRight: align === 'center' ? 'auto' : 0,
    }}>
      <div style={{
        fontSize: 11, fontWeight: 600, letterSpacing: '0.1em',
        textTransform: 'uppercase', color: 'var(--uk-accent)',
        marginBottom: 12,
      }}>{eyebrow}</div>
      <h2 style={{
        fontSize: 34, lineHeight: 1.15, letterSpacing: '-0.025em',
        fontWeight: 600, color: 'var(--uk-fg)',
      }}>{title}</h2>
      {lead && (
        <p style={{
          marginTop: 14, fontSize: 16, lineHeight: 1.55,
          color: 'var(--uk-fg-muted)', maxWidth: 620,
          marginLeft: align === 'center' ? 'auto' : 0,
          marginRight: align === 'center' ? 'auto' : 0,
        }}>{lead}</p>
      )}
    </div>
  );
}

// =============================================================
//  Hero (with embedded Kanban preview)
// =============================================================
function Hero() {
  return (
    <section style={{ padding: '88px 0 56px' }}>
      <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 28px' }}>
        {/* eyebrow pill */}
        <span style={{
          display: 'inline-flex', alignItems: 'center', gap: 8,
          padding: '4px 12px 4px 6px',
          background: 'var(--uk-ai-soft)', color: 'var(--uk-ai)',
          border: '1px solid var(--uk-ai-border)',
          borderRadius: 999, fontSize: 12, fontWeight: 500,
        }}>
          <span style={{
            width: 16, height: 16, borderRadius: 999,
            background: 'var(--uk-ai)', color: '#fff',
            display: 'grid', placeItems: 'center', fontSize: 9,
          }}>★</span>
          MCP-native · OAuth 2.1 + PKCE · Open source
        </span>

        <h1 style={{
          marginTop: 20,
          fontSize: 56, lineHeight: 1.05, letterSpacing: '-0.032em',
          fontWeight: 600, maxWidth: 800, color: 'var(--uk-fg)',
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
          marginTop: 22, maxWidth: 620,
          fontSize: 17, lineHeight: 1.55, color: 'var(--uk-fg-muted)',
        }}>
          Ukolio is a multi-tenant task manager built around the Model Context
          Protocol. Claude, Cursor, ChatGPT — any MCP client — plans, creates,
          moves, and closes tasks alongside your team. You get the human
          overview. Agents get a proper API, not a scraped UI.
        </p>

        <div style={{ marginTop: 28, display: 'flex', gap: 10, flexWrap: 'wrap' }}>
          <button className="uk-btn uk-btn--primary uk-btn--lg" style={{ paddingRight: 14 }}>
            Start a free workspace <Icon.Arrow />
          </button>
          <button className="uk-btn uk-btn--secondary uk-btn--lg">See how agents use it</button>
        </div>

        <div style={{
          marginTop: 24, display: 'flex', gap: 18, flexWrap: 'wrap',
          fontSize: 12.5, color: 'var(--uk-fg-subtle)',
        }}>
          {['Self-hostable', 'MIT licensed', 'Multi-tenant from day one'].map(t => (
            <span key={t} style={{ display: 'inline-flex', alignItems: 'center', gap: 6 }}>
              <span style={{ width: 6, height: 6, borderRadius: 999, background: 'var(--uk-success)' }} />
              {t}
            </span>
          ))}
        </div>

        <HeroBoard />
      </div>
    </section>
  );
}

function HeroBoard() {
  const Card = ({ id, title, prio, agent }) => (
    <div className="uk-task" style={{ marginBottom: 8 }}>
      <div className="uk-task-id">{id}</div>
      <div className="uk-task-title">{title}</div>
      <div className="uk-task-meta" style={{ marginTop: 2 }}>
        {prio && <span className={`uk-badge ${prio.cls}`}>{prio.label}</span>}
        {agent && <span className="uk-badge uk-badge--ai">{agent}</span>}
      </div>
    </div>
  );

  const Col = ({ dot, label, count, children }) => (
    <div>
      <div style={{
        display: 'flex', alignItems: 'center', gap: 8,
        fontSize: 11, fontWeight: 600, textTransform: 'uppercase',
        letterSpacing: '0.06em', color: 'var(--uk-fg-subtle)',
        marginBottom: 10,
      }}>
        <span style={{ width: 8, height: 8, borderRadius: 999, background: dot }} />
        {label} · {count}
      </div>
      {children}
    </div>
  );

  return (
    <div style={{
      marginTop: 48,
      background: 'var(--uk-surface)',
      border: '1px solid var(--uk-border)',
      borderRadius: 14, padding: 18,
      boxShadow: 'var(--uk-shadow-lg)',
    }}>
      <div style={{
        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        padding: '4px 4px 14px', marginBottom: 14,
        borderBottom: '1px solid var(--uk-border)',
        fontSize: 12.5, color: 'var(--uk-fg-muted)',
      }}>
        <div><strong style={{ color: 'var(--uk-fg)' }}>Acme · Backend</strong>&nbsp;·&nbsp;Sprint 14</div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
          <span className="uk-badge uk-badge--ai">3 of 12 by agents this week</span>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16 }}>
        <Col dot="var(--uk-status-todo)" label="To do" count={3}>
          <Card id="BE-217" title="Rate-limit refresh-token endpoint"
            prio={{ cls: 'uk-badge--danger', label: 'High' }}
            agent="via Claude" />
          <Card id="BE-219" title="Backfill workspace.owner_id for legacy rows"
            prio={{ cls: 'uk-badge', label: 'Medium' }} />
          <Card id="BE-220" title="Add OpenTelemetry tracing to MCP transport"
            agent="via Cursor" />
        </Col>
        <Col dot="var(--uk-status-doing)" label="In progress" count={2}>
          <Card id="BE-214" title="Migrate file attachments to S3-compatible storage"
            prio={{ cls: 'uk-badge--solid', label: 'Urgent' }} />
          <Card id="BE-216" title="Custom-field validation for Version type"
            prio={{ cls: 'uk-badge', label: 'Medium' }} />
        </Col>
        <Col dot="var(--uk-status-done)" label="Done" count={4}>
          <Card id="BE-210" title="OAuth 2.1 dynamic client registration"
            agent="closed by ChatGPT" />
          <Card id="BE-211" title="Workspace-scoped tag catalog"
            prio={{ cls: 'uk-badge', label: 'Medium' }} />
        </Col>
      </div>
    </div>
  );
}

// =============================================================
//  Pillars — three columns
// =============================================================
function Pillars() {
  const items = [
    {
      icon: <LIcon.Layers />,
      title: 'MCP-native, not bolted on',
      body: 'Streamable HTTP transport, Redis-backed session persistence, tools auto-discovered from the backend. Every domain operation an agent might need — projects, tasks, workflows, custom fields, tags, attachments, comments — is exposed as a typed MCP tool.',
    },
    {
      icon: <LIcon.Lock />,
      title: 'OAuth 2.1 + PKCE for agents',
      body: 'No shared API keys, no copy-paste tokens. Agents register dynamically, request user consent, and operate with hashed, revocable tokens. RFC 9728 discovery makes setup one click in Claude Desktop, Cursor, or any conforming MCP client.',
    },
    {
      icon: <LIcon.Clock />,
      title: 'Always know who did what',
      body: 'Every event, comment, and task is tagged Human or Agent — and named with the MCP client. When an agent moves your sprint, the audit log shows which agent, on whose behalf, and exactly when.',
    },
  ];
  return (
    <section style={{ padding: '72px 0', borderTop: '1px solid var(--uk-border)' }}>
      <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 28px' }}>
        <SectionHead
          eyebrow="Why Ukolio"
          title="A task manager designed for humans and agents to share."
          lead='Most tools bolt on an "AI assistant." Ukolio was designed the other way around — agents are first-class actors, with their own auth, their own audit trail, and tools shaped for them.'
        />
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 16 }}>
          {items.map(it => (
            <div key={it.title} className="uk-card" style={{ padding: 22 }}>
              <div style={{
                width: 32, height: 32, borderRadius: 7,
                background: 'var(--uk-accent-soft)', color: 'var(--uk-accent)',
                display: 'grid', placeItems: 'center', marginBottom: 14,
              }}>{it.icon}</div>
              <h3 style={{ fontSize: 16, fontWeight: 600, marginBottom: 6, letterSpacing: '-0.012em' }}>{it.title}</h3>
              <p style={{ fontSize: 13.5, lineHeight: 1.55, color: 'var(--uk-fg-muted)' }}>{it.body}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// =============================================================
//  How it works — steps + code
// =============================================================
function HowItWorks() {
  const steps = [
    {
      title: 'Sign up & get a workspace',
      body: 'Every account gets a personal workspace, pre-seeded with a To do → In progress → Done workflow. Invite teammates by email or stay solo.',
    },
    {
      title: 'Add the MCP server to your client',
      body: 'Drop in the Ukolio MCP URL. Your client discovers the OAuth endpoints, opens a browser tab for consent, and exchanges a PKCE-pinned code for a token — no shared secrets.',
    },
    {
      title: 'Let agents plan, create, and close',
      body: 'Tell Claude to triage your inbox into tasks. Ask Cursor to break a feature into subtasks with dependencies. Have ChatGPT close out everything that shipped in a release.',
    },
    {
      title: 'Review on the board, not in chat',
      body: 'The web UI is for humans: a fast Kanban, a workspace-wide task grid with search and filters, drawers for editing, and an event log that shows exactly what each agent did.',
    },
  ];

  return (
    <section style={{ padding: '72px 0', borderTop: '1px solid var(--uk-border)' }}>
      <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 28px' }}>
        <SectionHead
          eyebrow="For agents"
          title="Connect your MCP client in under a minute."
          lead="Point your client at the Ukolio MCP URL. Discovery, registration, and consent are handled automatically."
        />

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 48, alignItems: 'start' }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 22 }}>
            {steps.map((s, i) => (
              <div key={s.title} style={{ display: 'flex', gap: 14 }}>
                <div style={{
                  flexShrink: 0, width: 28, height: 28, borderRadius: 7,
                  background: 'var(--uk-fg)', color: '#fff',
                  display: 'grid', placeItems: 'center',
                  fontSize: 12, fontWeight: 600,
                  fontFamily: 'var(--uk-font-mono)',
                }}>{i + 1}</div>
                <div>
                  <h3 style={{ fontSize: 15, fontWeight: 600, marginBottom: 4 }}>{s.title}</h3>
                  <p style={{ fontSize: 13.5, lineHeight: 1.55, color: 'var(--uk-fg-muted)' }}>{s.body}</p>
                </div>
              </div>
            ))}
          </div>

          <CodeBlock />
        </div>
      </div>
    </section>
  );
}

function CodeBlock() {
  const C = ({ k, children }) => <span style={{ color: ({
    comment: '#71717a', key: '#a1a1aa', str: '#a5d6a7', fn: '#c5cae9',
  })[k] }}>{children}</span>;

  return (
    <div style={{
      background: '#18181b', color: '#e4e4e7',
      borderRadius: 10, padding: 20,
      fontFamily: 'var(--uk-font-mono)',
      fontSize: 12.5, lineHeight: 1.7,
      border: '1px solid #27272a',
    }}>
      <C k="comment"># claude_desktop_config.json</C><br />
      {'{'}<br />
      {'  '}<C k="key">"mcpServers"</C>: {'{'}<br />
      {'    '}<C k="key">"ukolio"</C>: {'{'}<br />
      {'      '}<C k="key">"url"</C>: <C k="str">"https://app.ukolio.com/api/mcp"</C><br />
      {'    }'}<br />
      {'  }'}<br />
      {'}'}<br />
      <br />
      <C k="comment"># In conversation:</C><br />
      &gt; Triage these 12 customer reports into the<br />
      &nbsp;&nbsp;Backend project. Mark anything mentioning<br />
      &nbsp;&nbsp;the migration as Urgent.<br />
      <br />
      <C k="fn">→ ukolio.find_project_by_name("Backend")</C><br />
      <C k="fn">→ ukolio.list_statuses(projectId)</C><br />
      <C k="fn">→ ukolio.create_task × 12</C><br />
      <C k="fn">→ ukolio.set_task_tags(["urgent"])</C>
    </div>
  );
}

// =============================================================
//  Feature grid — 3 × 3
// =============================================================
function FeatureGrid() {
  const items = [
    { icon: <LIcon.Kanban />, title: 'Kanban with custom workflows',
      body: <>Per-project workflows, drag-and-drop columns, Start / Normal / Finish status types. Tasks display as <code className="uk-mono" style={{ background: 'var(--uk-surface-2)', padding: '1px 6px', borderRadius: 4 }}>PROJECT-N</code> for stable references.</> },
    { icon: <LIcon.Grid />, title: 'Workspace-wide task grid',
      body: 'Search, multi-status filter, sortable columns, and pagination across every project. Open the existing detail drawer inline — no context switch.' },
    { icon: <LIcon.Chat />, title: 'Comments with attribution',
      body: 'Every comment carries an actor type. Threads stay readable when humans and three different agents are all chiming in on the same task.' },
    { icon: <LIcon.Check2 />, title: 'Custom fields',
      body: 'Workspace-level catalog: Text, Textarea, Select, semver Version. Attach to any project, set required, set defaults — all reachable from MCP.' },
    { icon: <LIcon.Tag />, title: 'Tags & typed relations',
      body: 'Workspace-scoped tags. Parent / DependsOn / Related / Duplicates between tasks — perfect for letting agents reason about blockers.' },
    { icon: <LIcon.Upload />, title: 'File attachments',
      body: 'S3-compatible object storage backs every task. Attach designs, logs, or screenshots; agents can fetch them by ID without scraping a UI.' },
    { icon: <LIcon.Users />, title: 'Multi-tenant workspaces',
      body: 'One account, many workspaces, three roles (Owner / Admin / Member) plus a SystemAdmin tier. Email invitations, atomic ownership transfer.' },
    { icon: <LIcon.Globe />, title: 'English & Czech',
      body: "UI and transactional email both honour the user's locale. Picked once in the topbar — synced to the server so invites land in the right language." },
    { icon: <LIcon.Doc />, title: 'Append-only event log',
      body: 'Every mutating action lands in a typed event stream — per project, per workspace, per task. Filter by actor type, by MCP client, or by user.' },
  ];

  return (
    <section style={{ padding: '72px 0', borderTop: '1px solid var(--uk-border)' }}>
      <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 28px' }}>
        <SectionHead
          eyebrow="Everything you'd expect"
          title="A complete task manager. Just one your tools can actually use."
          lead="Ukolio isn't a minimal toy wrapped around an MCP server. It's a full Kanban with the structure real teams need — and every part of it is reachable from agents and humans alike."
        />

        <div style={{
          display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)',
          gap: 1, background: 'var(--uk-border)',
          border: '1px solid var(--uk-border)', borderRadius: 10, overflow: 'hidden',
        }}>
          {items.map(it => (
            <div key={it.title} style={{ background: 'var(--uk-surface)', padding: 22 }}>
              <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 6, display: 'flex', alignItems: 'center', gap: 8, letterSpacing: '-0.008em' }}>
                <span style={{ color: 'var(--uk-fg-subtle)', display: 'inline-flex' }}>{it.icon}</span>
                {it.title}
              </h3>
              <p style={{ fontSize: 12.5, lineHeight: 1.55, color: 'var(--uk-fg-muted)' }}>{it.body}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// =============================================================
//  Trust block — 2-col checklist
// =============================================================
function Trust() {
  const items = [
    { title: 'OAuth 2.1 + PKCE, S256 only',
      body: 'Dynamic client registration, PKCE-pinned authorization codes, SHA-256-hashed tokens at rest. No shared secrets. 1 h access tokens, 30 d refresh.' },
    { title: 'RFC 9728 discovery',
      body: <>401 responses carry <code className="uk-mono" style={{ background: 'var(--uk-surface-2)', padding: '1px 5px', borderRadius: 4 }}>WWW-Authenticate: Bearer resource_metadata=…</code> so conforming MCP clients can auto-discover and reconnect.</> },
    { title: 'Strict CSP, per-request nonces',
      body: 'The Angular app ships with a tight Content-Security-Policy and rotates nonces per request. nginx adds the rest of the security header set.' },
    { title: 'Your data is yours',
      body: <>One-click data export. One-click account deletion. Self-host the whole stack — it's a single <code className="uk-mono" style={{ background: 'var(--uk-surface-2)', padding: '1px 5px', borderRadius: 4 }}>docker compose up</code>.</> },
    { title: 'PHPStan max, zero-warning lint',
      body: 'Backend at PHPStan max with bleedingEdge + strict + cognitive-complexity rules. Frontend lint runs --max-warnings=0. Tested via a real database, not mocks.' },
    { title: 'Open source, MIT',
      body: 'Read the code. Audit the OAuth flow. Run a fork. The hosted product and the source are the same codebase.' },
  ];

  return (
    <section style={{ padding: '72px 0', borderTop: '1px solid var(--uk-border)' }}>
      <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 28px' }}>
        <SectionHead
          eyebrow="Built right"
          title="The boring details, done seriously."
          lead="You're going to give agents write access to your team's work. Ukolio takes that seriously."
        />

        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '18px 32px' }}>
          {items.map(it => (
            <div key={it.title} style={{ display: 'flex', gap: 12, alignItems: 'flex-start' }}>
              <span style={{
                flexShrink: 0,
                width: 22, height: 22, borderRadius: 999,
                background: 'var(--uk-success-soft)', color: 'var(--uk-success)',
                display: 'grid', placeItems: 'center', marginTop: 1,
              }}>
                <LIcon.Check2 />
              </span>
              <div>
                <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 3 }}>{it.title}</h3>
                <p style={{ fontSize: 13, lineHeight: 1.55, color: 'var(--uk-fg-muted)' }}>{it.body}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

// =============================================================
//  CTA + Footer
// =============================================================
function CTA() {
  return (
    <section style={{ padding: '40px 0 64px' }}>
      <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 28px' }}>
        <div style={{
          background: 'var(--uk-fg)', color: '#fff',
          borderRadius: 14, padding: '52px 56px',
          textAlign: 'center',
          backgroundImage: 'radial-gradient(circle at 20% 0%, rgba(94,106,210,0.20), transparent 60%), radial-gradient(circle at 80% 100%, rgba(111,78,211,0.18), transparent 60%)',
        }}>
          <h2 style={{ fontSize: 34, lineHeight: 1.15, letterSpacing: '-0.025em', fontWeight: 600, color: '#fff' }}>
            Stop describing tasks to your agent. Let it manage them.
          </h2>
          <p style={{ margin: '14px auto 0', maxWidth: 560, color: '#a1a1aa', fontSize: 16, lineHeight: 1.55 }}>
            Free to try. Free to self-host. Connect your first MCP client in under a minute.
          </p>
          <div style={{ marginTop: 26, display: 'inline-flex', gap: 10 }}>
            <button className="uk-btn uk-btn--lg" style={{
              background: '#fff', color: 'var(--uk-fg)', borderColor: '#fff',
              paddingRight: 16,
            }}>Create a workspace <Icon.Arrow /></button>
            <button className="uk-btn uk-btn--lg" style={{
              background: 'transparent', color: '#fff',
              borderColor: '#3f3f46',
            }}>Sign in</button>
          </div>
        </div>
      </div>
    </section>
  );
}

function LandingFooter() {
  return (
    <footer style={{ borderTop: '1px solid var(--uk-border)', padding: '24px 0', background: 'var(--uk-surface)' }}>
      <div style={{
        maxWidth: 1120, margin: '0 auto', padding: '0 28px',
        display: 'flex', alignItems: 'center', justifyContent: 'space-between',
        fontSize: 12.5, color: 'var(--uk-fg-subtle)', flexWrap: 'wrap', gap: 12,
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
          <Mark size={20} />
          <span style={{ fontWeight: 600, color: 'var(--uk-fg)', letterSpacing: '-0.015em' }}>ukolio</span>
          <span style={{ marginLeft: 6 }}>© 2026</span>
        </div>
        <div style={{ display: 'flex', gap: 18 }}>
          <a href="#" style={{ color: 'inherit', textDecoration: 'none' }}>Open app</a>
          <a href="#" style={{ color: 'inherit', textDecoration: 'none' }}>Features</a>
          <a href="#" style={{ color: 'inherit', textDecoration: 'none' }}>Security</a>
          <a href="#" style={{ color: 'inherit', textDecoration: 'none' }}>Docs</a>
          <a href="#" style={{ color: 'inherit', textDecoration: 'none' }}>Contact</a>
        </div>
      </div>
    </footer>
  );
}

// =============================================================
//  Top-level LandingScreen
// =============================================================
function LandingScreen() {
  return (
    <div className="uk" style={{
      minHeight: '100%', width: '100%',
      background: 'var(--uk-bg)',
    }}>
      <LandingTopBar />
      <Hero />
      <Pillars />
      <HowItWorks />
      <FeatureGrid />
      <Trust />
      <CTA />
      <LandingFooter />
    </div>
  );
}

window.LandingScreen = LandingScreen;
window.LandingTopBar = LandingTopBar;
window.LandingFooter = LandingFooter;
window.SectionHead    = SectionHead;
window.LIcon          = LIcon;
