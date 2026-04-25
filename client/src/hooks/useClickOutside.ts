import { useEffect, useRef, type RefObject } from 'react';

/** Close floating UI when user clicks or focuses outside `ref` subtree. */
export function useClickOutside(ref: RefObject<HTMLElement | null>, onOutside: () => void, enabled = true) {
  const cbRef = useRef(onOutside);
  cbRef.current = onOutside;

  useEffect(() => {
    if (!enabled) {
      return;
    }
    const onPointer = (e: MouseEvent | TouchEvent) => {
      const t = e.target;
      if (!(t instanceof Node)) {
        return;
      }
      if (!ref.current?.contains(t)) {
        cbRef.current();
      }
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        cbRef.current();
      }
    };
    document.addEventListener('mousedown', onPointer);
    document.addEventListener('touchstart', onPointer, { passive: true });
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onPointer);
      document.removeEventListener('touchstart', onPointer);
      document.removeEventListener('keydown', onKey);
    };
  }, [enabled, ref]);
}
