import React from 'react';

const RC_IB_SIZES = { sm: 32, md: 40, lg: 48 };

/**
 * Racket Club — IconButton (square, icon-only action)
 */
export function IconButton({
  children,
  label,
  variant = 'ghost',
  size = 'md',
  disabled = false,
  style = {},
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const dim = RC_IB_SIZES[size] || RC_IB_SIZES.md;

  const variants = {
    ghost: { base: { background: 'transparent', color: 'var(--brand-primary)' }, hover: { background: 'color-mix(in srgb, var(--navy-600) 8%, transparent)' } },
    solid: { base: { background: 'var(--brand-primary)', color: 'var(--text-inverse)' }, hover: { background: 'var(--brand-primary-hover)' } },
    outline: { base: { background: 'transparent', color: 'var(--brand-primary)', boxShadow: 'inset 0 0 0 1px var(--border-default)' }, hover: { background: 'color-mix(in srgb, var(--navy-600) 6%, transparent)' } },
  };
  const v = variants[variant] || variants.ghost;

  return (
    <button
      type="button"
      aria-label={label}
      title={label}
      disabled={disabled}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      style={{
        width: dim, height: dim,
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        borderRadius: 'var(--radius-sm)', border: 'none',
        cursor: disabled ? 'not-allowed' : 'pointer', opacity: disabled ? 0.45 : 1,
        transition: 'background var(--dur-fast) var(--ease-out)',
        ...v.base, ...(hover && !disabled ? v.hover : null), ...style,
      }}
      {...rest}
    >
      {children}
    </button>
  );
}
