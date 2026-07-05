// ============================================================
// Ukolio Design System — Foundations
// Brand mark, color palette, type scale, spacing, radii, shadows
// ============================================================

const Mark = ({ size = 32, invert = false }) => (
  <span style={{
    display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
    width: size, height: size, borderRadius: size * 0.28,
    background: invert ? '#fff' : '#18181b',
    color: invert ? '#18181b' : '#fff',
    fontWeight: 700,
    fontSize: size * 0.5, letterSpacing: '-0.04em',
    fontFamily: "'Inter', sans-serif"
  }}>ú</span>
);

window.Mark = Mark;

// ---------- Brand ----------
function BrandBoard() {
  return (
    <div className="uk" style={{ padding: 32, background: '#fff', height: '100%' }}>
      <div className="uk-section-title">Brand</div>

      <div style={{ display: 'flex', alignItems: 'center', gap: 16, marginBottom: 28 }}>
        <Mark size={56} />
        <div>
          <div style={{ fontSize: 30, fontWeight: 600, letterSpacing: '-0.024em' }}>ukolio</div>
          <div className="uk-caption">Multi-tenant kanban · MCP-native</div>
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12, marginBottom: 24 }}>
        {[
          { bg: '#18181b', fg: '#fff', invert: true },
          { bg: '#5e6ad2', fg: '#fff' },
          { bg: '#fafafa', fg: '#18181b', border: true }
        ].map((t, i) => (
          <div key={i} style={{
            height: 80, borderRadius: 8, background: t.bg, color: t.fg,
            border: t.border ? '1px solid #e7e7ea' : 'none',
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8
          }}>
            <Mark size={28} invert={t.invert} />
            <span style={{ fontSize: 18, fontWeight: 600, letterSpacing: '-0.02em' }}>ukolio</span>
          </div>
        ))}
      </div>

      <div style={{ borderTop: '1px solid #e7e7ea', paddingTop: 20 }}>
        <div className="uk-overline" style={{ marginBottom: 10 }}>Voice</div>
        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 16 }}>
          <div>
            <div style={{ fontSize: 12, fontWeight: 600, color: '#16794a', marginBottom: 4 }}>YES</div>
            <ul style={{ margin: 0, paddingLeft: 16, fontSize: 12, color: '#52525b', lineHeight: 1.7 }}>
              <li>Direct, terse, lowercase chrome</li>
              <li>Surface agent activity plainly</li>
              <li>Numbers, IDs, timestamps</li>
            </ul>
          </div>
          <div>
            <div style={{ fontSize: 12, fontWeight: 600, color: '#b42318', marginBottom: 4 }}>NO</div>
            <ul style={{ margin: 0, paddingLeft: 16, fontSize: 12, color: '#52525b', lineHeight: 1.7 }}>
              <li>Marketing tone, exclamations</li>
              <li>Emoji or playful illustrations</li>
              <li>Gradients, drop shadows as decoration</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}

// ---------- Color palette ----------
const PALETTE = {
  surfaces: [
    ['Background',    '#fafafa', '--uk-bg'],
    ['Surface',       '#ffffff', '--uk-surface'],
    ['Surface · muted','#f4f4f5', '--uk-surface-2'],
    ['Surface · deep','#ebebed', '--uk-surface-3']
  ],
  text: [
    ['Foreground',    '#18181b', '--uk-fg'],
    ['Foreground · muted',  '#52525b', '--uk-fg-muted'],
    ['Foreground · subtle', '#8a8a92', '--uk-fg-subtle'],
    ['Foreground · faint',  '#b4b4ba', '--uk-fg-faint']
  ],
  borders: [
    ['Border',        '#e7e7ea', '--uk-border'],
    ['Border · strong','#d4d4d8','--uk-border-strong'],
    ['Border · focus','#5e6ad2', '--uk-border-focus']
  ],
  accent: [
    ['Accent',        '#5e6ad2', '--uk-accent'],
    ['Accent · hover','#4f5bbf', '--uk-accent-hover'],
    ['Accent · active','#424ea8','--uk-accent-active'],
    ['Accent · soft', '#eef0fb', '--uk-accent-soft']
  ],
  semantic: [
    ['Success', '#16794a', '--uk-success'],
    ['Warning', '#a35c00', '--uk-warn'],
    ['Danger',  '#b42318', '--uk-danger'],
    ['Info',    '#1e58b6', '--uk-info'],
    ['AI / agent', '#6f4ed3', '--uk-ai']
  ],
  status: [
    ['Todo',     '#94a3a8', '--uk-status-todo'],
    ['Doing',    '#c98a14', '--uk-status-doing'],
    ['Review',   '#4a8fd6', '--uk-status-review'],
    ['Done',     '#16794a', '--uk-status-done'],
    ['Blocked',  '#b42318', '--uk-status-blocked']
  ]
};

function SwatchGrid({ items, cols = 4 }) {
  return (
    <div style={{ display: 'grid', gridTemplateColumns: `repeat(${cols}, 1fr)`, gap: 12 }}>
      {items.map(([name, hex, varName]) => (
        <div className="uk-swatch" key={hex + name}>
          <div className="uk-swatch-color" style={{ background: hex }} />
          <div className="uk-swatch-meta">
            <span className="uk-swatch-name">{name}</span>
            <span className="uk-swatch-val">{hex}</span>
            <span className="uk-swatch-val" style={{ opacity: 0.7 }}>{varName}</span>
          </div>
        </div>
      ))}
    </div>
  );
}

function ColorBoard() {
  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%', overflow: 'hidden' }}>
      <div className="uk-section-title">Color · Light</div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 28, marginBottom: 24 }}>
        <div>
          <div className="uk-overline" style={{ marginBottom: 10 }}>Surfaces</div>
          <SwatchGrid items={PALETTE.surfaces} cols={2} />
        </div>
        <div>
          <div className="uk-overline" style={{ marginBottom: 10 }}>Text</div>
          <SwatchGrid items={PALETTE.text} cols={2} />
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 28, marginBottom: 24 }}>
        <div>
          <div className="uk-overline" style={{ marginBottom: 10 }}>Borders</div>
          <SwatchGrid items={PALETTE.borders} cols={3} />
        </div>
        <div>
          <div className="uk-overline" style={{ marginBottom: 10 }}>Accent · Indigo</div>
          <SwatchGrid items={PALETTE.accent} cols={4} />
        </div>
      </div>

      <div style={{ marginBottom: 24 }}>
        <div className="uk-overline" style={{ marginBottom: 10 }}>Semantic</div>
        <SwatchGrid items={PALETTE.semantic} cols={5} />
      </div>

      <div>
        <div className="uk-overline" style={{ marginBottom: 10 }}>Workflow status</div>
        <SwatchGrid items={PALETTE.status} cols={5} />
      </div>
    </div>
  );
}

// ---------- Typography ----------
function TypeBoard() {
  const rows = [
    ['Display',  '40 / 600 / -0.022em', 'uk-display',  'Ship faster with agents.'],
    ['H1',       '30 / 600 / -0.022em', 'uk-h1',       'Workspace overview'],
    ['H2',       '24 / 600 / -0.012em', 'uk-h2',       'Active projects'],
    ['H3',       '20 / 600 / -0.012em', 'uk-h3',       'Backend rewrite'],
    ['H4',       '16 / 600',            'uk-h4',       'In Progress · 12'],
    ['Body',     '15 / 400 / 1.55',     'uk-body',     'Description supports markdown. Use headings, lists, code blocks, and links to keep tasks rich.'],
    ['Body sm',  '13 / 400 / 1.5',      'uk-body-sm',  'Filter by status, priority, assignee, or workflow.'],
    ['Caption',  '12 / 400 / 1.4',      'uk-caption',  'Updated 4 minutes ago · by Skopal'],
    ['Overline', '11 / 600 / 0.08em',   'uk-overline', 'Workspace'],
    ['Mono',     '12 / 400 mono',       'uk-mono',     'UKO-318 · workspace_id=mskopal'],
  ];
  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%' }}>
      <div className="uk-section-title">Type · Inter</div>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        {rows.map(([name, spec, cls, sample]) => (
          <div key={name} style={{ display: 'grid', gridTemplateColumns: '90px 1fr', gap: 24, alignItems: 'baseline', paddingBottom: 12, borderBottom: '1px solid #f4f4f5' }}>
            <div>
              <div style={{ fontSize: 12, fontWeight: 600, color: '#18181b' }}>{name}</div>
              <div style={{ fontFamily: 'JetBrains Mono, monospace', fontSize: 10, color: '#8a8a92', marginTop: 2 }}>{spec}</div>
            </div>
            <div className={cls}>{sample}</div>
          </div>
        ))}
      </div>

      <div style={{ display: 'flex', gap: 8, marginTop: 16, alignItems: 'center' }}>
        <span className="uk-overline">Keyboard</span>
        <span className="uk-kbd">⌘</span>
        <span className="uk-kbd">K</span>
        <span style={{ fontSize: 12, color: '#8a8a92' }}>opens command palette</span>
      </div>
    </div>
  );
}

// ---------- Spacing / radii / shadows ----------
function MetricsBoard() {
  const spaces = [4, 6, 8, 10, 12, 16, 20, 24, 32, 40];
  const radii = [
    ['xs', 3],
    ['sm', 5],
    ['md', 7],
    ['lg', 10],
    ['xl', 14]
  ];
  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%' }}>
      <div className="uk-section-title">Spacing · Radius · Elevation</div>

      <div className="uk-overline" style={{ marginBottom: 10 }}>Spacing scale (4px)</div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginBottom: 24 }}>
        {spaces.map(s => (
          <div key={s} style={{ display: 'flex', alignItems: 'center', gap: 12, fontSize: 12 }}>
            <span style={{ width: 28, fontFamily: 'JetBrains Mono', fontSize: 11, color: '#8a8a92' }}>{s}</span>
            <span style={{ height: 10, width: s, background: '#5e6ad2', borderRadius: 2 }} />
            <span style={{ color: '#52525b', fontSize: 11 }}>--uk-s-{spaces.indexOf(s) + 1}</span>
          </div>
        ))}
      </div>

      <div className="uk-overline" style={{ marginBottom: 10 }}>Radius</div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 10, marginBottom: 24 }}>
        {radii.map(([name, r]) => (
          <div key={name} style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-start', gap: 4 }}>
            <div style={{ width: 56, height: 56, background: '#f4f4f5', border: '1px solid #e7e7ea', borderRadius: r }} />
            <div style={{ fontSize: 11, fontWeight: 600 }}>{name}</div>
            <div style={{ fontFamily: 'JetBrains Mono', fontSize: 10, color: '#8a8a92' }}>{r}px</div>
          </div>
        ))}
      </div>

      <div className="uk-overline" style={{ marginBottom: 10 }}>Elevation</div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 12 }}>
        {[
          ['xs · border', 'var(--uk-shadow-xs)'],
          ['sm · cards',  'var(--uk-shadow-sm)'],
          ['md · menus',  'var(--uk-shadow-md)'],
        ].map(([name, sh]) => (
          <div key={name} style={{ padding: 14 }}>
            <div style={{
              height: 56, background: '#fff',
              border: '1px solid #e7e7ea', borderRadius: 7,
              boxShadow: sh
            }} />
            <div style={{ fontSize: 11, fontWeight: 600, marginTop: 8 }}>{name}</div>
          </div>
        ))}
      </div>

      <div style={{ marginTop: 16, padding: 12, background: '#f4f4f5', border: '1px solid #e7e7ea', borderRadius: 7, fontSize: 11, color: '#52525b' }}>
        <strong style={{ color: '#18181b', fontWeight: 600 }}>Rule.</strong> Use borders, not shadows, to separate surfaces. Reserve shadows for floating UI: menus, drawers, toasts.
      </div>
    </div>
  );
}

Object.assign(window, { BrandBoard, ColorBoard, TypeBoard, MetricsBoard });
