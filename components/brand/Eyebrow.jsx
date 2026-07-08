import React from 'react';

/**
 * Racket Club — Eyebrow (overline label)
 * Small uppercase tracked label that sits above headings.
 */
export function Eyebrow({ children, tone = 'accent', rule = true, style = {}, ...rest }) {
  const color = tone === 'accent' ? 'var(--brand-accent)' : tone === 'muted' ? 'var(--text-muted)' : tone === 'inverse' ? 'var(--cream-500)' : 'var(--brand-primary)';
  return (
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 'var(--space-3)', ...style }} {...rest}>
      {rule && <span style={{ width: 28, height: 2, background: color, display: 'inline-block' }} />}
      <span style={{
        fontFamily: 'var(--font-body)', fontSize: 'var(--text-sm)', fontWeight: 'var(--fw-semibold)',
        letterSpacing: 'var(--tracking-widest)', textTransform: 'uppercase', color,
      }}>{children}</span>
    </span>
  );
}
