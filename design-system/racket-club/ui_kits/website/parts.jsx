/* Racket Club store — shared parts: Icon, Announcement, Header, Hero, Footer */
const RC = window.RacketClubDesignSystem_3c2e87;

function Icon({ n, s = 18, color = 'currentColor' }) {
  const ref = React.useRef(null);
  React.useEffect(() => {
    if (ref.current && window.lucide) window.lucide.createIcons({ attrs: { width: s, height: s, stroke: color, 'stroke-width': 1.6 }, nameAttr: 'data-lucide', root: ref.current });
  });
  return <span ref={ref} style={{ display: 'inline-flex' }}><i data-lucide={n}></i></span>;
}

function Announcement() {
  return (
    <div style={{ background: 'var(--navy-700)', color: 'var(--cream-500)', textAlign: 'center', fontSize: 'var(--text-xs)', letterSpacing: 'var(--tracking-widest)', textTransform: 'uppercase', padding: '9px 16px', fontWeight: 'var(--fw-medium)' }}>
      Complimentary shipping on orders over €200 · Limited autumn drop now live
    </div>
  );
}

function Header({ bagCount, onOpenBag }) {
  const { Logo } = RC;
  const links = ['Shop', 'Collections', 'The Club', 'Journal'];
  const [active, setActive] = React.useState('Shop');
  return (
    <header style={{ position: 'sticky', top: 0, zIndex: 50, background: 'color-mix(in srgb, var(--cream-500) 90%, transparent)', backdropFilter: 'blur(10px)', borderBottom: '1px solid var(--border-subtle)' }}>
      <div style={{ maxWidth: 'var(--container-xl)', margin: '0 auto', padding: '0 var(--space-8)', height: 76, display: 'grid', gridTemplateColumns: '1fr auto 1fr', alignItems: 'center' }}>
        <nav style={{ display: 'flex', gap: 'var(--space-8)' }}>
          {links.map((l) => (
            <a key={l} href="#" onClick={(e) => { e.preventDefault(); setActive(l); }}
              style={{ textDecoration: 'none', fontSize: 'var(--text-sm)', fontWeight: active === l ? 'var(--fw-semibold)' : 'var(--fw-medium)', letterSpacing: 'var(--tracking-wider)', textTransform: 'uppercase', color: active === l ? 'var(--text-primary)' : 'var(--text-muted)' }}>{l}</a>
          ))}
        </nav>
        <Logo layout="horizontal" tone="navy" size={28} />
        <div style={{ display: 'flex', alignItems: 'center', gap: 'var(--space-5)', justifySelf: 'end', color: 'var(--text-primary)' }}>
          <a href="#" aria-label="Search" style={{ color: 'inherit' }}><Icon n="search" s={19} /></a>
          <a href="#" aria-label="Account" style={{ color: 'inherit' }}><Icon n="user" s={19} /></a>
          <button onClick={onOpenBag} aria-label="Bag" style={{ position: 'relative', border: 'none', background: 'transparent', cursor: 'pointer', color: 'inherit', display: 'inline-flex', padding: 0 }}>
            <Icon n="shopping-bag" s={19} />
            {bagCount > 0 && <span style={{ position: 'absolute', top: -8, right: -10, minWidth: 17, height: 17, padding: '0 4px', borderRadius: 'var(--radius-pill)', background: 'var(--clay-600)', color: 'var(--cream-500)', fontSize: 10, fontWeight: 700, display: 'flex', alignItems: 'center', justifyContent: 'center' }}>{bagCount}</span>}
          </button>
        </div>
      </div>
    </header>
  );
}

function Hero() {
  const { Button, Eyebrow } = RC;
  return (
    <section style={{ background: 'var(--gradient-primary)', color: 'var(--cream-500)', position: 'relative', overflow: 'hidden' }}>
      <img src="../../assets/logo/mark-cream.svg" alt="" aria-hidden="true" style={{ position: 'absolute', left: -90, bottom: -80, width: 460, height: 460, opacity: 0.05 }} />
      <div style={{ maxWidth: 'var(--container-xl)', margin: '0 auto', padding: 'var(--space-24) var(--space-8)', display: 'grid', gridTemplateColumns: '1.05fr 0.95fr', gap: 'var(--space-16)', alignItems: 'center' }}>
        <div>
          <Eyebrow tone="inverse">The Art of Leisure</Eyebrow>
          <h1 style={{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-bold)', fontSize: 'var(--text-6xl)', lineHeight: 1.05, letterSpacing: 'var(--tracking-tight)', textTransform: 'uppercase', margin: 'var(--space-5) 0 0' }}>
            Style for your<br />leisure moments
          </h1>
          <p style={{ fontFamily: 'var(--font-script)', fontSize: '2.75rem', color: 'var(--sand-400)', margin: 'var(--space-3) 0 var(--space-6)', lineHeight: 1 }}>Legends &amp; Legacy</p>
          <p style={{ fontSize: 'var(--text-lg)', lineHeight: 'var(--leading-relaxed)', color: 'color-mix(in srgb, var(--cream-500) 82%, transparent)', maxWidth: 460, margin: 0 }}>
            Considered essentials for the hours off the court — the long brunch, the coastal walk, the evening that follows the match.
          </p>
          <div style={{ display: 'flex', gap: 'var(--space-4)', marginTop: 'var(--space-8)' }}>
            <Button variant="accent" size="lg">Explore the collection</Button>
            <Button variant="outline" size="lg" style={{ color: 'var(--cream-500)', borderColor: 'var(--border-on-dark)' }}>View the lookbook</Button>
          </div>
        </div>
        <div style={{ position: 'relative', aspectRatio: '4 / 5', borderRadius: 'var(--radius-sm)', overflow: 'hidden', boxShadow: 'var(--shadow-lg)' }}>
          <image-slot id="rc-hero" shape="rect" fit="cover" placeholder="Drop a lookbook image"></image-slot>
        </div>
      </div>
    </section>
  );
}

function Footer() {
  const { Logo, Input, Button } = RC;
  const cols = [
    ['Shop', ['Polos', 'Knitwear', 'Trousers', 'Accessories']],
    ['The Club', ['Our story', 'Sustainability', 'Journal', 'Stockists']],
    ['Care', ['Shipping', 'Returns', 'Garment care', 'Contact']],
  ];
  return (
    <footer style={{ background: 'var(--navy-700)', color: 'var(--cream-500)' }}>
      <div style={{ maxWidth: 'var(--container-xl)', margin: '0 auto', padding: 'var(--space-20) var(--space-8) var(--space-10)', display: 'grid', gridTemplateColumns: '1.6fr 1fr 1fr 1fr', gap: 'var(--space-10)' }}>
        <div>
          <Logo layout="horizontal" tone="cream" size={28} />
          <p style={{ fontFamily: 'var(--font-script)', fontSize: '1.9rem', color: 'var(--sand-400)', margin: 'var(--space-4) 0 0' }}>Legends &amp; Legacy</p>
        </div>
        {cols.map(([h, items]) => (
          <div key={h}>
            <div style={{ fontSize: 'var(--text-xs)', fontWeight: 'var(--fw-semibold)', letterSpacing: 'var(--tracking-widest)', textTransform: 'uppercase', color: 'color-mix(in srgb, var(--cream-500) 58%, transparent)', marginBottom: 'var(--space-4)' }}>{h}</div>
            <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 'var(--space-3)' }}>
              {items.map((it) => <li key={it}><a href="#" style={{ textDecoration: 'none', color: 'color-mix(in srgb, var(--cream-500) 80%, transparent)', fontSize: 'var(--text-sm)' }}>{it}</a></li>)}
            </ul>
          </div>
        ))}
      </div>
      <div style={{ maxWidth: 'var(--container-xl)', margin: '0 auto', padding: 'var(--space-6) var(--space-8)', borderTop: '1px solid var(--border-on-dark)', display: 'flex', justifyContent: 'space-between', fontSize: 'var(--text-sm)', color: 'color-mix(in srgb, var(--cream-500) 58%, transparent)' }}>
        <span>© 2026 Racket Club</span>
        <span>Privacy · Terms</span>
      </div>
    </footer>
  );
}

Object.assign(window, { Icon, Announcement, Header, Hero, Footer });
