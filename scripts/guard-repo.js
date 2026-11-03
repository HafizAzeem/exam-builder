import { readdir, readFile, stat } from 'node:fs/promises';
import path from 'node:path';

const root = process.cwd();
const skipPrefixes = [
    'node_modules',
    'vendor',
    '.git',
    '.cursor',
    'public/build',
    'storage',
    'bootstrap/cache',
    'scripts/guard-repo.js',
];
const blockedNames = new Set(['init']);
const findings = [];

function normalized(filePath) {
    return filePath.split(path.sep).join('/');
}

function skipped(relativePath) {
    const name = normalized(relativePath);

    return skipPrefixes.some((prefix) => name === prefix || name.startsWith(`${prefix}/`));
}

async function walk(directory) {
    const entries = await readdir(directory, { withFileTypes: true });

    for (const entry of entries) {
        const absolutePath = path.join(directory, entry.name);
        const relativePath = normalized(path.relative(root, absolutePath));

        if (skipped(relativePath)) {
            continue;
        }

        if (entry.isDirectory()) {
            await walk(absolutePath);
            continue;
        }

        if (!entry.isFile()) {
            continue;
        }

        if (entry.name.endsWith('.orc') || blockedNames.has(entry.name)) {
            findings.push(`${relativePath}: blocked file name`);
            continue;
        }

        if (/\.(png|jpe?g|gif|webp|ico|woff2?|ttf|eot|svg|pdf|zip)$/i.test(entry.name)) {
            continue;
        }

        const info = await stat(absolutePath);

        if (info.size > 2_000_000) {
            continue;
        }

        let text;

        try {
            text = await readFile(absolutePath, 'utf8');
        } catch {
            continue;
        }

        if (text.includes('\0')) {
            continue;
        }

        if (text.includes(`function ${'spin'}(`) || text.includes(`${'split'}Lane(`)) {
            findings.push(`${relativePath}: obfuscated startup payload`);
        }

        if (relativePath === 'package.json' && /node\s+init\b/.test(text)) {
            findings.push('package.json: a script runs node init');
        }

        if (relativePath.startsWith('.vscode/') && text.includes('folderOpen')) {
            findings.push(`${relativePath}: an editor task runs when the folder opens`);
        }

        if (relativePath.endsWith('settings.json') && /"task\.allowAutomaticTasks"\s*:\s*true/.test(text)) {
            findings.push(`${relativePath}: automatic editor tasks are enabled`);
        }
    }
}

await walk(root);

if (findings.length > 0) {
    console.error('Refusing to continue. These files match the removed startup payload:');

    for (const finding of findings) {
        console.error(` - ${finding}`);
    }

    process.exit(1);
}
