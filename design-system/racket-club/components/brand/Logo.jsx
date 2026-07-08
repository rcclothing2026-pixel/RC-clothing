import React from 'react';

/** The Racket Club mark, drawn inline (exact CI geometry). */
function Mark({ size = 40, square = 'var(--brand-primary)', detail = 'var(--cream-500)' }) {
  return (
    <svg width={size} height={size} viewBox="1360 1360 280 280" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style={{ display: 'block', flex: '0 0 auto' }}>
      <rect fill={square} x="1360" y="1360" width="280" height="280" />
      <rect fill={detail} x="1472" y="1500" width="56" height="141.13" />
      <rect fill={detail} x="1472.07" y="1386.88" width="56" height="280" transform="translate(-26.8095 3026.9558) rotate(-90)" />
      <circle fill={detail} cx="1567.41" cy="1446.49" r="14.38" />
    </svg>
  );
}

const RC_LOGO_COLORS = {
  navy:  { square: 'var(--navy-600)',  detail: 'var(--cream-500)', word: 'var(--navy-600)' },
  cream: { square: 'var(--cream-500)', detail: 'var(--navy-600)',  word: 'var(--cream-500)' },
  green: { square: 'var(--green-600)', detail: 'var(--cream-500)', word: 'var(--green-600)' },
};

/**
 * Racket Club — Logo
 * layout: 'horizontal' | 'stacked' | 'mark' | 'wordmark'
 * tone:   'navy' | 'cream' | 'green'
 */
export function Logo({ layout = 'horizontal', tone = 'navy', size = 40, showSlogan = false, style = {}, ...rest }) {
  const c = RC_LOGO_COLORS[tone] || RC_LOGO_COLORS.navy;
  const wordSize = size * 0.62;

  const word = (
    <span style={{
      fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-semibold)',
      fontSize: wordSize, lineHeight: 0.9, letterSpacing: '0.02em',
      color: c.word, whiteSpace: 'nowrap',
    }}>RACKET CLUB</span>
  );

  const slogan = showSlogan && (
    <span style={{ fontFamily: 'var(--font-script)', fontSize: wordSize * 0.72, color: c.word === 'var(--cream-500)' ? 'var(--cream-500)' : 'var(--clay-600)', lineHeight: 1 }}>
      Legends &amp; Legacy
    </span>
  );

  if (layout === 'mark') return <div style={style} {...rest}><Mark size={size} square={c.square} detail={c.detail} /></div>;
  if (layout === 'wordmark') return <div style={{ display: 'flex', flexDirection: 'column', gap: size * 0.12, ...style }} {...rest}>{word}{slogan}</div>;

  if (layout === 'stacked') {
    return (
      <div style={{ display: 'inline-flex', flexDirection: 'column', alignItems: 'center', gap: size * 0.28, ...style }} {...rest}>
        <Mark size={size} square={c.square} detail={c.detail} />
        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: size * 0.1 }}>{word}{slogan}</div>
      </div>
    );
  }

  return (
    <div style={{ display: 'inline-flex', alignItems: 'center', gap: size * 0.4, ...style }} {...rest}>
      <Mark size={size} square={c.square} detail={c.detail} />
      <div style={{ display: 'flex', flexDirection: 'column', gap: size * 0.08 }}>{word}{slogan}</div>
    </div>
  );
}
