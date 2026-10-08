import * as React from "react";
import { EditorView } from "prosemirror-view";
import { withTheme } from "styled-components";
import ToolbarButton from "./ToolbarButton";
import ToolbarSeparator from "./ToolbarSeparator";
import theme from "../theme";
import { MenuItem } from "../types";

type Props = {
  tooltip: typeof React.Component | React.FC<any>;
  onCreateFlashcard?: (txt?: string, surroundTxt?: string) => void;
  onMakeAnswer?: (txt?: string, surroundTxt?: string) => void;
  onToggleFormat?: () => void;
  commands: Record<string, any>;
  view: EditorView;
  theme: typeof theme;
  items: MenuItem[];
  getSelection?: () => Array<string>;
};

class Menu extends React.Component<Props> {
  render() {
    const { view, items, getSelection } = this.props;
    const { state } = view;
    const Tooltip = this.props.tooltip;
    return (
      <div>
        {items.map((item, index) => {
          if (item.name === "separator" && item.visible !== false) {
            return <ToolbarSeparator key={index} />;
          }
          if (
            item.text &&
            item.name === "back" &&
            this.props.onMakeAnswer &&
            getSelection
          ) {
            return (
              <button
                style={{
                  transform: "translate(0, -8px)",
                  border: "none",
                  backgroundColor: "#e5e7e9",
                  borderRadius: "0.25rem",
                  lineHeight: "26px",
                  cursor: "pointer",
                }}
                onClick={() => {
                  this.props.onToggleFormat && this.props.onToggleFormat();
                }}
                key={index}
              >
                {item.text}
              </button>
            );
          }
          if (
            item.text &&
            item.name === "format" &&
            this.props.onMakeAnswer &&
            getSelection
          ) {
            return (
              <button
                style={{
                  transform: "translate(0, -8px)",
                  border: "none",
                  marginLeft: "10px",
                  backgroundColor: "#e5e7e9",
                  borderRadius: "0.25rem",
                  lineHeight: "26px",
                  cursor: "pointer",
                }}
                onClick={() => {
                  this.props.onToggleFormat && this.props.onToggleFormat();
                }}
                key={index}
              >
                {item.text}
              </button>
            );
          }
          if (
            item.text &&
            item.name === "makeanswer" &&
            this.props.onMakeAnswer &&
            getSelection
          ) {
            return (
              <button
                style={{
                  transform: "translate(0, -8px)",
                  border: "none",
                  backgroundColor: "#e5e7e9",
                  borderRadius: "0.25rem",
                  lineHeight: "26px",
                  cursor: "pointer",
                  fontWeight: "bolder",
                }}
                onClick={() => {
                  const [selectedText, surroundingText] = getSelection();

                  this.props.onMakeAnswer &&
                    this.props.onMakeAnswer(selectedText, surroundingText);
                }}
                key={index}
              >
                {item.text}
              </button>
            );
          }
          if (
            item.text &&
            item.name === "add_flashcard" &&
            this.props.onCreateFlashcard &&
            getSelection
          ) {
            return (
              <button
                style={{
                  transform: "translate(0, -8px)",
                  border: "none",
                  marginLeft: "10px",
                  backgroundColor: "#e5e7e9",
                  borderRadius: "0.25rem",
                  lineHeight: "26px",
                  cursor: "pointer",
                  fontWeight: "bolder",
                }}
                className="onboarding-flashcard"
                onClick={() => {
                  const [selectedText, surroundingText] = getSelection();

                  this.props.onCreateFlashcard &&
                    this.props.onCreateFlashcard(selectedText, surroundingText);
                }}
                key={index}
              >
                {item.text}
              </button>
            );
          }
          if (item.visible === false || !item.icon) {
            return null;
          }
          const Icon = item.icon;
          const isActive = item.active ? item.active(state) : false;

          return (
            <ToolbarButton
              key={index}
              onClick={() => {
                item.name && this.props.commands[item.name](item.attrs);
              }}
              active={isActive}
            >
              <Tooltip tooltip={item.tooltip} placement="top">
                <Icon color={this.props.theme.toolbarItem} />
              </Tooltip>
            </ToolbarButton>
          );
        })}
      </div>
    );
  }
}

export default withTheme(Menu) as unknown as React.ComponentType<
  Omit<Props, "theme">
>;
