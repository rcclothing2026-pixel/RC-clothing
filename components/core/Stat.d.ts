import React from 'react';
export interface StatProps extends React.HTMLAttributes<HTMLDivElement> {
  value: React.ReactNode;
  label: string;
  caption?: string;
  align?: 'left' | 'center';
  tone?: 'primary' | 'accent' | 'inverse';
}
/** Big Mansory figure with a tracked uppercase label. */
export declare function Stat(props: StatProps): JSX.Element;
