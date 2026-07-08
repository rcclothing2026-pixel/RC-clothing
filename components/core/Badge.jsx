import React from 'react';

const RC_TONES = {
  navy:  { solid: ['var(--navy-600)', 'var(--cream-500)'], subtle: ['var(--navy-50)', 'var(--navy-700)'] },
  green: { solid: ['var(--green-600)', 'var(--cream-500)'], subtle: ['var(--green-50)', 'var(--green-700)'] },
  clay:  { solid: ['var(--clay-600)', 'var(--cream-500)'], subtle: ['var(--clay-50)', 'var(--clay-700)'] },
  brown: { solid: ['var(--brown-600)', 'var(--cream-500)'], subtle: ['var(--brown-50)', 'var(--brown-700)'] },
  sand:  { solid: ['var(--sand-500)', 'var(--navy-700)'], subtle: ['var(--sand-100)', 'var(--sand-800)'] },
};

/**
 * Racket Club — Badge (compact status / label)
 */
export function Badge({ children, tone = 'navy', variant = 'subtle', dot = false, style = {}, ...rest }) {
  const t = RC_TONES[tone] || RC_TONES.navy;
  const [bg, fg] = t[variant] || t.subtle;
  return (
    <span style={{
      display: 'inline-flex', alignItems: 'center', gap: '6px',
      fontFamily: 'var(--font-body)', fontSize: 'var(--text-xs)', fontWeight: 'var(--fw-semibold)',
      letterSpacing: 'var(--tracking-wider)', textTransform: 'uppercase',
      color: fg, background: bg, padding: '3px 9px', borderRadius: 'var(--radius-xs)',
      lineHeight: 1.4, ...style,
    }} {...rest}>
      {dot && <span style={{ width: 6, height: 6, borderRadius: 'var(--radius-pill)', background: fg }} />}
      {children}
    </span>
  );
}
