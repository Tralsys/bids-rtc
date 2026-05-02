import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  timeout: 60_000,       // 60s per test (SDP polling is 15s)
  globalTimeout: 300_000, // 5 minutes total
  workers: 1,            // run sequentially (tests share API state)
  reporter: 'list',
  use: {
    baseURL: process.env.API_BASE_URL || 'http://localhost:8080',
    extraHTTPHeaders: {
      'Content-Type': 'application/json',
    },
  },
});
