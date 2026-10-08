import PieChart from '@/components/PieChart/PieChart';
import ProCard from '@ant-design/pro-card';
import { PageContainer } from '@ant-design/pro-layout';
import React from 'react';

export default (): React.ReactNode => {
  return (
    <PageContainer>
      <ProCard split="vertical">
        <ProCard colSpan={12} layout="center">
          <PieChart metric={'Ulams\\Reports\\Metrics\\CoursesMoneySpentMetric'} />
        </ProCard>
        <ProCard colSpan={12} layout="center">
          <PieChart metric={'Ulams\\Reports\\Metrics\\CoursesPopularityMetric'} />
        </ProCard>
      </ProCard>
      <ProCard split="vertical">
        <ProCard colSpan={12} layout="center">
          <PieChart metric={'Ulams\\Reports\\Metrics\\CoursesSecondsSpentMetric'} />
        </ProCard>
        <ProCard colSpan={12} layout="center">
          <PieChart metric={'Ulams\\Reports\\Metrics\\TutorsPopularityMetric'} />
        </ProCard>
      </ProCard>
    </PageContainer>
  );
};
