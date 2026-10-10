import { isMobile } from "react-device-detect";
import { Link } from "react-router-dom";

import { Tutor } from "@ulams/components/components/molecules/Tutor/Tutor";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import styles from "./TutorsSection.module.css";
import { User } from "@ulams/sdk/types";
import { API_URL } from "@/config/index";

type CustomUser = User & {
  bio?: string;
};

interface TutorsSectionProps {
  users: CustomUser[];
  title: React.ReactNode;
}

const TutorsSection = ({ users, title }: TutorsSectionProps) => {
  if (!users?.length) {
    return null;
  }
  return (
    <div className={styles.root}>
      <section className="section-tutor with-border padding-right">
        <Title as="h3" level={4} className="title">
          {title}
        </Title>
        {users.map((user) => (
          <Tutor
            className={styles.tutor}
            mobile={isMobile}
            avatar={{
              alt: `${user.first_name} ${user.last_name}`,
              src: `${API_URL}/api/images/img?path=${user.path_avatar}` || "",
            }}
            // TODO: Change rating when will be available from response
            rating={{
              ratingValue: 4.1,
            }}
            title={<></>}
            fullName={
              <Link to={`/tutors/${user.id}`}>
                {`${user.first_name} ${user.last_name}`}
              </Link>
            }
            // TODO: Change courses info when will be available from response
            coursesInfo={"8 Curses"}
            description={user.bio}
          />
        ))}
      </section>
    </div>
  );
};

export default TutorsSection;
