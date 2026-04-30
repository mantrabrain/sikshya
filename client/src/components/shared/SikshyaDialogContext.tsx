import { createContext, useCallback, useContext, useMemo, useState, type ReactElement, type ReactNode } from 'react';
import { createRoot } from 'react-dom/client';
import { ConfirmDialog } from './ConfirmDialog';

export type SikshyaConfirmOptions = {
  title: string;
  message: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'default' | 'danger';
};

export type SikshyaAlertOptions = {
  title: string;
  message: ReactNode;
  buttonLabel?: string;
};

type ConfirmState = {
  kind: 'confirm';
  title: string;
  message: ReactNode;
  confirmLabel?: string;
  cancelLabel?: string;
  variant?: 'default' | 'danger';
  resolve: (v: boolean) => void;
};

type AlertState = {
  kind: 'alert';
  title: string;
  message: ReactNode;
  buttonLabel?: string;
  resolve: () => void;
};

type DialogState = ConfirmState | AlertState | null;

type Ctx = {
  confirm: (opts: SikshyaConfirmOptions) => Promise<boolean>;
  alert: (opts: SikshyaAlertOptions) => Promise<void>;
};

/**
 * Imperative mounts when {@link SikshyaDialogProvider} is not an ancestor (or context is unavailable).
 * Avoids `window.confirm` / `window.alert` so UX stays consistent.
 */
function mountDetachedDialog(node: ReactElement): () => void {
  if (typeof document === 'undefined') {
    return () => {};
  }
  const host = document.createElement('div');
  host.setAttribute('data-sikshya-imperative-dialog', '');
  document.body.appendChild(host);
  const root = createRoot(host);
  root.render(node);
  return () => {
    queueMicrotask(() => {
      root.unmount();
      host.remove();
    });
  };
}

function detachedConfirm(opts: SikshyaConfirmOptions): Promise<boolean> {
  if (typeof document === 'undefined') {
    void opts;
    return Promise.resolve(false);
  }
  return new Promise<boolean>((resolve) => {
    let teardown: (() => void) | undefined;
    const finish = (v: boolean): void => {
      teardown?.();
      resolve(v);
    };
    teardown = mountDetachedDialog(
      <ConfirmDialog
        open
        type="confirm"
        title={opts.title}
        confirmLabel={opts.confirmLabel}
        cancelLabel={opts.cancelLabel}
        variant={opts.variant}
        onClose={() => finish(false)}
        onConfirm={() => finish(true)}
      >
        {opts.message}
      </ConfirmDialog>
    );
  });
}

function detachedAlert(opts: SikshyaAlertOptions): Promise<void> {
  if (typeof document === 'undefined') {
    void opts;
    return Promise.resolve();
  }
  return new Promise<void>((resolve) => {
    let teardown: (() => void) | undefined;
    const finish = (): void => {
      teardown?.();
      resolve();
    };
    teardown = mountDetachedDialog(
      <ConfirmDialog
        open
        type="alert"
        title={opts.title}
        dismissLabel={opts.buttonLabel}
        onClose={finish}
      >
        {opts.message}
      </ConfirmDialog>
    );
  });
}

/** Used when React context is missing; still renders {@link ConfirmDialog}. */
const FALLBACK_CTX: Ctx = {
  confirm: detachedConfirm,
  alert: detachedAlert,
};

const SikshyaDialogContext = createContext<Ctx | null>(null);

export function SikshyaDialogProvider({ children }: { children: ReactNode }) {
  const [state, setState] = useState<DialogState>(null);

  const confirm = useCallback((opts: SikshyaConfirmOptions) => {
    return new Promise<boolean>((resolve) => {
      setState({
        kind: 'confirm',
        title: opts.title,
        message: opts.message,
        confirmLabel: opts.confirmLabel,
        cancelLabel: opts.cancelLabel,
        variant: opts.variant,
        resolve,
      });
    });
  }, []);

  const alert = useCallback((opts: SikshyaAlertOptions) => {
    return new Promise<void>((resolve) => {
      setState({
        kind: 'alert',
        title: opts.title,
        message: opts.message,
        buttonLabel: opts.buttonLabel,
        resolve,
      });
    });
  }, []);

  const value = useMemo(() => ({ confirm, alert }), [confirm, alert]);

  const closeConfirm = useCallback((result: boolean) => {
    setState((s) => {
      if (s?.kind === 'confirm') {
        s.resolve(result);
      }
      return null;
    });
  }, []);

  const closeAlert = useCallback(() => {
    setState((s) => {
      if (s?.kind === 'alert') {
        s.resolve();
      }
      return null;
    });
  }, []);

  const dialogEl =
    state?.kind === 'confirm' ? (
      <ConfirmDialog
        open
        type="confirm"
        title={state.title}
        confirmLabel={state.confirmLabel}
        cancelLabel={state.cancelLabel}
        variant={state.variant}
        onClose={() => closeConfirm(false)}
        onConfirm={() => closeConfirm(true)}
      >
        {state.message}
      </ConfirmDialog>
    ) : state?.kind === 'alert' ? (
      <ConfirmDialog
        open
        type="alert"
        title={state.title}
        dismissLabel={state.buttonLabel}
        onClose={closeAlert}
      >
        {state.message}
      </ConfirmDialog>
    ) : null;

  return (
    <SikshyaDialogContext.Provider value={value}>
      {children}
      {dialogEl}
    </SikshyaDialogContext.Provider>
  );
}

export function useSikshyaDialog(): Ctx {
  const ctx = useContext(SikshyaDialogContext);
  return ctx ?? FALLBACK_CTX;
}
