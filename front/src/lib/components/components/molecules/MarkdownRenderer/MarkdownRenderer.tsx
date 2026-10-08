import React, { useEffect, useId, useState } from "react";
import ReactMarkdown from "react-markdown";
import { ReactMarkdownOptions } from "react-markdown/lib/react-markdown";
// import rehypeRaw from "rehype-raw";
// import remarkGfm from "remark-gfm";
// import remarkMath from "remark-math";
// import rehypeKatex from "rehype-katex";
import "katex/dist/katex.min.css";
import { Gallery, Item } from "react-photoswipe-gallery";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { fixContentForMarkdown } from "../../../utils/components/markdown";
import "../../../utils/photoswipe.css";
import { Link } from "../../../";
import styles from "./MarkdownRenderer.module.css";

interface StyledMarkdownRendererProps {
  mobile?: boolean;
  fontSize?: string;
}

export interface MarkdownRendererProps
  extends ReactMarkdownOptions,
    StyledMarkdownRendererProps,
    ExtendableStyledComponent {}

const pxToEm = (px: string) => {
  const pxNumber = parseFloat(px);
  const emNumber = pxNumber / 14;
  return emNumber.toFixed(2);
};

export const MarkdownRenderer: React.FC<MarkdownRendererProps> = (props) => {
  const { mobile = false, fontSize = "16", children, className } = props;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [rehypePlugins, setRehypePlugins] = useState<Array<any>>([]);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const [remarkPlugins, setRemarkPlugins] = useState<Array<any>>([]);

  useEffect(() => {
    (async () => {
      const [rehypeRaw, rehypeKatex, remarkGfm, remarkMath] = await Promise.all(
        [
          import("rehype-raw").then((mod) => mod.default),
          import("rehype-katex").then((mod) => mod.default),
          import("remark-gfm").then((mod) => mod.default),
          import("remark-math").then((mod) => mod.default),
        ]
      );
      setRehypePlugins([rehypeRaw, rehypeKatex]);
      setRemarkPlugins([remarkGfm, remarkMath]);
    })();
  }, []);

  return (
    <div
      className={`${styles.root} ${
        mobile ? styles.mobile : ""
      } ulams-component ${className}`}
      style={
        fontSize
          ? ({
              "--ulams-md-font-size": `${pxToEm(fontSize)}em`,
            } as React.CSSProperties)
          : undefined
      }
    >
      <ReactMarkdown
        linkTarget="_blank"
        rehypePlugins={rehypePlugins}
        remarkPlugins={remarkPlugins}
        components={{
          img: (props) => {
            return <MarkdownImage {...props} />;
          },
          table: (props) => {
            return <MarkdownTable {...props} />;
          },
          a: (props) => {
            return <Link {...props} />;
          },
          input: (props) => {
            return <MarkdownCheckList {...props} />;
          },
        }}
        {...props}
      >
        {fixContentForMarkdown(children)}
      </ReactMarkdown>
    </div>
  );
};

export const MarkdownCheckList: React.FC<
  React.InputHTMLAttributes<HTMLInputElement>
> = ({ checked, disabled, type }) => {
  const ID = useId();
  return (
    <input
      id={ID}
      className="text-checkbox"
      type={type}
      disabled={disabled}
      checked={checked}
      aria-labelledby={ID}
      placeholder="checkbox"
    />
  );
};

export const MarkdownImage: React.FC<
  React.ImgHTMLAttributes<HTMLImageElement>
> = ({ src, alt, title }) => {
  const [size, setSize] = useState([0, 0]);

  return (
    <>
      <Gallery
        options={{
          arrowPrev: false,
          arrowNext: false,
          imageClickAction: "zoom",
          initialZoomLevel: "fit",
          secondaryZoomLevel: 2,
          maxZoomLevel: 3,
        }}
      >
        <Item original={src} width={size[0]} height={size[1]}>
          {({ ref, open }) => (
            <div className={`image ${title ? "image-" + title : ""}`}>
              <span
                role="button"
                onClick={open}
                onKeyDown={() => open({} as React.MouseEvent)}
                tabIndex={0}
                aria-label={`Open ${title}`}
                style={{ cursor: "pointer" }}
              >
                <img
                  ref={ref as React.MutableRefObject<HTMLImageElement>}
                  onLoad={(e) =>
                    setSize([
                      e.currentTarget.naturalWidth,
                      e.currentTarget.naturalHeight,
                    ])
                  }
                  src={src}
                  alt={alt}
                />
              </span>
            </div>
          )}
        </Item>
      </Gallery>
    </>
  );
};

export const MarkdownTable: React.ComponentType<
  React.TableHTMLAttributes<HTMLTableElement>
> = (props) => {
  return (
    <div className="table-responsive">
      <table className={`table ${props.className ?? ""}`} {...props}>
        {props.children}
      </table>
    </div>
  );
};

export default MarkdownRenderer;
