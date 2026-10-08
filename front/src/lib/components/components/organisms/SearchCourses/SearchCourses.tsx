import React, { useCallback, useContext, useRef, useState } from "react";
import { useTranslation } from "react-i18next";
import { course as fetchCourses } from "@ulams/sdk/services/courses";
import { API } from "@ulams/sdk";
import { UlamsContext } from "@ulams/sdk/react";
import { Button, Search as InputSearch } from "../../..";

import styles from "./SearchCourses.module.css";

export const SearchCourses: React.FC<{
  onItemSelected: (item: API.Course) => void;
  onInputSubmitted: (phrase: string) => void;
  mobile?: boolean;
}> = ({ onItemSelected, onInputSubmitted }) => {
  const abortController = useRef<AbortController>();
  const [fetching, setFetching] = useState(false);
  const [foundCourses, setFoundCourses] = useState<API.Course[]>([]);
  const { apiUrl } = useContext(UlamsContext);

  const setCoursesFromResponse = useCallback(
    (responseCourses: API.Course[]) => {
      setFoundCourses((prevCourses) =>
        [...prevCourses, ...responseCourses].filter(
          (course, index, arr) =>
            arr.findIndex((fcourse) => fcourse.id === course.id) === index
        )
      );
    },
    []
  );

  const fetch = useCallback((search?: string) => {
    setFetching(true);
    if (abortController.current) {
      abortController.current.abort();
    }

    abortController.current = new AbortController();
    fetchCourses
      .bind(null, apiUrl)(
        { title: search },
        { signal: abortController.current && abortController.current.signal }
      )
      .then((response) => {
        if (response && response.success) {
          setCoursesFromResponse(response.data);
        }
      })
      .catch(() => setFetching(false))
      .finally(() => setFetching(false));
  }, []);

  const onSearch = useCallback((val: string) => {
    fetch(val);
  }, []);
  const onSubmit = useCallback((val: string) => onInputSubmitted(val), []);
  const { t } = useTranslation();
  return (
    <div className={styles.wrapper}>
      <InputSearch
        loading={fetching}
        onSearch={onSearch}
        onSubmit={onSubmit}
        placeholder={t<string>("Search.Placeholder")}
      >
        {foundCourses.map((course) => (
          <Button
            className={styles.itemButton}
            block
            mode="white"
            key={course.id}
            onClick={() => {
              onItemSelected(course);
            }}
          >
            {course.title}
          </Button>
        ))}
      </InputSearch>
    </div>
  );
};

export default SearchCourses;
