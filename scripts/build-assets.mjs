import { mkdirSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

mkdirSync('public/assets/build', { recursive: true });

for (const script of ['build:css', 'build:js']) {
  const result = spawnSync('npm', ['run', script], {
    shell: process.platform === 'win32',
    stdio: 'inherit',
  });

  if (result.status !== 0) {
    process.exit(result.status ?? 1);
  }
}
