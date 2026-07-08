import React from 'react';
export interface TagProps extends React.HTMLAttributes<HTMLSpanElement> {
  selected?: boolean;
  /** When provided, renders a × remove control. */
  onRemove?: (e: React.MouseEvent) => void;
  children?: React.ReactNode;
}
/** Rounded category chip — selectable and/or removable. */
export declare function Tag(props: TagProps): JSX.Element;
