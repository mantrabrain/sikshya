import type { SikshyaReactConfig } from '../types';

export function getConfig(): SikshyaReactConfig {
  const c = window.sikshyaReact;
  if (!c) {
    throw new Error('sikshyaReact is not defined');
  }
  return c;
}
