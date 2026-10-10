import React from 'react';

/**
 * Racket Club — Button
 * Elegant, sharp-cornered buttons in the heritage palette.
 */
const RC_BTN_SIZES = {
  sm: { fontSize: 'var(--text-sm)', padding: '7px 14px', gap: '6px' },
  md: { fontSize: 'var(--text-base)', padding: '10px 20px', gap: '8px' },
  lg: { fontSize: 'var(--text-lg)', padding: '14px 28px', gap: '10px' },
};

const RC_BTN_VARIANTS = {
  primary: {
    base: { background: 'var(--brand-primary)', color: 'var(--text-inverse)', border: '1px solid var(--brand-primary)' },
    hover: { background: 'var(--brand-primary-hover)', borderColor: 'var(--brand-primary-hover)' },
  },
  secondary: {
    base: { background: 'var(--brand-secondary)', color: 'var(--cream-500)', border: '1px solid var(--brand-secondary)' },
    hover: { background: 'var(--brand-secondary-hover)', borderColor: 'var(--brand-secondary-hover)' },
  },
  gradient: {
    base: { background: 'var(--gradient-primary)', color: 'var(--cream-500)', border: 'none' },
    hover: { filter: 'brightness(1.08)' },
  },
  accent: {
    base: { background: 'var(--gradient-accent)', color: 'var(--text-on-accent)', border: 'none' },
    hover: { filter: 'brightness(0.93)' },
  },
  outline: {
    base: { background: 'transparent', color: 'var(--brand-primary)', border: '1px solid var(--border-default)' },
    hover: { background: 'color-mix(in srgb, var(--navy-600) 6%, transparent)', borderColor: 'var(--border-strong)' },
  },
  ghost: {
    base: { background: 'transparent', color: 'var(--brand-primary)', border: '1px solid transparent' },
    hover: { background: 'color-mix(in srgb, var(--navy-600) 8%, transparent)' },
  },
};

export function Button({
  children,
  variant = 'primary',
  size = 'md',
  disabled = false,
  fullWidth = false,
  leftIcon = null,
  rightIcon = null,
  type = 'button',
  style = {},
  ...rest
}) {
  const [hover, setHover] = React.useState(false);
  const v = RC_BTN_VARIANTS[variant] || RC_BTN_VARIANTS.primary;
  const s = RC_BTN_SIZES[size] || RC_BTN_SIZES.md;

  const composed = {
    display: 'inline-flex',
    alignItems: 'center',
    justifyContent: 'center',
    gap: s.gap,
    fontFamily: 'var(--font-body)',
    fontWeight: 'var(--fw-semibold)',
    fontSize: s.fontSize,
    lineHeight: 1,
    letterSpacing: 'var(--tracking-wide)',
    padding: s.padding,
    width: fullWidth ? '100%' : 'auto',
    borderRadius: 'var(--radius-sm)',
    cursor: disabled ? 'not-allowed' : 'pointer',
    opacity: disabled ? 0.45 : 1,
    transition: 'background var(--dur-fast) var(--ease-out), border-color var(--dur-fast) var(--ease-out), transform var(--dur-fast) var(--ease-out)',
    transform: hover && !disabled ? 'translateY(-1px)' : 'translateY(0)',
    ...v.base,
    ...(hover && !disabled ? v.hover : null),
    ...style,
  };

  return (
    <button
      type={type}
      disabled={disabled}
      style={composed}
      onMouseEnter={() => setHover(true)}
      onMouseLeave={() => setHover(false)}
      {...rest}
    >
      {leftIcon}
      {children}
      {rightIcon}
    </button>
  );
}
