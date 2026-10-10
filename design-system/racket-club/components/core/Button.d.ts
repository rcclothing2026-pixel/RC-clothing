import React from 'react';

export type ButtonVariant = 'primary' | 'secondary' | 'accent' | 'gradient' | 'outline' | 'ghost';
export type ButtonSize = 'sm' | 'md' | 'lg';

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Visual style. `primary` = navy, `secondary` = forest green, `accent` = the clay→brown CTA gradient, `gradient` = the navy→forest primary gradient. */
  variant?: ButtonVariant;
  size?: ButtonSize;
  disabled?: boolean;
  fullWidth?: boolean;
  /** Icon element rendered before the label. */
  leftIcon?: React.ReactNode;
  /** Icon element rendered after the label. */
  rightIcon?: React.ReactNode;
  children?: React.ReactNode;
}

/**
 * Primary action control for Racket Club. Sharp-cornered, tracked caps-friendly label.
 * @startingPoint section="Core" subtitle="Buttons in every variant & size" viewport="700x180"
 */
export declare function Button(props: ButtonProps): JSX.Element;
