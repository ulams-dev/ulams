import * as React from "react";
import { useTranslation } from "react-i18next";
import { Button, Modal, ModalNote, Icon } from "../../../";
import styles from "./CourseTopNav.module.css";
import { getUniqueId } from "../../../utils/utils";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import { useState } from "react";
import { BookmarkableType } from "@ulams/sdk/types";

interface StyledAsideProps {
  mobile?: boolean;
}

export interface NoteData {
  id: number;
  value: string;
  bookmarkable_id: number;
  bookmarkable_type: BookmarkableType;
}

export interface NewNoteData {
  id: number;
  type: BookmarkableType;
}

export interface CourseTopNavProps
  extends StyledAsideProps,
    ExtendableStyledComponent {
  isFinished: boolean;
  hasNext: boolean;
  hasPrev: boolean;
  onNext: () => void;
  onPrev: () => void;
  onFinish: () => void;
  allButtonsDisabled?: boolean;
  isMarkBtnDisabled?: boolean;
  currentNote?: NoteData;
  newNoteData?: NewNoteData;
  addNotes?: boolean;
  addBookmarks?: boolean;
  onBookmarkClick?: () => void;
  bookmarkBtnText?: "addBookmark" | "deleteBookmark";
  isLast?: boolean;
  onCourseFinished?: () => void;
}

export const CourseTopNav: React.FC<CourseTopNavProps> = (props) => {
  const {
    isFinished = false,
    isMarkBtnDisabled = false,
    isLast = false,
    hasNext = true,
    hasPrev = true,
    allButtonsDisabled = false,
    onNext,
    onPrev,
    onFinish,
    onBookmarkClick,
    newNoteData,
    currentNote,
    addNotes = false,
    addBookmarks = false,
    mobile,
    className = "",
    bookmarkBtnText = "addBookmark",
    onCourseFinished,
  } = props;

  const [showNoteModal, setShowNoteModal] = useState(false);
  const { t } = useTranslation();

  const renderFinishButton = React.useCallback(() => {
    if (!isLast && isFinished) {
      return (
        <Button
          mode={"primary"}
          className="icon-btn next-btn"
          onClick={() => onNext && onNext()}
          disabled={!hasNext || allButtonsDisabled}
          aria-label={t("Actions.ShowNext")}
        >
          {t("CourseTopNav.next")}
          <Icon name="chevronRight" />
        </Button>
      );
    }
    if (!isFinished) {
      return (
        <Button
          mode={"outline"}
          className="icon-btn mark-btn"
          onClick={() => {
            onFinish && onFinish();
          }}
          aria-label={t("Course.markAsFinished")}
          disabled={isMarkBtnDisabled || allButtonsDisabled}
        >
          <Icon name="finished" />
          {t("Course.markAsFinished")}
        </Button>
      );
    }
    return (
      <Button
        mode={"primary"}
        onClick={() => {
          if (onCourseFinished) {
            onCourseFinished();
          }
        }}
        aria-label={t("Course.finishCourse")}
        disabled={allButtonsDisabled}
      >
        {t("Course.finishCourse")}
      </Button>
    );
  }, [isFinished, t, onFinish, isLast, allButtonsDisabled]);

  const renderNoteButton = React.useCallback(() => {
    return (
      <Button
        mode={"icon"}
        className="icon-btn"
        onClick={() => setShowNoteModal(true)}
        aria-label={t("CourseTopNav.addNote")}
        disabled={allButtonsDisabled}
      >
        <Icon name="note" />
        {t("CourseTopNav.addNote")}
      </Button>
    );
  }, [t, setShowNoteModal, allButtonsDisabled]);

  const renderBookmarkButton = React.useCallback(() => {
    return (
      <Button
        mode={"icon"}
        className="icon-btn"
        onClick={() => onBookmarkClick && onBookmarkClick()}
        aria-label={t(`CourseTopNav.${bookmarkBtnText}`)}
        disabled={allButtonsDisabled}
      >
        <Icon name="bookmark" />
        {t(`CourseTopNav.${bookmarkBtnText}`)}
      </Button>
    );
  }, [onBookmarkClick, bookmarkBtnText, allButtonsDisabled]);

  return (
    <>
      <aside
        aria-label={getUniqueId("aside")}
        className={`ulams-component ${styles.root} ${
          mobile ? styles.mobile : ""
        } ${className}`}
      >
        {mobile && (
          <div className="course-nav-middle-btns">
            {addNotes && renderNoteButton()}
            {addBookmarks && renderBookmarkButton()}
          </div>
        )}
        <div className="course-nav-container">
          <Button
            className="icon-btn prev-btn"
            mode="gray"
            onClick={() => onPrev && onPrev()}
            disabled={!hasPrev || allButtonsDisabled}
            aria-label={t("Actions.ShowPrevious")}
          >
            <Icon name="chevronLeft" />
            {t("CourseTopNav.prev")}
          </Button>

          {!mobile && (
            <div className="course-nav-middle-btns">
              {addNotes && renderNoteButton()}
              {addBookmarks && renderBookmarkButton()}
            </div>
          )}

          {renderFinishButton()}
        </div>
      </aside>
      <Modal
        visible={showNoteModal}
        onClose={() => setShowNoteModal(false)}
        animation="zoom"
        maskAnimation="fade"
        destroyOnClose={true}
        width={800}
      >
        <ModalNote
          currentNote={currentNote}
          onClose={() => setShowNoteModal(false)}
          newNoteData={newNoteData}
        />
      </Modal>
    </>
  );
};
