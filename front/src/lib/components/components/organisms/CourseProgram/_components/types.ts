import { API } from "@lms/sdk";

export interface SharedComponentProps {
  mobile?: boolean;
  onTopicClick: (topic: API.Topic) => void;
}
