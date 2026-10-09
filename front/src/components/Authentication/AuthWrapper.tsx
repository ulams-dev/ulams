import styles from "./AuthWrapper.module.css";

type Props = {
  children: React.ReactNode;
};

const AuthWrapper: React.FC<Props> = ({ children }) => {
  return <div className={styles.loginPage}>{children}</div>;
};

export default AuthWrapper;
