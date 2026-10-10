import React from 'react';
export interface IconButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Accessible label (also the title tooltip). */
  label: string;
  variant?: 'ghost' | 'solid' | 'outline';
  size?: 'sm' | 'md' | 'lg';
  children?: React.ReactNode;
}
/** Square, icon-only action button. Pass an icon (e.g. a Lucide SVG) as children. */
export declare function IconButton(props: IconButtonProps): JSX.Element;
