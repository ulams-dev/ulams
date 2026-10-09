import { siblingAppUrl } from '@ulams/demo';
import { Space, Tag } from 'antd';
import React from 'react';
import { FormattedMessage, SelectLang, useModel } from 'umi';
import NoticeIconView from '../NoticeIcon';
import Avatar from './AvatarDropdown';
import styles from './index.less';

// import 'ant-design-pro/dist/ant-design-pro.css';
export type SiderTheme = 'light' | 'dark';

import { useMemo } from 'react';
import { useIntl } from 'umi';
import { getLangInfo } from '../../utils/utils';

declare const REACT_APP_ENV: string | undefined;

const ENVTagColor = {
  dev: 'orange',
  test: 'green',
  pre: '#87d068',
};

const GlobalHeaderRight: React.FC = () => {
  const { initialState } = useModel('@@initialState');
  const intl = useIntl();

  const langIcon = useMemo(() => {
    return getLangInfo(intl.locale).icon;
  }, [intl.locale]);

  if (!initialState || !initialState.settings) {
    return null;
  }

  const { navTheme, layout } = initialState.settings;
  const demo = initialState.demo;
  const learnerSite = demo?.enabled
    ? demo.frontUrl ?? siblingAppUrl(window.location, 'admin', 'app')
    : null;
  let className = styles.right;

  if ((navTheme === 'realDark' && layout === 'top') || layout === 'mix') {
    className = `${styles.right}  ${styles.dark}`;
  }

  return (
    <Space className={className}>
      {/* {!currentUser?.data.roles.includes('admin') && <NoticeIconView />} */}
      {demo?.enabled && (
        <Tag>
          <FormattedMessage id="demo_mode.badge" defaultMessage="Demo mode – reset hourly" />
          {learnerSite && (
            <>
              {' · '}
              <a href={learnerSite} target="_blank" rel="noopener noreferrer">
                <FormattedMessage
                  id="demo_mode.open_front"
                  defaultMessage="Open the learner site"
                />
              </a>
            </>
          )}
        </Tag>
      )}
      <NoticeIconView />
      <Avatar />
      {REACT_APP_ENV && (
        <span>
          <Tag color={ENVTagColor[REACT_APP_ENV as keyof typeof ENVTagColor]}>{REACT_APP_ENV}</Tag>
        </span>
      )}
      <SelectLang className={styles.action} icon={langIcon} />
    </Space>
  );
};
export default GlobalHeaderRight;
