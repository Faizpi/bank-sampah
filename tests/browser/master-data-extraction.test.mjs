import { test } from 'node:test';
import assert from 'node:assert/strict';
import { extractMasterData, parseInsertData } from '../../documents/extract-master-data.mjs';

test('literal parsing supports escaped apostrophes, commas, semicolons, and NULL', () => {
    const rows = parseInsertData("INSERT INTO `examples` (`id`, `label`, `note`) VALUES\n(1, 'Rumah, O\\'Brien; blok 1', NULL);\n");
    assert.deepEqual(rows.examples, [{ id: 1, label: "Rumah, O'Brien; blok 1", note: null }]);
    assert.throws(() => parseInsertData('INSERT INTO `examples` (`id`) VALUES (SLEEP(1));'));
});

test('extracts four roles and filters historical transactions and incompatible profiles', () => {
    const data = extractMasterData(`
INSERT INTO \`roles\` (\`id\`, \`name\`) VALUES (1, 'warga'), (2, 'petugas'), (5, 'superadmin');
INSERT INTO \`role_user\` (\`user_id\`, \`role_id\`) VALUES (1, 1), (2, 2), (3, 5);
INSERT INTO \`users\` (\`id\`, \`username\`, \`remember_token\`) VALUES (1, 'citizen', 'secret'), (2, 'officer', NULL), (3, 'formeradmin', NULL);
INSERT INTO \`customer_profiles\` (\`user_id\`, \`address\`, \`qr_token_encrypted\`) VALUES (1, 'Jalan Uji', 'old-key'), (2, 'Jalan Staff', 'old-key');
INSERT INTO \`ledger_entries\` (\`id\`, \`amount\`) VALUES (1, 5000);
`);
    assert.deepEqual(data.tables.users.map((user) => user.role), ['warga', 'petugas', 'admin']);
    assert.equal(data.tables.customer_profiles.length, 1);
    assert.ok(!('remember_token' in data.tables.users[0]));
    assert.ok(!('qr_token_encrypted' in data.tables.customer_profiles[0]));
    assert.ok(!('ledger_entries' in data.tables));
});

test('refuses ambiguous roles without inventing an assignment', () => {
    assert.throws(() => extractMasterData(`
INSERT INTO \`roles\` (\`id\`, \`name\`) VALUES (1, 'warga'), (2, 'petugas');
INSERT INTO \`role_user\` (\`user_id\`, \`role_id\`) VALUES (1, 1), (1, 2);
INSERT INTO \`users\` (\`id\`, \`username\`) VALUES (1, 'mixed');
`), /single-role decision/);
});
