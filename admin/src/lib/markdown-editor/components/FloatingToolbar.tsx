import ResizeObserver from "resize-observer-polyfill";
import * as React from "react";
import { Portal } from "react-portal";
import { EditorView } from "prosemirror-view";
import {
  cx,
  EditorThemeContext,
  themeScopeProps,
} from "../themeContext";
import "../styles/components.css";

const SSR = typeof window === "undefined";

type Props = {
  active?: boolean;
  view: EditorView;
  children: React.ReactNode;
  forwardedRef?: React.RefObject<HTMLDivElement> | null;
};

const defaultPosition = {
  left: -1000,
  top: 0,
  offset: 0,
  visible: false,
};

const useComponentSize = ref => {
  const [size, setSize] = React.useState({
    width: 0,
    height: 0,
  });

  React.useEffect(() => {
    let sizeObserver;
    try {
      sizeObserver = new ResizeObserver(entries => {
        entries.forEach(({ target }) => {
          if (
            size.width !== target.clientWidth ||
            size.height !== target.clientHeight
          ) {
            setSize({ width: target.clientWidth, height: target.clientHeight });
          }
        });
      });
      sizeObserver.observe(ref.current);
    } catch {
      console.warn(`ResizeObserver not supported`);
    }

    return () => sizeObserver && sizeObserver.disconnect();
  }, [ref]);

  return size;
};

function usePosition({ menuRef, isSelectingText, props }) {
  const { view, active } = props;
  const { selection } = view.state;
  const { width: menuWidth, height: menuHeight } = useComponentSize(menuRef);

  if (!active || !menuWidth || !menuHeight || SSR || isSelectingText) {
    return defaultPosition;
  }

  // based on the start and end of the selection calculate the position at
  // the center top
  const fromPos = view.coordsAtPos(selection.$from.pos);
  const toPos = view.coordsAtPos(selection.$to.pos);

  // ensure that start < end for the menu to be positioned correctly
  const selectionBounds = {
    top: Math.min(fromPos.top, toPos.top),
    bottom: Math.max(fromPos.bottom, toPos.bottom),
    left: Math.min(fromPos.left, toPos.left),
    right: Math.max(fromPos.right, toPos.right),
  };

  // tables are an oddity, and need their own positioning logic
  const isColSelection = selection.isColSelection && selection.isColSelection();
  const isRowSelection = selection.isRowSelection && selection.isRowSelection();

  if (isColSelection) {
    const { node: element } = view.domAtPos(selection.$from.pos);
    const { width } = element.getBoundingClientRect();
    selectionBounds.top -= 20;
    selectionBounds.right = selectionBounds.left + width;
  }

  if (isRowSelection) {
    selectionBounds.right = selectionBounds.left = selectionBounds.left - 18;
  }

  const isImageSelection =
    selection.node && selection.node.type.name === "image";
  // Images need their own positioning to get the toolbar in the center
  if (isImageSelection) {
    const element = view.nodeDOM(selection.from);

    // Images are wrapped which impacts positioning - need to traverse through
    // p > span > div.image
    const imageElement = element.getElementsByTagName("img")[0];
    const { left, top, width } = imageElement.getBoundingClientRect();

    return {
      left: Math.round(left + width / 2 + window.scrollX - menuWidth / 2),
      top: Math.round(top + window.scrollY - menuHeight),
      offset: 0,
      visible: true,
    };
  } else {
    // calcluate the horizontal center of the selection
    const halfSelection =
      Math.abs(selectionBounds.right - selectionBounds.left) / 2;
    const centerOfSelection = selectionBounds.left + halfSelection;

    // position the menu so that it is centered over the selection except in
    // the cases where it would extend off the edge of the screen. In these
    // instances leave a margin
    const margin = 12;
    const left = Math.min(
      window.innerWidth - menuWidth - margin,
      Math.max(margin, centerOfSelection - menuWidth / 2)
    );
    const top = Math.min(
      window.innerHeight - menuHeight - margin,
      Math.max(margin, selectionBounds.top - menuHeight)
    );

    // if the menu has been offset to not extend offscreen then we should adjust
    // the position of the triangle underneath to correctly point to the center
    // of the selection still
    const offset = left - (centerOfSelection - menuWidth / 2);
    return {
      left: Math.round(left + window.scrollX),
      top: Math.round(top + window.scrollY),
      offset: Math.round(offset),
      visible: true,
    };
  }
}

function FloatingToolbar(props) {
  const menuRef = props.forwardedRef || React.createRef<HTMLDivElement>();
  const [isSelectingText, setSelectingText] = React.useState(false);
  const position = usePosition({
    menuRef,
    isSelectingText,
    props,
  });

  React.useEffect(() => {
    const handleMouseDown = () => {
      if (!props.active) {
        setSelectingText(true);
      }
    };

    const handleMouseUp = () => {
      setSelectingText(false);
    };

    window.addEventListener("mousedown", handleMouseDown);
    window.addEventListener("mouseup", handleMouseUp);

    return () => {
      window.removeEventListener("mousedown", handleMouseDown);
      window.removeEventListener("mouseup", handleMouseUp);
    };
  }, [props.active]);

  // only render children when state is updated to visible
  // to prevent gaining input focus before calculatePosition runs
  // Portalled out of the editor root, so it carries the theme variables itself.
  const themeContext = React.useContext(EditorThemeContext);
  const active = props.active && position.visible;
  const scope = themeScopeProps(
    themeContext,
    cx(
      "ulams-md-floating-toolbar",
      active && "ulams-md-floating-toolbar--active"
    ),
    {
      top: `${position.top}px`,
      left: `${position.left}px`,
      ["--md-toolbar-offset" as string]: `${position.offset || 0}px`,
    }
  );

  return (
    <Portal>
      <div ref={menuRef} className={scope.className} style={scope.style}>
        {position.visible && props.children}
      </div>
    </Portal>
  );
}

export default React.forwardRef(function FloatingToolbarWithForwardedRef(
  props: Props,
  ref: React.RefObject<HTMLDivElement>
) {
  return <FloatingToolbar {...props} forwardedRef={ref} />;
});
