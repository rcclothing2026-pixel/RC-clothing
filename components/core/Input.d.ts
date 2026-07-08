import React from 'react';
export interface InputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'style'> {
  label?: string;
  hint?: string;
  /** Error message — shows in clay and marks the field invalid. */
  error?: string;
  leftIcon?: React.ReactNode;
  style?: React.CSSProperties;
  containerStyle?: React.CSSProperties;
}
/** Labelled text field with hint / error states and clay focus ring. */
export declare function Input(props: InputProps): JSX.Element;
