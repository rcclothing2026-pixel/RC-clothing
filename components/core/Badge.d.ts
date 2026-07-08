import React from 'react';
export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  tone?: 'navy' | 'green' | 'clay' | 'brown' | 'sand';
  variant?: 'solid' | 'subtle';
  /** Show a leading status dot. */
  dot?: boolean;
  children?: React.ReactNode;
}
/** Compact uppercase status / category label. */
export declare function Badge(props: BadgeProps): JSX.Element;
