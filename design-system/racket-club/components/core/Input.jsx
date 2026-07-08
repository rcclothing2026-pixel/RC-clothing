import React from 'react';

/**
 * Racket Club — Input (text field with label / hint / error)
 */
export function Input({
  label,
  hint,
  error,
  leftIcon = null,
  id,
  disabled = false,
  style = {},
  containerStyle = {},
  ...rest
}) {
  const [focus, setFocus] = React.useState(false);
  const inputId = id || React.useId();
  const invalid = Boolean(error);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: '6px', ...containerStyle }}>
      {label && (
        <label htmlFor={inputId} style={{
          fontFamily: 'var(--font-body)', fontSize: 'var(--text-sm)', fontWeight: 'var(--fw-medium)',
          color: 'var(--text-primary)', letterSpacing: 'var(--tracking-wide)',
        }}>{label}</label>
      )}
      <div style={{
        display: 'flex', alignItems: 'center', gap: '8px',
        background: 'var(--surface-card)',
        border: `1px solid ${invalid ? 'var(--clay-600)' : focus ? 'var(--brand-primary)' : 'var(--border-default)'}`,
        borderRadius: 'var(--radius-sm)',
        padding: '0 12px',
        boxShadow: focus ? 'var(--focus-ring)' : 'none',
        transition: 'border-color var(--dur-fast) var(--ease-out), box-shadow var(--dur-fast) var(--ease-out)',
        opacity: disabled ? 0.55 : 1,
      }}>
        {leftIcon && <span style={{ display: 'inline-flex', color: 'var(--text-muted)' }}>{leftIcon}</span>}
        <input
          id={inputId}
          disabled={disabled}
          onFocus={() => setFocus(true)}
          onBlur={() => setFocus(false)}
          aria-invalid={invalid}
          style={{
            flex: 1, border: 'none', outline: 'none', background: 'transparent',
            fontFamily: 'var(--font-body)', fontSize: 'var(--text-base)', color: 'var(--text-primary)',
            padding: '10px 0', minWidth: 0, ...style,
          }}
          {...rest}
        />
      </div>
      {(hint || error) && (
        <span style={{ fontSize: 'var(--text-sm)', color: invalid ? 'var(--clay-600)' : 'var(--text-muted)' }}>
          {error || hint}
        </span>
      )}
    </div>
  );
}
