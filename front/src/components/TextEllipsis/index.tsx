import {
  Text,
  TextProps,
} from "@ulams/components/components/atoms/Typography/Text";
import styles from "./styles.module.css";

type Props = TextProps & {
  text: string;
  length?: number;
  tail?: string;
};

export const TextEllipsis = ({
  text,
  length = 30,
  tail = "...",
  ...props
}: Props) => {
  const firstText = text.slice(0, length);
  const secondText = text.slice(length, text.length);

  return (
    <Text {...props}>
      <span className={styles.span}>
        {firstText}
        {secondText && (
          <>
            <span className={styles.tail}>{tail}</span>
            <span className={styles.child}>{secondText}</span>
          </>
        )}
      </span>
    </Text>
  );
};
