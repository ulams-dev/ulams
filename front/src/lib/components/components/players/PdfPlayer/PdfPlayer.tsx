import * as React from "react";
import { Document, Page, pdfjs } from "react-pdf";
import { Button, Text } from "../../..";
import { useTranslation } from "react-i18next";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import "react-pdf/dist/Page/AnnotationLayer.css";
import "react-pdf/dist/Page/TextLayer.css";
import styles from "./PdfPlayer.module.css";

interface PdfPlayerProps extends ExtendableStyledComponent {
  url: string;
  documentConfig?: Omit<React.ComponentProps<typeof Document>, "file">;
  pageConfig?: React.ComponentProps<typeof Page>;
  onLoad?: () => void;
  onTopicEnd?: () => void;
}

export const PdfPlayer: React.FunctionComponent<PdfPlayerProps> = ({
  url,
  onLoad,
  onTopicEnd,
  documentConfig = {},
  pageConfig = {},
  className = "",
}): React.ReactElement => {
  const [allPages, setAllPages] = React.useState<number | null>(null);
  const [currentPage, setCurrentPage] = React.useState(1);
  const [isMounted, setIsMounted] = React.useState(false);
  const [endActionFired, setEndActionFired] = React.useState(false);
  const { t } = useTranslation();

  const handleNextPageClick = () => {
    if (onTopicEnd && allPages && !(allPages > currentPage + 1)) {
      setEndActionFired(true);
      onTopicEnd();
    }
    setCurrentPage(currentPage + 1);
  };

  const handlePrevPageClick = () => {
    setCurrentPage(currentPage - 1);
    if (endActionFired) {
      setEndActionFired(false);
    }
  };

  React.useEffect(() => {
    pdfjs.GlobalWorkerOptions.workerSrc = `//unpkg.com/pdfjs-dist@${pdfjs.version}/legacy/build/pdf.worker.min.mjs`;
    setIsMounted(true);
    return () => setIsMounted(false);
  }, []);

  React.useEffect(() => {
    if (currentPage === allPages) {
      onLoad && onLoad();
    }
  }, [allPages, currentPage]);

  if (!url) {
    return <p>{t("PdfPlayer.notFound")}</p>;
  }

  return (
    <div className={`${styles.root} ulams-component ${className}`}>
      {isMounted && url && (
        <Document
          loading={t("Loading")}
          onLoadSuccess={({ numPages }) => setAllPages(numPages)}
          file={url}
          {...documentConfig}
        >
          <Page
            pageNumber={currentPage}
            renderAnnotationLayer={false}
            {...pageConfig}
          />
        </Document>
      )}

      {allPages && allPages > 1 && (
        <div className={`${styles.paginationArea} pagination-area`}>
          <Text>
            <strong>{currentPage}</strong> {t("PdfPlayer.of")}{" "}
            <strong>{allPages}</strong>
          </Text>
          <div>
            <Button
              mode="secondary"
              disabled={!(currentPage > 1)}
              className="nav-btn-modal"
              onClick={handlePrevPageClick}
            >
              {t("Prev")}
            </Button>
            <Button
              style={{ marginLeft: "10px" }}
              mode="secondary"
              disabled={onTopicEnd ? endActionFired : !(allPages > currentPage)}
              className="nav-btn-modal"
              onClick={handleNextPageClick}
            >
              {t("Next")}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
};

export default PdfPlayer;
