import React from 'react';

/**
 * Racket Club — Stat (display figure + label)
 * Big Mansory number with an uppercase tracked label — used across
 * club marketing (members, courts, years, championships).
 */
export function Stat({ value, label, caption, align = 'left', tone = 'primary', style = {}, ...rest }) {
  const color = tone === 'accent' ? 'var(--brand-accent)' : tone === 'inverse' ? 'var(--cream-500)' : 'var(--text-primary)';
  const labelColor = tone === 'inverse' ? 'color-mix(in srgb, var(--cream-500) 72%, transparent)' : 'var(--text-muted)';
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '4px', textAlign: align, alignItems: align === 'center' ? 'center' : 'flex-start', ...style }} {...rest}>
      <span style={{
        fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-semibold)',
        fontSize: 'var(--text-5xl)', lineHeight: 1, color, letterSpacing: 'var(--tracking-tight)',
      }}>{value}</span>
      <span style={{
        fontFamily: 'var(--font-body)', fontSize: 'var(--text-sm)', fontWeight: 'var(--fw-semibold)',
        letterSpacing: 'var(--tracking-widest)', textTransform: 'uppercase', color: labelColor,
      }}>{label}</span>
      {caption && <span style={{ fontSize: 'var(--text-sm)', color: labelColor, letterSpacing: 0, textTransform: 'none', fontWeight: 'var(--fw-regular)' }}>{caption}</span>}
    </div>
  );
}
