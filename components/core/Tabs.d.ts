import React from 'react';
export interface TabItem { id: string; label: React.ReactNode; }
export interface TabsProps extends Omit<React.HTMLAttributes<HTMLDivElement>, 'onChange'> {
  items: TabItem[];
  /** Controlled active id. */
  value?: string;
  defaultValue?: string;
  onChange?: (id: string) => void;
}
/** Underline tab bar with a clay active indicator. Controlled or uncontrolled. */
export declare function Tabs(props: TabsProps): JSX.Element;
