import type { ReactNode } from 'react';
import { createBrowserRouter, Navigate, Outlet } from 'react-router-dom';
import { LoginPage } from './pages/auth/LoginPage';
import { RegisterPage } from './pages/auth/RegisterPage';
import { ForgotPasswordPage } from './pages/auth/ForgotPasswordPage';
import { ResetPasswordPage } from './pages/auth/ResetPasswordPage';
import { VerifyEmailPage } from './pages/auth/VerifyEmailPage';
import { AcceptInvitePage } from './pages/auth/AcceptInvitePage';
import { DashboardPage } from './pages/DashboardPage';
import { SettingsPage } from './pages/SettingsPage';
import { NotFoundPage } from './pages/NotFoundPage';
import { AppLayout, RequireRegistrationOpen } from './components/layout';
import { useAuthStore } from './stores/useAuthStore';
import {
  VehicleDetailPage,
  VehicleEditPage,
  VehicleListPage,
  VehicleNewPage,
} from './pages/vehicles';
import {
  MaintenanceDetailPage,
  MaintenanceEditPage,
  MaintenanceListPage,
  MaintenanceNewPage,
} from './pages/maintenance';
import {
  RefuelingDetailPage,
  RefuelingEditPage,
  RefuelingListPage,
  RefuelingNewPage,
} from './pages/refueling';
import {
  ExpenseDetailPage,
  ExpenseEditPage,
  ExpenseListPage,
  ExpenseNewPage,
} from './pages/expenses';
import {
  ReminderDetailPage,
  ReminderEditPage,
  ReminderListPage,
  ReminderNewPage,
} from './pages/reminders';

/** Route guard: richiede sessione autenticata, altrimenti redirect login. */
function RequireAuth() {
  const status = useAuthStore((s) => s.status);
  if (status !== 'authenticated') return <Navigate to="/login" replace />;
  return <Outlet />;
}

/** Route guard inverso: se già autenticato, redirect home (login/register). */
function RedirectIfAuthed({ children }: { children: ReactNode }) {
  const status = useAuthStore((s) => s.status);
  if (status === 'authenticated') return <Navigate to="/" replace />;
  return <>{children}</>;
}

export const router = createBrowserRouter([
  {
    path: '/login',
    element: (
      <RedirectIfAuthed>
        <LoginPage />
      </RedirectIfAuthed>
    ),
  },
  {
    path: '/register',
    element: (
      <RedirectIfAuthed>
        <RequireRegistrationOpen>
          <RegisterPage />
        </RequireRegistrationOpen>
      </RedirectIfAuthed>
    ),
  },
  {
    path: '/forgot-password',
    element: (
      <RedirectIfAuthed>
        <ForgotPasswordPage />
      </RedirectIfAuthed>
    ),
  },
  {
    path: '/reset-password',
    element: (
      <RedirectIfAuthed>
        <ResetPasswordPage />
      </RedirectIfAuthed>
    ),
  },
  // Pubbliche e accessibili anche da loggati: il token arriva dall'email.
  { path: '/verify-email', element: <VerifyEmailPage /> },
  { path: '/accept-invite', element: <AcceptInvitePage /> },
  {
    element: <RequireAuth />,
    children: [
      {
        element: <AppLayout />,
        children: [
          { path: '/', element: <DashboardPage /> },
          { path: '/vehicles', element: <VehicleListPage /> },
          { path: '/vehicles/new', element: <VehicleNewPage /> },
          { path: '/vehicles/:id', element: <VehicleDetailPage /> },
          { path: '/vehicles/:id/edit', element: <VehicleEditPage /> },
          { path: '/maintenance', element: <MaintenanceListPage /> },
          { path: '/maintenance/new', element: <MaintenanceNewPage /> },
          { path: '/maintenance/:id', element: <MaintenanceDetailPage /> },
          { path: '/maintenance/:id/edit', element: <MaintenanceEditPage /> },
          { path: '/refueling', element: <RefuelingListPage /> },
          { path: '/refueling/new', element: <RefuelingNewPage /> },
          { path: '/refueling/:id', element: <RefuelingDetailPage /> },
          { path: '/refueling/:id/edit', element: <RefuelingEditPage /> },
          { path: '/expenses', element: <ExpenseListPage /> },
          { path: '/expenses/new', element: <ExpenseNewPage /> },
          { path: '/expenses/:id', element: <ExpenseDetailPage /> },
          { path: '/expenses/:id/edit', element: <ExpenseEditPage /> },
          { path: '/reminders', element: <ReminderListPage /> },
          { path: '/reminders/new', element: <ReminderNewPage /> },
          { path: '/reminders/:id', element: <ReminderDetailPage /> },
          { path: '/reminders/:id/edit', element: <ReminderEditPage /> },
          { path: '/settings', element: <SettingsPage /> },
          { path: '*', element: <NotFoundPage /> },
        ],
      },
    ],
  },
]);
