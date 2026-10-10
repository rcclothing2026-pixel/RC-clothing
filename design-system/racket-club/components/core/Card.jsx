import React from 'react';

const RC_CARD_PAD = { sm: 'var(--space-4)', md: 'var(--space-6)', lg: 'var(--space-8)' };

/**
 * Racket Club — Card (surface container)
 * variant: default (white) · sunken (cream) · inverse (navy) · court (green)
 */
export function Card({
  children,
  variant = 'default',
  padding = 'md',
  interactive = false,
  style = {},
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const variants = {
    default: { background: 'var(--surface-card)', color: 'var(--text-primary)', border: '1px solid var(--border-subtle)', boxShadow: 'var(--shadow-sm)' },
    sunken:  { background: 'var(--surface-page-alt)', color: 'var(--text-primary)', border: '1px solid var(--border-subtle)', boxShadow: 'none' },
    inverse: { background: 'var(--surface-inverse)', color: 'var(--text-inverse)', border: '1px solid var(--border-on-dark)', boxShadow: 'var(--shadow-md)' },
    court:   { background: 'var(--surface-inverse-2)', color: 'var(--cream-500)', border: '1px solid transparent', boxShadow: 'var(--shadow-md)' },
  };
  const v = variants[variant] || variants.default;
  return (
    <div
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      style={{
        borderRadius: 'var(--radius-sm)',
        padding: RC_CARD_PAD[padding] || RC_CARD_PAD.md,
        transition: 'transform var(--dur-base) var(--ease-out), box-shadow var(--dur-base) var(--ease-out)',
        transform: interactive && hover ? 'translateY(-2px)' : 'none',
        boxShadow: interactive && hover ? 'var(--shadow-lg)' : v.boxShadow,
        cursor: interactive ? 'pointer' : 'default',
        ...v, ...style,
      }}
      {...rest}
    >
      {children}
    </div>
  );
}
