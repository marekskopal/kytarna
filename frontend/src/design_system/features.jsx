// ============================================================
// Kytario Design System — New features
// Covers backend migrations:
//   20260518_TaskFiles      — per-task uploads
//   20260519_TaskRelations  — Related / Duplicates / Parent / DependsOn
//   20260520_Tags           — workspace tags + task_tags pivot
// ============================================================

// ------------------------------------------------------------
// Shared helpers
// ------------------------------------------------------------

// Curated tag palette — matches the rest of the system.
// 16 colours, vibrant enough for a solid chip, dark enough that
// white text passes contrast at 11px / weight 500.
const TAG_PALETTE = [
  { name: 'Slate',   hex: '#475569' },
  { name: 'Red',     hex: '#b42318' },
  { name: 'Orange',  hex: '#c2410c' },
  { name: 'Amber',   hex: '#a35c00' },
  { name: 'Yellow',  hex: '#854d0e' },
  { name: 'Lime',    hex: '#4d7c0f' },
  { name: 'Green',   hex: '#16794a' },
  { name: 'Teal',    hex: '#0f766e' },
  { name: 'Cyan',    hex: '#0e7490' },
  { name: 'Sky',     hex: '#0369a1' },
  { name: 'Blue',    hex: '#1e58b6' },
  { name: 'Indigo',  hex: '#5e6ad2' },
  { name: 'Violet',  hex: '#6f4ed3' },
  { name: 'Purple',  hex: '#7e22ce' },
  { name: 'Pink',    hex: '#be185d' },
  { name: 'Rose',    hex: '#be123c' },
];
window.TAG_PALETTE = TAG_PALETTE;

// Foreground for a tag chip background.
// Mirrors the Angular tagForeground() — luminance test.
function tagFg(hex) {
  const h = hex.replace('#', '');
  const r = parseInt(h.slice(0,2), 16);
  const g = parseInt(h.slice(2,4), 16);
  const b = parseInt(h.slice(4,6), 16);
  const lum = (0.299*r + 0.587*g + 0.114*b) / 255;
  return lum > 0.62 ? '#18181b' : '#ffffff';
}
window.tagFg = tagFg;

// Slight tint of a hex colour for soft backgrounds (used in pickers).
function tagTint(hex, alpha = 0.12) {
  const h = hex.replace('#', '');
  const r = parseInt(h.slice(0,2), 16);
  const g = parseInt(h.slice(2,4), 16);
  const b = parseInt(h.slice(4,6), 16);
  return `rgba(${r},${g},${b},${alpha})`;
}
window.tagTint = tagTint;

// ------------------------------------------------------------
// TagChip — the atomic unit
// ------------------------------------------------------------
function TagChip({ color, name, removable = false, onRemove, size = 'sm', soft = false, style }) {
  const h = size === 'xs' ? 16 : size === 'md' ? 22 : 18;
  const fs = size === 'xs' ? 10 : size === 'md' ? 12 : 11;
  const bg = soft ? tagTint(color, 0.13) : color;
  const fg = soft ? color : tagFg(color);
  const dot = soft && (
    <span style={{ width: 6, height: 6, borderRadius: '50%', background: color, flexShrink: 0 }}/>
  );
  return (
    <span style={{
      display: 'inline-flex',
      alignItems: 'center',
      gap: soft ? 5 : 4,
      height: h,
      padding: removable ? '0 2px 0 7px' : (soft ? '0 7px 0 6px' : '0 7px'),
      borderRadius: 4,
      background: bg,
      color: fg,
      fontSize: fs,
      fontWeight: 500,
      letterSpacing: '-0.005em',
      lineHeight: 1,
      whiteSpace: 'nowrap',
      ...style
    }}>
      {dot}
      <span>{name}</span>
      {removable && (
        <button
          onClick={onRemove}
          style={{
            appearance: 'none', border: 0, background: 'transparent', color: 'inherit',
            cursor: 'pointer', width: 14, height: 14, lineHeight: '14px',
            padding: 0, marginLeft: 1, borderRadius: 3, fontSize: 13, opacity: 0.85
          }}
          aria-label="Remove"
        >×</button>
      )}
    </span>
  );
}
window.TagChip = TagChip;

// ------------------------------------------------------------
// FileTypeIcon — small icon by extension / mime
// ------------------------------------------------------------
function FileTypeIcon({ ext, size = 28 }) {
  // Map extension → glyph + tint.
  const map = {
    pdf:  { tag: 'PDF',  fg: '#b42318', bg: '#fdecea' },
    doc:  { tag: 'DOC',  fg: '#1e58b6', bg: '#e6efff' },
    docx: { tag: 'DOC',  fg: '#1e58b6', bg: '#e6efff' },
    xls:  { tag: 'XLS',  fg: '#16794a', bg: '#e6f5ee' },
    xlsx: { tag: 'XLS',  fg: '#16794a', bg: '#e6f5ee' },
    csv:  { tag: 'CSV',  fg: '#16794a', bg: '#e6f5ee' },
    png:  { tag: 'IMG',  fg: '#6f4ed3', bg: '#f0ebfb' },
    jpg:  { tag: 'IMG',  fg: '#6f4ed3', bg: '#f0ebfb' },
    jpeg: { tag: 'IMG',  fg: '#6f4ed3', bg: '#f0ebfb' },
    svg:  { tag: 'IMG',  fg: '#6f4ed3', bg: '#f0ebfb' },
    gif:  { tag: 'IMG',  fg: '#6f4ed3', bg: '#f0ebfb' },
    md:   { tag: 'MD',   fg: '#18181b', bg: '#f4f4f5' },
    txt:  { tag: 'TXT',  fg: '#52525b', bg: '#f4f4f5' },
    log:  { tag: 'LOG',  fg: '#52525b', bg: '#f4f4f5' },
    json: { tag: 'JSON', fg: '#a35c00', bg: '#fbf2dd' },
    yaml: { tag: 'YML',  fg: '#a35c00', bg: '#fbf2dd' },
    yml:  { tag: 'YML',  fg: '#a35c00', bg: '#fbf2dd' },
    sql:  { tag: 'SQL',  fg: '#0e7490', bg: '#e0f2fe' },
    zip:  { tag: 'ZIP',  fg: '#52525b', bg: '#ebebed' },
    mp4:  { tag: 'MP4',  fg: '#be185d', bg: '#fce7f3' },
    mov:  { tag: 'MOV',  fg: '#be185d', bg: '#fce7f3' },
  };
  const m = map[(ext || '').toLowerCase()] || { tag: 'FILE', fg: '#52525b', bg: '#f4f4f5' };
  return (
    <span style={{
      display: 'inline-flex',
      alignItems: 'center',
      justifyContent: 'center',
      width: size, height: size,
      borderRadius: 5,
      background: m.bg,
      color: m.fg,
      fontFamily: 'JetBrains Mono, ui-monospace, monospace',
      fontSize: size <= 22 ? 8.5 : 9.5,
      fontWeight: 600,
      letterSpacing: '0.04em',
      flexShrink: 0,
    }}>{m.tag}</span>
  );
}
window.FileTypeIcon = FileTypeIcon;

// ------------------------------------------------------------
// Local feature icons
// ------------------------------------------------------------
const FIcon = {
  Paperclip: (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M11.5 7.5l-4 4a2 2 0 1 1-2.8-2.8l5-5a3 3 0 0 1 4.3 4.3l-5.3 5.3a4.5 4.5 0 0 1-6.4-6.4L7 2"/></svg>,
  Link:      (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M9 7a3 3 0 0 1 0 4.2l-1.5 1.5a3 3 0 0 1-4.2-4.2L4.5 7"/><path d="M7 9a3 3 0 0 1 0-4.2L8.5 3.3a3 3 0 0 1 4.2 4.2L11.5 9"/></svg>,
  Upload:    (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M8 11V3M5 6l3-3 3 3"/><path d="M3 11v1.5A1.5 1.5 0 0 0 4.5 14h7a1.5 1.5 0 0 0 1.5-1.5V11"/></svg>,
  Download:  (p) => <svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M8 3v8M5 8l3 3 3-3"/><path d="M3 12v0a1.5 1.5 0 0 0 1.5 1.5h7A1.5 1.5 0 0 0 13 12"/></svg>,
  Tag:       (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M2.5 2.5h5L13.5 8.5l-5 5L2.5 7.5z"/><circle cx="5" cy="5" r="0.8" fill="currentColor"/></svg>,
  ArrowR:    (p) => <svg width="11" height="11" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M3 8h10M9 4l4 4-4 4"/></svg>,
  Tree:      (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M3 3v5a2 2 0 0 0 2 2h8M5 10v3"/></svg>,
  Block:     (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" {...p}><circle cx="8" cy="8" r="5.5"/><path d="M4 4l8 8"/></svg>,
  Copy:      (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><rect x="5" y="5" width="9" height="9" rx="1.5"/><path d="M11 5V3.5A1.5 1.5 0 0 0 9.5 2h-6A1.5 1.5 0 0 0 2 3.5v6A1.5 1.5 0 0 0 3.5 11H5"/></svg>,
  Pencil:    (p) => <svg width="12" height="12" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" {...p}><path d="M11 2.5l2.5 2.5L5.5 13l-3 .5.5-3z"/></svg>,
};
window.FIcon = FIcon;

// ------------------------------------------------------------
// 04A — Tags · chip lib + management + picker
// ------------------------------------------------------------
function TagsBoard() {
  const sample = [
    ['bug',          '#b42318'],
    ['frontend',     '#5e6ad2'],
    ['backend',      '#0f766e'],
    ['needs-design', '#be185d'],
    ['blocked',      '#a35c00'],
    ['quick-win',    '#16794a'],
    ['tech-debt',    '#475569'],
    ['security',     '#7e22ce'],
    ['mcp',          '#6f4ed3'],
  ];

  const selectedOnTask = ['bug', 'frontend', 'mcp'];

  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%', display: 'flex', flexDirection: 'column', gap: 22 }}>
      <div>
        <div className="uk-section-title" style={{ marginBottom: 4 }}>Tags</div>
        <p className="uk-caption">Workspace-scoped labels. Solid chip on a curated palette · 16 colours · contrast-aware text.</p>
      </div>

      {/* === Chip lib === */}
      <div>
        <div className="uk-overline" style={{ marginBottom: 8 }}>Sizes &amp; states</div>
        <div style={{ display: 'grid', gridTemplateColumns: '110px 1fr', rowGap: 10, columnGap: 14, alignItems: 'center' }}>
          <span className="uk-caption">xs / 16</span>
          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            <TagChip size="xs" color="#5e6ad2" name="frontend"/>
            <TagChip size="xs" color="#16794a" name="quick-win"/>
            <TagChip size="xs" color="#b42318" name="bug"/>
          </div>

          <span className="uk-caption">sm / 18</span>
          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            <TagChip color="#5e6ad2" name="frontend"/>
            <TagChip color="#16794a" name="quick-win"/>
            <TagChip color="#b42318" name="bug"/>
            <TagChip color="#a35c00" name="blocked"/>
          </div>

          <span className="uk-caption">md / 22</span>
          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            <TagChip size="md" color="#5e6ad2" name="frontend"/>
            <TagChip size="md" color="#0f766e" name="backend"/>
            <TagChip size="md" color="#6f4ed3" name="mcp"/>
          </div>

          <span className="uk-caption">removable</span>
          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            <TagChip color="#5e6ad2" name="frontend" removable/>
            <TagChip color="#0f766e" name="backend" removable/>
            <TagChip color="#b42318" name="bug" removable/>
          </div>

          <span className="uk-caption">soft</span>
          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            <TagChip color="#5e6ad2" name="frontend" soft/>
            <TagChip color="#16794a" name="quick-win" soft/>
            <TagChip color="#a35c00" name="blocked" soft/>
            <span className="uk-caption" style={{ marginLeft: 6 }}>used in filter bars + table density</span>
          </div>
        </div>
      </div>

      <hr className="uk-hr"/>

      {/* === Management list + Picker side by side === */}
      <div style={{ display: 'grid', gridTemplateColumns: '1.35fr 1fr', gap: 18, alignItems: 'flex-start' }}>
        {/* Management list (workspace settings tab) */}
        <div className="uk-card" style={{ overflow: 'hidden' }}>
          <div style={{ padding: '12px 14px', borderBottom: '1px solid #e7e7ea', display: 'flex', alignItems: 'center' }}>
            <div>
              <div style={{ fontSize: 14, fontWeight: 600 }}>Workspace tags</div>
              <div className="uk-caption" style={{ fontSize: 11 }}>9 tags · only Admins &amp; Owners can manage</div>
            </div>
            <div style={{ marginLeft: 'auto', display: 'flex', gap: 6 }}>
              <div className="uk-input-group" style={{ height: 26, width: 160 }}>
                <Icon.Search style={{ color: '#8a8a92' }}/>
                <input className="uk-input" placeholder="Filter"/>
              </div>
              <button className="uk-btn uk-btn--primary uk-btn--sm"><Icon.Plus/>New tag</button>
            </div>
          </div>

          <div className="uk-list">
            {sample.map(([name, color], i) => (
              <div key={name} className="uk-row" style={{ padding: '8px 14px', minHeight: 36 }}>
                <TagChip color={color} name={name}/>
                <span className="uk-mono" style={{ color: '#8a8a92', fontSize: 11 }}>{color}</span>
                <span className="uk-caption" style={{ marginLeft: 'auto' }}>{[12,24,8,3,5,2,11,4,18][i]} tasks</span>
                <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs"><FIcon.Pencil/></button>
                <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" style={{ color: '#b42318' }}><Icon.Trash/></button>
              </div>
            ))}
          </div>

          {/* Edit row in-line */}
          <div style={{ borderTop: '1px solid #e7e7ea', background: '#fafafa', padding: 12 }}>
            <div className="uk-overline" style={{ marginBottom: 8 }}>Edit · &quot;backend&quot;</div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <input className="uk-input" defaultValue="backend" style={{ width: 160 }}/>
              <div style={{ display: 'flex', alignItems: 'center', gap: 4, padding: 4, border: '1px solid #e7e7ea', borderRadius: 5, background: '#fff' }}>
                {TAG_PALETTE.map(c => (
                  <button key={c.hex}
                    style={{
                      width: 18, height: 18, borderRadius: 3,
                      background: c.hex, border: '1px solid transparent',
                      cursor: 'pointer',
                      outline: c.hex === '#0f766e' ? '2px solid #18181b' : 'none',
                      outlineOffset: c.hex === '#0f766e' ? 2 : 0,
                    }}
                    title={c.name}
                  />
                ))}
              </div>
              <div style={{ flex: 1 }}/>
              <button className="uk-btn uk-btn--ghost uk-btn--sm">Cancel</button>
              <button className="uk-btn uk-btn--primary uk-btn--sm">Save</button>
            </div>
          </div>
        </div>

        {/* Picker popover (as opened from drawer) */}
        <div>
          <div className="uk-overline" style={{ marginBottom: 8 }}>On the task · &quot;UKO-318&quot;</div>

          {/* Chip row + Add button */}
          <div style={{
            border: '1px solid #e7e7ea', borderRadius: 7, background: '#fff', padding: 12
          }}>
            <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 10 }}>
              <span className="uk-overline">Tags</span>
              <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ marginLeft: 'auto', gap: 4 }}>
                <Icon.Plus/>Add tag
              </button>
            </div>
            <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6 }}>
              {selectedOnTask.map(n => {
                const c = sample.find(s => s[0] === n)[1];
                return <TagChip key={n} color={c} name={n} removable/>;
              })}
            </div>

            {/* Picker popover */}
            <div style={{
              marginTop: 10,
              border: '1px solid #d4d4d8',
              borderRadius: 7,
              background: '#fff',
              boxShadow: '0 16px 40px -8px rgba(24,24,27,0.14), 0 4px 8px rgba(24,24,27,0.06)',
              overflow: 'hidden'
            }}>
              <div style={{ padding: 8, borderBottom: '1px solid #e7e7ea' }}>
                <div className="uk-input-group" style={{ height: 28 }}>
                  <Icon.Search style={{ color: '#8a8a92' }}/>
                  <input className="uk-input" placeholder="Find or create tag…" defaultValue="bac"/>
                </div>
              </div>
              <div style={{ padding: 6, display: 'flex', flexDirection: 'column', gap: 1, maxHeight: 220 }}>
                {[
                  ['backend',      '#0f766e', true,  true],   // already on task → checked
                  ['needs-design', '#be185d', false, false],
                ].map(([n, c, _, checked]) => (
                  <button key={n} style={{
                    display: 'flex', alignItems: 'center', gap: 8,
                    padding: '5px 8px', borderRadius: 4,
                    background: 'transparent', border: 0, cursor: 'pointer',
                    textAlign: 'left',
                  }}>
                    <span style={{
                      width: 13, height: 13, borderRadius: 3,
                      border: '1px solid ' + (checked ? '#5e6ad2' : '#d4d4d8'),
                      background: checked ? '#5e6ad2' : '#fff',
                      display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                    }}>{checked && <Icon.Check style={{ color: '#fff', width: 9, height: 9 }}/>}</span>
                    <TagChip color={c} name={n}/>
                  </button>
                ))}
                <div style={{ height: 1, background: '#f4f4f5', margin: '4px 0' }}/>
                <button style={{
                  display: 'flex', alignItems: 'center', gap: 8,
                  padding: '6px 8px', borderRadius: 4,
                  background: '#fafafa', border: 0, cursor: 'pointer', textAlign: 'left'
                }}>
                  <Icon.Plus style={{ color: '#5e6ad2', width: 12, height: 12 }}/>
                  <span style={{ fontSize: 12 }}>
                    Create tag <span style={{ fontWeight: 600 }}>&ldquo;bac&rdquo;</span>
                  </span>
                </button>
              </div>
            </div>
          </div>

          <div style={{ marginTop: 10, padding: 10, background: '#f4f4f5', border: '1px solid #e7e7ea', borderRadius: 6, fontSize: 11, color: '#52525b', lineHeight: 1.55 }}>
            Picker is a popover anchored to the <em>Add tag</em> button. Type-ahead filters; pressing Enter with no exact match calls <code className="uk-mono">POST /workspaces/:id/tags</code> and attaches the new tag in the same gesture.
          </div>
        </div>
      </div>
    </div>
  );
}
window.TagsBoard = TagsBoard;

// ------------------------------------------------------------
// 04B — Files
// ------------------------------------------------------------
function FilesBoard() {
  const files = [
    { name: 'redis-migration-plan.md',       size: '12.4 KB', who: 'Marek Skopal · 2d ago',          ext: 'md',  agent: false },
    { name: 'cache-hit-ratio-grafana.png',   size: '418 KB',  who: 'Claude · via MCP · 4h ago',      ext: 'png', agent: true },
    { name: 'session-ttl-benchmark.csv',     size: '8.1 KB',  who: 'Cursor · via MCP · 4h ago',      ext: 'csv', agent: true },
    { name: 'audit-log-retention-RFC.pdf',   size: '342 KB',  who: 'Jakub Kostka · 1d ago',          ext: 'pdf', agent: false },
    { name: 'mcp_session_dir.yaml',          size: '1.2 KB',  who: 'Marek Skopal · 6h ago',          ext: 'yaml',agent: false },
  ];

  const Row = ({ f }) => (
    <li style={{
      display: 'grid',
      gridTemplateColumns: '32px 1fr auto auto auto',
      alignItems: 'center', gap: 10,
      padding: '8px 10px',
      border: '1px solid #e7e7ea',
      borderRadius: 5,
      background: '#fff',
    }}>
      <FileTypeIcon ext={f.ext} size={28}/>
      <div style={{ minWidth: 0 }}>
        <div style={{ fontSize: 13, fontWeight: 500, color: '#18181b', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{f.name}</div>
        <div style={{ display: 'flex', alignItems: 'center', gap: 6, fontSize: 11, color: '#8a8a92', marginTop: 1 }}>
          {f.agent && <span className="uk-badge uk-badge--ai" style={{ height: 14, padding: '0 5px' }}><Icon.Sparkle/>agent</span>}
          <span>{f.who}</span>
        </div>
      </div>
      <span style={{ fontSize: 11, color: '#8a8a92', whiteSpace: 'nowrap' }}>{f.size}</span>
      <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" title="Download"><FIcon.Download/></button>
      <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" style={{ color: '#b42318' }} title="Delete"><Icon.Trash/></button>
    </li>
  );

  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%', display: 'flex', flexDirection: 'column', gap: 20 }}>
      <div>
        <div className="uk-section-title" style={{ marginBottom: 4 }}>Task files</div>
        <p className="uk-caption">Per-task uploads · multipart/form-data · <code className="uk-mono">POST /tasks/:id/files</code>. Up to 25&nbsp;MB. Agents can upload too — tracked separately.</p>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18, alignItems: 'flex-start' }}>
        {/* Populated list */}
        <section>
          <div style={{ display: 'flex', alignItems: 'center', marginBottom: 8 }}>
            <span className="uk-overline">Populated</span>
            <span className="uk-caption" style={{ marginLeft: 'auto' }}>5 files · 782 KB total</span>
          </div>

          {/* Dropzone bar (calm — files already attached) */}
          <label style={{
            display: 'flex', alignItems: 'center', gap: 8,
            padding: '8px 12px',
            border: '1px dashed #d4d4d8', borderRadius: 5,
            background: '#fafafa',
            color: '#52525b', fontSize: 12, marginBottom: 8, cursor: 'pointer'
          }}>
            <FIcon.Upload style={{ color: '#5e6ad2' }}/>
            <span><strong style={{ color: '#18181b', fontWeight: 600 }}>Drop files</strong> or click to browse</span>
            <span className="uk-kbd" style={{ marginLeft: 'auto' }}>⌘U</span>
          </label>

          <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 6 }}>
            {files.map(f => <Row key={f.name} f={f}/>)}
          </ul>
        </section>

        {/* Empty + Uploading states */}
        <section style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          {/* Empty */}
          <div>
            <div className="uk-overline" style={{ marginBottom: 8 }}>Empty</div>
            <label style={{
              display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
              gap: 8, padding: '24px 12px',
              border: '1px dashed #d4d4d8', borderRadius: 6, background: '#fafafa',
              color: '#52525b', fontSize: 12, cursor: 'pointer', textAlign: 'center'
            }}>
              <span style={{
                width: 40, height: 40, borderRadius: 8, background: '#fff',
                border: '1px solid #e7e7ea',
                display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
                color: '#5e6ad2'
              }}><FIcon.Upload style={{ width: 18, height: 18 }}/></span>
              <div>
                <div style={{ color: '#18181b', fontWeight: 600, fontSize: 13 }}>Drop files to attach</div>
                <div style={{ marginTop: 2 }}>or <span style={{ color: '#5e6ad2', fontWeight: 500 }}>click to browse</span> · max 25 MB</div>
              </div>
            </label>
          </div>

          {/* Uploading */}
          <div>
            <div className="uk-overline" style={{ marginBottom: 8 }}>Uploading</div>
            <div style={{
              display: 'grid', gridTemplateColumns: '32px 1fr auto', gap: 10, alignItems: 'center',
              padding: '10px 10px',
              border: '1px solid #e7e7ea', borderRadius: 5, background: '#fff'
            }}>
              <FileTypeIcon ext="zip" size={28}/>
              <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 500 }}>regression-tests-output.zip</div>
                <div style={{ marginTop: 6, height: 4, background: '#ebebed', borderRadius: 2, overflow: 'hidden' }}>
                  <div style={{ width: '62%', height: '100%', background: '#5e6ad2' }}/>
                </div>
                <div style={{ fontSize: 11, color: '#8a8a92', marginTop: 4 }}>4.2 / 6.8 MB · 62% · 14 s left</div>
              </div>
              <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs"><Icon.X/></button>
            </div>
          </div>

          {/* Error */}
          <div>
            <div className="uk-overline" style={{ marginBottom: 8 }}>Error · too large</div>
            <div style={{
              display: 'grid', gridTemplateColumns: '32px 1fr auto', gap: 10, alignItems: 'center',
              padding: '10px 10px',
              border: '1px solid #f4b8b0', borderRadius: 5, background: '#fdecea'
            }}>
              <FileTypeIcon ext="mov" size={28}/>
              <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: 13, fontWeight: 500, color: '#18181b' }}>screen-recording.mov</div>
                <div style={{ fontSize: 11, color: '#b42318', marginTop: 2 }}>
                  File exceeds 25 MB limit (38 MB). Compress or attach a link instead.
                </div>
              </div>
              <button className="uk-btn uk-btn--ghost uk-btn--sm" style={{ color: '#b42318' }}>Retry</button>
            </div>
          </div>
        </section>
      </div>

      <div style={{ padding: 10, background: '#f4f4f5', border: '1px solid #e7e7ea', borderRadius: 6, fontSize: 11, color: '#52525b', lineHeight: 1.55 }}>
        <strong style={{ color: '#18181b' }}>Anatomy.</strong> Mono file-type chip · name + uploader · size · download / delete. Agent uploads carry the violet <em>agent</em> pill in the meta row so audit context stays one glance away.
      </div>
    </div>
  );
}
window.FilesBoard = FilesBoard;

// ------------------------------------------------------------
// 04C — Related tasks
// ------------------------------------------------------------

// Direction-aware labels for the 4 relation types.
// Source side = "this task is the X of the target"; mirror it for incoming.
const RELATION = {
  Parent:    { out: { label: 'Sub-tasks',     icon: <FIcon.Tree/>,   color: '#0e7490' },
               in:  { label: 'Parent task',   icon: <FIcon.Tree/>,   color: '#0e7490' } },
  DependsOn: { out: { label: 'Depends on',    icon: <FIcon.Block/>,  color: '#a35c00' },
               in:  { label: 'Blocks',        icon: <FIcon.Block/>,  color: '#b42318' } },
  Duplicates:{ out: { label: 'Duplicates',    icon: <FIcon.Copy/>,   color: '#6f4ed3' },
               in:  { label: 'Duplicated by', icon: <FIcon.Copy/>,   color: '#6f4ed3' } },
  Related:   { out: { label: 'Related',       icon: <FIcon.Link/>,   color: '#5e6ad2' },
               in:  { label: 'Related',       icon: <FIcon.Link/>,   color: '#5e6ad2' } },
};
window.RELATION = RELATION;

function RelationTypeChip({ type, dir = 'out' }) {
  const r = RELATION[type][dir];
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: 4,
      height: 18, padding: '0 6px', borderRadius: 4,
      background: '#fff', border: '1px solid #e7e7ea',
      color: r.color, fontSize: 11, fontWeight: 500, letterSpacing: '-0.005em',
    }}>{r.icon}{r.label}</span>
  );
}
window.RelationTypeChip = RelationTypeChip;

function RelationRow({ id, name, project, statusColor, type, dir = 'out', overdue }) {
  return (
    <li style={{
      display: 'grid',
      gridTemplateColumns: 'auto 60px 1fr auto auto auto',
      alignItems: 'center', gap: 10,
      padding: '7px 10px',
      border: '1px solid #e7e7ea', borderRadius: 5, background: '#fff',
      cursor: 'pointer',
    }}>
      <span style={{ width: 8, height: 8, borderRadius: '50%', background: statusColor, flexShrink: 0 }}/>
      <span className="uk-mono" style={{ color: '#8a8a92', fontSize: 11 }}>UKO-{id}</span>
      <span style={{ fontSize: 13, color: '#18181b', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{name}</span>
      <span className="uk-caption" style={{ fontSize: 11 }}>{project}</span>
      {overdue
        ? <span className="uk-badge uk-badge--danger" style={{ height: 16 }}>overdue</span>
        : <span style={{ width: 0 }}/>}
      <button className="uk-btn uk-btn--ghost uk-btn--icon uk-btn--xs" style={{ color: '#b42318' }}><Icon.X/></button>
    </li>
  );
}

function RelationsBoard() {
  return (
    <div className="uk" style={{ padding: 28, background: '#fff', height: '100%', display: 'flex', flexDirection: 'column', gap: 20 }}>
      <div>
        <div className="uk-section-title" style={{ marginBottom: 4 }}>Related tasks</div>
        <p className="uk-caption">4 relation types · direction-aware labels · grouped in the drawer. Same task pair can carry multiple relations.</p>
      </div>

      {/* Type chips reference */}
      <div>
        <div className="uk-overline" style={{ marginBottom: 8 }}>Types · outgoing &rarr; incoming</div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 10 }}>
          {[
            ['Parent',     'Source is the parent of target'],
            ['DependsOn',  'Source needs target finished first'],
            ['Duplicates', 'Source is a duplicate of target'],
            ['Related',    'Loose, undirected link'],
          ].map(([t, sub]) => (
            <div key={t} style={{ padding: 10, border: '1px solid #e7e7ea', borderRadius: 6 }}>
              <div className="uk-mono" style={{ fontSize: 10, color: '#8a8a92', marginBottom: 6 }}>{t}</div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 8 }}>
                <RelationTypeChip type={t} dir="out"/>
                <FIcon.ArrowR style={{ color: '#b4b4ba' }}/>
                <RelationTypeChip type={t} dir="in"/>
              </div>
              <div className="uk-caption" style={{ fontSize: 11, lineHeight: 1.4 }}>{sub}</div>
            </div>
          ))}
        </div>
      </div>

      <hr className="uk-hr"/>

      {/* Picker open + grouped list */}
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1.15fr', gap: 18, alignItems: 'flex-start' }}>
        {/* Picker */}
        <div>
          <div className="uk-overline" style={{ marginBottom: 8 }}>Add relation</div>
          <div style={{
            padding: 12, border: '1px solid #e7e7ea', borderRadius: 7, background: '#fff'
          }}>
            <div style={{ display: 'grid', gridTemplateColumns: 'auto 1fr auto', gap: 8, alignItems: 'center' }}>
              <select className="uk-select" defaultValue="DependsOn" style={{ width: 140 }}>
                <option value="Parent">Parent of</option>
                <option value="DependsOn">Depends on</option>
                <option value="Duplicates">Duplicates</option>
                <option value="Related">Related</option>
              </select>
              <div className="uk-input-group" style={{ height: 30 }}>
                <Icon.Search style={{ color: '#8a8a92' }}/>
                <input className="uk-input" defaultValue="redis" placeholder="Search tasks by name or ID…"/>
              </div>
              <button className="uk-btn uk-btn--ghost uk-btn--sm">Cancel</button>
            </div>

            <ul style={{ listStyle: 'none', padding: 0, margin: '10px 0 0', display: 'flex', flexDirection: 'column', gap: 4 }}>
              {[
                ['322', 'Redis cluster failover runbook',     'Backend rewrite', '#94a3a8'],
                ['318', 'Migrate sessions to Redis',          'Backend rewrite', '#c98a14', true],
                ['305', 'Add Redis health endpoint',          'Backend rewrite', '#16794a'],
                ['291', 'Document Redis env vars',            'MCP onboarding',  '#4a8fd6'],
              ].map(([id, name, proj, c, isSelf]) => (
                <li key={id}>
                  <button disabled={isSelf} style={{
                    width: '100%',
                    display: 'grid', gridTemplateColumns: 'auto 60px 1fr auto', gap: 10, alignItems: 'center',
                    padding: '6px 8px', borderRadius: 5,
                    background: 'transparent', border: '1px solid transparent', textAlign: 'left',
                    cursor: isSelf ? 'not-allowed' : 'pointer',
                    opacity: isSelf ? 0.5 : 1,
                    font: 'inherit'
                  }}>
                    <span style={{ width: 8, height: 8, borderRadius: '50%', background: c }}/>
                    <span className="uk-mono" style={{ color: '#8a8a92', fontSize: 11 }}>UKO-{id}</span>
                    <span style={{ fontSize: 13, color: '#18181b', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{name}</span>
                    <span className="uk-caption" style={{ fontSize: 11 }}>
                      {isSelf ? 'this task' : proj}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </div>

          <div style={{ marginTop: 10, padding: 10, background: '#f4f4f5', border: '1px solid #e7e7ea', borderRadius: 6, fontSize: 11, color: '#52525b', lineHeight: 1.55 }}>
            Self-link is disabled. Existing pairs are filtered out by relation type — same pair can still carry, say, both <em>Related</em> and <em>Duplicates</em>.
          </div>
        </div>

        {/* Grouped list */}
        <div>
          <div className="uk-overline" style={{ marginBottom: 8 }}>Grouped on the task</div>

          <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 6 }}>
                <RelationTypeChip type="Parent" dir="in"/>
                <span className="uk-caption" style={{ fontSize: 11 }}>1</span>
              </div>
              <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                <RelationRow id="300" name="Backend rewrite epic"   project="Backend rewrite" statusColor="#c98a14" type="Parent" dir="in"/>
              </ul>
            </div>

            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 6 }}>
                <RelationTypeChip type="DependsOn" dir="out"/>
                <span className="uk-caption" style={{ fontSize: 11 }}>2</span>
              </div>
              <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                <RelationRow id="305" name="Add Redis health endpoint"   project="Backend rewrite" statusColor="#16794a" type="DependsOn" dir="out"/>
                <RelationRow id="315" name="Audit log retention policy"  project="Backend rewrite" statusColor="#c98a14" type="DependsOn" dir="out" overdue/>
              </ul>
            </div>

            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 6 }}>
                <RelationTypeChip type="DependsOn" dir="in"/>
                <span className="uk-caption" style={{ fontSize: 11 }}>1</span>
              </div>
              <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                <RelationRow id="321" name="Add metrics for cache hit ratio" project="Backend rewrite" statusColor="#c98a14" type="DependsOn" dir="in"/>
              </ul>
            </div>

            <div>
              <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: 6 }}>
                <RelationTypeChip type="Related" dir="out"/>
                <span className="uk-caption" style={{ fontSize: 11 }}>1</span>
              </div>
              <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 4 }}>
                <RelationRow id="317" name="Document MCP OAuth + PKCE flow" project="MCP onboarding" statusColor="#4a8fd6" type="Related" dir="out"/>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
window.RelationsBoard = RelationsBoard;
