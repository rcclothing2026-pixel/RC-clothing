import React from 'react';

/**
 * Racket Club — Tag (pill category chip, optionally removable / selectable)
 */
export function Tag({ children, selected = false, onRemove, style = {}, ...rest }) {
  const [hover, setHover] = React.useState(false);
  const interactive = Boolean(rest.onClick);
  return (
    <span
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      style={{
        display: 'inline-flex', alignItems: 'center', gap: '6px',
        fontFamily: 'var(--font-body)', fontSize: 'var(--text-sm)', fontWeight: 'var(--fw-medium)',
        color: selected ? 'var(--cream-500)' : 'var(--text-primary)',
        background: selected ? 'var(--brand-primary)' : (hover && interactive ? 'var(--cream-600)' : 'var(--surface-page-alt)'),
        border: `1px solid ${selected ? 'var(--brand-primary)' : 'var(--border-subtle)'}`,
        padding: '5px 12px', borderRadius: 'var(--radius-pill)',
        cursor: interactive ? 'pointer' : 'default', lineHeight: 1.3,
        transition: 'background var(--dur-fast) var(--ease-out)', ...style,
      }}
      {...rest}
    >
      {children}
      {onRemove && (
        <button
          type="button" aria-label="Remove"
          onClick={(e) => { e.stopPropagation(); onRemove(e); }}
          style={{ border: 'none', background: 'transparent', cursor: 'pointer', color: 'inherit', padding: 0, lineHeight: 0, fontSize: '15px', opacity: 0.7 }}
        >×</button>
      )}
    </span>
  );
}
