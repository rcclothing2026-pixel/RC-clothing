/* Racket Club store — blocks: Products, Editorial, Collections, CartDrawer */
const RCB = window.RacketClubDesignSystem_3c2e87;

const RC_PRODUCTS = [
  { id: 'p1', name: 'The Terrace Polo', line: 'Piqué cotton · Ecru', price: 145, slot: 'rc-p1' },
  { id: 'p2', name: 'Crew Sweatshirt', line: 'Loopback · Navy', price: 165, slot: 'rc-p2' },
  { id: 'p3', name: 'Tailored Court Short', line: 'Cotton twill · Beige', price: 120, slot: 'rc-p3' },
  { id: 'p4', name: 'Ribbed Knit Cap', line: 'Merino · Forest', price: 65, slot: 'rc-p4' },
];

function SectionHead({ eyebrow, title, children, align = 'left', tone = 'primary' }) {
  const { Eyebrow } = RCB;
  const color = tone === 'inverse' ? 'var(--cream-500)' : 'var(--text-primary)';
  return (
    <div style={{ marginBottom: 'var(--space-12)', maxWidth: 620, textAlign: align, marginLeft: align === 'center' ? 'auto' : 0, marginRight: align === 'center' ? 'auto' : 0 }}>
      <Eyebrow tone={tone === 'inverse' ? 'inverse' : 'accent'}>{eyebrow}</Eyebrow>
      <h2 style={{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-semibold)', fontSize: 'var(--text-4xl)', lineHeight: 1.1, letterSpacing: 'var(--tracking-tight)', color, margin: 'var(--space-4) 0 var(--space-3)' }}>{title}</h2>
      {children && <p style={{ fontFamily: 'var(--font-body)', fontSize: 'var(--text-lg)', color: tone === 'inverse' ? 'color-mix(in srgb, var(--cream-500) 82%, transparent)' : 'var(--text-body)', lineHeight: 'var(--leading-relaxed)', margin: 0 }}>{children}</p>}
    </div>
  );
}

function ProductCard({ p, onAdd }) {
  const { Button } = RCB;
  const [hover, setHover] = React.useState(false);
  return (
    <div onMouseEnter={() => setHover(true)} onMouseLeave={() => setHover(false)} style={{ display: 'flex', flexDirection: 'column' }}>
      <div style={{ position: 'relative', aspectRatio: '4 / 5', background: 'var(--surface-page-alt)', borderRadius: 'var(--radius-sm)', overflow: 'hidden' }}>
        <image-slot id={p.slot} shape="rect" fit="cover" placeholder="Drop product image"></image-slot>
      </div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', marginTop: 'var(--space-4)', gap: 'var(--space-3)' }}>
        <div>
          <div style={{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-medium)', fontSize: 'var(--text-lg)', color: 'var(--text-primary)' }}>{p.name}</div>
          <div style={{ fontFamily: 'var(--font-body)', fontSize: 'var(--text-sm)', color: 'var(--text-muted)', marginTop: 2 }}>{p.line}</div>
        </div>
        <div style={{ fontFamily: 'var(--font-body)', fontSize: 'var(--text-base)', color: 'var(--text-primary)', whiteSpace: 'nowrap' }}>€{p.price}</div>
      </div>
      <div style={{ marginTop: 'var(--space-3)', height: 40, overflow: 'hidden' }}>
        <div style={{ transform: hover ? 'translateY(0)' : 'translateY(6px)', opacity: hover ? 1 : 0, transition: 'all var(--dur-base) var(--ease-out)' }}>
          <Button variant="outline" size="sm" fullWidth onClick={() => onAdd(p)}>Add to bag</Button>
        </div>
      </div>
    </div>
  );
}

function Products({ onAdd }) {
  return (
    <section style={{ background: 'var(--surface-page)', padding: 'var(--section-y) 0' }}>
      <div style={{ maxWidth: 'var(--container-xl)', margin: '0 auto', padding: '0 var(--space-8)' }}>
        <SectionHead eyebrow="The Essentials" title="Quietly made, endlessly worn">
          A tight edit of well-cut pieces in earthy, considered tones — built to outlast the season.
        </SectionHead>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 'var(--space-6)' }}>
          {RC_PRODUCTS.map((p) => <ProductCard key={p.id} p={p} onAdd={onAdd} />)}
        </div>
      </div>
    </section>
  );
}

function Editorial() {
  const { Eyebrow, Button } = RCB;
  return (
    <section style={{ background: 'var(--surface-page-alt)', padding: 'var(--section-y) 0' }}>
      <div style={{ maxWidth: 'var(--container-xl)', margin: '0 auto', padding: '0 var(--space-8)', display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 'var(--space-16)', alignItems: 'center' }}>
        <div style={{ aspectRatio: '5 / 4', borderRadius: 'var(--radius-sm)', overflow: 'hidden', boxShadow: 'var(--shadow-md)' }}>
          <image-slot id="rc-editorial" shape="rect" fit="cover" placeholder="Drop an editorial image"></image-slot>
        </div>
        <div>
          <Eyebrow>The Journal</Eyebrow>
          <h2 style={{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-semibold)', fontSize: 'var(--text-4xl)', lineHeight: 1.1, letterSpacing: 'var(--tracking-tight)', color: 'var(--text-primary)', margin: 'var(--space-4) 0 var(--space-4)' }}>
            Designed for the life<br />after the match
          </h2>
          <p style={{ fontFamily: 'var(--font-body)', fontSize: 'var(--text-lg)', color: 'var(--text-body)', lineHeight: 'var(--leading-relaxed)', margin: '0 0 var(--space-6)' }}>
            The game is best remembered over a slow lunch. Our pieces are cut for those hours — the terrace, the boardwalk, the golden light before dusk. Quiet luxury, worn without a word.
          </p>
          <Button variant="ghost" rightIcon={<Icon n="arrow-right" s={17} />}>Read the story</Button>
        </div>
      </div>
    </section>
  );
}

const RC_COLLECTIONS = [
  { id: 'c1', name: 'Coastal', line: 'Linen & cotton', slot: 'rc-c1' },
  { id: 'c2', name: 'The Terrace', line: 'Piqué & knit', slot: 'rc-c2' },
  { id: 'c3', name: 'Sunset Court', line: 'Twill & merino', slot: 'rc-c3' },
];

function Collections() {
  return (
    <section style={{ background: 'var(--surface-page)', padding: 'var(--section-y) 0' }}>
      <div style={{ maxWidth: 'var(--container-xl)', margin: '0 auto', padding: '0 var(--space-8)' }}>
        <SectionHead eyebrow="Collections" title="Three moods, one wardrobe" align="center" />
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 'var(--space-6)' }}>
          {RC_COLLECTIONS.map((c) => (
            <a key={c.id} href="#" style={{ position: 'relative', aspectRatio: '3 / 4', borderRadius: 'var(--radius-sm)', overflow: 'hidden', display: 'block', textDecoration: 'none' }}>
              <image-slot id={c.slot} shape="rect" fit="cover" placeholder={'Drop ' + c.name + ' image'}></image-slot>
              <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(to top, color-mix(in srgb, var(--navy-900) 78%, transparent), transparent 62%)', pointerEvents: 'none' }}></div>
              <div style={{ position: 'absolute', left: 'var(--space-6)', bottom: 'var(--space-6)', color: 'var(--cream-500)', pointerEvents: 'none' }}>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-semibold)', fontSize: 'var(--text-2xl)', letterSpacing: 'var(--tracking-tight)' }}>{c.name}</div>
                <div style={{ fontFamily: 'var(--font-body)', fontSize: 'var(--text-sm)', color: 'color-mix(in srgb, var(--cream-500) 78%, transparent)', marginTop: 2 }}>{c.line}</div>
              </div>
            </a>
          ))}
        </div>
      </div>
    </section>
  );
}

function CartDrawer({ open, items, onClose, onRemove }) {
  const { Button } = RCB;
  const subtotal = items.reduce((s, it) => s + it.price * it.qty, 0);
  return (
    <div aria-hidden={!open} style={{ position: 'fixed', inset: 0, zIndex: 1000, pointerEvents: open ? 'auto' : 'none' }}>
      <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'color-mix(in srgb, var(--navy-900) 50%, transparent)', opacity: open ? 1 : 0, transition: 'opacity var(--dur-base) var(--ease-out)' }}></div>
      <aside style={{ position: 'absolute', top: 0, right: 0, height: '100%', width: 400, maxWidth: '90vw', background: 'var(--surface-page)', boxShadow: 'var(--shadow-lg)', transform: open ? 'translateX(0)' : 'translateX(100%)', transition: 'transform var(--dur-slow) var(--ease-out)', display: 'flex', flexDirection: 'column' }}>
        <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', padding: 'var(--space-6)', borderBottom: '1px solid var(--border-subtle)' }}>
          <span style={{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-semibold)', fontSize: 'var(--text-2xl)', color: 'var(--text-primary)' }}>Your bag</span>
          <button onClick={onClose} aria-label="Close" style={{ border: 'none', background: 'transparent', cursor: 'pointer', color: 'var(--text-muted)', display: 'inline-flex' }}><Icon n="x" s={22} /></button>
        </div>
        <div style={{ flex: 1, overflowY: 'auto', padding: 'var(--space-6)', display: 'flex', flexDirection: 'column', gap: 'var(--space-5)' }}>
          {items.length === 0 && <p style={{ fontFamily: 'var(--font-body)', color: 'var(--text-muted)' }}>Your bag is empty.</p>}
          {items.map((it) => (
            <div key={it.id} style={{ display: 'flex', gap: 'var(--space-4)', alignItems: 'flex-start' }}>
              <div style={{ width: 64, height: 80, background: 'var(--surface-page-alt)', borderRadius: 'var(--radius-xs)', flex: '0 0 auto', overflow: 'hidden' }}>
                <image-slot id={'bag-' + it.slot} shape="rect" fit="cover" placeholder=""></image-slot>
              </div>
              <div style={{ flex: 1 }}>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-medium)', color: 'var(--text-primary)' }}>{it.name}</div>
                <div style={{ fontFamily: 'var(--font-body)', fontSize: 'var(--text-sm)', color: 'var(--text-muted)' }}>{it.line} · Qty {it.qty}</div>
                <button onClick={() => onRemove(it.id)} style={{ border: 'none', background: 'transparent', cursor: 'pointer', color: 'var(--text-link)', fontSize: 'var(--text-sm)', padding: '4px 0 0', fontFamily: 'var(--font-body)' }}>Remove</button>
              </div>
              <div style={{ fontFamily: 'var(--font-body)', color: 'var(--text-primary)' }}>€{it.price * it.qty}</div>
            </div>
          ))}
        </div>
        <div style={{ padding: 'var(--space-6)', borderTop: '1px solid var(--border-subtle)' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 'var(--space-4)', fontFamily: 'var(--font-body)' }}>
            <span style={{ color: 'var(--text-muted)' }}>Subtotal</span>
            <span style={{ fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-semibold)', fontSize: 'var(--text-xl)', color: 'var(--text-primary)' }}>€{subtotal}</span>
          </div>
          <Button variant="accent" size="lg" fullWidth disabled={items.length === 0}>Checkout</Button>
        </div>
      </aside>
    </div>
  );
}

Object.assign(window, { Products, Editorial, Collections, CartDrawer, SectionHead });
