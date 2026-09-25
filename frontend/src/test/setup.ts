// Matcher DOM (toBeInTheDocument, toHaveClass, ...) per Vitest.
import '@testing-library/jest-dom/vitest';
import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';

// Smonta gli alberi React tra un test e l'altro.
afterEach(() => {
  cleanup();
});
