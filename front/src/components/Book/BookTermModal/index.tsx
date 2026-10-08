import Modal from "@ulams/components/components/atoms/Modal/Modal";
import { API } from "@ulams/sdk";
import ProposedTermsContent from "./ProposedTermsContent";
import UserSelectDatePicker from "./UserSelectDatePicker";

interface Props {
  onClose: () => void;
  visible: boolean;
  consultation: API.Consultation & {
    proposed_terms: string[];
  };
}

const BookTermModal: React.FC<Props> = ({ onClose, visible, consultation }) => {
  const isSingle = consultation.product?.type === "single";

  return (
    <Modal
      onClose={onClose}
      visible={visible}
      animation="zoom"
      maskAnimation="fade"
      destroyOnClose={true}
      width={468}
    >
      {isSingle ? (
        <UserSelectDatePicker consultation={consultation} onClose={onClose} />
      ) : (
        <ProposedTermsContent consultation={consultation} onClose={onClose} />
      )}
    </Modal>
  );
};

export default BookTermModal;
