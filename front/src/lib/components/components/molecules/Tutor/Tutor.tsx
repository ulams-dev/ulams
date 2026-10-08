import * as React from "react";
import { Title } from "../../atoms/Typography/Title";
import { Avatar, AvatarProps } from "../../atoms/Avatar/Avatar";
import { RatingProps, Rating } from "../../atoms/Rating/Rating";
import { ReactNode } from "react";
import { Text } from "../../atoms/Typography/Text";
import { ExtendableStyledComponent } from "@ulams/components/types/component";
import styles from "./Tutor.module.css";

interface StyledTourProps {
  mobile?: boolean;
}
export interface TutorProps extends StyledTourProps, ExtendableStyledComponent {
  title?: ReactNode;
  fullName: ReactNode | string;
  avatar: AvatarProps;
  rating?: RatingProps;
  description?: ReactNode | string;
  coursesInfo?: ReactNode | string;
}

export const Tutor: React.FC<TutorProps> = (props) => {
  const {
    title,
    fullName,
    avatar,
    rating,
    coursesInfo,
    description,
    mobile,
    className = "",
  } = props;

  return (
    <div
      className={`ulams-component lms-tutor ${styles.root} ${
        mobile ? styles.mobile : ""
      } ${className}`}
    >
      {React.isValidElement(title) ? (
        title
      ) : (
        <Title as="h3" level={4} className="title">
          {title}
        </Title>
      )}
      <div className="avatar-row">
        <Avatar size={"extraLarge"} {...avatar} />
        <div className="avatar-info">
          <Title as="h4" level={4}>
            {fullName}
          </Title>
          <div className="ranking-row">
            {rating && <Rating {...rating} label={rating?.ratingValue} />}
            {coursesInfo && <Text className="course-info">{coursesInfo}</Text>}
          </div>
          {!mobile && <Text className="description">{description}</Text>}
        </div>
      </div>
      {mobile && <Text className="description">{description}</Text>}
    </div>
  );
};
