import { PageContainer } from '@ant-design/pro-layout';
import { Button, Card, Typography } from 'antd';
import React from 'react';
import { FormattedMessage } from 'umi';

import { studioUrl } from '@/services/studio';

const { Paragraph, Title } = Typography;

/** Menu entry for the AI Course Builder, which runs in the reference web app (/studio). */
const CourseBuilder: React.FC = () => (
  <PageContainer>
    <Card>
      <Title level={3}>
        <FormattedMessage id="course_builder.title" defaultMessage="Build a course with AI" />
      </Title>
      <Paragraph>
        <FormattedMessage
          id="course_builder.description"
          defaultMessage="Upload a document (Markdown, PDF or DOCX), answer a short interview, review the outline and the lessons, and the course is created here as an unpublished draft. Every lesson cites the passage it came from, and nothing changes until you approve it."
        />
      </Paragraph>
      <Button type="primary" size="large" href={studioUrl('/studio/new')}>
        <FormattedMessage id="course_builder.start" defaultMessage="Start the Course Builder" />
      </Button>{' '}
      <Button size="large" href={studioUrl('/studio')}>
        <FormattedMessage id="course_builder.sessions" defaultMessage="My builder sessions" />
      </Button>
    </Card>
  </PageContainer>
);

export default CourseBuilder;
