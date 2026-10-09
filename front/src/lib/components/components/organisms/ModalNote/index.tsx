import {
  NewNoteData,
  NoteData,
} from "@ulams/components/components/molecules/CourseTopNav/CourseTopNav";
import { Button, Title, TextArea } from "../../../";
import { UlamsContext } from "@ulams/sdk/react";
import { Formik, FormikErrors } from "formik";
import { FC, useContext } from "react";
import { useTranslation } from "react-i18next";

import styles from "./ModalNote.module.css";

interface NoteModalProps {
  currentNote?: NoteData;
  newNoteData?: NewNoteData;
  onClose: () => void;
}

const ModalNote: FC<NoteModalProps> = ({
  currentNote,
  newNoteData,
  onClose,
}) => {
  const initialValues = {
    noteValue: currentNote ? currentNote.value : "",
  };
  const { fetchBookmarkNotes, createBookmarkNote, updateBookmarkNote } =
    useContext(UlamsContext);

  const { t } = useTranslation();

  return (
    <div className={styles.wrapper}>
      <header className={styles.header}>
        <Title>{t<string>("Bookmarks.Notes")}</Title>
      </header>
      <Formik
        initialValues={initialValues}
        validate={(values) => {
          const errors: FormikErrors<{ noteValue: string }> = {};

          if (!values.noteValue) {
            errors.noteValue = "Required";
          }
          return errors;
        }}
        onSubmit={(values) => {
          currentNote
            ? updateBookmarkNote(currentNote.id, {
                value: values.noteValue,
                bookmarkable_id: currentNote.bookmarkable_id,
                bookmarkable_type: currentNote.bookmarkable_type,
              }).then(() => {
                fetchBookmarkNotes(), onClose();
              })
            : createBookmarkNote({
                value: values.noteValue,
                // newNoteData is optional for callers (the former styled wrapper erased the
                // prop type); a new note is only created when it is set.
                bookmarkable_id: newNoteData?.id as number,
                bookmarkable_type: newNoteData?.type as NewNoteData["type"],
              }).then(() => {
                fetchBookmarkNotes(), onClose();
              });
        }}
      >
        {({ values, handleChange, handleSubmit, isSubmitting }) => (
          <form onSubmit={handleSubmit}>
            <TextArea
              name="noteValue"
              id="noteValue"
              label={t("Bookmarks.YourNote", {
                defaultValue: "Your note",
              })}
              placeholder={
                t("Bookmarks.WriteNote", {
                  defaultValue: "Write a note...",
                }) ?? undefined
              }
              value={values.noteValue}
              onChange={handleChange}
            />
            <div className={styles.buttons}>
              <Button type="button" mode="secondary" onClick={onClose}>
                {t<string>("Bookmarks.Cancel")}
              </Button>
              <Button type="submit" mode="secondary" disabled={isSubmitting}>
                {t<string>(`Bookmarks.${currentNote ? "Update" : "Add"}`)}
              </Button>
            </div>
          </form>
        )}
      </Formik>
    </div>
  );
};

export default ModalNote;
