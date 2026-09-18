/**
 * Склонение существительного после числа.
 *
 * В русском три формы: 1 метка, 2 метки, 5 меток.
 * Правило: 11-14 всегда берут третью форму, иначе смотрим на последнюю цифру.
 *
 * @param forms [для 1, для 2-4, для 5-20]
 */
export function plural(count: number, forms: [string, string, string]): string {
  const abs = Math.abs(count) % 100;
  const last = abs % 10;

  if (abs > 10 && abs < 20) return forms[2];
  if (last > 1 && last < 5) return forms[1];
  if (last === 1) return forms[0];

  return forms[2];
}

/** Число вместе с существительным в нужной форме: «23 метки». */
export function pluralize(count: number, forms: [string, string, string]): string {
  return `${count} ${plural(count, forms)}`;
}
