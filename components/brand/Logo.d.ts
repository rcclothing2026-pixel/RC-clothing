import React from 'react';
export interface LogoProps extends React.HTMLAttributes<HTMLDivElement> {
  layout?: 'horizontal' | 'stacked' | 'mark' | 'wordmark';
  /** navy on light · cream on dark · green. */
  tone?: 'navy' | 'cream' | 'green';
  /** Mark height in px; wordmark scales from it. */
  size?: number;
  /** Show the "Legend & Legacy" script slogan. */
  showSlogan?: boolean;
}
/**
 * The Racket Club logo — mark + Mansory wordmark, drawn inline.
 * @startingPoint section="Brand" subtitle="Mark, wordmark & lockups" viewport="700x220"
 */
export declare function Logo(props: LogoProps): JSX.Element;
