import CourseRow from '@/components/CourseRow';
import GiftQuizRow from '@/components/GiftQuizRow';
import OrderRow from '@/components/OrderRow';
import UserRow from '@/components/UserRow';
import React from 'react';
import CategoryRow from '../CategoryRow';
import ConsultationRow from '../ConsultationRow';
import ProductRow from '../ProductRow';
import QuestionnaireRow from '../QuestionnaireRow';
import StationaryEventRow from '../StationaryEventRow';
import StudentsRow from '../StudentsRow';
import UserGroupRow from '../UserGroupRow';
import WebinarRow from '../WebinarRow';

type PossibleType =
  | 'App\\Models\\User'
  | 'App\\Models\\Course'
  | 'App\\Models\\Consultation'
  | 'Ulams\\Consultations\\Models\\Consultation'
  | 'App\\Models\\Webinar'
  | 'Ulams\\Webinars\\Models\\Webinar'
  | 'App\\Models\\StationaryEvent'
  | 'Ulams\\StationaryEvents\\Models\\StationaryEvent'
  | 'Ulams\\Core\\Models\\User'
  | 'Ulams\\Cart\\Models\\Order'
  | 'Ulams\\Cart\\Models\\Course'
  | 'Ulams\\Auth\\Models\\UserGroup'
  | 'Ulams\\TopicTypeGift\\Models\\GiftQuiz'
  | 'Ulams\\Vouchers\\Models\\Order'
  | 'Questionnaire'
  | 'Product'
  | 'Students'
  | 'Category';

type DataProps = API.LinkedType;

export const TypeButton: React.FC<{
  type: PossibleType;
  type_id: number;
  onData: (data: DataProps) => void;
  text?: React.ReactNode;
}> = ({ type, type_id, onData, text }) => {
  switch (type) {
    case 'App\\Models\\StationaryEvent':
    case 'Ulams\\StationaryEvents\\Models\\StationaryEvent':
      return (
        <StationaryEventRow id={type_id} onLoaded={(event) => onData({ type, value: event })} />
      );

    case 'App\\Models\\Webinar':
    case 'Ulams\\Webinars\\Models\\Webinar':
      return <WebinarRow id={type_id} onLoaded={(webinar) => onData({ type, value: webinar })} />;
    case 'App\\Models\\Consultation':
    case 'Ulams\\Consultations\\Models\\Consultation':
      return (
        <ConsultationRow
          id={type_id}
          onLoaded={(consultation) => onData({ type, value: consultation })}
        />
      );
    case 'App\\Models\\User':
    case 'Ulams\\Core\\Models\\User':
      return (
        <UserRow id={type_id} onLoaded={(user) => onData({ type, value: user })} text={text} />
      );
    case 'Ulams\\Cart\\Models\\Order':
      return <OrderRow id={type_id} onLoaded={(order) => onData({ type, value: order })} />;
    case 'Ulams\\Vouchers\\Models\\Order':
      return <OrderRow id={type_id} onLoaded={(order) => onData({ type, value: order })} />;
    case 'Ulams\\Cart\\Models\\Course':
    case 'App\\Models\\Course':
      return (
        <CourseRow
          id={type_id}
          onLoaded={(course) => onData({ type: 'Ulams\\Cart\\Models\\Course', value: course })}
          text={text}
        />
      );
    case 'Ulams\\Auth\\Models\\UserGroup':
      return (
        <UserGroupRow id={type_id} onLoaded={(userGroup) => onData({ type, value: userGroup })} />
      );
    case 'Questionnaire':
      return (
        <QuestionnaireRow
          id={type_id}
          onLoaded={(questionnaire) => onData({ type, value: questionnaire })}
          text={text}
        />
      );
    // TODO #1022 add onLoaded
    case 'Ulams\\TopicTypeGift\\Models\\GiftQuiz':
      return (
        <GiftQuizRow id={type_id} onLoaded={(giftQuiz) => onData({ type, value: giftQuiz })} />
      );
    case 'Product':
      return <ProductRow id={type_id} onLoaded={(product) => onData({ type, value: product })} />;
    case 'Students':
      return (
        <StudentsRow id={type_id} onLoaded={(students) => onData({ type, value: students })} />
      );
    case 'Category':
      return (
        <CategoryRow id={type_id} onLoaded={(category) => onData({ type, value: category })} />
      );
    default:
      return type && type_id ? (
        <pre>
          {type} id: {type_id}
        </pre>
      ) : (
        <React.Fragment />
      );
  }
};

export default TypeButton;
