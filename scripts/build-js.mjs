import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';

const bootstrapBundle = readFileSync(resolve('node_modules/bootstrap/dist/js/bootstrap.bundle.min.js'), 'utf8');
const appSource = readFileSync(resolve('resources/js/app.js'), 'utf8');
const outputPath = resolve('public/assets/build/app.js');

mkdirSync(dirname(outputPath), { recursive: true });
writeFileSync(outputPath, `${bootstrapBundle}\n;(() => {\n${appSource}\n})();\n`);
