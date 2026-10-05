export function isolatedEnvironment(env) {
    if (!/^bank_sampah_test(?:_[a-z0-9]+)*$/.test(env.E2E_DB_DATABASE ?? '')) {
        throw new Error('E2E_DB_DATABASE harus database uji bank_sampah_test yang terpisah.');
    }
    const url = new URL(env.E2E_BASE_URL ?? '');
    if (!['127.0.0.1', 'localhost', '[::1]'].includes(url.hostname)) {
        throw new Error('E2E_BASE_URL harus server uji loopback lokal.');
    }
    if (!env.E2E_ENVIRONMENT_TOKEN || env.E2E_ENVIRONMENT_TOKEN.length < 32) {
        throw new Error('E2E_ENVIRONMENT_TOKEN minimal 32 karakter wajib diatur pada runner dan server uji.');
    }
    return { baseUrl: url.href.replace(/\/$/, ''), database: env.E2E_DB_DATABASE };
}

export async function verifyIsolatedServer(env, fetcher = fetch) {
    const target = isolatedEnvironment(env);
    const response = await fetcher(`${target.baseUrl}/_test/environment`, {
        headers: { 'X-Test-Environment': env.E2E_ENVIRONMENT_TOKEN },
        redirect: 'error',
    });
    if (!response.ok) throw new Error('Server tujuan bukan lingkungan uji yang terverifikasi.');
    const identity = await response.json();
    if (identity.environment !== 'testing' || identity.database !== target.database) {
        throw new Error('Database server web berbeda dari database uji runner.');
    }
    return target;
}
