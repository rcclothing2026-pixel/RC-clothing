import React from 'react';

/**
 * Racket Club — Dialog (centered modal with scrim)
 */
export function Dialog({ open, onClose, title, children, footer, width = 480, style = {}, ...rest }) {
  React.useEffect(() => {
    if (!open) return;
    const onKey = (e) => { if (e.key === 'Escape') onClose && onClose(); };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div
      onClick={onClose}
      style={{
        position: 'fixed', inset: 0, zIndex: 1000,
        background: 'color-mix(in srgb, var(--navy-900) 55%, transparent)',
        display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 'var(--space-6)',
      }}
    >
      <div
        role="dialog" aria-modal="true" aria-label={title}
        onClick={(e) => e.stopPropagation()}
        style={{
          width: '100%', maxWidth: width, background: 'var(--surface-card)',
          borderRadius: 'var(--radius-sm)', boxShadow: 'var(--shadow-lg)',
          border: '1px solid var(--border-subtle)', overflow: 'hidden', ...style,
        }}
        {...rest}
      >
        {title && (
          <div style={{ padding: 'var(--space-6) var(--space-6) var(--space-4)', display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 'var(--space-4)' }}>
            <h2 style={{ margin: 0, fontFamily: 'var(--font-display)', fontWeight: 'var(--fw-semibold)', fontSize: 'var(--text-2xl)', color: 'var(--text-primary)', letterSpacing: 'var(--tracking-tight)' }}>{title}</h2>
            <button type="button" aria-label="Close" onClick={onClose} style={{ border: 'none', background: 'transparent', cursor: 'pointer', fontSize: '22px', lineHeight: 1, color: 'var(--text-muted)', padding: 0 }}>×</button>
          </div>
        )}
        <div style={{ padding: title ? '0 var(--space-6) var(--space-6)' : 'var(--space-6)', color: 'var(--text-secondary)', fontFamily: 'var(--font-body)', fontSize: 'var(--text-base)', lineHeight: 'var(--leading-normal)' }}>
          {children}
        </div>
        {footer && (
          <div style={{ padding: 'var(--space-4) var(--space-6)', borderTop: '1px solid var(--border-subtle)', display: 'flex', justifyContent: 'flex-end', gap: 'var(--space-3)', background: 'var(--surface-page-alt)' }}>
            {footer}
          </div>
        )}
      </div>
    </div>
  );
}
