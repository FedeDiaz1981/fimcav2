// @ts-check
import { defineConfig } from 'astro/config';

// https://astro.build/config
export default defineConfig({
  server: {
    // @ts-ignore
    proxy: {
        '/api': 'http://127.0.0.1:8080', // ⇦ tu server PHP
      },
  }
});
