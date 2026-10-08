import { useContext } from "react";
import { useTranslation } from "react-i18next";
import { useHistory } from "react-router-dom";
import { API } from "@ulams/sdk";
import { isMobile } from "react-device-detect";
import { UlamsContext } from "@ulams/sdk/react";
import { CourseAgenda } from "@ulams/components/components/organisms/CourseAgenda/CourseAgenda";
import {
  CourseScheduleContent,
  CourseScheduleTitle,
  CourseScheduleWrapper,
} from "@/components/Courses/Course/CoursePanelLayout/Schedule/styles";
import { SubheaderTitle } from "@/components/Courses/Course/CoursePanelLayout/Subheader/styles";
import { useCoursePanel } from "@/components/Courses/Course/Context";

export const CourseSchedule = () => {
  const { t } = useTranslation();
  const { user } = useContext(UlamsContext);
  const {
    currentTopic,
    finishedTopicsIds,
    availableTopicsIds,
    courseId,
    currentCourseProgram,
  } = useCoursePanel();
  const history = useHistory();

  return (
    <CourseScheduleWrapper>
      <CourseScheduleTitle>
        <SubheaderTitle level={2}>
          {t("CoursePanel.ScheduleTitle")}
        </SubheaderTitle>
      </CourseScheduleTitle>
      <CourseScheduleContent>
        <CourseAgenda
          areAllTopicsUnlocked={
            !!currentCourseProgram?.authors.find(
              ({ id }) => id === user.value?.id
            )
          }
          lessons={currentCourseProgram?.lessons || []}
          currentTopicId={Number(currentTopic?.id)}
          finishedTopicIds={finishedTopicsIds ?? []}
          onMarkFinished={() => console.log("onMarkFinished")}
          onTopicClick={(topic: API.Topic) => {
            history.push(`/course/${courseId}/${topic.lesson_id}/${topic.id}`);
          }}
          onCourseFinished={() => console.log("onCourseFinished")}
          availableTopicsIds={availableTopicsIds ?? []}
          isMobile={isMobile}
        />
      </CourseScheduleContent>
    </CourseScheduleWrapper>
  );
};
