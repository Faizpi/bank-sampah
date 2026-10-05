import test from 'node:test';
import assert from 'node:assert/strict';
import { isolatedEnvironment, verifyIsolatedServer } from './isolated-environment.mjs';

const safe = { E2E_BASE_URL: 'http://127.0.0.1:8099', E2E_DB_DATABASE: 'bank_sampah_test_browser', E2E_ENVIRONMENT_TOKEN: 'x'.repeat(32) };

test('rejects missing configuration, operational databases, remote URLs and short tokens', () => {
    for (const env of [{}, { ...safe, E2E_DB_DATABASE: 'bank_sampah' }, { ...safe, E2E_BASE_URL: 'https://example.com' }, { ...safe, E2E_ENVIRONMENT_TOKEN: 'short' }]) {
        assert.throws(() => isolatedEnvironment(env));
    }
});

test('verifies the actual web server database before any fixture mutations', async () => {
    await assert.rejects(verifyIsolatedServer(safe, async () => ({ ok: true, json: async () => ({ environment: 'testing', database: 'bank_sampah' }) })));
    await assert.rejects(verifyIsolatedServer(safe, async () => ({ ok: false })));
    assert.deepEqual(await verifyIsolatedServer(safe, async () => ({ ok: true, json: async () => ({ environment: 'testing', database: safe.E2E_DB_DATABASE }) })), { baseUrl: safe.E2E_BASE_URL, database: safe.E2E_DB_DATABASE });
});
