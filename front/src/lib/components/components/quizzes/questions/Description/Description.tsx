import React from "react";
import { API } from "@ulams/sdk";
import { Stack, Text } from "../../../../";

type Props = API.QuizQuestion_Description;

const Description: React.FC<Props> = ({ title, question }) => (
  <Stack data-testid={`description-${question}`} $gap={2}>
    {title && (
      <Text weight="bold" size="lg">
        {title}
      </Text>
    )}
    <Text family="secondary" weight="semiBold">
      {question}
    </Text>
  </Stack>
);

export default Description;
