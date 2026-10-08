import { useContext } from "react";
import { useTheme } from "styled-components";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import { Note } from "@ulams/components/components/atoms/Note/Note";
import Title from "@ulams/components/components/atoms/Typography/Title";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import { EventAgendaStyles } from "./EventAgendaStyles";

type Agenda = {
  id: number;
  title: string;
  subtitle: string;
  description: string;
  hour: string;
  tutors: number[];
};

const EventAgenda = () => {
  const { stationaryEvent } = useContext(UlamsContext);
  // TODO: fix this

  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const agenda: Agenda[] = stationaryEvent.value?.agenda as any;
  const theme = useTheme();
  const { t } = useTranslation();
  if (!agenda) {
    return null;
  }
  return (
    <section className="with-border">
      <EventAgendaStyles>
        <Title level={4}>{t("Agenda")}</Title>
        {agenda.map((agendaItem) => (
          <Note
            color={theme.primaryColor}
            description={
              <>
                <Title level={4}>{agendaItem.title}</Title>
                <Title level={5}>{agendaItem.subtitle}</Title>
                <Text>{agendaItem.description}</Text>
              </>
            }
            time={<>{agendaItem.hour}</>}
          />
        ))}
      </EventAgendaStyles>
    </section>
  );
};

export default EventAgenda;
