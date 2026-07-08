import React from 'react';

/**
 * Racket Club — Tabs (underline style, controlled or uncontrolled)
 * items: [{ id, label }]
 */
export function Tabs({ items = [], value, defaultValue, onChange, style = {}, ...rest }) {
  const [internal, setInternal] = React.useState(defaultValue ?? (items[0] && items[0].id));
  const active = value !== undefined ? value : internal;
  const [hover, setHover] = React.useState(null);

  const select = (id) => {
    if (value === undefined) setInternal(id);
    onChange && onChange(id);
  };

  return (
    <div role="tablist" style={{ display: 'flex', gap: 'var(--space-6)', borderBottom: '1px solid var(--border-default)', ...style }} {...rest}>
      {items.map((it) => {
        const on = it.id === active;
        return (
          <button
            key={it.id} role="tab" aria-selected={on} type="button"
            onClick={() => select(it.id)}
            onMouseEnter={() => setHover(it.id)}
            onMouseLeave={() => setHover(null)}
            style={{
              appearance: 'none', border: 'none', background: 'transparent', cursor: 'pointer',
              fontFamily: 'var(--font-body)', fontSize: 'var(--text-base)',
              fontWeight: on ? 'var(--fw-semibold)' : 'var(--fw-medium)',
              letterSpacing: 'var(--tracking-wide)',
              color: on ? 'var(--text-primary)' : (hover === it.id ? 'var(--text-primary)' : 'var(--text-muted)'),
              padding: '0 0 12px', marginBottom: '-1px',
              borderBottom: `2px solid ${on ? 'var(--brand-accent)' : 'transparent'}`,
              transition: 'color var(--dur-fast) var(--ease-out)',
            }}
          >
            {it.label}
          </button>
        );
      })}
    </div>
  );
}
