import { API } from "@ulams/sdk";

export interface SharedComponentProps {
  mobile?: boolean;
  onTopicClick: (topic: API.Topic) => void;
}
