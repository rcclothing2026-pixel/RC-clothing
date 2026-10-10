import React from 'react';
export interface EyebrowProps extends React.HTMLAttributes<HTMLSpanElement> {
  tone?: 'accent' | 'primary' | 'muted' | 'inverse';
  /** Show the short leading rule. */
  rule?: boolean;
  children?: React.ReactNode;
}
/** Overline label that sits above a heading. */
export declare function Eyebrow(props: EyebrowProps): JSX.Element;
