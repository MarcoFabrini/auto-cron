import { useEffect, useState, type ReactNode } from 'react';
import type { ToastActionElement, ToastProps } from '@/components/ui/Toast';

/**
 * Imperative toast API:
 *
 *   const { toast } = useToast();
 *   toast({ title: 'Salvato', variant: 'success' });
 *
 * Single global store (module-scoped). Adattato da shadcn/ui/sonner pattern.
 */

const TOAST_LIMIT = 3;
const TOAST_REMOVE_DELAY = 5_000;

type ToastVariant = NonNullable<ToastProps['variant']>;

export interface ToasterToast {
  id: string;
  title?: ReactNode;
  description?: ReactNode;
  action?: ToastActionElement;
  open?: boolean;
  variant?: ToastVariant;
  onOpenChange?: (open: boolean) => void;
}

type ActionType =
  | { type: 'ADD_TOAST'; toast: ToasterToast }
  | { type: 'UPDATE_TOAST'; toast: Partial<ToasterToast> & { id: string } }
  | { type: 'DISMISS_TOAST'; id?: string }
  | { type: 'REMOVE_TOAST'; id?: string };

interface State {
  toasts: ToasterToast[];
}

let memoryState: State = { toasts: [] };
const listeners: Array<(state: State) => void> = [];
const timeouts = new Map<string, ReturnType<typeof setTimeout>>();

function emit() {
  for (const l of listeners) l(memoryState);
}

function addRemoveTimeout(id: string) {
  if (timeouts.has(id)) return;
  const t = setTimeout(() => {
    timeouts.delete(id);
    dispatch({ type: 'REMOVE_TOAST', id });
  }, TOAST_REMOVE_DELAY);
  timeouts.set(id, t);
}

function reducer(state: State, action: ActionType): State {
  switch (action.type) {
    case 'ADD_TOAST':
      return { ...state, toasts: [action.toast, ...state.toasts].slice(0, TOAST_LIMIT) };
    case 'UPDATE_TOAST':
      return {
        ...state,
        toasts: state.toasts.map((t) => (t.id === action.toast.id ? { ...t, ...action.toast } : t)),
      };
    case 'DISMISS_TOAST': {
      if (action.id) {
        addRemoveTimeout(action.id);
      } else {
        state.toasts.forEach((t) => addRemoveTimeout(t.id));
      }
      return {
        ...state,
        toasts: state.toasts.map((t) =>
          t.id === action.id || action.id === undefined ? { ...t, open: false } : t,
        ),
      };
    }
    case 'REMOVE_TOAST':
      return {
        ...state,
        toasts: action.id ? state.toasts.filter((t) => t.id !== action.id) : [],
      };
  }
}

function dispatch(action: ActionType) {
  memoryState = reducer(memoryState, action);
  emit();
}

let counter = 0;
function genId() {
  counter = (counter + 1) % Number.MAX_SAFE_INTEGER;
  return counter.toString();
}

type ToastInput = Omit<ToasterToast, 'id' | 'open' | 'onOpenChange'>;

function toast(input: ToastInput) {
  const id = genId();
  const update = (next: Partial<ToasterToast>) =>
    dispatch({ type: 'UPDATE_TOAST', toast: { ...next, id } });
  const dismiss = () => dispatch({ type: 'DISMISS_TOAST', id });

  dispatch({
    type: 'ADD_TOAST',
    toast: {
      ...input,
      id,
      open: true,
      onOpenChange: (open) => {
        if (!open) dismiss();
      },
    },
  });

  return { id, dismiss, update };
}

export function useToast() {
  const [state, setState] = useState<State>(memoryState);

  useEffect(() => {
    listeners.push(setState);
    return () => {
      const i = listeners.indexOf(setState);
      if (i > -1) listeners.splice(i, 1);
    };
  }, []);

  return {
    toasts: state.toasts,
    toast,
    dismiss: (id?: string) => dispatch({ type: 'DISMISS_TOAST', id }),
  };
}

export { toast };
