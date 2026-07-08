import React from 'react';
export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  variant?: 'default' | 'sunken' | 'inverse' | 'court';
  padding?: 'sm' | 'md' | 'lg';
  /** Lifts on hover — use for clickable cards. */
  interactive?: boolean;
  children?: React.ReactNode;
}
/**
 * Surface container. `inverse` = navy, `court` = green.
 * @startingPoint section="Core" subtitle="Surface containers in 4 tones" viewport="700x260"
 */
export declare function Card(props: CardProps): JSX.Element;
