import ProCard from '@ant-design/pro-card';
import { FormattedMessage } from 'umi';

import Editor from './editor';
import Player, { type H5PLoadedInfo } from './player';

const Card: React.FC<{
  defaultCard?: 'edit' | 'preview';
  id: 'new' | number | string;
  onSubmit: (id: string) => void;
  onLoaded?: (info: H5PLoadedInfo) => void;
}> = ({ defaultCard = 'edit', id, onSubmit, onLoaded }) => {
  return (
    <ProCard
      tabs={{
        type: 'card',
        defaultActiveKey: defaultCard,
        destroyInactiveTabPane: true,
      }}
    >
      <ProCard.TabPane key="edit" disabled={!id} tab={<FormattedMessage id="edit" />}>
        <Editor key={id} id={id} onSubmitted={onSubmit} onLoaded={onLoaded} />
      </ProCard.TabPane>
      {id !== 'new' && (
        <ProCard.TabPane key="preview" disabled={!id} tab={<FormattedMessage id="preview" />}>
          <Player id={id} />
        </ProCard.TabPane>
      )}
    </ProCard>
  );
};

export default Card;
