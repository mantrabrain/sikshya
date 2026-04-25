import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react';
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
  if (!ctx) {
    throw new Error('useSikshyaDialog must be used within SikshyaDialogProvider');
  }
  return ctx;
}
