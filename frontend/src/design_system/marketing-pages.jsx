// ============================================================
// Kytario — additional marketing pages
// Pricing · Contact · Text (legal/long-form)
// Reuses LandingTopBar / LandingFooter from landing.jsx
// ============================================================

// =============================================================
//  Helper: page shell so every marketing page shares chrome
// =============================================================
function MarketingPage({ children }) {
  return (
    <div className="uk" style={{
      minHeight: '100%', width: '100%',
      background: 'var(--uk-bg)',
      display: 'flex', flexDirection: 'column',
    }}>
      <LandingTopBar />
      <main style={{ flex: 1 }}>{children}</main>
      <LandingFooter />
    </div>
  );
}

// =============================================================
//  1. PRICING
// =============================================================
function PricingScreen() {
  const [annual, setAnnual] = React.useState(true);

  const proMonthly = 12;
  const proAnnual  = 9; // $9/mo billed annually (~25% off the round monthly)

  const free = {
    name: 'Free',
    blurb: 'For individuals and small teams trying out MCP-driven task management.',
    price: 0,
    suffix: 'forever',
    cta: 'Start free',
    ctaClass: 'uk-btn--secondary',
    features: [
      '1 workspace, up to 3 members',
      'Up to 5 projects · unlimited tasks',
      'Kanban + workspace task grid',
      'Connect 1 MCP client (Claude, Cursor, …)',
      'Comments, attachments up to 25 MB / file',
      'Community support',
    ],
  };

  const pro = {
    name: 'Pro',
    blurb: 'For teams who run their work through agents and need the full surface area.',
    price: annual ? proAnnual : proMonthly,
    suffix: annual ? 'per member · billed annually' : 'per member · billed monthly',
    cta: 'Start 14-day trial',
    ctaClass: 'uk-btn--primary',
    badge: 'Most teams',
    features: [
      'Unlimited workspaces, members & projects',
      'Unlimited MCP clients per user',
      'Custom fields (Text, Select, Version, …)',
      'Tags, typed task relations, dependencies',
      'File attachments up to 1 GB / file',
      'Agent activity log + export',
      'SSO (SAML / OIDC) · audit retention 1 yr',
      'Priority support · 99.9 % SLA',
    ],
  };

  const PlanCard = ({ plan, highlighted }) => (
    <div className="uk-card" style={{
      padding: 28,
      borderColor: highlighted ? 'var(--uk-fg)' : 'var(--uk-border)',
      borderWidth: highlighted ? 1.5 : 1,
      position: 'relative',
      boxShadow: highlighted ? 'var(--uk-shadow-md)' : 'none',
      display: 'flex', flexDirection: 'column',
    }}>
      {plan.badge && (
        <span style={{
          position: 'absolute', top: -10, left: 28,
          background: 'var(--uk-fg)', color: '#fff',
          fontSize: 11, fontWeight: 500,
          padding: '4px 10px', borderRadius: 999,
          letterSpacing: '-0.005em',
        }}>{plan.badge}</span>
      )}

      <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between' }}>
        <h3 style={{ fontSize: 18, fontWeight: 600, letterSpacing: '-0.012em' }}>{plan.name}</h3>
      </div>
      <p style={{ marginTop: 6, fontSize: 13.5, color: 'var(--uk-fg-muted)', lineHeight: 1.55, minHeight: 38 }}>
        {plan.blurb}
      </p>

      <div style={{ marginTop: 20, display: 'flex', alignItems: 'flex-end', gap: 6 }}>
        <span style={{ fontSize: 44, fontWeight: 600, letterSpacing: '-0.03em', lineHeight: 1 }}>
          ${plan.price}
        </span>
        <span style={{ fontSize: 13, color: 'var(--uk-fg-subtle)', marginBottom: 6 }}>
          {plan.price === 0 ? '' : '/ mo'}
        </span>
      </div>
      <div style={{ marginTop: 4, fontSize: 12, color: 'var(--uk-fg-subtle)' }}>
        {plan.suffix}
      </div>

      <button className={`uk-btn ${plan.ctaClass} uk-btn--lg`}
              style={{ marginTop: 22, width: '100%' }}>
        {plan.cta}
      </button>

      <hr className="uk-hr" style={{ margin: '24px 0 18px' }}/>

      <ul style={{
        listStyle: 'none', padding: 0, margin: 0,
        display: 'flex', flexDirection: 'column', gap: 10,
      }}>
        {plan.features.map(f => (
          <li key={f} style={{
            display: 'flex', gap: 10, alignItems: 'flex-start',
            fontSize: 13, lineHeight: 1.5, color: 'var(--uk-fg)',
          }}>
            <span style={{
              flexShrink: 0, marginTop: 3,
              width: 14, height: 14, borderRadius: 999,
              background: 'var(--uk-success-soft)', color: 'var(--uk-success)',
              display: 'grid', placeItems: 'center',
            }}>
              <svg width="9" height="9" viewBox="0 0 16 16" fill="none"
                   stroke="currentColor" strokeWidth="2.4"
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

  // —— Billing toggle ——
  const Toggle = () => (
    <div style={{
      display: 'inline-flex',
      alignItems: 'center', gap: 0,
      padding: 4,
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
                    height: 32, padding: '0 16px', borderRadius: 999,
                    fontSize: 13, fontWeight: 500,
                    letterSpacing: '-0.005em',
                    background: active ? 'var(--uk-fg)' : 'transparent',
                    color: active ? '#fff' : 'var(--uk-fg-muted)',
                    display: 'inline-flex', alignItems: 'center', gap: 8,
                    transition: 'background 120ms, color 120ms',
                  }}>
            {opt.label}
            {opt.id === 'annual' && (
              <span style={{
                fontSize: 10, fontWeight: 600,
                padding: '2px 6px', borderRadius: 999,
                background: active ? 'rgba(255,255,255,0.16)' : 'var(--uk-success-soft)',
                color: active ? '#fff' : 'var(--uk-success)',
                letterSpacing: '0.02em',
              }}>−20%</span>
            )}
          </button>
        );
      })}
    </div>
  );

  return (
    <MarketingPage>
      <section style={{ padding: '88px 0 24px', textAlign: 'center' }}>
        <div style={{ maxWidth: 720, margin: '0 auto', padding: '0 28px' }}>
          <div style={{
            fontSize: 11, fontWeight: 600, letterSpacing: '0.1em',
            textTransform: 'uppercase', color: 'var(--uk-accent)',
            marginBottom: 12,
          }}>Pricing</div>
          <h1 style={{
            fontSize: 48, lineHeight: 1.1, letterSpacing: '-0.03em',
            fontWeight: 600,
          }}>Simple pricing.<br/>Free forever for small teams.</h1>
          <p style={{
            marginTop: 18, fontSize: 16.5, lineHeight: 1.55,
            color: 'var(--uk-fg-muted)',
          }}>
            Pay per active member, per month. Cancel anytime. Self-host the open-source
            edition free of charge — Pro features included.
          </p>

          <div style={{ marginTop: 32 }}>
            <Toggle />
          </div>
        </div>
      </section>

      <section style={{ padding: '24px 0 72px' }}>
        <div style={{ maxWidth: 880, margin: '0 auto', padding: '0 28px' }}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 20 }}>
            <PlanCard plan={free} highlighted={false} />
            <PlanCard plan={pro}  highlighted={true} />
          </div>

          <p style={{
            marginTop: 22, textAlign: 'center',
            fontSize: 12.5, color: 'var(--uk-fg-subtle)',
          }}>
            Prices in USD. Annual billing charged once per year. VAT applied where required.
          </p>
        </div>
      </section>

      {/* Community / Open source */}
      <section style={{ padding: '64px 0', borderTop: '1px solid var(--uk-border)' }}>
        <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 28px' }}>
          <CommunityPlan />
        </div>
      </section>

      {/* Compare row */}
      <section style={{ padding: '56px 0', borderTop: '1px solid var(--uk-border)' }}>
        <div style={{ maxWidth: 1120, margin: '0 auto', padding: '0 28px' }}>
          <SectionHead
            eyebrow="Compare"
            title="What's in each plan."
            align="left"
          />
          <PricingTable annual={annual} />
        </div>
      </section>

      {/* FAQ */}
      <section style={{ padding: '64px 0', borderTop: '1px solid var(--uk-border)' }}>
        <div style={{ maxWidth: 880, margin: '0 auto', padding: '0 28px' }}>
          <SectionHead
            eyebrow="FAQ"
            title="Things people ask before signing up."
            align="left"
          />
          <PricingFAQ />
        </div>
      </section>
    </MarketingPage>
  );
}

function CommunityPlan() {
  return (
    <div style={{
      display: 'grid',
      gridTemplateColumns: '1fr 1.2fr',
      gap: 36,
      alignItems: 'center',
      padding: 36,
      borderRadius: 14,
      background: 'var(--uk-fg)',
      color: 'var(--uk-bg)',
      position: 'relative',
      overflow: 'hidden',
    }}>
      {/* Left: pitch */}
      <div>
        <div style={{
          fontSize: 11, fontWeight: 600, letterSpacing: '0.1em',
          textTransform: 'uppercase', opacity: 0.6, marginBottom: 12,
        }}>Community · MIT</div>

        <h2 style={{
          fontSize: 32, lineHeight: 1.15, letterSpacing: '-0.02em',
          fontWeight: 600, marginBottom: 14,
        }}>Kytario Community Edition.</h2>

        <p style={{
          fontSize: 15, lineHeight: 1.6, opacity: 0.78,
          maxWidth: 460,
        }}>
          The full source — server, web client, and MCP bridge — published under the MIT
          license. Clone it, run it on your own hardware, hack on it, ship it inside your
          org. Free of charge, forever.
        </p>

        <div style={{ display: 'flex', gap: 10, marginTop: 24, flexWrap: 'wrap' }}>
          <a href="https://github.com/kytario/kytario"
             style={{
               display: 'inline-flex', alignItems: 'center', gap: 10,
               background: 'var(--uk-bg)', color: 'var(--uk-fg)',
               padding: '11px 18px', borderRadius: 8,
               fontSize: 13.5, fontWeight: 500,
               textDecoration: 'none',
               letterSpacing: '-0.005em',
             }}>
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden>
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
          <a href="https://github.com/kytario/kytario#self-hosting"
             style={{
               display: 'inline-flex', alignItems: 'center', gap: 8,
               background: 'transparent', color: 'var(--uk-bg)',
               border: '1px solid rgba(255,255,255,0.22)',
               padding: '11px 18px', borderRadius: 8,
               fontSize: 13.5, fontWeight: 500,
               textDecoration: 'none',
               letterSpacing: '-0.005em',
             }}>
            Self-host guide →
          </a>
        </div>

        {/* Repo stats row */}
        <div style={{
          marginTop: 26, display: 'flex', gap: 22,
          fontSize: 12, opacity: 0.62,
        }}>
          <span><b style={{ fontWeight: 600, opacity: 1.4 }}>4.2k</b> stars</span>
          <span><b style={{ fontWeight: 600, opacity: 1.4 }}>312</b> forks</span>
          <span><b style={{ fontWeight: 600, opacity: 1.4 }}>v0.18.2</b> latest release</span>
          <span style={{ fontFamily: 'var(--uk-font-mono)' }}>MIT</span>
        </div>
      </div>

      {/* Right: what's in / what's not */}
      <div style={{
        background: 'rgba(255,255,255,0.04)',
        border: '1px solid rgba(255,255,255,0.10)',
        borderRadius: 10,
        padding: 24,
      }}>
        <div style={{
          display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 24,
        }}>
          <div>
            <div style={{
              fontSize: 10.5, fontWeight: 600, letterSpacing: '0.08em',
              textTransform: 'uppercase', opacity: 0.55, marginBottom: 10,
            }}>What's included</div>
            <ul style={{
              listStyle: 'none', padding: 0, margin: 0,
              display: 'flex', flexDirection: 'column', gap: 8,
              fontSize: 13, lineHeight: 1.45,
            }}>
              {[
                'Full Kanban + workspace grid',
                'MCP server + OAuth flow',
                'Custom fields & typed relations',
                'docker compose up to run',
                'Schema migrations & CLI',
              ].map(f => (
                <li key={f} style={{ display: 'flex', gap: 9, alignItems: 'flex-start' }}>
                  <span style={{
                    flexShrink: 0, marginTop: 5,
                    width: 5, height: 5, borderRadius: 999,
                    background: 'var(--uk-bg)',
                  }}/>
                  <span style={{ opacity: 0.86 }}>{f}</span>
                </li>
              ))}
            </ul>
          </div>
          <div>
            <div style={{
              fontSize: 10.5, fontWeight: 600, letterSpacing: '0.08em',
              textTransform: 'uppercase', opacity: 0.55, marginBottom: 10,
            }}>Not included</div>
            <ul style={{
              listStyle: 'none', padding: 0, margin: 0,
              display: 'flex', flexDirection: 'column', gap: 8,
              fontSize: 13, lineHeight: 1.45,
            }}>
              {[
                'Vendor support or SLA',
                'Hosted infra & backups',
                'SSO (SAML / OIDC)',
                'Warranty of any kind',
                'Help with your deployment',
              ].map(f => (
                <li key={f} style={{ display: 'flex', gap: 9, alignItems: 'flex-start' }}>
                  <span style={{
                    flexShrink: 0, marginTop: 5,
                    width: 5, height: 5, borderRadius: 999,
                    background: 'transparent',
                    border: '1px solid rgba(255,255,255,0.35)',
                  }}/>
                  <span style={{ opacity: 0.62 }}>{f}</span>
                </li>
              ))}
            </ul>
          </div>
        </div>

        {/* Disclaimer */}
        <div style={{
          marginTop: 22,
          padding: '12px 14px',
          borderRadius: 8,
          background: 'rgba(255,255,255,0.05)',
          border: '1px solid rgba(255,255,255,0.08)',
          fontFamily: 'var(--uk-font-mono)',
          fontSize: 11.5, lineHeight: 1.55,
          opacity: 0.7,
          letterSpacing: '-0.005em',
        }}>
          {'// '}Provided <b style={{ opacity: 1.4, fontWeight: 600 }}>“as is”</b>,
          without warranty of any kind. No support, no SLA, no guarantees of fitness
          for a particular purpose. You run it, you own it.
        </div>
      </div>
    </div>
  );
}

function PricingTable({ annual }) {
  const Row = ({ label, free, pro, hint }) => (
    <tr>
      <td style={{ padding: '14px 16px', borderBottom: '1px solid var(--uk-border)' }}>
        <div style={{ fontSize: 13.5, color: 'var(--uk-fg)' }}>{label}</div>
        {hint && <div style={{ fontSize: 12, color: 'var(--uk-fg-subtle)', marginTop: 2 }}>{hint}</div>}
      </td>
      <td style={{ padding: '14px 16px', borderBottom: '1px solid var(--uk-border)', textAlign: 'center', fontSize: 13, color: 'var(--uk-fg-muted)' }}>
        {typeof free === 'boolean' ? (free ? <Check/> : <Dash/>) : free}
      </td>
      <td style={{ padding: '14px 16px', borderBottom: '1px solid var(--uk-border)', textAlign: 'center', fontSize: 13, color: 'var(--uk-fg)' }}>
        {typeof pro === 'boolean' ? (pro ? <Check/> : <Dash/>) : pro}
      </td>
    </tr>
  );

  return (
    <div style={{
      border: '1px solid var(--uk-border)', borderRadius: 10,
      overflow: 'hidden', background: 'var(--uk-surface)',
    }}>
      <table style={{ width: '100%', borderCollapse: 'separate', borderSpacing: 0 }}>
        <thead>
          <tr>
            <th style={cellTh}>Plan</th>
            <th style={{ ...cellTh, textAlign: 'center', width: 200 }}>Free</th>
            <th style={{ ...cellTh, textAlign: 'center', width: 200 }}>
              Pro <span style={{ color: 'var(--uk-fg-subtle)', fontWeight: 400 }}>
                · ${annual ? 9 : 12}/mo
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          <Row label="Workspaces"          free="1"               pro="Unlimited" />
          <Row label="Members per workspace" free="Up to 3"        pro="Unlimited" />
          <Row label="Projects"            free="Up to 5"          pro="Unlimited" />
          <Row label="Tasks"               free="Unlimited"        pro="Unlimited" />
          <Row label="MCP clients per user" free="1"              pro="Unlimited" />
          <Row label="Custom fields"       free={false}            pro={true} hint="Text, Textarea, Select, semver Version" />
          <Row label="Tags & typed relations" free={false}         pro={true} />
          <Row label="File attachments"    free="25 MB / file"     pro="1 GB / file" />
          <Row label="Agent activity log"  free="30 days"          pro="1 year · exportable" />
          <Row label="SSO (SAML / OIDC)"   free={false}            pro={true} />
          <Row label="Support"             free="Community"        pro="Priority · 99.9 % SLA" />
        </tbody>
      </table>
    </div>
  );
}

const cellTh = {
  textAlign: 'left',
  padding: '14px 16px',
  fontSize: 11,
  fontWeight: 600,
  textTransform: 'uppercase',
  letterSpacing: '0.08em',
  color: 'var(--uk-fg-subtle)',
  borderBottom: '1px solid var(--uk-border)',
  background: 'var(--uk-surface-2)',
};

const Check = () => (
  <span style={{
    display: 'inline-grid', placeItems: 'center',
    width: 18, height: 18, borderRadius: 999,
    background: 'var(--uk-success-soft)', color: 'var(--uk-success)',
  }}>
    <svg width="11" height="11" viewBox="0 0 16 16" fill="none"
         stroke="currentColor" strokeWidth="2.4"
         strokeLinecap="round" strokeLinejoin="round">
      <path d="M3 8.5l3 3 6-7"/>
    </svg>
  </span>
);
const Dash = () => <span style={{ color: 'var(--uk-fg-faint)' }}>—</span>;

function PricingFAQ() {
  const items = [
    {
      q: 'Can I self-host Kytario?',
      a: 'Yes. The full source is MIT-licensed. A single docker compose up gives you the hosted experience locally, including the MCP server and OAuth flow.',
    },
    {
      q: 'How is a "member" counted?',
      a: 'A member is anyone who has been added to one of your workspaces and accepted the invitation. Pending invitations and guests on shared links do not count.',
    },
    {
      q: 'Do agents count as members?',
      a: 'No. Agents authenticate as a user — usage from any MCP client a member connects is included in that member\'s seat.',
    },
    {
      q: 'Can I switch plans or billing periods later?',
      a: 'Anytime. Downgrades take effect at the next renewal; upgrades are pro-rated immediately. Switching to annual locks in the 20% discount.',
    },
    {
      q: 'What happens to my data if I cancel?',
      a: 'You keep 90 days of read-only access plus a one-click full export (JSON + attachments). After that the workspace is permanently deleted.',
    },
    {
      q: 'Do you offer discounts for non-profits or education?',
      a: 'Yes — 50% off Pro for registered non-profits and accredited educational institutions. Contact info@kytario.com.',
    },
  ];

  return (
    <div style={{
      border: '1px solid var(--uk-border)', borderRadius: 10,
      background: 'var(--uk-surface)',
    }}>
      {items.map((it, i) => (
        <div key={it.q} style={{
          padding: '20px 22px',
          borderTop: i === 0 ? 'none' : '1px solid var(--uk-border)',
        }}>
          <h3 style={{ fontSize: 14, fontWeight: 600, marginBottom: 6, letterSpacing: '-0.008em' }}>{it.q}</h3>
          <p style={{ fontSize: 13.5, lineHeight: 1.6, color: 'var(--uk-fg-muted)' }}>{it.a}</p>
        </div>
      ))}
    </div>
  );
}

// =============================================================
//  2. CONTACT
// =============================================================
function ContactScreen() {
  return (
    <MarketingPage>
      <section style={{ padding: '120px 0 96px' }}>
        <div style={{
          maxWidth: 720, margin: '0 auto', padding: '0 28px',
          textAlign: 'center',
        }}>
          <div style={{
            fontSize: 11, fontWeight: 600, letterSpacing: '0.1em',
            textTransform: 'uppercase', color: 'var(--uk-accent)',
            marginBottom: 12,
          }}>Contact</div>

          <h1 style={{
            fontSize: 48, lineHeight: 1.1, letterSpacing: '-0.03em',
            fontWeight: 600,
          }}>Say hello.</h1>

          <p style={{
            marginTop: 18, maxWidth: 520, marginLeft: 'auto', marginRight: 'auto',
            fontSize: 16.5, lineHeight: 1.55, color: 'var(--uk-fg-muted)',
          }}>
            Questions about pricing, self-hosting, security, or just curious what running
            a Kanban with agents is like? Drop us a line — a human writes back.
          </p>

          {/* The card */}
          <div style={{
            marginTop: 48,
            display: 'inline-flex', flexDirection: 'column',
            background: 'var(--uk-surface)',
            border: '1px solid var(--uk-border)',
            borderRadius: 14,
            padding: '32px 40px',
            boxShadow: 'var(--uk-shadow-md)',
            minWidth: 460,
          }}>
            <div style={{
              alignSelf: 'center',
              width: 44, height: 44, borderRadius: 10,
              background: 'var(--uk-accent-soft)', color: 'var(--uk-accent)',
              display: 'grid', placeItems: 'center',
              marginBottom: 18,
            }}>
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor" strokeWidth="1.7"
                   strokeLinecap="round" strokeLinejoin="round">
                <rect x="2" y="4" width="20" height="16" rx="2"/>
                <path d="m2 7 10 7 10-7"/>
              </svg>
            </div>

            <div style={{
              fontSize: 11, fontWeight: 600, letterSpacing: '0.1em',
              textTransform: 'uppercase', color: 'var(--uk-fg-subtle)',
              marginBottom: 8, textAlign: 'center',
            }}>Email us</div>

            <a href="mailto:info@kytario.com" style={{
              fontSize: 28, fontWeight: 600, letterSpacing: '-0.018em',
              color: 'var(--uk-fg)', textDecoration: 'none',
              fontFamily: 'var(--uk-font-mono)',
              textAlign: 'center',
            }}>info@kytario.com</a>

            <div style={{
              marginTop: 22, paddingTop: 18,
              borderTop: '1px solid var(--uk-border)',
              display: 'flex', alignItems: 'center', gap: 8, justifyContent: 'center',
              fontSize: 12.5, color: 'var(--uk-fg-subtle)',
            }}>
              <span style={{
                width: 6, height: 6, borderRadius: 999,
                background: 'var(--uk-success)',
              }}/>
              We reply within one business day, in English or Czech.
            </div>
          </div>

          <p style={{
            marginTop: 40, fontSize: 13, color: 'var(--uk-fg-subtle)',
          }}>
            For security disclosures, please email <a href="mailto:security@kytario.com" style={{ color: 'var(--uk-accent)' }}>security@kytario.com</a>.
          </p>
        </div>
      </section>
    </MarketingPage>
  );
}

// =============================================================
//  3. TEXT PAGE — legal / long-form prose template
// =============================================================
function TextPageScreen() {
  // shared prose styles — used by the rendered example
  const proseStyle = {
    fontFamily: 'var(--uk-font-sans)',
    fontSize: 16,
    lineHeight: 1.7,
    color: 'var(--uk-fg)',
  };

  return (
    <MarketingPage>
      <section style={{ padding: '64px 0 96px' }}>
        <div style={{
          maxWidth: 980, margin: '0 auto', padding: '0 28px',
          display: 'grid', gridTemplateColumns: '220px 1fr',
          gap: 56, alignItems: 'start',
        }}>
          {/* TOC sidebar */}
          <aside style={{
            position: 'sticky', top: 80,
            fontSize: 13,
          }}>
            <div style={{
              fontSize: 11, fontWeight: 600, letterSpacing: '0.1em',
              textTransform: 'uppercase', color: 'var(--uk-fg-subtle)',
              marginBottom: 12,
            }}>On this page</div>
            <nav style={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
              {[
                { id: 'intro',     label: 'Introduction', active: true },
                { id: 'accounts',  label: 'Accounts & access' },
                { id: 'agents',    label: 'Agents & MCP clients' },
                { id: 'data',      label: 'Your data' },
                { id: 'billing',   label: 'Billing' },
                { id: 'liability', label: 'Liability' },
                { id: 'contact',   label: 'Contact' },
              ].map(it => (
                <a key={it.id} href={`#${it.id}`} style={{
                  padding: '6px 10px',
                  borderLeft: it.active ? '2px solid var(--uk-accent)' : '2px solid transparent',
                  fontSize: 13, lineHeight: 1.4,
                  color: it.active ? 'var(--uk-fg)' : 'var(--uk-fg-muted)',
                  fontWeight: it.active ? 500 : 400,
                  textDecoration: 'none',
                  marginLeft: -2,
                }}>{it.label}</a>
              ))}
            </nav>
          </aside>

          {/* Document */}
          <article style={proseStyle}>
            <div style={{
              fontSize: 11, fontWeight: 600, letterSpacing: '0.1em',
              textTransform: 'uppercase', color: 'var(--uk-accent)',
              marginBottom: 12,
            }}>Legal</div>

            <h1 style={{
              fontSize: 44, lineHeight: 1.1, letterSpacing: '-0.028em',
              fontWeight: 600, marginBottom: 12,
            }}>Terms of Service</h1>

            <div style={{
              display: 'flex', gap: 14, alignItems: 'center',
              fontSize: 13, color: 'var(--uk-fg-subtle)',
              paddingBottom: 24, marginBottom: 32,
              borderBottom: '1px solid var(--uk-border)',
            }}>
              <span>Last updated <strong style={{ color: 'var(--uk-fg)', fontWeight: 500 }}>May 14, 2026</strong></span>
              <span style={{ width: 3, height: 3, borderRadius: 999, background: 'var(--uk-fg-faint)' }}/>
              <span>Version 2.3</span>
            </div>

            <p style={prosePara}>
              These Terms govern your use of Kytario, a task-management product operated by
              Kytario s.r.o. ("we", "us"). By creating an account or connecting an MCP
              client to your workspace, you agree to be bound by them. Please read the
              whole thing — it's not long.
            </p>

            <Callout>
              This is a template for long-form prose. The same styles cover Terms,
              Privacy, DPA, Acceptable Use, and Cookie pages — only the body content
              changes.
            </Callout>

            <h2 id="accounts" style={proseH2}>1 · Accounts &amp; access</h2>

            <p style={prosePara}>
              You need an account to use Kytario. You're responsible for keeping your
              credentials secure and for everything that happens under your account
              — including actions taken by agents you've authorised. We may suspend
              accounts that are inactive for more than 12 consecutive months.
            </p>

            <h3 style={proseH3}>Workspaces and roles</h3>

            <p style={prosePara}>
              Every account gets a personal workspace. You may create additional
              workspaces and invite other users to join them. Within each workspace,
              members hold one of the following roles:
            </p>

            <ul style={proseUl}>
              <li><strong>Owner</strong> — full administrative control, including billing and
                  workspace deletion. Each workspace has exactly one owner.</li>
              <li><strong>Admin</strong> — manage members, projects, tags, custom fields, and
                  workflows. Cannot transfer ownership or delete the workspace.</li>
              <li><strong>Member</strong> — create and edit tasks within projects they have
                  been added to.</li>
            </ul>

            <h2 id="agents" style={proseH2}>2 · Agents &amp; MCP clients</h2>

            <p style={prosePara}>
              You may connect one or more MCP-conforming clients (for example Claude
              Desktop, Cursor, or ChatGPT) to your account. Connecting a client follows
              this flow:
            </p>

            <ol style={proseOl}>
              <li>The client discovers Kytario's OAuth endpoints via RFC 9728 resource
                  metadata.</li>
              <li>The client registers itself dynamically and opens a browser window
                  requesting your consent.</li>
              <li>You review the requested scopes and approve or deny.</li>
              <li>On approval, the client receives a PKCE-pinned access token.</li>
            </ol>

            <p style={prosePara}>
              Actions taken via an MCP client are attributed to you in the activity
              log, with the client name preserved. You can revoke any client's access
              at any time from <em>Settings → Agents</em>.
            </p>

            <h2 id="data" style={proseH2}>3 · Your data</h2>

            <p style={prosePara}>
              You retain ownership of everything you put into Kytario: tasks, comments,
              attachments, custom fields, tags. We process this data only to provide
              the service and to comply with applicable law. We do <strong>not</strong> train
              machine-learning models on your content.
            </p>

            <h3 style={proseH3}>Export and deletion</h3>

            <p style={prosePara}>
              Workspace owners can request a full export at any time. The export
              contains:
            </p>

            <ul style={proseUl}>
              <li>A JSON archive of every project, task, comment, tag, and field value.</li>
              <li>The original binary of every file attachment, organised by task.</li>
              <li>A copy of the workspace's event log for the retention window of your plan.</li>
            </ul>

            <p style={prosePara}>
              Deleting your account removes all of the above from production storage
              within 30 days, and from encrypted backups within 90 days.
            </p>

            <h2 id="billing" style={proseH2}>4 · Billing</h2>

            <p style={prosePara}>
              The Free plan is, well, free. Paid plans are billed in advance — monthly
              or annually, per active member. Refunds are issued for the unused portion
              of any annual plan if you cancel within the first 30 days.
            </p>

            <h3 style={proseH3}>What counts as an "active member"</h3>

            <p style={prosePara}>
              A member is active in a billing period if they have signed in, made an
              API call, or had an action attributed to them by a connected MCP client
              during that period. Pending invitations and guest viewers are never
              counted.
            </p>

            <h2 id="liability" style={proseH2}>5 · Liability</h2>

            <p style={prosePara}>
              Kytario is provided "as is". To the maximum extent permitted by law, our
              total liability arising out of or in connection with these Terms is
              limited to the fees you paid us in the 12 months preceding the event
              giving rise to the claim. Nothing in these Terms limits liability that
              cannot be limited under applicable law.
            </p>

            <h2 id="contact" style={proseH2}>6 · Contact</h2>

            <p style={prosePara}>
              Questions about these Terms? Email <a href="mailto:info@kytario.com" style={proseLink}>info@kytario.com</a>.
              For data-protection inquiries, use <a href="mailto:dpo@kytario.com" style={proseLink}>dpo@kytario.com</a>.
            </p>

            <div style={{
              marginTop: 48, paddingTop: 24,
              borderTop: '1px solid var(--uk-border)',
              display: 'flex', gap: 12, alignItems: 'center',
              fontSize: 13, color: 'var(--uk-fg-subtle)',
            }}>
              <span>Kytario s.r.o.</span>
              <span style={{ width: 3, height: 3, borderRadius: 999, background: 'var(--uk-fg-faint)' }}/>
              <span>Karlovo náměstí 10, 120 00 Praha 2, Czech Republic</span>
              <span style={{ width: 3, height: 3, borderRadius: 999, background: 'var(--uk-fg-faint)' }}/>
              <span>IČO 12345678</span>
            </div>
          </article>
        </div>
      </section>
    </MarketingPage>
  );
}

// Prose style helpers (kept colocated so this page reads as a single template)
const prosePara = {
  marginBottom: 18,
  color: 'var(--uk-fg)',
  fontSize: 16,
  lineHeight: 1.7,
};
const proseH2 = {
  fontSize: 26,
  fontWeight: 600,
  letterSpacing: '-0.02em',
  lineHeight: 1.25,
  marginTop: 44,
  marginBottom: 14,
  color: 'var(--uk-fg)',
  scrollMarginTop: 80,
};
const proseH3 = {
  fontSize: 18,
  fontWeight: 600,
  letterSpacing: '-0.012em',
  lineHeight: 1.3,
  marginTop: 28,
  marginBottom: 10,
  color: 'var(--uk-fg)',
};
const proseUl = {
  margin: '0 0 18px',
  paddingLeft: 22,
  display: 'flex', flexDirection: 'column', gap: 8,
  fontSize: 16, lineHeight: 1.65,
  color: 'var(--uk-fg)',
};
const proseOl = { ...proseUl };
const proseLink = {
  color: 'var(--uk-accent)',
  textDecoration: 'underline',
  textDecorationColor: 'var(--uk-accent-soft)',
  textUnderlineOffset: 3,
};

function Callout({ children }) {
  return (
    <div style={{
      margin: '20px 0 26px',
      padding: '14px 18px',
      background: 'var(--uk-surface-2)',
      borderLeft: '3px solid var(--uk-accent)',
      borderRadius: '0 7px 7px 0',
      fontSize: 14.5, lineHeight: 1.6,
      color: 'var(--uk-fg-muted)',
    }}>
      {children}
    </div>
  );
}

window.PricingScreen  = PricingScreen;
window.ContactScreen  = ContactScreen;
window.TextPageScreen = TextPageScreen;
