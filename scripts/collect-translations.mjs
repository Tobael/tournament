import { readdir, readFile, writeFile } from "node:fs/promises";
import path from "node:path";
const root = path.resolve(import.meta.dirname, "..");
const de = JSON.parse(await readFile(path.join(root, "lang/de.json"), "utf8"));
const en = JSON.parse(await readFile(path.join(root, "lang/en.json"), "utf8"));
const keys = new Set();
async function collect(dir) {
    for (const entry of await readdir(dir, { withFileTypes: true })) {
        const file = path.join(dir, entry.name);
        if (entry.isDirectory()) await collect(file);
        else if (file.endsWith(".js")) {
            const source = await readFile(file, "utf8");
            for (const match of source.matchAll(
                /\bt\(\s*(["'])((?:\\.|(?!\1).)*?)\1\s*[,)]/gs,
            ))
                keys.add(match[2].replace(/\\'/g, "'").replace(/\\"/g, '"'));
        }
    }
}
await collect(path.join(root, "resources/js"));
const missing = [...keys].filter((key) => !(key in de) || !(key in en));
if (missing.length) {
    throw new Error("Missing DE/EN translations: " + missing.join(", "));
}
await writeFile(
    path.join(root, "lang/client-keys.json"),
    JSON.stringify([...keys].sort(), null, 2) + "\n",
);
console.log(
    `${keys.size} browser translation keys checked in German and English.`,
);
