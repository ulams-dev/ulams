import { FC } from "react";
// import { Link } from "react-router-dom";
import { isMobile } from "react-device-detect";
import { Tutor } from "@ulams/components/components/molecules/Tutor/Tutor";
import { API } from "@ulams/sdk";
import { APP_CONFIG } from "@/config/app";
import { avatarSrc } from "@ulams/images";
import { Col } from "react-grid-system";

interface CourseAuthorProps {
  author: API.UserItem;
}

export const CourseAuthor: FC<CourseAuthorProps> = ({ author }) => {
  return (
    <Col lg={6}>
      {/* <Link to={`/tutors/${author.id}`}> */}
      <Tutor
        mobile={isMobile}
        avatar={{
          alt: `${author.first_name} ${author.last_name}`,
          src: author.path_avatar
            ? avatarSrc(author)
            : APP_CONFIG.tutorPlaceholderPath,
        }}
        fullName={`${author.first_name} ${author.last_name}`}
        description={author.bio}
      />
      {/* </Link> */}
    </Col>
  );
};
