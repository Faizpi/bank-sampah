import { readFile, writeFile, mkdir } from 'node:fs/promises';
import { dirname } from 'node:path';
import { createHash } from 'node:crypto';

// Parse literals only. Never execute SQL, schema, triggers, or expressions from a dump.
export function parseInsertData(sql) {
    const tables = {};
    for (const match of sql.matchAll(/^INSERT INTO `([^`]+)`\s*\(([^)]+)\) VALUES\s*/gm)) {
        const columns = [...match[2].matchAll(/`([^`]+)`/g)].map((column) => column[1]);
        let position = match.index + match[0].length;
        const whitespace = () => { while (position < sql.length && /\s/.test(sql[position])) position++; };
        const literal = () => {
            whitespace();
            if (sql[position] === "'") {
                position++;
                let value = '';
                while (position < sql.length) {
                    const character = sql[position++];
                    if (character === '\\') {
                        const escaped = sql[position++];
                        value += ({ 0: '\0', n: '\n', r: '\r', t: '\t', Z: '\x1a' })[escaped] ?? escaped;
                    } else if (character === "'") {
                        if (sql[position] !== "'") return value;
                        value += "'";
                        position++;
                    } else value += character;
                }
                throw new Error('Unterminated SQL string.');
            }
            const start = position;
            while (position < sql.length && !/[,)]/.test(sql[position])) position++;
            const value = sql.slice(start, position).trim();
            if (value === 'NULL') return null;
            if (/^-?\d+$/.test(value) && Number.isSafeInteger(Number(value))) return Number(value);
            if (/^-?\d+\.\d+$/.test(value)) return value;
            throw new Error('Only SQL string, numeric, and NULL literals are supported.');
        };
        const rows = [];
        while (true) {
            whitespace();
            if (sql[position++] !== '(') throw new Error('Invalid SQL tuple.');
            const values = columns.map((column, index) => {
                const value = literal();
                whitespace();
                if (sql[position++] !== (index === columns.length - 1 ? ')' : ',')) throw new Error('Invalid SQL separator.');
                return value;
            });
            rows.push(Object.fromEntries(columns.map((column, index) => [column, values[index]])));
            whitespace();
            if (sql[position] === ';') break;
            if (sql[position++] !== ',') throw new Error('Invalid SQL tuple separator.');
        }
        tables[match[1]] = [...(tables[match[1]] ?? []), ...rows];
    }
    return tables;
}

export function extractMasterData(sql) {
    const source = parseInsertData(sql);
    const roleNames = new Map((source.roles ?? []).map((role) => [role.id, role.name]));
    const users = (source.users ?? []).map((user) => {
        const roles = [...new Set((source.role_user ?? []).filter((role) => role.user_id === user.id)
            .map((role) => roleNames.get(role.role_id)).map((role) => role === 'superadmin' ? 'admin' : role))];
        if (roles.length !== 1 || !['warga', 'petugas', 'bendahara', 'admin'].includes(roles[0])) {
            throw new Error(`Account ID ${user.id} requires an explicit single-role decision.`);
        }
        return { ...user, role: roles[0] };
    });
    const userRoles = new Map(users.map((user) => [user.id, user.role]));
    const columns = {
        dusun: ['id', 'code', 'name', 'is_active'],
        rw: ['id', 'dusun_id', 'code', 'name', 'is_active'],
        rt: ['id', 'rw_id', 'code', 'name', 'is_active'],
        service_areas: ['id', 'name', 'is_active'],
        service_area_rt: ['service_area_id', 'rt_id'],
        users: ['id', 'name', 'username', 'email', 'email_verified_at', 'password', 'phone', 'status', 'role', 'verified_at', 'rejection_reason', 'terms_version', 'terms_accepted_at', 'deleted_at', 'created_at', 'updated_at'],
        customer_profiles: ['user_id', 'customer_number', 'rt_id', 'address', 'joined_at', 'created_at', 'updated_at'],
        staff_profiles: ['user_id', 'staff_number', 'service_area_id', 'active_from', 'active_to', 'created_at', 'updated_at'],
        staff_service_areas: ['id', 'staff_profile_user_id', 'service_area_id', 'active_from', 'active_to', 'created_at', 'updated_at'],
        waste_categories: ['id', 'code', 'name', 'sort_order', 'is_active'],
        waste_units: ['id', 'code', 'name', 'symbol', 'classification', 'is_active', 'conversion_factor_to_kg'],
        waste_conditions: ['id', 'code', 'name', 'description', 'sort_order', 'is_active'],
        waste_types: ['id', 'waste_category_id', 'waste_unit_id', 'code', 'name', 'education_description', 'sort_order', 'is_plastic', 'is_active'],
        waste_type_conditions: ['waste_type_id', 'waste_condition_id'],
        waste_prices: ['id', 'waste_type_id', 'waste_condition_id', 'price', 'effective_from', 'effective_to', 'created_by', 'rounding_version', 'created_at', 'updated_at'],
    };
    source.users = users;
    source.customer_profiles = (source.customer_profiles ?? []).filter((profile) => userRoles.get(profile.user_id) === 'warga');
    source.staff_profiles = (source.staff_profiles ?? []).filter((profile) => ['petugas', 'bendahara'].includes(userRoles.get(profile.user_id)));
    source.staff_service_areas = (source.staff_service_areas ?? []).filter((area) => source.staff_profiles.some((profile) => profile.user_id === area.staff_profile_user_id));
    const tables = Object.fromEntries(Object.entries(columns).map(([table, fields]) => [table,
        (source[table] ?? []).map((row) => Object.fromEntries(fields.filter((field) => field in row).map((field) => [field, row[field]]))),
    ]));
    return { schema_version: 1, source_sha256: createHash('sha256').update(sql).digest('hex'), tables };
}

if (process.argv[1]?.endsWith('extract-master-data.mjs')) {
    const [source, destination] = process.argv.slice(2);
    if (!source || !destination) throw new Error('Usage: node documents/extract-master-data.mjs <source.sql> <private-output.json>');
    const data = extractMasterData(await readFile(source, 'utf8'));
    await mkdir(dirname(destination), { recursive: true });
    // Exclusive creation prevents accidental replacement of a private source dataset.
    await writeFile(destination, JSON.stringify(data, null, 2) + '\n', { flag: 'wx' });
    console.log(JSON.stringify({ source_sha256: data.source_sha256, counts: Object.fromEntries(Object.entries(data.tables).map(([table, rows]) => [table, rows.length])) }));
}
