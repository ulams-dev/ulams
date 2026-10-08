
export const guid = () =>
  Math.random()
    .toString(36)
    .replace(/[^a-z]+/g, "")
    .substr(2, 10);

export const calcPercentage = (current: number, max: number): string => {
  if (max === 0) {
    return "0%";
  }
  if (current >= max) {
    return "100%";
  }
  return `${Math.round((current / max) * 100)}%`;
};

export const roundPercentageList = (orig: number[], target?: number) => {
  if (!target) {
    target = 100;
  }

  const newVals = [];
  const len = orig.length;
  const marginOfErrors = [];

  let i = orig.length,
    j = 0,
    total = 0,
    change = 0,
    next,
    factor1,
    factor2;

  while (i--) {
    total += newVals[i] = Math.round(orig[i]);
  }

  change = total < target ? 1 : -1;

  while (total !== target) {
    for (i = 0; i < len; i++) {
      next = i === len - 1 ? 0 : i + 1;

      factor2 = errorFactor(orig[next], newVals[next] + change);
      factor1 = errorFactor(orig[i], newVals[i] + change);

      if (factor1 > factor2) {
        j = next;
      }
    }

    newVals[j] += change;
    total += change;
  }

  for (let i = 0; i < len; i++) {
    marginOfErrors[i] = newVals[i] && Math.abs(orig[i] - newVals[i]) / orig[i];
  }

  for (let i = 0; i < len; i++) {
    for (let j = 0; j < len; j++) {
      if (j === i) continue;

      const roundUpFactor =
        errorFactor(orig[i], newVals[i] + 1) +
        errorFactor(orig[j], newVals[j] - 1);
      const roundDownFactor =
        errorFactor(orig[i], newVals[i] - 1) +
        errorFactor(orig[j], newVals[j] + 1);
      const sumMargin = marginOfErrors[i] + marginOfErrors[j];

      if (roundUpFactor < sumMargin) {
        newVals[i] = newVals[i] + 1;
        newVals[j] = newVals[j] - 1;
        marginOfErrors[i] =
          newVals[i] && Math.abs(orig[i] - newVals[i]) / orig[i];
        marginOfErrors[j] =
          newVals[j] && Math.abs(orig[j] - newVals[j]) / orig[j];
      }

      if (roundDownFactor < sumMargin) {
        newVals[i] = newVals[i] - 1;
        newVals[j] = newVals[j] + 1;
        marginOfErrors[i] =
          newVals[i] && Math.abs(orig[i] - newVals[i]) / orig[i];
        marginOfErrors[j] =
          newVals[j] && Math.abs(orig[j] - newVals[j]) / orig[j];
      }
    }
  }

  function errorFactor(oldNum: number, newNum: number) {
    return Math.abs(oldNum - newNum) / oldNum;
  }

  return newVals;
};


let time = Date.now();
const usedIds: string[] = [];
export const getUniqueId = (uniqueName: string, twoIds = false) => {
  const newId = `${time}--${uniqueName}`;
  if (usedIds.filter((id) => id === newId).length > (twoIds ? 1 : 0)) {
    time = Date.now();
    usedIds.push(`${time}--${uniqueName}`);
    return `${time}--${uniqueName}`;
  }
  usedIds.push(newId);
  return newId;
};

/** Date formats */
export const DATE_FORMAT = "YYYY-MM-DD";
export const DATETIME_FORMAT = "yyyy-MM-dd HH:mm";
export const DAY_FORMAT = "yyyy-MM-dd";
