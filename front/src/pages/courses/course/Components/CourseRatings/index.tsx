import { FC, memo } from "react";
import { API } from "@ulams/sdk";
import { CourseRatingsReviews } from "./Reviews";
import { CourseRatingProvider } from "./Provider";

interface CourseRatingsProps {
  questionnaires: API.Questionnaire[];
}

export const CourseRatings: FC<CourseRatingsProps> = memo(
  ({ questionnaires }) => {
    return (
      <CourseRatingProvider questionnaires={questionnaires}>
        <section className="course-ratings">
          <CourseRatingsReviews />
        </section>
      </CourseRatingProvider>
    );
  }
);
