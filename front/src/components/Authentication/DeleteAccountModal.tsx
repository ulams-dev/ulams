import { Modal } from "@ulams/components/components/atoms/Modal/Modal";
import { Button } from "@ulams/components/components/atoms/Button/Button";
import { Title } from "@ulams/components/components/atoms/Typography/Title";
import { useTranslation } from "react-i18next";
import styles from "./DeleteAccountModal.module.css";

type Props = {
  closeModal: () => void;
  showModal: boolean;
  handleDeleteAccount: () => void;
  isLoading?: boolean;
};

const DeleteAccountModal: React.FC<Props> = ({
  closeModal,
  showModal,
  handleDeleteAccount,
  isLoading,
}) => {
  const { t } = useTranslation();
  return (
    <Modal
      onClose={() => closeModal()}
      visible={showModal}
      animation="zoom"
      maskAnimation="fade"
      destroyOnClose={true}
      width={468}
    >
      <div className={styles.confirmation}>
        <Title level={3} style={{ textAlign: "center" }}>
          {t("MyProfilePage.DeleteAccountConfirmation")}
        </Title>
        <div className={`actions ${styles.actions}`}>
          <Button mode="outline" onClick={() => closeModal()}>
            {t("ResetProgressModal.Cancel")}
          </Button>
          <Button loading={isLoading} onClick={handleDeleteAccount}>
            {t("MyProfilePage.Delete")}
          </Button>
        </div>
      </div>
    </Modal>
  );
};

export default DeleteAccountModal;
