import * as React from "react";
import Dialog, { DialogProps } from "rc-dialog";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import "./Modal.css";
import { legacyDefault } from "../../../utils/legacy";

export interface ModalProps extends DialogProps, ExtendableStyledComponent {}

const CloseBtn = () => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    width="18"
    height="18"
    viewBox="0 0 18 18"
    aria-label="Close modal"
  >
    <title>Close modal</title>
    <g id="close" transform="translate(0 -0.005)">
      <g id="Group_68" data-name="Group 68" transform="translate(0 0.005)">
        <path
          id="Path_31"
          data-name="Path 31"
          d="M15.367,2.638a9,9,0,1,0,0,12.734A9.014,9.014,0,0,0,15.367,2.638Zm-2.653,9.02a.75.75,0,1,1-1.061,1.061L9,10.066,6.349,12.718a.75.75,0,0,1-1.061-1.061L7.939,9,5.287,6.352A.75.75,0,0,1,6.348,5.291L9,7.944l2.652-2.653a.75.75,0,0,1,1.061,1.061L10.061,9Z"
          transform="translate(0 -0.005)"
          fill="#afafaf"
        />
      </g>
    </g>
  </svg>
);

export const Modal: React.FC<ModalProps> = (props) => {
  const { children, width, className = "", style } = props;
  // Former global style: `max-width: ${width ?? "468px"}` — a numeric width was unitless and ignored.
  const maxWidth =
    !width
      ? undefined
      : typeof width === "number"
      ? "none"
      : width;
  const wrapper = React.useRef<HTMLDivElement>(null);
  return (
    <React.Fragment>
      <div ref={wrapper}>
        <Dialog
          {...props}
          closeIcon={<CloseBtn />}
          className={`ulams-component ${className}`}
          style={
            maxWidth
              ? ({ "--modal-max-width": maxWidth, ...style } as React.CSSProperties)
              : style
          }
        >
          {children}
        </Dialog>
      </div>
    </React.Fragment>
  );
};

export default legacyDefault(Modal);
