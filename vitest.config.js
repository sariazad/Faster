import { defineConfig } from 'vitest/config';

// Unit tests for the browser game engines (no Laravel needed).
export default defineConfig({
    test: { include: ['tests/js/**/*.test.js'], environment: 'node' },
});
