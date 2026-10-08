import { Button, Title, Text, List, Icon, Stack } from "../../..";
import { UlamsContext } from "@ulams/sdk/react";
import { FC, ReactNode, useContext, useEffect, useMemo, useState } from "react";
import { API } from "@ulams/sdk";
import { t } from "i18next";
import styles from "./BookmarkNotes.module.css";

export interface Dropdown {
  id: number;
  content: ReactNode;
}

interface BookmarkNotesComponentProps {
  onClickBookmark: (
    courseId: number,
    lessonId: number,
    topicId: number
  ) => void;
  onDelete?: () => void;
}

export const BookmarkNotes: FC<BookmarkNotesComponentProps> = ({
  onClickBookmark,
  onDelete,
}) => {
  const [currentPage, setCurrentPage] = useState(1);
  const [lastPage, setLastPage] = useState(2);
  const previousDisabled = currentPage <= 1;
  const nextDisabled = currentPage >= lastPage;
  const [selectedListItem, setSelectedListItem] = useState(0);

  const { bookmarkNotes, fetchBookmarkNotes, deleteBookmarkNote } =
    useContext(UlamsContext);

  const bookmarks = useMemo(
    () => bookmarkNotes.list?.data.filter((item) => item.value === null),
    [bookmarkNotes.list?.data]
  );

  const notes = useMemo(
    () => bookmarkNotes.list?.data.filter((item) => item.value),
    [bookmarkNotes.list?.data]
  );

  const listItems = [
    {
      id: 0,
      icon: <Icon name="editAll" />,
      text: t<string>("Bookmarks.All"),
      numberOfItems: bookmarkNotes.list?.data.length || 0,
    },
    {
      id: 1,
      icon: <Icon name="editAlt" />,
      text: t<string>("Bookmarks.Notes"),
      numberOfItems: bookmarkNotes.list?.data.length
        ? Number(notes?.length)
        : 0,
    },
    {
      id: 2,
      icon: <Icon name="edit" />,
      text: t<string>("Bookmarks.Bookmarks"),
      numberOfItems: bookmarkNotes.list?.data.length
        ? Number(bookmarks?.length)
        : 0,
    },
  ];

  const getArrayToMap = () => {
    switch (selectedListItem) {
      case 0:
        return bookmarkNotes.list?.data;
      case 1:
        return notes;
      case 2:
        return bookmarks;
    }
  };

  const handleBookmark = (id: number) =>
    deleteBookmarkNote(id).then(() => {
      fetchBookmarkNotes();
      onDelete && onDelete();
    });

  useEffect(() => {
    fetchBookmarkNotes({ page: currentPage, per_page: 25 });
    setLastPage(Number(bookmarkNotes.list?.meta.last_page));
  }, [fetchBookmarkNotes, currentPage]);

  return (
    <div className={styles.container}>
      <div className={styles.header}>
        <Title level={4}>{t<string>("Bookmarks.Title")}</Title>
      </div>
      <div className={styles.body}>
        <div className={styles.menu}>
          <List
            listItems={listItems}
            selectedListItem={selectedListItem}
            setSelectedListItem={setSelectedListItem}
          />
        </div>
        <div className={styles.content}>
          <ul className={styles.list}>
            {getArrayToMap()?.map((item: API.BookmarkNote) => {
              const { id, bookmarkable_type, value } = item;
              const bookmarkInfo = bookmarkable_type.split(":");
              const bookmark = bookmarkable_type
                .split("/")
                .map((num) => parseInt(num));
              return (
                <li className={styles.item} key={id}>
                  <Stack>
                    <Title
                      className={styles.title}
                      size="lg"
                      weight="medium"
                      onClick={() =>
                        onClickBookmark(bookmark[0], bookmark[1], bookmark[2])
                      }
                    >
                      {`${bookmarkInfo[1]} : ${bookmarkInfo[2]}`}
                    </Title>
                    {value && <Text className={styles.noteText}>{value}</Text>}
                  </Stack>
                  <Button mode="outline" onClick={() => handleBookmark(id)}>
                    {t<string>("Bookmarks.Delete")}
                  </Button>
                </li>
              );
            })}
          </ul>
          {bookmarkNotes.list?.data.length === 0 && (
            <Text weight="light">{t<string>("Bookmarks.NoBookmarks")}</Text>
          )}
        </div>
      </div>
      <div className={styles.page}>
        <Button
          type="button"
          mode="outline"
          disabled={previousDisabled}
          onClick={() => setCurrentPage(currentPage - 1)}
        >
          {t<string>("Bookmarks.Prev")}
        </Button>

        <Title>{`${t<string>("Bookmarks.Page")} ${currentPage} / ${
          lastPage || 1
        }`}</Title>
        <Button
          type="button"
          mode="outline"
          disabled={nextDisabled}
          onClick={() => setCurrentPage(currentPage + 1)}
        >
          {t<string>("Bookmarks.Next")}
        </Button>
      </div>
    </div>
  );
};

export default BookmarkNotes;
