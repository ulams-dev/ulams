import { Row } from "@ulams/components/components/atoms/Row/index";
import styled from "styled-components";

export const Container = styled(Row)`
  justify-content: space-between;

  background: ${({ theme }) => theme.white};
`;
