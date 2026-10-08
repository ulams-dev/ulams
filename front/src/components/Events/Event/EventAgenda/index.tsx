import { useContext } from "react";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react";
import { Note } from "@ulams/components/components/atoms/Note/Note";
import Title from "@ulams/components/components/atoms/Typography/Title";
import { Text } from "@ulams/components/components/atoms/Typography/Text";
import styles from "./EventAgenda.module.css";

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
  const { t } = useTranslation();
  if (!agenda) {
    return null;
  }
  return (
    <section className="with-border">
      <div className={styles.root}>
        <Title level={4}>{t("Agenda")}</Title>
        {agenda.map((agendaItem) => (
          <Note
            color="var(--ulams-color-primary)"
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
      </div>
    </section>
  );
};

export default EventAgenda;
