import { useContext, useMemo } from "react";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react/context";
import { API } from "@ulams/sdk";
import { toast } from "@/utils/toast";

interface Props {
  topic?: API.Topic;
  courseId?: number;
  program: API.CourseProgram;
  lesson?: API.Lesson;
}

export const useBookmarkNotes = ({
  topic,
}: // courseId,
// program,
// lesson,
Props) => {
  const {
    bookmarkNotes,
    fetchBookmarkNotes,
    createBookmarkNote,
    deleteBookmarkNote,
  } = useContext(UlamsContext);
  const { t } = useTranslation();

  const topicBookmark = useMemo(
    () =>
      bookmarkNotes.list?.data.filter(
        (item) =>
          item.bookmarkable_id === Number(topic?.id) && item.value === null
      ),
    [bookmarkNotes.list?.data, topic?.id]
  );

  const handleBookmark = () => {
    return !topicBookmark?.length
      ? createBookmarkNote({
          bookmarkable_id: Number(topic?.id),
          bookmarkable_type: API.BookmarkableType.Topic,
          value: null,
        }).then(() => {
          fetchBookmarkNotes();
          toast(t("Notifications.CreateBookmark"), "success");
        })
      : deleteBookmarkNote(topicBookmark[0].id).then(() => {
          fetchBookmarkNotes();
          toast(t("Notifications.DeleteBookmark"), "success");
        });
  };

  const topicNote = useMemo(
    () =>
      bookmarkNotes.list?.data.filter(
        (item) =>
          item.bookmarkable_id === Number(topic?.id) && item.value !== null
      ),
    [bookmarkNotes.list?.data, topic?.id]
  );

  return {
    handleBookmark,
    topicBookmark,
    topicNote,
  };
};
