import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: ['test/**/*.test.ts'],
        environment: 'node',
        testTimeout: 30000,
        hookTimeout: 60000,
        // Integration test files each create their own throwaway schema, so
        // they can run in parallel safely.
        pool: 'forks'
    }
});
