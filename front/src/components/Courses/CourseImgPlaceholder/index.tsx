import Placeholder from "../../../images/no-picture.png";
import styles from "./styles.module.css";

const CourseImgPlaceholder = () => {
  return (
    <div className={styles.placeholder}>
      <img src={Placeholder} alt="" />
    </div>
  );
};

export default CourseImgPlaceholder;
