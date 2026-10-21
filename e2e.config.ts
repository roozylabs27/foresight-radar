import type { E2EConfig } from 'e2e';
import { web } from '@e2e-dev/web';

export default {
  targets: [
    {
      name: 'chromium',
      engine: web(),
      app: {
        url: 'http://127.0.0.1:8080',
      },
    },
  ],
  timeout: 300000,
  actionTimeout: 120000,
  assertionTimeout: 120000,
  credentials: {
    admin: {
      username: 'superadmin@foresight.com',
      password: process.env.ADMIN_PASSWORD ?? 'password',
    },
    dev: {
      username: 'dev@foresight.com',
      password: process.env.DEV_PASSWORD ?? 'password',
    },
  },
} satisfies E2EConfig;
