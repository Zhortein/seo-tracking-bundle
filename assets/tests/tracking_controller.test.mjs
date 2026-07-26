import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import test from 'node:test';

const controllerPath = fileURLToPath(new URL('../dist/tracking_controller.js', import.meta.url));

test('the distributed Stimulus controller is valid JavaScript', () => {
    assert.doesNotThrow(() => {
        execFileSync(process.execPath, ['--check', controllerPath], { stdio: 'pipe' });
    });
});
