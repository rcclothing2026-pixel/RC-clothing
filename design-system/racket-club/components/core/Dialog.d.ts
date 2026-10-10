import React from 'react';
export interface DialogProps {
  open: boolean;
  onClose?: () => void;
  title?: string;
  footer?: React.ReactNode;
  width?: number;
  children?: React.ReactNode;
  style?: React.CSSProperties;
}
/** Centered modal with navy scrim. Closes on Escape / scrim click. */
export declare function Dialog(props: DialogProps): JSX.Element | null;
