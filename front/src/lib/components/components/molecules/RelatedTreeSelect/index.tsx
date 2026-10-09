import React, { useContext, useMemo } from "react";
import { API } from "@ulams/sdk";
import { UlamsContext } from "@ulams/sdk/react";
import { TreeSelect, TreeSelectProps } from "../../atoms/TreeSelect";
import { Stack } from "../../../";
import styles from "./RelatedTreeSelect.module.css";

type Props<ValueType> = Omit<TreeSelectProps<ValueType>, "treeData"> & {
  label?: React.ReactNode;
  error?: React.ReactNode;
};

interface TreeNode {
  title: string;
  label: string;
  value: RelatedValue; // `${class}:${id}`
  children?: TreeNode[];
}

// `${class}:${id}`
export type RelatedValue =
  | `Ulams\\Courses\\Course:${number}`
  | `Ulams\\Courses\\Topic:${number}`
  | `Ulams\\Courses\\Lesson:${number}`;

function isLesson(el: API.Lesson | API.Topic): el is API.Lesson {
  return (el as API.Lesson).lessons !== undefined;
}

const traverseTree = (
  branch: API.Lesson[] | API.Topic[],
  currLabel: string
): TreeNode[] => {
  return branch?.map((br) => {
    if (isLesson(br)) {
      const label = `${currLabel} - ${br.title}`;
      return {
        title: br.title,
        label,
        value: `Ulams\\Courses\\Lesson:${br.id}`,
        children: [
          ...traverseTree(br.lessons as API.Lesson[], label),
          ...traverseTree(br?.topics ?? [], label),
        ],
      };
    }

    return {
      title: br.title,
      label: `${currLabel} - ${br.title}`,
      value: `Ulams\\Courses\\Topic:${br.id}`,
    };
  });
};

export const RelatedTreeSelect = <ValueType,>({
  treeDefaultExpandAll = true,
  id,
  label,
  error,
  ...props
}: Props<ValueType>) => {
  const { program } = useContext(UlamsContext);

  const treeData: TreeNode[] = useMemo(() => {
    if (!program || !program.byId) return [];

    return Object.values(program.byId).reduce<TreeNode[]>(
      (acc, courseProgram) => {
        if (!courseProgram.value) return acc;

        return [
          ...acc,
          {
            title: courseProgram.value.title,
            label: courseProgram.value.title,
            value: `Ulams\\Courses\\Course:${courseProgram.value.id}`,
            children: traverseTree(
              courseProgram.value?.lessons ?? [],
              courseProgram.value.title
            ),
          },
        ];
      },
      []
    );
  }, [program]);

  return (
    <Stack $gap={4}>
      {label && <label className={styles.label} htmlFor={id}>
          {label}
        </label>}
      <TreeSelect
        {...props}
        id={id}
        treeDefaultExpandAll={treeDefaultExpandAll}
        treeData={treeData}
      />
      {error && <div className={styles.error} data-testid={`Error.${id}`}>
          {error}
        </div>}
    </Stack>
  );
};
