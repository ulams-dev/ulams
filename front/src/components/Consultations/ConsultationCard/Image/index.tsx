import { Link } from "react-router-dom";
import { API } from "@lms/sdk";
import ResponsiveImage from "@lms/components/components/organisms/ResponsiveImage/ResponsiveImage";
import CourseImgPlaceholder from "../../../Courses/CourseImgPlaceholder";

interface Props {
  consultation: API.Consultation;
}

const ConsultationCardImage = ({ consultation }: Props) => {
  return (
    <Link to={`/consultations/${consultation.id}`}>
      {consultation.image_path ? (
        <ResponsiveImage
          path={consultation.image_path}
          alt={consultation.name}
          srcSizes={[300, 600, 900]}
        />
      ) : (
        <CourseImgPlaceholder />
      )}
    </Link>
  );
};

export default ConsultationCardImage;
